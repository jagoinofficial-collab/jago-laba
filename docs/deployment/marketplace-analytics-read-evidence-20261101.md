# MKT-READ-002 — Marketplace analytics/finance/HPP read evidence

Tanggal: 2026-11-01
Status: `PASS` untuk fixture terisolasi; provider Live, production MySQL, dan cutover tetap menjadi approval operasional terpisah.

## Kontrak dan batas

- Laravel route: `GET /pilot/marketplace/analytics`.
- Legacy baseline yang tetap aktif: `/marketplace.php?tab=analitik` dan `/api/marketplace-analytics.php`.
- Workspace: `marketplace`; company dan store ditentukan server-side.
- Query hanya membaca `marketplace_stores`, `marketplace_oauth_connections`, `marketplace_orders`, `marketplace_expenses`, dan binding integrasi bila tersedia.
- Filter yang dipertahankan: period, date range, created/released date basis, all/settled mode, dan store scope.
- Nilai `null`, unknown, estimated, dan `0` tidak disamakan. Metadata coverage/source ikut dikirim ke props Inertia.
- Provider call: `false`. Tabel aplikasi yang ditulis: tidak ada.

## Implementasi

- Adapter [MarketplaceAnalyticsReadService.php](../../laravel-foundation/app/Pilots/Marketplace/MarketplaceAnalyticsReadService.php) menjalankan service baca legacy pada PDO Laravel yang sama setelah memasang session company sementara dan memulihkannya di `finally`.
- Controller [MarketplaceAnalyticsController.php](../../laravel-foundation/app/Http/Controllers/MarketplaceAnalyticsController.php) menangani scope, store asing, store belum terhubung, dan batas periode legacy 1–45 hari.
- Request [MarketplaceAnalyticsRequest.php](../../laravel-foundation/app/Http/Requests/MarketplaceAnalyticsRequest.php) memvalidasi store, periode, basis tanggal, dan mode analytics.
- Halaman [MarketplaceAnalytics.vue](../../laravel-foundation/resources/js/Pages/Pilot/MarketplaceAnalytics.vue) menampilkan coverage, omzet, fee, net income, HPP, laba, biaya, ROAS, fee detail, source metadata, dan data harian tanpa tombol write/provider.
- Route ditambahkan di [web.php](../../laravel-foundation/routes/web.php). Legacy URL tidak dihapus atau dialihkan.
- Fixture [marketplace_price_smoke.php](../../tests/Integration/marketplace_price_smoke.php) disesuaikan agar mengisi `source_updated_at` yang diwajibkan dump database saat ini; tidak ada perubahan pada logic provider atau kontrak aplikasi.

## Hasil verifikasi

| Pemeriksaan | Hasil |
| --- | --- |
| Legacy finance smoke | PASS: signed fees, tax, subsidy, seller discount, unknown income, true zero, idempotence, unsettled/final, estimated profit setelah HPP |
| Legacy analytics smoke | PASS: settled/pending, adjustments, expenses, coverage, all-row aggregation, daily/store reconciliation, date basis, scope, validation, safe view, XLSX structure |
| Legacy price smoke | PASS: scope, selected variants, base/promo separation, verification, duplicate protection, dan HPP effective-date fixture |
| Laravel focused analytics | PASS: 7 tests, 67 assertions |
| Laravel full suite | PASS: 54 tests, 498 assertions |
| Same-fixture parity | PASS: payload analytics legacy vs Laravel dibandingkan setelah menghapus `generated_at`; nominal/coverage/source/date basis sama |
| Foreign store | PASS: HTTP 403 |
| Unknown finance/HPP | PASS: `net_income`, `cogs`, dan `gross_profit` tetap `null`; coverage count tetap ada |
| Period >45 hari | PASS: HTTP 422 |
| Route inspection | PASS: `GET|HEAD pilot/marketplace/analytics` terdaftar sebagai `pilot.marketplace.analytics` |
| TypeScript/Vue build | PASS: `npm.cmd run build` |
| PHP formatting | PASS: Pint pada file PHP Step 5 dengan PHP 8.4 + `php.ini` |
| Problems | PASS: tidak ada error pada file Step 5 |
| Diff whitespace | PASS: `git diff --check` tanpa error; hanya warning line-ending Git yang sudah ada |

## Bukti instance MySQL terisolasi

- Target: database `jago_laba` pada `127.0.0.1:3307` dengan datadir sementara `D:\xampp\mysql\data-step5-marketplace-price`.
- Sumber restore: `C:\Users\PT JAGO LABA\Downloads\jago_laba.sql`.
- SHA-256 sumber: `445EE9B2DB2C78A9AB8C952773076C733FCC63B43AD80BDD06B15445CC3A7B64`.
- Restore memakai `FOREIGN_KEY_CHECKS=0` hanya selama import dump karena dump memiliki row historis yang tidak konsisten; smoke test kemudian membuat schema test turunan dari database tersebut.
- Sebelum shutdown: 117 tabel sumber dan 16 row HPP terbaca; database test `jl_sync_test_*` dibersihkan oleh `finally` smoke test.
- Server 3307 dimatikan normal setelah test; tidak ada proses `mysqld/mariadbd` atau listener 3307 yang tersisa.

Provider yang dipakai smoke test adalah fake provider di fixture. Tidak ada request provider Live, token Live, atau database production.

## Rollback

Hapus route/controller/request/adapter/page/CSS/test Step 5 saja bila pilot dibatalkan. Biarkan `/marketplace.php?tab=analitik` dan `/api/marketplace-analytics.php` tetap aktif; database sementara test dapat dibuang sesuai prosedur cleanup lokal, dan tidak ada migrasi schema atau data production yang perlu di-rollback.

## Next task

`MKT-READ-002` selesai. `MKT-FILE-001` dilanjutkan sebagai alur preview XLSX terisolasi; lihat [marketplace-file-preview-evidence-20261101.md](marketplace-file-preview-evidence-20261101.md). Jangan melakukan cutover global, mengaktifkan provider Live, atau menjalankan price/write smoke pada database production.
