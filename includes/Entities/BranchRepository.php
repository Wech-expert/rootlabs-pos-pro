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

namespace MXPOSPro\Entities;

defined('ABSPATH') || exit;

class BranchRepository
{
    private string $table;

    public function __construct()
    {
        global $wpdb;

        $this->table = $wpdb->prefix . 'mx_pos_branches';
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

    public function get_default(): ?array
    {
        return $this->get_by_slug('main');
    }

    public function get_all_active(): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT * FROM {$this->table} WHERE is_active = 1 ORDER BY name ASC",
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    public function get_all(): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT * FROM {$this->table} ORDER BY name ASC",
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    public function create(array $data): int
    {
        global $wpdb;

        $slug = $this->normalise_slug($data['slug'] ?? '');

        if ($slug === '') {
            return 0;
        }

        $existing = $this->get_by_slug($slug);
        if ($existing !== null) {
            return 0;
        }

        $now = current_time('mysql');

        $result = $wpdb->insert(
            $this->table,
            [
                'name'       => sanitize_text_field($data['name'] ?? ''),
                'slug'       => $slug,
                'address'    => isset($data['address']) ? sanitize_textarea_field($data['address']) : null,
                'phone'      => isset($data['phone']) ? sanitize_text_field($data['phone']) : null,
                'is_active'  => isset($data['is_active']) ? (int) $data['is_active'] : 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%s', '%s', '%s', '%s', '%d', '%s', '%s']
        );

        if ($result === false) {
            return 0;
        }

        return (int) $wpdb->insert_id;
    }

    public function update(int $id, array $data): bool
    {
        global $wpdb;

        $branch = $this->get_by_id($id);
        if ($branch === null) {
            return false;
        }

        $set   = [];
        $formats = [];

        if (array_key_exists('name', $data)) {
            $set['name'] = sanitize_text_field($data['name']);
            $formats[]   = '%s';
        }

        if (array_key_exists('slug', $data)) {
            $new_slug = $this->normalise_slug($data['slug']);

            if ($new_slug === '') {
                return false;
            }

            if ($branch['slug'] === 'main' && $new_slug !== 'main') {
                return false;
            }

            $existing = $this->get_by_slug($new_slug);
            if ($existing !== null && (int) $existing['id'] !== $id) {
                return false;
            }

            $set['slug'] = $new_slug;
            $formats[]   = '%s';
        }

        if (array_key_exists('address', $data)) {
            $set['address'] = $data['address'] !== null
                ? sanitize_textarea_field($data['address'])
                : null;
            $formats[] = '%s';
        }

        if (array_key_exists('phone', $data)) {
            $set['phone'] = $data['phone'] !== null
                ? sanitize_text_field($data['phone'])
                : null;
            $formats[] = '%s';
        }

        if (array_key_exists('is_active', $data)) {
            $set['is_active'] = (int) $data['is_active'];
            $formats[]        = '%d';
        }

        if (empty($set)) {
            return true;
        }

        $set['updated_at'] = current_time('mysql');
        $formats[]         = '%s';

        $result = $wpdb->update(
            $this->table,
            $set,
            ['id' => $id],
            $formats,
            ['%d']
        );

        return $result !== false;
    }

    public function set_active(int $id, bool $active): bool
    {
        $branch = $this->get_by_id($id);
        if ($branch === null) {
            return false;
        }

        if ($branch['slug'] === 'main' && ! $active) {
            return false;
        }

        if (! $active && $this->count_active_registers($id) > 0) {
            return false;
        }

        return $this->update($id, ['is_active' => $active ? 1 : 0]);
    }

    public function count_active_registers(int $branch_id): int
    {
        global $wpdb;

        $registers_table = $wpdb->prefix . 'mx_pos_registers';

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$registers_table}` WHERE branch_id = %d AND is_active = 1",
                $branch_id
            )
        );
    }

    private function normalise_slug(string $slug): string
    {
        $slug = sanitize_title($slug);

        return $slug !== '' ? $slug : '';
    }
}
