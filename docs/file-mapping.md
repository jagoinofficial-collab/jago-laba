# Mapping File Refaktor

| Area lama | Boundary baru | Status |
| --- | --- | --- |
| `app/Database.php` | `app/Core/Database.php` | Implementasi dipindah; file lama menjadi adapter kompatibilitas |
| `app/config.php` | `config/app.php`, `config/database.php`, `config/security.php`, `config/backup.php`, `config/marketplace.php` | Komposisi baru, bentuk konfigurasi lama dipertahankan |
| daftar require di `app/bootstrap.php` | `app/Shared/Runtime.php` + `app/Modules/Konveksi/Module.php` | Loader dipisah berdasarkan tanggung jawab |
| service Marketplace yang di-require langsung oleh halaman | `app/Modules/Marketplace/Module.php` | Module loader aktif, class implementation lama tetap kompatibel |
| daftar view hard-coded di `public/index.php` | `KonveksiModule::viewNames()` | Manifest view aktif |
| parsing JSON di `public/api/rpc.php` | `app/Http/RpcRequest.php` | Request boundary aktif |
| serialisasi JSON di `public/api/rpc.php` | `app/Http/JsonResponse.php` | Response boundary aktif |
| `public/assets/js/jl-api.js` | client HTTP MySQL-backed RPC | Aktif; tidak ada `google.script.run` |
| `app/Services/MarketplaceScopeService.php` | `MarketplaceModule` dan policy scope aktif | Scope toko/perusahaan tetap dijaga oleh service existing |
| transfer stok lintas modul | `app/Bridges/KonveksiMarketplace/ExplicitStockTransfer.php` | DTO boundary; tidak mengaktifkan pemotongan stok otomatis |
| webhook/cron marketplace yang belum terverifikasi | endpoint/worker status belum aktif | Tidak membuat fake success |

## Adapter yang sengaja dipertahankan

Adapter hanya mempertahankan path publik dan include path internal. Adapter tidak menyimpan data, tidak memanggil Google API, dan tidak mengubah aturan bisnis. Penghapusan adapter dilakukan setelah semua referensi repository sudah dipindahkan dan smoke test lulus.
