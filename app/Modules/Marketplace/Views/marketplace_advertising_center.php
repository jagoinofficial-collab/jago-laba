<?php
declare(strict_types=1);

/**
 * JAGO LABA — Unified Marketplace Advertising Center
 * Shopee Ads + TikTok Ads + GMV Max
 * Single Source of Truth: Jago Laba Enterprise Business OS
 * Versi: 2026-10-29
 */

$mktSub = in_array($_GET['mkt_sub'] ?? '', ['ringkasan', 'shopee', 'tiktok', 'gmv_max', 'produk', 'laporan'], true) 
    ? $_GET['mkt_sub'] 
    : 'ringkasan';

$isDemo = ($_GET['mkt_demo'] ?? '') === '1';

// URL Helper untuk sub-tab
$mktTabUrl = static function (string $tabKey) use ($activeStoreId): string {
    $params = $_GET;
    $params['store'] = $activeStoreId;
    $params['tab'] = 'marketing';
    $params['mkt_sub'] = $tabKey;
    unset($params['page']);
    return 'marketplace.php?' . http_build_query($params);
};

// Data Binding & KPI Extraction
$kpi = $marketingData['kpi'] ?? [];
$products = $marketingData['products'] ?? [];

// Hitung data harian/mingguan untuk Chart
$chartLabels = [];
$chartSpend = [];
$chartRevenue = [];
if (!empty($products)) {
    // Ambil maksimal 7 produk/campaign teratas untuk bar chart
    $topItems = array_slice($products, 0, 7);
    foreach ($topItems as $item) {
        $name = (string)($item['campaign_name'] ?: $item['product_name']);
        $chartLabels[] = mb_substr($name, 0, 14) . (mb_strlen($name) > 14 ? '…' : '');
        $chartSpend[] = (float)$item['ad_spend'];
        $chartRevenue[] = (float)$item['gross_revenue'];
    }
} else {
    $chartLabels = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
    $chartSpend = [0, 0, 0, 0, 0, 0, 0];
    $chartRevenue = [0, 0, 0, 0, 0, 0, 0];
}

// Simulasi Data untuk Demo Mode TikTok Ads & GMV Max (jika diaktifkan)
if ($isDemo && in_array($mktSub, ['tiktok', 'gmv_max'], true)) {
    $demoSpend = 2450000;
    $demoRevenue = 8820000;
    $demoOrders = 112;
    $demoRoas = 3.60;
    $demoCpo = 21875;
    $demoClicks = 4280;
    $demoImpressions = 125000;
} else {
    $demoSpend = (float)($kpi['biaya_iklan'] ?? 0);
    $demoRevenue = (float)($kpi['pendapatan'] ?? 0);
    $demoOrders = (int)($kpi['total_orders'] ?? 0);
    $demoRoas = (float)($kpi['roas'] ?? 0);
    $demoCpo = (float)($kpi['cost_per_order'] ?? 0);
    $demoClicks = (int)($kpi['total_clicks'] ?? 0);
    $demoImpressions = (int)($kpi['total_impressions'] ?? 0);
}

$activePlatform = match($mktSub) {
    'shopee' => 'Shopee Ads',
    'tiktok' => 'TikTok Ads',
    'gmv_max' => 'TikTok GMV Max',
    default => 'Omnichannel'
};

$heroTitle = match($mktSub) {
    'shopee' => 'Performa Iklan Shopee',
    'tiktok' => 'Performa Iklan TikTok',
    'gmv_max' => 'Performa TikTok GMV Max',
    'produk' => 'Analitik Produk Beriklan',
    'laporan' => 'Laporan & Rekonsiliasi Iklan',
    default => 'Marketplace Advertising Center'
};

$heroDesc = match($mktSub) {
    'shopee' => 'Monitor biaya iklan, konversi, dan efektivitas kampanye Shopee terintegrasi.',
    'tiktok' => 'Pantau biaya iklan, performa kampanye, dan pendapatan teratribusi TikTok.',
    'gmv_max' => 'Pantau belanja iklan, performa GMV Max, target ROI, dan pesanan teratribusi TikTok.',
    'produk' => 'Evaluasi ROAS, HPP konveksi, margin laba bersih, dan deteksi dini produk boncos.',
    'laporan' => 'Unduh laporan berkala dan rekonsiliasi belanja iklan terhadap pencairan dana rekening.',
    default => 'Satu pusat analitik iklan untuk memantau efektivitas kampanye setiap toko dan platform.'
};
?>

