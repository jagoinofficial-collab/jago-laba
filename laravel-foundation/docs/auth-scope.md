# Auth dan scope foundation

Foundation membaca tabel legacy `auth_users_v2` terlebih dahulu lalu fallback ke `system_users`. Username dibandingkan tanpa membedakan kapitalisasi dan hanya user berstatus `aktif` atau `active` yang dapat login. Password selalu diverifikasi dengan `password_verify()` terhadap `password_hash`; adapter tidak menulis ulang hash dan tidak mengubah schema lama.

Guard `web` memakai `App\Auth\LegacyUserProvider`. Cookie native lama dibaca oleh `LegacySessionBridge`, lalu user yang berhasil ditemukan dihidrasi ke guard Laravel untuk request tersebut. Login baru menyimpan identifier provider di session Laravel dan membersihkan `workspace`, `store_id`, dan `workspace_filters`.

Middleware scope yang tersedia:

- `auth`: authenticated user Laravel, termasuk user yang di-bridge dari session legacy.
- `workspace.context:konveksi` atau `workspace.context:marketplace`: workspace harus diizinkan dan sudah aktif.
- `company.context`: request company harus sama dengan company context user atau `JL_COMPANY_KEY`.
- `store.context:store_id`: store harus ada, belum diarsipkan, dan memiliki `company_key` yang sama.
- `role.action:report.view` atau action lain: policy role/action legacy.

Endpoint kompatibilitas auth:

- `POST /api/rpc.php` dengan `method=prosesLoginBackend` mempertahankan bentuk respons RPC lama.
- `POST /api/workspace.php` mempertahankan respons `success` dan `url` lama.
- `POST /api/logout.php` mempertahankan respons `status=success` lama.
- `POST /auth/login`, `GET /auth/me`, `POST /auth/workspace`, dan `POST /auth/logout` menyediakan kontrak Laravel JSON baru.

CSRF browser tetap berasal dari middleware `web`. Endpoint RPC login dikecualikan dari validator global karena kontrak lama tidak mengirim token saat login; RPC non-login tetap menolak request tanpa token. Sanctum tidak dipasang pada slice ini karena belum ada API bearer-token yang membutuhkan autentikasi stateless.

Test memakai SQLite memory dan membuat tabel tiruan hanya di database test. Tidak ada migration, seed, atau perubahan pada database legacy produksi.
