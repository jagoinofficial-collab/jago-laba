# Referensi environment

Sumber: config/*.php, MarketplaceTokenVault dan launcher. Nilai secret tidak disalin. Nama variabel lengkap hasil pemindaian tersedia di environment_names pada [inventaris](../architecture/audit-inventory.json).

Kode yang diperiksa menggunakan getenv; bootstrap tidak otomatis memuat .env melalui Dotenv. Mengisi .env saja belum menjamin konfigurasi diterapkan. Gunakan environment service/web server/CLI atau implementasikan loader secara eksplisit. Web dan worker perlu konfigurasi konsisten.

| Variabel yang sudah dibaca | Fungsi |
|---|---|
| JL_APP_BASE_URL | Base aplikasi; callback sekarang menambahkan /public/api/marketplace-oauth.php. |
| JL_DB_HOST, JL_DB_PORT, JL_DB_NAME | Lokasi database. |
| JL_DB_USER, JL_DB_PASS | Kredensial DB dengan hak minimum. |
| JL_MARKETPLACE_TOKEN_ENCRYPTION_KEY | Key sesuai format/ukuran yang diterima vault aktual. |
| JL_MARKETPLACE_TOKEN_ENCRYPTION_KEY_FILE | Alternatif file key privat di luar public. |
| JL_SHOPEE_PARTNER_ID, JL_SHOPEE_PARTNER_KEY | Pasangan aplikasi sesuai lingkungan resmi. |
| JL_SHOPEE_REGION | live/sandbox; jangan campur kredensial. |
| JL_SHOPEE_AUTH_URL, JL_SHOPEE_API_BASE_URL | Override hanya endpoint resmi lingkungan benar. |
| JL_TIKTOK_SHOP_APP_KEY, JL_TIKTOK_SHOP_APP_SECRET | Kredensial TikTok Shop, terpisah Marketing API. |
| JL_TIKTOK_SHOP_SERVICE_ID, JL_TIKTOK_SHOP_MARKET, JL_TIKTOK_SHOP_REGION | Wilayah dan otorisasi aplikasi. |
| JL_TIKTOK_SHOP_AUTH_URL, JL_TIKTOK_SHOP_API_BASE_URL | Endpoint resmi yang kompatibel. |
| JL_TIKTOK_SHOP_REQUIRED_SCOPES | Scope yang harus diberikan provider; variabel ini tidak memberikan izin sendiri. |
| JL_MARKETPLACE_WEBHOOK_URL | URL tujuan; tidak mengaktifkan handler yang belum tersedia. |
| JL_PHP_CLI | Executable PHP worker. |
| JL_MYSQLDUMP | Executable backup. |
| JL_BACKUP_DIR, JL_BACKUP_MIRROR_DIR | Folder backup dan salinan terpisah di luar public. |

Kredensial juga dapat tersimpan terenkripsi melalui Integrasi API. Verifikasi prioritas sumber di MarketplacePartnerSettings; jangan menganggap perubahan env selalu menimpa database.

## Belum boleh dianggap didukung

APP_ENV, APP_DEBUG, URL callback eksplisit, STORAGE_PATH, session path, trusted proxy, secure cookie dan runner portabel harus ditinjau/ditambahkan sesuai kebutuhan kode. Menuliskan nama baru dalam contoh tidak mengaktifkan perilakunya.

Pisahkan secret development/staging/production. Jangan gunakan default kredensial pengembangan di produksi. Key lama harus dipertahankan saat migrasi; rotasi memerlukan re-enkripsi teruji. Secret tidak boleh berada di public, URL atau log.
