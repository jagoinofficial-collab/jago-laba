<?php
declare(strict_types=1);
$autoStates=isset($autoSyncService)?$autoSyncService->states($store['id']):[];
$schedulerAvailable=isset($autoSyncService)&&$autoSyncService->available();
$modes=[
    'returns'=>['title'=>'Pengembalian Barang & Dana','schedule'=>'Setiap 15 menit','desc'=>'Pembaruan kasus dari Return/Refund API TikTok; memerlukan izin seller.return_refund.basic.','is_new'=>false,'icon'=>'fa-rotate-left'],
    'status'=>['title'=>'Update Status Transaksi Otomatis','schedule'=>'Setiap 5 menit','desc'=>'Memperbarui status pesanan dari marketplace ke Jago Laba secara otomatis setiap 5 menit.','is_new'=>false,'icon'=>'fa-arrows-rotate'],
    'new'=>['title'=>'Realtime Transaksi Baru','schedule'=>'Setiap 1 menit','desc'=>'Menarik transaksi baru secara real-time dari tanggal 1 bulan integrasi tanpa perlu muat ulang halaman.','is_new'=>true,'icon'=>'fa-bolt'],
    'unpaid'=>['title'=>'Sinkron Pesanan Belum Dibayar','schedule'=>'Setiap 1 menit','desc'=>'Menarik pesanan status UNPAID secara berkala untuk pemantauan checkout pembeli.','is_new'=>false,'icon'=>'fa-clock-rotate-left'],
    'finance'=>['title'=>'Sinkronisasi Rincian Pendapatan','schedule'=>'Setiap 5 menit','desc'=>'Estimasi dan settlement resmi platform; membutuhkan izin Finance API.','is_new'=>false,'icon'=>'fa-wallet'],
];
?>
<div class="space-y-1.5 pt-1" data-auto-store="<?= htmlspecialchars($store['id'],ENT_QUOTES) ?>" aria-label="Auto sync toko">
 <?php foreach($modes as $mode=>$cfg):
  if(in_array($mode,['finance','returns'],true)&&$platform!=='TikTok Shop')continue;
  $state=$autoStates[$mode]??[];
  $enabled=!empty($state['enabled']);
 ?>
 <div class="flex items-center justify-between gap-3 py-2 border-b border-slate-100 last:border-b-0">
  <div class="flex flex-wrap items-center gap-2 min-w-0">
   <span class="w-6 h-6 rounded-md bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0 text-xs">
    <i class="fa-solid <?= $cfg['icon'] ?> <?= $enabled&&$mode==='status'?'fa-spin [animation-duration:8s]':'' ?>"></i>
   </span>
   <span class="text-xs font-semibold text-slate-800"><?= htmlspecialchars($cfg['title']) ?></span>
   <?php if($cfg['is_new']): ?>
    <span class="px-1.5 py-0.5 text-[9px] font-extrabold uppercase tracking-wider text-cyan-800 bg-cyan-50 border border-cyan-200 rounded">Baru</span>
   <?php endif; ?>
   <span data-auto-badge="<?= $mode ?>" class="px-2 py-0.5 rounded-full text-[10px] font-bold border transition <?= $enabled?'bg-emerald-50 text-emerald-700 border-emerald-200':'bg-slate-100 text-slate-500 border-slate-200' ?>">
    <?= $enabled?'Aktif':'Nonaktif' ?>
   </span>
   <span class="text-slate-400 cursor-help" title="<?= htmlspecialchars($cfg['desc']) ?>">
    <i class="fa-solid fa-circle-info text-[11px]"></i>
   </span>
   <?php if(!empty($cfg['schedule'])): ?>
    <span class="text-[11px] text-slate-400"><?= htmlspecialchars($cfg['schedule']) ?></span>
   <?php endif; ?>
  </div>
  <div class="shrink-0 flex items-center gap-2 pl-2">
   <button type="button" role="switch" aria-label="<?= htmlspecialchars($cfg['title']) ?>" aria-checked="<?= $enabled?'true':'false' ?>" data-auto-mode="<?= $mode ?>" <?= !$admin||(!$verified&&!$enabled)?'disabled':'' ?> class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-40 disabled:cursor-not-allowed <?= $enabled?'bg-emerald-500':'bg-slate-300' ?>" title="<?= $enabled?'Klik untuk menonaktifkan':'Klik untuk mengaktifkan' ?>">
    <span aria-hidden="true" class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-md ring-0 transition duration-200 ease-in-out <?= $enabled?'translate-x-5':'translate-x-0' ?>"></span>
   </button>
  </div>
 </div>
 <?php endforeach; ?>
 <p data-auto-notice role="status" aria-live="polite" class="text-[11px] text-slate-400 pt-0.5"></p>
</div>
