# Pelaksanaan PRD Konveksi — 29 Oktober 2026

## Sudah diterapkan
- Database operasional `jago_laba_konveksi` aktif dengan akun terbatas. Auth dan Marketplace tetap memakai database lama. Tidak ada pemisahan auth ke database ketiga.
- Allowlist koleksi Konveksi, transaksi atomik DML, serialisasi operasi, idempotensi permintaan, dan penolakan penimpaan data versi lama.
- Pembelian berulang tidak menggandakan stok/kas/hutang; penjualan menghitung nominal, HPP standar produk, laba negatif dan kembalian di server. VOID tidak membalik stok dua kali.
- Dokumen baru disimpan privat dan diakses melalui pemeriksaan sesi/perusahaan. Dokumen publik lama belum dimigrasikan.
- Alias RPC Google Sheets/Drive dan jalur keberhasilan simulasi offline dihapus. Tidak ada pemanggilan Apps Script pada domain Konveksi yang diperiksa.
- Panel kualitas data operasional dan tampilan responsif dasar.

## Pengosongan yang diotorisasi pengguna
Pilihan pengguna: data operasional Konveksi saja; akun, pengaturan, master dan Marketplace tetap disimpan.
- Menghapus 115 baris operasional di database aktif serta 115 baris salinannya di tabel Konveksi database lama.
- Pembelian, potong, jahit, keuangan, kasbon, pergerakan stok, pembayaran hutang dan operasional terkait diverifikasi kosong.
- Saldo awal rekening, stok produk/bahan dan saldo master stok dinolkan; identitas rekening/master tetap ada.
- Dipertahankan: 17 produk, 22 bahan, 10 pegawai, 7 supplier, 3 rekening, profil, pengguna, hak akses, template dan pengaturan HPP.
- Hitungan 43 tabel Marketplace/auth tidak berubah. Tidak memanggil API marketplace.
- Reset epoch menolak permintaan tulis dari halaman sebelum pengosongan. Cache browser versi lama dibatalkan.
- Tidak membuat backup baru. Backup historis, log audit dan berkas arsip lama tidak termasuk pengosongan ini; ini bukan penghapusan forensik seluruh salinan.
- Catatan hasil berisi hitungan saja: storage/temporary/konveksi-reset-result.json.

## Verifikasi
- PHP lint, dua blok JavaScript Core, adapter JavaScript dan pemeriksaan struktur lulus.
- Tes integrasi menggunakan database uji terpisah yang dibuang setelah selesai: retry, dedup, rollback, konflik versi, isolasi perusahaan, overselling SKU berulang, VOID, perhitungan uang/HPP dan kembalian.
- RPC baca database aktif: seluruh koleksi operasional yang diperiksa kosong; data master tetap ada; permintaan tulis sesi lama ditolak sebelum operasi dijalankan.
- Halaman localhost mengembalikan HTTP 200. Pesan umum Bad Request tanpa URL/ID permintaan belum dapat direproduksi.

## Pekerjaan PRD yang belum selesai
- Model relasional penuh, PO/penerimaan terpisah, produksi dan QC dengan biaya aktual batch, jurnal berpasangan serta endpoint per peran operasional belum seluruhnya dimigrasikan.
- Alur kompatibilitas tulis sementara dibatasi owner/admin. Data legacy masih terikat satu perusahaan yang diverifikasi.
- HPP penjualan adalah snapshot HPP standar master, bukan biaya aktual batch.
- Verifikasi visual menyeluruh desktop/ponsel dan seluruh alur operasional masih diperlukan.
