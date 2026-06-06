<?php

namespace MXPOSPro\API;

defined('ABSPATH') || exit;

use MXPOSPro\Auth\POSAuthService;
use MXPOSPro\Cash\CashSessionRepository;
use MXPOSPro\Cash\CashSessionService;
use MXPOSPro\Cash\CashMovementRepository;
use MXPOSPro\Core\RestSecurity;
use MXPOSPro\Entities\BranchRepository;
use MXPOSPro\Entities\EmployeeRepository;
use MXPOSPro\Entities\RegisterRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

class CashSessionController
{
    private CashSessionService $service;
    private POSAuthService $posAuthService;

    public function __construct()
    {
        $this->service = new CashSessionService(
            new CashSessionRepository(),
            new CashMovementRepository()
        );
        $this->posAuthService = new POSAuthService(new EmployeeRepository());
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route('mx-pos/v1', '/sessions/current', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'current'],
            'permission_callback' => [$this, 'permission_current'],
        ]);

        register_rest_route('mx-pos/v1', '/sessions/open', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'open'],
            'permission_callback' => [$this, 'permission_open'],
            'args'                => [
                'opening_amount' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => function ($value) {
                        return (string) $value;
                    },
                    'validate_callback' => function ($value) {
                        return is_numeric($value) && (float) $value >= 0;
                    },
                ],
            ],
        ]);
    }

    public function current(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $employee = $this->posAuthService->validate();

        if ($employee !== null) {
            $result = $this->service->get_current_session_for_pos_employee(
                (int) $employee['id']
            );

            return rest_ensure_response($result);
        }

        $result = $this->service->get_current_session(get_current_user_id());

        return rest_ensure_response($result);
    }

    public function open(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $opening_amount = (string) $request->get_param('opening_amount');
        $employee       = $this->posAuthService->validate();

        if ($employee !== null) {
            $selected_register = $this->posAuthService->get_selected_register();

            if ($selected_register === null) {
                return new WP_Error(
                    'mx_pos_register_required',
                    __('No hay una caja seleccionada para abrir sesión.', 'mx-pos-pro'),
                    ['status' => 409]
                );
            }

            $result = $this->service->open_session_for_pos_employee(
                (int) $employee['id'],
                (int) $selected_register['pos_register_id'],
                (int) $selected_register['branch_id'],
                $opening_amount
            );

            if (is_wp_error($result)) {
                return $result;
            }

            $this->posAuthService->clear_selected_register();

            return rest_ensure_response(['session' => $result]);
        }

        $result = $this->service->open_session(
            get_current_user_id(),
            $opening_amount
        );

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response(['session' => $result]);
    }

    public function permission_current(): bool
    {
        return $this->posAuthService->validate() !== null
            || current_user_can('mx_pos_access');
    }

    public function permission_open(WP_REST_Request $request): bool|\WP_Error
    {
        $employee = $this->posAuthService->validate();

        if ($employee !== null) {
            return $this->verify_pos_open_permission($request, $employee);
        }

        if (is_user_logged_in()) {
            return RestSecurity::verify_mutation($request, 'mx_pos_open_session');
        }

        return new WP_Error(
            'mx_pos_forbidden',
            __('No tienes permiso para abrir esta caja. Verifica empleado, caja o sesión.', 'mx-pos-pro'),
            ['status' => 403]
        );
    }

    private function verify_pos_open_permission(WP_REST_Request $request, array $employee): bool|WP_Error
    {
        $nonce = $request->get_header('X-WP-Nonce');

        if ($nonce === null || $nonce === '') {
            return new WP_Error(
                'mx_pos_invalid_nonce',
                __('Missing X-WP-Nonce header.', 'mx-pos-pro'),
                ['status' => 403]
            );
        }

        if (! wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error(
                'mx_pos_invalid_nonce',
                __('Invalid nonce.', 'mx-pos-pro'),
                ['status' => 403]
            );
        }

        if ((int) ($employee['id'] ?? 0) <= 0) {
            return new WP_Error(
                'mx_pos_forbidden',
                __('No tienes permiso para abrir esta caja. Verifica empleado, caja o sesión.', 'mx-pos-pro'),
                ['status' => 403]
            );
        }

        $selected_register = $this->posAuthService->get_selected_register();

        if ($selected_register === null) {
            return new WP_Error(
                'mx_pos_register_required',
                __('No hay una caja seleccionada para abrir sesión.', 'mx-pos-pro'),
                ['status' => 409]
            );
        }

        $register = (new RegisterRepository())->get_by_id(
            (int) $selected_register['pos_register_id']
        );

        if ($register === null || ! (int) $register['is_active']) {
            return new WP_Error(
                'mx_pos_register_inactive',
                __('La caja seleccionada no está activa.', 'mx-pos-pro'),
                ['status' => 403]
            );
        }

        if ((int) $register['branch_id'] !== (int) $selected_register['branch_id']) {
            return new WP_Error(
                'mx_pos_register_branch_mismatch',
                __('La caja no pertenece a la sucursal indicada.', 'mx-pos-pro'),
                ['status' => 403]
            );
        }

        $branch = (new BranchRepository())->get_by_id(
            (int) $selected_register['branch_id']
        );

        if ($branch === null || ! (int) $branch['is_active']) {
            return new WP_Error(
                'mx_pos_branch_inactive',
                __('La sucursal no está activa.', 'mx-pos-pro'),
                ['status' => 403]
            );
        }

        return true;
    }
}
