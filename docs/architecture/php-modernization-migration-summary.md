# Ringkasan eksekutif migrasi Jago Laba

Dokumen ini adalah ringkasan dari [rencana migrasi Laravel](./php-modernization-migration-plan.md). Rencana lengkap tetap menjadi sumber kerja utama.

## Keputusan target

Migrasi diarahkan ke **Laravel 13** secara bertahap dengan compatibility layer. Target runtime ditetapkan **PHP 8.4** agar sesuai dengan shared hosting yang dipilih.

| Area | Pilihan |
|---|---|
| Backend | Laravel 13, service container, middleware, Form Request, Policies/Gates, Artisan |
| UI | Vue 3 + TypeScript + Inertia 3, Tailwind CSS 4, Vite |
| Auth | Laravel session untuk browser; Sanctum hanya untuk API token/SPA yang memang membutuhkannya |
| Data | MySQL yang ada; Eloquent untuk kode baru, Query Builder/raw SQL untuk legacy dan nominal sensitif |
| Queue | Laravel Queue dengan database driver dan tabel `jobs`/`failed_jobs` |
| Scheduler | Laravel Scheduler dengan satu cron shared hosting; browser pulse/manual sebagai fallback |
| Storage | Laravel Filesystem private disk untuk dokumen; disk S3-compatible opsional tahap lanjut |
| Quality | Pest/PHPUnit, Laravel Pint, PHPStan/Larastan bertahap, `php artisan test` |
| AI | Laravel Boost sebagai dev dependency, MCP, `.ai/guidelines`, `.ai/rules`, task vertical slice |
| Operasi | PHP 8.4, Composer, Node.js LTS/npm lokal/CI, MySQL/MariaDB host; Docker/Sail hanya opsional lokal |

Komponen gratis/open source menjadi baseline. Tidak ada ketergantungan wajib pada Nova, layanan hosting berbayar, auth berbayar, atau database cloud.

Shared hosting hanya menerima PHP 8.4, `vendor` hasil Composer, dan `public/build` hasil Vite. Node.js, Docker, Redis, Horizon, Supervisor, dan worker daemon tidak diperlukan di production. Dokumen privat disimpan di luar public bila memungkinkan.

Baseline environment: `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=database`, dan `FILESYSTEM_DISK=local`. Nilai `APP_KEY`, database, provider credential, dan token vault diisi melalui panel/CLI host.

Queue memakai database driver. Bila cron tersedia, jadwalkan `php artisan schedule:run` setiap menit dan `php artisan queue:work database --stop-when-empty --max-time=45 --timeout=35 --tries=2` sebagai proses singkat. Bila cron/CLI tidak tersedia, sinkronisasi otomatis diturunkan ke manual/browser pulse dengan status dan retry yang terlihat.

## Ringkasan alur proses

```text
Browser -> Laravel route/middleware -> session + workspace + company/store scope
        -> controller/Form Request/Policy
        -> Action/Service -> Eloquent/Query Builder + provider adapter
        -> DB/audit/private storage atau Job queue
        -> Vue/Inertia/JSON response

OAuth: start -> provider -> callback -> state/origin/token verification
      -> token vault -> verified store -> initial sync job

Sync: cron/browser/admin -> database queue -> bounded `queue:work --stop-when-empty`
     -> provider API -> normalize -> idempotent ingest -> finance/HPP -> report
```

URL lama tetap aktif selama transisi: `index.php`, `marketplace.php`, `workspace.php`, `api/rpc.php`, dan callback OAuth. Setiap endpoint lama memanggil controller/use case Laravel melalui adapter sampai kontrak baru terbukti setara. Vue/Inertia dibuild lokal/CI; production tidak menjalankan Node atau SSR.

## Urutan kerja

1. Bekukan baseline URL, RPC, action, JS, form, tabel, role, scope, nominal, job, dan callback.
2. Restore staging DB, file privat, dan key; nonaktifkan outbound Live serta scheduler staging.
3. Buat skeleton Laravel 13 + Vue/Inertia, kunci dependency, pasang Boost, dan tulis aturan `.ai/`.
4. Buat compatibility shell: config `JL_*`, PDO/MySQL, session bridge, route facade, response/error adapter.
5. Pindahkan auth, workspace, company/store context, CSRF, dan Policies.
6. Jalankan pilot read-only, lalu satu mutasi lokal berisiko rendah.
7. Migrasikan Konveksi dan Marketplace per vertical slice.
8. Pindahkan OAuth dan provider adapter dengan sandbox/stub.
9. Pindahkan sync ke Job/database Queue/Scheduler + cron setelah lock, batas waktu, dan idempotensi lulus.
10. Cutover satu penulis, observasi, lalu hapus adapter hanya setelah rollback dan restore rehearsal lulus.

## Cara AI agent bekerja

