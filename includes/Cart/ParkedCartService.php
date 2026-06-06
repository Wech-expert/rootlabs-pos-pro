<?php

namespace MXPOSPro\Cart;

defined('ABSPATH') || exit;

use MXPOSPro\Cash\CashSessionService;
use MXPOSPro\Customers\CustomerLookupService;
use WP_Error;

class ParkedCartService
{
    public const MAX_ITEMS = 100;

    private ParkedCartRepository $repo;
    private CashSessionService $sessionService;

    public function __construct(
        ParkedCartRepository $repo,
        CashSessionService $sessionService
    ) {
        $this->repo            = $repo;
        $this->sessionService  = $sessionService;
    }

    public function list_current_session_carts(int $user_id): array
    {
        $sessionResult = $this->sessionService->get_current_session($user_id);

        if (! $sessionResult['has_open_session'] || $sessionResult['session'] === null) {
            return [
                'has_open_session' => false,
                'items'            => [],
            ];
        }

        $sessionId = (int) $sessionResult['session']['id'];
        $rows      = $this->repo->list_by_session($sessionId);

        return [
            'has_open_session' => true,
            'items'            => $this->map_summaries($rows),
        ];
    }

    public function create_parked_cart(
        int $user_id,
        array $items,
        ?string $label = null,
        ?int $customer_id = null,
        ?array $discount = null,
        ?array $coupon = null
    ): array|WP_Error {
        if ($user_id <= 0) {
            return new WP_Error(
                'mx_pos_invalid_user',
                __('Invalid user.', 'mx-pos-pro'),
                ['status' => 400]
            );
        }

        if (! is_array($items) || count($items) === 0) {
            return new WP_Error(
                'mx_pos_empty_cart',
                __('Cart must have at least one item.', 'mx-pos-pro'),
                ['status' => 400]
            );
        }

        if (count($items) > self::MAX_ITEMS) {
            return new WP_Error(
                'mx_pos_too_many_items',
                sprintf(
                    /* translators: %d: max items */
                    __('Maximum %d items allowed.', 'mx-pos-pro'),
                    self::MAX_ITEMS
                ),
                ['status' => 400]
            );
        }

        $sessionResult = $this->sessionService->get_current_session($user_id);

        if (! $sessionResult['has_open_session'] || $sessionResult['session'] === null) {
            return new WP_Error(
                'mx_pos_no_open_session',
                __('No open session found. Open a session before parking carts.', 'mx-pos-pro'),
                ['status' => 409]
            );
        }

        $sessionId = (int) $sessionResult['session']['id'];

        $customerSnapshot = null;

        if ($customer_id !== null && $customer_id > 0) {
            $customerService = new CustomerLookupService();
            $customer        = $customerService->get_by_id($customer_id);

            if ($customer === null) {
                return new WP_Error(
                    'mx_pos_customer_not_found',
                    __('Customer not found.', 'mx-pos-pro'),
                    ['status' => 400]
                );
            }

            $customerSnapshot = [
                'id'           => $customer['id'],
                'display_name' => $customer['display_name'],
                'email'        => $customer['email'],
                'phone'        => $customer['phone'],
            ];
        }

        $rawItems = $this->sanitizeItems($items);
        $validatedItems = [];
        $allValid = true;
        $validationErrors = [];
        $validator = new CartItemValidator();

        foreach ($rawItems as $item) {
            $result = $validator->validate($item);

            if (! $result->valid) {
                $allValid = false;
                $validationErrors[] = $result->errors;
            }

            $validatedItems[] = $result->to_array();
        }

        if (! $allValid) {
            $flatErrors = array_merge([], ...$validationErrors);

            return new WP_Error(
                'mx_pos_invalid_items',
                implode(' ', $flatErrors),
                ['status' => 400]
            );
        }

        $subtotal = 0.0;

        foreach ($validatedItems as $vi) {
            $subtotal += (float) $vi['line_total'];
        }

        $subtotal_str       = $this->formatDecimal($subtotal);
        $discount_total_str = '0.0000';
        $validated_discount  = null;

        if ($discount !== null) {
            $discountValidator = new CartDiscountValidator();
            $discountResult    = $discountValidator->validate(
                $discount,
                $subtotal_str,
                $user_id
            );

            if (is_wp_error($discountResult)) {
                return $discountResult;
            }

            $validated_discount  = $discountResult['discount'];
            $discount_total_str  = $discountResult['discount_total'];
        }

        $total_str = $this->formatDecimal((float) $subtotal_str - (float) $discount_total_str);

        $totals = [
            'subtotal'       => $subtotal_str,
            'discount_total' => $discount_total_str,
            'total'          => $total_str,
        ];

        $cartDataArr = [
            'items'           => $rawItems,
            'validated_items' => $validatedItems,
            'totals'          => $totals,
        ];

        if ($customerSnapshot !== null) {
            $cartDataArr['customer'] = $customerSnapshot;
        }

        if ($validated_discount !== null) {
            $cartDataArr['discount'] = $validated_discount;
        }

        if ($coupon !== null) {
            $cartDataArr['coupon'] = $coupon;
        }

        $cartData = wp_json_encode($cartDataArr);

        $cartHash = hash(
            'sha256',
            $sessionId . ':' . $user_id . ':' . microtime(true) . ':' . wp_json_encode($rawItems) . ':' . ($customer_id ?? 'none')
        );
        $cartHash = substr($cartHash, 0, 64);

        $labelValue = $label !== null && $label !== '' ? trim($label) : null;

        $data = [
            'session_id'   => $sessionId,
            'cashier_id'   => $user_id,
            'customer_id'  => $customer_id ?? null,
            'cart_hash'    => $cartHash,
            'cart_data'    => $cartData,
            'note'         => $labelValue,
            'status'       => 'parked',
            'created_at'   => current_time('mysql'),
            'updated_at'   => current_time('mysql'),
        ];

        $cart = $this->repo->create($data);

        $parkedCarts = $this->list_current_session_carts($user_id);

        return [
            'cart'         => $this->map_summary($cart),
            'parked_carts' => $parkedCarts['items'],
        ];
    }

