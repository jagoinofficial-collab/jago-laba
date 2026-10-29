# Matriks integrasi Jago Laba — 27 Oktober 2026

Matriks ini menjelaskan konektor yang ada di kode, bukan jaminan semua izin sudah disetujui platform. UI memverifikasi akses per sumber dari respons API yang berhasil. OAuth saja tidak membuktikan akses produk, keuangan, atau iklan.

| Sumber | Shopee | TikTok Shop | Bukti/izin yang diperlukan |
|---|---|---|---|
| Produk aktif, varian | GET `/api/v2/product/get_item_list`, `get_item_base_info`, `get_model_list` | POST `/product/202309/products/search`, GET `/product/202309/products/{id}` | Product API yang disetujui untuk aplikasi dan seller |
| Harga dan stok (baca) | Dari item/model katalog | Dari SKU detail produk | Nilai tersedia pada respons; data kosong tetap belum tersedia |
| Pesanan/status | GET `/api/v2/order/get_order_list`, `get_order_detail` | POST `/order/202309/orders/search`, GET `/order/202309/orders` | Order API; field sensitif mengikuti akses seller |
| Keuangan | Belum diimplementasikan oleh konektor Shopee ini | `/finance/202507/orders/unsettled`, `/finance/202501/orders/{id}/statement_transactions` | Finance API tersendiri; estimasi dibedakan dari final |
| Retur | Konektor khusus belum tersedia; status batal tetap berasal dari pesanan | `/return_refund/202602/returns/search` | Return/refund API dan dukungan region |
| PDF resi/pengemasan | Konektor belum tersedia | Layanan dokumen/fulfillment yang sudah ada | Izin fulfillment; dokumen baru dianggap tersedia setelah respons berhasil |
| Penarikan dana API | Belum tersedia | Belum tersedia | Impor XLSX yang sudah ada merupakan sumber berbeda |
| Iklan | Belum tersedia | TikTok Marketing API terpisah, belum tersedia | Aplikasi, OAuth, token dan advertiser terpisah |

## Lingkungan dan otorisasi

Shopee Live memakai API `partner.shopeemobile.com`; Sandbox memakai `openplatform.sandbox.test-stable.shopee.sg`. Konfigurasi OAuth memvalidasi pasangan lingkungan/host. TikTok API memakai `open-api.tiktokglobalshop.com`; pilihan US/ROW bukan penanda Sandbox. Administrator memilih Live/Development Shop secara eksplisit. Konfigurasi lama yang belum memilihnya ditandai **Lingkungan belum diverifikasi**.

State sekali pakai terikat pengguna, perusahaan, callback, platform dan fingerprint konfigurasi. Perubahan key/lingkungan/host membutuhkan otorisasi ulang. Token terenkripsi dan refresh otomatis memakai row lock serta pemeriksaan expiry setelah lock. Akses produk tidak mengaktifkan iklan secara otomatis. Identitas marketplace tidak dapat dipindahkan lintas lingkungan dengan menimpa data koneksi lama.

## Jadwal dan batas

- Pesanan awal dimulai tanggal 1 bulan saat toko diintegrasikan; kelanjutan memakai checkpoint/jendela sinkronisasi yang sudah ada. Katalog mengambil produk aktif, bukan hanya produk yang dibuat bulan ini.
- Shopee memecah rentang pesanan menjadi bagian maksimal tujuh hari dalam adapter yang ada; pagination tetap memakai cursor native. TikTok memakai page token endpoint masing-masing.
- Konkurensi dua slot per platform dan satu proses baca per toko merupakan kebijakan konservatif Jago Laba, **bukan kuota resmi platform**. Sesuaikan dengan kuota aplikasi yang disetujui di portal.
- Retry hanya untuk gangguan sementara/rate limit: maksimum tiga retry, exponential backoff dengan jitter dan `Retry-After`. Kesalahan autentikasi/izin/parameter memerlukan tindakan, tidak diulang otomatis.
- Job sumber berbeda tetap independen. Job menunggu retry tidak menghalangi sumber lain. Tidak ada persentase atau ETA buatan.
- Sinkronisasi manual menggunakan worker server Windows. Auto-sync saat ini memakai browser pulse: memerlukan tab aktif. Webhook belum diaktifkan; deployment Linux/domain resmi memerlukan worker/scheduler server yang dikonfigurasi dan diverifikasi terlebih dahulu.

## Sumber resmi dan batas verifikasi

- [Shopee Open Platform](https://open.shopee.com/): referensi Product/Order API dan kuota aplikasi di console. Fetch otomatis developer guide menerima HTTP 403; nama scope/kuota terbaru harus diperiksa pada akun partner, tidak diasumsikan dari kode.
- [TikTok Create your app](https://partner.us.tiktokshop.com/docv2/page/create-your-app): pemisahan token/otorisasi dan host API.
- [TikTok API versioning](https://partner.tiktokshop.com/docv2/page/api-versioning): versi berada di path dan ikut ditandatangani. Versi endpoint di tabel berasal dari implementasi yang tersedia; tidak berarti semua versi/izin sudah aktif untuk setiap toko.

Tidak menggunakan scraping atau cookie Seller Center. Tidak dilakukan perubahan harga/promo/stok/pengiriman toko Live untuk pengujian ini.