- Satu agent mengerjakan satu vertical slice dengan batas file/tabel yang jelas.
- Agent wajib membaca `AGENTS.md`, `.ai/rules`, ADR, kontrak, schema, dan test sebelum coding.
- Agent memakai Boost untuk konteks versi package, schema, docs, query, Tinker, dan log.
- Agent tidak menjalankan migration production, seed/repair, penghapusan, rotasi key, atau API Live tanpa checkpoint eksplisit.
- Setiap task harus mengubah atau menambah test, menjalankan Pint dan test yang relevan, serta melaporkan diff, perintah, hasil, risiko, dan rollback.
- Agent reviewer memeriksa diff, scope, query, secret, migration, dan bukti; agent pelaksana tidak menyetujui pekerjaannya sendiri pada fitur berisiko tinggi.

## Perintah eksekusi untuk AI agent

Jalankan perintah berikut **satu per satu** pada agent. Setelah setiap perintah, agent harus berhenti dan mengembalikan laporan. Jangan mengirim perintah “migrasikan seluruh proyek” dalam satu task.

### 1. Prompt pembuka / aturan kerja

```text
Anda adalah AI agent migrasi Laravel untuk proyek Jago Laba.

Baca terlebih dahulu:
- docs/architecture/php-modernization-migration-summary.md
- docs/architecture/php-modernization-migration-plan.md
- README.md
- composer.json
- semua AGENTS.md dan .ai/rules/* jika tersedia

Target teknis:
- Laravel 13
- PHP 8.4
- Vue 3 + TypeScript + Inertia 3 + Vite
- MySQL/MariaDB sesuai shared hosting
- Laravel database queue; tanpa Redis, Horizon, Supervisor, Docker, atau worker daemon di production
- Scheduler + cron shared hosting; browser/manual fallback jika cron tidak tersedia

Aturan:
- Kerjakan satu vertical slice saja.
- Jangan mengubah database production, mengirim API Live, merotasi secret, menghapus data,
  menjalankan repair/seed production, atau menghapus facade lama.
- Pertahankan URL, ID, nominal, status, callback OAuth, company/store scope, dan kontrak API.
- Sebelum coding, tulis rencana file, tabel, test, risiko, dan rollback.
- Setelah coding, jalankan test relevan, Pint, dan static check bila tersedia.
- Berhenti dan laporkan blocker jika scope, schema, secret, atau kontrak tidak jelas.

Format laporan wajib:
1. tujuan dan scope
2. file yang diubah
3. kontrak yang dipertahankan
4. perintah verifikasi dan hasilnya
5. perubahan database/queue/provider
6. risiko dan rollback
```

### 2. Prompt inventaris tanpa perubahan kode

Artefak inventaris yang dihasilkan: [endpoint-inventory-matrix.md](endpoint-inventory-matrix.md).

```text
Jalankan fase inventaris saja. Jangan mengubah file.

Petakan:
- semua public URL dan API endpoint
- semua method RpcService
- semua action marketplace-ajax
- semua form action dan fetch JavaScript
- tabel yang dibaca/ditulis setiap endpoint
- role, workspace, company scope, store scope, CSRF, audit
- job/worker/cron dan callback OAuth

Buat laporan matriks endpoint dengan kolom:
route, method, caller, input, output, status, auth, scope, tabel,
external effect, test existing, risk, proposed Laravel target.
```

### 3. Prompt membuat foundation Laravel

```text
Buat foundation Laravel 13 + PHP 8.4 pada branch/direktori target yang terisolasi.

Gunakan Vue 3 + TypeScript + Inertia 3 + Vite. Production shared hosting tidak menjalankan Node.
Pasang hanya dependency yang diperlukan, buat config adapter untuk variabel JL_*, database connection,
session bridge, error/response adapter, compatibility route, dan health endpoint.

Tambahkan Laravel Boost sebagai dev dependency dan dokumentasikan aturan .ai/.
Jangan memindahkan aturan bisnis atau mengubah schema lama pada task ini.

Verifikasi dengan php artisan about, route list, npm run build, dan satu HTTP read test.
Berhenti setelah foundation dan bukti verifikasi selesai.
```

### 4. Prompt auth dan scope

```text
Migrasikan login/session/workspace secara bertahap.

Pertahankan pembacaan user lama dan password hash melalui adapter.
Implementasikan middleware/policy untuk:
- authenticated user
- workspace Konveksi/Marketplace
- company context
- store context
- role/action permission
- CSRF browser dan Sanctum hanya untuk API yang memerlukannya

Tambahkan feature tests untuk user sah, session expired, role salah, company asing,
store asing, CSRF salah, dan perpindahan workspace.
Jangan menghapus endpoint auth lama.
```

### 5. Prompt pilot satu fitur

