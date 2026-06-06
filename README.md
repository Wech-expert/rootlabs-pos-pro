<div align="center">

<img src="https://raw.githubusercontent.com/mralonsocorona/mralonsocorona/main/rootlabs-pos-banner.png" alt="RootLabs POS for WooCommerce" width="100%"/>

# RootLabs POS for WooCommerce

**A self-hosted, open source point-of-sale system built on top of WooCommerce.**
No cloud. No SaaS. No lock-in. Just a `/pos` route and your store.

[![Version](https://img.shields.io/badge/version-0.1.0-1A1A1B?style=flat-square)](https://github.com/mralonsocorona/rootlabs-pos-for-woocommerce/releases)
[![License: GPL v2](https://img.shields.io/badge/license-GPL--2.0--or--later-7296D4?style=flat-square)](https://www.gnu.org/licenses/gpl-2.0.html)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759B?style=flat-square&logo=wordpress&logoColor=white)](https://wordpress.org)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-8.0%2B-96588A?style=flat-square&logo=woocommerce&logoColor=white)](https://woocommerce.com)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?style=flat-square&logo=php&logoColor=white)](https://php.net)
[![Schema](https://img.shields.io/badge/DB%20schema-1.11-E5E5E7?style=flat-square)](#database)
[![Status](https://img.shields.io/badge/status-early%20release-orange?style=flat-square)](#project-status)

</div>

---

RootLabs POS turns any WordPress + WooCommerce installation into a complete in-store checkout system. It registers a dedicated `/pos` route, a React cashier interface, and a PHP backend with its own REST API — fully synchronized with your WooCommerce products, customers, coupons, orders, inventory, and sales history.

> **Designed for physical stores already running WooCommerce.** Not a SaaS replacement. Not a cloud dashboard. A practical, auditable, extensible POS foundation you host and own.

---

## Table of Contents

- [What It Solves](#what-it-solves)
- [Project Status](#project-status)
- [Main Features](#main-features)
- [Architecture](#architecture)
- [Requirements](#requirements)
- [Installation](#installation)
- [Initial Setup](#initial-setup)
- [Daily Operation](#daily-operation)
- [Roles and Permissions](#roles-and-permissions)
- [REST API](#rest-api)
- [Database](#database)
- [WP-CLI](#wp-cli)
- [Development](#development)
- [Release Packaging](#release-packaging)
- [Security](#security)
- [Uninstall](#uninstall)
- [Support](#support)
- [Roadmap](#roadmap)
- [Contributing](#contributing)
- [License](#license)

---

## What It Solves

Most POS systems for WooCommerce are either SaaS subscriptions, hardware-locked solutions, or plugins that barely cover the checkout flow. RootLabs POS is different:

- **Self-hosted** — runs on your existing WordPress server, no external dependencies.
- **WooCommerce-native** — creates real WooCommerce orders, uses your existing products, customers, coupons, and inventory.
- **Cashier-focused** — dedicated `/pos` route independent of your active theme, with its own authentication flow.
- **Auditable** — full audit log, cash session tracking per employee/register/branch, and operational logging.
- **Extensible** — clean PHP domain architecture and a REST API designed to be built on.

### What you get out of the box

- POS register at `/pos` with fast product/variation search.
- Server-validated carts: stock, prices, discounts, and coupons.
- Cash sessions by employee, branch, and physical register.
- Opening cash count using MXN denominations.
- Cash, card, configurable payment methods, and split payments.
- WooCommerce order creation from the POS.
- Sales tickets, gift tickets, and cash cut tickets (80mm / 58mm).
- Cash movements, refunds, cancellations, X/Z cuts, and full audit logging.
- Admin dashboard, WP-CLI diagnostics, and optional Telegram notifications.

---

## Project Status

> **Early open source release.** The plugin includes complete operational flows and has been built with production intent, but **must be tested in a staging environment** before installation in a live store.

Before going to production, validate at minimum:

- Clean ZIP installation on your target environment.
- Compatibility with your WordPress, WooCommerce, PHP, active theme, and critical plugins.
- Full flows: sale → payment → refund → cut → closing with test data.
- Your specific tax, receipt, inventory, and role requirements.
- Backup and rollback plan.

**Out of scope for this release:**

| Not planned | Reason |
|---|---|
| Cloud sync | By design — self-hosted only |
| SaaS dashboard | Out of scope |
| Hardware-specific proprietary integrations | Platform-agnostic browser printing only |
| Offline order queue | No background sync; checkout requires connectivity |

---

## Main Features

| Area | Implemented |
|---|---|
| **POS Register** | Product search, variation picker, cart, customer selection, discounts, coupons, server validation, and checkout |
| **Cash Register** | Branches, physical registers, POS employees, opening denominations, current balance, movements, closing, and remote close |
| **Payments** | Cash, card, mixed, configurable methods, references, card fees, and split payments per order |
| **WooCommerce** | Creates WooCommerce orders, uses products/variations, applies coupons, customers, inventory, refunds, and HPOS |
| **Tickets** | Sales, gift, X/Z cut tickets, browser printing, default 80mm paper, 58mm support |
| **Refunds** | Sale cancellation, full/partial refunds, cash-out when applicable, WooCommerce refund handling |
| **History** | Sale lookup, sale detail, payments, refunds, and ticket reprint |
| **Product Index** | Dedicated `mx_pos_product_index`, WooCommerce sync hooks, CLI rebuild/stats/benchmark |
| **Admin** | Dashboard, status, branches, registers, employees, sessions, payment methods, reports, audit, and settings |
| **Notifications** | Optional Telegram alerts for opening/closing, discrepancies, cancellations, refunds, and Z cuts |
| **Diagnostics** | WP-CLI health checks for plugin status, schema, capabilities, sessions, cuts, and product index |

### Offline Behavior

The POS detects `online`, `offline`, `checking`, and `degraded` states.

- Checkout is **blocked** when the connection is not online.
- The pending cart is saved in `localStorage` for up to 30 minutes.
- After reconnection, the cart must be revalidated before checkout.
- There is no offline order queue or background sync.

---

## Architecture

### PHP Backend

Bootstrap starts in `mx-pos-pro.php` via `MXPOSPro\Core\Plugin`. On activation, the plugin:

1. Verifies WooCommerce is active.
2. Declares HPOS compatibility (`custom_order_tables`).
3. Runs database migrations.
4. Installs roles and capabilities.
5. Registers admin pages, `/pos`, assets, product hooks, REST controllers, CLI commands, and notifications.

The backend is organized by domain:

| Module | Responsibility |
|---|---|
| `Core` | Activation, compatibility, assets, capabilities, REST security, and POS capability bridge |
| `Frontend` | `/pos` route, POS login, register selection, and opening flow |
| `Auth` | POS employee authentication with a dedicated cookie, transient, and failed-attempt lockout |
| `Products` | Product/variation index and optimized search |
| `Cart` | Item validation, discounts, coupons, and parked carts |
| `Sales` | Checkout, WooCommerce order creation, payments, tickets, history, refunds, and cancellations |
| `Cash` | Sessions, movements, closing, remote close, X/Z cuts, and automation |
| `Payments` | POS payment methods and order payment lines |
| `Entities` | Branches, registers, and POS employees |
| `Admin` / `Reports` / `Audit` | Admin panel, dashboard, reports, and audit log |
| `Notifications` | Optional Telegram event formatting and delivery |
| `CLI` | Diagnostics, operational listings, and product index commands |

### React Frontend

| | |
|---|---|
| **Framework** | React 18 + TypeScript |
| **Build tool** | Vite 5 |
| **Source** | `frontend/` |
| **Output** | `assets/dist/` |
| **Auth** | `X-WP-Nonce` + `credentials: 'same-origin'` |
| **Locale** | Spanish UI, `MXN` currency via `es-MX` |

The PHP shell `templates/frontend/pos-shell.php` injects `window.mxPosProSettings` with `nonce`, `root`, `posUrl`, `posLogoutUrl`, `context`, `beepEnabled`, and POS capabilities visible to the UI.

### `/pos` Loading Flow

`/pos` runs completely independent of your active WordPress theme:

```
WordPress resolves rewrite rule
    → No POS session → POS login screen
    → Login validates mx_pos_employees
    → Employee selects branch / register
    → Opening cash count by denomination
    → Cash session opens + audit recorded
    → React POS register mounts
```

---

## Requirements

| Requirement | Minimum | Recommended |
|---|---:|---:|
| WordPress | `6.0` | Latest stable |
| WooCommerce | `8.0` | Latest validated stable |
| PHP | `8.0` | `8.1+` |
| MySQL / MariaDB | `5.7` / `10.2` | Version supported by your host |
| PHP extensions | `json` | `mbstring`, `curl`, `dom` |

> WooCommerce must be **installed and active** before activating RootLabs POS. Activation is blocked explicitly if WooCommerce is not present.

---

## Installation

### Via ZIP

1. Download `rootlabs-pos-for-woocommerce-v0.1.0.zip` from [Releases](https://github.com/mralonsocorona/rootlabs-pos-for-woocommerce/releases).
2. Go to `wp-admin → Plugins → Add New → Upload Plugin`.
3. Upload the ZIP and click **Install Now**.
4. **Activate** the plugin.

On activation, the plugin automatically:

- Creates or migrates the 14 `wp_mx_pos_*` tables (schema `1.11`).
- Installs POS capabilities and the `mx_pos_cashier` WordPress role.
- Registers the `/pos` rewrite route.
- Seeds initial Telegram and ticket options.
- Runs `flush_rewrite_rules()`.

### Quick Verification

```bash
wp mx-pos healthcheck
wp mx-pos db-check
wp mx-pos caps-check
```

If `/pos` returns 404 after activation:

```bash
wp rewrite flush
# or go to wp-admin → Settings → Permalinks and save.
```

---

## Initial Setup

After activation, complete the following in `wp-admin → RootLabs POS`:

1. **Status tab** — review the environment report.
2. **Branches** — create or validate `Sucursal Principal`.
3. **Registers** — create or validate `Caja Principal`.
4. **Employees** — add POS employees (separate from WordPress users).
5. **Payment Methods** — configure cash, card, and any additional methods.
6. **Settings** — configure ticket logo, paper width, and footer text.
7. **Telegram** *(optional)* — configure only if you need operational alerts.
8. Open `/pos` with a POS employee credential, select a register, and enter the opening cash count.

> Migration seeds included: branch `main` → *Sucursal Principal*, register `main` → *Caja Principal*, payment methods `cash`, `card`, `mixed`.

---

## Daily Operation

### Opening a Session

1. Employee opens `/pos`.
2. Signs in with POS credentials.
3. Selects an active register.
4. Counts opening cash by MXN denominations.
5. System opens a cash session and records audit data.

**Rules:**
- A register cannot have two open sessions simultaneously.
- An employee cannot have two open sessions simultaneously.
- 5 failed login attempts trigger a temporary employee lockout.

### Processing a Sale

1. Search products by name or SKU.
2. Pick a variation when applicable.
3. Add items to the cart.
4. Select or create a customer if needed.
5. Apply a WooCommerce coupon or manual discount if permitted.
6. **Validate** the cart (server-side: stock, price, coupon).
7. Charge using one or more payment lines.
8. WooCommerce order and POS sale are created atomically.
9. Print a regular or gift ticket.

> Checkout uses `client_request_id` for idempotency — safe to retry without risk of duplicate sales.

### Cash Register Operations

During a session:

| Operation | Description |
|---|---|
| Cash-in | Record additional cash added to the register |
| Cash-out | Record cash removed from the register |
| Movement reversal | Reverse a previous movement |
| X pre-cut | Preview totals without closing the session |
| Z cut | Final cut and session close |
| Remote close | Admin-initiated close when the cashier cannot close themselves |

### Refunds and Cancellations

- Sale cancellation
- Full order refund
- Partial item refund
- Cash-out record for cash refunds
- Full WooCommerce order and refund sync
- Audit log entry for every operation

---

## Roles and Permissions

### Access Points

| Access | Route | Requirement |
|---|---|---|
| POS frontend | `/pos` | POS employee authenticated via the plugin's own login |
| Admin panel | `wp-admin → RootLabs POS` | `manage_options` + optional `mx_pos_view_dashboard` / `mx_pos_view_audit` |
| REST API | `/wp-json/mx-pos/v1/*` | REST nonce + POS session or valid WordPress user + required capability |

### WordPress Capabilities

| Capability | What it grants |
|---|---|
| `mx_pos_access` | Operational POS access |
| `mx_pos_sell` | Validate carts, sell, charge, create customers/carts |
| `mx_pos_refund` | Refunds and cancellations |
| `mx_pos_open_session` | Open a cash session |
| `mx_pos_close_session` | Close own cash session |
| `mx_pos_apply_discount` | Apply manual discounts |
| `mx_pos_cash_cut` | Generate and view cuts |
| `mx_pos_manage_cash` | Cash movements |
| `mx_pos_manage_settings` | Configure payment methods, tickets, and settings |
| `mx_pos_view_dashboard` | View the admin dashboard |
| `mx_pos_view_audit` | View the audit log |
| `mx_pos_void_session` | Void open sessions |
| `mx_pos_remote_close` | Close sessions remotely |

### WordPress Role Map

| Role | Permissions |
|---|---|
| `administrator` | All POS capabilities |
| `shop_manager` | Sales, refunds, discounts, cash register, cuts, dashboard, audit — no settings, void, or remote close |
| `mx_pos_cashier` | POS access, sales, open/close sessions, cuts, and cash movements — no refunds or manual discounts |

### POS Employees vs WordPress Users

POS employees are stored in `mx_pos_employees` and are **not** required to be WordPress users. `POSCapabilityBridge` grants operational capabilities only inside `/pos` and the `mx-pos/v1` REST namespace, based on the POS cookie `mx_pos_auth_token`. It does not grant `manage_options` or any global admin capability.

POS employee roles: `cashier` (sales, register), `manager` / `supervisor` / `encargado` (additionally allow manual discounts and refunds in POS context).

---

## REST API

Namespace: `mx-pos/v1`. All mutations require `X-WP-Nonce` via `RestSecurity::verify_mutation()` and the appropriate capability.

| Area | Endpoints |
|---|---|
| Products | `GET /products/search`, `GET /products/catalog` |
| Cart | `POST /cart/validate` |
| Coupons | `GET /coupons/search` |
| Customers | `GET /customers/search`, `GET /customers/lookup`, `POST /customers`, `POST/PUT/PATCH /customers/{id}`, `GET /customers/{id}/purchases` |
| Parked Carts | `GET /parked-carts/current`, `POST /parked-carts`, `GET /parked-carts/{id}`, `DELETE /parked-carts/{id}` |
| Sessions | `GET /sessions/current`, `POST /sessions/open`, `POST /sessions/{id}/close`, `POST /sessions/{id}/remote-close` |
| Cash Movements | `GET /cash-movements/current`, `POST /cash-movements`, `POST /cash-movements/{id}/reverse` |
| Cuts | `GET /sessions/{id}/cuts/x`, `POST /sessions/{id}/cuts/z`, `GET /cuts`, `GET /cuts/{id}`, `GET /cuts/{id}/ticket` |
| Sales | `POST /sales`, `POST /checkout/complete`, `GET /sales/history`, `GET /sales/history/cashiers`, `GET /sales/lookup`, `GET /sales/{id}/detail` |
| Payments | `POST /sales/{id}/pay`, `GET /payments/methods/active`, `GET/POST /payments/methods`, `POST/PUT/PATCH /payments/methods/{id}`, `POST/PUT/PATCH /payments/methods/{id}/toggle`, `GET /payments/gateways` |
| Tickets | `GET /sales/{id}/ticket`, `GET /sales/{id}/gift-ticket`, `GET /cuts/{id}/ticket` |
| Refunds | `POST /sales/{id}/cancel`, `GET /sales/{id}/refund-options`, `POST /sales/{id}/refund` |

---

## Database

Current schema version: **`1.11`**

The plugin maintains its own 14 tables for POS operations while keeping WooCommerce as the source of truth for orders, products, customers, and coupons.

| Table | Purpose |
|---|---|
| `mx_pos_product_index` | Product and variation index for optimized POS search |
| `mx_pos_sessions` | Cash sessions by employee, register, and branch |
| `mx_pos_sales` | POS sales linked to WooCommerce orders |
| `mx_pos_sale_logs` | Sale lifecycle events |
| `mx_pos_refunds` | Refunds and cancellations |
| `mx_pos_cash_movements` | Cash-ins, cash-outs, and change |
| `mx_pos_parked_carts` | Saved carts for later restoration |
| `mx_pos_cash_cuts` | X/Z cuts and cash summaries |
| `mx_pos_audit_logs` | Full operational audit log |
| `mx_pos_branches` | Branches |
| `mx_pos_registers` | Physical registers |
| `mx_pos_employees` | POS employees and credentials |
| `mx_pos_payment_methods` | Configurable POS payment methods |
| `mx_pos_order_payments` | Payment lines per sale/order |

**Idempotency fields** — present on `mx_pos_sales`, `mx_pos_refunds`, `mx_pos_cash_movements`, and `mx_pos_order_payments` as `client_request_id` to prevent duplicate records on retry.

---

## WP-CLI

The plugin registers the `wp mx-pos` and `wp mx-pos index` command groups.

### Diagnostics

```bash
# Plugin health and schema
wp mx-pos healthcheck
wp mx-pos healthcheck --verbose
wp mx-pos healthcheck --format=json

wp mx-pos db-check
wp mx-pos db-check --verbose

# Capabilities
wp mx-pos caps-check
wp mx-pos caps-check --role=mx_pos_cashier

# Full diagnostic report
wp mx-pos diagnose --verbose
```

### Operational

```bash
# Sessions
wp mx-pos sessions list
wp mx-pos sessions list --status=open
wp mx-pos sessions list --cashier=1 --format=csv

# Cuts
wp mx-pos cuts list
wp mx-pos cuts list --session=1 --final-only
```

### Product Index

```bash
wp mx-pos index stats
wp mx-pos index rebuild
wp mx-pos index benchmark --queries="abc|cafe|750"
```

---

## Development

### Prerequisites

```bash
npm install
```

### Typecheck

```bash
npm run typecheck
```

### Build

```bash
npm run build
```

Vite configuration:

| Setting | Value |
|---|---|
| Root | `frontend/` |
| Entry | `frontend/index.html` |
| Alias | `@` → `frontend/src` |
| Output | `assets/dist/` |
| Expected files | `assets/dist/assets/index.js`, `assets/dist/assets/index.css` |

### Dev Server

```bash
npm run dev
```

> The Vite dev server serves the frontend, but the complete POS flow requires a running WordPress + WooCommerce installation. The API, nonces, POS cookies, and runtime settings are injected by PHP.

---

## Release Packaging

```bash
bash scripts/package-production.sh
```

The script: validates source files → typechecks → builds frontend → lints PHP → creates a clean staging directory → outputs `../packages/rootlabs-pos-for-woocommerce-v0.1.0.zip` → verifies compiled assets are included and development files are excluded.

**Excluded from production ZIP:** `.git/`, `node_modules/`, `frontend/`, `docs/`, `scripts/`, `README.md`, `CHANGELOG.md`, `CONTRIBUTING.md`, `SECURITY.md`, `ROADMAP.md`, `package.json`, `tsconfig.json`, `vite.config.ts`.

---

## Security

### Implemented Controls

- Capability check on every REST endpoint.
- REST nonce (`X-WP-Nonce`) for all mutations.
- Form nonces for login, logout, register selection, and opening cash count.
- POS cookies with `HttpOnly`, `SameSite=Lax`, and `Secure` when applicable.
- Temporary POS employee lockout after 5 failed login attempts.
- Sanitization and escaping throughout all PHP flows.
- Full operational audit log.

### Operational Considerations

- The Telegram token is stored in WordPress options — protect admin access and database backups.
- POS authentication uses transients for session tokens.
- Checkout requires network connectivity — there is no offline order queue.
- WooCommerce operations and POS tables are not executed inside a single global transaction. The codebase compensates with idempotency keys, order states, and audit logs, but failure edge cases should be explicitly tested in your environment.

**Never share in support requests:** Telegram token, API keys, WordPress or database credentials, cookies, authorization headers, SQL dumps, or real cash totals.

To report a vulnerability, see `SECURITY.md` or email **hola@rootlabs.mx**.

---

## Uninstall

The `uninstall.php` routine is **intentionally non-destructive**.

When deleting the plugin from WordPress admin:

✅ Removed: POS capabilities, `mx_pos_cashier` WordPress role.
🔒 Preserved: All POS tables, all options, all WooCommerce orders, all sales/session/movement/audit data.

> Business data is never destroyed on normal uninstall. If a full data purge is needed in the future, it will be implemented as an explicit WP-CLI action with confirmation — never as a silent side effect.

---

## Support

Before opening an issue, collect the following:

- WordPress, WooCommerce, PHP, and RootLabs POS versions.
- Installation method (ZIP / manual).
- Exact reproduction steps.
- Output of the diagnostic commands below.

```bash
wp mx-pos diagnose --verbose
wp mx-pos db-check --verbose
wp mx-pos caps-check
wp mx-pos sessions list --status=open
wp mx-pos cuts list --limit=10
```

**Related documentation in this repository:**

| File | Description |
|---|---|
| `docs/INSTALL.md` | Detailed installation guide |
| `docs/QA-CHECKLIST.md` | Pre-production QA checklist |
| `docs/SUPPORT-CHECKLIST.md` | Support request guide |
| `CONTRIBUTING.md` | Contribution guidelines |
| `SECURITY.md` | Vulnerability reporting |
| `CHANGELOG.md` | Release history |
| `ROADMAP.md` | Project direction |

---

## Roadmap

Near-term priorities:

- Expanded automated test coverage.
- Technical documentation for hooks and extension points.
- Formal translations for the React frontend.
- Improved history and refund search workflows.
- Performance improvements for large product catalogs.
- Public issue templates.
- Additional ticket and printing options.

**Out of scope for the first public release:** cloud sync, SaaS dashboard, multi-store cloud reporting, proprietary hardware integrations beyond browser-based printing.

---

## Contributing

Contributions are welcome — especially around stability, accessibility, testing, security, WooCommerce/HPOS compatibility, documentation, translations, and search performance.

Before opening a PR:

```bash
npm install
npm run typecheck
npm run build
```

Do not include: secrets, `.env` files, SQL dumps, logs, release ZIPs, `node_modules`, or real store data.

See `CONTRIBUTING.md` for full guidelines.

---

## License

RootLabs POS for WooCommerce is distributed under the **GPL-2.0-or-later** license.

See [`LICENSE`](./LICENSE) for the full license text.

---

<div align="center">

Built by [RootLabs.mx](https://rootlabs.mx) · Boca del Río, Veracruz, México 🇲🇽

</div>
