<?php

namespace MXPOSPro\Payments;

defined('ABSPATH') || exit;

use WP_Error;

class PaymentMethodRepository
{
    private string $table;
    private const PROTECTED_SLUGS = ['cash', 'card', 'mixed'];
    private const ALLOWED_TYPES = ['cash', 'card', 'mixed', 'woocommerce', 'other'];

    public function __construct()
    {
        global $wpdb;

        $this->table = $wpdb->prefix . 'mx_pos_payment_methods';
    }

    public function table_name(): string
    {
        return $this->table;
    }

    public function get_by_id(int $id): ?array
    {
        global $wpdb;

        if ($id <= 0) {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE id = %d LIMIT 1",
                $id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public function get_by_slug(string $slug): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE slug = %s LIMIT 1",
                $slug
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public function get_all_active(): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT * FROM {$this->table} WHERE is_active = 1 ORDER BY sort_order ASC",
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    public function get_all(): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT * FROM {$this->table} ORDER BY sort_order ASC",
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    public function get_cash_affecting(): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT * FROM {$this->table} WHERE is_active = 1 AND affects_cash_register = 1 ORDER BY sort_order ASC",
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    public function create(array $data): array|WP_Error
    {
        global $wpdb;

        $name = sanitize_text_field($data['name'] ?? '');
        $slug = sanitize_text_field($data['slug'] ?? '');
        $paymentType = isset($data['payment_type']) && in_array($data['payment_type'], self::ALLOWED_TYPES, true)
            ? $data['payment_type']
            : 'other';
        $affectsCash = isset($data['affects_cash_register']) ? ($data['affects_cash_register'] ? 1 : 0) : 0;
        $allowRef = isset($data['allow_reference']) ? ($data['allow_reference'] ? 1 : 0) : 0;
        $feeEnabled = isset($data['card_fee_enabled']) ? ($data['card_fee_enabled'] ? 1 : 0) : 0;
        $feeType = null;
        $feeValue = null;

        if ($feeEnabled) {
            $feeType = isset($data['card_fee_type']) && in_array($data['card_fee_type'], ['percentage', 'fixed'], true)
                ? $data['card_fee_type']
                : null;
            if ($feeType !== null && isset($data['card_fee_value']) && is_numeric($data['card_fee_value']) && (float) $data['card_fee_value'] >= 0) {
                $feeValue = (float) $data['card_fee_value'];
            }
        }

        $wcGatewayId = isset($data['wc_gateway_id']) && is_string($data['wc_gateway_id']) && $data['wc_gateway_id'] !== ''
            ? sanitize_text_field($data['wc_gateway_id'])
            : null;

        $sortOrder = isset($data['sort_order']) ? absint($data['sort_order']) : 0;

        if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            return new WP_Error(
                'mx_pos_invalid_name',
                __('Name must be between 2 and 100 characters.', 'mx-pos-pro'),
                ['status' => 400]
            );
        }

        if ($slug === '' || ! preg_match('/^[a-z0-9_-]+$/', $slug) || mb_strlen($slug) > 50) {
            return new WP_Error(
                'mx_pos_invalid_slug',
                __('Slug must contain only lowercase letters, numbers, hyphens and underscores, max 50 characters.', 'mx-pos-pro'),
                ['status' => 400]
            );
        }

        $existing = $this->get_by_slug($slug);
        if ($existing !== null) {
            return new WP_Error(
                'mx_pos_duplicate_slug',
                __('A payment method with this slug already exists.', 'mx-pos-pro'),
                ['status' => 409]
            );
        }

        $result = $wpdb->insert(
            $this->table,
            [
                'name'                  => $name,
                'slug'                  => $slug,
                'payment_type'          => $paymentType,
                'affects_cash_register' => $affectsCash,
                'allow_reference'       => $allowRef,
                'card_fee_enabled'      => $feeEnabled,
                'card_fee_type'         => $feeType,
                'card_fee_value'        => $feeValue,
                'wc_gateway_id'         => $wcGatewayId,
                'is_active'             => 1,
                'sort_order'            => $sortOrder,
                'created_at'            => current_time('mysql'),
                'updated_at'            => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%d', '%d', '%d', '%s', '%f', '%s', '%d', '%d', '%s', '%s']
        );

        if ($result === false) {
            return new WP_Error(
                'mx_pos_create_failed',
                __('Failed to create payment method.', 'mx-pos-pro'),
                ['status' => 500]
            );
        }

        $id = (int) $wpdb->insert_id;

        return $this->get_by_id($id);
    }

    public function update(int $id, array $data): array|WP_Error
    {
        global $wpdb;

        $method = $this->get_by_id($id);
        if ($method === null) {
            return new WP_Error(
                'mx_pos_not_found',
                __('Payment method not found.', 'mx-pos-pro'),
                ['status' => 404]
            );
        }

        $update = [];
        $formats = [];

        if (isset($data['name'])) {
            $name = sanitize_text_field($data['name']);
            if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 100) {
                return new WP_Error(
                    'mx_pos_invalid_name',
                    __('Name must be between 2 and 100 characters.', 'mx-pos-pro'),
                    ['status' => 400]
                );
            }
            $update['name'] = $name;
            $formats[] = '%s';
        }

        if (isset($data['payment_type'])) {
            if (! in_array($data['payment_type'], self::ALLOWED_TYPES, true)) {
                return new WP_Error(
                    'mx_pos_invalid_type',
                    __('Invalid payment type.', 'mx-pos-pro'),
                    ['status' => 400]
                );
            }
            $update['payment_type'] = $data['payment_type'];
            $formats[] = '%s';
        }

        if (isset($data['affects_cash_register'])) {
            $update['affects_cash_register'] = $data['affects_cash_register'] ? 1 : 0;
            $formats[] = '%d';
        }

        if (isset($data['allow_reference'])) {
            $update['allow_reference'] = $data['allow_reference'] ? 1 : 0;
            $formats[] = '%d';
        }

        if (isset($data['card_fee_enabled'])) {
            $enabled = $data['card_fee_enabled'] ? 1 : 0;
            $update['card_fee_enabled'] = $enabled;
            $formats[] = '%d';

            if ($enabled) {
                if (isset($data['card_fee_type'])) {
                    $feeType = in_array($data['card_fee_type'], ['percentage', 'fixed'], true) ? $data['card_fee_type'] : null;
                    $update['card_fee_type'] = $feeType;
                    $formats[] = '%s';
                }
                if (isset($data['card_fee_value']) && is_numeric($data['card_fee_value']) && (float) $data['card_fee_value'] >= 0) {
                    $update['card_fee_value'] = (float) $data['card_fee_value'];
                    $formats[] = '%f';
                }
            } else {
                $update['card_fee_type'] = null;
                $formats[] = '%s';
                $update['card_fee_value'] = null;
                $formats[] = '%s';
            }
        }

        if (isset($data['sort_order'])) {
            $update['sort_order'] = absint($data['sort_order']);
            $formats[] = '%d';
        }

        if (isset($data['wc_gateway_id'])) {
            $update['wc_gateway_id'] = $data['wc_gateway_id'] !== '' && is_string($data['wc_gateway_id'])
                ? sanitize_text_field($data['wc_gateway_id'])
                : null;
            $formats[] = '%s';
        }

        if (empty($update)) {
            return $method;
        }

        $update['updated_at'] = current_time('mysql');
        $formats[] = '%s';

        $result = $wpdb->update(
            $this->table,
            $update,
            ['id' => $id],
            $formats,
            ['%d']
        );

        if ($result === false) {
            return new WP_Error(
                'mx_pos_update_failed',
                __('Failed to update payment method.', 'mx-pos-pro'),
                ['status' => 500]
            );
        }

        return $this->get_by_id($id);
    }

    public function set_active(int $id, bool $active): array|WP_Error
    {
        global $wpdb;

        $method = $this->get_by_id($id);
        if ($method === null) {
            return new WP_Error(
                'mx_pos_not_found',
                __('Payment method not found.', 'mx-pos-pro'),
                ['status' => 404]
            );
        }

        if (! $active && in_array($method['slug'], self::PROTECTED_SLUGS, true)) {
            return new WP_Error(
                'mx_pos_protected_method',
                __('Base payment methods (cash, card, mixed) cannot be deactivated.', 'mx-pos-pro'),
                ['status' => 403]
            );
        }

        $wpdb->update(
            $this->table,
            [
                'is_active'  => $active ? 1 : 0,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $id],
            ['%d', '%s'],
            ['%d']
        );

        return $this->get_by_id($id);
    }

    public function get_woocommerce_gateways(): array
    {
        if (! function_exists('WC') || ! isset(WC()->payment_gateways)) {
            return [];
        }

        $gateways = WC()->payment_gateways->payment_gateways();
        $items = [];

        foreach ($gateways as $id => $gateway) {
            $items[] = [
                'id'          => $gateway->id,
                'title'       => $gateway->get_title(),
                'description' => $gateway->get_description(),
                'enabled'     => $gateway->enabled === 'yes',
                'method_title' => $gateway->get_method_title(),
            ];
        }

        return $items;
    }

    public function map_row(array $row): array
    {
        return [
            'id'                   => (int) $row['id'],
            'name'                 => $row['name'],
            'slug'                 => $row['slug'],
            'payment_type'         => $row['payment_type'] ?? 'other',
            'affects_cash_register' => (bool) ((int) ($row['affects_cash_register'] ?? 0)),
            'allow_reference'      => (bool) ((int) ($row['allow_reference'] ?? 0)),
            'card_fee_enabled'     => (bool) ((int) ($row['card_fee_enabled'] ?? 0)),
            'card_fee_type'        => $row['card_fee_type'] ?? null,
            'card_fee_value'       => isset($row['card_fee_value']) && $row['card_fee_value'] !== null
                ? (string) $row['card_fee_value']
                : null,
            'wc_gateway_id'        => $row['wc_gateway_id'] ?? null,
            'is_active'            => (bool) ((int) ($row['is_active'] ?? 0)),
            'sort_order'           => (int) ($row['sort_order'] ?? 0),
        ];
    }
}
