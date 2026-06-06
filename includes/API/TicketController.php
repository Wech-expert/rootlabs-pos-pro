<?php

namespace MXPOSPro\API;

defined('ABSPATH') || exit;

use MXPOSPro\Sales\TicketService;
use MXPOSPro\Audit\AuditLogger;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

class TicketController
{
    private TicketService $service;

    public function __construct()
    {
        $this->service = new TicketService();
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route('mx-pos/v1', '/sales/(?P<id>\d+)/ticket', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_ticket'],
            'permission_callback' => [$this, 'permission_ticket'],
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

        register_rest_route('mx-pos/v1', '/sales/(?P<id>\d+)/gift-ticket', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_gift_ticket'],
            'permission_callback' => [$this, 'permission_ticket'],
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

    public function get_ticket(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $sale_id = (int) $request->get_param('id');

        $html = $this->service->generate_ticket_html($sale_id);

        if (is_wp_error($html)) {
            return $html;
        }

        return new WP_REST_Response([
            'html' => $html,
        ], 200);
    }

    public function get_gift_ticket(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $sale_id = (int) $request->get_param('id');

        $html = $this->service->generate_gift_ticket_html($sale_id);

        if (is_wp_error($html)) {
            return $html;
        }

        AuditLogger::log('gift_ticket_printed', [
            'sale_id'     => $sale_id,
            'printed_by'  => get_current_user_id(),
        ]);

        return new WP_REST_Response([
            'html' => $html,
        ], 200);
    }

    public function permission_ticket(): bool
    {
        return current_user_can('mx_pos_sell');
    }
}