<div class="jl-ads-center">

    <!-- 1. NOTIFIKASI & STATUS KONEKSI INTEGRASI -->
    <?php if ($mktSub === 'tiktok' || $mktSub === 'gmv_max'): ?>
    <div class="jl-ads-alert jl-ads-alert--warning flex items-center justify-between">
        <div class="flex items-center gap-3">
            <span class="w-6 h-6 rounded-full bg-amber-200 text-amber-900 flex items-center justify-center font-bold text-xs shrink-0">!</span>
            <div>
                <strong>Status TikTok Ads: Belum Terhubung (Fase 1 — UI/UX Terpadu)</strong>
                <p class="text-[11px] text-amber-800 mt-0.5">Hubungkan TikTok Ads API untuk menampilkan data iklan aktual. Tidak ada request API palsu atau token simulasi yang dikirimkan ke server.</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" onclick="toggleAdsDemoMode()" class="px-3 py-1.5 rounded-xl border border-amber-300 bg-white hover:bg-amber-50 text-amber-900 font-bold text-xs transition">
                <?= $isDemo ? 'Matikan Mode Simulasi' : 'Pratinjau Mode Simulasi' ?>
            </button>
        </div>
    </div>
    <?php else: ?>
    <div id="mktNotificationBanner" class="jl-ads-alert jl-ads-alert--success flex items-center justify-between">
        <div class="flex items-center gap-3">
            <span class="w-6 h-6 rounded-full bg-emerald-200 text-emerald-900 flex items-center justify-center font-bold text-xs shrink-0">&#10003;</span>
            <div>
                <strong>Status Data <?= htmlspecialchars($activeStore['store_name']) ?> (<?= htmlspecialchars($activeStore['platform']) ?>):</strong>
                <span><?= !empty($activeStore['last_successful_sync_at']) ? 'Sinkronisasi berhasil terakhir pada ' . htmlspecialchars((string)$activeStore['last_successful_sync_at']) . ' WIB' : 'Belum ada data sinkronisasi API' ?>. Biaya iklan dan HPP produk terhubung secara sah.</span>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a href="marketplace.php?tab=pengaturan&amp;peng_sub=integrasi" class="px-3 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs">Periksa Integrasi</a>
            <button type="button" onclick="document.getElementById('mktNotificationBanner').remove()" class="text-emerald-700 hover:text-emerald-900 font-bold px-2 py-1 text-xs">Tutup</button>
        </div>
    </div>
    <?php endif; ?>

    <!-- 2. HEADER KENDALI UTAMA (Pilihan Platform, Toko, PPN, dan Export) -->
    <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-600 to-indigo-700 text-white flex items-center justify-center font-bold shadow-md shadow-blue-600/20">
                    <i class="fa-solid fa-bullhorn text-sm"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-2xl font-black text-slate-900 tracking-tight">Manajemen Iklan Marketplace</h1>
                        <?php if ($isDemo): ?>
                            <span class="jl-demo-mode-badge"><i class="fa-solid fa-flask"></i> DATA SIMULASI</span>
                        <?php else: ?>
                            <span class="text-[10px] px-2.5 py-0.5 rounded-full bg-blue-100 text-blue-700 font-extrabold uppercase tracking-wider">ENTERPRISE OS</span>
                        <?php endif; ?>
                    </div>
                    <p class="text-xs text-slate-500 mt-0.5">Pantau biaya, pendapatan, ROAS, dan efektivitas iklan seluruh marketplace dalam satu pusat kendali.</p>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 text-xs">
            <button type="button" onclick="openModal('mktHelpModal')" title="Panduan Perhitungan ROAS & Formula" class="p-2.5 text-slate-400 hover:text-slate-600 border border-slate-200 bg-white rounded-xl shadow-xs">
                <i class="fa-solid fa-circle-question text-sm"></i>
            </button>
            <a href="marketplace.php?tab=pengaturan&amp;peng_sub=integrasi" class="flex items-center gap-1.5 px-3.5 py-2 bg-white border border-slate-200 hover:bg-slate-50 rounded-xl text-slate-700 font-bold shadow-xs transition">
                <i class="fa-solid fa-plug text-xs text-blue-600"></i> Status Integrasi
            </a>
            <button type="button" onclick="window.print()" class="flex items-center gap-1.5 px-3.5 py-2 bg-white border border-slate-200 hover:bg-slate-50 rounded-xl text-slate-700 font-bold shadow-xs">
                <i class="fa-solid fa-download text-xs text-slate-500"></i> Ekspor Laporan
            </button>
            <!-- Toggle PPN 11% Real-Time -->
            <button type="button" onclick="togglePpn()" class="px-3.5 py-2 rounded-xl text-xs font-bold transition shadow-xs flex items-center gap-1.5 <?= $mktIncludePpn ? 'bg-indigo-600 text-white hover:bg-indigo-700 shadow-indigo-600/20' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50' ?>" title="Kalkulasi tagihan iklan sebelum vs sesudah pajak PPN 11%">
                <span><?= $mktIncludePpn ? '✓ PPN 11% ON (Netto + Pajak)' : 'PPN 11% OFF (Sebelum Pajak)' ?></span>
            </button>
            <!-- Filter Toko -->
            <select onchange="window.location.href='marketplace.php?store='+this.value+'&tab=marketing&mkt_sub=<?= urlencode($mktSub) ?>'" class="px-3 py-2 bg-white border border-slate-200 rounded-xl font-bold text-slate-700 shadow-xs">
                <option value="ALL" disabled>Pilih Toko Aktif</option>
                <?php foreach ($stores as $st): ?>
                <option value="<?= htmlspecialchars($st['id']) ?>" <?= $st['id'] === $activeStoreId ? 'selected' : '' ?>><?= htmlspecialchars($st['store_name']) ?> (<?= htmlspecialchars($st['platform']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- 3. INTERNAL SUB-NAVIGATION TABS (Segmented Control) -->
    <nav class="jl-ads-tabs" aria-label="Navigasi Internal Pusat Iklan">
        <a href="<?= htmlspecialchars($mktTabUrl('ringkasan')) ?>" class="jl-ads-tab <?= $mktSub === 'ringkasan' ? 'is-active' : '' ?>">
            <i class="fa-solid fa-layer-group text-xs"></i>
            <span>Ringkasan Iklan</span>
            <span class="jl-ads-tab-badge">Omnichannel</span>
        </a>
        <a href="<?= htmlspecialchars($mktTabUrl('shopee')) ?>" class="jl-ads-tab <?= $mktSub === 'shopee' ? 'is-active' : '' ?>">
            <i class="fa-solid fa-store text-xs text-orange-500"></i>
            <span>Shopee Ads</span>
            <span class="jl-ads-tab-badge">Aktif</span>
        </a>
        <a href="<?= htmlspecialchars($mktTabUrl('tiktok')) ?>" class="jl-ads-tab <?= $mktSub === 'tiktok' ? 'is-active' : '' ?>">
            <i class="fa-brands fa-tiktok text-xs text-slate-800"></i>
            <span>TikTok Ads</span>
            <span class="jl-ads-tab-badge"><?= $isDemo ? 'Simulasi' : 'Standby' ?></span>
        </a>
        <a href="<?= htmlspecialchars($mktTabUrl('gmv_max')) ?>" class="jl-ads-tab <?= $mktSub === 'gmv_max' ? 'is-active' : '' ?>">
            <i class="fa-solid fa-bolt text-xs text-purple-600"></i>
            <span>TikTok GMV Max</span>
            <span class="jl-ads-tab-badge">Pro</span>
        </a>
        <a href="<?= htmlspecialchars($mktTabUrl('produk')) ?>" class="jl-ads-tab <?= $mktSub === 'produk' ? 'is-active' : '' ?>">
            <i class="fa-solid fa-box-open text-xs text-blue-600"></i>
            <span>Analitik Produk</span>
            <?php if (($kpi['bocor_count'] ?? 0) > 0): ?>
                <span class="jl-ads-tab-badge bg-rose-100 text-rose-700"><?= $kpi['bocor_count'] ?> Bocor</span>
            <?php endif; ?>
        </a>
        <a href="<?= htmlspecialchars($mktTabUrl('laporan')) ?>" class="jl-ads-tab <?= $mktSub === 'laporan' ? 'is-active' : '' ?>">
            <i class="fa-solid fa-file-invoice-dollar text-xs text-emerald-600"></i>
            <span>Laporan Iklan</span>
        </a>
    </nav>

    <!-- 4. HERO COMMAND CENTER BANNER (Navy & Gold Enterprise) -->
    <div class="jl-marketplace">
        <section class="jl-hero p-6 sm:p-8">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 relative z-10">
                <div class="space-y-2">
                    <div class="jl-hero-kicker">
                        <i class="fa-solid fa-crown text-amber-400 text-xs"></i>
                        <span>JAGO LABA ENTERPRISE • <?= strtoupper($activePlatform) ?></span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-black text-white"><?= htmlspecialchars($heroTitle) ?></h1>
                    <p class="text-xs sm:text-sm text-blue-100 max-w-2xl leading-relaxed"><?= htmlspecialchars($heroDesc) ?></p>
                </div>

                <div class="flex flex-wrap items-center gap-3 shrink-0">
                    <div class="px-4 py-2.5 rounded-2xl bg-slate-950/60 border border-white/10 backdrop-blur-md flex items-center gap-2.5 text-xs font-bold text-slate-200 shadow-inner">
                        <span class="w-2.5 h-2.5 rounded-full <?= ($mktSub === 'tiktok' || $mktSub === 'gmv_max') ? 'bg-amber-400' : 'bg-emerald-400 animate-pulse' ?>"></span>
                        <span><?= ($mktSub === 'tiktok' || $mktSub === 'gmv_max') ? 'TikTok API Standby' : 'Shopee Ads Live Sync' ?></span>
                    </div>
                    <button type="button" id="btnRefreshMkt" onclick="syncMarketingAds('<?= htmlspecialchars($activeStoreId) ?>')" class="jl-hero-action">
                        <i class="fa-solid fa-arrows-rotate text-xs"></i>
                        <span>Sinkronisasi Data Iklan</span>
                    </button>
                    <button type="button" onclick="openModal('priceSimulatorModal')" class="jl-hero-action jl-hero-action-secondary">
                        <i class="fa-solid fa-calculator text-xs"></i>
                        <span>Kalkulator ROAS</span>
                    </button>
                </div>
            </div>
        </section>
    </div>

    <!-- FILTER PERIODE FORM -->
    <form method="get" class="flex flex-wrap items-center justify-between gap-3 bg-white p-4 rounded-2xl border border-slate-200 shadow-xs">
        <div class="flex items-center gap-2 text-xs font-bold text-slate-700">
            <i class="fa-regular fa-calendar text-blue-600"></i>
            <span>Periode Data Laporan Iklan:</span>
        </div>
        <div class="flex items-center gap-2">
            <?php foreach ($_GET as $key => $value): ?>
                <?php if (is_scalar($value) && !in_array($key, ['date_start', 'date_end', 'page'], true)): ?>
                    <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars((string)$value) ?>">
                <?php endif; ?>
            <?php endforeach; ?>
            <?php require (dirname(__DIR__, 4) . '/app/Modules/Marketplace/Views/marketplace_date_range.php'); ?>
        </div>
    </form>

    <!-- ==================================================================== -->
    <!-- 5. EMPAT KPI CARD UTAMA — PERSIS SCREENSHOT FIN-KAMI                 -->
    <!-- Urutan: 1. Biaya Iklan, 2. Pendapatan, 3. ROAS, 4. Biaya per Pesanan -->
    <!-- ==================================================================== -->
    <div class="jl-ads-kpi-grid">
        <!-- KPI 1: BIAYA IKLAN -->
        <article class="jl-ads-kpi-card">
            <div class="jl-ads-kpi-content">
                <span class="jl-ads-kpi-title">
                    Biaya Iklan
                    <span class="jl-ads-kpi-info-trigger" data-tooltip="Total biaya iklan yang tercatat dalam periode yang dipilih berdasarkan sumber laporan iklan." title="Total biaya iklan yang tercatat dalam periode yang dipilih berdasarkan sumber laporan iklan.">&#9432;</span>
                </span>
                <p class="jl-ads-kpi-value">
                    <?= $demoSpend > 0 ? 'Rp ' . number_format($demoSpend, 0, ',', '.') : 'Rp 0' ?>
                </p>
                <span class="jl-ads-kpi-desc jl-ads-kpi-desc--gray">
                    <?= $mktIncludePpn ? 'Simulasi termasuk PPN 11%' : 'Sesuai biaya iklan yang tercatat' ?>
                </span>
            </div>
            <div class="jl-ads-kpi-icon-box jl-ads-kpi-icon-box--rose">
                <span>&#36;</span>
            </div>
        </article>

        <!-- KPI 2: PENDAPATAN -->
        <article class="jl-ads-kpi-card">
            <div class="jl-ads-kpi-content">
                <span class="jl-ads-kpi-title">
                    Pendapatan
                    <span class="jl-ads-kpi-info-trigger" data-tooltip="Total pendapatan yang diatribusikan kepada iklan dalam periode terpilih." title="Total pendapatan yang diatribusikan kepada iklan dalam periode terpilih.">&#9432;</span>
                </span>
                <p class="jl-ads-kpi-value">
                    <?= $demoRevenue > 0 ? 'Rp ' . number_format($demoRevenue, 0, ',', '.') : 'Rp 0' ?>
                </p>
                <span class="jl-ads-kpi-desc jl-ads-kpi-desc--emerald">
                    <?= number_format($demoOrders) ?> pesanan dihasilkan
                </span>
            </div>
            <div class="jl-ads-kpi-icon-box jl-ads-kpi-icon-box--emerald">
                <span>&#128722;</span>
            </div>
        </article>

        <!-- KPI 3: ROAS -->
        <article class="jl-ads-kpi-card">
            <div class="jl-ads-kpi-content">
                <span class="jl-ads-kpi-title">
                    ROAS
                    <span class="jl-ads-kpi-info-trigger" data-tooltip="Rasio pendapatan yang diatribusikan ke iklan dibandingkan dengan biaya iklan." title="Rasio pendapatan yang diatribusikan ke iklan dibandingkan dengan biaya iklan.">&#9432;</span>
                </span>
                <p class="jl-ads-kpi-value">
                    <?= $demoSpend > 0 ? number_format($demoRoas, 2) . '×' : '0.00×' ?>
                </p>
                <span class="jl-ads-kpi-desc jl-ads-kpi-desc--teal">
                    Pendapatan dibagi biaya iklan tercatat
                </span>
            </div>
            <div class="jl-ads-kpi-icon-box jl-ads-kpi-icon-box--teal">
                <span>&#10034;</span>
            </div>
        </article>

        <!-- KPI 4: BIAYA PER PESANAN -->
        <article class="jl-ads-kpi-card">
            <div class="jl-ads-kpi-content">
                <span class="jl-ads-kpi-title">
                    Biaya per Pesanan
                    <span class="jl-ads-kpi-info-trigger" data-tooltip="Rata-rata biaya iklan untuk memperoleh satu pesanan teratribusi." title="Rata-rata biaya iklan untuk memperoleh satu pesanan teratribusi.">&#9432;</span>
                </span>
                <p class="jl-ads-kpi-value">
                    <?= $demoOrders > 0 ? 'Rp ' . number_format($demoCpo, 0, ',', '.') : 'Rp 0' ?>
                </p>
                <span class="jl-ads-kpi-desc jl-ads-kpi-desc--purple">
                    Rata-rata Akuisisi Order
                </span>
            </div>
            <div class="jl-ads-kpi-icon-box jl-ads-kpi-icon-box--purple">
                <span>&#128092;</span>
            </div>
        </article>
    </div>

    <!-- ==================================================================== -->
    <!-- 6. METRIK IKLAN LANJUTAN (Advanced Metrics Grid)                     -->
    <!-- ==================================================================== -->
    <div class="jl-ads-advanced-grid">
        <div class="jl-ads-metric-card">
            <div class="jl-ads-metric-card__header">
                <span class="jl-ads-metric-card__label">Total Klik</span>
                <i class="fa-solid fa-arrow-pointer jl-ads-metric-card__icon"></i>
            </div>
            <span class="jl-ads-metric-card__value"><?= number_format($demoClicks) ?></span>
            <span class="jl-ads-metric-card__sub">Aktivitas klik interaktif</span>
        </div>

        <div class="jl-ads-metric-card">
            <div class="jl-ads-metric-card__header">
                <span class="jl-ads-metric-card__label">Total Impresi</span>
                <i class="fa-regular fa-eye jl-ads-metric-card__icon"></i>
            </div>
            <span class="jl-ads-metric-card__value"><?= number_format($demoImpressions) ?></span>
            <span class="jl-ads-metric-card__sub">Tayangan iklan produk</span>
        </div>

        <div class="jl-ads-metric-card">
            <div class="jl-ads-metric-card__header">
                <span class="jl-ads-metric-card__label">CTR (Rasio Klik)</span>
                <i class="fa-solid fa-percent jl-ads-metric-card__icon"></i>
            </div>
            <span class="jl-ads-metric-card__value"><?= $demoImpressions > 0 ? number_format(($demoClicks / $demoImpressions) * 100, 2) . '%' : '0.00%' ?></span>
            <span class="jl-ads-metric-card__sub">Efektivitas materi iklan</span>
        </div>

        <div class="jl-ads-metric-card">
            <div class="jl-ads-metric-card__header">
                <span class="jl-ads-metric-card__label">CPC (Biaya per Klik)</span>
                <i class="fa-solid fa-coins jl-ads-metric-card__icon"></i>
            </div>
            <span class="jl-ads-metric-card__value"><?= $demoClicks > 0 ? 'Rp ' . number_format($demoSpend / $demoClicks, 0, ',', '.') : 'Rp 0' ?></span>
            <span class="jl-ads-metric-card__sub">Rata-rata biaya per klik</span>
        </div>

        <div class="jl-ads-metric-card">
            <div class="jl-ads-metric-card__header">
                <span class="jl-ads-metric-card__label">CPM (Biaya 1.000 Tayang)</span>
                <i class="fa-solid fa-chart-line jl-ads-metric-card__icon"></i>
            </div>
            <span class="jl-ads-metric-card__value"><?= $demoImpressions > 0 ? 'Rp ' . number_format(($demoSpend / $demoImpressions) * 1000, 0, ',', '.') : 'Rp 0' ?></span>
            <span class="jl-ads-metric-card__sub">Estimasi tarif jangkauan</span>
        </div>

        <div class="jl-ads-metric-card">
            <div class="jl-ads-metric-card__header">
                <span class="jl-ads-metric-card__label">Pesanan Teratribusi</span>
                <i class="fa-solid fa-bag-shopping jl-ads-metric-card__icon"></i>
            </div>
            <span class="jl-ads-metric-card__value"><?= number_format($demoOrders) ?></span>
            <span class="jl-ads-metric-card__sub">Konversi order langsung</span>
        </div>

        <div class="jl-ads-metric-card">
            <div class="jl-ads-metric-card__header">
                <span class="jl-ads-metric-card__label">Conversion Rate</span>
                <i class="fa-solid fa-bullseye jl-ads-metric-card__icon"></i>
            </div>
            <span class="jl-ads-metric-card__value"><?= $demoClicks > 0 ? number_format(($demoOrders / $demoClicks) * 100, 2) . '%' : '0.00%' ?></span>
            <span class="jl-ads-metric-card__sub">Rasio klik jadi pesanan</span>
        </div>

        <div class="jl-ads-metric-card">
            <div class="jl-ads-metric-card__header">
                <span class="jl-ads-metric-card__label">Campaign / Model Aktif</span>
                <i class="fa-solid fa-cubes jl-ads-metric-card__icon"></i>
            </div>
            <span class="jl-ads-metric-card__value"><?= count($products) ?></span>
            <span class="jl-ads-metric-card__sub">Item iklan termonitor</span>
        </div>
    </div>

    <!-- ==================================================================== -->
    <!-- 7. GRAFIK ANALITIK IKLAN (AdsPerformanceChart)                       -->
    <!-- ==================================================================== -->
    <div class="jl-ads-chart-card">
        <div class="jl-ads-chart-header">
            <div>
                <h3 class="jl-ads-chart-title">Performa Belanja Iklan vs Pendapatan Teratribusi</h3>
                <p class="jl-ads-chart-subtitle">Perbandingan nominal biaya iklan terhadap omzet transaksi yang dihasilkan per campaign/produk</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Tampilan:</span>
                <span class="px-2.5 py-1 rounded-lg bg-blue-50 text-blue-700 font-bold text-xs border border-blue-200">Per Campaign</span>
            </div>
        </div>

        <div class="jl-ads-chart-canvas-wrap">
            <canvas id="adsPerformanceCanvas"></canvas>
        </div>

        <!-- JSON Data Bridge untuk Grafik -->
        <script id="adsChartDataJson" type="application/json">
            <?= json_encode([
                'labels' => $chartLabels,
                'spend' => $chartSpend,
                'revenue' => $chartRevenue
            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
        </script>
    </div>

    <!-- ==================================================================== -->
    <!-- 8. GMV MAX KHUSUS KONTROL & AKSI (Jika Tab GMV Max Dipilih)          -->
    <!-- ==================================================================== -->
    <?php if ($mktSub === 'gmv_max'): ?>
    <section class="bg-white rounded-2xl border border-slate-200 p-5 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100">
            <div>
                <h3 class="font-extrabold text-slate-900 text-sm flex items-center gap-2">
                    <i class="fa-solid fa-sliders text-purple-600"></i>
                    Kendali Kampanye TikTok GMV Max
                </h3>
                <p class="text-xs text-slate-500">Tindakan kendali kampanye otomatis API TikTok Shop & Ads Manager.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" disabled class="jl-gmv-action-btn" title="Menunggu Integrasi TikTok Ads API pada Fase 2">
                    <i class="fa-solid fa-plus text-xs"></i> Buat Campaign Baru
                </button>
                <button type="button" disabled class="jl-gmv-action-btn" title="Menunggu Integrasi TikTok Ads API pada Fase 2">
                    <i class="fa-solid fa-pen text-xs"></i> Atur Budget
                </button>
                <button type="button" disabled class="jl-gmv-action-btn" title="Menunggu Integrasi TikTok Ads API pada Fase 2">
                    <i class="fa-solid fa-pause text-xs text-amber-500"></i> Jeda Campaign
                </button>
                <button type="button" disabled class="jl-gmv-action-btn" title="Menunggu Integrasi TikTok Ads API pada Fase 2">
                    <i class="fa-solid fa-play text-xs text-emerald-500"></i> Lanjutkan Campaign
                </button>
                <button type="button" disabled class="jl-gmv-action-btn" title="Menunggu Integrasi TikTok Ads API pada Fase 2">
                    <i class="fa-solid fa-crosshairs text-xs text-purple-600"></i> Ubah Target ROI
                </button>
            </div>
        </div>
        <div class="p-3 bg-purple-50/70 border border-purple-100 rounded-xl text-xs text-purple-900 leading-relaxed flex items-center gap-2.5">
            <i class="fa-solid fa-circle-info text-purple-600"></i>
            <span>Tombol tindakan di atas sengaja dinonaktifkan secara aman sesuai spesifikasi Fase 1. Eksekusi mutasi budget dan pause/resume akan dihubungkan saat integrasi resmi TikTok Ads API diaktifkan.</span>
        </div>
    </section>
    <?php endif; ?>

    <!-- ==================================================================== -->
    <!-- 9. TABEL CAMPAIGN IKLAN & PRODUK (15 Kolom Lengkap Sesuai PRD)        -->
    <!-- ==================================================================== -->
    <section class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-xs space-y-4">
        <!-- Filter Bar & Search -->
        <div class="p-4 border-b border-slate-100 space-y-3">
            <div class="flex flex-col md:flex-row gap-3 items-center justify-between">
                <div class="relative flex-1 max-w-md w-full">
                    <input type="text" id="mktSearchInput" value="<?= htmlspecialchars($mktSearch ?? '') ?>" placeholder="Cari nama produk, SKU, ID item atau campaign..." onkeyup="if(event.key === 'Enter') applyMktFilter()" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:ring-1 focus:ring-blue-500 font-medium">
                </div>

                <div class="flex items-center gap-2 overflow-x-auto text-xs w-full md:w-auto scrollbar-hide">
                    <a href="marketplace.php?store=<?= urlencode($activeStoreId) ?>&tab=marketing&mkt_sub=<?= urlencode($mktSub) ?>&mkt_ad_type=Semua<?= $mktIncludePpn ? '&ppn=1' : '' ?>" class="px-3.5 py-2 rounded-xl border border-slate-200 font-semibold whitespace-nowrap transition <?= $mktAdType === 'Semua' && empty($mktFilter) ? 'bg-blue-50 text-blue-700 border-blue-400 font-bold' : 'text-slate-600 hover:bg-slate-50' ?>">
                        Semua Tipe
                    </a>
                    <a href="marketplace.php?store=<?= urlencode($activeStoreId) ?>&tab=marketing&mkt_sub=<?= urlencode($mktSub) ?>&mkt_ad_type=Produk<?= $mktIncludePpn ? '&ppn=1' : '' ?>" class="px-3.5 py-2 rounded-xl border border-slate-200 font-semibold whitespace-nowrap transition <?= $mktAdType === 'Produk' ? 'bg-blue-50 text-blue-700 border-blue-400 font-bold' : 'text-slate-600 hover:bg-slate-50' ?>">
                        Iklan Produk
                    </a>
                    <a href="marketplace.php?store=<?= urlencode($activeStoreId) ?>&tab=marketing&mkt_sub=<?= urlencode($mktSub) ?>&mkt_ad_type=Grup_Toko<?= $mktIncludePpn ? '&ppn=1' : '' ?>" class="px-3.5 py-2 rounded-xl border border-slate-200 font-semibold whitespace-nowrap transition <?= $mktAdType === 'Grup_Toko' ? 'bg-blue-50 text-blue-700 border-blue-400 font-bold' : 'text-slate-600 hover:bg-slate-50' ?>">
                        Iklan Toko &amp; Discovery
                    </a>
                    <a href="marketplace.php?store=<?= urlencode($activeStoreId) ?>&tab=marketing&mkt_sub=<?= urlencode($mktSub) ?>&mkt_ad_type=Live_Affiliate<?= $mktIncludePpn ? '&ppn=1' : '' ?>" class="px-3.5 py-2 rounded-xl border border-slate-200 font-semibold whitespace-nowrap transition <?= $mktAdType === 'Live_Affiliate' ? 'bg-purple-50 text-purple-700 border-purple-400 font-bold' : 'text-slate-600 hover:bg-slate-50' ?>">
                        Iklan Live &amp; Creator
                    </a>
                    <a href="marketplace.php?store=<?= urlencode($activeStoreId) ?>&tab=marketing&mkt_sub=<?= urlencode($mktSub) ?>&mkt_filter=bocor<?= $mktIncludePpn ? '&ppn=1' : '' ?>" class="px-3.5 py-2 rounded-xl border border-rose-200 font-bold whitespace-nowrap transition <?= $mktFilter === 'bocor' ? 'bg-rose-50 text-rose-600 border-rose-400' : 'text-rose-600 hover:bg-rose-50' ?>">
                        🔥 Produk Bocor (<?= (int)($kpi['bocor_count'] ?? 0) ?>)
                    </a>
                    <a href="marketplace.php?store=<?= urlencode($activeStoreId) ?>&tab=marketing&mkt_sub=<?= urlencode($mktSub) ?>&mkt_filter=roas_rendah<?= $mktIncludePpn ? '&ppn=1' : '' ?>" class="px-3.5 py-2 rounded-xl border border-slate-200 font-semibold whitespace-nowrap transition <?= $mktFilter === 'roas_rendah' ? 'bg-rose-50 text-rose-600 border-rose-400 font-bold' : 'text-slate-600 hover:bg-slate-50' ?>">
                        ROAS Rendah
                    </a>
                </div>
            </div>

            <div class="flex items-center justify-between text-xs text-slate-400 pt-1">
                <span>Menampilkan <?= count($products) ?> baris kampanye iklan (<?= $mktIncludePpn ? 'Status: Termasuk PPN 11%' : 'Status: Sebelum PPN' ?>)</span>
                <span>Klik baris mana saja untuk melihat rincian kalkulasi laba dan HPP produk</span>
            </div>
        </div>

        <!-- Table View -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs whitespace-nowrap">
                <thead class="bg-slate-50 text-slate-600 uppercase tracking-wider font-extrabold border-b border-slate-200">
                    <tr>
                        <th class="p-3 pl-4">DETAIL PRODUK / CAMPAIGN</th>
                        <th class="p-3 text-right">BIAYA IKLAN <?= $mktIncludePpn ? '(+PPN)' : '' ?></th>
                        <th class="p-3 text-right">PENDAPATAN &#9432;</th>
                        <th class="p-3 text-center">GROSS ROAS &#9432;</th>
                        <th class="p-3 text-center">NET ROAS &#9432;</th>
                        <th class="p-3 text-center">ACTUAL ROAS &#9432;</th>
                        <th class="p-3 text-center">MIN. ROAS &#9432;</th>
                        <th class="p-3 text-center">PESANAN &#9432;</th>
                        <th class="p-3 text-right">BIAYA/PESANAN &#9432;</th>
                        <th class="p-3 text-right">HPP/UNIT</th>
                        <th class="p-3 text-center">MARGIN LABA &#9432;</th>
                        <th class="p-3 text-right">PROFIT RIIL &#9432;</th>
                        <th class="p-3 text-right">BIAYA PLATFORM &#9432;</th>
                        <th class="p-3 text-right">KOMISI AFFILIATE &#9432;</th>
                        <th class="p-3 text-right pr-4">NET PROFIT &#9432;</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($products as $p): ?>
                    <?php 
                        $isRugi = (float)$p['net_profit'] < 0; 
                        $isBocor = (bool)($p['is_bocor'] ?? false);
                    ?>
                    <tr class="<?= $isBocor ? 'bg-rose-50/60 hover:bg-rose-50/90' : ($isRugi ? 'bg-rose-50/30 hover:bg-rose-50/60' : 'hover:bg-slate-50') ?> transition cursor-pointer" onclick="openProductAdDetail(<?= htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8') ?>)">
                        <!-- 1. Detail Produk -->
                        <td class="p-3 pl-4">
                            <div class="flex items-center gap-3">
                                <img src="<?= htmlspecialchars((string)$p['image_url']) ?>" alt="Product" class="w-10 h-10 rounded-lg object-cover bg-slate-100 border border-slate-200 shrink-0" onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'40\' height=\'40\' fill=\'%23cbd5e1\'><rect width=\'40\' height=\'40\'/></svg>'">
                                <div class="max-w-[220px]">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="font-bold text-slate-900 truncate" title="<?= htmlspecialchars((string)$p['product_name']) ?>">
                                            <?= htmlspecialchars((string)$p['product_name']) ?>
                                        </span>
                                        <?php if (!empty($p['ad_type']) && $p['ad_type'] !== 'Produk'): ?>
                                        <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-blue-100 text-blue-800 uppercase"><?= htmlspecialchars(str_replace('_', ' ', (string)$p['ad_type'])) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="text-[10px] text-slate-400 font-mono block">ID: <?= htmlspecialchars((string)$p['marketplace_item_id']) ?> &bull; <?= htmlspecialchars((string)$p['sku']) ?></span>
                                    <?php if ($isBocor): ?>
                                    <span class="mt-1 px-1.5 py-0.5 rounded text-[10px] font-extrabold bg-rose-100 text-rose-700 border border-rose-300 block line-clamp-1" title="<?= htmlspecialchars((string)$p['bocor_reason']) ?>">
                                        🔥 PRODUK BOCOR: <?= htmlspecialchars((string)$p['bocor_reason']) ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>

                        <!-- 2. Biaya Iklan -->
                        <td class="p-3 text-right font-bold text-rose-600">
                            Rp <?= number_format((float)$p['ad_spend'], 0, ',', '.') ?>
                            <?php if ($mktIncludePpn): ?>
                            <span class="block text-[10px] text-slate-400 font-normal">PPN: Rp <?= number_format((float)$p['ppn_amount'], 0, ',', '.') ?></span>
                            <?php endif; ?>
                        </td>

                        <!-- 3. Pendapatan -->
                        <td class="p-3 text-right font-bold text-blue-600">
                            Rp <?= number_format((float)$p['gross_revenue'], 0, ',', '.') ?>
                        </td>

                        <!-- 4. Gross ROAS -->
                        <td class="p-3 text-center font-extrabold <?= (float)$p['gross_roas'] >= 5.0 ? 'text-emerald-600' : ((float)$p['gross_roas'] >= 2.0 ? 'text-amber-600' : 'text-rose-600') ?>">
                            <?= number_format((float)$p['gross_roas'], 2) ?>x
                        </td>

                        <!-- 5. Net ROAS -->
                        <td class="p-3 text-center font-semibold text-slate-700">
                            <?= number_format((float)$p['net_roas'], 2) ?>x
                        </td>

                        <!-- 6. Actual ROAS -->
                        <td class="p-3 text-center font-semibold text-slate-800">
                            <?= number_format((float)$p['actual_roas'], 2) ?>x
                        </td>

                        <!-- 7. Min ROAS -->
                        <td class="p-3 text-center">
                            <span class="font-bold block"><?= number_format((float)$p['min_roas'], 2) ?>x</span>
                            <?php if ($p['is_aman']): ?>
                            <span class="text-[10px] text-emerald-600 font-bold">&#10003; aman</span>
                            <?php else: ?>
                            <span class="text-[10px] text-rose-600 font-bold">&#10007; rugi</span>
                            <?php endif; ?>
                        </td>

                        <!-- 8. Pesanan -->
                        <td class="p-3 text-center">
                            <span class="font-bold text-slate-900 block"><?= (int)$p['orders_count'] ?></span>
                            <span class="text-[10px] text-slate-400 block">Qty: <?= (int)$p['qty_sold'] ?></span>
                        </td>

                        <!-- 9. Biaya / Pesanan -->
                        <td class="p-3 text-right font-medium text-slate-700">
                            Rp <?= number_format((float)$p['cost_per_order'], 0, ',', '.') ?>
                        </td>

                        <!-- 10. HPP / Unit -->
                        <td class="p-3 text-right font-semibold text-slate-800">
                            Rp <?= number_format((float)$p['unit_cogs_hpp'], 0, ',', '.') ?>
                        </td>

                        <!-- 11. Margin Laba -->
                        <td class="p-3 text-center font-semibold <?= (float)$p['margin_laba_pct'] >= 70 ? 'text-emerald-600' : 'text-slate-700' ?>">
                            <?= number_format((float)$p['margin_laba_pct'], 1) ?>%
                        </td>

                        <!-- 12. Profit Riil -->
                        <td class="p-3 text-right">
                            <span class="font-bold <?= (float)$p['profit_riil'] < 0 ? 'text-rose-600' : 'text-emerald-600' ?> block">
                                <?= number_format((float)$p['profit_riil_pct'], 1) ?>%
                            </span>
                            <span class="text-[11px] <?= (float)$p['profit_riil'] < 0 ? 'text-rose-600' : 'text-slate-600' ?> block">
                                Rp <?= number_format((float)$p['profit_riil'], 0, ',', '.') ?>
                            </span>
                        </td>

                        <!-- 13. Biaya Platform -->
                        <td class="p-3 text-right">
                            <span class="font-bold text-amber-600 block"><?= number_format((float)$p['platform_fee_pct'], 1) ?>%</span>
                            <span class="text-[11px] text-slate-500 block">Rp <?= number_format((float)$p['platform_fee'], 0, ',', '.') ?></span>
                        </td>

                        <!-- 14. Komisi Affiliate -->
                        <td class="p-3 text-right">
                            <span class="font-bold text-purple-700 block"><?= (float)$p['affiliate_fee'] > 0 ? formatRp($p['affiliate_fee']) : '-' ?></span>
                            <?php if ((float)$p['affiliate_fee'] > 0): ?>
                            <span class="text-[10px] text-purple-500 block">Creator/Host</span>
                            <?php endif; ?>
                        </td>

                        <!-- 15. Net Profit Akhir -->
                        <td class="p-3 text-right pr-4">
                            <span class="font-bold <?= $isRugi ? 'text-rose-600' : 'text-emerald-600' ?> block">
                                <?= number_format((float)$p['net_profit_pct'], 1) ?>%
                            </span>
                            <span class="text-[11px] font-bold <?= $isRugi ? 'text-rose-600' : 'text-emerald-600' ?> block">
                                <?= $isRugi ? '-' : '' ?>Rp <?= number_format(abs((float)$p['net_profit']), 0, ',', '.') ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                    <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="15" class="p-12 text-center text-slate-400">
                            <div class="flex flex-col items-center justify-center gap-2">
                                <i class="fa-solid fa-chart-simple text-3xl text-slate-300"></i>
                                <p class="font-bold text-slate-600 text-sm">Belum Ada Data Iklan untuk Periode Ini</p>
                                <p class="text-xs text-slate-400">Sinkronkan data iklan atau sesuaikan filter rentang tanggal laporan di atas.</p>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

</div>

