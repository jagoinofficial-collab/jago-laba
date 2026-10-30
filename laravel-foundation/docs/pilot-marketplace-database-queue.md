# Marketplace database queue pilot

Pilot ini memindahkan satu proses kecil: scan order lokal/sandbox per company dan store untuk membuktikan checkpoint queue. Job tidak memanggil provider live, tidak menggunakan token OAuth, dan tidak mengubah baris `marketplace_orders`. Perubahan data yang diizinkan hanya status/checkpoint pada `marketplace_sync_jobs`.

## Kontrak job

- `MarketplaceSyncJob` memproses satu store dan satu page per delivery.
- `company_key`, `store_id`, `connection_id`, `run_key`, `request_id`, dan nomor page dibawa di payload job.
- Page size, jumlah page maksimum, jumlah store maksimum, queue name, dan lease ada di `config/jago.php` melalui variabel `JL_MARKETPLACE_SYNC_*`.
- Unique key lama `(connection_id, job_type, run_key)` mencegah dispatch duplikat. Row `marketplace_sync_jobs` dan payload database queue dimasukkan dalam transaksi database yang sama; jika insert queue gagal, row/status ikut rollback dan tidak boleh tertinggal sebagai `queued` tanpa job.
- `WithoutOverlapping` memakai cache lock non-Redis. Production shared hosting wajib memakai cache database atau file yang persisten; tidak ada Horizon, Redis, Supervisor, atau daemon.
- Job memakai `tries=2`, timeout 35 detik, dan backoff 5/15 detik. Timeout keras meninggalkan `started_at`; delivery berikutnya dapat mengambil alih setelah lease 45 detik.
- Error final ditulis sebagai `failed` pada `marketplace_sync_jobs`. Queue Laravel memakai tabel `jobs` dan `failed_jobs` database.

## Scheduler dan cron shared hosting

Laravel Scheduler hanya membuat antrean setiap lima menit. Cron server tetap dijalankan setiap menit:

```text
php artisan schedule:run
```

Worker shared hosting dijalankan sebagai proses pendek, bukan daemon:

```text
php artisan queue:work database --stop-when-empty --max-time=45 --timeout=35 --tries=2
```

Kedua cron tersebut boleh berjalan berulang. Scheduler memiliki `withoutOverlapping`, sementara dispatch per store memakai unique run key dan database checkpoint. Enqueue page berikutnya juga atomik dengan commit checkpoint page saat ini; bila enqueue gagal, checkpoint tidak maju dan payload page saat ini dapat di-retry. Worker dapat dimulai ulang setelah timeout tanpa mengulang page yang sudah berhasil.

Sebelum mengaktifkan cron, pastikan migration queue Laravel (`jobs`, `failed_jobs`) dan schema legacy Marketplace (`marketplace_oauth_connections`, `marketplace_sync_jobs`, `marketplace_orders`) sudah tersedia. Task ini tidak menjalankan migration produksi dan tidak mengubah schema lama.

## Mode manual/browser

Browser yang sudah login dan berada pada workspace Marketplace dapat mengirim:

```text
POST /pilot/marketplace/sync
X-CSRF-TOKEN: <token>
X-Idempotency-Key: browser-run-001
X-Request-Id: request-001
store_id=<store-id>
```

Endpoint memeriksa authenticated user, company/store scope, role `sync.trigger`, dan CSRF. `X-Idempotency-Key` dapat diulang dengan aman; request kedua menerima job yang sama tanpa dispatch baru. Jika cron tidak tersedia, endpoint ini tetap hanya menaruh row checkpoint dan payload ke database queue dalam satu transaksi; kegagalan queue menghasilkan `503` tanpa row `queued` yatim.

## Observability dan retry

Request ID disertakan pada response, tag job, dan checkpoint. Log tidak memuat access token, refresh token, atau payload provider. Pemeriksaan queue yang tersedia:

```text
php artisan queue:failed
php artisan queue:retry <failed-job-id>
```

Retry queue Laravel mengulang payload job. Untuk membuat percobaan sinkronisasi baru setelah status domain `failed`, gunakan `marketplace:sync --mode=manual --store=<store-id>` dengan `--run-key` baru.

## Bukti yang diuji

Feature test memeriksa bounded page, company/store scope, duplicate prevention, atomic dispatch awal, atomic enqueue page berikutnya, rollback saat insert queue gagal, overlap pada checkpoint yang masih ber-lease, restart setelah lease kedaluwarsa, final failed state, manual/browser CSRF dan role, serta registrasi scheduler. Tidak ada test yang memanggil provider live.
