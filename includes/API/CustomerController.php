<?php

namespace MXPOSPro\API;

defined('ABSPATH') || exit;

use MXPOSPro\Customers\CustomerLookupService;
use MXPOSPro\Core\RestSecurity;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

class CustomerController
{
    private CustomerLookupService $service;

    public function __construct()
    {
        $this->service = new CustomerLookupService();
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route('mx-pos/v1', '/customers/search', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'search'],
            'permission_callback' => [$this, 'permission_search'],
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
                    'default'           => 20,
                    'sanitize_callback' => function ($value) {
                        return max(1, min(CustomerLookupService::MAX_LIMIT, absint($value)));
                    },
                ],
            ],
        ]);

        register_rest_route('mx-pos/v1', '/customers/lookup', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'lookup'],
            'permission_callback' => [$this, 'permission_search'],
            'args'                => [
                'email' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_email',
                    'validate_callback' => function ($value) {
                        return is_string($value) && is_email($value);
                    },
                ],
            ],
        ]);

        register_rest_route('mx-pos/v1', '/customers', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'create'],
            'permission_callback' => [$this, 'permission_create'],
            'args'                => [
                'name' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function ($value) {
                        $trimmed = trim((string) $value);
                        return mb_strlen($trimmed) >= 2 && mb_strlen($trimmed) <= 150;
                    },
                ],
                'email' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_email',
                    'validate_callback' => function ($value) {
                        return is_string($value) && is_email($value);
                    },
                ],
                'phone' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function ($value) {
                        $trimmed = trim((string) $value);
                        return mb_strlen($trimmed) >= 5 && mb_strlen($trimmed) <= 30;
                    },
                ],
            ],
        ]);

        register_rest_route('mx-pos/v1', '/customers/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => [$this, 'update'],
            'permission_callback' => [$this, 'permission_create'],
            'args'                => [
                'id' => [
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function ($value) {
                        return is_numeric($value) && (int) $value > 0;
                    },
                ],
                'name' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function ($value) {
                        $trimmed = trim((string) $value);
                        return mb_strlen($trimmed) >= 2 && mb_strlen($trimmed) <= 150;
                    },
                ],
                'phone' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function ($value) {
                        $trimmed = trim((string) $value);
                        return mb_strlen($trimmed) >= 5 && mb_strlen($trimmed) <= 30;
                    },
                ],
            ],
        ]);

        register_rest_route('mx-pos/v1', '/customers/(?P<id>\d+)/purchases', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'purchases'],
            'permission_callback' => [$this, 'permission_search'],
            'args'                => [
                'id' => [
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function ($value) {
                        return is_numeric($value) && (int) $value > 0;
                    },
                ],
                'limit' => [
                    'required'          => false,
                    'type'              => 'integer',
                    'default'           => 10,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function ($value) {
                        $v = (int) $value;
                        return $v >= 1 && $v <= CustomerLookupService::MAX_PURCHASE_LIMIT;
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

    public function lookup(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $email = (string) $request->get_param('email');

        $customer = $this->service->lookup_by_email($email);

        if ($customer === null) {
            return new WP_Error(
                'mx_pos_customer_not_found',
                __('Customer not found.', 'mx-pos-pro'),
                ['status' => 404]
            );
        }

        return rest_ensure_response($customer);
    }

    public function create(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $name  = (string) $request->get_param('name');
        $email = (string) $request->get_param('email');
        $phone = (string) $request->get_param('phone');

        $result = $this->service->create($name, $email, $phone);

        if (is_wp_error($result)) {
            return $result;
        }

        $response = rest_ensure_response($result);
        $response->set_status(201);

        return $response;
    }

    public function update(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $customerId = (int) $request->get_param('id');
        $name       = (string) $request->get_param('name');
        $phone      = (string) $request->get_param('phone');

        $result = $this->service->update($customerId, $name, $phone);

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response($result);
    }

    public function purchases(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $customerId = (int) $request->get_param('id');
        $limit      = (int) $request->get_param('limit');

        $result = $this->service->get_purchase_history($customerId, $limit);

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response($result);
    }

    public function permission_search(): bool
    {
        return current_user_can('mx_pos_sell');
    }

    public function permission_create(WP_REST_Request $request): bool|WP_Error
    {
        return RestSecurity::verify_mutation($request, 'mx_pos_sell');
    }
}
