# Pilot OAuth Marketplace: sandbox semua platform

Boundary OAuth Laravel tersedia untuk semua platform registry: Shopee, TikTok Shop, Tokopedia, Lazada, dan Blibli. Semua provider di task ini adalah stub lokal; tidak ada HTTP call ke provider Live, tidak ada credential Live yang dibaca, dan token sandbox tidak dikembalikan ke response atau log.

## Callback dan route

- Callback lama dipertahankan: `GET|POST /api/marketplace-oauth.php`.
- Callback baru sudah terdaftar untuk pengujian: `GET /pilot/marketplace/oauth/callback`.
- Sandbox authorization endpoint: `GET /__sandbox/marketplace/oauth/authorize`.
- Status: `GET /api/marketplace-oauth.php?action=status`.
- Start: `POST /api/marketplace-oauth.php?action=start` dengan `platform`, optional `return_path`, dan scenario test-only.
- Multi-store: `GET ...?action=pending&pending_id=...`, lalu `POST ...?action=select`.
- Refresh: `POST /api/marketplace-oauth.php?action=refresh` dengan `store_id`.

Start tetap menghasilkan callback URL lama agar registrasi provider existing tidak berubah. Response status juga mencantumkan callback Laravel baru untuk tahap registrasi dan cutover berikutnya.

## Security boundary

State disimpan sebagai hash dan terikat ke user legacy, company, platform, return path, expiry, serta konsumsi satu kali. Callback memeriksa state expired/replay, platform mismatch, session hilang, workspace Marketplace, company context, dan origin start.

Pending multi-store hanya mengembalikan identitas toko sandbox. Access/refresh token disimpan terenkripsi pada tabel legacy OAuth; token plaintext tidak pernah masuk response, audit, log, atau laporan.

Koneksi toko memakai company/store ownership check sebelum insert/update. Refresh token berjalan dalam transaction dengan row lock; failure menandai koneksi `reauthorize` tanpa menghapus token lama secara sembarangan.

## Failure scenarios sandbox

Parameter `scenario` pada start hanya untuk fixture/test sandbox:

- `success`
- `cancel`
- `exchange_fail`
- `refresh_fail`
- `multi_store`
- `foreign_store`
- `timeout`, `timeout_once`
- `rate_limit`, `rate_limit_once`

Timeout dan rate limit yang retryable dicoba ulang sesuai `JL_MARKETPLACE_OAUTH_RETRY_ATTEMPTS`; response akhir memakai error aman `PROVIDER_TIMEOUT` atau `PROVIDER_RATE_LIMITED` tanpa detail token.

## Bukti

[OAuthSandboxTest.php](../tests/Feature/OAuthSandboxTest.php) menguji semua platform, callback lama, state expired/replay, origin berbeda, session hilang, exchange failure, refresh success/failure, multi-store selection, store milik company lain, timeout, rate limit, retry, serta ketiadaan token pada response/database plaintext.

Tidak ada migration baru pada task ini. Adapter mengharapkan tabel OAuth legacy sudah tersedia pada environment staging/target; bila tabel belum ada, request berhenti dengan `OAUTH_STORAGE_UNAVAILABLE`.
