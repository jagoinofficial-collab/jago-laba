# Evidence MKT-WRITE-002 — Marketplace expense mutation

Tanggal uji: 2026-11-01 08:58–09:01 WIB
Target: restore MariaDB terisolasi pada `127.0.0.1:3307`, database `jago_laba`
Runtime: PHP 8.4.27, Laravel foundation, `APP_ENV=staging`
Provider: tidak dipanggil; tidak ada provider write atau token Live.

## Scope

Mutasi yang diuji hanya pencatatan expense lokal ke `marketplace_expenses`.
Tidak ada perubahan order, HPP, harga, stok, payout, OAuth, atau tabel provider.
Caller legacy `POST /api/marketplace-ajax.php?action=add_expense` dan caller
Laravel/Inertia `POST /pilot/marketplace/expenses` tetap dipertahankan.

## Precondition dan migration

- Restore memiliki `marketplace_expenses`, `marketplace_stores`,
  `idempotency_keys`, dan `system_audit_logs`.
- Sebelum migration, `idempotency_keys` masih memiliki unique global pada
  `idempotency_key` dan belum memiliki `company_key`.
- Hanya migration
  `2026_10_30_000003_scope_idempotency_keys.php` dijalankan pada staging.
- Post-migration memiliki unique composite
  `(company_key, module, idempotency_key)` bernama `uq_idempotency_scope`.

## Runtime results

1. Write dengan company `LOCAL-COMPANY`, store
   `OAUTH-TTS-9303C45A46DA636FCC255A54B25FBFE1`, dan key
   `step8-expense-20261101-01`.
   - PASS; expense `35` tercatat dengan nominal `12,345.67`.
   - PASS; satu audit `CREATE_EXPENSE` dan satu receipt scoped dibuat.
2. Replay request yang sama.
   - PASS; response `duplicate=true`, `expense_id=35`, tidak membuat row kedua.
3. Payload berbeda memakai key yang sama.
   - PASS; `IdempotencyConflict`, tidak membuat effect tambahan.
4. Store milik company lain.
   - PASS; `DomainException`, tidak ada write lintas company/store.
5. Rollback fault injection.
   - `marketplace_expenses` di-rename sementara pada staging terisolasi.
   - Write gagal dengan `QueryException` karena tabel tidak tersedia.
   - PASS; setelah tabel dikembalikan, receipt rollback tetap `0` dan audit
     rollback tetap `0`; expense fixture yang sudah ada tetap utuh.
6. Cleanup fixture.
   - PASS; row expense/key/audit uji dihapus dengan predicate ID/key yang tepat.
   - Post-cleanup: `marketplace_expenses` uji `0`, `idempotency_keys` uji `0`,
     audit baseline kembali `9`.

## Regression evidence

- Focused Laravel tests: **6 tests, 78 assertions — PASS**.
- Test coverage mencakup success, validation, role, CSRF, foreign store,
  duplicate, scoped company/module idempotency, database rollback, audit, dan
  refresh redirect Inertia.
- Full Laravel suite sebelumnya tetap PASS: **56 tests, 524 assertions**.
- `problems` pada request, controller, service, policy middleware, dan route:
  tidak ada error.

## Decision

**PASS — isolated staging, dengan batasan operasional.**

Mutation pilot memenuhi transaction, audit, scoped idempotency, company/store
scope, CSRF/role boundary, legacy response, Inertia redirect, dan rollback.
Migration production, provider Live, dan cutover URL tetap release gate terpisah.
