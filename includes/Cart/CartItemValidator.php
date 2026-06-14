<?php

namespace MXPOSPro\Cart;

defined('ABSPATH') || exit;

class CartItemValidator
{
    /**
     * @param array{product_id: int, variation_id: ?int, quantity: int} $item
     */
    public function validate(array $item): CartValidatedItem
    {
        $product_id   = (int) $item['product_id'];
        $variation_id = isset($item['variation_id']) ? (int) $item['variation_id'] : null;
        $quantity     = (int) $item['quantity'];

        $errors = [];
        $sku    = '';
        $name   = '';
        $unit_price     = '0';
        $line_total     = '0';
        $line_subtotal  = '0.0000';
        $line_discount_total = '0.0000';
        $manual_discount = null;
        $stock_status   = '';
        $stock_quantity = null;

        if ($product_id < 1) {
            $errors[] = __('Invalid product ID.', 'mx-pos-pro');

            return new CartValidatedItem(
                $product_id, $variation_id, $sku, $name, $quantity,
                $unit_price, $line_total, $stock_status, $stock_quantity,
                false, $errors
            );
        }

        if ($variation_id !== null && $variation_id > 0) {
            $variation = wc_get_product($variation_id);

            if (! $variation || ! $variation->exists()) {
                $errors[] = __('Variation not found.', 'mx-pos-pro');

                return new CartValidatedItem(
                    $product_id, $variation_id, $sku, $name, $quantity,
                    $unit_price, $line_total, $stock_status, $stock_quantity,
                    false, $errors
                );
            }

            if (! $variation->is_type('variation')) {
                $errors[] = __('The specified variation ID does not belong to a variation.', 'mx-pos-pro');

                return new CartValidatedItem(
                    $product_id, $variation_id, $sku, $name, $quantity,
                    $unit_price, $line_total, $stock_status, $stock_quantity,
                    false, $errors
                );
            }

            $parent_id = $variation->get_parent_id();

            if ($parent_id !== $product_id) {
                $errors[] = __('Variation does not belong to the specified parent product.', 'mx-pos-pro');

                return new CartValidatedItem(
                    $product_id, $variation_id, $sku, $name, $quantity,
                    $unit_price, $line_total, $stock_status, $stock_quantity,
                    false, $errors
                );
            }

            $sellable = $variation;
        } else {
            $product = wc_get_product($product_id);

            if (! $product || ! $product->exists()) {
                $errors[] = __('Product not found.', 'mx-pos-pro');

                return new CartValidatedItem(
                    $product_id, $variation_id, $sku, $name, $quantity,
                    $unit_price, $line_total, $stock_status, $stock_quantity,
                    false, $errors
                );
            }

            if ($product->is_type('variable')) {
                $errors[] = __('Cannot sell a variable product directly. Please select a variation.', 'mx-pos-pro');

                return new CartValidatedItem(
                    $product_id, $variation_id, $sku, $name, $quantity,
                    $unit_price, $line_total, $stock_status, $stock_quantity,
                    false, $errors
                );
            }

            $sellable = $product;
        }

        if ($sellable->get_status() !== 'publish') {
            $errors[] = __('Product is not published.', 'mx-pos-pro');

            return new CartValidatedItem(
                $product_id, $variation_id, $sku, $name, $quantity,
                $unit_price, $line_total, $stock_status, $stock_quantity,
                false, $errors
            );
        }

        if (! $sellable->is_purchasable()) {
            $errors[] = __('Product is not purchasable.', 'mx-pos-pro');

            return new CartValidatedItem(
                $product_id, $variation_id, $sku, $name, $quantity,
                $unit_price, $line_total, $stock_status, $stock_quantity,
                false, $errors
            );
        }

        $sku            = (string) $sellable->get_sku();
        $name           = (string) $sellable->get_name();

        if ($variation_id !== null && $variation_id > 0) {
            $parent_product = wc_get_product($product_id);

            if ($parent_product && $parent_product->exists()) {
                $parent_name    = trim((string) $parent_product->get_name());
                $variation_name = trim((string) $sellable->get_name());

                if ($parent_name !== '' && $variation_name !== '' && stripos($variation_name, $parent_name) === false) {
                    $name = $parent_name . ' - ' . $variation_name;
                }
            }
        }

        $mx_pos_compose_variation_cart_name = true;
        $stock_status   = (string) $sellable->get_stock_status();
        $stock_quantity = $sellable->get_stock_quantity();

        if ($stock_status === 'outofstock') {
            $errors[] = __('Product is out of stock.', 'mx-pos-pro');
        }

        if (count($errors) === 0 && $sellable->managing_stock() && $stock_quantity !== null && $stock_quantity < $quantity) {
            $errors[] = sprintf(
                /* translators: %d: requested quantity */
                __('Insufficient stock. Available: %d.', 'mx-pos-pro'),
                $stock_quantity
            );
        }

        $unit_price = $this->getCanonicalPrice($sellable);
        $line_subtotal = $this->formatDecimal((float) $unit_price * $quantity);
        $line_total = $line_subtotal;

        $discountResult = $this->validate_manual_discount(
            isset($item['manual_discount']) && is_array($item['manual_discount']) ? $item['manual_discount'] : null,
            (float) $line_subtotal
        );

        if (is_wp_error($discountResult)) {
            $errors[] = $discountResult->get_error_message();
        } else {
            $manual_discount = $discountResult['manual_discount'];
            $line_discount_total = $discountResult['line_discount_total'];
            $line_total = $this->formatDecimal(max(0, (float) $line_subtotal - (float) $line_discount_total));
        }

        $valid = count($errors) === 0;

        return new CartValidatedItem(
            $product_id, $variation_id, $sku, $name, $quantity,
            $unit_price, $line_total, $stock_status,
            $stock_quantity !== '' ? $stock_quantity : null,
            $valid, $errors,
            $line_subtotal,
            $line_discount_total,
            $manual_discount
        );
    }


