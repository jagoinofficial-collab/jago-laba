# Step 11 — isolated staging rehearsal (2026-11-01)

## Decision

**PARTIAL / NO-GO.** The database-only restore and Laravel read-only connectivity
were rehearsed in a new disposable MariaDB 10.4.32 instance bound to
`127.0.0.1:3317`. Full staging restore is not complete: private files and the
authoritative key are absent, and restored Marketplace data has 17 rows with a
`product_variant_id` that does not match `product_variants.id`. Do not use this
database as a GO/cutover baseline until the data owner reconciles the FK gap.
The read-only investigation and owner decision sheet are in the
[orphan-variant reconciliation packet](./orphan-variant-owner-reconciliation-20261101.md).

## Inputs and isolation

- Source dump SHA-256 values matched the previously recorded artifacts:
  - `jago_laba.sql`: `445EE9B2DB2C78A9AB8C952773076C733FCC63B43AD80BDD06B15445CC3A7B64`
  - `jago_laba_konveksi.sql`: `961D502C0D2087B9F40BCD2BDDF37A7F8EE86FB985B3AE66DD2F443DD3EEB926`
- Imported only into new schemas in a fresh per-user temporary datadir; it did
  not use the XAMPP data directory or production database.
- MariaDB listened on loopback only, port 3317. The original XAMPP database
  service and ports 3306/3307 were not running/used.
- A disposable Laravel account was granted `SELECT` only on the Marketplace
  rehearsal schema. No application DB write permission was granted.
- No cron, Laravel scheduler, queue worker, provider request, or Live OAuth was
  started. The HTTP smoke used `APP_ENV=staging`, `APP_DEBUG=false`, OAuth mode
  sandbox, blank provider secrets, file session/cache, and `LOG_CHANNEL=null`.

## Restore and parity checks

| Check | Result |
|---|---|
| Normal import with FK validation | Failed at `fk_stock_variant`: 17 stock rows reference missing product variants. No source or production data was changed. |
| Isolated retry with FK checks disabled for dump replay | Import exit 0; integrity was then checked explicitly and still found 17 orphan product-variant references. No repair/update was attempted. |
| Marketplace source objects | 117 base tables; dump contains 117 `CREATE TABLE` statements. |
| Konveksi source objects | 43 base tables; dump contains 43 `CREATE TABLE` statements. |
| Selected Marketplace rows | `auth_users_v2` 1; stores 6; orders 1,938; order items 1,948; products 17; materials 22. These match the earlier recorded spot checks. |
| Selected Konveksi rows | `system_table_map` 35; products 5; materials 22. These match earlier recorded spot checks. |
| Migration tables | Absent in both schemas. `php artisan migrate:status` returned “Migration table not found”; it did not create a migration table. No migration was run. |

The earlier [database-only restore note](./restore-rehearsal-20261030-database-only.md)
reported 80 Marketplace tables. This rehearsal and the SQL dump both count 117
base tables. Treat the old count as an unresolved evidence discrepancy; the
selected row-count parity above does not explain the 37-table difference.

## Laravel smoke

- `php artisan db:table marketplace_stores --database=mysql` succeeded against
  the restored Marketplace schema through the SELECT-only database account.
- HTTP GET `/api/health.php`: **200**, `{"status":"ok"}`; this endpoint executes
  `SELECT 1` against that account/schema.
- HTTP GET `/`: **200**, HTML page contained the Jago Laba foundation title.
- `php artisan migrate:status`: returned the expected missing migration-table
  error. The registry approves no migration for the read-only slice; write/queue
  migrations remain deferred. No migration command that mutates schema ran.
- The local HTTP server was stopped after the smoke. This is not HTTPS staging,
  browser login, a restored session/key test, or a provider callback test.

## Logging and remaining gates

The attached `config/logging.php` uses the standard `stack`/`single` file
channel; the fallback log level is `debug`. The smoke explicitly used the `null`
channel and `warning` level, so it produced no application log evidence. App
error contexts inspected here contain request ID/exception class, not tokens.
For a real staging host, set `APP_DEBUG=false`, `LOG_LEVEL=warning`, keep log
storage outside public access, and verify rotation/permissions before enabling
traffic.

Still unproven: private-file and key restore, role/login with an authorized
staging identity, Konveksi service connection from an isolated connection file,
nominal/HPP reconciliation on the restored data, HTTPS/proxy/cookie behavior,
provider callback registration, actual host permissions, cron/worker restart
and heartbeat, provider Live approval, production migration, and URL cutover.
The current [staging gate](./staging-release-gate-20261101.md) therefore remains
**NO-GO**.
