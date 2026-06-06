<?php

namespace MXPOSPro\API;

defined('ABSPATH') || exit;

use MXPOSPro\Cart\ParkedCartRepository;
use MXPOSPro\Cart\ParkedCartService;
use MXPOSPro\Cash\CashSessionRepository;
use MXPOSPro\Cash\CashSessionService;
use MXPOSPro\Cash\CashMovementRepository;
use MXPOSPro\Core\RestSecurity;
use MXPOSPro\Auth\POSAuthService;
use MXPOSPro\Entities\EmployeeRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

class ParkedCartController
{
    private ParkedCartService $service;

    public function __construct()
    {
        $sessionRepo = new CashSessionRepository();
        $movementRepo = new CashMovementRepository();
        $sessionSvc  = new CashSessionService($sessionRepo, $movementRepo);
        $cartRepo    = new ParkedCartRepository();

        $this->service = new ParkedCartService($cartRepo, $sessionSvc);
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route('mx-pos/v1', '/parked-carts/current', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'list_current'],
            'permission_callback' => [$this, 'permission_list'],
        ]);

        register_rest_route('mx-pos/v1', '/parked-carts', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'create'],
            'permission_callback' => [$this, 'permission_create'],
            'args'                => [
                'label' => [
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'customer_id' => [
                    'required'          => false,
                    'type'              => 'integer',
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
                'items' => [
                    'required'          => true,
                    'type'              => 'array',
                    'minItems'          => 1,
                    'maxItems'          => ParkedCartService::MAX_ITEMS,
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
            ],
        ]);

        register_rest_route('mx-pos/v1', '/parked-carts/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_single'],
            'permission_callback' => [$this, 'permission_get_single'],
            'args'                => [
                'id' => [
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function ($value) {
                        return $value > 0;
                    },
                ],
            ],
        ]);

        register_rest_route('mx-pos/v1', '/parked-carts/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => [$this, 'delete'],
            'permission_callback' => [$this, 'permission_delete'],
            'args'                => [
                'id' => [
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function ($value) {
                        return $value > 0;
                    },
                ],
            ],
        ]);
    }

    public function list_current(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $result = $this->service->list_current_session_carts($this->resolve_pos_actor_id());

        return rest_ensure_response($result);
    }

    public function create(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $items       = $request->get_param('items');
        $label       = $request->get_param('label');
        $customer_id = $request->get_param('customer_id');
        $discount    = $request->get_param('discount');
        $couponCode  = $request->get_param('coupon_code');

        $couponData = null;

        if ($couponCode !== null && $couponCode !== '') {
            $couponData = [
                'code' => $couponCode,
            ];
        }

        $result = $this->service->create_parked_cart(
            $this->resolve_pos_actor_id(),
            $items,
            $label !== null ? (string) $label : null,
            $customer_id !== null ? (int) $customer_id : null,
            $discount !== null ? (array) $discount : null,
            $couponData
        );

        if (is_wp_error($result)) {
            return $result;
        }

        $response = rest_ensure_response($result);
        $response->set_status(201);

        return $response;
    }

    public function get_single(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = (int) $request->get_param('id');

        $result = $this->service->get_parked_cart($this->resolve_pos_actor_id(), $id);

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response($result);
    }

    public function delete(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = (int) $request->get_param('id');

        $result = $this->service->cancel_parked_cart($this->resolve_pos_actor_id(), $id);

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response($result);
    }

    public function permission_list(): bool
    {
        return current_user_can('mx_pos_access');
    }

    public function permission_create(WP_REST_Request $request): bool|\WP_Error
    {
        return RestSecurity::verify_mutation($request, 'mx_pos_sell');
    }

    public function permission_get_single(): bool
    {
        return current_user_can('mx_pos_access');
    }

    public function permission_delete(WP_REST_Request $request): bool|\WP_Error
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
