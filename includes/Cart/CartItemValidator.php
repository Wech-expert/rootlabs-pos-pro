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
        $line_total = $this->formatDecimal((float) $unit_price * $quantity);

        $valid = count($errors) === 0;

        return new CartValidatedItem(
            $product_id, $variation_id, $sku, $name, $quantity,
            $unit_price, $line_total, $stock_status,
            $stock_quantity !== '' ? $stock_quantity : null,
            $valid, $errors
        );
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