    public function get_parked_cart(int $user_id, int $parked_cart_id): array|WP_Error
    {
        $sessionResult = $this->sessionService->get_current_session($user_id);

        if (! $sessionResult['has_open_session'] || $sessionResult['session'] === null) {
            return new WP_Error(
                'mx_pos_no_open_session',
                __('No open session found.', 'mx-pos-pro'),
                ['status' => 409]
            );
        }

        $sessionId = (int) $sessionResult['session']['id'];
        $cart      = $this->repo->get_by_id($parked_cart_id);

        if ($cart === null) {
            return new WP_Error(
                'mx_pos_parked_cart_not_found',
                __('Parked cart not found.', 'mx-pos-pro'),
                ['status' => 404]
            );
        }

        if ((int) $cart['session_id'] !== $sessionId) {
            return new WP_Error(
                'mx_pos_parked_cart_forbidden',
                __('This parked cart belongs to a different session.', 'mx-pos-pro'),
                ['status' => 403]
            );
        }

        if ($cart['status'] !== 'parked') {
            return new WP_Error(
                'mx_pos_parked_cart_not_available',
                __('This parked cart is no longer available.', 'mx-pos-pro'),
                ['status' => 404]
            );
        }

        $cartData = json_decode($cart['cart_data'], true);

        if (! is_array($cartData) || ! isset($cartData['items']) || ! is_array($cartData['items'])) {
            return new WP_Error(
                'mx_pos_parked_cart_corrupted',
                __('Parked cart data is corrupted.', 'mx-pos-pro'),
                ['status' => 500]
            );
        }

        $validator = new CartItemValidator();
        $revalidatedItems = [];
        $revalidationErrors = [];

        foreach ($cartData['items'] as $item) {
            $result = $validator->validate($item);
            $revalidatedItems[] = $result->to_array();

            if (! $result->valid) {
                $revalidationErrors[] = $result->errors;
            }
        }

        $subtotal = 0.0;

        foreach ($revalidatedItems as $ri) {
            if (empty($ri['errors'])) {
                $subtotal += (float) $ri['line_total'];
            }
        }

        $subtotal_str       = $this->formatDecimal($subtotal);
        $discount_total_str = '0.0000';
        $restored_discount   = null;

        if (isset($cartData['discount']) && is_array($cartData['discount'])) {
            $discountValidator = new CartDiscountValidator();
            $discountResult    = $discountValidator->validate(
                $cartData['discount'],
                $subtotal_str,
                $user_id
            );

            if (is_wp_error($discountResult)) {
                return new WP_Error(
                    'mx_pos_parked_cart_discount_invalid',
                    __('The discount saved with this cart is no longer valid.', 'mx-pos-pro'),
                    ['status' => 409]
                );
            }

            $restored_discount  = $discountResult['discount'];
            $discount_total_str = $discountResult['discount_total'];
        }

        $total_str = $this->formatDecimal((float) $subtotal_str - (float) $discount_total_str);

        $totals = [
            'subtotal'       => $subtotal_str,
            'discount_total' => $discount_total_str,
            'total'          => $total_str,
        ];

        return [
            'cart' => [
                'id'       => (int) $cart['id'],
                'label'    => $cart['note'] ?? null,
                'customer' => $cartData['customer'] ?? null,
                'discount' => $restored_discount,
                'coupon'   => $cartData['coupon'] ?? null,
                'items'    => $revalidatedItems,
                'totals'   => $totals,
            ],
        ];
    }

