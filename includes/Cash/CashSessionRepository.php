<?php

namespace MXPOSPro\Cash;

defined('ABSPATH') || exit;

class CashSessionRepository
{
    private string $table;

    public function __construct()
    {
        global $wpdb;

        $this->table = $wpdb->prefix . 'mx_pos_sessions';
    }

    public function table_name(): string
    {
        return $this->table;
    }

    public function find_open_by_cashier(int $cashier_id): ?array
    {
        global $wpdb;

        if ($cashier_id <= 0) {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table}
                 WHERE cashier_id = %d AND status = 'open'
                 LIMIT 1",
                $cashier_id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public function create(
        int $cashier_id,
        string $opening_amount,
        string $register_id = ''
    ): array {
        global $wpdb;

        $data = [
            'cashier_id'     => $cashier_id,
            'register_id'    => $register_id,
            'status'         => 'open',
            'opening_amount' => $opening_amount,
            'opened_at'      => current_time('mysql'),
        ];

        $formats = ['%d', '%s', '%s', '%f', '%s'];

        $wpdb->insert($this->table, $data, $formats);

        return $this->get_by_id((int) $wpdb->insert_id);
    }

    public function close(int $session_id, array $data): ?array
    {
        global $wpdb;

        if ($session_id <= 0) {
            return null;
        }

        $data_formats = [];

        if (array_key_exists('status', $data)) {
            $data_formats[] = '%s';
        }

        if (array_key_exists('closing_expected', $data)) {
            $data_formats[] = '%f';
        }

        if (array_key_exists('closing_counted', $data)) {
            $data_formats[] = '%f';
        }

        if (array_key_exists('difference', $data)) {
            $data_formats[] = '%f';
        }

        if (array_key_exists('closed_by', $data)) {
            $data_formats[] = '%d';
        }

        if (array_key_exists('close_note', $data)) {
            $data_formats[] = '%s';
        }

        if (array_key_exists('denominations_json', $data)) {
            $data_formats[] = '%s';
        }

        if (array_key_exists('closed_at', $data)) {
            $data_formats[] = '%s';
        }

        $wpdb->update(
            $this->table,
            $data,
            ['id' => $session_id, 'status' => 'open'],
            $data_formats,
            ['%d', '%s']
        );

        if ($wpdb->rows_affected === 0) {
            return null;
        }

        return $this->get_by_id($session_id);
    }

    public function get_recent(int $limit = 20): array
    {
        global $wpdb;

        $limit = max(1, min($limit, 100));

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} ORDER BY opened_at DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    public function get_open_sessions(): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT * FROM {$this->table} WHERE status = 'open' ORDER BY opened_at DESC",
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    public function count_by_status(string $status): int
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE status = %s",
                $status
            )
        );
    }

    public function get_by_id(int $session_id): ?array
    {
        global $wpdb;

        if ($session_id <= 0) {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE id = %d LIMIT 1",
                $session_id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public function find_open_by_pos_employee(int $pos_employee_id): ?array
    {
        global $wpdb;

        if ($pos_employee_id <= 0) {
            return null;
        }

        $registers_table = $wpdb->prefix . 'mx_pos_registers';
        $branches_table  = $wpdb->prefix . 'mx_pos_branches';
        $employees_table = $wpdb->prefix . 'mx_pos_employees';

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT s.*, r.name AS register_name, b.name AS branch_name, e.display_name AS employee_name
                 FROM {$this->table} s
                 LEFT JOIN `{$registers_table}` r ON s.pos_register_id = r.id
                 LEFT JOIN `{$branches_table}` b ON s.branch_id = b.id
                 LEFT JOIN `{$employees_table}` e ON s.pos_employee_id = e.id
                 WHERE s.pos_employee_id = %d AND s.status = 'open'
                 LIMIT 1",
                $pos_employee_id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public function find_open_by_register(int $pos_register_id): ?array
    {
        global $wpdb;

        if ($pos_register_id <= 0) {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table}
                 WHERE pos_register_id = %d AND status = 'open'
                 LIMIT 1",
                $pos_register_id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public function find_session_today_by_register(int $pos_register_id): ?array
    {
        global $wpdb;

        if ($pos_register_id <= 0) {
            return null;
        }

        $today = current_time('Y-m-d');

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, status, opened_at, closed_at,
                        pos_register_id, branch_id, pos_employee_id,
                        opening_amount
                 FROM {$this->table}
                 WHERE pos_register_id = %d
                   AND DATE(opened_at) = %s
                   AND status IN ('open', 'closed')
                 ORDER BY opened_at DESC
                 LIMIT 1",
                $pos_register_id,
                $today
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public function create_for_pos_employee(
        int $pos_employee_id,
        int $pos_register_id,
        int $branch_id,
        string $opening_amount,
        ?string $denominations_json = null
    ): array {
        global $wpdb;

        $data = [
            'cashier_id'      => 0,
            'register_id'     => '',
            'pos_register_id' => $pos_register_id,
            'branch_id'       => $branch_id,
            'pos_employee_id' => $pos_employee_id,
            'status'          => 'open',
            'opening_amount'  => $opening_amount,
            'opened_at'       => current_time('mysql'),
        ];

        $formats = ['%d', '%s', '%d', '%d', '%d', '%s', '%f', '%s'];

        if ($denominations_json !== null) {
            $data['denominations_json'] = $denominations_json;
            $formats[]                  = '%s';
        }

        $wpdb->insert($this->table, $data, $formats);

        return $this->get_by_id((int) $wpdb->insert_id);
    }

    public function void(int $session_id, int $voided_by, string $void_reason): ?array
    {
        global $wpdb;

        if ($session_id <= 0 || $voided_by <= 0) {
            return null;
        }

        $data = [
            'status'      => 'voided',
            'voided_at'   => current_time('mysql'),
            'voided_by'   => $voided_by,
            'void_reason' => $void_reason,
        ];

        $formats = ['%s', '%s', '%d', '%s'];

        $wpdb->update(
            $this->table,
            $data,
            ['id' => $session_id, 'status' => 'open'],
            $formats,
            ['%d', '%s']
        );

        if ($wpdb->rows_affected === 0) {
            return null;
        }

        return $this->get_by_id($session_id);
    }
}
