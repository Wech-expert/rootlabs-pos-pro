<?php

namespace MXPOSPro\API;

defined('ABSPATH') || exit;

use MXPOSPro\Sales\SaleService;
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
use MXPOSPro\Core\RestSecurity;
use MXPOSPro\Customers\CustomerLookupService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

class SaleController
{
    private SaleService $service;

    public function __construct()
    {
        $saleRepo         = new SaleRepository();
        $sessionRepo      = new CashSessionRepository();
        $movementRepo     = new CashMovementRepository();
        $sessionService   = new CashSessionService($sessionRepo, $movementRepo);
        $itemValidator    = new CartItemValidator();
        $discountValidator = new CartDiscountValidator();
        $customerService  = new CustomerLookupService();
        $orderFactory     = new WooOrderFactory();
        $parkedRepo       = new ParkedCartRepository();

        $this->service = new SaleService(
            $saleRepo,
            $sessionService,
            $itemValidator,
            $discountValidator,
            $customerService,
            $orderFactory,
            $parkedRepo
        );
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route('mx-pos/v1', '/sales', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'create'],
            'permission_callback' => [$this, 'permission_create'],
            'args'                => [
                'items' => [
                    'required'          => true,
                    'type'              => 'array',
                    'minItems'          => 1,
                    'maxItems'          => SaleService::MAX_ITEMS,
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
                                    'manual_discount' => null,
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
                                'manual_discount' => isset($item['manual_discount']) && is_array($item['manual_discount'])
                                    ? [
                                        'type'   => isset($item['manual_discount']['type']) ? sanitize_text_field((string) $item['manual_discount']['type']) : '',
                                        'value'  => isset($item['manual_discount']['value']) ? (string) $item['manual_discount']['value'] : '',
                                        'reason' => isset($item['manual_discount']['reason']) ? sanitize_text_field((string) $item['manual_discount']['reason']) : '',
                                    ]
                                    : null,
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
                    'maxLength'         => SaleService::MAX_CLIENT_REQUEST_ID_LENGTH,
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
            ],
        ]);
    }

    public function create(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $items            = $request->get_param('items');
        $customerId       = $request->get_param('customer_id');
        $discount         = $request->get_param('discount');
        $parkedCartId     = $request->get_param('parked_cart_id');
        $clientRequestId  = $request->get_param('client_request_id');
        $couponCode       = $request->get_param('coupon_code');

        $payload = [
            'items'             => $items,
            'customer_id'       => $customerId,
            'discount'          => $discount,
            'parked_cart_id'    => $parkedCartId,
            'client_request_id' => $clientRequestId,
            'coupon_code'       => $couponCode,
        ];

        $result = $this->service->create_order_from_cart(
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

    public function permission_create(WP_REST_Request $request): bool|\WP_Error
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
