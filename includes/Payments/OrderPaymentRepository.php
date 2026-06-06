<?php


/**
 * RootLabs POS uses custom operational tables for POS data.
 * These database calls are intentional and isolated in repository/service layers.
 *
 * rootlabs-pos-pro-w2a-db-intentional
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
 * phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
 * phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
 * phpcs:disable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
 * phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
 */

namespace MXPOSPro\Payments;

defined('ABSPATH') || exit;

class OrderPaymentRepository
{
    private string $table;

    public function __construct()
    {
        global $wpdb;

        $this->table = $wpdb->prefix . 'mx_pos_order_payments';
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

    public function get_by_sale(int $sale_id): array
    {
        global $wpdb;

        if ($sale_id <= 0) {
            return [];
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE sale_id = %d ORDER BY id ASC",
                $sale_id
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    public function get_totals_by_sale(int $sale_id): array
    {
        $payments = $this->get_by_sale($sale_id);

        $total = 0.0;
        foreach ($payments as $p) {
            $total += (float) $p['amount'];
        }

        return [
            'payments' => $payments,
            'total'    => number_format($total, 4, '.', ''),
            'count'    => count($payments),
        ];
    }
}
