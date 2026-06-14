<?php
/**
 * RootLabs POS asset cache bust.
 *
 * Fuerza versión por filemtime para los assets compilados del POS.
 * Esto evita que el navegador siga usando index.js/index.css viejos
 * cuando se aplican actualizaciones directas en producción.
 */

if (! defined('ABSPATH')) {
    return;
}

add_filter('script_loader_src', 'rootlabs_pos_asset_cache_bust_src', 999, 2);
add_filter('style_loader_src', 'rootlabs_pos_asset_cache_bust_src', 999, 2);

function rootlabs_pos_asset_cache_bust_src($src, $handle) {
    if (! is_string($src) || $src === '') {
        return $src;
    }

    $plugin_slugs = array(
        'mx-pos-pro',
        'rootlabs-pos-for-woocommerce',
    );

    $asset_names = array(
        'index.js',
        'index.css',
    );

    foreach ($plugin_slugs as $plugin_slug) {
        foreach ($asset_names as $asset_name) {
            $needle = '/wp-content/plugins/' . $plugin_slug . '/assets/dist/assets/' . $asset_name;

            if (strpos($src, $needle) === false) {
                continue;
            }

            $file = WP_PLUGIN_DIR . '/' . $plugin_slug . '/assets/dist/assets/' . $asset_name;

            if (! file_exists($file)) {
                return $src;
            }

            return add_query_arg(
                'ver',
                (string) filemtime($file),
                remove_query_arg('ver', $src)
            );
        }
    }

    return $src;
}
