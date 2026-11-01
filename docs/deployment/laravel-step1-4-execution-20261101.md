# Eksekusi Step 1–4 Laravel — 1 November 2026

## Keputusan

**Belum lulus seluruh acceptance Step 1–4.** Inventaris teknis, backup lokal, restore diagnostik, audit data, registry dan rehearsal schema sudah dikerjakan. Pengguna memilih staging lokal sementara karena hosting HTTPS belum tersedia. Koreksi data dan persetujuan baseline belum dapat dituntaskan karena data sumber berbeda. Jawaban pengguna “iyah lanjutkan” melanjutkan pekerjaan, tetapi belum menentukan saldo/HPP yang benar pada collision. Tidak ada perbaikan database aplikasi, provider Live atau cutover yang dilakukan.

## Hasil per langkah

| Langkah | Yang selesai dan terverifikasi | Yang belum selesai |
|---|---|---|
| 1 — Inventaris/kontrak | 178 item source dengan ID task, status, pemilik fungsi, target kompatibilitas/usulan, dependensi, lokasi/checksum dan kontrak baseline. 722 kemunculan kontrol/caller dipetakan ke 79 task file UI. 31 facade publik, 27 RPC, 29 AJAX action, 27 action endpoint lain, 24 menu Konveksi, 36 view Marketplace, 4 worker. ID unik dan source hash valid. | Keputusan pemilik atas fitur legacy yang sengaja nonaktif; kontrak yang belum terbukti runtime tetap ditandai untuk task fitur, bukan dianggap lulus. Angka item adalah permukaan implementasi yang dapat tumpang tindih, bukan 178 fitur bisnis unik. |
| 2 — Staging/backup | Backup privat di luar webroot dengan ACL terbatas. Kedua dump dipulihkan dan semua jumlah baris per tabel cocok dengan sumber: Marketplace 117 tabel / 9.531 baris; Konveksi 43 tabel / 128 baris. Key disalin/format 32 byte dan round-trip crypto lulus. Konveksi memakai koneksi staging terpisah. Akun aplikasi SELECT-only menolak DDL. HTTP health dan blok request tulis terbukti. | Baseline belum disetujui karena integritas data gagal. Pengujian hosting HTTPS/proxy/TLS/cookie nyata belum dijalankan; lokal bukan penggantinya. Snapshot SQL yang dipakai berasal dari 30 Oktober, bukan backup transaksi final untuk cutover. |
| 3 — Rekonsiliasi | Seluruh 38 FK Marketplace diperiksa; Konveksi tidak memiliki FK deklaratif sehingga juga diperiksa melalui adapter koleksi. Audit stok/HPP, candidate mapping, collision dan lembar keputusan dibuat. Perbandingan read-only ke database aplikasi aktif memastikan masalah yang sama masih ada. | 17 referensi varian stok, 5 referensi item pesanan, 5 produk Konveksi tanpa ID stok dan rekonsiliasi ledger belum diselesaikan. Tidak ada mapping, penggabungan, penghapusan atau perubahan saldo yang ditebak. |
| 4 — Registry/rehearsal | 5 migration Laravel dan 31 source migrasi legacy terinventaris. Empat migration eksplisit di clone diagnostik: cache, jobs, scoped idempotency dan sesi terpisah. Apply, rerun, session round-trip dan rollback lulus; semua data tabel asli cocok sesudah rollback. Default users migration diblok karena collision. | Belum ada persetujuan migration pada baseline tulis/rilis yang integritasnya gagal. Kebutuhan perubahan schema fitur berikutnya dibuka per task, bukan menjalankan semua migration. |

## Artefak dan lokasi

- [Checklist yang dapat dibaca](../architecture/laravel-feature-checklist-20261101.md), [CSV pelacakan](../architecture/laravel-feature-checklist-20261101.csv), [JSON kontrak/source/caller](../architecture/laravel-feature-checklist-20261101.json), [hasil pemeriksaan cakupan](../architecture/laravel-feature-coverage-20261101.json).
- [Registry terbaru](staging-migration-registry-20261101.json).
- Backup privat: `D:/JAGO_LABA_BACKUP/laravel-migration/20261101-step1-4`.
- Staging diagnostik: `D:/JAGO_LABA_STAGING/laravel-step1-4-20261101`.
- Bukti lokal: subfolder `evidence/` pada staging, meliputi restore, audit, dump parity, probe Laravel, schema rehearsal, perbandingan source aktif, scheduler isolation dan HTTP smoke.
- Petunjuk menjalankan ulang: `RUNBOOK.txt` pada staging. Provisioning menolak overwrite; clone terputus lama tidak dipakai sebagai baseline dan tidak dihapus.
- Akun/konfigurasi/kunci hanya berada di `private/`; jangan menyalin folder tersebut ke webroot atau commit.

