# Pemeriksaan API resi — 26 Oktober 2026

Uji baca langsung pada toko JAGOIN TikTok Shop yang terverifikasi, token belum kedaluwarsa. Scope tersimpan mencakup seller.fulfillment.basic. Dua package ID dari payload order yang tersimpan, status AWAITING_COLLECTION, diuji menggunakan GET /fulfillment/202309/packages/{package_id}/shipping_documents dengan document_type SHIPPING_LABEL, document_size A6, document_format PDF, shop_cipher dan tanda tangan konektor yang sudah ada.

Hasil dua percobaan: respons code=0 melalui TikTokDataClient; doc_url HTTPS tersedia dan tracking_number dikembalikan. URL dokumen, token, identitas penerima, dan nomor resi tidak ditulis ke laporan atau output diagnostik. Berkas PDF tidak diunduh; isi PDF/barcode belum diuji. Tidak ada call Ship Package, Create Package, update status, atau cetak fisik.

Dokumentasi resmi yang diverifikasi melalui halaman JavaScript Partner Center: https://partner.tiktokshop.com/docv2/page/get-package-shipping-document-202309 . Scope Fulfillment Basic (seller.fulfillment.basic) atau FS Order&Fulfillment Management; khusus TikTok Shipping, setelah pengiriman diatur dan sebelum pickup. Dokumen dapat ditolak untuk paket batal, paket sudah pickup, atau Seller Shipping. Hasil berhasil ini tidak membuktikan semua status/jenis paket dapat dicetak.

Tidak ada toko Shopee terverifikasi pada hasil pemeriksaan koneksi, sehingga akses label Shopee belum diuji. Status UI Cetak Resi pada modul Pesanan masih berupa penjelasan keterbatasan; pemeriksaan ini belum menambahkan konektor unduhan/preview label ke UI.

Diagnostik scripts/check_marketplace_shipping_readonly.php hanya dapat berjalan melalui CLI; maksimal dua permintaan baca per toko TikTok terverifikasi. Tidak dijadwalkan otomatis.
