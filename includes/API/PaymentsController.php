<?php

namespace MXPOSPro\API;

defined('ABSPATH') || exit;

use MXPOSPro\Payments\PaymentMethodRepository;
use MXPOSPro\Core\RestSecurity;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

class PaymentsController
{
    private PaymentMethodRepository $repo;

    public function __construct()
    {
        $this->repo = new PaymentMethodRepository();
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route('mx-pos/v1', '/payments/methods/active', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'active'],
            'permission_callback' => [$this, 'permission_pos'],
        ]);

        register_rest_route('mx-pos/v1', '/payments/methods', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'list_all'],
            'permission_callback' => [$this, 'permission_admin'],
        ]);

        register_rest_route('mx-pos/v1', '/payments/methods', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'create'],
            'permission_callback' => [$this, 'permission_admin_mutation'],
            'args'                => [
                'name' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'slug' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'payment_type' => [
                    'required'          => false,
                    'type'              => 'string',
                    'default'           => 'other',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'affects_cash_register' => [
                    'required' => false,
                    'type'     => 'boolean',
                    'default'  => false,
                ],
                'allow_reference' => [
                    'required' => false,
                    'type'     => 'boolean',
                    'default'  => false,
                ],
                'card_fee_enabled' => [
                    'required' => false,
                    'type'     => 'boolean',
                    'default'  => false,
                ],
                'card_fee_type' => [
                    'required' => false,
                    'type'     => ['string', 'null'],
                    'default'  => null,
                ],
                'card_fee_value' => [
                    'required' => false,
                    'type'     => ['number', 'null'],
                    'default'  => null,
                ],
                'wc_gateway_id' => [
                    'required' => false,
                    'type'     => ['string', 'null'],
                    'default'  => null,
                ],
                'sort_order' => [
                    'required' => false,
                    'type'     => 'integer',
                    'default'  => 0,
                ],
            ],
        ]);

        register_rest_route('mx-pos/v1', '/payments/methods/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => [$this, 'update'],
            'permission_callback' => [$this, 'permission_admin_mutation'],
            'args'                => [
                'id' => [
                    'required' => true,
                    'type'     => 'integer',
                ],
            ],
        ]);

        register_rest_route('mx-pos/v1', '/payments/methods/(?P<id>\d+)/toggle', [
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => [$this, 'toggle'],
            'permission_callback' => [$this, 'permission_admin_mutation'],
            'args'                => [
                'id' => [
                    'required' => true,
                    'type'     => 'integer',
                ],
                'active' => [
                    'required' => true,
                    'type'     => 'boolean',
                ],
            ],
        ]);

        register_rest_route('mx-pos/v1', '/payments/gateways', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'gateways'],
            'permission_callback' => [$this, 'permission_admin'],
        ]);
    }

    public function active(WP_REST_Request $request): WP_REST_Response
    {
        $methods = $this->repo->get_all_active();
        $items   = array_map([$this->repo, 'map_row'], $methods);

        return rest_ensure_response(['items' => $items]);
    }

    public function list_all(WP_REST_Request $request): WP_REST_Response
    {
        $methods = $this->repo->get_all();
        $items   = array_map([$this->repo, 'map_row'], $methods);

        return rest_ensure_response(['items' => $items]);
    }

    public function create(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $data = [
            'name'                  => $request->get_param('name'),
            'slug'                  => $request->get_param('slug'),
            'payment_type'          => $request->get_param('payment_type'),
            'affects_cash_register' => $request->get_param('affects_cash_register'),
            'allow_reference'       => $request->get_param('allow_reference'),
            'card_fee_enabled'      => $request->get_param('card_fee_enabled'),
            'card_fee_type'         => $request->get_param('card_fee_type'),
            'card_fee_value'        => $request->get_param('card_fee_value'),
            'wc_gateway_id'         => $request->get_param('wc_gateway_id'),
            'sort_order'            => $request->get_param('sort_order'),
        ];

        $result = $this->repo->create($data);

        if (is_wp_error($result)) {
            return $result;
        }

        $response = rest_ensure_response($this->repo->map_row($result));
        $response->set_status(201);

        return $response;
    }

    public function update(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id   = (int) $request->get_param('id');
        $body = $request->get_json_params();

        if (! is_array($body)) {
            $body = [];
        }

        $data = [];

        if (isset($body['name'])) $data['name'] = $body['name'];
        if (isset($body['payment_type'])) $data['payment_type'] = $body['payment_type'];
        if (isset($body['affects_cash_register'])) $data['affects_cash_register'] = $body['affects_cash_register'];
        if (isset($body['allow_reference'])) $data['allow_reference'] = $body['allow_reference'];
        if (isset($body['card_fee_enabled'])) $data['card_fee_enabled'] = $body['card_fee_enabled'];
        if (isset($body['card_fee_type'])) $data['card_fee_type'] = $body['card_fee_type'];
        if (isset($body['card_fee_value'])) $data['card_fee_value'] = $body['card_fee_value'];
        if (isset($body['sort_order'])) $data['sort_order'] = $body['sort_order'];
        if (isset($body['wc_gateway_id'])) $data['wc_gateway_id'] = $body['wc_gateway_id'];

        $result = $this->repo->update($id, $data);

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response($this->repo->map_row($result));
    }

    public function toggle(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id     = (int) $request->get_param('id');
        $active = (bool) $request->get_param('active');

        $result = $this->repo->set_active($id, $active);

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response($this->repo->map_row($result));
    }

    public function gateways(WP_REST_Request $request): WP_REST_Response
    {
        $gateways = $this->repo->get_woocommerce_gateways();

        return rest_ensure_response(['items' => $gateways]);
    }

    public function permission_pos(): bool
    {
        return current_user_can('mx_pos_sell');
    }

    public function permission_admin(): bool
    {
        return current_user_can('mx_pos_manage_settings');
    }

    public function permission_admin_mutation(WP_REST_Request $request): bool|WP_Error
    {
        return RestSecurity::verify_mutation($request, 'mx_pos_manage_settings');
    }
}
