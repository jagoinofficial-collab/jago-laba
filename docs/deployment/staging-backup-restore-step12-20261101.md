# Step 12 — isolated backup and restore rehearsal (2026-11-01)

## Decision

**PARTIAL / NO-GO.** Both supplied SQL dumps and the configured legacy
Marketplace encryption key were copied into a per-user temporary staging tree
with an explicit Windows ACL limited to the current user, SYSTEM, and local
Administrators. Both databases were restored to a fresh MariaDB datadir bound
to `127.0.0.1:3318`; the service has been stopped. The required private-file
archive was not present, so this is not a complete database/files/key restore
and must not be treated as a production-like staging approval.

## Backup inputs and isolation

| Input | Source | Staged copy | Verification |
|---|---|---|---|
| Marketplace/main SQL dump | `C:/Users/PT JAGO LABA/Downloads/jago_laba.sql` | `%TEMP%/jl-step12-stage-20261101/backup/jago_laba.sql` | 33,727,742 bytes; SHA-256 `445EE9B2DB2C78A9AB8C952773076C733FCC63B43AD80BDD06B15445CC3A7B64`; copy hash matched |
| Konveksi SQL dump | `C:/Users/PT JAGO LABA/Downloads/jago_laba_konveksi.sql` | `%TEMP%/jl-step12-stage-20261101/backup/jago_laba_konveksi.sql` | 386,230 bytes; SHA-256 `961D502C0D2087B9F40BCD2BDDF37A7F8EE86FB985B3AE66DD2F443DD3EEB926`; copy hash matched |
| Marketplace token-encryption key | Configured Windows fallback `D:/xampp/apache/conf/extra/jago-laba-marketplace.key` | `%TEMP%/jl-step12-stage-20261101/private/jago-laba-marketplace.key` | Source exists; base64 decodes to 32 bytes; staged copy hash matched source. Secret value/hash intentionally not recorded. |
| Private user documents/files | No private backup archive found; workspace inventory has only `.gitkeep` placeholders plus `storage/private/konveksi/database.php` | Not restored | **BLOCKED**: the PHP file is a connection configuration, not a document archive, and was not copied because it could point at a non-staging database. |

The staging tree is outside the web/document root. Its root ACL is protected from
inheritance and has three full-control entries: current user, SYSTEM, and local
Administrators. This verifies access-control scope, not encryption-at-rest;
BitLocker/EFS state was not verified. The SQL dumps contain application data and
the key can decrypt Marketplace credentials, so keep this temporary tree
restricted and remove it through an approved secure-cleanup procedure after the
owner accepts or rejects this rehearsal. Do not enable provider calls from it.

## Isolated restore and checks

- MariaDB 10.4.32 used a fresh datadir under `%TEMP%/jl-step12-stage-20261101/mysql-data`, bound only to loopback on port 3318.
- Schemas restored: `jl_step12_market_20261101` (117 base tables) and `jl_step12_konveksi_20261101` (43 base tables).
- Import used `FOREIGN_KEY_CHECKS=0` for dump replay. The inventory FK definitions exist after import, but the explicit post-check still finds 17 orphan `inventory_stocks.product_variant_id` values; no row was repaired or removed.
- Selected rows: Marketplace `auth_users_v2` 1, stores 6, orders 1,938, order items 1,948; Konveksi `system_table_map` 35, products 5, materials 22.
- No Laravel application, HTTP server, scheduler, queue worker, provider request, or OAuth flow was started. No migration or seed ran.
- MariaDB was shut down after checks; port 3318 has no listener.

## Outstanding gates

1. Obtain the authorized private-file backup and checksum/manifest; restore into
   this staging tree while preserving private-path permissions.
2. Obtain the data owner's mapping/disposition for the 17 orphan inventory
   references and reconcile quantity/HPP to the ledger using the
   [owner decision packet](./orphan-variant-owner-reconciliation-20261101.md).
3. Rehearse a Konveksi connection config that points only to the isolated
   Konveksi schema; do not copy the legacy connection config unchanged.
4. Verify OS-level at-rest encryption and staging host permissions before
   promoting this local rehearsal to an actual staging host.

Until these checks pass, restore status remains **PARTIAL / NO-GO**. This task
did not authorize production migration, provider Live, or URL cutover.
