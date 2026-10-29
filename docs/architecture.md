# Arsitektur Jago Laba

Dokumen ini menjelaskan arsitektur aktif setelah refaktor organisasi. Perubahan ini tidak mengubah rumus HPP, jurnal, status marketplace, atau isi data MySQL.

## Batas modul

| Modul | Tanggung jawab | Entry point aktif |
| --- | --- | --- |
| Shared | konfigurasi komposisi, koneksi database, workspace, audit, snapshot/import migrasi | `app/bootstrap.php` |
| Konveksi | dashboard, master, HPP, stok, pembelian, penjualan, keuangan, backup, RPC legacy | `public/index.php`, `public/api/rpc.php` |
| Marketplace | toko, pesanan, settlement, retur, biaya, HPP marketplace, scope toko, API integration boundary | `public/marketplace.php`, `public/api/marketplace-ajax.php` |
| Bridge Konveksi–Marketplace | transfer barang atau mapping SKU yang eksplisit; tidak boleh ada pemotongan stok implisit | kontrak domain marketplace yang sudah ada di service aktif |

## Alur request

```text
Browser
  -> public/*.php atau public/api/*.php
  -> app/bootstrap.php
  -> SharedRuntime + Module loader
  -> Controller/endpoint orchestration
  -> Service bisnis
  -> CollectionStore / query PDO MySQL
  -> response JSON atau HTML
```

`public/assets/js/jl-api.js` adalah satu-satunya API client frontend. Aplikasi tidak lagi menggunakan bridge browser atau runtime Apps Script.

## Database

`config/database.php` menjadi sumber konfigurasi koneksi. `app/Core/Database.php` menyediakan PDO dengan prepared statement, `utf8mb4`, InnoDB melalui schema yang tersedia, dan timezone aplikasi. `app/Database.php` tetap dipertahankan sebagai adapter kompatibilitas bagi script migrasi/seed lama.

`SnapshotImporter` dan collection dinamis hanya dipakai untuk kompatibilitas migrasi dan storage lama. Operasi baru tetap berjalan melalui MySQL dan service PHP.

## Integrasi eksternal

`app/Modules/Marketplace/Integrations/Contracts/MarketplacePlatformAdapter.php` menetapkan kontrak OAuth, verifikasi webhook, dan pengambilan order. Implementasi platform hanya boleh diaktifkan setelah credential resmi tersedia dan diverifikasi; endpoint yang belum siap tetap mengembalikan status belum aktif.
