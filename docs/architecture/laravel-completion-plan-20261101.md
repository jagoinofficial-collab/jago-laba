# Rencana penuntasan migrasi Laravel Jago Laba

Tanggal: 1 November 2026. Status: rencana eksekusi; bukan persetujuan perubahan produksi.

Eksekusi Step 1–4 sudah dimulai dan dibuktikan pada [laporan hasil](../deployment/laravel-step1-4-execution-20261101.md). Inventaris teknis, staging lokal, backup, audit, registry dan rehearsal schema tersedia. Acceptance penuh masih tertahan pada keputusan data dan bukti hosting HTTPS; jangan menandai semua langkah selesai.

## Tujuan dan baseline

Selesai berarti seluruh fitur bisnis dalam inventaris berjalan melalui Laravel, data dan hasil keuangan cocok, integrasi nyata tervalidasi, hosting stabil, rollback terbukti, dan aplikasi lama tidak lagi menjadi ketergantungan runtime. Berhasil build atau membuka dashboard belum memenuhi tujuan ini.

Baseline yang dipertahankan: foundation, auth/scope pilot, dashboard baca, daftar/detail pesanan, analitik baca, expense pilot, withdrawal preview, OAuth sandbox dan queue pilot sudah ada. Verifikasi lokal terakhir yang tercatat: 58 tes / 532 assertions dan artifact r4. Validasi ulang hanya bila perubahan atau kegagalan membutuhkan; jangan mengulang pembangunan foundation.

Sumber utama:
- [Gate 1 November](../deployment/staging-release-gate-20261101.md)
- [Inventaris endpoint/action/RPC](endpoint-inventory-matrix.md)
- [Backlog teknis terdahulu](php-modernization-next-phase-agent-plan.md)
- [Keputusan data orphan](../deployment/orphan-variant-owner-reconciliation-20261101.md)

## Cara mengerjakan dan mengukur

Kerjakan satu alur bisnis per task: request, otorisasi, service, transaksi, UI, kompatibilitas, dan pengujian yang diperlukan. Tiap task mencatat pemilik, dependensi, commit/artifact, hasil uji, risiko dan rollback. Status: belum mulai / dikerjakan / terhambat / lulus. Jangan memakai satu centang untuk seluruh modul jika masih ada endpoint/action/RPC tertinggal.

Setiap mutasi wajib menjaga company/store/workspace/role/CSRF dari server, nominal dan tanggal HPP, transaksi, audit dan idempotency bila relevan. Satu proses bisnis hanya memiliki satu penulis aktif. Secret disimpan melalui konfigurasi privat, bukan chat atau artefak. Test/restore memakai target terisolasi. Migrasi produksi, koreksi data, provider Live dan cutover memerlukan tugas operasional serta otorisasi tersendiri; dokumen ini tidak menjalankannya.

## Langkah 1 — Kunci daftar pekerjaan dan kontrak

Pemilik: pengembang + pemilik proses bisnis. Dependensi: tidak ada.

- Cocokkan inventaris dengan kode terbaru, menu, tombol, ekspor, RPC, action AJAX, webhook dan command terjadwal.
- Tandai setiap item: sudah ada di Laravel / sebagian / belum; sertakan URL lama/baru, tabel, role, perilaku, test, pemilik dan dependensi.
- Daftarkan fitur yang belum berfungsi di aplikasi lama; pemilik memutuskan dipertahankan, dibangun atau dihentikan, agar bug lama tidak dianggap kontrak wajib.
- Pecah langkah 6–11 menjadi task per alur berdasarkan inventaris ini.

Hasil: checklist cakupan lengkap beserta kontrak input/output. Lulus jika tidak ada fungsi bisnis tanpa keputusan dan task. Persentase progres baru dihitung dari checklist tersebut, bukan perkiraan.

## Langkah 2 — Siapkan staging lengkap dan terisolasi

Pemilik: operator hosting + pengembang. Dependensi: langkah 1.

- Tentukan origin HTTPS, document root Laravel public, PHP 8.4, database Marketplace dan Konveksi terpisah, akun database, private storage dan lokasi secret.
- Sediakan backup authoritative kedua database, file privat dan key lama melalui kanal privat; catat checksum.
- Pulihkan ke staging; blok provider Live, scheduler lama/baru, browser trigger dan jalur tulis keluar sampai pengujian yang sesuai.
- Buktikan konfigurasi Konveksi mengarah ke staging, bukan database asli. Periksa izin file, log dan host allowlist.