    public function cancel_parked_cart(int $user_id, int $parked_cart_id): array|WP_Error
    {
        $sessionResult = $this->sessionService->get_current_session($user_id);

        if (! $sessionResult['has_open_session'] || $sessionResult['session'] === null) {
            return new WP_Error(
                'mx_pos_no_open_session',
                __('No open session found.', 'mx-pos-pro'),
                ['status' => 409]
            );
        }

        $sessionId = (int) $sessionResult['session']['id'];
        $cart      = $this->repo->get_by_id($parked_cart_id);

        if ($cart === null) {
            return new WP_Error(
                'mx_pos_parked_cart_not_found',
                __('Parked cart not found.', 'mx-pos-pro'),
                ['status' => 404]
            );
        }

        if ((int) $cart['session_id'] !== $sessionId) {
            return new WP_Error(
                'mx_pos_parked_cart_forbidden',
                __('This parked cart belongs to a different session.', 'mx-pos-pro'),
                ['status' => 403]
            );
        }

        if ($cart['status'] !== 'parked') {
            return new WP_Error(
                'mx_pos_parked_cart_not_available',
                __('This parked cart is no longer available.', 'mx-pos-pro'),
                ['status' => 404]
            );
        }

        $cancelled = $this->repo->cancel($parked_cart_id);

        if (! $cancelled) {
            return new WP_Error(
                'mx_pos_parked_cart_cancel_failed',
                __('Failed to cancel parked cart.', 'mx-pos-pro'),
                ['status' => 500]
            );
        }

        return ['deleted' => true];
    }

    private function sanitizeItems(array $items): array
    {
        return array_values(array_map(function ($item) {
            return [
                'product_id'   => isset($item['product_id']) ? absint($item['product_id']) : 0,
                'variation_id' => isset($item['variation_id']) && $item['variation_id'] !== null
                    ? absint($item['variation_id'])
                    : null,
                'quantity'     => isset($item['quantity']) ? absint($item['quantity']) : 0,
            ];
        }, $items));
    }

    private function map_summaries(array $rows): array
    {
        return array_map([$this, 'map_summary'], $rows);
    }

    private function map_summary(array $row): array
    {
        $cartData = json_decode($row['cart_data'], true);
        $itemCount = 0;
        $total     = '0.0000';

        if (is_array($cartData)) {
            if (isset($cartData['validated_items']) && is_array($cartData['validated_items'])) {
                $itemCount = count($cartData['validated_items']);
            }

            if (isset($cartData['totals']['total'])) {
                $total = $cartData['totals']['total'];
            }
        }

        $customerLabel = null;

        if (is_array($cartData) && isset($cartData['customer']['display_name'])) {
            $customerLabel = $cartData['customer']['display_name'];
        }

        return [
            'id'             => (int) $row['id'],
            'label'          => $row['note'] ?? null,
            'customer_label' => $customerLabel,
            'item_count'     => $itemCount,
            'total'          => $total,
            'created_at'     => $row['created_at'],
        ];
    }

    private function formatDecimal(float $value): string
    {
        return number_format($value, 4, '.', '');
    }
}
