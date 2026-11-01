# KNV-READ-001 — Konveksi read pilot

Tanggal verifikasi: 2026-11-01
Status: **PASS WITH RISKS — isolated staging read dan legacy regression PASS; instance XAMPP lama pada port 3306 tetap bermasalah.**

## Selesai

- `GET /pilot/konveksi?tab=dashboard` tetap read-only dan memakai halaman Inertia.
- `loadSemuaData` tetap tersedia melalui adapter RPC lama dengan envelope `{ok,result}`.
- Collection allowlist, dynamic table mapping, `_row_order/_row_id`, metadata checksum, reset epoch, health, dan company binding dipertahankan.
- Laravel sekarang memilih database Konveksi terisolasi berdasarkan konfigurasi private deployment pada runtime non-testing; tidak fallback diam-diam ke koneksi default.
- Mapping table di luar allowlist dan company asing ditolak sebelum data dibaca.
- Route legacy `/index.php?tab=dashboard` tetap menjadi rollback path.
- Dump Konveksi diuji pada instance MariaDB staging sementara yang terisolasi di port 3307 tanpa memakai data directory XAMPP lama.

## Bukti fixture

- Feature test Konveksi Laravel membandingkan payload legacy dan Laravel pada SQLite in-memory yang sama.
- Foreign-company guard dan table-allowlist guard lulus.
- Tidak ada provider call, write data aplikasi, migration, atau schema change.

Perintah fokus:

```text
vendor/bin/phpunit tests/Feature/PilotReadOnlyTest.php --filter konveksi
```

Hasil focused setelah adapter koneksi/allowlist: **6 tests, 49 assertions, PASS**. Suite Laravel penuh: **50 tests, 457 assertions, PASS**.

Pemeriksaan tambahan: `php -l` pada service lulus, `php artisan route:list --path=pilot/konveksi` menemukan satu route GET/HEAD, dan `npm.cmd run build` lulus dengan chunk `Konveksi-DkgEOCov.js`.

## Bukti database staging aktual

- Instance MariaDB sementara berhasil start pada port 3307 dengan data directory baru; dump `jago_laba_konveksi` diimpor tanpa mengubah data directory XAMPP lama.
- Regression legacy `tests/Integration/konveksi_integrity_test.php`: **PASS** untuk retry purchase, key conflict, document dedup, stale write, cross-domain access, rollback, multi-line SKU, void idempotence, dan company isolation.
- Read Laravel pada database yang sama: `read_only=true`, `database_isolated=true`, 36 collection domain, produk 5, master stok 19.
- Metrik legacy dan Laravel cocok: `missing_stock_ids=5`, `duplicate_stock_ids=0`, `negative_stock=0`.
- Setelah regression, source staging tetap 43 tabel, 35 mapping, 5 produk, dan 19 master stok.

## Risiko operasional tersisa

Instance XAMPP lama pada `127.0.0.1:3306` masih abort saat membaca metadata replication/master-info. Verifikasi ini sengaja memakai instance staging sementara yang baru dan sudah dihentikan setelah pengujian; tidak ada repair, penghapusan metadata lama, atau perubahan schema.

Untuk deployment berikutnya, service database target harus memakai data directory yang sehat atau prosedur pemulihan yang disetujui operator. Risiko ini tidak menghalangi kelulusan read pilot pada dump staging terisolasi, tetapi tetap menjadi release gate operasional.