Hasil: staging yang dapat direproduksi dan catatan isolasi. Lulus jika DB/file/key lengkap, health berhasil, dan tidak ada koneksi tulis ke produksi.

## Langkah 3 — Rekonsiliasi data dan bereskan orphan

Pemilik: pemilik data stok/keuangan + pengembang. Dependensi: langkah 2.

- Gunakan paket keputusan 17 referensi varian tanpa induk; jangan menebak mapping dari nama/ID/jumlah stok.
- Pemilik menetapkan mapping atau perlakuan setiap baris, termasuk stok dan HPP nol, berdasarkan sumber authoritative.
- Rekonsiliasi temuan baseline 1.720 unit dan nilai tersirat Rp62.494.000 terhadap ledger; periksa ulang terhadap backup staging terkini.
- Siapkan perubahan terpisah dengan backup, before/after dan rollback; uji dahulu pada salinan staging.
- Periksa duplikasi, relasi, saldo stok, pesanan, nominal dan histori HPP lintas kedua database.

Hasil: keputusan data dan rekonsiliasi yang ditandatangani pemilik. Lulus jika tidak ada selisih tanpa penjelasan; tidak menghapus atau menyamarkan histori demi meluluskan pemeriksaan.

## Langkah 4 — Tetapkan schema dan urutan migrasi

Pemilik: pengembang database. Dependensi: langkah 2–3.

- Daftarkan migration yang benar-benar diperlukan per fitur beserta urutan dependensi, checksum dan kondisi awal.
- Verifikasi kebutuhan session/cache/jobs/failed_jobs, idempotency, indeks dan tabel domain terhadap schema hasil restore.
- Siapkan post-check, collision policy dan rollback termasuk data yang ditulis setelah migrasi.
- Jalankan hanya daftar yang disetujui pada staging; tidak menjalankan seluruh folder migrasi legacy.

Hasil: registry schema dan bukti rehearsal. Lulus jika schema sesuai target tanpa kehilangan ID, saldo atau histori dan aplikasi lama tetap kompatibel selama transisi.

## Langkah 5 — Tuntaskan login, navigasi dan otorisasi

Pemilik: pengembang aplikasi + penguji. Dependensi: langkah 2 dan 4 bila schema diperlukan.

- Tuntaskan halaman login, logout, workspace chooser dan navigasi utama; hilangkan dead-end pada halaman yang siap dimigrasikan.
- Uji sesi lama/baru, kedaluwarsa, CSRF, pembatasan percobaan login, dan hak setiap peran yang terdaftar.
- Uji penolakan akses lintas perusahaan/toko/workspace melalui URL langsung dan request buatan.
- Buktikan secure cookie, HTTPS, trusted proxy dan absolute URL pada staging sebenarnya.

Hasil: matriks pengguna/peran lulus di hosting, bukan hanya fixture.

## Langkah 6 — Tuntaskan seluruh fitur baca dan laporan Marketplace

Pemilik: pengembang Marketplace + pemilik laporan. Dependensi: langkah 1–5.

- Pertahankan pilot orders/analytics yang telah ada; lengkapi filter, paging, detail, status, katalog, stok, keuangan, HPP/laba dan layar pengaturan baca sesuai inventaris.
- Migrasikan seluruh ekspor CSV/XLSX dan unduhan terkait; cocokkan isi, tipe nominal, periode, zona waktu dan batas akses.
- Bandingkan aplikasi lama dan Laravel pada fixture yang sama, lalu snapshot staging yang sama.

Hasil: setiap endpoint baca dalam cakupan berstatus lulus. Nilai unknown/null/estimated tidak disulap menjadi nol.

## Langkah 7 — Tuntaskan mutasi lokal Marketplace

Pemilik: pengembang Marketplace + pemilik proses. Dependensi: langkah 4–6.

- Pertahankan expense yang sudah ada; inventarisasi dan migrasikan mutasi lokal lainnya per action: katalog/varian, stok, HPP, pengaturan, biaya, dan pencatatan keuangan sesuai fitur nyata.
- Pisahkan withdrawal preview dari penyimpanan/pelaksanaan final; jangan menganggap preview sebagai transaksi selesai.
- Untuk setiap action: validasi, otorisasi server, transaksi, audit, idempotency serta uji retry, double-submit dan konflik data yang relevan.
- Tetapkan pemilik penulisan untuk mencegah legacy dan Laravel mengubah proses yang sama bersamaan.

