# Rancangan struktur tujuan

Usulan, belum diterapkan. Pertahankan PHP dan arsitektur modular monolith; tidak perlu mengganti framework hanya untuk merapikan folder.

```text
jago-laba/
  app/
    Core/                         # database, router, composition
    Shared/
      Auth/                       # session, izin, company context, CSRF
      Audit/
      Documents/                  # storage privat dan unduhan berizin
      Backup/
      Http/
      Views/
    Modules/
      Konveksi/
        Module.php
        Http/Controllers/
        Application/              # produksi, pembelian, penjualan
        Domain/                   # HPP produksi, bahan, SPK, hutang
        Infrastructure/Repositories/
        Views/
      Marketplace/
        Module.php
        Http/Controllers/
        Application/
          Stores/
          Catalog/
          Orders/
          Finance/
          Marketing/
          Inventory/
          Sync/
        Domain/
        Infrastructure/Repositories/
        Integrations/
          Contracts/
          Shopee/
          TikTokShop/
          TikTokAds/              # hanya setelah konektor tersedia
          Support/
        Views/
    Bridges/KonveksiMarketplace/   # transfer eksplisit saja
  bootstrap/
  config/
  routes/                         # shared, konveksi, marketplace
  public/                         # document root tunggal
    index.php
    marketplace.php               # facade legacy sementara
    workspace.php
    api/                          # facade endpoint lama sementara
    assets/{shared,konveksi,marketplace}/
  database/
    migrations/{shared,konveksi,marketplace}/
    seeds/development/
    legacy/
  scripts/{operations,konveksi,marketplace}/
  storage/
    private/{documents,imports,exports}/
    cache/
    logs/{shared,konveksi,marketplace}/
    sessions/
    locks/
  tests/{unit,integration,acceptance}/
  docs/{architecture,deployment,recovery}/
  .env.example
```

Folder dibuat saat dibutuhkan. CSV menawarkan tujuan tahap awal yang konservatif, misalnya Services. Pemisahan lanjut ke Application/Domain dilakukan setelah kontrak diekstrak; bukan semua kelas Service langsung diganti nama.

## Batas modul

| Modul | Tanggung jawab | Batas |
|---|---|---|
| Konveksi | bahan, produksi, SPK, pembelian, stok pabrik, HPP produksi, hutang | Tidak mengakses tabel marketplace langsung. |
| Marketplace | toko terotorisasi, katalog, pesanan, keuangan, HPP SKU, iklan, stok toko | Wajib company/store scope; nominal dan status berdasarkan sumber sah. |
| Shared | identitas, izin, audit, konfigurasi, storage dan backup | Tidak memuat aturan bisnis khusus salah satu domain. |
| Bridge | alokasi/transfer eksplisit dengan referensi unik dan audit | Tidak menyamakan stok pabrik dan toko secara otomatis. |

Awali dengan satu database. Pemetaan tabel/foreign key harus diverifikasi sebelum repositori diubah. Jangan mengganti ID toko/pesanan, company_key, SKU, migration ID, tabel atau riwayat hanya karena folder berubah.

## Penamaan dan kompatibilitas

- PHP memakai PascalCase mengikuti kelas; namespace tujuan JagoLaba\Shared, JagoLaba\Modules\Konveksi, JagoLaba\Modules\Marketplace.
- Config/route memakai nama kecil; script/aset baru kebab-case. Periksa kapitalisasi seluruh path di Linux.
- Autoload baru disiapkan bertahap setelah dependency tooling dipilih; require/alias sementara mempertahankan kontrak lama.
- Endpoint lama tetap adapter tipis sampai frontend, task dan callback provider berpindah. Jangan redirect callback OAuth aktif secara sembarang.
- Pisahkan pengambilan API, parser, persistensi, perhitungan laba dan tampilan. Data belum tersedia tetap null/menunggu; jangan menjadi nol atau laba final.
- Token TikTok Shop dan TikTok Ads terpisah. Folder integrasi bukan bukti izin API tersedia.
- API tulis tetap memakai izin, konfirmasi, CSRF, audit dan idempotensi. Refaktor tidak mengizinkan pengiriman ulang fulfillment/harga/promo.

Target request: bootstrap bersama → session/izin → workspace/toko → modul yang diperlukan → controller → use case → repository/provider → view/JSON. Bootstrap tidak menjalankan migration/seed/repair otomatis per request.

Pisahkan filesystem path dan URL. Gunakan konfigurasi URL serta trusted proxy; callback harus sama persis dengan registrasi provider. Domain resmi belum diberikan; tidak ada domain baru yang diterapkan.
