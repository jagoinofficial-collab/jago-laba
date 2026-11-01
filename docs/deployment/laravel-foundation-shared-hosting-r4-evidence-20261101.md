# Build evidence — Laravel foundation shared hosting r4

Tanggal: 2026-11-01
Status: **PASS untuk build/package lokal; deployment dan production cutover tidak dilakukan.**

## Artifact

- ZIP: `release-artifacts/laravel-foundation-shared-hosting-20261101-r4.zip`
- SHA-256: `2DF35C1900D2B5FD16E0FE2FCEFD11CCC03157B2C45DBF79C3C9D44F3F2C9A8E`
- Ukuran ZIP: 7,632,610 byte
- Isi: 6,075 file / 26,333,084 byte sebelum kompresi
- Scan ZIP: 0 `.env`/secret, `.git`, `node_modules`, source map, log,
  dump/backup database, atau storage runtime.
- `bootstrap/app.php` memuat host allowlist melalui `trustHosts`.
- `config/jago.php` memuat `JL_DOCUMENT_ROOT_PUBLIC`.
- `bootstrap/cache/` dan `storage/` hanya berisi placeholder; cache, session,
  log, dan file runtime hasil uji tidak ikut ZIP.

## Build dan smoke evidence

- `npm ci`: PASS, 54 package ditambahkan, audit 0 vulnerability.
- `npm run build`: PASS; manifest dan asset Vite Marketplace, analytics,
  orders, Konveksi, dan app shell tersedia.
- `composer install --no-dev --optimize-autoloader`: PASS; 75 dependency
  runtime dipasang dari lockfile.
- `php artisan about`: PASS; Laravel 13.39.0, PHP 8.4.27, environment
  production, debug OFF.
- `php artisan route:list --json`: PASS, 31 route.
- HTTP GET `/` dari paket: **200**, `text/html`, memuat judul Jago Laba.
  Smoke memakai APP_KEY acak sementara dan session/cache file; tidak ada key
  yang disimpan ke paket atau log.
- `trustHosts` dan opsi document-root dibaca langsung dari ZIP final; scan
  exclusion dilakukan terhadap entry ZIP, bukan direktori source.

## Batas

Artifact r4 menggantikan r3 untuk build berikutnya; r3 dan manifest 30 Oktober
dipertahankan sebagai jejak historis. Artifact ini belum diuji pada domain HTTPS
staging aktual dan belum membuktikan restore DB/file/key, permission host,
registrasi callback provider, worker/cron live, provider Live, migration
production, atau cutover URL. Jangan gunakan artifact sebagai bukti GO produksi.
