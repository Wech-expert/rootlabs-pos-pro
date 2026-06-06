# RootLabs POS for WooCommerce — Installation Guide

## Prerequisites

| Requirement | Minimum | Recommended |
|---|---|---|
| WordPress | 6.0 | Latest |
| WooCommerce | 8.0 | Latest |
| PHP | 8.0 | 8.1+ |
| PHP Extensions | — | mbstring, json, curl, dom |
| MySQL / MariaDB | 5.7 / 10.2 | Latest |

WooCommerce must be installed, activated, and configured before installing RootLabs POS.

---

## Installation Steps

### 1. Download the ZIP

Obtain `rootlabs-pos-for-woocommerce-v0.1.0.zip` from the release package.

### 2. Upload via WordPress Admin

1. Log in to **wp-admin**
2. Go to **Plugins > Add New**
3. Click **Upload Plugin**
4. Choose `rootlabs-pos-for-woocommerce-v0.1.0.zip`
5. Click **Install Now**

### 3. Activate the Plugin

1. After installation completes, click **Activate Plugin**
2. The plugin will automatically:
   - Create 9 database tables (`wp_mx_pos_*`)
   - Register POS capabilities
   - Create the `mx_pos_cashier` role
   - Register the `/pos` URL route
   - Add the "RootLabs POS" admin menu

### 4. Verify Installation

#### Via WP-CLI

```bash
wp mx-pos healthcheck
```

Expected output: All checks pass with status **OK**.

```bash
wp mx-pos db-check
```

Expected output: All tables, columns, and indexes pass.

```bash
wp mx-pos caps-check
```

Expected output: All capabilities assigned correctly.

#### Via WordPress Admin

- A new menu item **RootLabs POS** appears in the admin sidebar
- Navigate to `/pos` — you should see the login page (if logged out) or the POS interface

### 5. Configure Permalinks

If `/pos` returns a 404:

1. Go to **Settings > Permalinks**
2. Click **Save Changes** (flushes rewrite rules)
3. Try `/pos` again

### 6. Assign Cashier Users

1. Go to **Users > Add New** or edit an existing user
2. Set the role to **POS Cashier** (for cashiers) or keep **Administrator** / **Shop Manager** for full access
3. The user can now log in at `/pos`

### 7. Configure Settings

Go to **RootLabs POS > Settings** to configure:

- **Telegram notifications** (bot token, chat ID) — optional
- **Ticket 58mm** customization (business name, logo, footer, visibility)

### 8. First POS Session

1. Log in at `/pos` as a user with POS capabilities
2. Open a cash session by entering your opening cash amount
3. The POS interface is ready for sales

---

## Upgrading from a Previous Version

If you are upgrading from a previous version:

1. Deactivate the current version: **Plugins > RootLabs POS > Deactivate**
2. Delete the old version: **Plugins > RootLabs POS > Delete**
3. Install the new ZIP as described above
4. Activate

**Note:** Deleting the plugin does not remove database tables or settings. The uninstall routine is intentionally non-destructive to prevent accidental business data loss.

---

## Troubleshooting Installation

| Problem | Solution |
|---|---|
| "WooCommerce is required" | Install and activate WooCommerce first |
| "PHP version not supported" | Upgrade PHP to 8.0 or higher |
| `/pos` returns 404 | Flush permalinks (Settings > Permalinks > Save) |
| "You do not have permission" | Assign the user a role with `mx_pos_access` capability |
| Tables not created | Run `wp mx-pos db-check` and re-activate the plugin |
| White screen / fatal error | Check `wp-content/debug.log` (enable WP_DEBUG) |
