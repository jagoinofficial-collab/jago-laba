# Staging release gate — 2026-11-01

## Decision

**BLOCKED untuk staging restore dan cutover produksi.** Build/source lokal sudah
terverifikasi dan artifact r4 tersedia. Tidak ada bukti yang mengizinkan
pernyataan restore/live-operation/cutover selesai. Status blocker ini adalah
gate operasional eksternal, bukan kegagalan build lokal.

## Bukti lokal terkini

- Source suite: **58 tests / 532 assertions — PASS**.
- `npm ci`, `npm run build`, dan production Composer install: PASS.
- `php artisan about`: Laravel 13.39.0, PHP 8.4.27, production mode, debug OFF.
- `php artisan route:list --json`: 31 route.
- HTTP GET `/` dari artifact r4: HTTP 200.
- Scan ZIP r4: 6.075 file, 0 file terlarang; `trustHosts` dan
  `JL_DOCUMENT_ROOT_PUBLIC` ada pada bytes artifact.
- Bukti callback terbaru: path document-root dan sandbox sudah dibuktikan lokal;
  tidak ada registrasi callback/provider Live.
- Detail checksum/build: [r4 build evidence](./laravel-foundation-shared-hosting-r4-evidence-20261101.md).
- Catatan rilis 30 Oktober tetap tersimpan sebagai historical snapshot di
  [gate lama](./staging-release-gate-20261030.md).

## Matriks gate operasional

| Gate | Status | Yang sudah ada | Bukti yang masih dibutuhkan |
|---|---|---|---|
| Artifact | PASS lokal | ZIP r4, hash, allowlist, exclusion scan, smoke HTTP | Validasi ulang pada mesin/OS target jika berbeda |
| Restore database, private files, key | BLOCKED | Rehearsal database-only tercatat; private files dan key tidak disediakan | Backup authoritative + checksum; restore DB/files/key ke staging terisolasi; post-check dan rollback record |
| Scheduler dan outbound write benar-benar mati | BLOCKED | Test sandbox tidak memanggil provider Live | Bukti konfigurasi/host staging mematikan scheduler dan outbound write, termasuk worker/browser trigger |
| Migration dependency/registry | BLOCKED | Registry staging dan migration precondition dicatat; tidak ada migration production dijalankan | Target schema hasil restore, status migration, dependency approval, pre/post-check, dan rollback rehearsal |
| Login, workspace, tenant/store, HPP/laba, XLSX/download | PARTIAL | Feature/regression tests pada fixture terisolasi lulus | Smoke dengan identitas dan data hasil restore; file authorization di host staging |
| HTTPS, cookie, proxy, host allowlist, absolute URL | PARTIAL | `trustHosts`, proxy/host config, contract test, dan document-root option masuk r4 | Uji pada hostname HTTPS staging aktual di belakang proxy/web server target |
| OAuth callback | PARTIAL | Sandbox callback path root-public benar; callback legacy dapat dikonfigurasi untuk document root | Registrasi URL pada console masing-masing provider dan callback test sandbox di hostname target |
| OAuth Live/seller consent | BLOCKED | Platform boundary sandbox tersedia; token tidak ada dalam paket | Approval seller, app/provider approval, dan task Live terpisah dengan credential disimpan di secret manager host |
| Cron/worker retry/restart/heartbeat | PARTIAL | DB queue, bounded job, retry/checkpoint, scheduler diuji pada fixture/local DB | Cron entry, worker run/stop, overlap/restart/failure/retry, dan heartbeat dibuktikan pada host staging |
| Production migration/cutover | BLOCKED | Tidak dilakukan | Go/no-go tertulis, backup final, rollback plan, maintenance window, dan smoke setelah cutover |

## Callback contract terkini

Pernyataan 30 Oktober bahwa kode selalu membentuk
`JL_APP_BASE_URL + /public/api/marketplace-oauth.php` sudah tidak berlaku sebagai
deskripsi source terbaru. `JL_DOCUMENT_ROOT_PUBLIC=true` membentuk callback
tanpa segmen `/public`; `false` mempertahankan layout XAMPP lama. Laravel
callback memakai root-public route. Uji pembentukan URL dan sandbox lokal lulus,
tetapi callback provider belum didaftarkan dan HTTPS staging aktual belum ada.

## Urutan membuka gate

1. Bangun ulang/ambil r4 yang hash-nya cocok; jangan gunakan r3 untuk rilis baru.
2. Owner staging menyiapkan host, backup authoritative, file privat, dan key
   melalui kanal penyimpanan rahasia—jangan kirim key/token ke chat.
3. Pulihkan seluruh bahan ke isolasi dan buktikan scheduler/outbound write mati.
4. Cocokkan schema dengan registry, jalankan hanya migration yang disetujui,
   simpan precondition/post-check/rollback evidence.
5. Jalankan checklist login/scope/keuangan/file/HTTPS/callback/queue pada target.
6. Minta approval provider Live dan cutover secara eksplisit hanya sesudah semua
   gate di atas PASS; sampai itu, keputusan tetap **NO-GO**.
