# Riwayat penarikan marketplace

Sumber saat ini: impor laporan resmi Seller Center (CSV UTF-8), terpisah dari pendapatan pesanan dan mutasi bank. Mendukung XLSX income TikTok asli: sheet Riwayat penarikan, baris Withdrawal saja. Sheet Laporan menyediakan mata uang dan zona waktu. CSV lama tetap didukung. Tidak ada endpoint penarikan yang diasumsikan tersedia.

TikTok Get Payments: https://partner.tiktokshop.com/docv2/page/get-payments-202605 — dokumentasi menyatakan belum tersedia untuk pasar SEA. Konektor penarikan Shopee belum diverifikasi/diimplementasikan. Tidak ada polling penarikan otomatis yang diklaim berjalan.

Alur: Pemasukan > Pencairan Marketplace > Impor laporan > pilih toko > template/susun kolom > unggah > tinjau > konfirmasi. Maksimum 2 MB dan 2.000 baris; pratinjau 15 menit. Nilai opsional kosong tetap NULL. Tanggal WIB dan format ISO; nominal desimal titik tanpa pemisah ribuan. Status asli disimpan dengan pemetaan eksplisit; status lain ditampilkan belum dipetakan. Tidak ada penghitungan net amount dari komponen yang belum tersedia.

Identitas unik company/store/withdrawal ID, upsert atomik, fingerprint baris dan berkas, audit perubahan, rekening hanya empat digit terakhir. File asli tidak disimpan. Tidak ada pencatatan jurnal, perubahan pendapatan pesanan, atau konfirmasi bank otomatis. Ringkasan berhasil memakai net_amount berstatus berhasil dan mata uang IDR saja.

Verifikasi: tests/marketplace_withdrawal_smoke.php mencakup parsing, masking, NULL, duplikasi, pembaruan status, audit, filter dan isolasi perusahaan. --preview menghasilkan fixture sintetis; hapus public/withdrawal-design-preview.html setelah QA.

XLSX: unggah langsung maksimal 10 MB. Total saldo bertanda negatif disimpan sebagai source_signed_amount, nominal penarikan ditampilkan positif. Earnings dan GMV Pay Deduction dikecualikan; Transferred dipetakan berhasil menurut laporan. Tidak mengarang biaya/net amount. Tanggal tanpa waktu dipertahankan dalam tanggal sumber UTC+8, bukan dikonversi dari tengah malam palsu. Tanggal lengkap dikonversi ke WIB. Formula tidak dievaluasi, XML eksternal ditolak, ZIP tidak diekstrak, ukuran uncompressed dibatasi. Uji lampiran memakai database disposable, tanpa memasukkan data ke toko asli.
