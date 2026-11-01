# MKT-FILE-001 — withdrawal XLSX preview evidence

Status: **PASS — isolated fixture**
Tanggal: 2026-11-01
Scope: satu alur `marketplace-withdrawals.php?action=preview`; final import/confirm tetap legacy.

## Kontrak yang dipertahankan

- `store_id` wajib berasal dari company context dan diverifikasi melalui store middleware.
- Role/action Marketplace `withdrawal.manage`, workspace Marketplace, dan browser CSRF wajib lulus.
- Ekstensi `csv`/`xlsx`, batas upload 10 MiB, parser XLSX seller report, tanggal WIB, mata uang, nominal, status, masking rekening, dan metadata source tetap memakai parser legacy.
- Preview mengembalikan `key`, jumlah, maksimal 10 baris, dan metadata; state preview terikat company/actor/store dan kedaluwarsa 15 menit.
- Tidak ada provider call, tidak ada file upload yang dipersistenkan, dan tidak ada insert ke `marketplace_withdrawals`.
- Endpoint rollback `/api/marketplace-withdrawals.php?action=preview` tetap tersedia. Endpoint `confirm` belum dipindahkan.

## Implementasi Laravel

- Route: `POST /pilot/marketplace/withdrawals/preview`
- Request: `MarketplaceWithdrawalPreviewRequest`
- Controller: `MarketplaceWithdrawalPreviewController`
- Adapter: `MarketplaceWithdrawalPreviewService`
- Parser yang dipakai: `MarketplaceWithdrawalXlsx` dan `MarketplaceWithdrawalService::parseCsv` dari legacy.

## Bukti verifikasi

1. PHPUnit fokus:

   ```text
   vendor/bin/phpunit tests/Feature/MarketplaceWithdrawalPreviewTest.php
   OK (2 tests, 26 assertions)
   ```

   Fixture XLSX sintetis valid diuji untuk nominal `1234.50`, tanggal `2026-10-01`, timezone `UTC+8`, ID 19 digit, scope store, dan session preview. Baris final `marketplace_withdrawals` tetap `0`.

   Suite Laravel penuh setelah perubahan:

   ```text
   OK (56 tests, 524 assertions)
   ```

2. Regresi parser XLSX legacy pada fixture nyata:

   ```text
   php tests/Integration/marketplace_withdrawal_xlsx_smoke.php "D:\DATA DOWNLKOAD\income_20261026083307(UTC+8).xlsx"
   PASS: attached XLSX, upload tmp filename, 16 withdrawals, exact IDs, 71,179,458 IDR, UTC+8 date precision, masking, and exclusion of earnings/ads.
   ```

   SHA-256 fixture: `1C32874695D6809C82AF34FE19FE221D9217A11629BD79E00F9AFD6E03A3CECE`.

3. Regresi berjalan pada MariaDB sementara `127.0.0.1:3307`, bukan XAMPP database utama dan bukan production. Database test smoke dibuat acak dan dihapus oleh `finally`; server sementara kemudian dihentikan. Tidak ada provider Live.

4. Static checks:

   - `php -l` untuk request, service, controller, dan test: lulus.
   - Problems untuk file Step 6 dan route: tidak ada error.
   - `git diff --check`: lulus; hanya warning line-ending pre-existing pada summary arsitektur.
   - Scan source Step 6: tidak ada token provider Live, dump, private upload path, atau URL provider yang ditambahkan. Credential dan encryption key pada test dibuat sementara untuk fixture lalu dipulihkan.

## Risiko dan rollback

Risiko yang tersisa adalah endpoint `confirm`/final financial import belum dimigrasikan, sehingga tidak boleh dipanggil melalui route baru. Rollback cukup menghapus route/controller/request/adapter/test Step 6; caller legacy dan endpoint confirm tidak diubah. Gate operasional production/shared hosting tetap di luar Step 6.
