# Audit dan rancangan struktur Jago Laba

27 Oktober 2026 — workspace `D:/xampp/htdocs/jago-laba`.

**Pembaruan: struktur telah diterapkan. Lihat [hasil restrukturisasi](restructure-result.md) dan [manifest pemindahan](applied-structure.json).**

Dokumen audit dan rancangan di bawah merupakan baseline sebelum implementasi; angka serta status sebelum perubahan tetap dipertahankan sebagai riwayat.

Dokumen:

1. [ADR-001: baseline stack dan batas migrasi](adr/ADR-001-laravel-modernization-stack.md).
2. [Matriks inventaris endpoint](endpoint-inventory-matrix.md).
3. [Struktur aktual dan temuan](current-structure.md).
4. [Struktur tujuan](target-structure.md).
5. [Peta dan tahap migrasi](file-migration-map.md).
6. [Persiapan produksi .com](../deployment/production-guide.md).
7. [Referensi environment](../deployment/environment-reference.md).
8. [Backup, restore dan rollback](../recovery/backup-restore-rollback.md).

Bukti pendukung:

- [Inventaris JSON](audit-inventory.json).
- [Peta setiap file dan checksum awal CSV](file-migration-map.csv).
- [Kandidat dependensi include CSV](include-dependencies.csv).
- [Referensi jalur yang perlu disesuaikan CSV](portability-references.csv).

Keputusan rancangan: gunakan modular monolith PHP. Pisahkan Konveksi dan Marketplace melalui modul, aturan bisnis, akses data dan izin; pertahankan layanan bersama. Pertahankan database fisik dahulu sampai kepemilikan tabel dan foreign key diverifikasi. Pemisahan folder sendiri tidak menjamin server lebih cepat; ukur query, waktu respons dan beban worker sebelum/sesudah.

Audit ini tidak menjalankan installer, menghubungi database/API marketplace, membaca nilai secret ke laporan, memindahkan runtime storage atau mengganti domain. Kesiapan produksi belum terverifikasi.
