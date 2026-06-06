<?php

namespace MXPOSPro\API;

defined('ABSPATH') || exit;

use MXPOSPro\Sales\PaymentService;
use MXPOSPro\Auth\POSAuthService;
use MXPOSPro\Entities\EmployeeRepository;
use MXPOSPro\Sales\SaleService;
use MXPOSPro\Sales\SaleRepository;
use MXPOSPro\Sales\WooOrderFactory;
use MXPOSPro\Cart\CartItemValidator;
use MXPOSPro\Cart\CartDiscountValidator;
use MXPOSPro\Cart\ParkedCartRepository;
use MXPOSPro\Cash\CashSessionRepository;
use MXPOSPro\Cash\CashSessionService;
use MXPOSPro\Cash\CashMovementRepository;
use MXPOSPro\Cash\CashMovementService;
use MXPOSPro\Core\RestSecurity;
use MXPOSPro\Customers\CustomerLookupService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

class PaymentController
{
    private PaymentService $service;

    public function __construct()
    {
        $saleRepo         = new SaleRepository();
        $sessionRepo      = new CashSessionRepository();
        $movementRepo     = new CashMovementRepository();
        $sessionService   = new CashSessionService($sessionRepo, $movementRepo);
        $cashMovementService = new CashMovementService($movementRepo, $sessionService);

        $this->service = new PaymentService(
            $saleRepo,
            $sessionService,
            $cashMovementService
        );
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route('mx-pos/v1', '/sales/(?P<id>\d+)/pay', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'create'],
            'permission_callback' => [$this, 'permission_pay'],
            'args'                => [
                'id' => [
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function ($value) {
                        return is_numeric($value) && (int) $value > 0;
                    },
                ],
                'payment_method' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function ($value) {
                        return is_string($value) && trim($value) !== '';
                    },
                ],
                'amount_received' => [
                    'required'          => false,
                    'type'              => ['number', 'null'],
                    'sanitize_callback' => function ($value) {
                        if ($value === null || $value === '') {
                            return null;
                        }

                        return (float) $value;
                    },
                ],
                'card_reference' => [
                    'required'          => false,
                    'type'              => ['string', 'null'],
                    'maxLength'         => 100,
                    'sanitize_callback' => function ($value) {
                        if ($value === null || $value === '') {
                            return null;
                        }

                        return sanitize_text_field($value);
                    },
                ],
                'payment_lines' => [
                    'required'          => false,
                    'type'              => ['array', 'null'],
                    'sanitize_callback' => function ($lines) {
                        if ($lines === null || ! is_array($lines)) {
                            return null;
                        }

                        return array_values(array_map(function ($line) {
                            if (! is_array($line)) {
                                return [];
                            }

                            return [
                                'method'    => isset($line['method']) ? sanitize_text_field($line['method']) : '',
                                'amount'    => isset($line['amount']) ? (float) $line['amount'] : 0,
                                'reference' => isset($line['reference']) && $line['reference'] !== '' && $line['reference'] !== null
                                    ? sanitize_text_field($line['reference'])
                                    : null,
                            ];
                        }, $lines));
                    },
                ],
                'client_request_id' => [
                    'required'          => true,
                    'type'              => 'string',
                    'maxLength'         => PaymentService::MAX_PAYMENT_CLIENT_REQUEST_ID_LENGTH,
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function ($value) {
                        return is_string($value) && trim($value) !== '';
                    },
                ],
            ],
        ]);
    }

    public function create(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $saleId             = (int) $request->get_param('id');
        $paymentMethod      = $request->get_param('payment_method');
        $amountReceived     = $request->get_param('amount_received');
        $cardReference      = $request->get_param('card_reference');
        $clientRequestId    = $request->get_param('client_request_id');
        $paymentLines       = $request->get_param('payment_lines');

        $payload = [
            'payment_method'    => $paymentMethod,
            'amount_received'   => $amountReceived,
            'card_reference'    => $cardReference,
            'client_request_id' => $clientRequestId,
            'payment_lines'    => $paymentLines,
        ];

        $result = $this->service->process_payment(
            $this->resolve_pos_actor_id(),
            $saleId,
            $payload
        );

        if (is_wp_error($result)) {
            return $result;
        }

        $response = rest_ensure_response($result);
        $response->set_status(200);

        return $response;
    }

    public function permission_pay(WP_REST_Request $request): bool|\WP_Error
    {
        return RestSecurity::verify_mutation($request, 'mx_pos_sell');
    }

    private function resolve_pos_actor_id(): int
    {
        try {
            $posAuthService = new POSAuthService(new EmployeeRepository());
            $posEmployee    = $posAuthService->get_current_employee();

            if (is_array($posEmployee) && isset($posEmployee['id'])) {
                $posEmployeeId = (int) $posEmployee['id'];

                if ($posEmployeeId > 0) {
                    return $posEmployeeId;
                }
            }
        } catch (\Throwable $e) {
            // Fallback to WordPress user below.
        }

        $wpUserId = get_current_user_id();

        return $wpUserId > 0 ? $wpUserId : 0;
    }

}
