<?php

namespace MXPOSPro\Auth;

defined('ABSPATH') || exit;

use MXPOSPro\Audit\AuditLogger;
use MXPOSPro\Entities\EmployeeRepository;
use WP_Error;

class POSAuthService
{
    private EmployeeRepository $employeeRepo;

    private const COOKIE_NAME        = 'mx_pos_auth_token';
    private const TRANSIENT_PREFIX   = 'mx_pos_auth_';
    private const MAX_FAILED_ATTEMPTS = 5;
    private const LOCK_DURATION      = 300;
    private const TOKEN_BYTES        = 32;

    public function __construct(EmployeeRepository $employeeRepo)
    {
        $this->employeeRepo = $employeeRepo;
    }

    // ── Public API ──────────────────────────────────────

    public function login(string $username, string $password): array|WP_Error
    {
        $result = $this->employeeRepo->verify_credentials($username, $password);

        if (is_wp_error($result)) {
            $code = $result->get_error_code();

            $this->audit_pos_login_failed($username, $result);

            if ($code === 'mx_pos_employee_locked') {
                $this->audit_employee_locked($username);
            }

            return $result;
        }

        $employee_id   = (int) $result['id'];
        $employee_safe = $this->sanitize_employee($result);

        $this->employeeRepo->mark_login_success($employee_id);

        $token = $this->set_auth_cookie($employee_id);

        $this->audit_pos_login_success($employee_id);
        $this->audit_pos_session_created($employee_id, $token);

        return $employee_safe;
    }

    public function validate(): ?array
    {
        $token = $this->read_token_from_cookie();

        if ($token === null || $token === '') {
            return null;
        }

        $employee_id = $this->get_transient_for_token($token);

        if ($employee_id === null) {
            return null;
        }

        $employee = $this->employeeRepo->get_by_id($employee_id);

        if ($employee === null) {
            $this->delete_transient_for_token($token);

            return null;
        }

        if (! (int) $employee['is_active'] || $employee['deleted_at'] !== null) {
            $this->delete_transient_for_token($token);

            return null;
        }

        if ($this->employeeRepo->is_locked($employee)) {
            $this->delete_transient_for_token($token);

            return null;
        }

        return $this->sanitize_employee($employee);
    }

    public function get_current_employee(): ?array
    {
        return $this->validate();
    }

    public function logout(): void
    {
        $token = $this->read_token_from_cookie();

        if ($token !== null && $token !== '') {
            $employee_id = $this->get_transient_for_token($token);

            $this->delete_transient_for_token($token);

            if ($employee_id !== null) {
                $this->audit_pos_logout($employee_id);
                $this->audit_pos_session_destroyed($employee_id);
            }
        }

        $this->clear_auth_cookie();
    }

    // ── Register selection context ─────────────────────

    public function set_selected_register(int $pos_register_id, int $branch_id): bool
    {
        $token = $this->read_token_from_cookie();

        if ($token === null || $token === '') {
            return false;
        }

        $key   = $this->transient_key($token);
        $value = get_transient($key);

        if (! is_array($value) || ! isset($value['pos_employee_id'])) {
            return false;
        }

        $value['selected_register_id'] = $pos_register_id;
        $value['selected_branch_id']   = $branch_id;

        $result = set_transient($key, $value, 0);

        if ($result) {
            AuditLogger::log('pos_register_selected', [
                'entity_type'      => 'pos_register',
                'entity_id'        => $pos_register_id,
                'pos_employee_id'  => $value['pos_employee_id'],
                'pos_register_id'  => $pos_register_id,
                'branch_id'        => $branch_id,
                'actor_type'       => 'pos_employee',
                'severity'         => 'info',
                'message'          => __('Caja seleccionada por el empleado.', 'mx-pos-pro'),
                'metadata'         => [
                    'pos_employee_id' => $value['pos_employee_id'],
                ],
            ]);
        }

        return $result;
    }

