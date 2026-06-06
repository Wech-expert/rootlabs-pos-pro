<?php

namespace MXPOSPro\Core;

defined('ABSPATH') || exit;

use WP_Error;
use WP_REST_Request;

class RestSecurity
{
    public static function verify_mutation(WP_REST_Request $request, string $capability): bool|WP_Error
    {
        if (! current_user_can($capability)) {
            return new WP_Error(
                'mx_pos_forbidden',
                __('You do not have permission to perform this action.', 'mx-pos-pro'),
                ['status' => 403]
            );
        }

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

        return true;
    }
}
