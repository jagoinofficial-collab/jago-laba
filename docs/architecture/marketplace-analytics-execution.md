# Analitik & Laporan per Toko — implementasi 28 Oktober 2026

Halaman `marketplace.php?store=<id>&tab=analitik` sekarang memakai MarketplaceAnalyticsService, bukan ringkasan lama getStoreDashboard. Sumbernya catatan pesanan API/import toko berwenang serta pengeluaran khusus toko yang memengaruhi laba. Tidak ada pengambilan seluruh API saat membuka halaman.

## Sudah tersedia
- Filter periode (hari ini, kemarin, 7 hari, bulan ini/lalu, rentang maksimal 45 hari) dan dasar tanggal pesanan/settlement sumber. Pembanding sepanjang periode sebelumnya.
- Identitas platform dan lingkungan dari binding koneksi bila tersedia; tidak menebak Live/Sandbox dari nama toko.
- KPI final + estimasi dan final terpisah. Bukti keuangan final tidak disebut dana masuk bank.
- Penjualan memakai finance_revenue bila tersedia. Potongan bersih = penjualan - Untuk Dibayar. Rincian biaya asli tetap ditampilkan terpisah dan tidak dikurangkan ulang.
- Laba setelah HPP = Untuk Dibayar - HPP; penyesuaian sumber tidak dihitung dua kali. Pembatalan terkonfirmasi dikeluarkan, permintaan pembatalan dipisahkan. Retur dengan kuantitas belum diketahui ditandai HPP belum lengkap.
- Jumlah data final, belum final, menunggu keuangan, HPP belum lengkap, rekonsiliasi, dan status operasional.
- Grafik/tabel harian, rincian biaya, unit produk/varian dari detail pesanan, tautan transaksi/pemasukan/riwayat.
- Ekspor XLSX lima sheet: Metadata, KPI, Harian, Biaya, Produk. Inline text mencegah nama produk/toko menjadi formula spreadsheet. Tidak membutuhkan ZipArchive atau library baru.
- Refresh database setiap 30 detik, kegagalan mempertahankan tampilan terakhir. Filter yang sedang diubah dipertahankan.

## Batas yang ditampilkan jujur
- Laba bersih operasional belum dinyatakan final: keberadaan pengeluaran tidak membuktikan kelengkapan seluruh iklan/operasional. Subtotal yang tersedia diberi label dan jumlah pesanan pembentuknya.
- Laporan atribusi advertiser belum disambungkan di halaman ini. Rasio penjualan toko/iklan diberi nama tersendiri dan bukan ROAS kampanye.
- Produk menampilkan kuantitas, varian, SKU, dan retur sumber. Belum ada alokasi biaya tingkat pesanan atau laba per SKU.
- Penarikan dan bank ditelusuri melalui modul Pemasukan. PDF ringkasan belum disediakan.
- Zona laporan IDR/Asia Jakarta; dukungan lintas mata uang/zona perlu normalisasi tambahan. Layanan lama masih memakai nominal float; migrasi decimal seluruh pipeline bukan bagian dari perubahan ini.
- Grafik kosong tidak membuktikan API sudah lengkap. Waktu hitung database berbeda dari waktu sinkronisasi berhasil.

## Verifikasi
Jalankan dari root proyek:
```
D:/xampp/php/php.exe tests/Integration/marketplace_analytics_smoke.php --preview
D:/xampp/php/php.exe tests/Integration/marketplace_finance_smoke.php
D:/xampp/php/php.exe tests/Integration/marketplace_dashboard_smoke.php
node tests/Feature/marketplace_analytics_browser.cjs
```
Test analitik memakai SQLite memory (tidak mengubah pesanan nyata), memeriksa isolasi perusahaan/toko, penyesuaian, final/estimasi, seller discount, pembatalan, semua baris tanpa batas UI, agregasi harian, tanggal, escaping HTML dan struktur XLSX. Browser memeriksa desktop1440/mobile390, overflow dan disclosure rumus dengan fixture lokal. Tidak menguji otorisasi advertiser atau rekonsiliasi seluruh data Live secara end-to-end.