    public function get_selected_register(): ?array
    {
        $token = $this->read_token_from_cookie();

        if ($token === null || $token === '') {
            return null;
        }

        $key   = $this->transient_key($token);
        $value = get_transient($key);

        if (! is_array($value)) {
            return null;
        }

        if (
            ! isset($value['selected_register_id'])
            || ! isset($value['selected_branch_id'])
        ) {
            return null;
        }

        $pos_register_id = (int) $value['selected_register_id'];
        $branch_id       = (int) $value['selected_branch_id'];

        if ($pos_register_id <= 0 || $branch_id <= 0) {
            return null;
        }

        return [
            'pos_register_id' => $pos_register_id,
            'branch_id'       => $branch_id,
        ];
    }

    public function clear_selected_register(): bool
    {
        $token = $this->read_token_from_cookie();

        if ($token === null || $token === '') {
            return false;
        }

        $key   = $this->transient_key($token);
        $value = get_transient($key);

        if (! is_array($value)) {
            return false;
        }

        unset($value['selected_register_id'], $value['selected_branch_id']);

        return set_transient($key, $value, 0);
    }

    // ── Cookie management ──────────────────────────────

    public function set_auth_cookie(int $employee_id): string
    {
        $token = $this->generate_token();

        $this->set_transient_for_token($token, $employee_id);

        $this->send_cookie($token);

        return $token;
    }

