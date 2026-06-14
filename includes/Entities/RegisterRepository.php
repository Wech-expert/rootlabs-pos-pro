<?php

namespace MXPOSPro\Entities;

defined('ABSPATH') || exit;

class RegisterRepository
{
    private string $table;

    public function __construct()
    {
        global $wpdb;

        $this->table = $wpdb->prefix . 'mx_pos_registers';
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

    public function get_by_branch(int $branch_id): array
    {
        global $wpdb;

        if ($branch_id <= 0) {
            return [];
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE branch_id = %d AND is_active = 1 ORDER BY name ASC",
                $branch_id
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
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

    public function get_all_with_branch(): array
    {
        global $wpdb;

        $branches_table = $wpdb->prefix . 'mx_pos_branches';

        $rows = $wpdb->get_results(
            "SELECT r.*, b.name AS branch_name
             FROM `{$this->table}` r
             LEFT JOIN `{$branches_table}` b ON r.branch_id = b.id
             ORDER BY r.name ASC",
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    public function get_active_by_branch(int $branch_id): array
    {
        return $this->get_by_branch($branch_id);
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

        $branch_id = (int) ($data['branch_id'] ?? 0);
        if ($branch_id <= 0) {
            return 0;
        }

        $now = current_time('mysql');

        $result = $wpdb->insert(
            $this->table,
            [
                'branch_id'  => $branch_id,
                'name'       => sanitize_text_field($data['name'] ?? ''),
                'slug'       => $slug,
                'is_active'  => isset($data['is_active']) ? (int) $data['is_active'] : 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%d', '%s', '%s', '%d', '%s', '%s']
        );

        if ($result === false) {
            return 0;
        }

        return (int) $wpdb->insert_id;
    }

    public function update(int $id, array $data): bool
    {
        global $wpdb;

        $register = $this->get_by_id($id);
        if ($register === null) {
            return false;
        }

        $set     = [];
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

            if ($register['slug'] === 'main' && $new_slug !== 'main') {
                return false;
            }

            $existing = $this->get_by_slug($new_slug);
            if ($existing !== null && (int) $existing['id'] !== $id) {
                return false;
            }

            $set['slug'] = $new_slug;
            $formats[]   = '%s';
        }

        if (array_key_exists('branch_id', $data)) {
            $set['branch_id'] = (int) $data['branch_id'];
            $formats[]        = '%d';
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
        $register = $this->get_by_id($id);
        if ($register === null) {
            return false;
        }

        if ($register['slug'] === 'main' && ! $active) {
            return false;
        }

        if (! $active && $this->count_open_sessions($id) > 0) {
            return false;
        }

        return $this->update($id, ['is_active' => $active ? 1 : 0]);
    }

    public function count_active(): int
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM `{$this->table}` WHERE is_active = 1"
        );
    }

    public function count_open_sessions(int $register_id): int
    {
        global $wpdb;

        $sessions_table = $wpdb->prefix . 'mx_pos_sessions';

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$sessions_table}` WHERE pos_register_id = %d AND status = 'open'",
                $register_id
            )
        );
    }

    public function get_active_registers_for_selection(): array
    {
        global $wpdb;

        $branches_table = $wpdb->prefix . 'mx_pos_branches';

        $rows = $wpdb->get_results(
            "SELECT r.id, r.name, r.branch_id, r.is_active,
                    b.name AS branch_name, b.is_active AS branch_is_active
             FROM `{$this->table}` r
             INNER JOIN `{$branches_table}` b ON r.branch_id = b.id
             WHERE r.is_active = 1 AND b.is_active = 1
             ORDER BY b.name ASC, r.name ASC",
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    private function normalise_slug(string $slug): string
    {
        $slug = sanitize_title($slug);

        return $slug !== '' ? $slug : '';
    }
}
