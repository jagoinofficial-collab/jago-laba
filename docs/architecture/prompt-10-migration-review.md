# Prompt 10 — Migration review

Tanggal review: 2026-10-30
Target branch: `codex/prompt-10-migration-review`
Scope: Laravel foundation, auth/scope bridge, pilot Marketplace dan Konveksi, expense mutation lokal, OAuth sandbox boundary, database queue, dan release documentation.

## Status

**PASS WITH RISKS**

Tidak ada blocker kode pada scope pilot setelah review ulang. Status bukan `PASS` penuh karena production database restore, permission live, worker/cron live, provider Live, dan cutover URL belum dijalankan; semua itu berada di luar task foundation dan memerlukan approval operasional terpisah.

## Evidence matrix

| Area | Evidence | Result |
|---|---|---|
| URL, HTTP, legacy adapter, dan props Inertia | `laravel-foundation/tests/Feature/PilotReadOnlyTest.php`, `routes/web.php` | Marketplace dan Konveksi memakai response/props pilot yang diuji; entry URL lama tetap kompatibel melalui redirect/reservation. |
| Legacy-vs-Laravel parity | `test_marketplace_legacy_and_laravel_runtime_match_on_the_same_fixture`, `test_konveksi_legacy_and_laravel_runtime_match_on_the_same_fixture` | Fixture SQLite yang sama membandingkan status, payload, filter/query penting, company/store scope, dan waktu baca. |
| Auth, workspace, company, store, role, CSRF | `laravel-foundation/tests/Feature/AuthScopeTest.php`, `PilotReadOnlyTest.php` | User sah, session expired, role salah, company/store asing, CSRF salah, dan perpindahan workspace diuji. |
| Expense mutation | `MarketplaceExpenseWriteService.php`, `PilotReadOnlyTest.php` | Form Request, policy, scope, transaction, audit, idempotency scoped, conflict, rollback, dan refresh redirect diuji. |
| OAuth/provider | `laravel-foundation/tests/Feature/OAuthSandboxTest.php` | Semua platform memakai sandbox/stub; state replay, origin/session, timeout, retry, refresh, multi-store, dan foreign company diuji tanpa provider Live. |
| Database queue | `MarketplaceQueueTest.php`, `MarketplaceQueueAtomicityTest.php` | Bounded page, checkpoint, lease, retry, failed job, duplicate prevention, scheduler, initial dispatch atomicity, dan next-page enqueue atomicity lulus. |
| Migration safety | `laravel-foundation/tests/Unit/IdempotencyScopeMigrationTest.php` | Precondition, backfill/post-check, rollback sukses, dan rollback refusal saat scoped collision diuji. |
| Secret/log/private storage | `laravel-foundation/docs/pilot-marketplace-oauth-sandbox.md`, `shared-hosting-release.md` | Token tidak dipanggil/log; artifact release tidak memasukkan `.env`, secret, dump, backup, `.git`, atau runtime storage. |

## Database precondition, post-check, dan rollback

Precondition sebelum host menjalankan migration/queue:

```text
php artisan about
php artisan migrate:status
```

Pastikan tabel `idempotency_keys`, `jobs`, `failed_jobs`, `marketplace_oauth_connections`, `marketplace_stores`, `marketplace_sync_jobs`, dan `marketplace_orders` tersedia, serta database queue memakai koneksi yang sama dengan transaksi aplikasi.

Post-check migration idempotency:

```sql
-- legacy row harus memiliki tenant/module hasil backfill
SELECT company_key, module, COUNT(*)
FROM idempotency_keys
GROUP BY company_key, module;

-- unique scope wajib ada; implementasi memeriksa metadata index
-- (company_key, module, idempotency_key)
```

Rollback hanya boleh dilakukan setelah memastikan tidak ada key sama lintas scope. Implementasi migration menolak rollback bila global uniqueness akan collision; test membuktikan kondisi tersebut. Rollback aman juga diuji pada fixture tanpa collision.

## Queue failure closure

Dispatch awal memasukkan row `marketplace_sync_jobs` dan payload `jobs` dalam transaction yang sama. Enqueue page berikutnya juga dilakukan dalam transaction yang sama dengan commit checkpoint. Jika insert queue gagal, status/checkpoint rollback dan payload saat ini tetap dapat di-retry; endpoint mengembalikan `503` tanpa row `queued` yatim.

## Verification result

- Full Laravel suite setelah penambahan migration-safety assertions: `46 tests, 415 assertions, OK`.
- Queue dan atomicity suite: `11 tests, 55 assertions, OK`.
- Migration safety suite setelah penambahan evidence: `4 tests, 11 assertions, OK`.
- Pint dan diagnostik file yang diubah: lulus/tidak ada error.
- Regression legacy sebelumnya: project structure, Marketplace dashboard smoke, dan Konveksi integrity lulus.

Production migration, restore drill, permission live, provider Live, dan cutover tetap bukan bagian dari review lokal ini.
