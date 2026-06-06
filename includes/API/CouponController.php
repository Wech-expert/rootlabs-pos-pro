<?php

namespace MXPOSPro\API;

defined('ABSPATH') || exit;

use MXPOSPro\Coupons\CouponLookupService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

class CouponController
{
    private CouponLookupService $service;

    public function __construct()
    {
        $this->service = new CouponLookupService();
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route('mx-pos/v1', '/coupons/search', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'search'],
            'permission_callback' => [$this, 'permission_check'],
            'args'                => [
                'q' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function ($value) {
                        return is_string($value) && mb_strlen(trim((string) $value)) >= 2;
                    },
                ],
                'limit' => [
                    'required'          => false,
                    'type'              => 'integer',
                    'default'           => 10,
                    'sanitize_callback' => function ($value) {
                        return max(1, min(CouponLookupService::MAX_LIMIT, absint($value)));
                    },
                ],
            ],
        ]);
    }

    public function search(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $q     = (string) $request->get_param('q');
        $limit = (int) $request->get_param('limit');

        $result = $this->service->search($q, $limit);

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response($result);
    }

    public function permission_check(): bool
    {
        return current_user_can('mx_pos_sell');
    }
}
