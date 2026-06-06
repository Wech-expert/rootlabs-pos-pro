<?php

namespace MXPOSPro\API;

defined('ABSPATH') || exit;

use MXPOSPro\Products\ProductIndexRepository;
use MXPOSPro\Products\ProductSearch;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

class ProductSearchController
{
    public function register(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route('mx-pos/v1', '/products/search', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'search'],
            'permission_callback' => [$this, 'permission_callback'],
            'args'                => [
                'q' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function ($value) {
                        $value = trim((string) $value);

                        return mb_strlen($value) >= 2;
                    },
                ],
                'limit' => [
                    'default'           => 20,
                    'sanitize_callback' => function ($value) {
                        return max(1, min(50, absint($value)));
                    },
                ],
            ],
        ]);
    }

    public function search(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $q     = trim(sanitize_text_field($request->get_param('q')));
        $limit = (int) $request->get_param('limit');

        if (mb_strlen($q) > 100) {
            $q = mb_substr($q, 0, 100);
        }

        if (mb_strlen($q) < 2) {
            return new WP_Error(
                'mx_pos_invalid_query',
                __('Search query must be at least 2 characters.', 'mx-pos-pro'),
                ['status' => 400]
            );
        }

        $repository = new ProductIndexRepository();
        $search     = new ProductSearch($repository);

        $results = $search->search($q, $limit);

        $items = [];

        foreach ($results as $row) {
            $items[] = [
                'product_id'     => (int) ($row['product_id'] ?? 0),
                'variation_id'   => isset($row['variation_id']) ? (int) $row['variation_id'] : null,
                'sku'            => $row['sku'] ?? '',
                'name'           => $row['name'] ?? '',
                'type'           => $row['type'] ?? 'simple',
                'stock_quantity' => isset($row['stock_quantity']) ? (int) $row['stock_quantity'] : null,
                'stock_status'   => $row['stock_status'] ?? 'instock',
                'regular_price'  => $row['regular_price'] ?? null,
                'sale_price'     => $row['sale_price'] ?? null,
                'image_url'      => isset($row['image_url']) && $row['image_url'] !== '' ? esc_url_raw((string) $row['image_url']) : null,
                'image_alt'      => $row['image_alt'] ?? '',
            ];
        }

        return rest_ensure_response(['items' => $items]);
    }

    public function permission_callback(): bool
    {
        return current_user_can('mx_pos_access');
    }
}
