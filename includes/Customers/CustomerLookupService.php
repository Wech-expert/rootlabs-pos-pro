<?php

namespace MXPOSPro\Customers;

defined('ABSPATH') || exit;

use WP_Error;

class CustomerLookupService
{
    public const MAX_LIMIT = 50;
    public const MAX_PURCHASE_LIMIT = 50;

    public function search(string $query, int $limit = 20): array|WP_Error
    {
        $query = trim(sanitize_text_field($query));

        if (mb_strlen($query) < 2) {
            return new WP_Error(
                'mx_pos_invalid_query',
                __('Search query must be at least 2 characters.', 'mx-pos-pro'),
                ['status' => 400]
            );
        }

        if (mb_strlen($query) > 100) {
            $query = mb_substr($query, 0, 100);
        }

        $limit = max(1, min($limit, self::MAX_LIMIT));

        $args = [
            'search'         => '*' . $query . '*',
            'search_columns' => ['user_login', 'user_nicename', 'user_email', 'display_name'],
            'number'         => $limit,
            'orderby'        => 'display_name',
            'order'          => 'ASC',
        ];

        $userQuery = new \WP_User_Query($args);
        $results   = $userQuery->get_results();
        $seen      = [];

        foreach ($results as $user) {
            $seen[$user->ID] = $user;
        }

        if (count($seen) < $limit) {
            $remaining = $limit - count($seen);

            $metaArgs = [
                'meta_query' => [
                    [
                        'key'     => 'billing_phone',
                        'value'   => $query,
                        'compare' => 'LIKE',
                    ],
                ],
                'number'         => $remaining,
                'orderby'        => 'display_name',
                'order'          => 'ASC',
                'fields'         => 'all',
            ];

            $phoneQuery = new \WP_User_Query($metaArgs);
            $phoneResults = $phoneQuery->get_results();

            foreach ($phoneResults as $user) {
                if (! isset($seen[$user->ID])) {
                    $seen[$user->ID] = $user;
                }
            }
        }

        $items = [];

        foreach ($seen as $userId => $user) {
            $items[] = $this->map_user($user);
        }

        return ['items' => $items];
    }

    public function lookup_by_email(string $email): ?array
    {
        $email = sanitize_email($email);

        if (! is_email($email)) {
            return null;
        }

        $user = get_user_by('email', $email);

        if (! $user instanceof \WP_User) {
            return null;
        }

        return $this->map_user($user);
    }

    public function get_by_id(int $customer_id): ?array
    {
        if ($customer_id <= 0) {
            return null;
        }

        $user = get_userdata($customer_id);

        if (! $user) {
            return null;
        }

        return $this->map_user($user);
    }

    public function create(string $name, string $email, string $phone): array|WP_Error
    {
        $name  = sanitize_text_field($name);
        $email = sanitize_email($email);
        $phone = sanitize_text_field($phone);

        $nameTrimmed  = trim($name);
        $phoneTrimmed = trim($phone);

        if (mb_strlen($nameTrimmed) < 2 || mb_strlen($nameTrimmed) > 150) {
            return new WP_Error(
                'mx_pos_invalid_name',
                __('Name must be between 2 and 150 characters.', 'mx-pos-pro'),
                ['status' => 400]
            );
        }

        if (! is_email($email)) {
            return new WP_Error(
                'mx_pos_invalid_email',
                __('A valid email address is required.', 'mx-pos-pro'),
                ['status' => 400]
            );
        }

        if (email_exists($email)) {
            return new WP_Error(
                'mx_pos_customer_email_exists',
                __('A user with this email address already exists.', 'mx-pos-pro'),
                ['status' => 409]
            );
        }

        if (mb_strlen($phoneTrimmed) < 5 || mb_strlen($phoneTrimmed) > 30) {
            return new WP_Error(
                'mx_pos_invalid_phone',
                __('Phone must be between 5 and 30 characters.', 'mx-pos-pro'),
                ['status' => 400]
            );
        }

        $parts = explode(' ', $nameTrimmed);

        if (count($parts) === 1) {
            $firstName = $nameTrimmed;
            $lastName  = '';
        } else {
            $lastName  = array_pop($parts);
            $firstName = implode(' ', $parts);
        }

        $password = wp_generate_password(16, true);

        $userData = [
            'user_login'      => $email,
            'user_email'      => $email,
            'user_pass'       => $password,
            'display_name'    => $nameTrimmed,
            'first_name'      => $firstName,
            'last_name'       => $lastName,
            'role'            => 'customer',
        ];

        $userId = wp_insert_user($userData);

        if (is_wp_error($userId)) {
            return new WP_Error(
                'mx_pos_customer_create_failed',
                __('Failed to create customer.', 'mx-pos-pro'),
                ['status' => 500]
            );
        }

        update_user_meta($userId, 'billing_first_name', $firstName);
        update_user_meta($userId, 'billing_last_name', $lastName);
        update_user_meta($userId, 'billing_phone', $phoneTrimmed);
        update_user_meta($userId, 'billing_email', $email);

        return [
            'id'           => $userId,
            'display_name' => $nameTrimmed,
            'email'        => $email,
            'phone'        => $phoneTrimmed,
            'first_name'   => $firstName !== '' ? $firstName : null,
            'last_name'    => $lastName !== '' ? $lastName : null,
        ];
    }

