<?php

namespace MXPOSPro\API;

defined('ABSPATH') || exit;

use MXPOSPro\Products\ProductIndexRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class ProductCatalogController
{
    public function register(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route('mx-pos/v1', '/products/catalog', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'catalog'],
            'permission_callback' => [$this, 'permission_callback'],
            'args'                => [
                'q' => [
                    'required'          => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'limit' => [
                    'default'           => 24,
                    'sanitize_callback' => function ($value) {
                        return max(1, min(50, absint($value)));
                    },
                ],
            ],
        ]);
    }

    public function catalog(WP_REST_Request $request): WP_REST_Response
    {
        $start = microtime(true);
        $q = trim(sanitize_text_field((string) $request->get_param('q')));

        if (mb_strlen($q) > 100) {
            $q = mb_substr($q, 0, 100);
        }

        $limit = (int) $request->get_param('limit');
        $repository = new ProductIndexRepository();
        $sql_start = microtime(true);
        $rows = mb_strlen($q) >= 2
            ? $repository->search_catalog_rows($q, $limit)
            : $repository->list_catalog_rows($limit);
        $sql_ms = round((microtime(true) - $sql_start) * 1000, 2);

        $items = $this->group_rows($rows);
        $response = rest_ensure_response([
            'items' => $items,
        ]);

        $response->header('Cache-Control', 'private, max-age=30, must-revalidate');
        $response->header('X-MX-POS-Search-Time', (string) round((microtime(true) - $start) * 1000, 2));
        $response->header('X-MX-POS-SQL-Time', (string) $sql_ms);
        $response->header('X-MX-POS-Result-Count', (string) count($items));

        return $response;
    }

    public function permission_callback(): bool
    {
        return current_user_can('mx_pos_access');
    }

    private function group_rows(array $rows): array
    {
        $groups = [];

        foreach ($rows as $row) {
            $group_id = (int) ($row['catalog_group_id'] ?? $row['product_id'] ?? 0);

            if ($group_id <= 0) {
                continue;
            }

            if (! isset($groups[$group_id])) {
                $groups[$group_id] = [
                    'parent'     => null,
                    'variations' => [],
                ];
            }

            if (isset($row['variation_id']) && $row['variation_id'] !== null && (int) $row['variation_id'] > 0) {
                if ((int) ($row['is_purchasable'] ?? 0) === 1) {
                    $groups[$group_id]['variations'][] = $row;
                }
                continue;
            }

            $groups[$group_id]['parent'] = $row;
        }

        $items = [];

        foreach ($groups as $group) {
            $parent = $group['parent'];
            $variations = $group['variations'];

            if (count($variations) > 0) {
                $items[] = $this->format_variable($parent, $variations);
                continue;
            }

            if ($parent !== null && (int) ($parent['is_purchasable'] ?? 0) === 1) {
                $items[] = $this->format_single($parent);
            }
        }

        return $items;
    }

    private function format_single(array $row): array
    {
        return [
            'product_id'     => (int) ($row['product_id'] ?? 0),
            'variation_id'   => isset($row['variation_id']) ? $this->nullable_int($row['variation_id']) : null,
            'type'           => (string) ($row['type'] ?? 'simple'),
            'sku'            => (string) ($row['sku'] ?? ''),
            'name'           => (string) ($row['name'] ?? ''),
            'stock_status'   => (string) ($row['stock_status'] ?? 'instock'),
            'stock_quantity' => $this->nullable_int($row['stock_quantity'] ?? null),
            'regular_price'  => $this->nullable_price($row['regular_price'] ?? null),
            'sale_price'     => $this->nullable_price($row['sale_price'] ?? null),
            'min_price'      => $this->nullable_price($row['min_price'] ?? null),
            'max_price'      => $this->nullable_price($row['max_price'] ?? null),
            'image_url'      => isset($row['image_url']) && $row['image_url'] !== '' ? esc_url_raw((string) $row['image_url']) : null,
            'image_alt'      => (string) ($row['image_alt'] ?? ''),
            'variations'     => [],
        ];
    }

    private function format_variable(?array $parent, array $variation_rows): array
    {
        $first = $parent ?? $variation_rows[0];
        $variations = [];

        foreach ($variation_rows as $row) {
            $variations[] = $this->format_variation($row);
        }

        $price_range = $this->variation_price_range($variations);

        return [
            'product_id'     => (int) ($first['product_id'] ?? $first['catalog_group_id'] ?? 0),
            'variation_id'   => null,
            'type'           => 'variable',
            'sku'            => (string) ($first['sku'] ?? ''),
            'name'           => (string) ($first['name'] ?? $first['parent_name'] ?? ''),
            'stock_status'   => (string) ($first['stock_status'] ?? 'instock'),
            'stock_quantity' => $this->nullable_int($first['stock_quantity'] ?? null),
            'regular_price'  => null,
            'sale_price'     => null,
            'min_price'      => $this->nullable_price($first['min_price'] ?? null) ?? $price_range['min'],
            'max_price'      => $this->nullable_price($first['max_price'] ?? null) ?? $price_range['max'],
            'image_url'      => isset($first['image_url']) && $first['image_url'] !== '' ? esc_url_raw((string) $first['image_url']) : null,
            'image_alt'      => (string) ($first['image_alt'] ?? ''),
            'variations'     => $variations,
        ];
    }

    private function format_variation(array $row): array
    {
        return [
            'product_id'     => (int) ($row['product_id'] ?? 0),
            'variation_id'   => $this->nullable_int($row['variation_id'] ?? null),
            'sku'            => (string) ($row['sku'] ?? ''),
            'name'           => (string) ($row['variation_label'] ?: $row['name'] ?? ''),
            'type'           => 'variation',
            'stock_status'   => (string) ($row['stock_status'] ?? 'instock'),
            'stock_quantity' => $this->nullable_int($row['stock_quantity'] ?? null),
            'regular_price'  => $this->nullable_price($row['regular_price'] ?? null),
            'sale_price'     => $this->nullable_price($row['sale_price'] ?? null),
            'image_url'      => isset($row['image_url']) && $row['image_url'] !== '' ? esc_url_raw((string) $row['image_url']) : null,
            'image_alt'      => (string) ($row['image_alt'] ?? ''),
        ];
    }

    private function variation_price_range(array $variations): array
    {
        $prices = [];

        foreach ($variations as $variation) {
            $price = $variation['sale_price'] ?: $variation['regular_price'];

            if ($price !== null && (float) $price > 0) {
                $prices[] = (float) $price;
            }
        }

        if (count($prices) === 0) {
            return [
                'min' => null,
                'max' => null,
            ];
        }

        return [
            'min' => $this->format_decimal(min($prices)),
            'max' => $this->format_decimal(max($prices)),
        ];
    }

    private function nullable_int(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function nullable_price(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->format_decimal((float) $value);
    }

    private function format_decimal(float $value): string
    {
        return number_format($value, 4, '.', '');
    }
}
