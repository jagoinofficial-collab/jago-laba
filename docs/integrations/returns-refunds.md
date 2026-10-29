# Pengembalian Barang dan Dana

Implementasi 29 Oktober 2026. Penyimpanan memakai marketplace_sync_records resource_type=return: tidak ada salinan kasus atau migrasi baru.

## Alur
Pesanan → Pengembalian Barang. Tautan order_tab=complaint tetap diterima. Subfilter jenis kasus dan basis tanggal kasus/pesanan tersedia. Hitungan memakai pesanan unik; satu pesanan dapat memiliki beberapa kasus. Detail kasus juga tersedia melalui rincian Transaksi & Retur. Filter transaksi memakai relasi kasus, bukan flag komplain.

TikTok: POST /return_refund/202602/returns/search, scope seller.return_refund.basic. Pagination mempertahankan rentang update_time_ge / update_time_lt dan sort_field=update_time. Respons tanpa daftar tidak dianggap kosong kecuali total_count=0. Kasus yang memiliki order lama yang belum tersimpan mengambil detail order secara terarah. Respons lama ditolak melalui update_time; upsert kasus idempotent. Perubahan kasus menandai keuangan order untuk diperiksa lagi oleh job finance. Checkpoint hanya selesai setelah rentang disimpan melalui worker yang sudah ada.

Pengaturan auto-sync menyediakan opsi returns setiap 15 menit untuk TikTok (opt-in; tidak diaktifkan diam-diam). Layanan worker/penjadwal yang sudah ada harus berjalan. Halaman membaca data tersimpan, bukan menarik semua API ketika dibuka.

## Keuangan dan stok
Refund SUCCESS ditampilkan sedang diproses; COMPLETE menandakan selesai. Refund tanpa barang tidak menandai retur fisik. Nilai kasus tidak dikurangi lagi dari settlement sumber. Tidak ada penambahan stok, pembalikan HPP, persetujuan kasus atau pembayaran refund otomatis.

## Batas yang belum selesai
- Konektor Return/Refund Shopee belum terverifikasi; job Shopee tetap hanya katalog, pesanan, keuangan. Dokumentasi resmi tidak dapat diakses pada sesi ini. UI menampilkan cakupan belum lengkap, bukan keberhasilan semu.
- Verifikasi internal penerimaan/kondisi barang dan pembalikan HPP terkontrol belum dibuat. Barang kembali tetap memerlukan pemeriksaan gudang.
- Rincian item tetap tersimpan utuh pada payload sumber; panel menampilkan identitas/status/nominal/alasan/resi/waktu kasus, belum tabel item kasus per SKU.
- Pengujian menggunakan fixture/database terisolasi. Belum ada bukti respons Return API dari toko Live pada sesi ini, dan tidak ada izin/token toko yang diubah.
- Webhook baru tidak diaktifkan. Coverage yang tersedia berarti pernah ada job returns berhasil; kegagalan terbaru tetap ditampilkan.

## Pengujian
D:/xampp/php/php.exe tests/Integration/marketplace_orders_smoke.php
D:/xampp/php/php.exe tests/Integration/marketplace_sync_smoke.php
Tes mencakup kasus ganda, pesanan lama, refund proses vs selesai, nilai belum diketahui, status baru, relasi toko, filter, pagination, idempotensi, akses dan kegagalan API. Tampilan diperiksa dengan Playwright pada 1440px dan 390px menggunakan data uji.

Rujukan: https://partner.tiktokshop.com/docv2/page/search-returns-202602