    public function clear_auth_cookie(): void
    {
        if (headers_sent()) {
            return;
        }

        setcookie(
            self::COOKIE_NAME,
            '',
            [
                'expires'  => time() - 3600,
                'path'     => '/',
                'domain'   => $this->cookie_domain(),
                'secure'   => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
    }

    public static function get_cookie_name(): string
    {
        return self::COOKIE_NAME;
    }

    // ── Private: cookie helpers ────────────────────────

    private function send_cookie(string $token): void
    {
        if (headers_sent()) {
            return;
        }

        setcookie(
            self::COOKIE_NAME,
            $token,
            [
                'expires'  => 0,
                'path'     => '/',
                'domain'   => $this->cookie_domain(),
                'secure'   => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
    }

    private function read_token_from_cookie(): ?string
    {
        if (! isset($_COOKIE[self::COOKIE_NAME])) {
            return null;
        }

        $token = $_COOKIE[self::COOKIE_NAME];

        if (! is_string($token) || $token === '') {
            return null;
        }

        return $token;
    }

    private function cookie_domain(): string
    {
        $host = wp_parse_url(home_url(), PHP_URL_HOST);

        return is_string($host) ? $host : '';
    }

    // ── Private: transient storage ─────────────────────

    private function generate_token(): string
    {
        return bin2hex(random_bytes(self::TOKEN_BYTES));
    }

    private function transient_key(string $token): string
    {
        return self::TRANSIENT_PREFIX . hash('sha256', $token);
    }

    private function set_transient_for_token(string $token, int $employee_id): void
    {
        $key   = $this->transient_key($token);
        $value = [
            'pos_employee_id' => $employee_id,
            'created_at'      => current_time('mysql'),
        ];

        set_transient($key, $value, 0);
    }

    private function get_transient_for_token(string $token): ?int
    {
        $key   = $this->transient_key($token);
        $value = get_transient($key);

        if (! is_array($value) || ! isset($value['pos_employee_id'])) {
            return null;
        }

        $employee_id = (int) $value['pos_employee_id'];

        return $employee_id > 0 ? $employee_id : null;
    }

    private function delete_transient_for_token(string $token): void
    {
        delete_transient($this->transient_key($token));
    }

    // ── Private: sanitization ──────────────────────────

    private function sanitize_employee(array $employee): array
    {
        return [
            'id'           => (int) $employee['id'],
            'username'     => $employee['username'] ?? '',
            'display_name' => $employee['display_name'] ?? '',
            'role'         => $employee['role'] ?? 'cashier',
            'branch_id'    => isset($employee['branch_id']) ? (int) $employee['branch_id'] : null,
        ];
    }

    // ── Private: audit (best-effort) ───────────────────

    private function audit_pos_login_success(int $employee_id): void
    {
        $this->write_audit([
            'action'           => 'pos_login_success',
            'entity_type'      => 'employee',
            'entity_id'        => $employee_id,
            'pos_employee_id'  => $employee_id,
        ]);
    }

    private function audit_pos_login_failed(string $username, WP_Error $error): void
    {
        $this->write_audit([
            'action'       => 'pos_login_failed',
            'entity_type'  => 'employee',
            'context_data' => [
                'username'      => $username,
                'error_code'    => $error->get_error_code(),
                'error_message' => $error->get_error_message(),
            ],
        ]);
    }

    private function audit_employee_locked(string $username): void
    {
        $employee = $this->employeeRepo->get_auth_by_username($username);

        $this->write_audit([
            'action'           => 'pos_employee_locked',
            'entity_type'      => 'employee',
            'entity_id'        => $employee !== null ? (int) $employee['id'] : null,
            'pos_employee_id'  => $employee !== null ? (int) $employee['id'] : null,
            'context_data'     => [
                'username'         => $username,
                'failed_attempts'  => $employee !== null ? (int) $employee['failed_attempts'] : null,
                'locked_until'     => $employee !== null ? $employee['locked_until'] : null,
            ],
        ]);
    }

    private function audit_pos_session_created(int $employee_id, string $token): void
    {
        $this->write_audit([
            'action'           => 'pos_session_created',
            'entity_type'      => 'pos_auth',
            'entity_id'        => $employee_id,
            'pos_employee_id'  => $employee_id,
            'context_data'     => [
                'token_prefix' => substr(hash('sha256', $token), 0, 8),
            ],
        ]);
    }

    private function audit_pos_session_destroyed(int $employee_id): void
    {
        $this->write_audit([
            'action'           => 'pos_session_destroyed',
            'entity_type'      => 'pos_auth',
            'entity_id'        => $employee_id,
            'pos_employee_id'  => $employee_id,
        ]);
    }

    private function audit_pos_logout(int $employee_id): void
    {
        $this->write_audit([
            'action'           => 'pos_logout',
            'entity_type'      => 'pos_auth',
            'entity_id'        => $employee_id,
            'pos_employee_id'  => $employee_id,
        ]);
    }

    private function write_audit(array $data): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mx_pos_audit_logs';

        $found = $wpdb->get_var(
            $wpdb->prepare('SHOW TABLES LIKE %s', $table)
        );

        if ($found !== $table) {
            return;
        }

        try {
            $actor_id     = get_current_user_id() > 0 ? get_current_user_id() : null;
            $ip           = isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR'])
                ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']))
                : '';
            $user_agent   = isset($_SERVER['HTTP_USER_AGENT']) && is_string($_SERVER['HTTP_USER_AGENT'])
                ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']))
                : '';
            $context_data = isset($data['context_data']) && is_array($data['context_data'])
                ? wp_json_encode($data['context_data'])
                : null;

            $wpdb->insert(
                $table,
                [
                    'actor_id'         => $actor_id,
                    'branch_id'        => null,
                    'pos_register_id'  => null,
                    'pos_employee_id'  => $data['pos_employee_id'] ?? null,
                    'action'           => $data['action'] ?? '',
                    'entity_type'      => $data['entity_type'] ?? '',
                    'entity_id'        => $data['entity_id'] ?? null,
                    'ip_address'       => $ip,
                    'user_agent'       => $user_agent,
                    'context_data'     => $context_data,
                    'created_at'       => current_time('mysql'),
                ],
                ['%d', '%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s']
            );
        } catch (\Exception $e) {
            error_log(
                sprintf(
                    '[MX POS Pro] Audit write failed for action=%s: %s',
                    $data['action'] ?? 'unknown',
                    $e->getMessage()
                )
            );
        }
    }
}
