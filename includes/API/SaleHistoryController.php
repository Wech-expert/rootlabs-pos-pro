<?php

namespace MXPOSPro\API;

defined('ABSPATH') || exit;

use MXPOSPro\Sales\SaleHistoryRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

class SaleHistoryController
{
    private SaleHistoryRepository $repository;

    public function __construct()
    {
        $this->repository = new SaleHistoryRepository();
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route('mx-pos/v1', '/sales/history/cashiers', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'cashiers'],
            'permission_callback' => [$this, 'permission_check'],
        ]);

        register_rest_route('mx-pos/v1', '/sales/history', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'history'],
            'permission_callback' => [$this, 'permission_check'],
            'args'                => $this->get_history_args(),
        ]);

        register_rest_route('mx-pos/v1', '/sales/lookup', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'lookup'],
            'permission_callback' => [$this, 'permission_check'],
            'args'                => [
                'query' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function ($value) {
                        return is_string($value) && trim($value) !== '';
                    },
                ],
            ],
        ]);

        register_rest_route('mx-pos/v1', '/sales/(?P<id>\d+)/detail', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'detail'],
            'permission_callback' => [$this, 'permission_check'],
            'args'                => [
                'id' => [
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function ($value) {
                        return is_numeric($value) && (int) $value > 0;
                    },
                ],
            ],
        ]);
    }

    private function get_history_args(): array
    {
        return [
            'page' => [
                'required'          => false,
                'type'              => 'integer',
                'default'           => 1,
                'sanitize_callback' => 'absint',
                'validate_callback' => function ($value) {
                    return (int) $value >= 1;
                },
            ],
            'per_page' => [
                'required'          => false,
                'type'              => 'integer',
                'default'           => 20,
                'sanitize_callback' => 'absint',
                'validate_callback' => function ($value) {
                    $v = (int) $value;
                    return $v >= 1 && $v <= 100;
                },
            ],
            'date_from' => [
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => function ($value) {
                    if ($value === '' || $value === null) {
                        return true;
                    }

                    return (bool) \DateTime::createFromFormat('Y-m-d', $value);
                },
            ],
            'date_to' => [
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => function ($value) {
                    if ($value === '' || $value === null) {
                        return true;
                    }

                    return (bool) \DateTime::createFromFormat('Y-m-d', $value);
                },
            ],
            'status' => [
                'required'          => false,
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => function ($value) {
                    if ($value === '' || $value === null) {
                        return true;
                    }

                    $allowed = ['pending', 'completed', 'partially_refunded', 'cancelled', 'refunded'];

                    return in_array($value, $allowed, true);
                },
            ],
            'cashier_id' => [
                'required'          => false,
                'type'              => ['integer', 'null'],
                'sanitize_callback' => function ($value) {
                    if ($value === null || $value === '' || (int) $value <= 0) {
                        return null;
                    }

                    return absint($value);
                },
            ],
            'search' => [
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'session_id' => [
                'required'          => false,
                'type'              => ['integer', 'null'],
                'sanitize_callback' => function ($value) {
                    if ($value === null || $value === '' || (int) $value <= 0) {
                        return null;
                    }

                    return absint($value);
                },
            ],
        ];
    }

    public function history(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $userId   = get_current_user_id();
        $page     = (int) ($request->get_param('page') ?: 1);
        $perPage  = (int) ($request->get_param('per_page') ?: 20);
        $dateFrom = $request->get_param('date_from');
        $dateTo   = $request->get_param('date_to');
        $status   = $request->get_param('status');
        $cashierId = $request->get_param('cashier_id');
        $search   = $request->get_param('search');
        $sessionId = $request->get_param('session_id');

        if (! empty($dateFrom) && ! empty($dateTo) && $dateFrom > $dateTo) {
            return new WP_Error(
                'mx_pos_invalid_date_range',
                __('date_from must not be later than date_to.', 'mx-pos-pro'),
                ['status' => 400]
            );
        }

        $filters = array_filter([
            'date_from'  => $dateFrom !== null && $dateFrom !== '' ? $dateFrom : null,
            'date_to'    => $dateTo !== null && $dateTo !== '' ? $dateTo : null,
            'status'     => $status !== null && $status !== '' ? $status : null,
            'cashier_id' => $cashierId !== null ? $cashierId : null,
            'search'     => $search !== null && $search !== '' ? $search : null,
            'session_id' => $sessionId !== null ? $sessionId : null,
        ], function ($value) {
            return $value !== null;
        });

        $result = $this->repository->query_paginated($userId, $filters, $page, $perPage);

        return rest_ensure_response([
            'items'      => $result['items'],
            'pagination' => [
                'page'        => $page,
                'per_page'    => $perPage,
                'total'       => $result['total'],
                'total_pages' => max(1, (int) ceil($result['total'] / $perPage)),
            ],
        ]);
    }

    public function detail(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $userId  = get_current_user_id();
        $saleId  = (int) $request->get_param('id');

        $detail = $this->repository->get_detail($saleId, $userId);

        if ($detail === null) {
            return new WP_Error(
                'mx_pos_sale_not_found',
                __('Sale not found.', 'mx-pos-pro'),
                ['status' => 404]
            );
        }

        return rest_ensure_response($detail);
    }

    public function cashiers(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $cashiers = $this->repository->get_distinct_cashiers();

        return rest_ensure_response(['cashiers' => $cashiers]);
    }

    public function lookup(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $userId = get_current_user_id();
        $query  = (string) $request->get_param('query');

        $result = $this->repository->lookup_by_query($userId, $query);

        return rest_ensure_response(['items' => $result]);
    }

    public function permission_check(): bool
    {
        return current_user_can('mx_pos_access');
    }
}
