<?php

namespace MXPOSPro\API;

defined('ABSPATH') || exit;

use MXPOSPro\Cash\CashSessionRepository;
use MXPOSPro\Cash\CashSessionService;
use MXPOSPro\Cash\CashMovementRepository;
use MXPOSPro\Auth\POSAuthService;
use MXPOSPro\Entities\EmployeeRepository;
use MXPOSPro\Core\RestSecurity;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

class SessionCloseController
{
    private CashSessionService $service;

    public function __construct()
    {
        $this->service = new CashSessionService(
            new CashSessionRepository(),
            new CashMovementRepository()
        );
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route('mx-pos/v1', '/sessions/(?P<id>\d+)/close', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'close'],
            'permission_callback' => [$this, 'permission_close'],
            'args'                => $this->get_close_args(),
        ]);
    }

    private function get_close_args(): array
    {
        $known_keys = array_keys([
            'bill-1000' => true, 'bill-500' => true, 'bill-200' => true,
            'bill-100'  => true, 'bill-50'  => true, 'bill-20'  => true,
            'coin-20'   => true, 'coin-10'  => true, 'coin-5'   => true,
            'coin-2'    => true, 'coin-1'   => true, 'coin-050' => true,
        ]);

        return [
            'id' => [
                'required'          => true,
                'sanitize_callback' => 'absint',
                'validate_callback' => function ($value) {
                    return absint($value) > 0;
                },
            ],
            'denominations' => [
                'required'          => true,
                'type'              => 'object',
                'sanitize_callback' => function ($value) use ($known_keys) {
                    if (! is_array($value)) {
                        return [];
                    }

                    $sanitized = [];

                    foreach ($value as $key => $qty) {
                        if (! in_array((string) $key, $known_keys, true)) {
                            continue;
                        }

                        $sanitized[(string) $key] = max(0, (int) $qty);
                    }

                    return $sanitized;
                },
                'validate_callback' => function ($value) {
                    if (! is_array($value) || count($value) === 0) {
                        return false;
                    }

                    foreach ($value as $qty) {
                        if (! is_int($qty) || $qty < 0) {
                            return false;
                        }
                    }

                    return true;
                },
            ],
            'close_note' => [
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => function ($value) {
                    return is_string($value);
                },
            ],
        ];
    }

    public function close(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $sessionId     = (int) $request->get_param('id');
        $denominations = (array) $request->get_param('denominations');
        $closeNote     = $request->get_param('close_note');

        /*
         * POS employee identity must take precedence over WordPress identity.
         *
         * When an administrator is logged into wp-admin and also uses the POS
         * employee login, get_current_user_id() returns the WP user ID.
         * Sessions opened through the POS employee flow are owned by
         * pos_employee_id, so closing must use the POS employee ID.
         */
        $actorId = 0;

        try {
            $posAuthService = new POSAuthService(new EmployeeRepository());
            $posEmployee    = $posAuthService->get_current_employee();

            if (is_array($posEmployee) && isset($posEmployee['id']) && (int) $posEmployee['id'] > 0) {
                $actorId = (int) $posEmployee['id'];
            }
        } catch (\Throwable $e) {
            $actorId = 0;
        }

        if ($actorId <= 0) {
            $actorId = get_current_user_id();
        }

        $result = $this->service->close_session(
            $sessionId,
            $actorId,
            $denominations,
            $closeNote !== null ? (string) $closeNote : null
        );

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response($result);
    }

    public function permission_close(WP_REST_Request $request): bool|\WP_Error
    {
        $posAuthService = new POSAuthService(new EmployeeRepository());
        $employee       = $posAuthService->validate();

        if (is_array($employee) && isset($employee['id']) && (int) $employee['id'] > 0) {
            return true;
        }

        return RestSecurity::verify_mutation($request, 'mx_pos_close_session');
    }
}
