<?php

/**
 * Template variables are local view variables provided by the POS route renderer.
 *
 * rootlabs-pos-pro-w2a-template-vars
 *
 * phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
 * phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
 */

/**
 * Non-destructive uninstall routine for RootLabs POS.
 *
 * Uninstall normal preserves business data. Sales, cash sessions,
 * movements, parked carts, refunds, audit logs, product index rows,
 * branches, registers, employees, payment methods, order payments,
 * and WooCommerce orders are intentionally kept.
 *
 * Destructive table cleanup must not run here. If a future release needs
 * destructive cleanup, it should be exposed as an explicit WP-CLI operation
 * with confirmation.
 *
 * @package MXPOSPro
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

$capabilities_file = __DIR__ . '/includes/Core/Capabilities.php';

if (is_readable($capabilities_file)) {
    require_once $capabilities_file;

    if (class_exists('MXPOSPro\\Core\\Capabilities')) {
        \MXPOSPro\Core\Capabilities::remove_capabilities();
    }
}

// No plugin-owned scheduled hooks are registered in RC1.
// Plugin settings and database tables are preserved to prevent accidental business data loss
// and to avoid accidental loss of POS business history.
