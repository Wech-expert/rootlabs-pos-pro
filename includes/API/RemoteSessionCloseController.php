<?php

namespace MXPOSPro\API;

defined('ABSPATH') || exit;

use MXPOSPro\Cash\CashSessionRepository;
use MXPOSPro\Cash\CashSessionService;
use MXPOSPro\Cash\CashMovementRepository;
use MXPOSPro\Core\RestSecurity;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

class RemoteSessionCloseController
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
        register_rest_route('mx-pos/v1', '/sessions/(?P<id>\d+)/remote-close', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'remote_close'],
            'permission_callback' => [$this, 'permission_remote_close'],
            'args'                => $this->get_remote_close_args(),
        ]);
    }

    private function get_remote_close_args(): array
    {
        return [
            'id' => [
                'required'          => true,
                'sanitize_callback' => 'absint',
                'validate_callback' => function ($value) {
                    return absint($value) > 0;
                },
            ],
            'remote_reason' => [
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => function ($value) {
                    return is_string($value) && trim($value) !== '';
                },
            ],
        ];
    }

    public function remote_close(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $sessionId    = (int) $request->get_param('id');
        $remoteReason = (string) $request->get_param('remote_reason');
        $adminUserId  = get_current_user_id();

        $result = $this->service->close_session_remote(
            $sessionId,
            $adminUserId,
            $remoteReason
        );

        if (is_wp_error($result)) {
            return $result;
        }

        $response = rest_ensure_response($result);
        $response->set_status(200);

        return $response;
    }

    public function permission_remote_close(WP_REST_Request $request): bool|WP_Error
    {
        return RestSecurity::verify_mutation($request, 'manage_options');
    }
}
