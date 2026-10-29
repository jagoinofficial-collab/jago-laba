# Dashboard Toko — 26 Oktober 2026

Dashboard menggunakan MarketplaceDashboardService, bukan agregasi lama getStoreDashboard. Hanya source_kind=api pada toko aktif, internal_state aktif, tanggal pesanan sesuai periode WIB dan akses perusahaan yang berkontribusi. Default bulan berjalan, tersedia preset dan rentang khusus hingga 366 hari. Ingestion resmi saat ini menolak mata uang non-IDR.

Keuangan final (`is_estimated=false` dan net_income numerik), estimasi, serta keuangan belum tersedia dipisahkan. Tidak menggunakan nilai kolom default 0 sebagai pengganti snapshot kosong. Penjualan memakai finance_revenue jika tersedia; jika tidak, gross_amount snapshot ditampilkan sebagai Penjualan tercatat. Potongan bersih = dasar penjualan − net_income, sehingga diskon/kredit/refund/subsidi yang sudah tercakup tidak dipotong ulang. Snapshot yang totalnya berselisih tidak mengesahkan laba setelah HPP.

HPP memakai total_cogs snapshot transaksi yang dipelihara pengaturan HPP produk. Data retur dengan jumlah pengembalian belum diketahui menghalangi pengesahan HPP. Batal/belum dibayar dipisahkan; pembatalan yang masih memiliki dampak keuangan diberi perhatian untuk rekonsiliasi, tidak dianggap nol otomatis.

Biaya hanya dari marketplace_expenses dengan affects_profit=1 dan is_cashflow_only=0, tanggal beban pada periode yang sama. Tidak menambahkan tabel ad_spends lama atau settlement/penarikan sebagai penjualan baru. Tidak ada jaminan cakupan biaya lengkap dari konektor Ads saat ini: biaya tanpa catatan tetap NULL. Subtotal setelah biaya tercatat ditampilkan secara eksplisit sebagai PARSIAL (pesanan dengan net dan HPP lengkap dikurangi biaya tercatat periode). Laba Bersih Riil belum disahkan. Pengeluaran harian belum dialokasikan ke kelompok final/estimasi; tidak dipaksakan menjadi margin final.

UI memuat kartu final/estimasi, margin setelah HPP, alur keuntungan, biaya, grafik harian, perhatian, rincian 50 pesanan per halaman, ekspor CSV rincian, dan modal rumus. Pencairan diarahkan ke Pemasukan. Nilai CSV kosong tetap kosong, tidak berubah menjadi nol. Pemeriksaan hak akses tetap dilakukan di endpoint HTML/CSV.

Pembaruan browser membaca database setiap 60 detik saat halaman terlihat dan setelah perubahan pekerjaan sinkronisasi. Tidak memanggil API marketplace dari halaman dashboard. Filter URL dipertahankan; penyegaran ditunda jika pengguna sedang mengedit filter atau membaca dialog. Worker server lama tidak diubah.

Verifikasi: marketplace_dashboard_smoke (database pengujian terpisah), marketplace_finance_smoke, marketplace_transaction_detail_smoke, render PHP strict dashboard/transaksi. Uji mencakup dasar finance revenue vs diskon, penghitungan biaya sekali, final/estimasi/unknown, batal/belum bayar, retur tidak terkonfirmasi dan retur nol terkonfirmasi, selisih rekonsiliasi, filter tanggal, sumber API, arus kas saja dan isolasi tenant. Tampilan desktop dan 390px diuji dengan data sintetis; alur menjadi vertikal dan tanpa overflow horizontal; dialog rumus berhasil dibuka/ditutup.

Tidak ada perubahan harga/stok marketplace atau penghapusan riwayat dari perubahan dashboard ini.
