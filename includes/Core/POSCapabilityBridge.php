<?php

declare(strict_types=1);


/**
 * Request superglobals are checked/sanitized before operational use.
 *
 * rootlabs-pos-pro-w2a-request-superglobals
 *
 * phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotValidated
 */

namespace MXPOSPro\Core;

use MXPOSPro\Auth\POSAuthService;
use MXPOSPro\Entities\EmployeeRepository;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class POSCapabilityBridge
{
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;

        add_filter('user_has_cap', [self::class, 'grant_pos_caps'], 10, 4);
    }

    /**
     * Grants operational POS capabilities to an authenticated POS employee
     * only inside the POS frontend or mx-pos/v1 REST namespace.
     *
     * This intentionally does not grant manage_options or admin capabilities.
     *
     * @param array<string,bool> $allcaps
     * @param array<int,string>  $caps
     * @param array<int,mixed>   $args
     * @param mixed              $user
     *
     * @return array<string,bool>
     */
    public static function grant_pos_caps(array $allcaps, array $caps, array $args, $user): array
    {
        if (! self::is_pos_context()) {
            return $allcaps;
        }

        $requested = isset($args[0]) ? (string) $args[0] : '';

        $pos_caps = [
            'mx_pos_access',
            'mx_pos_sell',
            'mx_pos_cash_cut',
            'mx_pos_apply_discount',
            'mx_pos_refund',
        
            'mx_pos_close_session',
            'mx_pos_close_cash_session',
            'mx_pos_session_close',
            'mx_pos_manage_cash_sessions',
            'mx_pos_open_session',
            'mx_pos_manage_cash',
        ];

        $needs_pos_cap = in_array($requested, $pos_caps, true);

        if (! $needs_pos_cap) {
            foreach ($caps as $cap) {
                if (in_array((string) $cap, $pos_caps, true)) {
                    $needs_pos_cap = true;
                    break;
                }
            }
        }

        if (! $needs_pos_cap) {
            return $allcaps;
        }

        $employee = self::get_current_pos_employee();

        if ($employee === null) {
            return $allcaps;
        }

        $granted = self::capabilities_for_employee($employee);

        foreach ($granted as $cap => $allowed) {
            if ($allowed) {
                $allcaps[$cap] = true;
            }
        }

        return $allcaps;
    }

    private static function is_pos_context(): bool
    {
        $mx_pos_pro_request_uri = isset( $mx_pos_pro_request_uri )
            ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
            : '';

        $uri = isset($mx_pos_pro_request_uri) ? (string) $mx_pos_pro_request_uri : '';
        $path = (string) wp_parse_url($uri, PHP_URL_PATH);

        if (strpos($path, '/wp-json/mx-pos/v1/') !== false) {
            return true;
        }

        if (strpos($uri, 'rest_route=') !== false && strpos($uri, 'mx-pos/v1') !== false) {
            return true;
        }

        if ($path === '/pos' || $path === '/pos/') {
            return true;
        }

        return false;
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function get_current_pos_employee(): ?array
    {
        static $cached = false;
        static $employee = null;

        if ($cached) {
            return is_array($employee) ? $employee : null;
        }

        $cached = true;

        try {
            $auth = new POSAuthService(new EmployeeRepository());
            $employee = $auth->get_current_employee();
        } catch (\Throwable $e) {
            $employee = null;
        }

        if (! is_array($employee)) {
            return null;
        }

        if (isset($employee['is_active']) && (int) $employee['is_active'] !== 1) {
            return null;
        }

        if (! empty($employee['deleted_at'])) {
            return null;
        }

        if (! empty($employee['locked_until'])) {
            $locked_until = strtotime((string) $employee['locked_until']);

            if ($locked_until !== false && $locked_until > time()) {
                return null;
            }
        }

        return $employee;
    }

    /**
     * @param array<string,mixed> $employee
     *
     * @return array<string,bool>
     */
    private static function capabilities_for_employee(array $employee): array
    {
        $role = strtolower((string) ($employee['role'] ?? ''));

        $caps = [
            'mx_pos_access'         => true,
            'mx_pos_sell'           => true,
            'mx_pos_cash_cut'       => true,
            'mx_pos_apply_discount' => false,
            'mx_pos_refund'         => false,
            'mx_pos_open_session'   => true,
            'mx_pos_manage_cash'    => true,
        
            'mx_pos_close_session' => true,
            'mx_pos_close_cash_session' => true,
            'mx_pos_session_close' => true,
            'mx_pos_manage_cash_sessions' => true,];

        if (in_array($role, ['admin', 'administrator', 'manager', 'supervisor', 'encargado'], true)) {
            $caps['mx_pos_apply_discount'] = true;
            $caps['mx_pos_refund'] = true;
        }

        return $caps;
    }
}

POSCapabilityBridge::register();
