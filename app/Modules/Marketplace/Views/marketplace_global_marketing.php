<?php
$marketingMetrics = [
    'ad_spend' => ['Biaya Iklan', 'money', 'Belanja iklan tercatat sebelum PPN tambahan.'],
    'gross_revenue' => ['Pendapatan', 'money', 'Pendapatan yang diatribusikan pada laporan iklan, bukan seluruh penjualan toko.'],
    'gross_roas' => ['Gross ROAS', 'ratio', 'Pendapatan ÷ biaya iklan.'],
    'net_roas' => ['Net ROAS', 'ratio', '(Pendapatan − biaya platform − komisi affiliate) ÷ biaya iklan.'],
    'actual_roas' => ['Actual ROAS', 'ratio', '(Pendapatan − biaya platform − komisi affiliate − total HPP) ÷ biaya iklan.'],
    'min_roas' => ['Min. ROAS', 'ratio', 'Pendapatan ÷ laba sebelum iklan. Titik impas; tidak tersedia bila laba sebelum iklan nol atau negatif.'],
    'orders_count' => ['Pesanan', 'count', 'Pesanan yang diatribusikan pada iklan. Qty adalah jumlah unit terjual.'],
    'cost_per_order' => ['Biaya/Pesanan', 'money', 'Biaya iklan ÷ jumlah pesanan.'],
    'unit_cogs_hpp' => ['HPP/Unit', 'money', 'HPP unit yang tercatat pada laporan produk Marketplace.'],
    'margin_laba_pct' => ['Margin Laba', 'percent', '(Pendapatan − total HPP) ÷ pendapatan × 100%.'],
    'profit_riil' => ['Profit Riil', 'money', 'Pendapatan − total HPP − biaya platform − komisi affiliate; sebelum iklan.'],
    'platform_fee' => ['Biaya Platform', 'money', 'Biaya platform yang tercatat pada laporan. Komisi affiliate ditampilkan terpisah pada detail.'],
    'net_profit' => ['Net Profit', 'money', 'Profit riil − biaya iklan.'],
];
$marketingFormat = static function ($value, string $type): string {
    return match ($type) {
        'money' => formatRp($value),
        'ratio' => number_format((float)$value, 2, ',', '.') . 'x',
        'percent' => number_format((float)$value, 1, ',', '.') . '%',
        default => number_format((float)$value, 0, ',', '.'),
    };
};
?>
<section id="global-marketing" class="space-y-4">
    <details class="rounded-2xl border border-slate-200 bg-white p-4 text-xs">
        <summary class="cursor-pointer font-bold text-brand-700">Impor rincian iklan produk Shopee / TikTok</summary>
        <p class="mt-3 text-slate-500 leading-6">Siapkan laporan produk marketplace dengan <a href="assets/marketplace/product-ad-report-template.csv" download class="text-blue-600 underline">template CSV</a>. Isi angka tanpa Rp/pemisah ribuan, desimal memakai titik, tanggal YYYY-MM-DD. Gunakan biaya iklan sebelum PPN tambahan dan komisi affiliate terpisah dari biaya platform. Impor ulang produk/campaign pada periode yang sama mengganti nilai sebelumnya; periode tumpang tindih ditolak.</p>
        <form id="import-product-ads" class="flex flex-wrap items-end gap-3 mt-3" enctype="multipart/form-data">
            <label class="grid gap-1">Toko<select name="store_id" required class="border rounded-lg p-2"><option value="">Pilih toko tujuan</option><?php foreach ($stores as $store): ?><?php if (in_array($store['platform'], ['Shopee', 'TikTok Shop'], true)): ?><option value="<?= htmlspecialchars($store['id']) ?>"><?= htmlspecialchars($store['store_name'] . ' · ' . $store['platform']) ?></option><?php endif; ?><?php endforeach; ?></select></label>
            <label class="grid gap-1">Laporan CSV (maks. 2 MB)<input type="file" name="report" accept=".csv,text/csv" required class="border rounded-lg p-2"></label>
            <button class="rounded-lg bg-brand-600 text-white px-4 py-2 font-bold" type="submit">Impor laporan</button>
        </form>
        <p id="import-product-ads-status" class="mt-3 text-slate-600" role="status"></p>
    </details>
    <div class="grid grid-cols-2 xl:grid-cols-4 gap-3">
    <?php foreach (['biaya_iklan' => ['Biaya Iklan', 'money'], 'pendapatan' => ['Pendapatan', 'money'], 'roas' => ['Gross ROAS', 'ratio'], 'cost_per_order' => ['Biaya per Pesanan', 'money']] as $key => [$label, $type]): ?>
        <div class="bg-white border border-slate-200 rounded-2xl p-5"><p class="text-xs text-slate-500"><?= $label ?></p><p class="mt-2 text-xl font-extrabold"><?= $marketingFormat($marketingData['kpi'][$key], $type) ?></p></div>
    <?php endforeach; ?>
    </div>
    <div class="flex flex-wrap gap-2 text-xs">
    <?php foreach (['' => 'Semua Produk', 'roas_rendah' => 'ROAS Rendah', 'tanpa_hpp' => 'Tanpa HPP', 'rugi' => 'Rugi'] as $value => $label): ?>
        <a href="?<?= $query(['mkt_filter' => $value ?: null, 'page' => 1]) ?>" <?= $marketingFilter === $value ? 'aria-current="true"' : '' ?> class="rounded-xl border px-4 py-2 font-bold <?= $marketingFilter === $value ? 'bg-brand-50 text-brand-700 border-brand-500' : 'bg-white border-slate-200' ?>"><?= $label ?></a>
    <?php endforeach; ?>
    </div>
    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs leading-6 text-amber-900">
        Laporan produk yang tersimpan di database; konektor API iklan Shopee/TikTok belum aktif. KPI mengikuti produk dan filter pada tabel ini. Iklan grup/toko tidak dialokasikan ke produk. Filter tanggal hanya menyertakan laporan yang seluruh periodenya berada dalam rentang pilihan; angka tidak dibagi rata per hari. Laba dengan HPP kosong masih bersifat sementara.
    </div>
    <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500"><span>Menampilkan <?= count($marketingData['products']) ?> laporan produk · Klik Detail untuk melihat sumber perhitungan</span><a href="?<?= $query(['mkt_filter' => $marketingFilter ?: null]) ?>" class="rounded-lg border px-3 py-2 bg-white font-bold">Perbarui data tersimpan</a></div>
    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
        <div class="overflow-auto max-h-[720px]" tabindex="0" aria-label="Tabel performa iklan produk, dapat digulir horizontal">
        <table class="w-full text-xs whitespace-nowrap jl-table">
            <thead class="bg-slate-50 text-slate-500 text-[10px] uppercase"><tr><th class="p-3 text-left">Detail Produk</th><?php foreach ($marketingMetrics as [$label, $type, $formula]): ?><th class="p-3 text-right" title="<?= htmlspecialchars($formula) ?>"><?= $label ?> <span aria-hidden="true">ⓘ</span></th><?php endforeach; ?></tr></thead>
            <tbody class="divide-y divide-slate-100">
            <?php foreach ($marketingData['products'] as $product): ?>
            <tr class="hover:bg-slate-50 align-top">
                <td class="p-3 min-w-[280px] max-w-[340px] whitespace-normal">
                    <div class="flex items-start gap-3">
                        <?php if (preg_match('~^https?://~i', $product['image_url'])): ?><img src="<?= htmlspecialchars($product['image_url']) ?>" alt="" loading="lazy" class="w-10 h-10 object-cover rounded-lg bg-slate-100"><?php else: ?><span class="w-10 h-10 shrink-0 rounded-lg bg-slate-100 flex items-center justify-center text-slate-400"><i class="fa-solid fa-box" aria-hidden="true"></i></span><?php endif; ?>
                        <div><p class="font-bold"><?= htmlspecialchars($product['product_name']) ?></p><p class="text-[10px] text-slate-400 mt-1">ID: <?= htmlspecialchars($product['marketplace_item_id']) ?> · SKU: <?= htmlspecialchars($product['sku']) ?></p><p class="text-[10px] text-blue-600 mt-1"><?= htmlspecialchars($product['platform']) ?> · <?= htmlspecialchars($product['store_id']) ?></p>
                        <details class="mt-2"><summary class="cursor-pointer text-brand-700 font-bold">Detail laporan</summary><dl class="text-[11px] space-y-2 mt-2 text-slate-500"><div><dt>Periode</dt><dd><?= htmlspecialchars($product['period_start']) ?> — <?= htmlspecialchars($product['period_end']) ?></dd></div><div><dt>Waktu pada catatan sumber</dt><dd><?= htmlspecialchars($product['last_synced_at'] ?: 'Belum tersedia') ?></dd></div><div><dt>Total HPP (HPP × qty)</dt><dd><?= formatRp($product['total_cogs_hpp']) ?></dd></div><div><dt>Komisi affiliate</dt><dd><?= formatRp($product['affiliate_fee']) ?></dd></div><div><dt>Campaign</dt><dd><?= htmlspecialchars($product['campaign_name'] ?: '—') ?></dd></div></dl></details></div>
                    </div>
                </td>
                <?php foreach ($marketingMetrics as $key => [$label, $type, $formula]):
                    $unavailable = (in_array($key, ['gross_roas','net_roas','actual_roas'], true) && $product['ad_spend'] <= 0)
                        || ($key === 'cost_per_order' && $product['orders_count'] <= 0)
                        || ($key === 'min_roas' && $product['profit_riil'] <= 0);
                    $color = $key === 'ad_spend' || ($key === 'net_profit' && $product[$key] < 0) ? 'text-rose-600' : ($key === 'gross_revenue' ? 'text-blue-600' : 'text-slate-700');
                ?><td class="p-3 text-right <?= $color ?>" title="<?= htmlspecialchars($formula) ?>"><span class="font-bold"><?= $unavailable ? '—' : $marketingFormat($product[$key], $type) ?></span>
                    <?php if ($key === 'orders_count'): ?><span class="block text-[10px] text-slate-400 mt-1">Qty: <?= (int)$product['qty_sold'] ?></span><?php endif; ?>
                    <?php if ($key === 'unit_cogs_hpp' && $product[$key] <= 0): ?><span class="block text-[10px] text-amber-600 mt-1">HPP belum diisi</span><?php endif; ?>
                    <?php if ($key === 'min_roas' && !$unavailable): ?><span class="block text-[10px] mt-1 <?= $product['is_aman'] ? 'text-emerald-600' : 'text-rose-600' ?>"><?= $product['is_aman'] ? '✓ impas / untung' : 'Di bawah impas' ?></span><?php endif; ?>
                    <?php if (in_array($key, ['profit_riil', 'platform_fee', 'net_profit'], true)): ?><span class="block text-[10px] mt-1"><?= $marketingFormat($product[match($key) { 'profit_riil' => 'profit_riil_pct', 'platform_fee' => 'platform_fee_pct', default => 'net_profit_pct' }], 'percent') ?></span><?php endif; ?>
                </td><?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
            <?php if (!$marketingData['products']): ?><tr><td colspan="14" class="p-12 text-center whitespace-normal text-slate-500">Belum ada laporan iklan produk untuk toko dan periode ini. Data membutuhkan laporan produk tersimpan atau konektor iklan resmi.</td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
    <details class="rounded-xl border border-slate-200 bg-white p-4 text-xs"><summary class="cursor-pointer font-bold">Panduan perhitungan</summary><dl class="grid sm:grid-cols-2 gap-4 mt-4"><?php foreach ($marketingMetrics as [$label, $type, $formula]): ?><div><dt class="font-bold"><?= $label ?></dt><dd class="text-slate-500 mt-1 leading-5"><?= htmlspecialchars($formula) ?></dd></div><?php endforeach; ?></dl></details>
</section>
<script>
document.getElementById('import-product-ads').addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.currentTarget;
    const button = form.querySelector('button');
    const status = document.getElementById('import-product-ads-status');
    const data = new FormData(form);
    data.set('action', 'import_product_ad_report');
    button.disabled = true;
    status.textContent = 'Mengimpor dan memeriksa laporan…';
    try {
        const response = await fetch('api/marketplace-ajax.php', {method: 'POST', credentials: 'same-origin', headers: {'X-CSRF-Token': <?= json_encode(Workspace::csrf()) ?>}, body: data});
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || 'Impor gagal.');
        status.textContent = result.message;
        const link = document.createElement('a');
        link.href = location.href;
        link.className = 'text-blue-600 underline ml-2';
        link.textContent = 'Tampilkan hasil';
        status.append(link);
    } catch (error) { status.textContent = error.message; }
    finally { button.disabled = false; }
});
</script>