Windows ACL backup dan staging mematikan inheritance serta membatasi akses kepada pengguna saat ini, SYSTEM dan Administrators. Ini pemeriksaan izin akses, bukan bukti enkripsi-at-rest atau restore hosting sebenarnya.

## Hasil restore dan isolasi

Marketplace `jl_s14_market` dan Konveksi `jl_s14_konveksi` berada pada MariaDB terpisah di loopback `127.0.0.1:3320`, datadir di staging. Clone schema yang dipakai adalah `jl_s14_schema_rehearsal_v2`; 70 trigger sumber juga disalin setelah penyalinan data. Clone pertama terputus saat sesi eksekusi berubah dan sengaja tidak dipakai. Sebelum melanjutkan, seluruh nama tabel, jumlah baris dan nama FK kedua baseline diverifikasi kembali terhadap dump sumber.

Replay dump menonaktifkan pemeriksaan FK sementara, kemudian seluruh FK diperiksa secara eksplisit. Import berhasil bukan berarti integritas lulus. Tidak ada schema baseline yang dikoreksi untuk menyembunyikan kegagalan audit.

Source file privat yang tersedia lokal hanya konfigurasi koneksi; folder dokumen tidak berisi dokumen. `document_files` pada kedua dump memiliki 0 baris. Backup mencatat kondisi kosong itu, bukan mengklaim menemukan arsip dokumen eksternal. Config koneksi asli disimpan sebagai backup saja; adapter staging menggunakan config baru yang hanya menunjuk ke schema staging.

HTTP lokal `http://127.0.0.1:8820` memakai allowlist khusus diagnosis:

| Request | Status |
|---|---|
| GET `/` | 200 |
| GET `/api/health.php` | 200 |
| GET `/api/diagnostics.php` | 403 |
| POST `/api/rpc.php` | 403 |

Hanya empat path GET foundation/health diizinkan. Ini bukan UI bisnis Laravel lengkap. Tidak ada scheduler/worker staging dipasang atau dijalankan. Pemeriksaan Scheduled Tasks menemukan 0 task yang merujuk path/schema staging ini. Proses aplikasi lama di luar staging tidak diubah. Endpoint provider staging diarahkan ke loopback yang nonaktif, credential provider dikosongkan dan OAuth tetap sandbox; tidak ada provider request dikirim.

## Data yang harus diputuskan

1. **17 referensi varian stok**: 1.720 unit, nilai tersirat Rp62.494.000. Candidate berdasarkan ID stok dan SKU sumber tersedia untuk semua baris, tetapi tidak otomatis menjadi mapping yang disetujui.
2. **Collision nyata**: stok ID 4 memiliki 418 unit dengan HPP Rp45.000. Master sumber yang menjadi kandidat menunjukkan 0 unit dengan HPP Rp45.000. Varian tujuan sudah memiliki stok ID 32 sebanyak 0 unit dengan HPP Rp90.000. Mengubah ID saja akan membentur unique warehouse/variant dan tidak menentukan saldo/HPP yang benar.
3. **5 item pesanan tanpa induk**: 7 unit, subtotal harga Rp630.000 dan subtotal HPP Rp320.000. Pemilik harus memastikan order induk yang benar atau menetapkan perlakuan historisnya berdasarkan sumber; tidak dihapus sebagai asumsi data contoh.
4. **5 produk Konveksi tanpa `id_stok_item`**: adapter membaca 36 koleksi; duplikasi ID stok 0 dan stok negatif 0, tetapi identitas produk tetap belum lengkap. Mapping harus diperiksa terhadap master stok tanpa membuat identitas baru sembarangan.

