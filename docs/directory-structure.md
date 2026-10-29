# Struktur Direktori Target

```text
app/
  Core/                         # infrastruktur inti, termasuk PDO Database
  Http/                         # request/response HTTP JSON
  Shared/                       # runtime lintas modul
  Modules/
    Konveksi/                   # loader dan manifest view Konveksi
    Marketplace/                # loader dan kontrak integrasi Marketplace
  Bridges/KonveksiMarketplace/  # DTO transfer eksplisit lintas modul
  Services/                     # lokasi implementasi historis yang masih dijaga adapter
  views/                        # view legacy aktif, dimuat oleh manifest modul
config/                         # konfigurasi terpisah per concern
database/                       # migration, seed terkontrol, snapshot migrasi
public/                         # entrypoint web, asset, endpoint HTTP
scripts/                        # backup dan worker CLI
storage/                        # backup, cache, log, upload server
tests/                          # smoke dan integration test aktif
docs/                           # arsitektur, mapping, verifikasi
```

## Status migrasi folder

- `Core`, `Http`, `Shared`, `Modules`, dan `config` sudah menjadi boundary runtime nyata.
- Service historis di `app/Services` belum dipindahkan massal karena banyak endpoint dan migrasi lama merujuk path tersebut. Loader modul memusatkan pemuatan dan menjaga URL lama tetap kompatibel.
- View Konveksi tetap berada di `app/views` untuk sementara; daftar view sekarang dikelola oleh `KonveksiModule::viewNames()`.
- Pemindahan fisik service/view berikutnya harus dilakukan satu modul per commit dengan adapter tipis, lint, smoke test, dan verifikasi browser.
