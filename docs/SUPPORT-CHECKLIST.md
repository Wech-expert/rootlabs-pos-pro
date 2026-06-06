# RootLabs POS for WooCommerce — Support Checklist

Use this checklist when providing technical support for RootLabs POS installations.

---

## Step 1: Gather Environment Information

Ask the user for:

- WordPress version
- WooCommerce version
- PHP version
- RootLabs POS version (visible in wp-admin > Plugins)
- How the plugin was installed (ZIP upload)
- Any recent changes (updates, new plugins, theme changes)

---

## Step 2: Run Diagnostics

Ask the user to run the following WP-CLI commands and share the output:

```bash
# General health check
wp mx-pos healthcheck --verbose

# Database schema validation
wp mx-pos db-check --verbose

# Capability validation
wp mx-pos caps-check

# Extended diagnostic
wp mx-pos diagnose --verbose
```

If WP-CLI is not available, the user can enable `WP_DEBUG` and `WP_DEBUG_LOG` in `wp-config.php` and check `wp-content/debug.log`.

---

## Step 3: Common Issues

### Plugin activation fails

**Possible causes:**
- WooCommerce not installed or not activated
- PHP version below 8.0
- WordPress version below 6.0
- Conflicting plugin

**Diagnosis:**
```bash
wp plugin list --status=active
php -v
wp mx-pos healthcheck
```

### /pos returns 404

**Diagnosis:**
```bash
wp rewrite flush
wp mx-pos healthcheck   # Check "POS route" line
```

Navigate to **Settings > Permalinks** and click **Save Changes**.

### Database migration issues

**Diagnosis:**
```bash
wp mx-pos db-check
wp eval 'echo get_option("mx_pos_pro_db_version");'
```

Compare with expected version `1.4`. If mismatched, re-activate the plugin to trigger migrations.

### "You do not have permission" errors

**Diagnosis:**
```bash
wp mx-pos caps-check
wp user list-caps <user_id>
```

Ensure the user's role has the required capability.

### Telegram not working

**Checks:**
- Is `mx_pos_telegram_enabled` set to `yes`?
- Are bot token and chat ID configured?
- Is the bot added to the target chat/group?
- Firewall or hosting restrictions on outbound HTTP calls?

**Diagnosis:**
- Settings page > Test Telegram button
- Check `wp-content/debug.log` for Telegram API errors

### White screen or fatal error

**Diagnosis:**
1. Enable `WP_DEBUG` and `WP_DEBUG_LOG` in `wp-config.php`
2. Reproduce the issue
3. Check `wp-content/debug.log`
4. Run `wp mx-pos healthcheck`

---

## Step 4: What to Collect

When escalating an issue, collect:

- [ ] WP-CLI diagnostic output (`wp mx-pos diagnose --verbose`)
- [ ] WordPress and WooCommerce versions
- [ ] PHP version and configuration
- [ ] Relevant entries from `wp-content/debug.log`
- [ ] Steps to reproduce the issue
- [ ] Screenshots of error messages (if applicable)

---

## Step 5: What NOT to Ask For

**Never ask for:**
- Telegram bot token
- Any API keys or secrets
- Database credentials
- WordPress admin credentials
- Server SSH access

---

## Step 6: Quick Health Summary

| Check | Command |
|---|---|
| Plugin status | `wp mx-pos healthcheck` |
| Database health | `wp mx-pos db-check` |
| Capabilities | `wp mx-pos caps-check` |
| Open sessions | `wp mx-pos sessions list --status=open` |
| Recent cuts | `wp mx-pos cuts list --limit=10` |
| Full report | `wp mx-pos diagnose` |
