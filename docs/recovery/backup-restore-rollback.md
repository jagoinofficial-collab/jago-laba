# Backup, restore dan rollback

Rancangan, belum dijalankan pada audit ini. RPO/RTO dan retensi produksi perlu disepakati berdasarkan volume/kebutuhan bisnis.

## Isi satu set backup

- DB lengkap: schema, data, registry migration, OAuth, antrean, metadata dan dokumen terenkripsi yang dibutuhkan.
- Runtime file privat/unggahan sesuai referensi DB, termasuk public/storage lama selama belum dipindah.
- Key enkripsi yang sama, tersimpan terpisah dan terlindungi.
- Konfigurasi pemulihan, versi artefak/PHP/DB, konfigurasi web/worker dan manifest checksum.

Jangan menyalin direktori data MySQL yang sedang berjalan sebagai pengganti backup konsisten. Pilih dump/snapshot sesuai engine setelah schema diverifikasi. Drive D menyediakan kapasitas lokal; backup pada mesin/disk yang sama belum melindungi dari kerusakan mesin atau ransomware. Sediakan salinan independen.

## Sebelum refaktor/cutover

1. Tentukan waktu konsistensi dan jeda penulis/scheduler/job; menutup browser saja tidak cukup.
2. Catat job aktif dan operasi yang sudah diterima marketplace; jangan mengirim ulang fulfillment secara buta.
3. Buat dump/snapshot DB konsisten dan salinan file yang sesuai, beserta ukuran/checksum.
4. Lindungi key/config melalui ACL dan media terpisah; jangan memasukkan secret ke laporan umum.
5. Periksa integritas, keterbacaan dan kapasitas penyimpanan.

## Restore rehearsal

Restore ke DB/folder terisolasi. Matikan scheduler dan outbound API tulis sebelum menjalankan hasil restore. Pasang key lama, URL staging dan verifikasi schema, jumlah/ID entitas, relasi, nominal uang, HPP, dokumen serta akses lintas perusahaan. Login atau SELECT 1 saja tidak cukup.

Catat durasi dan hasil. Backup hanya dinyatakan teruji setelah restore dan pemeriksaan data lulus. Audit ini tidak mengklaim backup lama sudah diuji ulang hari ini.

## Rollback

- Refaktor tanpa perubahan schema: kembalikan artefak/adapter/config sebelumnya setelah kontrak DB dipastikan cocok.
- Perubahan schema: gunakan expand/contract, jangan hapus tabel/kolom lama pada release pertama. Restore snapshot harus memperhitungkan transaksi setelah waktu backup dan rekonsiliasi.
- Cutover: hentikan penulis baru sebelum menghidupkan server lama; pastikan DNS, cookie dan callback konsisten agar tidak ada dua sumber tulis.
- Restore lokal tidak membatalkan perubahan di marketplace. Rekonsiliasi berdasarkan ID resmi, request ID dan status provider sebelum retry harga/promo/pengiriman.
- Aktifkan worker satu per satu setelah heartbeat, lock, scope dan idempotensi terverifikasi.

Tidak ada pembersihan data pelanggan untuk merapikan folder. Hapus backup hanya sesuai retensi yang disahkan dan setelah pengganti teruji.
