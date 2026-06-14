<?php

namespace MXPOSPro\Notifications;

defined('ABSPATH') || exit;

class TelegramNotificationService
{
    private bool $enabled;
    private string $botToken;
    private string $destinationId;
    private const TIMEOUT_SECONDS = 2;

    public function __construct()
    {
        $this->enabled  = get_option('mx_pos_telegram_enabled', 'no') === 'yes';
        $this->botToken = (string) get_option('mx_pos_telegram_bot_token', '');
        $this->destinationId = self::resolve_destination_id();
    }

    public function register_hooks(): void
    {
        add_action('mx_pos_cash_session_opened', [$this, 'on_session_opened'], 10, 1);
        add_action('mx_pos_cash_session_closed', [$this, 'on_session_closed'], 10, 1);
        add_action('mx_pos_cash_difference_detected', [$this, 'on_difference_detected'], 10, 1);
        add_action('mx_pos_sale_cancelled', [$this, 'on_sale_cancelled'], 10, 1);
        add_action('mx_pos_sale_refunded', [$this, 'on_sale_refunded'], 10, 1);
        add_action('mx_pos_cut_z_generated', [$this, 'on_cut_z_generated'], 10, 1);
    }

    public function on_session_opened(array $context): void
    {
        $this->notify(NotificationFormatter::session_opened($context), 'session_opened', $context);
    }

    public function on_session_closed(array $context): void
    {
        $this->notify(NotificationFormatter::session_closed($context), 'session_closed', $context);
    }

    public function on_difference_detected(array $context): void
    {
        $this->notify(NotificationFormatter::difference_detected($context), 'difference_detected', $context);
    }

    public function on_sale_cancelled(array $context): void
    {
        $this->notify(NotificationFormatter::sale_cancelled($context), 'sale_cancelled', $context);
    }

    public function on_sale_refunded(array $context): void
    {
        $this->notify(NotificationFormatter::sale_refunded($context), 'sale_refunded', $context);
    }

    public function on_cut_z_generated(array $context): void
    {
        $this->notify(NotificationFormatter::cut_z_generated($context), 'cut_z_generated', $context);
    }

    private function notify(string $message, string $event, array $context): void
    {
        if (! $this->enabled) {
            return;
        }

        if ($this->botToken === '' || $this->destinationId === '') {
            return;
        }

        $url = sprintf(
            'https://api.telegram.org/bot%s/sendMessage',
            $this->botToken
        );

        $body = [
            'chat_id' => $this->destinationId,
            'text'    => $message,
            'parse_mode' => 'HTML',
        ];

        try {
            $response = wp_remote_post($url, [
                'body'    => $body,
                'timeout' => self::TIMEOUT_SECONDS,
            ]);

            if (is_wp_error($response)) {
                $this->log_error($event, $context, $response->get_error_message());

                return;
            }

            $statusCode = wp_remote_retrieve_response_code($response);

            if ($statusCode < 200 || $statusCode >= 300) {
                $bodyStr = wp_remote_retrieve_body($response);
                $this->log_error(
                    $event,
                    $context,
                    sprintf('HTTP %d: %s', $statusCode, $bodyStr !== '' ? $bodyStr : 'Unknown error')
                );
            }
        } catch (\Exception $e) {
            $this->log_error($event, $context, $e->getMessage());
        }
    }

    private function log_error(string $event, array $context, string $errorMessage): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mx_pos_audit_logs';

        $truncatedError = mb_substr($errorMessage, 0, 500);
        $ipAddress = isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR'])
            ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']))
            : '';

        $wpdb->insert(
            $table,
            [
                'actor_id'     => $context['cashier_id'] ?? $context['generated_by'] ?? 0,
                'action'       => 'telegram_notification_failed',
                'entity_type'  => 'notification',
                'entity_id'    => $context['session_id'] ?? $context['sale_id'] ?? $context['cut_id'] ?? 0,
                'ip_address'   => $ipAddress,
                'context_data' => wp_json_encode([
                    'event'         => $event,
                    'error'         => $truncatedError,
                    'notification_context' => $context,
                ]),
                'created_at'   => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%d', '%s', '%s', '%s']
        );
    }

    public static function test_connection(string $bot_token, string $destination_id): bool|string
    {
        $url = sprintf(
            'https://api.telegram.org/bot%s/sendMessage',
            $bot_token
        );

        $body = [
            'chat_id'    => $destination_id,
            'text'       => 'MX POS Pro — Test connection',
            'parse_mode' => 'HTML',
        ];

        try {
            $response = wp_remote_post($url, [
                'body'    => $body,
                'timeout' => 5,
            ]);

            if (is_wp_error($response)) {
                return $response->get_error_message();
            }

            $statusCode = wp_remote_retrieve_response_code($response);

            if ($statusCode < 200 || $statusCode >= 300) {
                $bodyStr = wp_remote_retrieve_body($response);

                return sprintf('HTTP %d: %s', $statusCode, $bodyStr !== '' ? $bodyStr : 'Unknown error');
            }

            return true;
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    private static function resolve_destination_id(): string
    {
        $groupId = trim((string) get_option('mx_pos_telegram_group_id', ''));

        if ($groupId !== '') {
            return $groupId;
        }

        return trim((string) get_option('mx_pos_telegram_chat_id', ''));
    }
}
