# Shopee Live - Jago Laba

Jalur aktif: Live. Sandbox ID/key sebelumnya dipertahankan di previous_profiles dalam settings_encrypted; tidak dipakai sebagai kredensial Live. Backup terenkripsi sebelum perubahan ada di D:/JAGO_LABA_BACKUP/shopee-settings-before-live-*.encrypted. Token/toko/transaksi yang ada tidak dihapus.

Langkah administrator:
1. Pastikan aplikasi Shopee sudah Go-Live/disetujui dan memiliki akses API yang diperlukan.
2. Pengaturan -> Integrasi API -> Shopee: isi Live Partner ID dan Live Partner Key dari console. Jangan memakai Test Partner ID atau mengirim key lewat chat.
3. Lingkungan Live -> Simpan & uji API. Uji endpoint publik partner tidak membuktikan otorisasi toko, dan endpoint publik dapat dibatasi untuk kategori aplikasi tertentu.
4. Pada console Shopee, daftarkan Live Redirect URL Domain yang sama dengan domain publik Jago Laba. Salin URL callback lengkap dari kartu Integrasi API untuk callback aplikasi.
5. Pengaturan -> Tambah Toko -> Hubungkan Shopee. Login dengan akun toko utama, setujui otorisasi, lalu kembali ke Jago Laba.
6. Toko baru hanya dinyatakan terhubung setelah callback/state tervalidasi, token terenkripsi dan identitas toko diverifikasi API. Sinkronisasi mengikuti worker yang tersedia. Izin logistik/iklan/produk tergantung persetujuan aplikasi dan konektor yang tersedia.

Perbaikan:
- Otorisasi modern /auth: partner_id, auth_type=seller, response_type=code, redirect_uri, state.
- Live API https://partner.shopeemobile.com; auth https://open.shopee.com/auth.
- Sandbox V2 API https://openplatform.sandbox.test-stable.shopee.sg; auth https://open.sandbox.test-stable.shopee.com/auth.
- Get/Refresh token: partner_id, timestamp, sign pada query; code/refresh_token, partner_id, shop_id numerik pada JSON body.
- Guard host/lingkungan mencegah pengiriman token ke host di luar lingkungan resmi. Mengubah lingkungan memerlukan key baru; frontend mengosongkan ID/key.
- shopee_integration_smoke memakai dummy credentials/transport dan database sementara. Kredensial Live belum tersedia, sehingga belum ada uji toko nyata.

Referensi resmi: https://open.shopee.com/developer-guide/20 dan Sandbox Testing V2 melalui navigasi Getting Started.
