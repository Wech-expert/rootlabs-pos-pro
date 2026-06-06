<?php

namespace MXPOSPro\Core;

defined('ABSPATH') || exit;

class Compatibility
{
    public static function check(): bool
    {
        if (! class_exists('WooCommerce')) {
            add_action('admin_notices', [self::class, 'render_missing_wc_notice']);

            return false;
        }

        return true;
    }

    public static function render_missing_wc_notice(): void
    {
        $message = __(
            'RootLabs POS requires WooCommerce to be installed and activated.',
            'mx-pos-pro'
        );

        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html($message)
        );
    }

    public static function declare_hpos_compatibility(): void
    {
        if (! class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            return;
        }

        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            MX_POS_PRO_FILE,
            true
        );
    }
}
