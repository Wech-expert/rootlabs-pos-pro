<?php

namespace MXPOSPro\API;

defined('ABSPATH') || exit;

use MXPOSPro\Cash\CashMovementRepository;
use MXPOSPro\Cash\CashMovementService;
use MXPOSPro\Cash\CashSessionRepository;
use MXPOSPro\Cash\CashSessionService;
use MXPOSPro\Auth\POSAuthService;
use MXPOSPro\Entities\EmployeeRepository;
use MXPOSPro\Core\RestSecurity;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

class CashMovementController
{
    private CashMovementService $service;
    private POSAuthService $posAuthService;

    public function __construct()
    {
        $sessionRepo  = new CashSessionRepository();
        $movementRepo = new CashMovementRepository();
        $sessionSvc   = new CashSessionService($sessionRepo, $movementRepo);

        $this->service        = new CashMovementService($movementRepo, $sessionSvc);
        $this->posAuthService = new POSAuthService(new EmployeeRepository());
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route('mx-pos/v1', '/cash-movements/current', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'current'],
            'permission_callback' => [$this, 'permission_current'],
        ]);

        register_rest_route('mx-pos/v1', '/cash-movements', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'create'],
            'permission_callback' => [$this, 'permission_create'],
            'args'                => [
                'movement_type' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function ($value) {
                        return in_array($value, ['cash_in', 'cash_out'], true);
                    },
                ],
                'amount' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => function ($value) {
                        return (string) $value;
                    },
                    'validate_callback' => function ($value) {
                        return is_numeric($value) && (float) $value > 0;
                    },
                ],
                'reason' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => function ($value) {
                        return trim(sanitize_text_field($value));
                    },
                    'validate_callback' => function ($value) {
                        return is_string($value) && mb_strlen(trim($value)) >= 5;
                    },
                ],
                'client_request_id' => [
                    'required'          => false,
                    'type'              => 'string',
                    'maxLength'         => 100,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route('mx-pos/v1', '/cash-movements/(?P<id>\d+)/reverse', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'reverse'],
            'permission_callback' => [$this, 'permission_create'],
            'args'                => [
                'id' => [
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function ($value) {
                        return absint($value) > 0;
                    },
                ],
                'reason' => [
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function ($value) {
                        return is_string($value);
                    },
                ],
            ],
        ]);
    }

    public function current(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $actorId = $this->resolve_actor_id();
        $result = $this->service->get_current_session_movements($actorId);

        return rest_ensure_response($result);
    }

    public function create(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $movement_type     = (string) $request->get_param('movement_type');
        $amount            = (string) $request->get_param('amount');
        $reason            = trim((string) $request->get_param('reason'));
        $client_request_id = $request->get_param('client_request_id');

        if ($reason === '') {
            return new WP_Error(
                'mx_pos_reason_required',
                __('El motivo es obligatorio (mínimo 5 caracteres).', 'mx-pos-pro'),
                ['status' => 400]
            );
        }

        $result = $this->service->create_movement(
            $this->resolve_actor_id(),
            $movement_type,
            $amount,
            $reason,
            $client_request_id !== null ? (string) $client_request_id : null
        );

        if (is_wp_error($result)) {
            return $result;
        }

        $response = rest_ensure_response($result);
        $response->set_status(201);

        return $response;
    }

    public function reverse(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $movement_id = (int) $request->get_param('id');
        $reason      = $request->get_param('reason');

        $result = $this->service->reverse_movement(
            $this->resolve_actor_id(),
            $movement_id,
            $reason !== null ? (string) $reason : null
        );

        if (is_wp_error($result)) {
            return $result;
        }

        $response = rest_ensure_response($result);
        $response->set_status(201);

        return $response;
    }

    private function resolve_actor_id(): int
    {
        $employee = $this->posAuthService->validate();
        return $employee !== null ? (int) $employee['id'] : get_current_user_id();
    }

    public function permission_current(): bool
    {
        return current_user_can('mx_pos_access');
    }

    public function permission_create(WP_REST_Request $request): bool|\WP_Error
    {
        return RestSecurity::verify_mutation($request, 'mx_pos_manage_cash');
    }
}
