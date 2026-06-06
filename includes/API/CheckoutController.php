<?php

namespace MXPOSPro\API;

defined('ABSPATH') || exit;

use MXPOSPro\Sales\CheckoutService;
use MXPOSPro\Auth\POSAuthService;
use MXPOSPro\Entities\EmployeeRepository;
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
use MXPOSPro\Payments\PaymentMethodRepository;
use MXPOSPro\Payments\OrderPaymentRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

class CheckoutController
{
    private CheckoutService $service;

    public function __construct()
    {
        $saleRepo           = new SaleRepository();
        $sessionRepo        = new CashSessionRepository();
        $movementRepo       = new CashMovementRepository();
        $sessionService     = new CashSessionService($sessionRepo, $movementRepo);
        $cashMovementService = new CashMovementService($movementRepo, $sessionService);
        $itemValidator      = new CartItemValidator();
        $discountValidator  = new CartDiscountValidator();
        $customerService    = new CustomerLookupService();
        $orderFactory       = new WooOrderFactory();
        $parkedRepo         = new ParkedCartRepository();
        $methodRepo         = new PaymentMethodRepository();
        $orderPaymentRepo   = new OrderPaymentRepository();

        $this->service = new CheckoutService(
            $saleRepo,
            $sessionService,
            $cashMovementService,
            $itemValidator,
            $discountValidator,
            $customerService,
            $orderFactory,
            $parkedRepo,
            $methodRepo,
            $orderPaymentRepo
        );
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route('mx-pos/v1', '/checkout/complete', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'complete'],
            'permission_callback' => [$this, 'permission_checkout'],
            'args'                => [
                'items' => [
                    'required'          => true,
                    'type'              => 'array',
                    'minItems'          => 1,
                    'maxItems'          => CheckoutService::MAX_ITEMS,
                    'sanitize_callback' => function ($items) {
                        if (! is_array($items)) {
                            return [];
                        }

                        return array_values(array_map(function ($item) {
                            if (! is_array($item)) {
                                return [
                                    'product_id'   => 0,
                                    'variation_id'  => null,
                                    'quantity'      => 0,
                                ];
                            }

                            return [
                                'product_id'     => isset($item['product_id']) ? absint($item['product_id']) : 0,
                                'variation_id'   => isset($item['variation_id']) && $item['variation_id'] !== null
                                    ? absint($item['variation_id'])
                                    : null,
                                'quantity'       => isset($item['quantity'])
                                    ? absint($item['quantity'])
                                    : 0,
                            ];
                        }, $items));
                    },
                    'validate_callback' => function ($items) {
                        if (! is_array($items) || count($items) === 0) {
                            return false;
                        }

                        foreach ($items as $item) {
                            if (! is_array($item)) {
                                return false;
                            }

                            if (! isset($item['product_id']) || (int) $item['product_id'] < 1) {
                                return false;
                            }

                            if (! isset($item['quantity']) || (int) $item['quantity'] < 1) {
                                return false;
                            }
                        }

                        return true;
                    },
                ],
                'customer_id' => [
                    'required'          => false,
                    'type'              => ['integer', 'null'],
                    'sanitize_callback' => function ($value) {
                        if ($value === null || $value === '' || (int) $value <= 0) {
                            return null;
                        }

                        return absint($value);
                    },
                ],
                'discount' => [
                    'required'          => false,
                    'type'              => ['object', 'null'],
                    'sanitize_callback' => function ($discount) {
                        if ($discount === null || ! is_array($discount)) {
                            return null;
                        }

                        return [
                            'type'   => isset($discount['type']) ? sanitize_text_field($discount['type']) : '',
                            'value'  => isset($discount['value']) ? (string) $discount['value'] : '',
                            'reason' => isset($discount['reason']) ? sanitize_text_field($discount['reason']) : '',
                        ];
                    },
                ],
                'parked_cart_id' => [
                    'required'          => false,
                    'type'              => ['integer', 'null'],
                    'sanitize_callback' => function ($value) {
                        if ($value === null || $value === '' || (int) $value <= 0) {
                            return null;
                        }

                        return absint($value);
                    },
                ],
                'client_request_id' => [
                    'required'          => true,
                    'type'              => 'string',
                    'maxLength'         => CheckoutService::MAX_CLIENT_REQUEST_ID_LENGTH,
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function ($value) {
                        return is_string($value) && trim($value) !== '';
                    },
                ],
                'coupon_code' => [
                    'required'          => false,
                    'type'              => ['string', 'null'],
                    'sanitize_callback' => function ($value) {
                        if ($value === null || $value === '') {
                            return null;
                        }
                        return sanitize_text_field((string) $value);
                    },
                ],
                'payment_lines' => [
                    'required'          => true,
                    'type'              => 'array',
                    'minItems'          => 1,
                    'maxItems'          => CheckoutService::MAX_PAYMENT_LINES,
                    'sanitize_callback' => function ($lines) {
                        if (! is_array($lines)) {
                            return [];
                        }

                        return array_values(array_map(function ($line) {
                            if (! is_array($line)) {
                                return [
                                    'method'    => '',
                                    'amount'    => 0,
                                    'reference' => null,
                                ];
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
                    'validate_callback' => function ($lines) {
                        if (! is_array($lines) || count($lines) === 0) {
                            return false;
                        }

                        foreach ($lines as $line) {
                            if (! is_array($line)) {
                                return false;
                            }
                            if (! isset($line['method']) || trim((string) $line['method']) === '') {
                                return false;
                            }
                            if (! isset($line['amount']) || (float) $line['amount'] <= 0) {
                                return false;
                            }
                        }

                        return true;
                    },
                ],
            ],
        ]);
    }

    public function complete(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $items           = $request->get_param('items');
        $customerId      = $request->get_param('customer_id');
        $discount        = $request->get_param('discount');
        $parkedCartId    = $request->get_param('parked_cart_id');
        $clientRequestId = $request->get_param('client_request_id');
        $couponCode      = $request->get_param('coupon_code');
        $paymentLines    = $request->get_param('payment_lines');

        $payload = [
            'items'             => $items,
            'customer_id'       => $customerId,
            'discount'          => $discount,
            'parked_cart_id'    => $parkedCartId,
            'client_request_id' => $clientRequestId,
            'coupon_code'       => $couponCode,
            'payment_lines'     => $paymentLines,
        ];

        $result = $this->service->execute(
            $this->resolve_pos_actor_id(),
            $payload
        );

        if (is_wp_error($result)) {
            return $result;
        }

        $response = rest_ensure_response($result);
        $response->set_status(201);

        return $response;
    }

    public function permission_checkout(WP_REST_Request $request): bool|\WP_Error
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
