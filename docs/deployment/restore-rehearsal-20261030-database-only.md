# Restore rehearsal — database-only

Tanggal: 2026-10-30
Task: `OPS-RESTORE-001`
Status: **PARTIAL — database restored; private files and key not supplied**

## Source artifacts

| Source | SHA-256 | Size |
|---|---|---:|
| `C:/Users/PT JAGO LABA/Downloads/jago_laba.sql` | `445EE9B2DB2C78A9AB8C952773076C733FCC63B43AD80BDD06B15445CC3A7B64` | 33,727,742 bytes |
| `C:/Users/PT JAGO LABA/Downloads/jago_laba_konveksi.sql` | `961D502C0D2087B9F40BCD2BDDF37A7F8EE86FB985B3AE66DD2F443DD3EEB926` | 386,230 bytes |

## Isolated targets

| Source domain | Target database | Result |
|---|---|---|
| Main / Marketplace | `jl_staging_restore_20261030` | Imported successfully; 80 tables |
| Konveksi | `jl_staging_restore_konveksi_20261030` | Imported successfully; 43 tables |

The source databases `jago_laba` and `jago_laba_konveksi` were not modified.
The import used a local administrative account only; no application runtime was
configured to use that account permanently.

## Read-only parity evidence

| Table | Source rows | Restored rows |
|---|---:|---:|
| `auth_users_v2` | 1 | 1 |
| `marketplace_stores` | 6 | 6 |
| `marketplace_orders` | 1,938 | 1,938 |
| `marketplace_order_items` | 1,948 | 1,948 |
| `jl_data_produk` (main) | 17 | 17 |
| `jl_data_bahan` (main) | 22 | 22 |
| `system_table_map` (Konveksi) | 35 | 35 |
| `jl_data_produk` (Konveksi) | 5 | 5 |
| `jl_data_bahan` (Konveksi) | 22 | 22 |

Laravel `php artisan about` and `php artisan db:table auth_users_v2 --database=mysql`
were run with a temporary `DB_DATABASE=jl_staging_restore_20261030` override.
The repository `.env` was not edited. No migration, seed, scheduler, queue worker,
outbound provider call, or Live OAuth operation was executed.

## Remaining gate

This rehearsal is not a full staging go decision because no private-file backup or
authoritative legacy key was supplied. A dedicated non-root staging database user
and an application-readable outbound/scheduler-disabled control must be configured
before application smoke tests. Do not attach production traffic or provider Live
credentials to these targets.
