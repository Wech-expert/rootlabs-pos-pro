<?php


/**
 * Template variables are local view variables provided by the POS route renderer.
 *
 * rootlabs-pos-pro-w2a-template-vars
 *
 * phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
 * phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
 */

use MXPOSPro\Core\Assets;

defined('ABSPATH') || exit;

$asset_data = Assets::pos_asset_data();

?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php echo esc_attr(get_bloginfo('charset', 'display')); ?>" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="robots" content="noindex,nofollow" />
    <title><?php echo esc_html__('RootLabs POS', 'mx-pos-pro'); ?></title>
    <style>
        html,
        body {
            margin: 0;
            min-height: 100%;
            background: #f9f9f9;
        }
    </style>
    <?php if ($asset_data): ?>
        <?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- Standalone POS shell prints the compiled Vite stylesheet directly. ?>
        <link rel="stylesheet" href="<?php echo esc_url($asset_data['css_url']); ?>" />
    <?php endif; ?>
</head>
<body class="mx-pos-pro-pos">
    <div id="mx-pos-pro-root">
        <?php if (! $asset_data): ?>
            <div style="max-width:480px;margin:48px auto;padding:32px 48px;background:#fff;border:1px solid #e2e2e2;border-radius:8px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:#1b1b1b;text-align:center;">
                <p style="font-size:24px;font-weight:700;margin:0 0 8px;">RootLabs POS</p>
                <p style="font-size:14px;color:#7e7e7e;margin:0;">
                    <?php esc_html_e('React build not found. Run npm run build to compile assets.', 'mx-pos-pro'); ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
    <?php if ($asset_data): ?>
        <script>
            window.mxPosProSettings = <?php echo wp_json_encode($asset_data['settings'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        </script>
        <?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- Standalone POS shell prints the compiled Vite script directly. ?>
        <script type="module" src="<?php echo esc_url($asset_data['js_url']); ?>"></script>
    <?php endif; ?>
</body>
</html>
