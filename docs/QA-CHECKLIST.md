# RootLabs POS for WooCommerce — QA Checklist v0.1.0

Use this checklist to verify the plugin before releasing to external testers.

---

## Environment

| Field | Value |
|---|---|
| WordPress version | |
| WooCommerce version | |
| PHP version | |
| Browser | |
| Test date | |
| Tester | |

---

## Installation

| # | Test | Result | Notes |
|---|---|---|---|
| 1.1 | Upload ZIP via Plugins > Add New | PASS / FAIL | |
| 1.2 | Plugin appears in plugins list | PASS / FAIL | |
| 1.3 | Version shows 0.1.0 or the current public release | PASS / FAIL | |

## Activation

| # | Test | Result | Notes |
|---|---|---|---|
| 2.1 | Activate without fatal error | PASS / FAIL | |
| 2.2 | No WP debug.log errors | PASS / FAIL | |
| 2.3 | "RootLabs POS" menu appears in admin sidebar | PASS / FAIL | |
| 2.4 | DB version set to 1.4 | PASS / FAIL | |
| 2.5 | All 9 tables created | PASS / FAIL | |

## Roles & Capabilities

| # | Test | Result | Notes |
|---|---|---|---|
| 3.1 | `mx_pos_cashier` role exists | PASS / FAIL | |
| 3.2 | Administrator has all 9 capabilities | PASS / FAIL | |
| 3.3 | Shop Manager has 8 capabilities (no manage_settings) | PASS / FAIL | |
| 3.4 | Cashier has 6 capabilities | PASS / FAIL | |
| 3.5 | `wp mx-pos caps-check` passes | PASS / FAIL | |

## POS Frontend

| # | Test | Result | Notes |
|---|---|---|---|
| 4.1 | `/pos` loads (logged out -> redirect to login) | PASS / FAIL | |
| 4.2 | Login and access `/pos` with cashier role | PASS / FAIL | |
| 4.3 | Forbidden page shown for users without mx_pos_access | PASS / FAIL | |
| 4.4 | Product search works | PASS / FAIL | |
| 4.5 | Product grid displays products | PASS / FAIL | |
| 4.6 | Cart add/remove/update works | PASS / FAIL | |

## Cash Register

| # | Test | Result | Notes |
|---|---|---|---|
| 5.1 | Open session with opening amount | PASS / FAIL | |
| 5.2 | Cash in movement recorded | PASS / FAIL | |
| 5.3 | Cash out movement recorded (reason required) | PASS / FAIL | |
| 5.4 | Cash out blocked when insufficient balance | PASS / FAIL | |
| 5.5 | Close session with denominations | PASS / FAIL | |
| 5.6 | Difference note required when discrepancy | PASS / FAIL | |
| 5.7 | Session status changes to closed | PASS / FAIL | |

## Sales

| # | Test | Result | Notes |
|---|---|---|---|
| 6.1 | Create order with products (cash payment) | PASS / FAIL | |
| 6.2 | Process card payment | PASS / FAIL | |
| 6.3 | Apply manual discount (%) | PASS / FAIL | |
| 6.4 | Apply manual discount (fixed) | PASS / FAIL | |
| 6.5 | Apply WooCommerce coupon | PASS / FAIL | |
| 6.6 | Park a cart and recover it | PASS / FAIL | |
| 6.7 | Convert parked cart to sale | PASS / FAIL | |
| 6.8 | Cancel parked cart | PASS / FAIL | |

## Cancellations & Refunds

| # | Test | Result | Notes |
|---|---|---|---|
| 7.1 | Cancel a pending order | PASS / FAIL | |
| 7.2 | Full refund of a completed order | PASS / FAIL | |
| 7.3 | Partial refund (single item) | PASS / FAIL | |
| 7.4 | Refund with cash method records cash_out | PASS / FAIL | |
| 7.5 | Stock restored on cancel/refund (if restock enabled) | PASS / FAIL | |

## Cash Cuts (X/Z)

| # | Test | Result | Notes |
|---|---|---|---|
| 8.1 | Generate Corte X on open session | PASS / FAIL | |
| 8.2 | Close session, generate Corte Z | PASS / FAIL | |
| 8.3 | Corte Z blocked on open session | PASS / FAIL | |
| 8.4 | Corte X/Z ticket generated | PASS / FAIL | |
| 8.5 | Only one Corte Z per session allowed | PASS / FAIL | |

## Tickets 58mm

| # | Test | Result | Notes |
|---|---|---|---|
| 9.1 | Sale ticket generated correctly | PASS / FAIL | |
| 9.2 | Ticket shows items, totals, payment method | PASS / FAIL | |
| 9.3 | Re-print ticket from history | PASS / FAIL | |
| 9.4 | Corte Z ticket generated | PASS / FAIL | |

## History

| # | Test | Result | Notes |
|---|---|---|---|
| 10.1 | Sales history loads | PASS / FAIL | |
| 10.2 | Date filter works | PASS / FAIL | |
| 10.3 | Status filter works | PASS / FAIL | |
| 10.4 | Cashier filter works | PASS / FAIL | |
| 10.5 | Search by sale ID / order ID works | PASS / FAIL | |
| 10.6 | Sale detail view shows items, payments, refunds | PASS / FAIL | |

## Settings

| # | Test | Result | Notes |
|---|---|---|---|
| 11.1 | Settings page loads | PASS / FAIL | |
| 11.2 | Save Telegram config (enabled, token, chat ID) | PASS / FAIL | |
| 11.3 | Test Telegram connection | PASS / FAIL | |
| 11.4 | Save ticket business name and footer | PASS / FAIL | |
| 11.5 | Upload and select ticket logo | PASS / FAIL | |
| 11.6 | Ticket visibility toggles work | PASS / FAIL | |

## WP-CLI

| # | Test | Result | Notes |
|---|---|---|---|
| 12.1 | `wp mx-pos healthcheck` — all OK | PASS / FAIL | |
| 12.2 | `wp mx-pos db-check` — all OK | PASS / FAIL | |
| 12.3 | `wp mx-pos caps-check` — all OK | PASS / FAIL | |
| 12.4 | `wp mx-pos sessions list` works | PASS / FAIL | |
| 12.5 | `wp mx-pos cuts list` works | PASS / FAIL | |
| 12.6 | `wp mx-pos diagnose` — status OK | PASS / FAIL | |
| 12.7 | `wp mx-pos index stats` works | PASS / FAIL | |

## Security

| # | Test | Result | Notes |
|---|---|---|---|
| 13.1 | Endpoints require nonce | PASS / FAIL | |
| 13.2 | Endpoints require correct capability | PASS / FAIL | |
| 13.3 | Cashier cannot access admin settings | PASS / FAIL | |
| 13.4 | Telegram token not exposed in output | PASS / FAIL | |

---

## Summary

| Metric | Count |
|---|---|
| Total tests | |
| Pass | |
| Fail | |
| Blocked / Skipped | |

**Overall: PASS / FAIL**

---

## Notes

(Add any observations, edge cases, or concerns here)
