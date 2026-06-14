<?php

namespace MXPOSPro\Core;

defined('ABSPATH') || exit;

class Assets
{
    public function register(): void
    {
        // Assets are printed by the standalone /pos shell, not by wp-admin.
    }

    public static function pos_asset_data(): ?array
    {
        $plugin_dir = plugin_dir_path(MX_POS_PRO_FILE);
        $js_file    = $plugin_dir . 'assets/dist/assets/index.js';
        $css_file   = $plugin_dir . 'assets/dist/assets/index.css';

        if (! file_exists($js_file) || ! file_exists($css_file)) {
            return null;
        }

        $js_version  = (string) filemtime($js_file);
        $css_version = (string) filemtime($css_file);
        $pos_logout_url = html_entity_decode(
            wp_nonce_url(
                home_url('/pos?mx_pos_action=logout'),
                'mx_pos_logout',
                'mx_pos_logout_nonce'
            ),
            ENT_QUOTES,
            'UTF-8'
        );

        return [
            'js_url'   => esc_url(plugins_url('assets/dist/assets/index.js', MX_POS_PRO_FILE) . '?ver=' . rawurlencode($js_version)),
            'css_url'  => esc_url(plugins_url('assets/dist/assets/index.css', MX_POS_PRO_FILE) . '?ver=' . rawurlencode($css_version)),
            'settings' => [
                'nonce'        => wp_create_nonce('wp_rest'),
                'root'         => esc_url_raw(rest_url('mx-pos/v1/')),
                'posUrl'       => esc_url_raw(home_url('/pos')),
                'posLogoutUrl' => esc_url_raw($pos_logout_url),
                'context'      => 'pos',
                'beepEnabled'  => get_option('mx_pos_beep_enabled', 'yes') === 'yes',
                'capabilities' => [
                    'canApplyDiscount' => current_user_can('mx_pos_apply_discount'),
                    'canRefund'        => current_user_can('mx_pos_refund'),
                    'canCashCut'       => current_user_can('mx_pos_cash_cut'),
                ],
            ],
        ];
    }

    public static function print_pos_styles(): void
    {
        $asset_data = self::pos_asset_data();

        if (! $asset_data) {
            return;
        }

        printf(
            '<link rel="stylesheet" href="%s" />' . "\n",
            esc_url($asset_data['css_url'])
        );
    }

    public static function print_pos_runtime(): void
    {
        $asset_data = self::pos_asset_data();

        if (! $asset_data) {
            return;
        }

        printf(
            '<script>window.mxPosProSettings = %s;</script>' . "\n",
            wp_json_encode($asset_data['settings'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
        );
        printf(
            '<script type="module" src="%s"></script>' . "\n",
            esc_url($asset_data['js_url'])
        );
    }
}
