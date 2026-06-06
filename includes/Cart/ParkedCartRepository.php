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

namespace MXPOSPro\Cart;

defined('ABSPATH') || exit;

class ParkedCartRepository
{
    private string $table;

    public function __construct()
    {
        global $wpdb;

        $this->table = $wpdb->prefix . 'mx_pos_parked_carts';
    }

    public function table_name(): string
    {
        return $this->table;
    }

    public function create(array $data): array
    {
        global $wpdb;

        $formats = ['%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s'];

        $wpdb->insert($this->table, $data, $formats);

        return $this->get_by_id((int) $wpdb->insert_id);
    }

    public function list_by_session(int $session_id): array
    {
        global $wpdb;

        if ($session_id <= 0) {
            return [];
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table}
                 WHERE session_id = %d AND status = 'parked'
                 ORDER BY created_at DESC",
                $session_id
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    public function get_by_id(int $parked_cart_id): ?array
    {
        global $wpdb;

        if ($parked_cart_id <= 0) {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE id = %d LIMIT 1",
                $parked_cart_id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public function cancel(int $parked_cart_id): bool
    {
        global $wpdb;

        if ($parked_cart_id <= 0) {
            return false;
        }

        $updated = $wpdb->update(
            $this->table,
            [
                'status'     => 'cancelled',
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $parked_cart_id, 'status' => 'parked'],
            ['%s', '%s'],
            ['%d', '%s']
        );

        return $updated !== false && $updated > 0;
    }

    public function mark_converted(int $parked_cart_id): bool
    {
        global $wpdb;

        if ($parked_cart_id <= 0) {
            return false;
        }

        $updated = $wpdb->update(
            $this->table,
            [
                'status'     => 'converted',
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $parked_cart_id, 'status' => 'parked'],
            ['%s', '%s'],
            ['%d', '%s']
        );

        return $updated !== false && $updated > 0;
    }

    public function find_by_hash(string $hash): ?array
    {
        global $wpdb;

        if ($hash === '') {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE cart_hash = %s LIMIT 1",
                $hash
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }
}
