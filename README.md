# MX POS Pro v1.0.0-rc1

**Release Candidate — Not for production use.**

MX POS Pro is a premium Point of Sale plugin for WordPress + WooCommerce. It provides a complete POS system with a React interface and native WooCommerce synchronization.

---

## Important: This is a Release Candidate (RC1)

This version is intended for **testing in staging or test environments only**. Do not install on a live production site. Report any issues found during testing.

---

## Requirements

| Requirement | Minimum Version |
|---|---|
| WordPress | 6.0 |
| WooCommerce | 8.0 |
| PHP | 8.0 |

WooCommerce must be installed and activated before activating MX POS Pro.

---

## Installation

1. Download `mx-pos-pro-v1.0.0-rc1.zip`
2. Go to **wp-admin > Plugins > Add New > Upload Plugin**
3. Upload the ZIP file and click **Install Now**
4. Click **Activate Plugin**

The plugin will automatically:
- Create the required database tables
- Register POS capabilities for administrator, shop_manager, and mx_pos_cashier roles
- Register the `/pos` URL route

---

## Access

| URL | Purpose | Required Capability |
|---|---|---|
| `/pos` | POS frontend | `mx_pos_access` |
| `wp-admin > MX POS Pro` | Dashboard | `mx_pos_access` |
| `wp-admin > MX POS Pro > Settings` | Plugin settings | `mx_pos_manage_settings` |

The POS frontend requires login. Unauthenticated users are redirected to the WordPress login page.

---

## Roles & Capabilities

| Capability | Administrator | Shop Manager | POS Cashier |
|---|---|---|---|
| `mx_pos_access` | Yes | Yes | Yes |
| `mx_pos_sell` | Yes | Yes | Yes |
| `mx_pos_refund` | Yes | Yes | No |
| `mx_pos_open_session` | Yes | Yes | Yes |
| `mx_pos_close_session` | Yes | Yes | Yes |
| `mx_pos_apply_discount` | Yes | Yes | No |
| `mx_pos_cash_cut` | Yes | Yes | Yes |
| `mx_pos_manage_cash` | Yes | Yes | Yes |
| `mx_pos_manage_settings` | Yes | No | No |

The **mx_pos_cashier** role is created automatically on plugin activation.

---

## Quick Start

1. Activate the plugin
2. Go to **MX POS Pro > Settings** and configure Telegram (optional) and ticket preferences
3. Create a user with the **POS Cashier** role (or use an administrator account)
4. Log in at `/pos`
5. Open a cash session with your opening amount
6. Start selling

---

## WP-CLI Diagnostics

Run these commands from the terminal for diagnostics and support:

```
wp mx-pos healthcheck      # General plugin status check
wp mx-pos db-check         # Validate database schema
wp mx-pos caps-check       # Validate role capabilities
wp mx-pos sessions list    # List cash register sessions
wp mx-pos cuts list        # List X/Z cuts
wp mx-pos diagnose         # Extended diagnostic report
wp mx-pos index stats      # Product index statistics
wp mx-pos index rebuild    # Rebuild product search index
```

All commands are read-only by default. Use `--format=json` for machine-readable output.

---

## Database Schema v1.5

The plugin database schema has been extended to prepare for multi-branch, multi-register, and multi-employee operation in future releases.

### New entities (structure only — not yet used in POS UI)

| Table | Purpose | Status |
|---|---|---|
| `mx_pos_branches` | Physical store locations | Schema ready, seed `main` created |
| `mx_pos_registers` | Physical cash register terminals | Schema ready, seed `main` created |
| `mx_pos_employees` | Internal POS employees (separate from WP users) | Schema ready |
| `mx_pos_payment_methods` | Configurable POS payment methods | Schema ready, seeds `cash`/`card`/`mixed` created |
| `mx_pos_order_payments` | Split payment support (multiple methods per sale) | Schema ready |

**Note:** Sprint 1 prepares the database foundation. POS login, admin UI for entities, multi-method payments, and full multi-branch operation are not yet active. Existing POS flows (sales, sessions, payments) are preserved and continue to work as before.

All existing data is preserved. Historical records are backfilled to the default branch/register where safely assignable. Uninstall remains non-destructive.

## Database Diagnostics

**Plugin fails to activate**
- Ensure WooCommerce is installed and activated
- Ensure PHP version is 8.0 or higher
- Check the WordPress debug log for errors

**POS page not loading**
- Verify rewrite rules are flushed: go to **Settings > Permalinks** and click **Save Changes**
- Run `wp mx-pos healthcheck` to check plugin status

**Database issues**
- Run `wp mx-pos db-check` to validate schema
- Re-activate the plugin to trigger migrations

---

## Uninstall

When deleting the plugin from WordPress, the physical plugin files are removed only if the web server has sufficient filesystem ownership and permissions. If WordPress cannot delete the plugin directory, check the local file owner/group and write permissions.

The RC1 uninstall routine is intentionally non-destructive. It removes the POS role/capabilities created by the plugin, but preserves database tables, settings, WooCommerce orders, sales history, cash sessions, cash movements, parked carts, refunds, and audit logs to prevent accidental data loss during testing.

Destructive cleanup of plugin tables is not performed by normal uninstall. If it is needed in a future release, it should be run through an explicit WP-CLI cleanup command with confirmation.

---

## Support

For diagnostics, run:

```
wp mx-pos diagnose --verbose
```

Provide the output when reporting issues. Never share your Telegram bot token.

---

## License

GPL-2.0+
