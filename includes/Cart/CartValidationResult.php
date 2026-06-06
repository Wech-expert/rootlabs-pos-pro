<?php

namespace MXPOSPro\Cart;

defined('ABSPATH') || exit;

class CartValidationResult
{
    public bool $valid;
    public array $items;
    public ?array $discount;
    public ?array $coupon;
    public ?string $coupon_error;
    public array $totals;
    public array $errors;

    public function __construct(
        bool $valid,
        array $items,
        ?array $discount,
        ?array $coupon,
        ?string $coupon_error,
        array $totals,
        array $errors
    ) {
        $this->valid        = $valid;
        $this->items        = $items;
        $this->discount     = $discount;
        $this->coupon       = $coupon;
        $this->coupon_error = $coupon_error;
        $this->totals       = $totals;
        $this->errors       = $errors;
    }

    public function to_array(): array
    {
        $result = [
            'valid'    => $this->valid,
            'items'    => array_map(function ($item) {
                return $item->to_array();
            }, $this->items),
            'discount' => $this->discount,
            'totals'   => $this->totals,
            'errors'   => $this->errors,
        ];

        if ($this->coupon !== null) {
            $result['coupon'] = $this->coupon;
        }

        if ($this->coupon_error !== null) {
            $result['coupon_error'] = $this->coupon_error;
        }

        return $result;
    }
}
