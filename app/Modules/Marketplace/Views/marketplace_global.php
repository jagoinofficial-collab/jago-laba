<?php
declare(strict_types=1);

$summary = $globalData['summary'];
require (dirname(__DIR__, 4) . '/app/Modules/Marketplace/Views/marketplace_global_navigation_config.php');
$platformBadge = static function (string $platform): string {
    return match ($platform) {
        'Shopee' => 'bg-orange-100 text-orange-700 border-orange-200',
        'TikTok Shop' => 'bg-slate-900 text-white border-slate-900',
        default => 'bg-slate-100 text-slate-700 border-slate-200',
    };
};
$statusClass = static function (string $tone): string {
    return match ($tone) {
        'success' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'danger' => 'bg-rose-50 text-rose-700 border-rose-200',
        default => 'bg-amber-50 text-amber-800 border-amber-200',
    };
};
$query = static function (array $changes = []) use ($globalFilters, $globalTab, &$marketingFilter, &$productSection): string {
    $targetTab = (string)($changes['tab'] ?? $globalTab);
    $values = array_merge([
        'scope' => 'global',
        'tab' => $targetTab,
        'store_filter' => $globalFilters['store_id'],
        'platform' => $globalFilters['platform'],
        'search' => $globalFilters['search'],
        'date_start' => $globalFilters['date_start'],
        'date_end' => $globalFilters['date_end'],
        'page' => $targetTab === $globalTab ? ($globalFilters['page'] ?? 1) : null,
        'include_disconnected' => $globalFilters['include_disconnected'] ? '1' : null,
        'mkt_filter' => $targetTab === 'marketing' ? ($marketingFilter ?? null) : null,
        'product_section' => $targetTab === 'produk' ? ($productSection ?? 'hpp') : null,
        'product_status' => $targetTab === 'produk' ? ($_GET['product_status'] ?? null) : null,
        'date_basis' => $targetTab === 'analitik' ? $globalFilters['date_basis'] : null,
        'analytics_mode' => $targetTab === 'analitik' ? $globalFilters['analytics_mode'] : null,
        'analytics_stores' => $targetTab === 'analitik' ? $globalFilters['analytics_stores'] : null,
    ], $changes);
    return http_build_query(array_filter($values, static fn($value): bool => $value !== null && $value !== ''));
};
$oauthStatus = (string)($_GET['oauth_status'] ?? '');
$oauthNotice = match ($oauthStatus) {
    'success' => ['success', 'Koneksi toko berhasil diverifikasi. Antrean sinkronisasi awal telah dibuat.'],
    'cancelled' => ['warning', 'Otorisasi dibatalkan.'],
    'choose' => ['warning', 'Platform mengembalikan beberapa toko. Pilih toko yang akan dihubungkan.'],
    'error' => ['danger', 'Koneksi toko belum dapat diproses. Periksa konfigurasi dan coba lagi.'],
    default => null,
};
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marketplace Global &bull; Jago Laba</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/marketplace/css/marketplace-ui.css">
<link rel="stylesheet" href="assets/marketplace/css/marketplace-system.css"><script src="assets/marketplace/js/marketplace-system.js" defer></script>
<link rel="stylesheet" href="assets/shared/css/jl-design-tokens.css">
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter', '"Plus Jakarta Sans"', 'sans-serif'] }, colors: { brand: { 50:'#fff7ed',100:'#ffedd5',500:'#f97316',600:'#ea580c',700:'#c2410c' }, blue: { 600:'#2563eb',700:'#1d4ed8' }, gold: { 500:'#f59e0b',600:'#d97706' } } } } };
    </script>
    <style>
        body { font-family: 'Inter', 'Plus Jakarta Sans', sans-serif; background: #f8fafc; }
        .jl-table th { position: sticky; top: 0; z-index: 1; }
        .integration-hero { background: radial-gradient(circle at 85% 15%, rgba(251,146,60,.22), transparent 32%), linear-gradient(125deg, #0f172a, #1e293b 58%, #334155); }
        .integration-card { transition: transform .18s ease, box-shadow .18s ease; }
        .integration-card:hover { transform: translateY(-2px); box-shadow: 0 16px 35px rgba(15,23,42,.08); }
    </style>
</head>
<body class="jl-marketplace text-slate-800 min-h-screen">
<div class="min-h-screen flex flex-col md:flex-row">
    <?php require_once (dirname(__DIR__, 4) . '/app/Modules/Marketplace/Views/marketplace_platform_logo.php'); require (dirname(__DIR__, 4) . '/app/Modules/Marketplace/Views/marketplace_global_sidebar.php'); ?>

    <main class="jl-main md:ml-64 min-w-0 flex-1 p-4 sm:p-6 lg:p-8 pb-16">
        <?php require (dirname(__DIR__, 4) . '/app/Modules/Marketplace/Views/marketplace_background_sync.php'); ?>
        <div class="max-w-[1500px] mx-auto space-y-6">
            <header class="jl-hero flex flex-col lg:flex-row lg:items-center justify-between gap-5 p-6 sm:p-8">
                <div>
                    <p class="jl-hero-kicker">Jago Laba · Manajemen Marketplace</p>
                    <h1 class="text-2xl sm:text-3xl font-extrabold mt-2"><?= htmlspecialchars($tabLabels[$globalTab]) ?></h1>
                    <p class="text-sm mt-2 leading-6">Pantau data toko yang diizinkan dalam satu ruang kerja. Data Konveksi tetap tercatat terpisah.</p>
                </div>
                <div class="flex flex-wrap gap-2"><?php if ($globalTab === 'projects'): ?><button type="button" id="open-connect-store-modal" class="jl-hero-action">+ Hubungkan toko</button><?php else: ?><a href="marketplace.php?scope=global&tab=projects" class="jl-hero-action">Lihat semua toko</a><?php endif; ?><a href="marketplace.php?scope=global&tab=pengaturan&peng_sub=integrasi" class="jl-hero-action jl-hero-action-secondary">Status integrasi</a></div>
            </header>

            <?php if ($oauthNotice): ?>
                <div role="status" aria-live="polite" class="rounded-2xl border px-4 py-3 text-xs <?= $oauthNotice[0] === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : ($oauthNotice[0] === 'danger' ? 'border-rose-200 bg-rose-50 text-rose-900' : 'border-amber-200 bg-amber-50 text-amber-950') ?>">
                    <?= htmlspecialchars($oauthNotice[1]) ?>
                </div>
            <?php endif; ?>

            <?php if ($globalTab === 'integrasi'): ?>
                <?php require (dirname(__DIR__, 4) . '/app/Modules/Marketplace/Views/marketplace_integrations.php'); ?>
            <?php else: ?>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-950">
                <b>Status integrasi:</b> Koneksi terverifikasi belum berarti pesanan atau stok sudah tersinkron. Waktu sinkronisasi hanya ditampilkan setelah proses resmi berhasil.
            </div>

            <form method="get" class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm grid grid-cols-1 md:grid-cols-2 xl:grid-cols-6 gap-3">
                <input type="hidden" name="scope" value="global">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($globalTab) ?>">
                <?php if ($globalTab === 'analitik'): ?>
                    <input type="hidden" name="analytics_mode" value="<?= htmlspecialchars($globalFilters['analytics_mode']) ?>">
                    <label><span class="block text-[11px] font-bold text-slate-500 mb-1">Dasar Tanggal</span><select name="date_basis" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs"><option value="created" <?= $globalFilters['date_basis']==='created'?'selected':'' ?>>Tgl Dibuat</option><option value="released" <?= $globalFilters['date_basis']==='released'?'selected':'' ?>>Tgl Dana Dilepas</option></select></label>
                    <details class="relative"><summary class="cursor-pointer text-xs font-bold border rounded-xl px-3 py-2">Pilih beberapa proyek/toko</summary><div class="absolute z-30 bg-white border rounded-xl shadow-lg p-3 mt-2 min-w-[250px] max-h-72 overflow-auto"><?php foreach($analyticsReport['available_stores'] as $store): ?><label class="flex gap-2 py-2 text-xs"><input type="checkbox" name="analytics_stores[]" value="<?= htmlspecialchars($store['id']) ?>" <?= in_array($store['id'],$globalFilters['analytics_stores'],true)?'checked':'' ?>><?= htmlspecialchars($store['store_name']) ?></label><?php endforeach; ?><p class="text-[10px] text-slate-400">Tanpa pilihan = semua toko dalam filter.</p></div></details>
                <?php endif; ?>
                <?php if ($globalTab === 'produk'): ?><input type="hidden" name="product_section" value="<?= htmlspecialchars($productSection) ?>"><?php endif; ?>
                <?php if ($globalTab === 'produk'): ?><label><span class="block text-[11px] font-bold text-slate-500 mb-1">Status Produk</span><select name="product_status" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs"><option value="">Semua Status</option><?php foreach ($catalogData['statuses'] as $status): ?><option value="<?= htmlspecialchars($status) ?>" <?= ($_GET['product_status'] ?? '') === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option><?php endforeach; ?></select></label><?php endif; ?>
                <?php if ($globalTab === 'marketing' && $marketingFilter !== ''): ?><input type="hidden" name="mkt_filter" value="<?= htmlspecialchars($marketingFilter) ?>"><?php endif; ?>
                <?php if ($globalTab !== 'analitik'): ?><label class="xl:col-span-2">
                    <span class="block text-[11px] font-bold text-slate-500 mb-1">Cari toko / deskripsi / vendor / SKU</span>
                    <input name="search" value="<?= htmlspecialchars($globalFilters['search']) ?>" placeholder="Cari toko, deskripsi, vendor, produk..." class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-brand-100">
                </label>
                <label>
                    <span class="block text-[11px] font-bold text-slate-500 mb-1">Toko</span>
                    <select name="store_filter" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs">
                        <option value="">Semua Toko</option>
                        <?php foreach ($globalData['stores'] as $store): ?>
                            <option value="<?= htmlspecialchars((string)$store['id']) ?>" <?= $globalFilters['store_id'] === (string)$store['id'] ? 'selected' : '' ?>><?= htmlspecialchars($store['store_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php endif; ?>
                <label>
                    <span class="block text-[11px] font-bold text-slate-500 mb-1">Platform</span>
                    <select name="platform" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs">
                        <option value="">Semua Platform</option>
                        <option value="Shopee" <?= $globalFilters['platform'] === 'Shopee' ? 'selected' : '' ?>>Shopee</option>
                        <option value="TikTok Shop" <?= $globalFilters['platform'] === 'TikTok Shop' ? 'selected' : '' ?>>TikTok Shop</option>
                        <option value="Tokopedia" <?= $globalFilters['platform'] === 'Tokopedia' ? 'selected' : '' ?>>Tokopedia (legacy)</option>
                    </select>
                </label>
                <?php require (dirname(__DIR__, 4) . '/app/Modules/Marketplace/Views/marketplace_date_range.php'); ?>
                <div class="xl:col-span-6 flex flex-wrap items-center justify-between gap-3 pt-1">
                    <label class="flex items-center gap-2 text-xs text-slate-600"><input type="checkbox" name="include_disconnected" value="1" <?= $globalFilters['include_disconnected'] ? 'checked' : '' ?>> Sertakan toko terputus untuk histori</label>
                    <div class="flex gap-2"><a href="marketplace.php?scope=global&tab=<?= urlencode($globalTab) ?>" class="px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-600">Reset</a><button class="px-4 py-2 rounded-xl bg-slate-900 text-white text-xs font-bold">Terapkan Filter</button></div>
                </div>
            </form>

            <?php if ($globalTab === 'projects'): ?>
                <section class="space-y-4">
                    <div class="flex items-end justify-between"><div><h2 class="text-lg font-extrabold text-slate-900">Proyek/Toko</h2><p class="text-xs text-slate-500 mt-1">Klik toko untuk masuk ke ruang kerja per toko.</p></div><span class="text-xs font-bold text-slate-500"><?= count($globalData['stores']) ?> toko dalam scope</span></div>
                    <?php if (!$globalData['stores']): ?><div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">Belum ada toko yang dapat diakses pada scope ini.</div><?php endif; ?>
                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                        <?php foreach ($globalData['stores'] as $store): ?>
                            <article class="jl-store-card bg-white rounded-2xl border border-slate-200 p-5 shadow-sm hover:shadow-md transition">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="flex items-start gap-3 min-w-0"><?= jlMarketplacePlatformLogo((string)$store['platform'],40) ?><div class="min-w-0"><h3 class="font-extrabold text-slate-900 truncate"><?= htmlspecialchars($store['store_name']) ?></h3><p class="text-xs text-slate-500 mt-1"><?= htmlspecialchars($store['platform']) ?> &bull; <?= htmlspecialchars((string)$store['store_identifier']) ?></p></div></div>
                                    <span class="shrink-0 rounded-full border px-2.5 py-1 text-[10px] font-bold <?= $statusClass((string)$store['authorization_tone']) ?>"><?= htmlspecialchars($store['authorization_label']) ?></span>
                                </div>
                                <div class="grid grid-cols-2 gap-3 border-t border-slate-100 mt-5 pt-4 text-xs"><div><p class="text-slate-400">Pesanan</p><p class="font-extrabold text-slate-900 mt-1"><?= number_format((int)$store['total_orders'], 0, ',', '.') ?></p></div><div><p class="text-slate-400">Penjualan terfilter</p><p class="font-extrabold text-slate-900 mt-1"><?= formatRp($store['total_sales']) ?></p></div></div>
                                <div class="mt-4 text-[11px] text-slate-500"><span class="font-bold <?= ($store['sync_status'] ?? '') === 'failed' ? 'text-rose-600' : (($store['sync_status'] ?? '') === 'queued' ? 'text-amber-700' : 'text-slate-600') ?>"><?= htmlspecialchars((string)($store['sync_label'] ?? 'Sinkronisasi awal menunggu worker')) ?></span><?php if (!empty($store['last_successful_sync_at'])): ?><span class="block mt-1">Berhasil terakhir <?= htmlspecialchars((string)$store['last_successful_sync_at']) ?></span><?php endif; ?><?php if (!empty($store['sync_active_jobs'])): ?><span class="block mt-1">Antrean aktif: <?= (int)$store['sync_active_jobs'] ?> job</span><?php endif; ?><?php if (!empty($store['sync_success_jobs'])): ?><span class="block mt-1">Job berhasil: <?= (int)$store['sync_success_jobs'] ?></span><?php endif; ?><?php if (!empty($store['sync_failed_jobs'])): ?><span class="block text-rose-600 mt-1">Job gagal: <?= (int)$store['sync_failed_jobs'] ?></span><?php endif; ?><?php if (!empty($store['last_error_code'])): ?><span class="block text-rose-600 mt-1">Error: <?= htmlspecialchars((string)$store['last_error_code']) ?></span><?php endif; ?></div>
                                <div class="mt-4 flex flex-wrap gap-2">
                                    <a href="marketplace.php?store=<?= urlencode((string)$store['id']) ?>&tab=dashboard" class="inline-flex items-center gap-2 rounded-xl bg-brand-50 px-3 py-2 text-xs font-extrabold text-brand-700 hover:bg-brand-100">Masuk ke toko <span aria-hidden="true">&rarr;</span></a>
                                    <?php if (!empty($store['oauth_connection_id'])): ?>
                                        <?php if (!empty($store['is_connection_verified'])): ?><button type="button" data-oauth-action="sync" data-store-id="<?= htmlspecialchars((string)$store['id']) ?>" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Sinkronisasi Data</button><?php endif; ?>
                                        <?php if (($store['oauth_status'] ?? '') === 'reauthorize' || ($store['oauth_status'] ?? '') === 'disconnected'): ?>
                                            <button type="button" data-oauth-action="reconnect" data-platform="<?= htmlspecialchars((string)$store['platform']) ?>" class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-bold text-amber-800 hover:bg-amber-100">Hubungkan ulang</button>
                                        <?php else: ?>
                                            <button type="button" data-oauth-action="disconnect" data-store-id="<?= htmlspecialchars((string)$store['id']) ?>" class="rounded-xl border border-rose-200 px-3 py-2 text-xs font-bold text-rose-700 hover:bg-rose-50">Putuskan koneksi</button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php else: ?>
                <?php if (!in_array($globalTab, ['pengeluaran', 'marketing', 'produk', 'analitik', 'pemasukan'], true)): ?><section class="grid grid-cols-2 lg:grid-cols-6 gap-3">
                    <?php foreach ([['Pesanan',$summary['total_orders'],'number'],['Penjualan',$summary['total_sales'],'money'],['Biaya',$summary['total_fees'],'money'],['Untuk Dibayar',$summary['net_income'],'money'],['Total HPP',$summary['total_cogs'],'money'],['Laba Transaksi',$summary['profit'],'money']] as $kpi): ?>
                        <div class="jl-stat-card bg-white border border-slate-200 rounded-2xl p-4 shadow-sm"><p class="text-[11px] font-bold text-slate-400"><?= htmlspecialchars($kpi[0]) ?></p><p class="text-lg font-extrabold mt-2 <?= $kpi[0] === 'Laba Transaksi' && $kpi[1] < 0 ? 'text-rose-600' : 'text-slate-900' ?>"><?= $kpi[2] === 'money' ? ($kpi[1]===null?'Menunggu data keuangan/HPP':formatRp($kpi[1])) : number_format((int)$kpi[1], 0, ',', '.') ?></p></div>
                    <?php endforeach; ?>
                </section>

                <?php endif; ?>
                <?php if ($globalTab === 'pemasukan'): ?>
                    <?php require (dirname(__DIR__, 4) . '/app/Modules/Marketplace/Views/marketplace_income.php'); ?>
                <?php elseif ($globalTab === 'marketing'): ?>
                    <?php require (dirname(__DIR__, 4) . '/app/Modules/Marketplace/Views/marketplace_global_marketing.php'); ?>
                <?php elseif ($globalTab === 'analitik'): ?>
                    <?php require (dirname(__DIR__, 4) . '/app/Modules/Marketplace/Views/marketplace_global_analytics.php'); ?>
                <?php elseif ($globalTab === 'produk'): ?>
                    <?php require (dirname(__DIR__, 4) . '/app/Modules/Marketplace/Views/marketplace_global_products.php'); ?>
                <?php else: ?>
                    <section class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden"><div class="p-5 border-b border-slate-100"><h2 class="font-extrabold text-slate-900"><?= $globalTab === 'pemasukan' ? 'Pendapatan Global' : 'Transaksi Global' ?></h2><p class="text-xs text-slate-500 mt-1"><?= $globalTab === 'pemasukan' ? 'Pendapatan bukan jurnal baru; status dana dan payout ditampilkan sebagai state yang berbeda.' : 'Setiap baris menyertakan identitas toko dan platform.' ?></p></div><div class="overflow-x-auto"><table class="w-full text-xs jl-table"><thead class="bg-slate-50 text-slate-500 uppercase"><tr><th class="p-3 text-left">Pesanan</th><th class="p-3 text-left">Toko / platform</th><th class="p-3 text-left">Tanggal</th><th class="p-3 text-right">Penjualan</th><th class="p-3 text-right">Biaya</th><th class="p-3 text-right">Untuk Dibayar</th><th class="p-3 text-right">HPP Produk</th><th class="p-3 text-right">Laba Bersih</th><th class="p-3 text-left">Status</th></tr></thead><tbody class="divide-y divide-slate-100"><?php foreach ($globalData['transactions'] as $row): ?><tr><td class="p-3 font-mono font-bold"><?= htmlspecialchars($row['id']) ?><span class="block text-[10px] text-slate-400 mt-1"><?= htmlspecialchars($row['product_summary'] ?: 'Item belum tersedia') ?></span></td><td class="p-3 font-bold"><?= htmlspecialchars($row['store_name']) ?><span class="block text-[10px] text-slate-400"><?= htmlspecialchars($row['platform']) ?></span></td><td class="p-3 whitespace-nowrap"><?= htmlspecialchars(substr((string)$row['order_date'], 0, 10)) ?></td><td class="p-3 text-right"><?= formatRp($row['gross_sales'] - $row['seller_discount']) ?></td><td class="p-3 text-right text-rose-600"><?= $row['total_fees']===null?'Menunggu data keuangan/HPP':formatRp($row['total_fees']) ?></td><td class="p-3 text-right text-emerald-600"><?= $row['net_settlement_amount']===null?'Menunggu data keuangan/HPP':formatRp($row['net_settlement_amount']) ?><span class="block text-[10px] text-slate-400"><?= $globalTab === 'pemasukan' ? htmlspecialchars($row['escrow_status'] ?: 'Status dana belum tersedia') : '' ?></span></td><td class="p-3 text-right text-blue-600"><?= $row['total_cogs_hpp']===null?'Menunggu data keuangan/HPP':formatRp($row['total_cogs_hpp']) ?></td><td class="p-3 text-right font-bold <?= $row['profit'] < 0 ? 'text-rose-600' : 'text-emerald-600' ?>"><?= $row['profit']===null?'Menunggu data keuangan/HPP':formatRp($row['profit']) ?></td><td class="p-3"><span class="rounded-full bg-slate-100 border border-slate-200 px-2 py-1 text-[10px] font-bold"><?= htmlspecialchars($row['order_status']) ?></span><?php if ($globalTab === 'pemasukan'): ?><span class="block text-[10px] text-slate-400 mt-1"><?= $row['payout_batch_id'] ? 'Payout: ' . htmlspecialchars($row['payout_batch_id']) : 'Belum ada payout' ?></span><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?php if (($globalData['pagination']['total_pages'] ?? 1) > 1): ?><div class="flex items-center justify-between border-t border-slate-100 p-4 text-xs"><span class="text-slate-500">Halaman <?= (int)$globalData['pagination']['page'] ?> dari <?= (int)$globalData['pagination']['total_pages'] ?> (<?= number_format((int)$globalData['pagination']['total'], 0, ',', '.') ?> pesanan)</span><div class="flex gap-2"><?php if ($globalData['pagination']['page'] > 1): ?><a class="px-3 py-1.5 rounded-lg border border-slate-200 font-bold" href="marketplace.php?<?= $query(['page' => $globalData['pagination']['page'] - 1]) ?>">Sebelumnya</a><?php endif; ?><?php if ($globalData['pagination']['page'] < $globalData['pagination']['total_pages']): ?><a class="px-3 py-1.5 rounded-lg bg-brand-600 text-white font-bold" href="marketplace.php?<?= $query(['page' => $globalData['pagination']['page'] + 1]) ?>">Berikutnya</a><?php endif; ?></div></div><?php endif; ?></section>
                <?php endif; ?>
                <?php if (!$globalData['transactions'] && $globalTab === 'transaksi'): ?><div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">Tidak ada transaksi pada cakupan filter ini.</div><?php endif; ?>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
</div>
<div id="connect-store-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/50 p-4" role="dialog" aria-modal="true" aria-labelledby="connect-store-title">
    <div class="w-full max-w-2xl rounded-2xl bg-white shadow-2xl" role="document">
        <div class="flex items-start justify-between border-b border-slate-100 p-5">
            <div><p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-600">Otorisasi resmi</p><h2 id="connect-store-title" class="mt-1 text-lg font-extrabold text-slate-900">Hubungkan Toko</h2><p class="mt-1 text-xs text-slate-500">Pilih platform. Anda akan diarahkan ke halaman resmi untuk menyetujui akses seller.</p></div>
            <button type="button" data-close-connect-modal class="rounded-lg px-2 py-1 text-xl text-slate-400 hover:bg-slate-100" aria-label="Tutup modal">&times;</button>
        </div>
        <div id="connect-store-modal-message" class="hidden px-5 pt-4" role="status" aria-live="polite"></div>
        <div id="connect-store-platforms" class="grid gap-3 p-5 md:grid-cols-2">
            <?php foreach (($oauthConfig['platforms'] ?? []) as $platform => $platformInfo): ?>
                <div class="rounded-2xl border border-slate-200 p-4 <?= $platformInfo['ready'] ? 'bg-white' : 'bg-slate-50' ?>">
                    <div class="flex items-center justify-between gap-3"><div><h3 class="font-extrabold text-slate-900"><?= htmlspecialchars($platform) ?></h3><p class="mt-1 text-[11px] leading-5 text-slate-500"><?= htmlspecialchars($platformInfo['authorization']) ?></p></div><span class="rounded-full border px-2 py-1 text-[10px] font-bold <?= $platformInfo['ready'] ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-800' ?>"><?= htmlspecialchars($platformInfo['label']) ?></span></div>
                    <?php if ($platformInfo['ready']): ?>
                        <button type="button" data-start-oauth="<?= htmlspecialchars($platform) ?>" class="mt-4 w-full rounded-xl bg-brand-600 px-3 py-2.5 text-xs font-extrabold text-white hover:bg-brand-700">Hubungkan <?= htmlspecialchars($platform) ?></button>
                    <?php else: ?>
                        <p class="mt-4 text-[11px] leading-5 text-slate-600"><?= !empty($oauthConfig['is_admin']) ? 'Administrator perlu melengkapi environment aplikasi sebelum otorisasi dibuka.' : 'Konfigurasi aplikasi belum lengkap. Hubungi administrator untuk melanjutkan.' ?></p>
                        <?php if (!empty($oauthConfig['is_admin'])): ?><a href="marketplace.php?scope=global&tab=pengaturan&peng_sub=integrasi#integration-settings" class="mt-3 inline-flex text-[11px] font-bold text-brand-700 hover:underline">Buka pengaturan integrasi</a><?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <div id="connect-store-pending" class="hidden p-5 pt-0"></div>
        <div class="border-t border-slate-100 px-5 py-4 text-[11px] text-slate-500">Rahasia aplikasi dikelola administrator di server. Jago Laba tidak meminta App Secret atau access token dari penjual.</div>
    </div>
</div>
<script>
(() => {
    const modal = document.getElementById('connect-store-modal');
    const message = document.getElementById('connect-store-modal-message');
    const pageMessage = document.getElementById('integration-action-message');
    const pendingBox = document.getElementById('connect-store-pending');
    const csrf = <?= json_encode(Workspace::csrf(), JSON_UNESCAPED_SLASHES) ?>;
    document.querySelectorAll('.global-logout-trigger').forEach(button => button.addEventListener('click', async () => {
        button.disabled = true;
        try {
            const response = await fetch('api/logout.php', {method:'POST', credentials:'same-origin', headers:{'X-CSRF-Token':csrf}});
            if (!response.ok) throw new Error('Gagal keluar. Muat ulang lalu coba lagi.');
            sessionStorage.clear();
            location.assign('index.php');
        } catch (error) { button.disabled = false; alert(error.message); }
    }));
    const api = 'api/marketplace-oauth.php';
    const showNotice = (text, tone = 'warning') => {
        const cls = tone === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-800';
        const target = modal.classList.contains('hidden') && pageMessage ? pageMessage : message;
        target.className = 'rounded-xl border px-3 py-2 text-xs ' + cls;
        target.textContent = text;
        target.classList.remove('hidden');
        if (target === pageMessage && tone !== 'success') target.scrollIntoView({behavior: 'smooth', block: 'nearest'});
    };
    const openModal = () => { modal.classList.remove('hidden'); modal.classList.add('flex'); document.querySelector('[data-close-connect-modal]')?.focus(); };
    const closeModal = () => { modal.classList.add('hidden'); modal.classList.remove('flex'); };
    const request = async (action, data = {}, method = 'POST') => {
        const options = {method, credentials: 'same-origin', headers: {'Accept': 'application/json'}};
        if (method === 'POST') { options.headers['X-CSRF-Token'] = csrf; options.body = new URLSearchParams({action, ...data}); }
        const response = await fetch(api + (method === 'GET' ? '?action=' + encodeURIComponent(action) + '&' + new URLSearchParams(data) : ''), options);
        const payload = await response.json().catch(() => ({}));
        if (!response.ok || !payload.success) throw new Error(payload.message || 'Permintaan belum dapat diproses.');
        return payload.data;
    };
    const start = async (platform) => {
        const callbackUrls = <?= json_encode($oauthConfig['callback_urls'] ?? [], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
        if (callbackUrls[platform]) {
            const callback = new URL(callbackUrls[platform]);
            if (callback.origin !== window.location.origin) {
                // Establish a fresh session on the callback origin before creating OAuth state.
                const publicPage = new URL('../marketplace.php?scope=global&tab=projects', callback);
                window.location.assign(publicPage.href);
                return;
            }
        }
        showNotice('Menyiapkan tautan otorisasi resmi...', 'success');
        try { const result = await request('start', {platform, return_path: 'marketplace.php?scope=global&tab=pengaturan&peng_sub=integrasi'}); window.location.assign(result.authorization_url); }
        catch (e) { showNotice(e.message); }
    };
    const loadPending = async (pendingId) => {
        openModal();
        document.getElementById('connect-store-platforms').classList.add('hidden');
        pendingBox.classList.remove('hidden');
        pendingBox.textContent = 'Memuat daftar toko terotorisasi...';
        try {
            const data = await request('pending', {pending_id: pendingId}, 'GET');
            pendingBox.innerHTML = '<h3 class="font-extrabold text-slate-900">Pilih toko ' + data.platform + '</h3><p class="mt-1 text-xs text-slate-500">Beberapa toko dikembalikan oleh platform. Pilihan ini hanya dapat digunakan sekali.</p><div class="mt-3 grid gap-2">' + data.shops.map(shop => '<button type="button" data-select-shop="' + String(shop.id).replace(/"/g, '&quot;') + '" class="rounded-xl border border-slate-200 p-3 text-left text-xs hover:border-brand-500 hover:bg-brand-50"><b>' + String(shop.name).replace(/</g, '&lt;') + '</b><span class="block text-[11px] text-slate-500">ID toko: ' + String(shop.id).replace(/</g, '&lt;') + '</span></button>').join('') + '</div>';
            pendingBox.querySelectorAll('[data-select-shop]').forEach(button => button.addEventListener('click', async () => {
                button.disabled = true;
                try { await request('select', {pending_id: pendingId, external_store_id: button.dataset.selectShop}); window.location.assign('marketplace.php?scope=global&tab=pengaturan&peng_sub=integrasi&oauth_status=success'); }
                catch (e) { button.disabled = false; showNotice(e.message); }
            }));
        } catch (e) { showNotice(e.message); }
    };
    document.getElementById('open-connect-store-modal')?.addEventListener('click', () => location.assign('marketplace.php?scope=global&tab=pengaturan&peng_sub=tambah_toko'));
    document.querySelector('[data-close-connect-modal]')?.addEventListener('click', closeModal);
    modal?.addEventListener('click', e => { if (e.target === modal) closeModal(); });
    document.querySelectorAll('[data-start-oauth]').forEach(button => button.addEventListener('click', () => start(button.dataset.startOauth)));
    document.querySelectorAll('[data-copy-callback]').forEach(button => button.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(button.dataset.callback);
            const label = button.textContent;
            button.textContent = 'Tersalin';
            window.setTimeout(() => { button.textContent = label; }, 1800);
        } catch (e) { showNotice('URL belum dapat disalin otomatis. Pilih dan salin URL callback secara manual.'); }
    }));
    document.querySelectorAll('.partner-test-form').forEach(form => form.addEventListener('submit', async event => {
        event.preventDefault();
        const button = form.querySelector('[type="submit"]');
        const result = form.querySelector('.partner-test-result');
        const badge = form.closest('article').querySelector('[data-test-badge]');
        button.disabled = true;
        button.textContent = 'Menghubungi API partner...';
        result.className = 'partner-test-result rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs leading-5 text-slate-700';
        result.textContent = 'Menyimpan pengaturan dan menguji API...';
        try {
            const response = await fetch('api/marketplace-partner-settings.php', {
                method: 'POST', credentials: 'same-origin',
                headers: {'Accept': 'application/json', 'X-CSRF-Token': csrf},
                body: new URLSearchParams(new FormData(form)),
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok || !payload.success) throw new Error(payload.message || 'Pengaturan belum dapat disimpan.');
            const passed = !!payload.data.test.ok;
            result.className = 'partner-test-result rounded-xl border px-3 py-2.5 text-xs leading-5 ' + (passed ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-900');
            result.textContent = 'Kredensial tersimpan terenkripsi. ' + payload.data.test.message;
            badge.className = 'rounded-full px-3 py-1 text-[10px] font-extrabold ' + (passed ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-800');
            badge.textContent = passed ? 'Uji berhasil' : 'Uji belum berhasil';
            form.querySelectorAll('input[type="password"]').forEach(input => { input.value = ''; if (input.name !== 'test_access_token') input.required = false; });
        } catch (error) {
            result.className = 'partner-test-result rounded-xl border border-rose-200 bg-rose-50 px-3 py-2.5 text-xs leading-5 text-rose-800';
            result.textContent = error.message;
        } finally {
            button.disabled = false;
            button.textContent = 'Simpan & uji API';
        }
    }));
    document.querySelectorAll('[data-oauth-action]').forEach(button => button.addEventListener('click', async () => {
        const action = button.dataset.oauthAction;
        if(action==='sync') {button.disabled=true;try{await window.jlStartBackgroundSync(button.dataset.storeId);}catch(error){showNotice(error.message);}finally{button.disabled=false;}return;}
        if (action === 'reconnect') return location.assign('marketplace.php?scope=global&tab=pengaturan&peng_sub=tambah_toko');
        if (action === 'disconnect' && !window.confirm('Putuskan koneksi internal toko ini? Histori transaksi tidak akan dihapus.')) return;
        button.disabled = true;
        try { const result = await request(action, {store_id: button.dataset.storeId}); showNotice(result.message || 'Permintaan berhasil diproses.', 'success'); window.setTimeout(() => window.location.reload(), 700); }
        catch (e) { button.disabled = false; showNotice(e.message); }
    }));
    const pendingId = new URLSearchParams(window.location.search).get('pending');
    if (pendingId && <?= json_encode($oauthStatus === 'choose') ?>) loadPending(pendingId);
})();
</script>
</body>
</html>
