# ADR-001: Baseline stack dan batas migrasi Laravel

- Status: Accepted for foundation and staging; production cutover pending
- Date: 2026-10-30
- Decision owner: project owner and data owner
- Scope: Laravel foundation and the first vertical migration slices

## Context

Jago Laba memiliki aplikasi PHP legacy yang melayani Konveksi dan Marketplace
dengan URL, session, schema, nominal, status, callback OAuth, dan proses
sinkronisasi yang sudah dipakai pengguna. Target deployment adalah shared
hosting yang tidak menjalankan Node.js, Redis, Horizon, Supervisor, atau worker
daemon. Migrasi harus bertahap dan tidak boleh mengganti pemilik traffic legacy
sebelum contract test per URL lulus.

Foundation Laravel sudah dibuat terisolasi di direktori `laravel-foundation/`.
ADR ini menetapkan baseline teknis agar pekerjaan berikutnya tidak mengambil
keputusan stack yang berbeda-beda.

## Decisions

### 1. Runtime dan framework

- Framework target adalah Laravel 13.
- Runtime target adalah PHP 8.4 pada web dan CLI.
- Database target adalah MySQL atau MariaDB yang sudah dipakai aplikasi.
- Dependency dikunci melalui `composer.lock` dan `package-lock.json`.
- Laravel Boost hanya merupakan dev dependency untuk konteks agent dan
  development; bukan dependency runtime pengguna.

### 2. UI dan asset production

- UI baru menggunakan Vue 3, TypeScript, Inertia 3, dan Vite.
- Asset dibuild pada mesin lokal atau CI dengan `npm ci` dan `npm run build`.
- Production shared hosting hanya menerima hasil build pada `public/build`;
  production tidak menjalankan Node.js dan tidak menggunakan Inertia SSR.
- URL, query, filter, redirect, dan props yang dipakai UI legacy adalah
  kontrak. Perubahan hanya boleh dilakukan setelah contract test dan parity
  test tersedia.

### 3. Struktur aplikasi dan transisi traffic

- Bentuk target adalah modular monolith satu repository dan satu database
  fisik terlebih dahulu.
- Konveksi dan Marketplace tetap dipisahkan secara modular pada service,
  controller, authorization, dan test.
- URL legacy tetap aktif selama transisi: `index.php`, `marketplace.php`,
  `workspace.php`, `api/rpc.php`, dan callback OAuth.
- Route/controller adapter boleh memanggil use case baru, tetapi aplikasi
  legacy tetap menjadi pemilik traffic sampai vertical slice lulus contract
  test dan review.
- Tidak ada cutover, redirect global, penghapusan facade, atau penggantian
  domain pada ADR ini.

### 4. Authentication, session, dan authorization

- Browser menggunakan Laravel session dengan jembatan yang dapat membaca
  identitas dan password hash legacy tanpa memaksa migrasi user sekaligus.
- Sanctum hanya digunakan untuk API token atau SPA cookie pada endpoint yang
  memang memerlukannya; session browser tidak diubah menjadi bearer token.
- Setiap request yang relevan wajib memvalidasi authenticated user, workspace,
  company, store, role/action, dan CSRF di server.
- Policy dan scope tidak boleh mempercayai company/store yang hanya dikirim
  oleh browser.
- Endpoint auth lama tetap dipertahankan sampai pengganti dan contract test
  terbukti setara.

### 5. Database dan schema

- Schema legacy tetap menjadi sumber data dan tidak diubah pada task foundation.
- Laravel migration hanya boleh menambah schema pendukung yang sudah disetujui
  untuk slice tertentu; bukan menjalankan migrasi, seed, repair, reset, atau
  penghapusan pada database production.
- Setiap perubahan schema selanjutnya harus memiliki precondition, post-check,
  owner, rollback realistis, dan rehearsal di database staging terisolasi.
- Query nominal, status, ID, tanggal berlaku HPP, dan foreign-key scope tetap
  direkonsiliasi terhadap runtime legacy sebelum ORM atau query baru dianggap
  setara.

### 6. Queue, scheduler, dan shared hosting

- Queue production menggunakan Laravel database queue dengan `jobs` dan
  `failed_jobs`.
- Scheduler dijalankan melalui cron singkat:

  ```text
  php artisan schedule:run
  ```

- Worker dijalankan secara bounded dan berhenti setelah antrean kosong:

  ```text
  php artisan queue:work database --stop-when-empty --max-time=45 --timeout=35 --tries=2
  ```

- Job sync wajib pendek, idempotent, memiliki request ID, checkpoint
  company/store, batas halaman/toko, retry terbatas, failed state, dan lock.
