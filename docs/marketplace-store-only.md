# Marketplace per toko — 26 Oktober 2026

Operasional Marketplace kini selalu memakai satu toko terintegrasi dalam perusahaan aktif. Identitas toko divalidasi ulang setiap permintaan; pilihan terakhir dari sesi tidak dipercaya tanpa validasi. Toko yang perlu otorisasi ulang tetap dapat dibuka untuk melihat riwayat yang tersimpan.

Tanpa toko: Pengaturan → Tambah Toko. Satu toko: langsung dashboard. Beberapa toko: pilih toko atau buka pilihan terakhir yang masih diizinkan. `choose_store=1` selalu membuka pemilih. Tautan `scope=global` lama diarahkan ke menu setara pada toko aktif; pengaturan dan callback lama tetap diterima. Data dan tabel lama tidak dihapus.

Pengaturan perusahaan dan Integrasi API tetap terpusat. Halaman operasional tidak lagi memanggil getGlobalOverview, inventori pabrik, alokasi lintas toko, semua jurnal, atau konfigurasi seluruh toko pada setiap permintaan. Katalog dengan store_id hanya memeriksa toko tersebut. Pemantauan browser hanya memakai toko aktif; penjadwal dan worker server toko lain tidak diubah.

## Verifikasi
- Render PHP dengan error handler ketat: dashboard, transaksi, produk, pengeluaran, pemasukan, analitik, marketing, pengaturan.
- Smoke tests: pemilihan toko (0/1/banyak, sesi lama dan penolakan ID asing), OAuth, callback origin, katalog/HPP, pemasukan, stok dan isolasi perusahaan.
- Benchmark baca lokal untuk pemuatan data transaksi, satu proses per mode: alur lama 681 ms / 32 pernyataan database / 14 MiB puncak; alur baru 24,8 ms / 7 pernyataan / 8 MiB. Angka bergantung cache dan beban. Bukan pengukuran waktu tampil browser. `SHOW SESSION STATUS Questions` juga menghitung pemeriksaan status terakhir. Tidak ada klaim penurunan ukuran database.
- Tidak menghapus riwayat, token, konfigurasi, HPP, transaksi atau penjadwal. Tidak mengirim perubahan harga/stok ke marketplace.

Perintah audit baca: `php -d session.save_path=storage tests/marketplace_page_readcheck.php transaksi`; benchmark `tests/marketplace_loading_benchmark.php before|after` memakai perusahaan lokal dan hanya membaca data.
