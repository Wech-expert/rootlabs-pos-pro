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

namespace MXPOSPro\Audit;

defined('ABSPATH') || exit;

class AuditLogger
{
    private const SENSITIVE_KEYS = [
        'password',
        'password_hash',
        'token',
        'nonce',
        'cookie',
        'authorization',
        'card',
        'card_number',
        'card_reference_full',
        'request_payload',
        'response_payload',
        'secret',
        'api_key',
    ];

    private const MAX_STRING_LENGTH = 1000;
    private const MAX_USER_AGENT = 500;

    public static function log(string $action, array $params = []): void
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
            $ip         = isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR'])
                ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']))
                : '';
            $user_agent = isset($_SERVER['HTTP_USER_AGENT']) && is_string($_SERVER['HTTP_USER_AGENT'])
                ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']))
                : '';

            $user_agent = mb_substr($user_agent, 0, self::MAX_USER_AGENT);

            $wp_user_id    = get_current_user_id() > 0 ? get_current_user_id() : null;
            $actor_id      = $params['actor_id'] ?? $wp_user_id;
            $actor_type    = self::resolve_actor_type($params, $wp_user_id);

            $entity_type   = $params['entity_type'] ?? '';
            $entity_id     = $params['entity_id'] ?? null;

            $pos_employee_id = $params['pos_employee_id'] ?? null;
            $branch_id       = $params['branch_id'] ?? null;
            $pos_register_id = $params['pos_register_id'] ?? null;

            $context = [];

            if (isset($params['cash_session_id'])) {
                $context['cash_session_id'] = $params['cash_session_id'];
            }

            if (isset($params['sale_id'])) {
                $context['sale_id'] = $params['sale_id'];
            }

            $context['actor_type'] = $actor_type;

            if ($wp_user_id !== null) {
                $context['wp_user_id'] = $wp_user_id;
            }

            if (isset($params['severity'])) {
                $context['severity'] = $params['severity'];
            } else {
                $context['severity'] = 'info';
            }

            if (isset($params['message']) && is_string($params['message']) && $params['message'] !== '') {
                $context['message'] = mb_substr($params['message'], 0, self::MAX_STRING_LENGTH);
            }

            if (isset($params['metadata']) && is_array($params['metadata']) && ! empty($params['metadata'])) {
                $context['metadata'] = self::sanitize_metadata($params['metadata']);
            }

            $context_data = wp_json_encode($context);

            if (! is_string($context_data)) {
                $context_data = null;
            }

            $wpdb->insert(
                $table,
                [
                    'actor_id'         => $actor_id,
                    'branch_id'        => $branch_id,
                    'pos_register_id'  => $pos_register_id,
                    'pos_employee_id'  => $pos_employee_id,
                    'action'           => $action,
                    'entity_type'      => $entity_type,
                    'entity_id'        => $entity_id,
                    'ip_address'       => $ip,
                    'user_agent'       => $user_agent,
                    'context_data'     => $context_data,
                    'created_at'       => current_time('mysql'),
                ],
                ['%d', '%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s']
            );
        } catch (\Exception $e) {
        }
    }

    private static function resolve_actor_type(array $params, ?int $wp_user_id): string
    {
        if (isset($params['actor_type']) && in_array($params['actor_type'], ['wp_admin', 'pos_employee', 'system'], true)) {
            return $params['actor_type'];
        }

        if (isset($params['pos_employee_id']) && (int) $params['pos_employee_id'] > 0) {
            return 'pos_employee';
        }

        if ($wp_user_id !== null) {
            return 'wp_admin';
        }

        return 'system';
    }

    private static function sanitize_metadata(array $metadata): array
    {
        $clean = [];

        foreach ($metadata as $key => $value) {
            $key_lower = strtolower((string) $key);

            if (self::is_sensitive_key($key_lower)) {
                $clean[$key] = '[FILTERED]';
                continue;
            }

            if (is_string($value)) {
                $clean[$key] = mb_substr($value, 0, self::MAX_STRING_LENGTH);
            } elseif (is_array($value)) {
                $clean[$key] = self::sanitize_metadata($value);
            } elseif (is_scalar($value) || $value === null) {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }

    private static function is_sensitive_key(string $key): bool
    {
        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if (strpos($key, $sensitive) !== false) {
                return true;
            }
        }

        return false;
    }
}