- Redis, Horizon, Supervisor, Docker, dan daemon worker bukan prasyarat
  production.
- Jika cron atau CLI tidak tersedia, fitur otomatis diturunkan menjadi mode
  manual/browser pulse dengan status dan retry yang terlihat; request web tidak
  boleh menunggu sinkronisasi penuh.

### 7. OAuth dan provider

- Provider diakses melalui adapter yang memiliki timeout, retry, rate limit,
  request ID, dan redaction secret.
- Pengembangan dan test menggunakan stub atau sandbox saja.
- Callback URL legacy tetap dipertahankan sampai URL Laravel terdaftar dan
  diuji pada environment yang disetujui.
- Token disimpan melalui vault/encryption yang sesuai dan tidak boleh masuk
  log, test output, artifact, atau laporan.
- Tidak ada otorisasi ulang massal dan tidak ada provider Live pada scope ADR
  ini.

### 8. Storage dan release

- File privat menggunakan Laravel Filesystem disk `local` dan tidak diekspos
  melalui symlink publik tanpa pemeriksaan authorization.
- Release dibangun di mesin build/CI dengan Composer production install dan
  Vite build.
- Artifact tidak boleh berisi `.env`, secret, key mentah, dump, backup, `.git`,
  source map sensitif, atau storage runtime yang tidak diperlukan.
- Document root production diarahkan ke `public`; `storage` dan
  `bootstrap/cache` harus writable pada host.
- `php artisan optimize` hanya dijalankan pada host setelah environment
  production benar dan CLI tersedia.

### 9. Testing dan quality gate

- Setiap vertical slice memiliki test legacy dan test Laravel pada fixture yang
  sama bila provider dan database mengizinkan.
- Minimum evidence per slice mencakup HTTP/route contract, auth/CSRF/scope,
  query penting, nominal/HPP, failure path, dan rollback bila ada mutasi.
- Build dan quality gate baseline adalah `php artisan about`,
  `php artisan route:list`, `php artisan test`, `vendor/bin/pint --test`, dan
  `npm run build` sesuai scope.
- Review sebelum merge/cutover dilakukan sebagai tahap terpisah dari agent
  pelaksana dan menghasilkan `PASS`, `PASS WITH RISKS`, atau `BLOCKED`.

## Consequences

### Benefits

- Legacy URL dan database tetap menjadi anchor sehingga migrasi dapat dilakukan
  per slice tanpa cutover besar.
- Constraint shared hosting tidak disembunyikan di balik asumsi Redis, Node,
  atau worker daemon.
- Scope, nominal, OAuth, queue, dan release memiliki aturan yang sama untuk
  pekerjaan berikutnya.

### Costs and risks

- Untuk sementara terdapat adapter dan kemungkinan implementasi legacy serta
  Laravel berjalan berdampingan.
- Parity test membutuhkan fixture yang stabil dan akses staging yang aman.
- Database queue membutuhkan desain idempotency, checkpoint, dan recovery yang
  lebih ketat daripada worker daemon.
- Keputusan produksi masih membutuhkan konfirmasi document root, cron/CLI,
  kapasitas host, RPO/RTO, dan owner untuk rollback.

## Explicitly not decided by this ADR

- Cutover traffic atau penghapusan endpoint legacy.
- Penggantian provider marketplace atau aktivasi OAuth Live.
- Migrasi massal user, schema, file privat, atau data historis.
- Pemisahan database Konveksi dan Marketplace.
- Nilai RPO/RTO final, jadwal cron final, serta kapasitas shared hosting.
- Perbaikan blocker queue, parity, dan idempotency yang akan ditemukan pada
  review vertical slice.

## Go/no-go conditions

Migration work must stop before merge or cutover when any of these remains
unresolved:

- contract URL/HTTP/RPC atau props Inertia berbeda tanpa keputusan;
- user dapat mengakses company, store, workspace, atau action yang salah;
- nominal atau HPP tidak dapat direkonsiliasi;
- job dapat berduplikasi atau hilang setelah timeout/crash;
- token, secret, atau PII masuk log/artifact;
- schema precondition, post-check, atau rollback tidak jelas;
- backup/restore staging belum berhasil;
- lebih dari satu scheduler/worker dapat memproses scope yang sama.

## References

- [Migration plan](../php-modernization-migration-plan.md)
- [Migration prompt summary](../php-modernization-migration-summary.md)
- [Foundation agent rules](../../../laravel-foundation/AGENTS.md)
- [Foundation scope rules](../../../laravel-foundation/.ai/rules/scope.md)
- [Foundation documentation](../../../laravel-foundation/docs/foundation.md)
