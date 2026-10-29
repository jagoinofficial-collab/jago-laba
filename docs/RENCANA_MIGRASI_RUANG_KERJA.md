# Rencana klasifikasi dan migrasi ruang kerja

Status: **menunggu persetujuan**. Berkas `database/migration_workspace_review.sql` hanya rancangan DDL. Tidak dijalankan, dan tidak ada data operasional yang dipindahkan, digandakan, atau dihapus.

## Peta sumber

| Kelompok | Sumber saat ini | Bukti kepemilikan | Perlakuan |
| --- | --- | --- | --- |
| Pesanan, biaya, iklan, retur, jurnal, payout, toko | `marketplace_orders`, `marketplace_order_items`, `marketplace_expenses`, `marketplace_ad_spends`, `marketplace_return_claims`, `marketplace_journal_entries`, `marketplace_payout_reconciliations`, `marketplace_stores` | `store_id` dan referensi pesanan toko | Tetap marketplace; audit setiap baris tanpa `store_id` atau relasi toko yang valid. |
| Akun, saldo, mutasi, stok, produk, HPP | `accounts`, `cash_transactions`, `inventory_stocks`, `stock_mutations`, `products`, `product_variants` | Tidak ada penanda ruang kerja yang dapat dipercaya | Tandai **perlu ditinjau**. Jangan memasukkannya ke marketplace dari nama, SKU, atau rekening default saja. |
| Relasi lama lintas modul | `marketplace_stores.default_account_id`, `marketplace_expenses.account_id`, `marketplace_payout_reconciliations.account_id`, `marketplace_sku_mappings.product_variant_id`, `marketplace_stock_allocations.variant_id` | Referensi tabel konveksi, bukan bukti transfer | Pertahankan untuk audit sejarah. Buat referensi baru hanya setelah ada keputusan pemilik dan saldo awal. |
| Koleksi konveksi dari sistem lama | `system_table_map` dan tabel koleksi MySQL yang diakses `CollectionStore` | Nama koleksi, sumber dokumen, dan referensi transaksi konveksi | Tetap pada konveksi; periksa baris ambigu satu per satu. |
| Login dan identitas perusahaan | `auth_users_v2`, `system_users`, profil perusahaan | Bersama menurut kebutuhan produk | Tidak diklasifikasi sebagai data operasional. |

## Tahap yang perlu disetujui

1. Ambil backup database konsisten dan catat checksum, jumlah baris, serta rentang tanggal untuk setiap tabel di atas. Jalankan seluruh langkah lebih dulu pada **database pengujian**, bukan database operasional.
2. Jalankan DDL kosong pada database pengujian. Pastikan nama indeks, tipe ID dan constraint cocok dengan skema nyata. DDL dapat ditambah tanpa mengubah baris lama.
3. Buat daftar klasifikasi per baris dengan kolom `source_table`, `source_id`, `candidate_workspace`, `evidence`, `decision`, `reviewer`, `reviewed_at`. Bukti kuat: `store_id` yang valid, nomor pesanan platform, atau dokumen sumber yang terhubung. Jika bukti tidak cukup, gunakan `review_required`.
4. Tinjau rekening marketplace, saldo awal, produk/SKU, stok awal, dan semua mutasi historis yang pernah dibuat otomatis dari marketplace ke konveksi. Jangan membalik mutasi lama secara otomatis; rekonsiliasi manual dapat menghasilkan jurnal koreksi bertanggal dan beralasan.
5. Setelah daftar disetujui, salin **hanya baris yang disetujui** ke tabel target dengan ID sumber dan checksum rekonsiliasi. Proses harus idempoten dan dapat dihentikan sebelum cutover. Catat setiap transformasi.
6. Perbarui service agar baca/tulis akun, produk, HPP, dan stok marketplace dari tabel baru. Semua detail, pencarian, laporan, ekspor, unggahan, webhook, dan cron harus memvalidasi `store_id` serta ruang kerja server. Tambahkan uji isolasi dua arah.
7. Lakukan cutover setelah hasil hitung dan saldo disetujui. Tabel lama tetap read-only untuk jejak audit sampai masa retensi selesai.

## Pemulihan

Sebelum cutover, hentikan worker dan webhook, pulihkan snapshot database pengujian untuk rehearsal, lalu verifikasi checksum dan jumlah baris. Jika cutover produksi gagal, hentikan penulisan, pulihkan backup konsisten ke database terpisah, bandingkan transaksi yang terjadi sesudah backup, lalu putuskan replay berjejak atau rollback. Jangan menjalankan `DROP`, `DELETE`, atau skrip pembalikan otomatis pada data lama. Simpan log migrasi dan daftar keputusan peninjau.

## Batas implementasi saat ini

Endpoint dan halaman kini mensyaratkan sesi ruang kerja yang sesuai. Mutasi otomatis marketplace ke saldo/stok konveksi dihentikan. Inventori marketplace yang dahulu membaca master/stok konveksi tidak ditampilkan sebagai stok marketplace sampai klasifikasi selesai. Tabel target belum aktif; saldo, akun, produk, dan stok marketplace mandiri masih memerlukan migrasi yang disetujui. Kebijakan hak akses saat ini: owner dapat memilih keduanya; staf lama hanya konveksi, kecuali diberikan grant eksplisit setelah tabel `workspace_user_access` tersedia.
