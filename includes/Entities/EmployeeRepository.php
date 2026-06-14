<?php

namespace MXPOSPro\Entities;

defined('ABSPATH') || exit;

class EmployeeRepository
{
    private string $table;

    public function __construct()
    {
        global $wpdb;

        $this->table = $wpdb->prefix . 'mx_pos_employees';
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

    public function get_by_wp_user(int $wp_user_id): ?array
    {
        global $wpdb;

        if ($wp_user_id <= 0) {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE wp_user_id = %d LIMIT 1",
                $wp_user_id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public function get_by_username(string $username): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE username = %s LIMIT 1",
                $username
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public function get_all_active(): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT * FROM {$this->table} WHERE is_active = 1 AND deleted_at IS NULL ORDER BY display_name ASC",
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    public function get_by_branch(int $branch_id): array
    {
        global $wpdb;

        if ($branch_id <= 0) {
            return [];
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE branch_id = %d AND is_active = 1 AND deleted_at IS NULL ORDER BY display_name ASC",
                $branch_id
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    public function get_all(): array
    {
        global $wpdb;

        $branches_table = $wpdb->prefix . 'mx_pos_branches';

        $rows = $wpdb->get_results(
            "SELECT e.*, b.name AS branch_name
             FROM `{$this->table}` e
             LEFT JOIN `{$branches_table}` b ON e.branch_id = b.id
             ORDER BY e.deleted_at ASC, e.display_name ASC",
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    public function get_active(): array
    {
        return $this->get_all_active();
    }

    public function create(array $data): int
    {
        global $wpdb;

        $username = $this->normalise_username($data['username'] ?? '');

        if ($username === '') {
            return 0;
        }

        if ($this->get_by_username($username) !== null) {
            return 0;
        }

        $plaintext = $data['password'] ?? '';
        if ($plaintext === '' || strlen($plaintext) < 8) {
            return 0;
        }

        $password_hash = wp_hash_password($plaintext);

        $now = current_time('mysql');

        $result = $wpdb->insert(
            $this->table,
            [
                'branch_id'      => isset($data['branch_id']) && (int) $data['branch_id'] > 0
                    ? (int) $data['branch_id']
                    : null,
                'wp_user_id'     => null,
                'username'       => $username,
                'password_hash'  => $password_hash,
                'display_name'   => sanitize_text_field($data['display_name'] ?? ''),
                'role'           => $data['role'] ?? 'cashier',
                'is_active'      => 1,
                'deleted_at'     => null,
                'failed_attempts' => 0,
                'locked_until'   => null,
                'last_login_at'  => null,
                'created_at'     => $now,
                'updated_at'     => $now,
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s']
        );

        if ($result === false) {
            return 0;
        }

        return (int) $wpdb->insert_id;
    }

    public function update(int $id, array $data): bool
    {
        global $wpdb;

        $employee = $this->get_by_id($id);
        if ($employee === null) {
            return false;
        }

        $set     = [];
        $formats = [];

        if (array_key_exists('display_name', $data)) {
            $set['display_name'] = sanitize_text_field($data['display_name']);
            $formats[]           = '%s';
        }

        if (array_key_exists('role', $data)) {
            $set['role'] = $data['role'];
            $formats[]   = '%s';
        }

        if (array_key_exists('branch_id', $data)) {
            $set['branch_id'] = $data['branch_id'] !== null && (int) $data['branch_id'] > 0
                ? (int) $data['branch_id']
                : null;
            $formats[] = '%d';
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
        return $this->update($id, ['is_active' => $active ? 1 : 0]);
    }

    public function soft_delete(int $id): bool
    {
        global $wpdb;

        $employee = $this->get_by_id($id);
        if ($employee === null) {
            return false;
        }

        $now = current_time('mysql');

        $result = $wpdb->update(
            $this->table,
            [
                'deleted_at' => $now,
                'is_active'  => 0,
                'updated_at' => $now,
            ],
            ['id' => $id],
            ['%s', '%d', '%s'],
            ['%d']
        );

        return $result !== false;
    }

    public function restore(int $id): bool
    {
        global $wpdb;

        $employee = $this->get_by_id($id);
        if ($employee === null) {
            return false;
        }

        $result = $wpdb->update(
            $this->table,
            [
                'deleted_at' => null,
                'is_active'  => 1,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $id],
            ['%s', '%d', '%s'],
            ['%d']
        );

        return $result !== false;
    }

    public function update_password(int $id, string $plaintext): bool
    {
        global $wpdb;

        $employee = $this->get_by_id($id);
        if ($employee === null) {
            return false;
        }

        if (strlen($plaintext) < 8) {
            return false;
        }

        $result = $wpdb->update(
            $this->table,
            [
                'password_hash'  => wp_hash_password($plaintext),
                'failed_attempts' => 0,
                'locked_until'   => null,
                'updated_at'     => current_time('mysql'),
            ],
            ['id' => $id],
            ['%s', '%d', '%s', '%s'],
            ['%d']
        );

        return $result !== false;
    }

    public function reset_failed_attempts(int $id): bool
    {
        global $wpdb;

        $result = $wpdb->update(
            $this->table,
            [
                'failed_attempts' => 0,
                'updated_at'      => current_time('mysql'),
            ],
            ['id' => $id],
            ['%d', '%s'],
            ['%d']
        );

        return $result !== false;
    }

    public function unlock(int $id): bool
    {
        global $wpdb;

        $result = $wpdb->update(
            $this->table,
            [
                'failed_attempts' => 0,
                'locked_until'   => null,
                'updated_at'     => current_time('mysql'),
            ],
            ['id' => $id],
            ['%d', '%s', '%s'],
            ['%d']
        );

        return $result !== false;
    }

    public function get_auth_by_username(string $username): ?array
    {
        global $wpdb;

        $normalised = $this->normalise_username($username);

        if ($normalised === '') {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, username, password_hash, display_name, role,
                        is_active, deleted_at, failed_attempts, locked_until,
                        last_login_at, branch_id
                 FROM {$this->table}
                 WHERE username = %s
                 LIMIT 1",
                $normalised
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public function verify_credentials(string $username, string $password): array|\WP_Error
    {
        $employee = $this->get_auth_by_username($username);

        if ($employee === null) {
            return new \WP_Error(
                'mx_pos_invalid_credentials',
                __('Credenciales inválidas.', 'mx-pos-pro'),
                ['status' => 401]
            );
        }

        if (! (int) $employee['is_active'] || $employee['deleted_at'] !== null) {
            return new \WP_Error(
                'mx_pos_invalid_credentials',
                __('Credenciales inválidas.', 'mx-pos-pro'),
                ['status' => 401]
            );
        }

        if ($this->is_locked($employee)) {
            $locked_until = strtotime($employee['locked_until']);
            $now          = time();
            $remaining    = ceil(($locked_until - $now) / 60);

            return new \WP_Error(
                'mx_pos_employee_locked',
                sprintf(
                    /* translators: %d: minutes remaining */
                    __('Empleado bloqueado. Intente de nuevo en %d minuto(s).', 'mx-pos-pro'),
                    (int) $remaining
                ),
                ['status' => 423]
            );
        }

        if (! wp_check_password($password, $employee['password_hash'])) {
            $this->increment_failed_attempts((int) $employee['id']);

            return new \WP_Error(
                'mx_pos_invalid_credentials',
                __('Credenciales inválidas.', 'mx-pos-pro'),
                ['status' => 401]
            );
        }

        return $employee;
    }

    public function increment_failed_attempts(int $id): int
    {
        global $wpdb;

        $employee = $this->get_by_id($id);
        if ($employee === null) {
            return 0;
        }

        $new_count = (int) ($employee['failed_attempts'] ?? 0) + 1;

        $data = [
            'failed_attempts' => $new_count,
            'updated_at'      => current_time('mysql'),
        ];

        $formats = ['%d', '%s'];

        if ($new_count >= 5) {
            $data['locked_until'] = gmdate('Y-m-d H:i:s', time() + 300);
            $formats[]            = '%s';
        }

        $wpdb->update(
            $this->table,
            $data,
            ['id' => $id],
            $formats,
            ['%d']
        );

        return $new_count;
    }

    public function lock_until(int $id, string $until): bool
    {
        global $wpdb;

        $result = $wpdb->update(
            $this->table,
            [
                'locked_until' => $until,
                'updated_at'   => current_time('mysql'),
            ],
            ['id' => $id],
            ['%s', '%s'],
            ['%d']
        );

        return $result !== false;
    }

    public function mark_login_success(int $id): bool
    {
        global $wpdb;

        $result = $wpdb->update(
            $this->table,
            [
                'failed_attempts' => 0,
                'locked_until'    => null,
                'last_login_at'   => current_time('mysql'),
                'updated_at'      => current_time('mysql'),
            ],
            ['id' => $id],
            ['%d', '%s', '%s', '%s'],
            ['%d']
        );

        return $result !== false;
    }

    public function is_locked(array $employee): bool
    {
        if (empty($employee['locked_until'])) {
            return false;
        }

        $locked_until = strtotime($employee['locked_until']);

        return $locked_until !== false && $locked_until > time();
    }

    public function count_active(): int
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table} WHERE is_active = 1 AND deleted_at IS NULL"
        );
    }

    public function count_by_role(string $role): int
    {
        global $wpdb;

        if (! in_array($role, ['cashier', 'manager'], true)) {
            return 0;
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE role = %s AND is_active = 1 AND deleted_at IS NULL",
                $role
            )
        );
    }

    public function username_exists(string $username, ?int $exclude_id = null): bool
    {
        $normalised = $this->normalise_username($username);
        if ($normalised === '') {
            return false;
        }

        $existing = $this->get_by_username($normalised);
        if ($existing === null) {
            return false;
        }

        if ($exclude_id !== null && (int) $existing['id'] === $exclude_id) {
            return false;
        }

        return true;
    }

    private function normalise_username(string $username): string
    {
        $username = sanitize_user($username, true);

        return $username !== '' ? $username : '';
    }
}
