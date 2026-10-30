# Foundation Laravel Jago Laba

Direktori ini adalah aplikasi Laravel 13 terpisah. Document root production harus menunjuk ke `laravel-foundation/public` hanya setelah cutover tersendiri. Saat ini seluruh traffic bisnis tetap milik aplikasi PHP lama.

## Runtime dan dependency

- PHP 8.4 dengan PDO MySQL/MariaDB, Composer 2, dan ekstensi standar Laravel.
- Vue 3 + TypeScript + Inertia 3 dibangun oleh Vite. Jalankan `npm ci && npm run build` pada mesin build/CI dan upload `public/build`. Production tidak menjalankan Node atau SSR.
- Laravel Boost dan Pint hanya dev dependency. Aturan agen ada di `.ai/rules/scope.md` dan `.ai/guidelines/jago-laba.md`.
- Tidak ada Redis, Horizon, Supervisor, atau worker daemon. Konfigurasi target memakai `SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=database`. Scheduler `marketplace:sync` dan worker database short-lived didokumentasikan pada [pilot-marketplace-database-queue.md](./pilot-marketplace-database-queue.md).

## Konfigurasi

Salin `.env.example` menjadi `.env` untuk pengembangan dan buat `APP_KEY`. File `.env` tidak boleh di-commit atau di-upload dari mesin build. `DB_*` menjadi sumber utama; `JL_DB_HOST`, `JL_DB_PORT`, `JL_DB_NAME`, `JL_DB_USER`, dan `JL_DB_PASS` dipakai sebagai fallback bila `DB_*` tidak diisi. Koneksi default adalah MySQL, sementara driver `mariadb` bisa dipilih melalui `DB_CONNECTION`.

`JL_COMPANY_KEY`, `JL_KONVEKSI_DATABASE_ISOLATED`, `JL_APP_BASE_URL`, endpoint/region/scope Shopee dan TikTok Shop, webhook, serta interval rekonsiliasi dibaca oleh `config/jago.php`. Nilai credential provider dipetakan untuk kebutuhan adapter berikutnya, tetapi foundation tidak memanggil provider dan tidak menaruh nilai token vault di config. Key config internal `jago.secrets.marketplace_token_encryption_key_configured` hanya menunjukkan apakah `JL_MARKETPLACE_TOKEN_ENCRYPTION_KEY` terisi; key dapat berasal dari environment atau file privat `JL_MARKETPLACE_TOKEN_ENCRYPTION_KEY_FILE`. `JL_LEGACY_SESSION_COOKIE` defaultnya `JAGO_LABA_LOCAL_SESSION`; `JL_LEGACY_SESSION_PATH` mengarah ke penyimpanan session PHP lama bila berbeda dari `session.save_path` CLI/web.

Bridge session membaca atribut identitas dasar dari cookie native lama dan, bila user legacy masih dapat dibaca, menghidrasi guard Laravel untuk request tersebut. Bridge tidak mengubah role dari database legacy dan tidak memindahkan aturan bisnis.

Login `prosesLoginBackend` pada `/api/rpc.php`, pemilihan workspace pada `/api/workspace.php`, logout pada `/api/logout.php`, dan boundary OAuth Marketplace pada `/api/marketplace-oauth.php` sudah memiliki adapter kompatibilitas. RPC selain login tetap mengembalikan 503 sampai adapter use case per fitur tersedia. OAuth memakai sandbox lokal untuk semua platform registry; provider Live tidak dipanggil.

Browser routes memakai session Laravel dan CSRF bawaan grup `web`. `/api/rpc.php` dikecualikan dari validator CSRF global agar login lama tetap dapat dipanggil, tetapi method RPC selain login wajib mengirim token CSRF yang valid. Sanctum belum dipasang karena foundation ini belum mempunyai API bearer-token; ia harus ditambahkan hanya bersama endpoint API yang memang memerlukannya.

`/foundation` adalah halaman Inertia baca tanpa database. `/up` adalah liveness Laravel. `/api/health.php` memeriksa `SELECT 1` pada database terkonfigurasi dan mengembalikan 503 bila koneksi belum siap.

Pilot Marketplace dan Konveksi tersedia pada `/pilot/marketplace` dan `/pilot/konveksi`. Batas baseline, parity contract, mutasi expense, dan bukti verifikasi dicatat pada `docs/pilot-marketplace-konveksi.md`; boundary OAuth sandbox dicatat pada `docs/pilot-marketplace-oauth-sandbox.md`.

Pilot queue Marketplace menyediakan checkpoint scan order lokal/sandbox pada `/pilot/marketplace/sync` untuk browser/manual dan command `marketplace:sync` untuk Scheduler. Pilot ini tidak memanggil provider Live dan tidak mengubah baris order; detail cron, retry, failed jobs, lease, dan rollback ada pada [pilot-marketplace-database-queue.md](./pilot-marketplace-database-queue.md).

## Batas schema dan operasi

Migration skeleton Laravel belum dijalankan. Jangan jalankan `migrate`, seed, atau repair pada database legacy. Sebelum menggunakan database session/cache/queue, review nama tabel dan jalankan migration yang diperlukan hanya pada staging terisolasi. Queue pilot mengasumsikan tabel `jobs`, `failed_jobs`, serta tabel Marketplace legacy sudah tersedia; task ini tidak mengubah schema production.