Perbandingan SELECT-only ke database aktif pada port 3306 menemukan jumlah masalah yang sama: 17 orphan stok, 5 orphan item pesanan dan 5 produk tanpa ID stok. Masalah tidak hanya berada pada backup lama. Pembacaan source tidak menjalankan bootstrap/repair/seed aplikasi dan tidak mengubah data.

Lembar review berada di staging privat:

- `private/orphan-proposed-mapping-review.csv` — candidate mapping dan collision untuk 17 stok.
- `private/orphan-owner-decisions.csv` — keputusan lengkap beserta sumber/ledger/persetujuan.
- `private/orphan-source-candidates.json` — provenance candidate dan data benturan.
- `private/orphan-order-items-owner-decisions.csv` — keputusan 5 item pesanan.
- `private/konveksi-stock-identity-owner-decisions.csv` — identitas 5 produk Konveksi.

Setelah sumber authoritative dan keputusan tersedia: buat perubahan terpisah dengan before/after, latihan pada clone, buktikan saldo/HPP/ID/histori tetap benar, jalankan kembali seluruh FK dan health adapter, lalu minta penerimaan baseline. Persetujuan tidak boleh dibuat atas nama pemilik oleh pengembang.

Usulan 16 mapping tanpa collision sudah diuji dalam transaksi pada clone `jl_s14_schema_rehearsal_v2`: orphan stok turun sementara dari 17 menjadi 1, jumlah stok dan valuasi total tetap sama. Stok ID 4 ditahan karena collision. Transaksi kemudian di-rollback; fingerprint seluruh data stok kembali persis seperti sebelum uji. Bukti `evidence/orphan-mapping-rehearsal.json` tidak mengesahkan saldo/HPP, mapping bisnis, atau mengubah baseline.

## Schema yang dibuktikan

Default `0001_01_01_000000_create_users_table` tidak dijalankan karena tabel `users` lama sudah ada. Migration baru `2026_11_01_000004_create_laravel_sessions_table` hanya membuat `laravel_sessions`, tanpa FK ke `users` lama. `.env.example` menyediakan `SESSION_TABLE=laravel_sessions`; konfigurasi server sebenarnya belum diubah.

Pada clone diagnostik, empat migration terpilih dijalankan secara eksplisit, kemudian rerun dan rollback. Semua operasi keluar dengan kode 0; data seluruh tabel asli tetap cocok, tabel users tetap utuh, session read/write lulus dan repository migration tersisa 0 baris. Repository migration kosong tetap ada pada clone; tidak dihapus. Rehearsal ini independen dari keputusan baseline data dan tidak memberikan persetujuan rilis.

Schema tabel pada koneksi Konveksi ditinjau tersendiri dalam registry; hasil eksperimen pada clone Marketplace tidak boleh dianggap otomatis berlaku pada database Konveksi.

## Verifikasi source

- PHPUnit Laravel: **59 tests / 539 assertions — PASS**, PHP 8.4.27.
- Tes baru membuktikan migration sesi dapat diterapkan/di-rollback dengan tabel `users` legacy dan tabel `sessions` yang sudah ada tetap utuh.
- Unit scanner SQL: PASS untuk semicolon/kurung di dalam string, escaped quote, doubled quote, komentar dan multiline value.
- Scanner dump dan query seluruh tabel: 160/160 tabel cocok jumlah barisnya dengan dump sumber.
- Account staging SELECT-only: write ditolak; Konveksi memakai company binding dan koneksi staging terpisah.
- Tidak ada perubahan frontend sehingga build asset tidak diulang. Artifact r4 terdahulu tidak otomatis memuat migration baru ini; paket final harus dibangun ulang pada tahap rilis.

## Kebutuhan untuk menutup acceptance

Pemilik data perlu menetapkan sumber master/ledger untuk saldo dan HPP, menyetujui mapping/disposisi semua temuan, kemudian menerima hasil koreksi staging. Untuk saat ini staging lokal dipakai sesuai pilihan pengguna; bukti origin HTTPS/proxy/cookie pada hosting nyata ditunda sampai host tersedia dan bukan hasil yang telah lulus. Sampai keputusan data tersedia dan verifikasi koreksi lulus, status baseline tetap **PARTIAL / NO-GO**.
