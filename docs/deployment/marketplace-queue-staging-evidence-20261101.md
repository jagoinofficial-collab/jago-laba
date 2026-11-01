# Evidence QUEUE-STG-001 — database queue staging

Tanggal uji: 2026-11-01 08:53–08:55 WIB
Target: restore MariaDB terisolasi pada `127.0.0.1:3307`, database `jago_laba`
Runtime: PHP 8.4.27, Laravel foundation, `APP_ENV=staging`
Provider: tidak dipanggil; token Live tidak digunakan.

## Scope dan precondition

- Restore staging berasal dari database Marketplace yang sudah dipakai pada
  evidence read-only sebelumnya.
- Tabel domain `marketplace_sync_jobs` dan `marketplace_sync_checkpoints` sudah
  ada pada restore.
- Tabel Laravel `jobs`, `job_batches`, dan `failed_jobs` belum ada pada restore.
  Hanya migration `0001_01_01_000002_create_jobs_table` dijalankan pada target
  staging terisolasi. Migration production dan migration schema lain tidak
  dijalankan.
- Store fixture yang dipakai: `OAUTH-TTS-9303C45A46DA636FCC255A54B25FBFE1`,
  company `LOCAL-COMPANY`, 1.935 order sebelum dan sesudah uji.

## Commands dan hasil

1. `php artisan migrate --path=database/migrations/0001_01_01_000002_create_jobs_table.php --no-interaction`
   - PASS; membuat `jobs`, `job_batches`, `failed_jobs` pada staging.
2. Dispatch dengan run key `step7-stage-20261101-01`, lalu dispatch ulang dengan
   run key yang sama.
   - PASS; dispatch pertama membuat domain job `1404` dan satu payload queue.
   - PASS; dispatch kedua menjadi `duplicate=1`, tidak membuat payload kedua.
3. `php artisan queue:work database --queue=marketplace --stop-when-empty --max-time=45 --timeout=35 --tries=2`
   - PASS; 20 page bounded selesai, domain job menjadi `succeeded`,
     `success_count=1935`, `failure_count=0`.
4. Fault injection staging: source table diganti nama sementara, worker tetap
   hidup melewati backoff, lalu source dikembalikan.
   - PASS; percobaan pertama dan kedua gagal, domain job menjadi `failed`,
     `failure_count=2`, dan satu row masuk `failed_jobs`.
   - PASS; `php artisan queue:retry all --queue=marketplace`, dilanjutkan worker,
     mengembalikan job menjadi `succeeded`, `success_count=1935`.
   - PASS; setelah retry, `queue:failed` kosong.
5. `php artisan schedule:list`
   - PASS; tepat satu entry `php artisan marketplace:sync --mode=cron`,
     interval lima menit, `withoutOverlapping(1)`.
6. `php artisan migrate:status` dan `php artisan queue:failed`
   - PASS; PHP CLI melihat database/queue staging yang sama; tidak ada failed job
     tersisa setelah recovery.

## Post-check

- `jobs=0`, `failed_jobs=0` setelah worker selesai.
- Tidak ada domain row `queued` yatim pada fixture uji.
- `started_at`, `finished_at`, `success_count`, `failure_count`, dan
  `last_error_code` dapat diaudit di `marketplace_sync_jobs`.
- Jumlah source order tetap 1.935; pilot hanya membaca `marketplace_orders` dan
  menulis state queue/checkpoint.
- `marketplace_sync_checkpoints` tetap merupakan tabel incremental legacy yang
  sudah ada pada restore. Pilot queue sandbox memakai checkpoint JSON pada
  `marketplace_sync_jobs`; sinkronisasi provider/incremental legacy tetap berada
  di luar scope task ini dan tidak diaktifkan.
- Tidak ada proses `mysqld`/listener staging yang dibiarkan aktif setelah uji.

## Keputusan

**PASS — isolated staging, dengan batasan operasional.**

QUEUE-STG-001 terbukti pada restore MySQL terisolasi untuk dispatch atomic,
duplicate prevention, bounded page, restart/recovery, failed job, retry,
scheduler registration, dan mode manual CLI. Production cron/permission,
restore production, provider Live, dan cutover URL tetap merupakan release gate
terpisah.
