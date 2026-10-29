# Shared-hosting release artifact

Tanggal verifikasi: 2026-10-30

Dokumen ini mencatat paket runtime Laravel foundation untuk shared hosting. Paket
ini belum melakukan cutover, deployment production, atau migration production.

## Build yang diverifikasi

Build dilakukan pada staging bersih:

```text
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

Hasil:

- Composer production install berhasil dengan PHP 8.4.27 dan menghasilkan
  `vendor/autoload.php`.
- `npm ci` berhasil; 54 package ditambahkan dan audit melaporkan 0 vulnerability.
- `npm run build` berhasil; `public/build/manifest.json` dan asset Vite produksi
  tersedia.
- `php artisan about` pada paket upload berhasil dengan Laravel 13.39.0, PHP
  8.4.27, dan environment production.
- `php artisan optimize` sengaja tidak dijalankan pada mesin build. Jalankan di
  host hanya setelah `.env`, PHP CLI, koneksi database, dan permission sudah
  diverifikasi.

## Isi paket upload

Paket runtime hanya memuat:

- `app/`, `bootstrap/`, `config/`, `routes/`, dan `vendor/` production.
- `public/` termasuk `.htaccess`, `index.php`, `build/manifest.json`, dan asset
  produksi.
- `resources/views/` untuk app shell Blade.
- `artisan`, `composer.json`, dan `composer.lock`.
- Placeholder `.gitignore` pada `storage/` dan `bootstrap/cache/`; isi runtime
  storage tidak dibawa dari mesin build.

Paket tidak memuat `.env`, contoh environment, secret, dump/backup database,
`.git`, `node_modules`, source frontend, test, dokumentasi internal, source map,
log, atau storage runtime yang tidak diperlukan.

## Checklist shared hosting

1. Arahkan document root domain/subdomain ke direktori `public/`, bukan ke root
   project.
2. Upload isi paket dan pertahankan struktur direktori.
3. Pastikan `storage/` dan `bootstrap/cache/` writable oleh user PHP/web server;
   gunakan permission yang sesuai platform, bukan permission global yang longgar.
4. Buat `.env` production di host dan isi `JL_*`, database, session, cache, dan
   queue sesuai environment host. Jangan mengunggah `.env` dari mesin build.
5. Sebelum mengaktifkan write expense, setelah backup dan pre-check koneksi,
   jalankan migration scope idempotency:
   `php artisan migrate --force --path=database/migrations/2026_10_30_000003_scope_idempotency_keys.php`.
   Migration menolak rollback bila key lintas scope akan kembali collision.
6. Setelah CLI PHP, environment, koneksi database, dan permission benar, jalankan
   `php artisan optimize` di host bila diperlukan.
7. Migration dan cutover production belum dijalankan dalam task ini. Artifact
   upload yang dibuat sebelum migration scope ini harus dibuild ulang sebelum
   dipakai.

Artifact dan daftar file yang diperiksa dicatat di
`release-artifacts/laravel-foundation-shared-hosting-20261030-upload-manifest.md`.
