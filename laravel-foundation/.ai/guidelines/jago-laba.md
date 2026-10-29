# Jago Laba foundation

Rujuk `.ai/rules/scope.md` dan dokumen migrasi di repositori induk.
Route `*.php` dalam foundation hanya reservasi dan mengembalikan 503 hingga adapter per fitur diuji.
Bridge session hanya membaca identitas legacy; ia tidak memberikan otorisasi Laravel.
Gunakan koneksi MySQL/MariaDB dan varian `JL_DB_*` sebagai fallback konfigurasi.
