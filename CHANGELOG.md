# Changelog

## v1.0.0-rc1 (2026-05-21)

### POS
- React POS interface with product search, grid, and cart
- WooCommerce product catalog with variations and images
- Fast product indexing for search by SKU, name, and attributes

### Cash Register
- Session open/close with MXN denomination counting (bills and coins)
- Manual cash movements: cash_in and cash_out
- Real-time balance tracking
- Difference reconciliation on close

### Sales & Payments
- Order creation via WooCommerce CRUD (HPOS compatible)
- Payment methods: cash, card, transfer, and mixed
- Automatic cash_in recording for cash payments
- Idempotent payments via client_request_id dedup

### Discounts & Coupons
- Manual percentage and fixed-amount discounts
- WooCommerce coupon support
- Discount validation (max %, max amount, reason required)

### Tickets 58mm
- Professional thermal-print tickets for sales
- Configurable: business name, logo, footer, visibility toggles
- Logo support with WordPress media library

### Cancellations & Refunds
- Cancel pending orders (restore stock)
- Full and partial refunds (via wc_create_refund)
- Refund audit trail with reasons

### Cash Cuts X/Z
- Corte X: live partial summary snapshot
- Corte Z: definitive cut (persisted, one per session)
- Cut summary includes sales, refunds, discounts, and cash movements
- 58mm ticket for X/Z cuts

### Telegram Notifications
- Optional notifications for: session open, session close, differences, cancellations, refunds, Z cuts
- HTML formatted messages via Telegram Bot API
- Connection test from Settings

### Sales History
- Paginated history with role-based visibility (cashiers see own sales)
- Filters: date range, status, cashier, search by ID
- Full sale detail with order items, payments, refunds, and event logs
- Distinct cashier listing

### Settings
- Telegram configuration (bot token, chat ID, test button)
- Ticket customization (business name, footer, logo, visibility)
- Token masking and secure storage

### POS URL
- Dedicated `/pos` frontend route with rewrite rules
- Access control: login required, mx_pos_access capability checked
- Separate admin pages for dashboard and settings

### Security Hardening
- Nonce + capability validation on all REST mutation endpoints
- HPOS compatibility declared for WooCommerce custom order tables
- Input sanitization and output escaping throughout
- Audit logging for sensitive events
- Role-based data visibility (cashiers restricted to own records)

### WP-CLI Diagnostics
- `wp mx-pos healthcheck`: plugin status, WC, DB, caps, assets, route
- `wp mx-pos db-check`: schema validation (tables, columns, indexes)
- `wp mx-pos caps-check`: capability validation per role
- `wp mx-pos sessions list`: cash sessions with filters
- `wp mx-pos cuts list`: X/Z cuts with filters
- `wp mx-pos diagnose`: extended diagnostic with counts and risk flags
- `wp mx-pos index rebuild`: rebuild product search index
- `wp mx-pos index stats`: product index statistics
- All read-only by default; JSON/CSV format support

### Known Limitations
- **Uninstall cleanup intentionally disabled in RC1.** To prevent accidental data deletion during testing, the uninstall routine does not drop database tables or remove capabilities. Full uninstall cleanup will be enabled in a future release.
- Telegram notifications require a valid bot token and chat ID configured in Settings.
- No i18n / translations in this release.
- 58mm ticket is HTML-based; actual thermal printer setup depends on the hosting environment.

### Backlog (non-blocking for RC1)
- Payment race condition hardening (H-006)
- Refund TOCTOU hardening (H-007)
- Cash-out rollback atomicity (H-008)
- Reversal search uses LIKE on reason (H-009)
- Centralized AuditService abstraction (M-004)
- Audit log coverage for cash movements and parked carts (M-005)
- user_agent column not populated in audit_logs (M-012)
- dbDelta may duplicate CREATE TABLE statements (M-013)