Hasil: seluruh action lokal diuji lengkap; tindakan yang memanggil provider diselesaikan pada langkah 11.

## Langkah 8 — Tuntaskan UI dan operasi Konveksi

Pemilik: pengembang Konveksi + operator bisnis. Dependensi: langkah 3–5.

- Bangun UI operasional dari adapter baca yang tersedia; petakan seluruh method RPC ke alur pengguna.
- Kerjakan bertahap sesuai dependensi: master data → bahan/produk/stok → pembelian/penerimaan → pesanan/penjualan → produksi/QC → pengiriman → kas/pembayaran/laporan. Sesuaikan dengan inventaris aktual dan dependensi yang ditemukan.
- Uji tiap alur sebelum lanjut: stok tidak berlebih terjual, retry tidak menggandakan transaksi, VOID konsisten, HPP/tanggal dan saldo sesuai baseline.
- Perubahan model/schema besar dipisahkan dari pemindahan perilaku; jangan sekaligus menulis ulang seluruh domain.

Hasil: seluruh menu, tombol dan RPC Konveksi dalam cakupan berfungsi di Laravel serta lulus rekonsiliasi.

## Langkah 9 — Tuntaskan dokumen dan file privat

Pemilik: pengembang + operator storage. Dependensi: langkah 2, 5 dan alur terkait pada 6/8.

- Inventaris dokumen lama beserta metadata, ukuran, checksum dan kepemilikan.
- Siapkan preview/download/upload Laravel dengan pemeriksaan izin; file privat berada di luar webroot.
- Uji PDF, invoice, lampiran, ekspor dan resi resmi sesuai fitur; pertahankan barcode/dokumen provider.
- Latih migrasi lokasi file dengan mapping dan rollback, lalu buktikan file tidak dapat dibaca tanpa izin.

Hasil: seluruh file terbaca oleh pengguna berhak, checksum cocok dan tidak ada tautan publik yang membocorkan file privat.

## Langkah 10 — Buktikan queue dan scheduler di hosting

Pemilik: pengembang + operator hosting. Dependensi: langkah 4–5.

- Pasang satu cron scheduler dan worker database berumur singkat sesuai target shared hosting.
- Uji dispatch atomik, checkpoint, lease/overlap, timeout, restart, retry, failed job dan heartbeat menggunakan data staging/sandbox.
- Buktikan cron legacy yang tumpang tindih tidak aktif; tetapkan log, rotasi, pemantauan dan prosedur operator.

Hasil: bukti run/stop/restart/failure nyata di host. Provider Live tetap dimatikan.

## Langkah 11 — Integrasi provider dari sandbox sampai Live

Pemilik: pengembang integrasi + pemilik akun seller/provider. Dependensi: langkah 5–7, 9–10 sesuai operasi.

- Daftarkan exact HTTPS callback pada sandbox provider; uji state, replay, session/origin, multi-store dan enkripsi token.
- Implementasikan adapter nyata dan alur provider yang ada dalam inventaris: sinkronisasi katalog/pesanan/stok/keuangan, webhook, dokumen pengiriman dan tindakan fulfillment yang didukung.
- Uji pagination/checkpoint, rate limit, timeout, token refresh, duplikasi event dan rekonsiliasi pada sandbox.
- Catat batas dukungan per platform; platform/operasi yang belum didukung harus dinyatakan dan diputuskan dalam cakupan, bukan dianggap selesai.
- Setelah persetujuan provider dan seller, lakukan tugas Live terbatas dengan cakupan, kredensial privat, pemantauan dan rollback yang jelas.

Hasil: bukti per platform/per operasi. Sandbox lokal saja tidak meluluskan langkah Live; operasi tulis provider tidak diaktifkan otomatis.

## Langkah 12 — UAT lengkap dan rehearsal rollback

Pemilik: penguji + pengguna bisnis + operator. Dependensi: langkah 6–11.

