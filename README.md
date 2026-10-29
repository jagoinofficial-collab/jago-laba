# Jago Laba — Marketplace Marketing System & Konveksi

Kode dipisahkan ke `app/Modules/Konveksi`, `app/Modules/Marketplace`, serta `app/Shared` dan `app/Core` untuk fungsi bersama.

- [Hasil restrukturisasi dan kompatibilitas](docs/architecture/restructure-result.md).
- [Manifest file yang dipindahkan](docs/architecture/applied-structure.json).
- [Persiapan deployment](docs/deployment/production-guide.md).
- [Backup dan rollback](docs/recovery/backup-restore-rollback.md).

Sesudah checkout/deployment, jalankan `composer install` untuk membuat autoload. Variabel lingkungan harus disediakan oleh server/CLI; keberadaan .env.example tidak berarti .env otomatis dimuat. Konfigurasi tidak berisi token untuk disalin ke frontend.

Pemeriksaan struktur tanpa koneksi database:

```sh
php bin/console structure:check
php tests/Unit/project_structure.php
```

Entrypoint browser dan API lama di public tetap tersedia sebagai facade kompatibilitas. Jangan menjalankan seed/migration/installer secara massal saat deploy. Data runtime tetap pada lokasi sebelumnya sampai migrasi storage terpisah disiapkan.
