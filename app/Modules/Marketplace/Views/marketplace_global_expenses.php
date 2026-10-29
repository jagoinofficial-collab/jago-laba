<?php
$expenseTotals = $globalData['expense_summary'];
$expensePages = max(1, (int)ceil($expenseTotals['count'] / $globalData['pagination']['per_page']));
$expenseDate = static function (string $date): string {
    $months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    $time = strtotime($date);
    return $time === false ? $date : date('d', $time) . ' ' . $months[(int)date('n', $time)-1] . ' ' . date('Y', $time);
};
?>
<section id="global-expenses" class="space-y-4">
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <?php foreach ([['Total Pengeluaran', $expenseTotals['total']], ['Advertising', $expenseTotals['advertising']], ['Pengeluaran Lainnya', $expenseTotals['total'] - $expenseTotals['advertising']]] as [$label, $value]): ?>
        <div class="bg-white rounded-2xl border border-slate-200 border-l-4 border-l-rose-500 p-5"><p class="text-xs text-slate-500"><?= $label ?></p><p class="text-xl font-extrabold text-rose-600 mt-2"><?= formatRp($value) ?></p><p class="text-xs text-slate-400 mt-1">Sesuai filter aktif</p></div>
        <?php endforeach; ?>
    </div>
    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex flex-wrap justify-between gap-3 items-center">
            <div><h2 class="font-extrabold">Pengeluaran Global</h2><p class="text-xs text-slate-500 mt-1"><?= (int)$expenseTotals['count'] ?> entri · Pengeluaran harian seluruh proyek/toko</p></div>
            <div class="flex items-center gap-3"><span id="expense-refresh-status" role="status" class="text-xs text-slate-500">Pembaruan database setiap 30 detik</span><button type="button" id="refresh-expenses" class="rounded-xl border px-3 py-2 text-xs font-bold">Perbarui</button></div>
        </div>
        <p class="px-5 py-3 bg-amber-50 text-amber-900 text-xs">Pengambilan otomatis biaya iklan TikTok/Shopee belum aktif. Data di bawah berasal dari pengeluaran yang sudah tercatat. PPN mengikuti catatan transaksi.</p>
        <div class="overflow-x-auto"><table class="w-full text-xs jl-table"><thead class="bg-slate-50 text-slate-500 uppercase"><tr><?php foreach (['Deskripsi','Tanggal','Kategori','Jumlah','Metode','Catatan','Aksi'] as $heading): ?><th class="p-3 text-left"><?= $heading ?></th><?php endforeach; ?></tr></thead>
        <tbody class="divide-y divide-slate-100">
        <?php foreach ($globalData['expenses'] as $row):
            $isAds = preg_match('/iklan|advertising/i', (string)$row['category']) === 1;
            $subCategory = $isAds ? match ($row['platform']) { 'TikTok Shop' => 'TikTok Ads Manager', 'Shopee' => 'Shopee Ads Manager', default => $row['platform'] . ' Ads' } : '—';
            $detailId = 'expense-detail-' . (int)$row['id'];
        ?>
            <tr class="hover:bg-slate-50"><td class="p-3 min-w-[260px]"><b><?= htmlspecialchars($row['description']) ?></b><span class="block text-slate-500 mt-1"><?= htmlspecialchars($subCategory) ?></span><span class="block text-blue-600">Proyek: <?= htmlspecialchars($row['store_name']) ?></span><span class="block text-slate-400">Vendor: <?= htmlspecialchars($row['vendor_name'] ?: '—') ?></span></td><td class="p-3 whitespace-nowrap"><?= htmlspecialchars($expenseDate($row['expense_date'])) ?></td><td class="p-3"><span class="inline-block rounded-full bg-purple-100 text-purple-700 px-2 py-1"><?= htmlspecialchars($row['category']) ?></span></td><td class="p-3 font-bold text-rose-600 whitespace-nowrap">-<?= formatRp($row['amount']) ?></td><td class="p-3"><?= htmlspecialchars($row['payment_method']) ?></td><td class="p-3"><?= htmlspecialchars($row['notes'] ?: '—') ?></td><td class="p-3"><button type="button" data-expense-detail="<?= $detailId ?>" aria-expanded="false" aria-controls="<?= $detailId ?>" class="rounded-lg border px-3 py-2 text-slate-600 hover:bg-slate-100" aria-label="Lihat detail <?= htmlspecialchars($row['description']) ?>"><i class="fa-regular fa-eye" aria-hidden="true"></i></button></td></tr>
            <tr id="<?= $detailId ?>" hidden><td colspan="7" class="p-4 bg-slate-50"><h3 class="font-bold mb-3">Detail Pengeluaran</h3><dl class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 rounded-xl bg-white border p-4">
            <?php foreach (['Deskripsi' => $row['description'], 'Kategori' => $row['category'], 'Subkategori' => $subCategory, 'Jumlah' => '-' . formatRp($row['amount']), 'Metode Pembayaran' => $row['payment_method'], 'Vendor' => $row['vendor_name'] ?: '—', 'Tanggal' => $expenseDate($row['expense_date']), 'Proyek' => $row['store_name'], 'Catatan' => $row['notes'] ?: '—'] as $label => $value): ?><div><dt class="text-slate-400 mb-1"><?= $label ?></dt><dd class="font-bold break-words <?= $label === 'Jumlah' ? 'text-rose-600' : '' ?>"><?= nl2br(htmlspecialchars($value)) ?></dd></div><?php endforeach; ?>
            </dl></td></tr>
        <?php endforeach; ?>
        <?php if (!$globalData['expenses']): ?><tr><td colspan="7" class="p-12 text-center text-slate-500">Belum ada pengeluaran untuk filter ini. Catat pengeluaran melalui menu Pengeluaran pada toko terkait.</td></tr><?php endif; ?>
        </tbody></table></div>
        <div class="p-4 flex flex-wrap justify-between items-center gap-3 border-t text-xs text-slate-500"><span>Halaman <?= (int)$globalFilters['page'] ?> dari <?= $expensePages ?></span><div class="flex gap-2"><?php if ($globalFilters['page'] > 1): ?><a class="border rounded-lg px-3 py-2" href="?<?= $query(['page' => $globalFilters['page'] - 1]) ?>">Sebelumnya</a><?php endif; ?><?php if ($globalFilters['page'] < $expensePages): ?><a class="border rounded-lg px-3 py-2" href="?<?= $query(['page' => $globalFilters['page'] + 1]) ?>">Berikutnya</a><?php endif; ?></div></div>
    </div>
</section>
<script src="assets/marketplace/js/global-expenses.js" defer></script>