- Uji alur pengguna menyeluruh untuk setiap peran dan workspace, desktop serta seluler; cek semua menu/tombol dari langkah 1.
- Bandingkan jumlah data, ID, stok, saldo, HPP, laba, laporan, file dan audit dengan snapshot baseline.
- Uji beban representatif, kegagalan jaringan/provider dan pemulihan job tanpa transaksi ganda.
- Bangun artifact baru dari source final; scan secret, verifikasi dependency dan asset. Artifact r4 adalah baseline, bukan otomatis artifact final setelah perubahan kode.
- Latih upgrade dan rollback dengan data yang berubah setelah upgrade; tentukan cara menjaga transaksi baru saat kembali ke legacy. Restore backup lama saja tidak cukup.

Hasil: cakupan 100% diputuskan, seluruh tes wajib lulus, tanpa masalah kritis/tinggi terbuka dan pengguna menyetujui hasil. Pengecualian harus eksplisit dan tidak boleh menyembunyikan fitur yang belum dimigrasikan.

## Langkah 13 — Persiapan dan pelaksanaan cutover

Pemilik: operator + pengembang + pemilik aplikasi. Dependensi: langkah 12 dan persetujuan operasional eksplisit.

- Siapkan paket review: checksum artifact, daftar migration, backup final DB/file/key, rekonsiliasi, konfigurasi host/cron, runbook dan pemicu rollback.
- Tetapkan maintenance window dan siapa yang memutuskan lanjut/kembali. Ambil backup final, hentikan penulisan, selaraskan perubahan sejak rehearsal, dan validasi lagi.
- Jalankan perubahan yang disetujui, alihkan document root/traffic dan aktifkan satu penulis serta satu scheduler.
- Smoke login, workspace, pesanan, transaksi, stok, laporan, dokumen, callback dan queue; jika batas kegagalan yang disepakati terlampaui, jalankan rollback.

Hasil: traffic bisnis Laravel aktif dan bukti smoke/rekonsiliasi tersimpan. Jangan mengalihkan hanya karena root Laravel menampilkan HTTP 200.

## Langkah 14 — Observasi produksi dan penutupan legacy

Pemilik: operator + pengembang + pemilik bisnis. Dependensi: langkah 13.

- Amati setidaknya satu siklus operasional yang disepakati, termasuk sinkronisasi, transaksi, rekonsiliasi harian dan laporan periode yang digunakan bisnis.
- Pantau error, gagal login, antrean, duplikasi, saldo/stok, callback dan unduhan; tetapkan ambang serta penanggung jawab sebelum cutover.
- Setelah periode stabil dan persetujuan pemilik, cabut cron/worker/akses tulis legacy. Pertahankan facade URL yang masih dipakai sampai caller dimigrasikan.
- Pastikan adapter Laravel tidak lagi memuat source legacy; pindahkan ketergantungan tersisa dengan test kontrak sebelum arsip.
- Arsipkan source/backup sesuai retensi; hapus facade hanya setelah bukti tidak ada caller dan rollback tidak lagi memerlukannya. Jangan hapus database/histori sebagai bagian otomatis.
- Serahkan runbook deploy, backup/restore, pemulihan worker dan pengelolaan secret kepada operator.

Hasil akhir: Laravel mandiri, seluruh cakupan diselesaikan, operasional stabil, backup dapat dipulihkan dan tidak ada proses bisnis yang masih bergantung pada runtime lama.

## Urutan eksekusi dan penghambat saat ini

Jalur utama: 1 → 2 → 3 → 4 → 5 → 6 → 7 → 8 → 9 → 10 → 11 → 12 → 13 → 14. Dependensi rinci di setiap langkah menjadi acuan; pekerjaan independen boleh dijadwalkan bersamaan oleh tim, tetapi bukan alasan mencampur alur dalam satu task.

Kebutuhan dari pemilik/operator: staging HTTPS dan akses operator; backup file privat dan key melalui kanal privat; keputusan 17 orphan beserta ledger; akun uji/peran; registrasi sandbox provider; kemudian persetujuan seller/Live dan cutover. Sambil menunggu kebutuhan eksternal, pengembang dapat menyelesaikan inventaris, kontrak, UI dan test fixture untuk alur yang tidak bergantung pada akses tersebut.

Tidak menetapkan tanggal selesai sebelum inventaris langkah 1 dipecah dan kapasitas tim diketahui. Estimasi diberikan per task setelah dependensi dan akses siap; status implementasi, uji lokal, staging dan produksi dicatat terpisah.
