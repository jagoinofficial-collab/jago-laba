# Aturan migrasi Jago Laba

- Foundation ini terisolasi. Jangan mengalihkan traffic atau menghapus facade PHP lama sebelum contract test per URL lulus.
- Setiap mutasi harus mempertahankan session, CSRF, role, workspace, company dan store scope di server.
- Jangan menjalankan migrasi, seed, repair, reset, atau penghapusan pada database production.
- Jangan mengirim API provider Live atau menaruh token, kredensial, dan PII pada log atau artefak.
- ID, nominal, status, tanggal berlaku HPP, dan callback OAuth adalah kontrak yang harus direkonsiliasi.
- Target production: PHP 8.4, MySQL/MariaDB, database session/cache/queue, cron scheduler dan worker singkat. Tidak ada Redis, Horizon, Supervisor, Node runtime, atau Inertia SSR.
- Perubahan berikutnya harus satu vertical slice, dengan test legacy dan Laravel pada fixture yang sama.
