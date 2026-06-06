<?php

namespace MXPOSPro\Core;

defined('ABSPATH') || exit;

class Activation
{
    public static function activate(): void
    {
        if (! function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if (! is_plugin_active('woocommerce/woocommerce.php')) {
            deactivate_plugins(plugin_basename(MX_POS_PRO_FILE));

            wp_die(
                esc_html__(
                    'RootLabs POS requires WooCommerce to be installed and activated.',
                    'mx-pos-pro'
                ),
                esc_html__('Plugin Activation Error', 'mx-pos-pro'),
                ['back_link' => true]
            );
        }

        require_once MX_POS_PRO_INCLUDES . 'Database/Schema.php';
        require_once MX_POS_PRO_INCLUDES . 'Database/Migrator.php';

        \MXPOSPro\Database\Migrator::run();

        require_once MX_POS_PRO_INCLUDES . 'Core/Capabilities.php';

        Capabilities::install();

        require_once MX_POS_PRO_INCLUDES . 'Frontend/PosRoute.php';

        \MXPOSPro\Frontend\PosRoute::register_rewrite_rules();

        add_option('mx_pos_telegram_enabled', 'no', '', 'no');
        add_option('mx_pos_telegram_bot_token', '', '', 'no');
        add_option('mx_pos_telegram_chat_id', '', '', 'no');
        add_option('mx_pos_telegram_group_id', '', '', 'no');

        add_option('mx_pos_ticket_business_name', '', '', 'yes');
        add_option('mx_pos_ticket_footer_text', '', '', 'yes');
        add_option('mx_pos_ticket_paper_width', '80mm', '', 'yes');
        add_option('mx_pos_ticket_show_logo', 'no', '', 'yes');
        add_option('mx_pos_ticket_logo_attachment_id', 0, '', 'yes');
        add_option('mx_pos_ticket_apply_logo_to_sales', 'yes', '', 'yes');
        add_option('mx_pos_ticket_apply_logo_to_cuts', 'no', '', 'yes');
        add_option('mx_pos_ticket_show_store_info', 'yes', '', 'yes');
        add_option('mx_pos_ticket_show_cashier', 'yes', '', 'yes');
        add_option('mx_pos_ticket_show_payment_method', 'yes', '', 'yes');

        flush_rewrite_rules();
    }
}