    public function update(int $customer_id, string $name, string $phone): array|WP_Error
    {
        $name  = sanitize_text_field($name);
        $phone = sanitize_text_field($phone);

        $nameTrimmed  = trim($name);
        $phoneTrimmed = trim($phone);

        if (mb_strlen($nameTrimmed) < 2 || mb_strlen($nameTrimmed) > 150) {
            return new WP_Error(
                'mx_pos_invalid_name',
                __('Name must be between 2 and 150 characters.', 'mx-pos-pro'),
                ['status' => 400]
            );
        }

        if (mb_strlen($phoneTrimmed) < 5 || mb_strlen($phoneTrimmed) > 30) {
            return new WP_Error(
                'mx_pos_invalid_phone',
                __('Phone must be between 5 and 30 characters.', 'mx-pos-pro'),
                ['status' => 400]
            );
        }

        if ($customer_id <= 0) {
            return new WP_Error(
                'mx_pos_customer_not_found',
                __('Customer not found.', 'mx-pos-pro'),
                ['status' => 404]
            );
        }

        $user = get_userdata($customer_id);

        if (! $user instanceof \WP_User) {
            return new WP_Error(
                'mx_pos_customer_not_found',
                __('Customer not found.', 'mx-pos-pro'),
                ['status' => 404]
            );
        }

        if (user_can($user, 'manage_options') || user_can($user, 'mx_pos_manage_settings')) {
            return new WP_Error(
                'mx_pos_customer_not_editable',
                __('This user cannot be edited from POS.', 'mx-pos-pro'),
                ['status' => 403]
            );
        }

        if (! in_array('customer', $user->roles, true) && ! user_can($user, 'mx_pos_access')) {
            $hasAllowedRole = false;
            foreach ($user->roles as $roleName) {
                $role = get_role($roleName);
                if ($role instanceof \WP_Role && ! $role->has_cap('manage_options')) {
                    $hasAllowedRole = true;
                    break;
                }
            }
            if (! $hasAllowedRole) {
                return new WP_Error(
                    'mx_pos_customer_not_editable',
                    __('This user cannot be edited from POS.', 'mx-pos-pro'),
                    ['status' => 403]
                );
            }
        }

        $parts = explode(' ', $nameTrimmed);

        if (count($parts) === 1) {
            $firstName = $nameTrimmed;
            $lastName  = '';
        } else {
            $lastName  = array_pop($parts);
            $firstName = implode(' ', $parts);
        }

        wp_update_user([
            'ID'           => $customer_id,
            'display_name' => $nameTrimmed,
            'first_name'   => $firstName,
            'last_name'    => $lastName,
        ]);

        update_user_meta($customer_id, 'billing_first_name', $firstName);
        update_user_meta($customer_id, 'billing_last_name', $lastName);
        update_user_meta($customer_id, 'billing_phone', $phoneTrimmed);

        return [
            'id'           => $customer_id,
            'display_name' => $nameTrimmed,
            'email'        => $user->user_email,
            'phone'        => $phoneTrimmed,
            'first_name'   => $firstName !== '' ? $firstName : null,
            'last_name'    => $lastName !== '' ? $lastName : null,
        ];
    }

    public function get_purchase_history(int $customer_id, int $limit = 10): array|WP_Error
    {
        if ($customer_id <= 0) {
            return new WP_Error(
                'mx_pos_customer_not_found',
                __('Customer not found.', 'mx-pos-pro'),
                ['status' => 404]
            );
        }

        $user = get_userdata($customer_id);

        if (! $user instanceof \WP_User) {
            return new WP_Error(
                'mx_pos_customer_not_found',
                __('Customer not found.', 'mx-pos-pro'),
                ['status' => 404]
            );
        }

        $limit = max(1, min($limit, self::MAX_PURCHASE_LIMIT));

        $orders = wc_get_orders([
            'customer_id' => $customer_id,
            'limit'       => $limit,
            'orderby'     => 'date',
            'order'       => 'DESC',
            'return'      => 'objects',
        ]);

        $items = [];

        foreach ($orders as $order) {
            if (! $order instanceof \WC_Order) {
                continue;
            }

            $paymentMethod = $order->get_payment_method();
            $methodLabel   = $paymentMethod === 'cash'
                ? __('Efectivo', 'mx-pos-pro')
                : ($paymentMethod === 'card'
                    ? __('Tarjeta', 'mx-pos-pro')
                    : $paymentMethod);

            $items[] = [
                'order_id'             => $order->get_id(),
                'order_number'         => (string) $order->get_order_number(),
                'date'                 => $order->get_date_created()
                    ? $order->get_date_created()->format('Y-m-d\TH:i:s')
                    : null,
                'total'                => (string) $order->get_total(),
                'status'               => $order->get_status(),
                'payment_method'       => $paymentMethod ?: null,
                'payment_method_label' => $methodLabel !== '' ? $methodLabel : null,
            ];
        }

        return ['items' => $items];
    }

    private function map_user(\WP_User $user): array
    {
        $firstName = get_user_meta($user->ID, 'first_name', true) ?: null;
        $lastName  = get_user_meta($user->ID, 'last_name', true) ?: null;
        $phone     = get_user_meta($user->ID, 'billing_phone', true) ?: null;

        if ($firstName && $lastName) {
            $displayName = "$firstName $lastName";
        } elseif ($user->display_name && $user->display_name !== '') {
            $displayName = $user->display_name;
        } else {
            $displayName = $user->user_email;
        }

        return [
            'id'           => $user->ID,
            'display_name' => $displayName,
            'email'        => $user->user_email,
            'phone'        => $phone !== null && $phone !== '' ? $phone : null,
            'first_name'   => $firstName,
            'last_name'    => $lastName,
        ];
    }
}
