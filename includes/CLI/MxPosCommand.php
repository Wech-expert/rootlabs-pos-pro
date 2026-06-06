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

namespace MXPOSPro\CLI;

defined('ABSPATH') || exit;

use WP_CLI;
use MXPOSPro\Core\Capabilities;
use MXPOSPro\Database\Migrator;
use MXPOSPro\Database\Schema;

/**
 * RootLabs POS diagnostic and support commands.
 *
 * ## EXAMPLES
 *
 *     wp mx-pos healthcheck
 *     wp mx-pos db-check
 *     wp mx-pos caps-check
 *     wp mx-pos sessions list
 *     wp mx-pos cuts list
 *     wp mx-pos diagnose
 *
 * @when after_wp_load
 */
class MxPosCommand
{
    private const ALLOWED_STATUSES = ['open', 'closed', 'all'];
    private const ALLOWED_FORMATS = ['table', 'json', 'csv'];
    private const ALLOWED_ROLES = ['administrator', 'shop_manager', 'mx_pos_cashier', 'all'];
    private const DEFAULT_LIMIT = 20;
    private const MAX_LIMIT = 100;

    /**
     * Run a general diagnostic of the plugin status.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format: table, json.
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     * ---
     *
     * [--verbose]
     * : Show detailed information.
     *
     * ## EXAMPLES
     *
     *     wp mx-pos healthcheck
     *     wp mx-pos healthcheck --format=json
     *     wp mx-pos healthcheck --verbose
     *
     * @when after_wp_load
     */
    public function healthcheck(array $args, array $assoc_args): void
    {
        $format  = $this->sanitize_format($assoc_args['format'] ?? 'table');
        $verbose = isset($assoc_args['verbose']);

        $checks = [];

        $checks[] = $this->check_plugin_loaded();
        $checks[] = $this->check_woocommerce();
        $checks[] = $this->check_db_version();
        $checks[] = $this->check_tables();
        $checks[] = $this->check_options();
        $checks[] = $this->check_capabilities();
        $checks[] = $this->check_assets();
        $checks[] = $this->check_pos_route();

        $has_fail = false;
        foreach ($checks as $c) {
            if ($c['status'] === 'FAIL') {
                $has_fail = true;
                break;
            }
        }

        if ($format === 'json') {
            WP_CLI::line(wp_json_encode($checks, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $rows = array_map(function (array $c): array {
                return [
                    'Check'   => $c['check'],
                    'Status'  => $c['status'],
                    'Details' => $c['details'] ?? '',
                ];
            }, $checks);

            WP_CLI\Utils\format_items('table', $rows, ['Check', 'Status', 'Details']);
        }

        if ($has_fail) {
            WP_CLI::error('One or more checks failed.', false);
            WP_CLI::halt(1);
        }

        WP_CLI::success('All checks passed.');
    }

    /**
     * Validate database schema: tables, critical columns, and indexes.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format: table, json.
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     * ---
     *
     * [--verbose]
     * : Show all columns, not just critical ones.
     *
     * ## EXAMPLES
     *
     *     wp mx-pos db-check
     *     wp mx-pos db-check --verbose
     *     wp mx-pos db-check --format=json
     *
     * @subcommand db-check
     * @when after_wp_load
     */
    public function db_check(array $args, array $assoc_args): void
    {
        $format  = $this->sanitize_format($assoc_args['format'] ?? 'table');
        $verbose = isset($assoc_args['verbose']);

        if (! class_exists('WooCommerce')) {
            WP_CLI::error('WooCommerce must be active to run database checks.');
        }

        $results = [];

        $tables = array_keys(Schema::get_tables());

        foreach ($tables as $table) {
            $exists = $this->table_exists($table);
            $results[] = [
                'table'   => $table,
                'check'   => 'Table exists',
                'status'  => $exists ? 'OK' : 'FAIL',
            ];

            if (! $exists) {
                continue;
            }

            $critical = $this->get_critical_columns($table);

            foreach ($critical as $col) {
                $col_exists = $this->column_exists($table, $col);
                $results[] = [
                    'table'   => $table,
                    'check'   => "Column: {$col}",
                    'status'  => $col_exists ? 'OK' : 'FAIL',
                ];
            }

            $critical_indexes = $this->get_critical_indexes($table);

            foreach ($critical_indexes as $idx) {
                $idx_exists = $this->index_exists($table, $idx);
                $results[] = [
                    'table'   => $table,
                    'check'   => "Index: {$idx}",
                    'status'  => $idx_exists ? 'OK' : 'FAIL',
                ];
            }

            if ($verbose) {
                $all_cols = $this->get_all_columns_for($table);

                foreach ($all_cols as $col) {
                    if (in_array($col, $critical, true)) {
                        continue;
                    }

                    $col_exists = $this->column_exists($table, $col);
                    $results[] = [
                        'table'   => $table,
                        'check'   => "Column: {$col}",
                        'status'  => $col_exists ? 'OK' : 'FAIL',
                    ];
                }
            }
        }

        $db_ver       = get_option('mx_pos_pro_db_version', '');
        $expected_ver = MX_POS_PRO_DB_VERSION;
        $results[]    = [
            'table'  => '(options)',
            'check'  => "DB version ({$db_ver} == {$expected_ver})",
            'status' => $db_ver === $expected_ver ? 'OK' : 'FAIL',
        ];

        $has_fail = false;
        foreach ($results as $r) {
            if ($r['status'] === 'FAIL') {
                $has_fail = true;
                break;
            }
        }

        if ($format === 'json') {
            WP_CLI::line(wp_json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $rows = array_map(function (array $r): array {
                return [
                    'Table'  => $r['table'],
                    'Check'  => $r['check'],
                    'Status' => $r['status'],
                ];
            }, $results);

            WP_CLI\Utils\format_items('table', $rows, ['Table', 'Check', 'Status']);
        }

        if ($has_fail) {
            WP_CLI::error('One or more database checks failed.', false);
            WP_CLI::halt(3);
        }

        WP_CLI::success('All database checks passed.');
    }

    /**
     * Validate capabilities assigned to POS roles.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format: table, json.
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     * ---
     *
     * [--role=<role>]
     * : Filter by role: administrator, shop_manager, mx_pos_cashier, all.
     * ---
     * default: all
     * ---
     *
     * ## EXAMPLES
     *
     *     wp mx-pos caps-check
     *     wp mx-pos caps-check --role=mx_pos_cashier
     *     wp mx-pos caps-check --format=json
     *
     * @subcommand caps-check
     * @when after_wp_load
     */
    public function caps_check(array $args, array $assoc_args): void
    {
        $format = $this->sanitize_format($assoc_args['format'] ?? 'table');
        $role   = $this->sanitize_role($assoc_args['role'] ?? 'all');

        $role_map   = Capabilities::role_capability_map();
        $all_caps   = Capabilities::capabilities();
        $results    = [];
        $has_issues = false;

        $roles_to_check = $role === 'all'
            ? array_keys($role_map)
            : [$role];

        foreach ($roles_to_check as $role_name) {
            $wp_role = get_role($role_name);

            if (! $wp_role instanceof \WP_Role) {
                $has_issues = true;

                foreach ($all_caps as $cap) {
                    $expected = in_array($role_name, array_keys($role_map), true)
                        && in_array($cap, $role_map[$role_name] ?? [], true);

                    $results[] = [
                        'role'       => $role_name,
                        'capability' => $cap,
                        'actual'     => 'N/A',
                        'expected'   => $expected ? 'Yes' : 'No',
                        'status'     => 'FAIL',
                    ];
                }

                continue;
            }

            $expected_caps = $role_map[$role_name] ?? [];

            foreach ($all_caps as $cap) {
                $actual   = $wp_role->has_cap($cap);
                $expected = in_array($cap, $expected_caps, true);

                if ($actual !== $expected) {
                    $has_issues = true;
                    $status     = 'FAIL';
                } else {
                    $status = 'OK';
                }

                $results[] = [
                    'role'       => $role_name,
                    'capability' => $cap,
                    'actual'     => $actual ? 'Yes' : 'No',
                    'expected'   => $expected ? 'Yes' : 'No',
                    'status'     => $status,
                ];
            }
        }

        if ($format === 'json') {
            WP_CLI::line(wp_json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $rows = array_map(function (array $r): array {
                return [
                    'Role'       => $r['role'],
                    'Capability' => $r['capability'],
                    'Actual'     => $r['actual'],
                    'Expected'   => $r['expected'],
                    'Status'     => $r['status'],
                ];
            }, $results);

            WP_CLI\Utils\format_items('table', $rows, ['Role', 'Capability', 'Actual', 'Expected', 'Status']);
        }

        if ($has_issues) {
            WP_CLI::error('One or more capability checks failed.', false);
            WP_CLI::halt(4);
        }

        WP_CLI::success('All capability checks passed.');
    }

    /**
     * List cash register sessions.
     *
     * ## OPTIONS
     *
     * [<action>]
     * : Action: list.
     * ---
     * default: list
     * options:
     *   - list
     * ---
     *
     * [--status=<status>]
     * : Filter by status: open, closed, all.
     * ---
     * default: all
     * ---
     *
     * [--limit=<n>]
     * : Max results.
     * ---
     * default: 20
     * ---
     *
     * [--cashier=<id>]
     * : Filter by cashier user ID.
     *
     * [--format=<format>]
     * : Output format: table, json, csv.
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     *   - csv
     * ---
     *
     * ## EXAMPLES
     *
     *     wp mx-pos sessions list
     *     wp mx-pos sessions list --status=open
     *     wp mx-pos sessions list --limit=5
     *     wp mx-pos sessions list --cashier=1
     *     wp mx-pos sessions list --format=csv
     *
     * @when after_wp_load
     */
    public function sessions(array $args, array $assoc_args): void
    {
        $action = $args[0] ?? 'list';

        if ($action !== 'list') {
            WP_CLI::error("Unknown action: {$action}. Available: list.");
        }

        $this->sessions_list($assoc_args);
    }

    /**
     * List cash register cuts (X/Z).
     *
     * ## OPTIONS
     *
     * [<action>]
     * : Action: list.
     * ---
     * default: list
     * options:
     *   - list
     * ---
     *
     * [--session=<id>]
     * : Filter by session ID.
     *
     * [--limit=<n>]
     * : Max results when no session is specified.
     * ---
     * default: 20
     * ---
     *
     * [--final-only]
     * : Show only definitive Z cuts.
     *
     * [--format=<format>]
     * : Output format: table, json, csv.
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     *   - csv
     * ---
     *
     * ## EXAMPLES
     *
     *     wp mx-pos cuts list
     *     wp mx-pos cuts list --session=1
     *     wp mx-pos cuts list --final-only
     *     wp mx-pos cuts list --format=json
     *
     * @when after_wp_load
     */
    public function cuts(array $args, array $assoc_args): void
    {
        $action = $args[0] ?? 'list';

        if ($action !== 'list') {
            WP_CLI::error("Unknown action: {$action}. Available: list.");
        }

        $this->cuts_list($assoc_args);
    }

    /**
     * Extended diagnostic for technical support.
     *
     * Combines healthcheck, db-check, caps-check, table counts,
     * operational status, and known risk flags.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format: table, json.
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     * ---
     *
     * [--verbose]
     * : Include detailed breakdown of each section.
     *
     * ## EXAMPLES
     *
     *     wp mx-pos diagnose
     *     wp mx-pos diagnose --format=json
     *     wp mx-pos diagnose --verbose
     *
     * @when after_wp_load
     */
    public function diagnose(array $args, array $assoc_args): void
    {
        $format  = $this->sanitize_format($assoc_args['format'] ?? 'table');
        $verbose = isset($assoc_args['verbose']);

        $report = [];

        $report['plugin_name']    = 'RootLabs POS';
        $report['plugin_version'] = MX_POS_PRO_VERSION;

        $report['healthcheck']   = $this->collect_healthcheck();
        $report['db_results']    = $this->collect_db_results();
        $report['caps_results']  = $this->collect_caps_results();

        $report['counts']        = $this->collect_counts();
        $report['operations']    = $this->collect_operations();
        $report['risk_flags']    = $this->collect_risk_flags();

        $overall_ok = true;

        foreach ($report['healthcheck'] as $c) {
            if ($c['status'] === 'FAIL') {
                $overall_ok = false;
                break;
            }
        }

        if ($overall_ok) {
            foreach ($report['db_results'] as $r) {
                if ($r['status'] === 'FAIL') {
                    $overall_ok = false;
                    break;
                }
            }
        }

        if ($overall_ok) {
            foreach ($report['caps_results'] as $r) {
                if ($r['status'] === 'FAIL') {
                    $overall_ok = false;
                    break;
                }
            }
        }

        $risk_count   = count($report['risk_flags']);
        $report['overall_status'] = $overall_ok ? 'OK' : 'FAIL';
        $report['risk_flag_count'] = $risk_count;

        if ($format === 'json') {
            WP_CLI::line(wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            if (! $overall_ok) {
                WP_CLI::halt(1);
            }

            return;
        }

        WP_CLI::line('');
        WP_CLI::line('RootLabs POS — Diagnostic Report');
        WP_CLI::line(str_repeat('=', 40));
        $status_line = "Status: {$report['overall_status']}";

        if ($risk_count > 0) {
            $status_line .= " ({$risk_count} risk " . ($risk_count === 1 ? 'flag' : 'flags') . ')';
        }

        WP_CLI::line($status_line);
        WP_CLI::line('');

        WP_CLI::line('System');
        WP_CLI::line(str_repeat('-', 40));

        $sys_rows = [];
        foreach ($report['healthcheck'] as $c) {
            $sys_rows[] = [
                'Check'   => $c['check'],
                'Status'  => $c['status'],
                'Details' => $c['details'] ?? '',
            ];
        }
        WP_CLI\Utils\format_items('table', $sys_rows, ['Check', 'Status', 'Details']);
        WP_CLI::line('');

        if ($verbose) {
            $this->render_verbose_section('DB Results', $report['db_results']);
            $this->render_verbose_section('Caps Results', $report['caps_results']);
        }

        WP_CLI::line('Counts');
        WP_CLI::line(str_repeat('-', 40));
        $count_rows = [];
        foreach ($report['counts'] as $label => $value) {
            $count_rows[] = [
                'Resource' => $label,
                'Count'    => (string) $value,
            ];
        }
        WP_CLI\Utils\format_items('table', $count_rows, ['Resource', 'Count']);
        WP_CLI::line('');

        WP_CLI::line('Operational Status');
        WP_CLI::line(str_repeat('-', 40));
        $op_rows = [];
        foreach ($report['operations'] as $label => $value) {
            $op_rows[] = [
                'Metric' => $label,
                'Value'  => (string) $value,
            ];
        }
        WP_CLI\Utils\format_items('table', $op_rows, ['Metric', 'Value']);
        WP_CLI::line('');

        if ($risk_count > 0) {
            WP_CLI::line('Operational Risk Flags');
            WP_CLI::line(str_repeat('-', 40));
            $risk_rows = [];
            foreach ($report['risk_flags'] as $flag) {
                $risk_rows[] = [
                    'Flag'     => $flag['flag'],
                    'Severity' => $flag['severity'],
                ];
            }
            WP_CLI\Utils\format_items('table', $risk_rows, ['Flag', 'Severity']);
            WP_CLI::line('');
        }

        if (! $overall_ok) {
            WP_CLI::error('Diagnostic found failures. Review the report above.', false);
            WP_CLI::halt(1);
        }

        if ($risk_count > 0) {
            WP_CLI::warning(
                "{$risk_count} risk " . ($risk_count === 1 ? 'flag' : 'flags') . '. These are informational and do not block operation.'
            );
        }

        WP_CLI::success('Diagnostic complete.');
    }

    // ── Private: healthcheck collectors ─────────────

    private function check_plugin_loaded(): array
    {
        return [
            'check'   => 'Plugin loaded',
            'status'  => 'OK',
            'details' => 'RootLabs POS ' . MX_POS_PRO_VERSION,
        ];
    }

    private function check_woocommerce(): array
    {
        if (class_exists('WooCommerce')) {
            return [
                'check'   => 'WooCommerce',
                'status'  => 'OK',
                'details' => 'Active',
            ];
        }

        return [
            'check'   => 'WooCommerce',
            'status'  => 'FAIL',
            'details' => 'Not active — WooCommerce is required',
        ];
    }

    private function check_db_version(): array
    {
        $current  = get_option('mx_pos_pro_db_version', '');
        $expected = MX_POS_PRO_DB_VERSION;

        if ($current === $expected) {
            return [
                'check'   => 'DB version',
                'status'  => 'OK',
                'details' => $current,
            ];
        }

        if ($current === '') {
            return [
                'check'   => 'DB version',
                'status'  => 'FAIL',
                'details' => "Not set (expected {$expected})",
            ];
        }

        return [
            'check'   => 'DB version',
            'status'  => 'WARN',
            'details' => "{$current} (expected {$expected}) — pending migration",
        ];
    }

    private function check_tables(): array
    {
        $tables        = array_keys(Schema::get_tables());
        $expected      = count($tables);

        if (! class_exists('WooCommerce')) {
            return [
                'check'   => 'Tables',
                'status'  => 'WARN',
                'details' => 'Skipped (WC not active)',
            ];
        }

        $present = 0;
        foreach ($tables as $table) {
            if ($this->table_exists($table)) {
                $present++;
            }
        }

        if ($present === $expected) {
            return [
                'check'   => 'Tables',
                'status'  => 'OK',
                'details' => "{$present}/{$expected} present",
            ];
        }

        if ($present === 0) {
            return [
                'check'   => 'Tables',
                'status'  => 'FAIL',
                'details' => "{$present}/{$expected} — no tables found",
            ];
        }

        return [
            'check'   => 'Tables',
            'status'  => 'FAIL',
            'details' => "{$present}/{$expected} — missing tables",
        ];
    }

    private function check_options(): array
    {
        $required = [
            'mx_pos_pro_db_version',
        ];

        $optional = [
            'mx_pos_telegram_enabled',
            'mx_pos_telegram_group_id',
            'mx_pos_ticket_business_name',
            'mx_pos_ticket_footer_text',
            'mx_pos_ticket_show_logo',
            'mx_pos_ticket_logo_attachment_id',
            'mx_pos_ticket_apply_logo_to_sales',
            'mx_pos_ticket_apply_logo_to_cuts',
            'mx_pos_ticket_show_store_info',
            'mx_pos_ticket_show_cashier',
            'mx_pos_ticket_show_payment_method',
        ];

        $all     = array_merge($required, $optional);
        $total   = count($all);
        $present = 0;

        foreach ($all as $key) {
            $value = get_option($key, '__mx_sentinel__');
            if ($value !== '__mx_sentinel__') {
                $present++;
            }
        }

        if ($present === $total) {
            return [
                'check'   => 'Options',
                'status'  => 'OK',
                'details' => "{$present}/{$total} present",
            ];
        }

        if ($present === 0) {
            return [
                'check'   => 'Options',
                'status'  => 'FAIL',
                'details' => "{$present}/{$total} — options not initialized",
            ];
        }

        return [
            'check'   => 'Options',
            'status'  => 'WARN',
            'details' => "{$present}/{$total} present",
        ];
    }

    private function check_capabilities(): array
    {
        if (! class_exists('WP_Role')) {
            return [
                'check'   => 'Capabilities',
                'status'  => 'WARN',
                'details' => 'Skipped (WP not fully loaded)',
            ];
        }

        if (Capabilities::has_capabilities()) {
            return [
                'check'   => 'Capabilities',
                'status'  => 'OK',
                'details' => 'Roles valid',
            ];
        }

        return [
            'check'   => 'Capabilities',
            'status'  => 'WARN',
            'details' => 'Some capabilities missing or roles not found',
        ];
    }

    private function check_assets(): array
    {
        $js_file  = MX_POS_PRO_DIR . 'assets/dist/assets/index.js';
        $css_file = MX_POS_PRO_DIR . 'assets/dist/assets/index.css';

        $js_present  = file_exists($js_file);
        $css_present = file_exists($css_file);

        if ($js_present && $css_present) {
            return [
                'check'   => 'Assets build',
                'status'  => 'OK',
                'details' => 'index.js / index.css',
            ];
        }

        if (! $js_present && ! $css_present) {
            return [
                'check'   => 'Assets build',
                'status'  => 'FAIL',
                'details' => 'Build not found — run npm run build',
            ];
        }

        $missing = [];
        if (! $js_present) {
            $missing[] = 'index.js';
        }
        if (! $css_present) {
            $missing[] = 'index.css';
        }

        return [
            'check'   => 'Assets build',
            'status'  => 'WARN',
            'details' => 'Missing: ' . implode(', ', $missing),
        ];
    }

    private function check_pos_route(): array
    {
        $rules = get_option('rewrite_rules');

        if (! is_array($rules)) {
            return [
                'check'   => 'POS route',
                'status'  => 'WARN',
                'details' => 'Rewrite rules not available',
            ];
        }

        foreach ($rules as $rule => $query) {
            if (strpos($query, 'mx_pos_route=pos') !== false) {
                return [
                    'check'   => 'POS route',
                    'status'  => 'OK',
                    'details' => '/pos registered',
                ];
            }
        }

        return [
            'check'   => 'POS route',
            'status'  => 'WARN',
            'details' => '/pos not found in rewrite rules — may need flush',
        ];
    }

    // ── Private: sessions list ─────────────────────

    private function sessions_list(array $assoc_args): void
    {
        global $wpdb;

        if (! class_exists('WooCommerce')) {
            WP_CLI::error('WooCommerce must be active to list sessions.');
        }

        $limit   = $this->sanitize_limit($assoc_args['limit'] ?? null);
        $status  = $this->sanitize_status($assoc_args['status'] ?? 'all');
        $cashier = isset($assoc_args['cashier']) ? absint($assoc_args['cashier']) : null;
        $format  = $this->sanitize_format($assoc_args['format'] ?? 'table');

        $table  = $wpdb->prefix . 'mx_pos_sessions';
        $where  = '1=1';
        $params = [];

        if ($status !== 'all') {
            $where   .= ' AND status = %s';
            $params[] = $status;
        }

        if ($cashier !== null && $cashier > 0) {
            $where   .= ' AND cashier_id = %d';
            $params[] = $cashier;
        }

        $params[] = $limit;
        $sql      = "SELECT * FROM `{$table}` WHERE {$where} ORDER BY opened_at DESC, id DESC LIMIT %d";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL contains only internal table identifiers and fixed WHERE fragments; dynamic values are prepared.
        $prepared = $wpdb->prepare($sql, $params);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is prepared immediately above.
        $rows     = $wpdb->get_results($prepared, ARRAY_A);

        if (! is_array($rows) || count($rows) === 0) {
            WP_CLI::line('No sessions found.');

            return;
        }

        $items = [];
        foreach ($rows as $row) {
            $user_name = $this->resolve_display_name((int) ($row['cashier_id'] ?? 0));

            $items[] = [
                'ID'               => (int) ($row['id'] ?? 0),
                'Cashier ID'       => (int) ($row['cashier_id'] ?? 0),
                'Cashier Name'     => $user_name,
                'Status'           => $row['status'] ?? '',
                'Opening Amount'   => $row['opening_amount'] ?? '0',
                'Closing Expected' => $row['closing_expected'] ?? '',
                'Closing Counted'  => $row['closing_counted'] ?? '',
                'Difference'       => $row['difference'] ?? '',
                'Opened At'        => $row['opened_at'] ?? '',
                'Closed At'        => $row['closed_at'] ?? '',
            ];
        }

        $fields = ['ID', 'Cashier ID', 'Cashier Name', 'Status', 'Opening Amount', 'Closing Expected', 'Closing Counted', 'Difference', 'Opened At', 'Closed At'];

        $this->render_items($format, $items, $fields);
    }

    // ── Private: cuts list ─────────────────────────

    private function cuts_list(array $assoc_args): void
    {
        global $wpdb;

        if (! class_exists('WooCommerce')) {
            WP_CLI::error('WooCommerce must be active to list cuts.');
        }

        $limit     = $this->sanitize_limit($assoc_args['limit'] ?? null);
        $session   = isset($assoc_args['session']) ? absint($assoc_args['session']) : null;
        $finalOnly = isset($assoc_args['final-only']);
        $format    = $this->sanitize_format($assoc_args['format'] ?? 'table');

        $table  = $wpdb->prefix . 'mx_pos_cash_cuts';
        $where  = '1=1';
        $params = [];

        if ($session !== null && $session > 0) {
            $where   .= ' AND session_id = %d';
            $params[] = $session;
        }

        if ($finalOnly) {
            $where   .= ' AND is_final = 1';
        }

        $params[] = $limit;
        $sql      = "SELECT * FROM `{$table}` WHERE {$where} ORDER BY generated_at DESC, id DESC LIMIT %d";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL contains only internal table identifiers and fixed WHERE fragments; dynamic values are prepared.
        $prepared = $wpdb->prepare($sql, $params);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is prepared immediately above.
        $rows     = $wpdb->get_results($prepared, ARRAY_A);

        if (! is_array($rows) || count($rows) === 0) {
            WP_CLI::line('No cuts found.');

            return;
        }

        $items = [];
        foreach ($rows as $row) {
            $user_name = $this->resolve_display_name((int) ($row['generated_by'] ?? 0));

            $item = [
                'ID'           => (int) ($row['id'] ?? 0),
                'Session ID'   => (int) ($row['session_id'] ?? 0),
                'Cut Type'     => $row['cut_type'] ?? '',
                'Sequence'     => (int) ($row['sequence'] ?? 0),
                'Is Final'     => ((int) ($row['is_final'] ?? 0)) === 1 ? 'Yes' : 'No',
                'Generated By' => $user_name,
                'Generated At' => $row['generated_at'] ?? '',
            ];

            if ($format === 'json') {
                $summary = $row['summary_json'] ?? null;
                if (is_string($summary) && $summary !== '') {
                    $decoded = json_decode($summary, true);
                    if (is_array($decoded)) {
                        $item['summary'] = $decoded;
                    }
                }
            }

            $items[] = $item;
        }

        $fields = ['ID', 'Session ID', 'Cut Type', 'Sequence', 'Is Final', 'Generated By', 'Generated At'];

        $this->render_items($format, $items, $fields);
    }

    // ── Private: diagnose collectors ──────────────

    private function collect_healthcheck(): array
    {
        return [
            $this->check_plugin_loaded(),
            $this->check_woocommerce(),
            $this->check_db_version(),
            $this->check_tables(),
            $this->check_capabilities(),
            $this->check_assets(),
            $this->check_pos_route(),
        ];
    }

    private function collect_db_results(): array
    {
        if (! class_exists('WooCommerce')) {
            return [];
        }

        $results = [];
        $tables  = array_keys(Schema::get_tables());

        foreach ($tables as $table) {
            $exists = $this->table_exists($table);
            $results[] = [
                'table'  => $table,
                'check'  => 'Table exists',
                'status' => $exists ? 'OK' : 'FAIL',
            ];

            if (! $exists) {
                continue;
            }

            foreach ($this->get_critical_columns($table) as $col) {
                $col_exists = $this->column_exists($table, $col);
                $results[] = [
                    'table'  => $table,
                    'check'  => "Column: {$col}",
                    'status' => $col_exists ? 'OK' : 'FAIL',
                ];
            }

            foreach ($this->get_critical_indexes($table) as $idx) {
                $idx_exists = $this->index_exists($table, $idx);
                $results[] = [
                    'table'  => $table,
                    'check'  => "Index: {$idx}",
                    'status' => $idx_exists ? 'OK' : 'FAIL',
                ];
            }
        }

        $db_ver       = get_option('mx_pos_pro_db_version', '');
        $expected_ver = MX_POS_PRO_DB_VERSION;
        $results[]    = [
            'table'  => '(options)',
            'check'  => "DB version ({$db_ver} == {$expected_ver})",
            'status' => $db_ver === $expected_ver ? 'OK' : 'FAIL',
        ];

        return $results;
    }

    private function collect_caps_results(): array
    {
        $role_map = Capabilities::role_capability_map();
        $all_caps = Capabilities::capabilities();
        $results  = [];

        foreach ($role_map as $role_name => $expected_caps) {
            $wp_role = get_role($role_name);

            if (! $wp_role instanceof \WP_Role) {
                foreach ($all_caps as $cap) {
                    $results[] = [
                        'role'       => $role_name,
                        'capability' => $cap,
                        'actual'     => 'N/A',
                        'expected'   => in_array($cap, $expected_caps, true) ? 'Yes' : 'No',
                        'status'     => 'FAIL',
                    ];
                }
                continue;
            }

            foreach ($all_caps as $cap) {
                $actual   = $wp_role->has_cap($cap);
                $expected = in_array($cap, $expected_caps, true);
                $results[] = [
                    'role'       => $role_name,
                    'capability' => $cap,
                    'actual'     => $actual ? 'Yes' : 'No',
                    'expected'   => $expected ? 'Yes' : 'No',
                    'status'     => ($actual === $expected) ? 'OK' : 'FAIL',
                ];
            }
        }

        return $results;
    }

    private function collect_counts(): array
    {
        global $wpdb;

        $prefix = $wpdb->prefix;

        $counts       = [];
        $counts['Total sessions']         = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$prefix}mx_pos_sessions`");
        $counts['Open sessions']          = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$prefix}mx_pos_sessions` WHERE status = 'open'");
        $counts['Total cuts']             = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$prefix}mx_pos_cash_cuts`");
        $counts['Total sales']            = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$prefix}mx_pos_sales`");
        $counts['Total refunds']          = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$prefix}mx_pos_refunds`");
        $counts['Parked carts (active)']  = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$prefix}mx_pos_parked_carts` WHERE status = 'parked'");
        $counts['Cash movements']         = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$prefix}mx_pos_cash_movements`");
        $counts['Audit log entries']      = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$prefix}mx_pos_audit_logs`");
        $counts['Product index entries']  = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$prefix}mx_pos_product_index`");

        return $counts;
    }

    private function collect_operations(): array
    {
        global $wpdb;

        $prefix = $wpdb->prefix;
        $ops    = [];

        $open_sessions = $wpdb->get_results(
            "SELECT id, cashier_id, opened_at FROM `{$prefix}mx_pos_sessions` WHERE status = 'open' ORDER BY opened_at DESC LIMIT 5",
            ARRAY_A
        );

        if (is_array($open_sessions) && count($open_sessions) > 0) {
            $lines = [];
            foreach ($open_sessions as $s) {
                $name = $this->resolve_display_name((int) ($s['cashier_id'] ?? 0));
                $lines[] = "ID {$s['id']} ({$name}, since {$s['opened_at']})";
            }
            $ops['Open sessions'] = implode(' | ', $lines);
        } else {
            $ops['Open sessions'] = 'None';
        }

        $last_closed = $wpdb->get_row(
            "SELECT id, cashier_id, closed_at FROM `{$prefix}mx_pos_sessions` WHERE status = 'closed' ORDER BY closed_at DESC LIMIT 1",
            ARRAY_A
        );

        if (is_array($last_closed)) {
            $name = $this->resolve_display_name((int) ($last_closed['cashier_id'] ?? 0));
            $ops['Last closed session'] = "ID {$last_closed['id']} ({$name}, {$last_closed['closed_at']})";
        } else {
            $ops['Last closed session'] = 'None';
        }

        $last_z = $wpdb->get_row(
            "SELECT id, session_id, generated_at FROM `{$prefix}mx_pos_cash_cuts` WHERE cut_type = 'Z' AND is_final = 1 ORDER BY generated_at DESC LIMIT 1",
            ARRAY_A
        );

        if (is_array($last_z)) {
            $ops['Last Z cut'] = "ID {$last_z['id']} (Session {$last_z['session_id']}, {$last_z['generated_at']})";
        } else {
            $ops['Last Z cut'] = 'None';
        }

        $telegram_enabled = get_option('mx_pos_telegram_enabled', 'no') === 'yes';
        $telegram_token = trim((string) get_option('mx_pos_telegram_bot_token', ''));
        $telegram_destination_id = trim((string) get_option('mx_pos_telegram_group_id', ''));
        if ($telegram_destination_id === '') {
            $telegram_destination_id = trim((string) get_option('mx_pos_telegram_chat_id', ''));
        }

        $ops['Telegram configured'] = ($telegram_enabled && $telegram_token !== '' && $telegram_destination_id !== '')
            ? 'Yes'
            : 'No';

        return $ops;
    }

    private function collect_risk_flags(): array
    {
        return [
            ['flag' => 'race-condition-payment',     'severity' => 'WARN'],
            ['flag' => 'toctou-refund',              'severity' => 'WARN'],
            ['flag' => 'rollback-cashout',           'severity' => 'WARN'],
            ['flag' => 'like-reversal',              'severity' => 'WARN'],
            ['flag' => 'dead-user-agent',            'severity' => 'WARN'],
            ['flag' => 'duplicate-ddl',              'severity' => 'WARN'],
        ];
    }

    // ── Private: render helpers ────────────────────

    private function render_verbose_section(string $title, array $results): void
    {
        if (empty($results)) {
            return;
        }

        WP_CLI::line($title);
        WP_CLI::line(str_repeat('-', 40));

        $rows = array_map(function (array $r): array {
            return [
                'Table/Role' => $r['table'] ?? $r['role'] ?? '',
                'Check/Cap'  => $r['check'] ?? $r['capability'] ?? '',
                'Status'     => $r['status'] ?? '',
            ];
        }, $results);

        $fields = ['Table/Role', 'Check/Cap', 'Status'];
        WP_CLI\Utils\format_items('table', $rows, $fields);
        WP_CLI::line('');
    }

    private function render_items(string $format, array $items, array $fields): void
    {
        if ($format === 'json') {
            WP_CLI::line(wp_json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            WP_CLI\Utils\format_items($format, $items, $fields);
        }
    }

    // ── Private: DB introspection helpers ──────────

    private function table_exists(string $table): bool
    {
        global $wpdb;

        $full = $wpdb->prefix . $table;
        $found = $wpdb->get_var(
            $wpdb->prepare('SHOW TABLES LIKE %s', $full)
        );

        return $found === $full;
    }

    private function column_exists(string $table, string $column): bool
    {
        global $wpdb;

        $full  = $wpdb->prefix . $table;
        $found = $wpdb->get_var(
            $wpdb->prepare("SHOW COLUMNS FROM `{$full}` LIKE %s", $column)
        );

        return $found !== null && $found !== '';
    }

    private function index_exists(string $table, string $index): bool
    {
        global $wpdb;

        $full  = $wpdb->prefix . $table;
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = %s
                   AND INDEX_NAME = %s",
                $full,
                $index
            )
        );

        return (int) $count > 0;
    }

    private function get_critical_columns(string $table): array
    {
        $map = [
            'mx_pos_product_index'   => ['object_id', 'catalog_group_id', 'sku_normalized', 'name_normalized', 'is_purchasable', 'display_price', 'image_url', 'index_generation'],
            'mx_pos_sessions'       => ['closed_by', 'close_note', 'denominations_json', 'pos_register_id', 'branch_id', 'pos_employee_id', 'voided_at', 'voided_by', 'void_reason'],
            'mx_pos_sales'          => ['refunded_total', 'branch_id', 'pos_register_id', 'pos_employee_id'],
            'mx_pos_cash_movements' => ['client_request_id', 'created_at', 'branch_id', 'pos_register_id', 'pos_employee_id'],
            'mx_pos_cash_cuts'      => ['is_final', 'summary_json', 'branch_id', 'pos_register_id'],
            'mx_pos_audit_logs'     => ['branch_id', 'pos_register_id', 'pos_employee_id'],
            'mx_pos_branches'       => ['slug', 'is_active'],
            'mx_pos_registers'      => ['branch_id', 'slug', 'is_active'],
            'mx_pos_employees'      => ['username', 'role', 'is_active'],
            'mx_pos_payment_methods' => ['slug', 'affects_cash_register', 'is_active'],
            'mx_pos_order_payments' => ['sale_id', 'payment_method_id', 'amount', 'currency', 'status'],
        ];

        return $map[$table] ?? [];
    }

    private function get_critical_indexes(string $table): array
    {
        $map = [
            'mx_pos_product_index'      => ['object_id', 'sku_normalized', 'name_normalized', 'catalog_group_id', 'status_stock_group', 'index_generation'],
            'mx_pos_cash_cuts'          => ['unique_final_z'],
            'mx_pos_cash_movements'     => ['client_request_id'],
            'mx_pos_branches'           => ['slug'],
            'mx_pos_registers'          => ['slug'],
            'mx_pos_employees'          => ['wp_user_id', 'username'],
            'mx_pos_payment_methods'    => ['slug'],
        ];

        return $map[$table] ?? [];
    }

    private function get_all_columns_for(string $table): array
    {
        $map = [
            'mx_pos_product_index'   => ['object_id', 'product_id', 'variation_id', 'parent_id', 'catalog_group_id', 'sku', 'sku_normalized', 'name', 'name_normalized', 'parent_name', 'variation_label', 'type', 'status', 'is_purchasable', 'stock_quantity', 'stock_status', 'regular_price', 'sale_price', 'display_price', 'min_price', 'max_price', 'image_url', 'image_alt', 'image_version', 'searchable_text', 'index_generation', 'indexed_at'],
            'mx_pos_sessions'        => ['cashier_id', 'register_id', 'pos_register_id', 'branch_id', 'pos_employee_id', 'status', 'opening_amount', 'closing_expected', 'closing_counted', 'difference', 'closed_by', 'close_note', 'denominations_json', 'opened_at', 'closed_at', 'voided_at', 'voided_by', 'void_reason'],
            'mx_pos_sales'           => ['wc_order_id', 'session_id', 'branch_id', 'pos_register_id', 'pos_employee_id', 'cashier_id', 'total', 'refunded_total', 'payment_summary', 'status', 'created_at'],
            'mx_pos_sale_logs'       => ['sale_id', 'event_type', 'message', 'created_by', 'created_at'],
            'mx_pos_refunds'         => ['sale_id', 'wc_refund_id', 'session_id', 'cashier_id', 'refund_type', 'refund_amount', 'refund_method', 'items_data', 'reason', 'client_request_id', 'created_at'],
            'mx_pos_cash_movements'  => ['session_id', 'branch_id', 'pos_register_id', 'pos_employee_id', 'movement_type', 'amount', 'reason', 'created_by', 'client_request_id', 'created_at'],
            'mx_pos_parked_carts'    => ['session_id', 'cashier_id', 'customer_id', 'cart_hash', 'cart_data', 'note', 'status', 'created_at', 'updated_at'],
            'mx_pos_cash_cuts'       => ['session_id', 'branch_id', 'pos_register_id', 'pos_employee_id', 'cut_type', 'sequence', 'summary_json', 'generated_by', 'generated_at', 'is_final'],
            'mx_pos_audit_logs'      => ['actor_id', 'branch_id', 'pos_register_id', 'pos_employee_id', 'action', 'entity_type', 'entity_id', 'ip_address', 'user_agent', 'context_data', 'created_at'],
            'mx_pos_branches'        => ['name', 'slug', 'address', 'phone', 'is_active', 'created_at', 'updated_at'],
            'mx_pos_registers'       => ['branch_id', 'name', 'slug', 'is_active', 'created_at', 'updated_at'],
            'mx_pos_employees'       => ['branch_id', 'wp_user_id', 'username', 'password_hash', 'display_name', 'role', 'is_active', 'deleted_at', 'failed_attempts', 'locked_until', 'last_login_at', 'created_at', 'updated_at'],
            'mx_pos_payment_methods' => ['name', 'slug', 'affects_cash_register', 'is_active', 'sort_order', 'created_at', 'updated_at'],
            'mx_pos_order_payments'  => ['sale_id', 'payment_method_id', 'amount', 'tendered_amount', 'change_amount', 'currency', 'status', 'card_reference', 'transaction_id', 'created_at', 'updated_at'],
        ];

        return $map[$table] ?? [];
    }

    // ── Private: sanitization helpers ──────────────

    private function sanitize_format(string $format): string
    {
        if (in_array($format, self::ALLOWED_FORMATS, true)) {
            return $format;
        }

        if ($format === 'csv') {
            return 'csv';
        }

        return 'table';
    }

    private function sanitize_status(string $status): string
    {
        if (in_array($status, self::ALLOWED_STATUSES, true)) {
            return $status;
        }

        return 'all';
    }

    private function sanitize_role(string $role): string
    {
        if (in_array($role, self::ALLOWED_ROLES, true)) {
            return $role;
        }

        return 'all';
    }

    private function sanitize_limit(mixed $limit): int
    {
        $limit = absint($limit);

        if ($limit < 1) {
            return self::DEFAULT_LIMIT;
        }

        if ($limit > self::MAX_LIMIT) {
            return self::MAX_LIMIT;
        }

        return $limit;
    }

    // ── Private: user helpers ──────────────────────

    private function resolve_display_name(int $user_id): string
    {
        if ($user_id <= 0) {
            return 'Unknown (0)';
        }

        $user = get_userdata($user_id);

        if (! $user instanceof \WP_User) {
            return "Deleted User ({$user_id})";
        }

        $name = $user->display_name;

        return $name !== '' ? $name : "User {$user_id}";
    }
}
