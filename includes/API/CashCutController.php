<?php

namespace MXPOSPro\API;

defined('ABSPATH') || exit;

use MXPOSPro\Cash\CashCutRepository;
use MXPOSPro\Cash\CashCutService;
use MXPOSPro\Cash\CashMovementRepository;
use MXPOSPro\Cash\CashSessionRepository;
use MXPOSPro\Cash\CashSessionService;
use MXPOSPro\Sales\RefundRepository;
use MXPOSPro\Sales\SaleRepository;
use MXPOSPro\Sales\TicketService;
use MXPOSPro\Core\RestSecurity;
use MXPOSPro\Audit\AuditLogger;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

class CashCutController
{
    private CashCutService $service;
    private CashCutRepository $cutRepo;

    public function __construct()
    {
        $this->cutRepo   = new CashCutRepository();
        $sessionRepo     = new CashSessionRepository();
        $movementRepo    = new CashMovementRepository();
        $sessionSvc      = new CashSessionService($sessionRepo, $movementRepo);
        $saleRepo        = new SaleRepository();
        $refundRepo      = new RefundRepository();
        $ticketSvc       = new TicketService();

        $this->service = new CashCutService(
            $this->cutRepo,
            $sessionSvc,
            $movementRepo,
            $saleRepo,
            $refundRepo,
            $ticketSvc
        );
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route('mx-pos/v1', '/sessions/(?P<id>\d+)/cuts/x', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'generate_x'],
            'permission_callback' => [$this, 'permission_cut'],
            'args'                => [
                'id' => [
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function ($value) {
                        return absint($value) > 0;
                    },
                ],
            ],
        ]);

        register_rest_route('mx-pos/v1', '/sessions/(?P<id>\d+)/cuts/z', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'generate_z'],
            'permission_callback' => [$this, 'permission_cut_mutation'],
            'args'                => [
                'id' => [
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function ($value) {
                        return absint($value) > 0;
                    },
                ],
            ],
        ]);

        register_rest_route('mx-pos/v1', '/cuts', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_cuts'],
            'permission_callback' => [$this, 'permission_cut'],
            'args'                => [
                'page' => [
                    'default'           => 1,
                    'sanitize_callback' => 'absint',
                ],
                'per_page' => [
                    'default'           => 20,
                    'sanitize_callback' => 'absint',
                ],
                'session_id' => [
                    'default'           => null,
                    'sanitize_callback' => function ($value) {
                        return $value !== null && $value !== '' ? absint($value) : null;
                    },
                ],
                'cut_type' => [
                    'default'           => null,
                    'sanitize_callback' => function ($value) {
                        if ($value === 'X' || $value === 'Z') {
                            return $value;
                        }

                        return null;
                    },
                ],
                'date_from' => [
                    'default'           => null,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'date_to' => [
                    'default'           => null,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route('mx-pos/v1', '/cuts/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_cut'],
            'permission_callback' => [$this, 'permission_cut'],
            'args'                => [
                'id' => [
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function ($value) {
                        return absint($value) > 0;
                    },
                ],
            ],
        ]);

        register_rest_route('mx-pos/v1', '/cuts/(?P<id>\d+)/ticket', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_cut_ticket'],
            'permission_callback' => [$this, 'permission_cut'],
            'args'                => [
                'id' => [
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function ($value) {
                        return absint($value) > 0;
                    },
                ],
            ],
        ]);
    }

    public function get_cuts(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $filters = [];
        $sessionId = $request->get_param('session_id');
        $cutType   = $request->get_param('cut_type');
        $dateFrom  = $request->get_param('date_from');
        $dateTo    = $request->get_param('date_to');

        if ($sessionId !== null) {
            $filters['session_id'] = (int) $sessionId;
        }

        if ($cutType !== null && in_array($cutType, ['X', 'Z'], true)) {
            $filters['cut_type'] = $cutType;
        }

        if ($dateFrom !== null && $dateFrom !== '') {
            $filters['date_from'] = $dateFrom;
        }

        if ($dateTo !== null && $dateTo !== '') {
            $filters['date_to'] = $dateTo;
        }

        $page    = (int) $request->get_param('page');
        $perPage = (int) $request->get_param('per_page');

        $result = $this->cutRepo->list_all($filters, $page, $perPage);

        return rest_ensure_response($result);
    }

    public function generate_x(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $sessionId = (int) $request->get_param('id');

        $result = $this->service->generate_x($sessionId, get_current_user_id());

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response($result);
    }

    public function generate_z(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $sessionId = (int) $request->get_param('id');

        $result = $this->service->generate_z($sessionId, get_current_user_id());

        if (is_wp_error($result)) {
            return $result;
        }

        $response = rest_ensure_response($result);
        $response->set_status(201);

        return $response;
    }

    public function get_cut(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $cutId = (int) $request->get_param('id');

        $cut = $this->service->get_cut_by_id($cutId);

        if ($cut === null) {
            return new WP_Error(
                'mx_pos_cut_not_found',
                __('Cut not found.', 'mx-pos-pro'),
                ['status' => 404]
            );
        }

        return rest_ensure_response(['cut' => $cut]);
    }

    public function get_cut_ticket(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $cutId = (int) $request->get_param('id');

        $html = $this->service->get_cut_ticket_html($cutId);

        if (is_wp_error($html)) {
            return $html;
        }

        $cut = $this->cutRepo->get_by_id($cutId);

        AuditLogger::log('cash_cut_ticket_reprinted', [
            'cut_id'        => $cutId,
            'session_id'    => is_array($cut) ? (int) $cut['session_id'] : 0,
            'cut_type'      => is_array($cut) ? $cut['cut_type'] : '',
            'reprinted_by'  => get_current_user_id(),
        ]);

        return new WP_REST_Response(['html' => $html], 200);
    }

    public function permission_cut(): bool
    {
        return current_user_can('mx_pos_cash_cut');
    }

    public function permission_cut_mutation(WP_REST_Request $request): bool|\WP_Error
    {
        return RestSecurity::verify_mutation($request, 'mx_pos_cash_cut');
    }
}
