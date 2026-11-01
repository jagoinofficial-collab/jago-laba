# MKT-READ-001 — Marketplace order read pilot

Tanggal verifikasi: 2026-10-30
Status: PASS untuk slice read-only lokal/fixture terisolasi.

## Scope

Migrated flow:

- daftar order Marketplace dengan store, periode half-open, tab status, pencarian, pembayaran, kondisi, retur, dan pagination;
- detail order melalui JSON read endpoint;
- status, item, fee, HPP, laba, paket, timeline, retur, dan nilai yang tidak tersedia tetap nullable;
- company/store scope dan penolakan store asing;
- legacy route tetap aktif sebagai rollback path.

Tidak termasuk provider refresh, fulfillment, shipping document, OAuth Live, atau write aplikasi.

## Implementasi

- [MarketplaceOrderController.php](../../laravel-foundation/app/Http/Controllers/MarketplaceOrderController.php)
- [MarketplaceOrderRequest.php](../../laravel-foundation/app/Http/Requests/MarketplaceOrderRequest.php)
- [OrderReadService.php](../../laravel-foundation/app/Pilots/Marketplace/OrderReadService.php)
- [MarketplaceOrders.vue](../../laravel-foundation/resources/js/Pages/Pilot/MarketplaceOrders.vue)
- [web.php](../../laravel-foundation/routes/web.php)
- focused assertions in [PilotReadOnlyTest.php](../../laravel-foundation/tests/Feature/PilotReadOnlyTest.php)

Routes:

- `GET /pilot/marketplace/orders`
- `GET /pilot/marketplace/orders/{order}/detail`

## Verification evidence

| Check | Result |
| --- | --- |
| PHP syntax and language diagnostics | PASS; no errors reported |
| Laravel focused read tests | PASS; order list/detail, return filter, scope, and no-write assertions |
| Full Laravel test suite | PASS; 48 tests, 455 assertions |
| Legacy order regression | PASS; status groups, counts, filters, API-only, cancellation request, returns, pagination, tenant isolation |
| Legacy transaction-detail regression | PASS; totals, subsidy, percentages, items, COD, missing data, stale sync, manual protection |
| Route registration | PASS; 2 expected Marketplace order routes |
| Production frontend build | PASS; `npm.cmd run build`, `vue-tsc --noEmit` and Vite build |
| Provider call | PASS; no provider call path in the read service |
| Application DB write | PASS; tests assert order/return row counts unchanged after reads |

## Parity notes

The legacy smoke scenarios and Laravel fixture assertions cover the same contract dimensions: status grouping, API-only source filtering, half-open dates, store isolation, return-type filtering, pagination, item/fee/HPP/nullability, and response props. The Laravel page exposes those values through Inertia while the detail route exposes the JSON read contract. No legacy URL was cut over; rollback is to keep using the existing legacy page.

## Remaining release gate

This PASS is limited to the isolated read-only pilot. It does not approve production migration, live permission checks, production restore, cron/worker activation, provider Live access, or URL cutover. Those remain operational approvals under `CUTOVER-001`.
