<?php $esc=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); ?>
<section class="rounded-2xl border border-slate-200 bg-white p-5 space-y-4" aria-labelledby="archived-stores-title">
 <div><h2 id="archived-stores-title" class="font-bold">Toko diarsipkan (<?= count($archivedStores) ?>)</h2><p class="text-xs text-slate-500 mt-2">Arsip menghentikan sinkronisasi dan mempertahankan riwayat. Pulihkan untuk menampilkan kembali; otorisasi baru diperlukan untuk sinkronisasi.</p></div>
 <?php if(!$archivedStores): ?><p class="text-sm text-slate-500">Belum ada toko diarsipkan.</p><?php endif; ?>
 <div class="divide-y"><?php foreach($archivedStores as $s): ?>
 <article class="flex flex-wrap items-center justify-between gap-4 py-4" data-archived-row="<?= $esc($s['id']) ?>">
  <div class="flex items-center gap-3"><?= jlMarketplacePlatformLogo($s['platform'],40) ?><div><h3 class="text-sm font-bold"><?= $esc($s['store_name']) ?></h3><p class="text-xs text-slate-500 mt-1"><?= $esc($s['platform']) ?> · <?= $esc($s['environment_label']??'Lingkungan belum terverifikasi') ?> · Diarsipkan <?= $esc($s['archived_at']) ?></p></div></div>
  <div class="flex flex-wrap gap-2"><?php if(!in_array($s['id'],array_column($deletionStatuses,'store_id'),true)): ?><button type="button" class="restore-archived-store rounded-xl border px-4 py-2 text-xs font-bold" data-store-id="<?= $esc($s['id']) ?>">Pulihkan</button><?php endif; ?><button type="button" class="delete-store rounded-xl border border-red-200 text-red-700 px-4 py-2 text-xs font-bold" data-store-id="<?= $esc($s['id']) ?>"><i class="fa-solid fa-trash-can mr-2" aria-hidden="true"></i>Hapus Integrasi &amp; Data Toko</button></div>
 </article><?php endforeach; ?></div>
</section>
<?php if($deletionStatuses): ?><section class="rounded-2xl border border-slate-200 bg-white p-5" aria-labelledby="deletion-status-title"><h2 id="deletion-status-title" class="font-bold">Status penghapusan</h2><div class="divide-y mt-3"><?php foreach($deletionStatuses as $job): ?><div class="flex flex-wrap justify-between gap-3 py-3 text-sm"><div><strong><?= $esc($job['store_id']) ?></strong><span class="text-slate-500"> · <?= $esc($job['platform']) ?></span><p class="text-xs text-slate-500"><?= $esc($job['requested_at']) ?></p></div><div><span><?= $esc(match($job['status']){'completed'=>'Penghapusan selesai','failed'=>'Pembersihan belum selesai',default=>'Sedang menghapus'}) ?></span><?php if($job['status']!=='completed'): ?><button type="button" class="delete-resume ml-3 rounded-lg border px-3 py-2 text-xs font-bold" data-store-id="<?= $esc($job['store_id']) ?>">Coba lagi</button><?php endif; ?></div></div><?php endforeach; ?></div></section><?php endif; ?>
<style>
#store-delete-dialog{border:0;border-radius:20px;padding:0;width:min(640px,calc(100vw - 24px));max-height:90vh;color:#17243b;box-shadow:0 24px 80px #10204044}#store-delete-dialog::backdrop{background:#10203888;backdrop-filter:blur(3px)}#store-delete-dialog .delete-body{padding:24px;overflow:auto;max-height:65vh}#store-delete-dialog header,#store-delete-dialog footer{padding:20px 24px;border-bottom:1px solid #e5eaf1;display:flex;gap:12px;justify-content:space-between;align-items:center}#store-delete-dialog footer{border-top:1px solid #e5eaf1;flex-wrap:wrap}#store-delete-dialog button{padding:10px 16px;border:1px solid #dce3ed;border-radius:10px}#store-delete-dialog button:disabled{opacity:.5;cursor:not-allowed}#delete-submit{background:#c92b38;color:white}#delete-confirm{width:100%;border:1px solid #cbd5e1;border-radius:10px;padding:12px;margin-top:8px}#delete-counts{margin:16px 0;padding:12px;background:#f7f9fc;border-radius:12px;font-size:12px;max-height:180px;overflow:auto}#delete-counts li{display:flex;justify-content:space-between;padding:5px}#delete-state{margin:12px 0;color:#9c2632;font-size:13px}
</style>
<dialog id="store-delete-dialog" aria-labelledby="delete-title">
 <header><h2 id="delete-title" class="font-bold text-lg"><i class="fa-solid fa-trash-can text-red-600 mr-2" aria-hidden="true"></i>Hapus Integrasi &amp; Data Toko</h2><button type="button" data-delete-close aria-label="Tutup">×</button></header>
 <div class="delete-body">
  <div class="flex items-center gap-3">
   <div id="delete-logo-container"></div>
   <div>
    <h3 id="delete-name" class="font-bold text-base text-slate-900"></h3>
    <p id="delete-platform" class="text-sm text-slate-500"></p>
   </div>
  </div>
  <p class="text-sm mt-4 text-slate-700">Data toko ini akan dihapus dari Jago Laba. Toko, produk, dan pesanan asli di marketplace tetap tersedia. Jika dihubungkan kembali, data akan diambil ulang sesuai izin dan ketersediaan API.</p>
  <ul id="delete-counts"></ul><p class="text-xs text-slate-500">Penghapusan penyimpanan aktif tidak otomatis menghapus backup lama. Tidak ada backup tambahan yang dibuat.</p>
  <div id="delete-state" role="status" aria-live="polite"></div><label class="block text-sm font-semibold mt-4 text-slate-800">Ketik nama toko persis untuk mengonfirmasi<input id="delete-confirm" autocomplete="off" placeholder="Nama toko persis"></label></div>
 <footer><button type="button" data-delete-close class="hover:bg-slate-50 transition cursor-pointer">Batal</button><button type="button" id="delete-submit" disabled class="transition cursor-pointer font-bold">Hapus Integrasi &amp; Data</button></footer>
</dialog>
<script src="assets/marketplace/js/marketplace-store-deletion.js?v=2" defer></script>
