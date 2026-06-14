<?php

namespace MXPOSPro\Cart;

defined('ABSPATH') || exit;

class CartValidatedItem
{
    public int $product_id;
    public ?int $variation_id;
    public string $sku;
    public string $name;
    public int $quantity;
    public string $unit_price;
    public string $line_total;
    public string $line_subtotal;
    public string $line_discount_total;
    public ?array $manual_discount;
    public string $stock_status;
    public ?int $stock_quantity;
    public bool $valid;
    public array $errors;

    public function __construct(
        int $product_id,
        ?int $variation_id,
        string $sku,
        string $name,
        int $quantity,
        string $unit_price,
        string $line_total,
        string $stock_status,
        ?int $stock_quantity,
        bool $valid,
        array $errors,
        string $line_subtotal = '',
        string $line_discount_total = '0.0000',
        ?array $manual_discount = null
    ) {
        $this->product_id     = $product_id;
        $this->variation_id   = $variation_id;
        $this->sku            = $sku;
        $this->name           = $name;
        $this->quantity       = $quantity;
        $this->unit_price     = $unit_price;
        $this->line_total     = $line_total;
        $this->line_subtotal  = $line_subtotal !== '' ? $line_subtotal : $line_total;
        $this->line_discount_total = $line_discount_total;
        $this->manual_discount = $manual_discount;
        $this->stock_status   = $stock_status;
        $this->stock_quantity = $stock_quantity;
        $this->valid          = $valid;
        $this->errors         = $errors;
    }

    public function to_array(): array
    {
        return [
            'product_id'     => $this->product_id,
            'variation_id'   => $this->variation_id,
            'sku'            => $this->sku,
            'name'           => $this->name,
            'quantity'       => $this->quantity,
            'unit_price'     => $this->unit_price,
            'line_subtotal'  => $this->line_subtotal !== '' ? $this->line_subtotal : $this->line_total,
            'line_total'     => $this->line_total,
            'line_discount_total' => $this->line_discount_total,
            'manual_discount' => $this->manual_discount,
            'stock_status'   => $this->stock_status,
            'stock_quantity' => $this->stock_quantity,
            'valid'          => $this->valid,
            'errors'         => $this->errors,
        ];
    }
}
