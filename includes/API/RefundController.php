<?php

namespace MXPOSPro\API;

defined('ABSPATH') || exit;

use MXPOSPro\Auth\POSAuthService;
use MXPOSPro\Entities\EmployeeRepository;
use MXPOSPro\Sales\RefundService;
use MXPOSPro\Sales\RefundRepository;
use MXPOSPro\Sales\SaleRepository;
use MXPOSPro\Cash\CashSessionRepository;
use MXPOSPro\Cash\CashSessionService;
use MXPOSPro\Cash\CashMovementRepository;
use MXPOSPro\Cash\CashMovementService;
use MXPOSPro\Core\RestSecurity;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

class RefundController
{
    private RefundService $service;
    private POSAuthService $posAuthService;

    public function __construct()
    {
        $saleRepo            = new SaleRepository();
        $refundRepo          = new RefundRepository();
        $sessionRepo         = new CashSessionRepository();
        $movementRepo        = new CashMovementRepository();
        $sessionService      = new CashSessionService($sessionRepo, $movementRepo);
        $movementService     = new CashMovementService($movementRepo, $sessionService);
        $this->posAuthService = new POSAuthService(new EmployeeRepository());

        $this->service = new RefundService(
            $saleRepo,
            $refundRepo,
            $sessionService,
            $movementService
        );
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route('mx-pos/v1', '/sales/(?P<id>\d+)/cancel', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'cancel'],
            'permission_callback' => [$this, 'permission_check_mutation'],
            'args'                => [
                'id' => [
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function ($value) {
                        return is_numeric($value) && (int) $value > 0;
                    },
                ],
                'reason' => [
                    'required'          => false,
                    'type'              => 'string',
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'client_request_id' => [
                    'required'          => true,
                    'type'              => 'string',
                    'maxLength'         => RefundService::MAX_CLIENT_REQUEST_ID_LENGTH,
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function ($value) {
                        return is_string($value) && trim($value) !== '';
                    },
                ],
            ],
        ]);

        register_rest_route('mx-pos/v1', '/sales/(?P<id>\d+)/refund-options', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'refund_options'],
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

        register_rest_route('mx-pos/v1', '/sales/(?P<id>\d+)/refund', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'refund'],
            'permission_callback' => [$this, 'permission_check_mutation'],
            'args'                => [
                'id' => [
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function ($value) {
                        return is_numeric($value) && (int) $value > 0;
                    },
                ],
                'items' => [
                    'required'          => false,
                    'type'              => 'array',
                    'default'           => [],
                    'sanitize_callback' => function ($items) {
                        if (! is_array($items)) {
                            return [];
                        }

                        return array_values(array_map(function ($item) {
                            if (! is_array($item)) {
                                return [];
                            }

                            return [
                                'order_item_id' => isset($item['order_item_id'])
                                    ? absint($item['order_item_id'])
                                    : 0,
                                'quantity' => isset($item['quantity'])
                                    ? absint($item['quantity'])
                                    : 0,
                            ];
                        }, $items));
                    },
                ],
                'refund_method' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function ($value) {
                        return is_string($value) && in_array($value, ['cash', 'card'], true);
                    },
                ],
                'reason' => [
                    'required'          => false,
                    'type'              => 'string',
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'client_request_id' => [
                    'required'          => true,
                    'type'              => 'string',
                    'maxLength'         => RefundService::MAX_CLIENT_REQUEST_ID_LENGTH,
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function ($value) {
                        return is_string($value) && trim($value) !== '';
                    },
                ],
            ],
        ]);
    }

    public function cancel(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $saleId           = (int) $request->get_param('id');
        $reason           = $request->get_param('reason') ?: '';
        $clientRequestId  = $request->get_param('client_request_id');
        $userId           = $this->resolve_current_user_id();

        if (is_wp_error($userId)) {
            return $userId;
        }

        try {
            $result = $this->service->cancel(
                $userId,
                $saleId,
                $reason,
                $clientRequestId
            );
        } catch (\Throwable $e) {
            return $this->unexpected_error($e, 'cancel', $saleId);
        }

        if (is_wp_error($result)) {
            return $result;
        }

        $response = rest_ensure_response($result);

        return $response;
    }

    public function refund_options(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $saleId = (int) $request->get_param('id');

        try {
            $result = $this->service->get_refund_options($saleId);
        } catch (\Throwable $e) {
            return $this->unexpected_error($e, 'refund_options', $saleId);
        }

        if (is_wp_error($result)) {
            return $result;
        }

        $response = rest_ensure_response($result);

        return $response;
    }

    public function refund(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $saleId           = (int) $request->get_param('id');
        $refundItems      = $request->get_param('items') ?: [];
        $refundMethod     = $request->get_param('refund_method');
        $reason           = $request->get_param('reason') ?: '';
        $clientRequestId  = $request->get_param('client_request_id');
        $userId           = $this->resolve_current_user_id();

        if (is_wp_error($userId)) {
            return $userId;
        }

        try {
            $result = $this->service->refund(
                $userId,
                $saleId,
                $refundItems,
                $refundMethod,
                $reason,
                $clientRequestId
            );
        } catch (\Throwable $e) {
            return $this->unexpected_error($e, 'refund', $saleId);
        }

        if (is_wp_error($result)) {
            return $result;
        }

        $response = rest_ensure_response($result);

        return $response;
    }

    public function permission_check(): bool
    {
        return current_user_can('mx_pos_refund');
    }

    public function permission_check_mutation(WP_REST_Request $request): bool|\WP_Error
    {
        return RestSecurity::verify_mutation($request, 'mx_pos_refund');
    }

    private function resolve_current_user_id(): int|WP_Error
    {
        $employee = $this->posAuthService->get_current_employee();

        if (is_array($employee) && isset($employee['id'])) {
            $employeeId = (int) $employee['id'];

            if ($employeeId > 0) {
                return $employeeId;
            }
        }

        $userId = get_current_user_id();

        if ($userId > 0) {
            return $userId;
        }

        $cookieUserId = (int) wp_validate_auth_cookie('', 'logged_in');

        if ($cookieUserId > 0) {
            wp_set_current_user($cookieUserId);

            return $cookieUserId;
        }

        return new WP_Error(
            'mx_pos_invalid_user',
            __('Sesión no válida. Vuelve a iniciar sesión antes de procesar la devolución.', 'mx-pos-pro'),
            ['status' => 403]
        );
    }

    private function unexpected_error(\Throwable $e, string $context, int $saleId): WP_Error
    {

        return new WP_Error(
            'mx_pos_refund_unexpected_error',
            __('No se pudo procesar la devolución. Intenta nuevamente o revisa el log.', 'mx-pos-pro'),
            ['status' => 500]
        );
    }
}
