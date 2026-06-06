<?php

namespace MXPOSPro\Cart;

defined('ABSPATH') || exit;

use WP_Error;

class CartDiscountValidator
{
    public const MAX_REASON_LENGTH = 255;

    public function validate(?array $discount, string $subtotal, int $user_id): array|WP_Error
    {
        if ($discount === null) {
            return [
                'discount'       => null,
                'discount_total' => '0.0000',
            ];
        }

        if (! current_user_can('mx_pos_apply_discount')) {
            return new WP_Error(
                'mx_pos_discount_forbidden',
                __('You do not have permission to apply discounts.', 'mx-pos-pro'),
                ['status' => 403]
            );
        }

        if (! isset($discount['type']) || ! is_string($discount['type'])) {
            return new WP_Error(
                'mx_pos_invalid_discount',
                __('Discount type is required.', 'mx-pos-pro'),
                ['status' => 400]
            );
        }

        $type = $discount['type'];

        if (! in_array($type, ['percentage', 'fixed'], true)) {
            return new WP_Error(
                'mx_pos_invalid_discount',
                __('Discount type must be percentage or fixed.', 'mx-pos-pro'),
                ['status' => 400]
            );
        }

        if (! isset($discount['value']) || ! is_numeric($discount['value'])) {
            return new WP_Error(
                'mx_pos_invalid_discount',
                __('Discount value must be a number.', 'mx-pos-pro'),
                ['status' => 400]
            );
        }

        $value = (float) $discount['value'];

        if ($value <= 0) {
            return new WP_Error(
                'mx_pos_invalid_discount',
                __('Discount value must be greater than zero.', 'mx-pos-pro'),
                ['status' => 400]
            );
        }

        if (! isset($discount['reason']) || ! is_string($discount['reason'])) {
            return new WP_Error(
                'mx_pos_invalid_discount',
                __('Discount reason is required.', 'mx-pos-pro'),
                ['status' => 400]
            );
        }

        $reason = trim($discount['reason']);

        if ($reason === '') {
            return new WP_Error(
                'mx_pos_invalid_discount',
                __('Discount reason cannot be empty.', 'mx-pos-pro'),
                ['status' => 400]
            );
        }

        if (mb_strlen($reason) > self::MAX_REASON_LENGTH) {
            return new WP_Error(
                'mx_pos_invalid_discount',
                sprintf(
                    /* translators: %d: max characters */
                    __('Discount reason must not exceed %d characters.', 'mx-pos-pro'),
                    self::MAX_REASON_LENGTH
                ),
                ['status' => 400]
            );
        }

        $subtotalFloat = (float) $subtotal;

        if ($type === 'percentage') {
            if ($value > 100) {
                return new WP_Error(
                    'mx_pos_invalid_discount',
                    __('Percentage discount cannot exceed 100%.', 'mx-pos-pro'),
                    ['status' => 400]
                );
            }

            $discountTotal = $subtotalFloat * ($value / 100);
        } else {
            if ($value > $subtotalFloat) {
                return new WP_Error(
                    'mx_pos_invalid_discount',
                    __('Fixed discount cannot exceed the subtotal.', 'mx-pos-pro'),
                    ['status' => 400]
                );
            }

            $discountTotal = $value;
        }

        if ($discountTotal > $subtotalFloat) {
            $discountTotal = $subtotalFloat;
        }

        $total = $subtotalFloat - $discountTotal;

        if ($total < 0) {
            $total = 0;
            $discountTotal = $subtotalFloat;
        }

        $discountTotalStr = number_format($discountTotal, 4, '.', '');
        $valueStr         = number_format($value, 4, '.', '');

        return [
            'discount' => [
                'type'   => $type,
                'value'  => $valueStr,
                'reason' => $reason,
                'amount' => $discountTotalStr,
            ],
            'discount_total' => $discountTotalStr,
        ];
    }
}