```text
Migrasikan hanya fitur [NAMA FITUR] sebagai pilot read-only.

Baseline: [URL/file lama], [tabel], [test].
Target: Laravel controller + Form Request bila diperlukan + Action/Service + Vue/Inertia page.
Pertahankan query/filter/redirect/props yang dipakai frontend lama.

Bandingkan runtime lama dan baru pada fixture yang sama:
status, response, data, query penting, scope, waktu respons, dan tampilan.
Jalankan regression test lama dan test Laravel baru.
Jangan mengubah provider atau melakukan write.
```

### 6. Prompt satu mutasi lokal

```text
Migrasikan satu mutasi lokal berisiko rendah: [NAMA MUTASI].

Wajib ada Form Request, Policy, CSRF, company/store scope, transaction,
idempotency/unique constraint, audit, error response, dan rollback.
Pertahankan adapter endpoint lama agar caller lama tetap berfungsi.
Uji success, validation error, unauthorized, duplicate request, database failure,
dan refresh halaman setelah redirect/Inertia response.
```

### 7. Prompt OAuth/provider

```text
Migrasikan boundary OAuth/provider untuk [PLATFORM] menggunakan stub/sandbox.

Pertahankan callback URL lama sampai URL Laravel terdaftar dan diuji.
Uji state expired/replay, origin berbeda, session hilang, token exchange gagal,
refresh token, multi-store, store milik company lain, timeout, rate limit, dan retry.
Jangan memanggil provider Live. Jangan menaruh token di log atau hasil laporan.
```

### 8. Prompt queue dan cron shared hosting

```text
Migrasikan satu proses sinkronisasi ke Laravel Job dengan database queue.

Job harus pendek, idempotent, memiliki checkpoint company/store, batas halaman/toko,
retry terbatas, failed_jobs, request ID, dan lock tanpa Redis.
Tambahkan Scheduler command dan dokumentasikan dua cron:
- php artisan schedule:run
- php artisan queue:work database --stop-when-empty --max-time=45 --timeout=35 --tries=2

Uji cron overlap, restart setelah timeout, duplicate prevention, failed job retry,
dan mode manual/browser bila cron tidak tersedia.
Jangan memakai Horizon, Redis, Supervisor, atau daemon.
```

### 9. Prompt build dan paket upload

```text
Siapkan release artifact untuk shared hosting.

Jalankan pada mesin build/CI:
- composer install --no-dev --optimize-autoloader
- npm ci
- npm run build

Buat daftar file yang akan di-upload dan pastikan tidak berisi .env, secret,
dump, backup, .git, source map sensitif, atau storage runtime yang tidak diperlukan.
Pastikan document root menunjuk public, storage/bootstrap/cache writable,
dan `php artisan optimize` hanya dijalankan di host jika CLI dan environment sudah benar.
Jangan melakukan cutover atau migration production.
```

### 10. Prompt review sebelum merge/cutover

```text
Review diff migrasi [BRANCH/PR] sebagai agent verifier.

Periksa:
- kontrak URL/HTTP/RPC dan props Inertia
- auth, CSRF, role, company/store scope
- query, transaction, idempotency, audit, nominal/HPP
- migration precondition/post-check dan rollback
- database queue, cron overlap, checkpoint, retry, failed jobs
- secret/log/PII dan private storage
- parity dengan test lama dan test Laravel baru

Berikan status PASS, PASS WITH RISKS, atau BLOCKED.
Jika BLOCKED, jangan memperbaiki sendiri; tulis blocker, bukti, dan keputusan yang diperlukan.
```

### Perintah lokal yang aman untuk agent

Perintah ini untuk development/staging atau mesin build. Path PHP, Composer, database, dan Node harus disesuaikan dengan lingkungan aktual:

```text
php artisan about
php artisan route:list
php artisan migrate:status
php artisan test
vendor/bin/pint --dirty
npm run build
php artisan queue:failed
php artisan queue:retry all        # hanya setelah review job failed
```

Agent tidak boleh menjalankan `migrate --force`, `queue:flush`, `db:wipe`, `model:prune`, perintah repair, atau perintah provider Live tanpa task dan checkpoint eksplisit.

## Kriteria berhenti dan go/no-go

**Lanjut** jika kontrak HTTP/RPC sama, role dan scope lulus, nominal/HPP dapat direkonsiliasi, job tidak ganda, secret terlindungi, dan restore berhasil.

**Berhenti** jika backup belum dapat direstore, ada akses lintas company/store, callback OAuth tidak sama, nominal tidak terjelaskan, dua scheduler aktif, installer sensitif terbuka, atau rollback belum jelas.

## Langkah pertama

1. Setujui ADR stack Laravel 13, PHP 8.4, Vue/Inertia, dan database queue.
2. Buat matriks kontrak endpoint dan aksi.
3. Siapkan staging restore.
4. Buat skeleton Laravel + Boost pada branch eksperimen.
5. Migrasikan satu endpoint baca sebagai pilot dan ukur parity.