    private function validate_manual_discount(?array $discount, float $lineSubtotal): array|\WP_Error
    {
        if ($discount === null) {
            return [
                'manual_discount' => null,
                'line_discount_total' => '0.0000',
            ];
        }

        if (
            ! current_user_can('mx_pos_apply_discount')
            && ! current_user_can('manage_woocommerce')
            && ! current_user_can('manage_options')
        ) {
            return new \WP_Error(
                'mx_pos_discount_forbidden',
                __('You do not have permission to apply discounts.', 'mx-pos-pro')
            );
        }

        $type = isset($discount['type']) && is_string($discount['type'])
            ? sanitize_text_field($discount['type'])
            : '';

        if (! in_array($type, ['percentage', 'fixed'], true)) {
            return new \WP_Error(
                'mx_pos_invalid_line_discount',
                __('Invalid line discount type.', 'mx-pos-pro')
            );
        }

        $value = isset($discount['value']) && is_numeric($discount['value'])
            ? (float) $discount['value']
            : 0.0;

        if ($value <= 0) {
            return new \WP_Error(
                'mx_pos_invalid_line_discount',
                __('Line discount value must be greater than zero.', 'mx-pos-pro')
            );
        }

        if ($type === 'percentage' && $value > 100) {
            return new \WP_Error(
                'mx_pos_invalid_line_discount',
                __('Percentage discount cannot exceed 100%.', 'mx-pos-pro')
            );
        }

        $reason = isset($discount['reason']) && is_string($discount['reason'])
            ? trim(sanitize_text_field($discount['reason']))
            : '';

        if ($reason === '' || strlen($reason) < 3) {
            return new \WP_Error(
                'mx_pos_invalid_line_discount',
                __('Line discount reason is required.', 'mx-pos-pro')
            );
        }

        if ($type === 'percentage') {
            $amount = $lineSubtotal * ($value / 100);
        } else {
            if ($value > $lineSubtotal) {
                return new \WP_Error(
                    'mx_pos_invalid_line_discount',
                    __('Fixed discount cannot exceed the line subtotal.', 'mx-pos-pro')
                );
            }

            $amount = $value;
        }

        $amount = min($lineSubtotal, max(0, $amount));
        $amountStr = number_format($amount, 4, '.', '');

        return [
            'manual_discount' => [
                'type'   => $type,
                'value'  => number_format($value, 2, '.', ''),
                'reason' => $reason,
                'amount' => $amountStr,
            ],
            'line_discount_total' => $amountStr,
        ];
    }
    private function getCanonicalPrice(\WC_Product $product): string
    {
        $price = $product->get_price('edit');

        if ($price !== '' && $price !== null && (float) $price >= 0) {
            return $this->formatDecimal((float) $price);
        }

        $sale_price = $product->get_sale_price('edit');

        if ($sale_price !== '' && $sale_price !== null && (float) $sale_price > 0) {
            return $this->formatDecimal((float) $sale_price);
        }

        $regular_price = $product->get_regular_price('edit');

        if ($regular_price !== '' && $regular_price !== null && (float) $regular_price > 0) {
            return $this->formatDecimal((float) $regular_price);
        }

        return '0.0000';
    }

    private function formatDecimal(float $value): string
    {
        return number_format($value, 4, '.', '');
    }
}
