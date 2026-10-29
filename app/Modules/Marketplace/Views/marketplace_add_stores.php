<?php
declare(strict_types=1);
require_once __DIR__.'/marketplace_platform_logo.php';
require_once __DIR__.'/../Services/MarketplaceIntegrationHealth.php';
$integrationHealth=$integrationHealth??new MarketplaceIntegrationHealth(Database::pdo());
$maxQuotaPerPlatform=15;

$timeAgo=static function(?string $dt):string{
    if(!$dt)return 'Belum pernah disinkronkan';
    $ts=strtotime($dt);
    if($ts<=0)return 'Belum pernah disinkronkan';
    $diff=time()-$ts;
    if($diff<60)return 'Baru saja';
    if($diff<3600)return (int)floor($diff/60).' menit lalu';
    if($diff<86400)return (int)floor($diff/3600).' jam lalu';
    return (int)floor($diff/86400).' hari lalu';
};
?>
<link rel="stylesheet" href="assets/marketplace/css/marketplace-integration-hub.css">

<section class="space-y-6" aria-labelledby="toko-heading">
 <!-- Header Halaman Toko (Sesuai FinKami) -->
 <header class="flex flex-wrap items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
  <div>
   <div class="flex items-center gap-2">
    <h2 id="toko-heading" class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">Toko</h2>
    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-slate-100 text-slate-400 text-xs cursor-help" title="Hubungkan toko marketplace Anda untuk sinkronisasi pesanan otomatis dan pengelolaan satu pintu.">
     <i class="fa-solid fa-info text-[10px]"></i>
    </span>
   </div>
   <p class="mt-1 text-xs text-slate-500">Hubungkan toko marketplace Anda untuk sinkronisasi pesanan otomatis</p>
  </div>
  <div class="flex items-center gap-2">
   <a href="marketplace.php?tab=pengaturan&amp;peng_sub=integrasi" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 text-xs font-bold text-slate-700 transition">
    <i class="fa-solid fa-sliders text-slate-500"></i> Pengaturan Integrasi API
   </a>
  </div>
 </header>

 <?php foreach(['TikTok Shop','Shopee'] as $platform):
  $platformStores=array_values(array_filter($stores,static fn($s)=>$s['platform']===$platform));
  $ready=!empty($oauthConfig['platforms'][$platform]['ready']);
  $admin=!empty($oauthConfig['is_admin']);
  $isTikTok=$platform==='TikTok Shop';
  $connectedCount=count(array_filter($platformStores,static fn($s)=>!empty($s['is_connection_verified'])));
  $quotaText=$connectedCount.'/'.$maxQuotaPerPlatform.' toko';
 ?>
 <section class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden" aria-label="Platform <?= htmlspecialchars($platform) ?>">
  <!-- Header Kartu Platform (Sesuai FinKami) -->
  <div class="p-5 flex flex-wrap items-center justify-between gap-4 border-b border-slate-100">
   <div class="flex items-center gap-3.5 min-w-0">
    <div class="w-12 h-12 rounded-xl border border-slate-100 bg-white p-1.5 shadow-sm flex items-center justify-center shrink-0">
     <?= jlMarketplacePlatformLogo($platform,38) ?>
    </div>
    <div class="min-w-0">
     <div class="flex items-center gap-2">
      <h3 class="font-extrabold text-base text-slate-900"><?= htmlspecialchars($platform) ?></h3>
      <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600 border border-slate-200"><?= $quotaText ?></span>
     </div>
     <p class="text-xs text-slate-500 mt-0.5">Sinkronisasi pesanan dan data penjualan dari <?= htmlspecialchars($platform) ?></p>
    </div>
   </div>

   <!-- Tombol Aksi Platform (Hubungkan Toko & Hubungkan Ads) -->
   <div class="flex flex-wrap items-center gap-2.5">
    <?php if($admin&&$ready): ?>
     <button type="button" data-settings-connect="<?= htmlspecialchars($platform) ?>" data-new-tab="true" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl font-bold text-xs text-white transition shadow-sm cursor-pointer <?= $isTikTok?'bg-[#FE2C55] hover:bg-[#E11D48]':'bg-[#EE4D2D] hover:bg-[#D83E20]' ?>">
      <i class="fa-solid fa-plus text-[10px]"></i> Hubungkan <?= htmlspecialchars($platform) ?>
     </button>
    <?php else: ?>
     <a href="marketplace.php?tab=pengaturan&amp;peng_sub=integrasi" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 text-xs font-bold text-slate-700 transition">
      <i class="fa-solid fa-plug text-slate-400"></i> <?= $admin?'Lengkapi Konfigurasi API':'Minta Akses Admin' ?>
     </a>
    <?php endif; ?>

    <!-- Tombol Hubungkan Ads (Buka di Tab Baru) -->
    <button type="button" data-ads-connect="<?= $isTikTok?'TikTok':'Shopee' ?>" data-new-tab="true" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl font-bold text-xs text-white bg-blue-600 hover:bg-blue-700 transition shadow-sm cursor-pointer">
     <i class="fa-solid fa-plus text-[10px]"></i> Hubungkan <?= $isTikTok?'TikTok Ads':'Shopee Ads' ?>
    </button>
   </div>
  </div>

  <!-- Catatan Peringatan Akun Utama (FinKami Note) -->
  <div class="px-5 py-3 bg-slate-50/70 border-b border-slate-100 text-[11px] text-slate-600 flex items-center gap-2 leading-relaxed">
   <i class="fa-solid fa-circle-info text-slate-400 text-xs shrink-0"></i>
   <span>
    <strong>Catatan:</strong>
    <?= $isTikTok
        ?'Hanya akun utama TikTok dapat dihubungkan. Sub akun tidak dapat dihubungkan.'
        :'Hanya akun utama (Main Account) Shopee dan akun SIP/Shop Account (Shopee International Platform) dapat dihubungkan. Sub akun tidak dapat dihubungkan.' ?>
   </span>
  </div>

  <!-- Daftar Toko yang Terhubung -->
  <div class="divide-y divide-slate-100">
   <?php foreach($platformStores as $store):
    $verified=!empty($store['is_connection_verified']);
    try{
        $health=$integrationHealth->forStore($store['id']);
    }catch(Throwable $e){
        $health=[
            'store_id'=>$store['id'],
            'platform'=>$store['platform'],
            'connection_status'=>$verified?'verified':'unverified',
            'environment_label'=>'Live',
            'last_successful_sync_at'=>null,
            'counts'=>['products'=>0,'variants'=>0,'orders'=>0],
        ];
    }
    $extShopId=(string)($store['oauth_external_store_id']??$store['store_identifier']??'—');
    $orderCount=(int)($health['counts']['orders']??0);
    $syncTimeString=$timeAgo($health['last_successful_sync_at']??null).($orderCount>0?' ('.$orderCount.' pesanan)':'');
   ?>
   <article class="p-5 space-y-3.5" data-integration-store="<?= htmlspecialchars($store['id'],ENT_QUOTES) ?>" data-can-manage="<?= $admin?'1':'0' ?>">
    <!-- Baris 1: Judul Toko, Badge Aktif, dan Ikon Hapus di Ujung Kanan (FinKami) -->
    <div class="flex items-center justify-between gap-3">
     <div class="flex items-center gap-2.5 min-w-0">
      <h4 class="font-extrabold text-base text-slate-900 tracking-tight"><?= htmlspecialchars($store['store_name']) ?></h4>
      <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold <?= $verified?'bg-emerald-50 text-emerald-700 border border-emerald-200':'bg-rose-50 text-rose-700 border border-rose-200' ?>">
       <span class="w-1.5 h-1.5 rounded-full <?= $verified?'bg-emerald-500':'bg-rose-500' ?>"></span>
       <?= $verified?'Aktif':'Terputus' ?>
      </span>
      <span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold text-slate-500 bg-slate-100 border border-slate-200">
       <?= htmlspecialchars($health['environment_label']) ?>
      </span>
     </div>

     <!-- Ikon Trash Hapus Integrasi Toko di Ujung Kanan (Sesuai FinKami) -->
     <div class="flex items-center gap-2 shrink-0">
      <?php if($admin): ?>
       <button type="button" class="delete-store p-2 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition cursor-pointer" data-store-id="<?= htmlspecialchars($store['id'],ENT_QUOTES) ?>" title="Hapus Integrasi &amp; Data Toko" aria-label="Hapus Integrasi Toko">
        <i class="fa-regular fa-trash-can text-sm"></i>
       </button>
      <?php endif; ?>
     </div>
    </div>

    <!-- Baris 2: Waktu Sinkronisasi & ID Toko (FinKami) -->
    <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500">
     <span><i class="fa-regular fa-clock text-slate-400 mr-1"></i> <?= htmlspecialchars($syncTimeString) ?></span>
     <span class="text-slate-300">|</span>
     <span>ID: <strong class="text-slate-700 font-semibold"><?= htmlspecialchars($extShopId) ?></strong></span>
     <span class="text-slate-300">|</span>
     <span>Produk: <strong class="text-slate-700 font-semibold" data-count-products><?= $health['counts']['products'] ?></strong></span>
     <span class="text-slate-300">|</span>
     <span>Varian: <strong class="text-slate-700 font-semibold" data-count-variants><?= $health['counts']['variants'] ?></strong></span>
    </div>

    <!-- Baris 3: Proyek (Sesuai FinKami) -->
    <div class="flex items-center gap-2 pt-0.5 text-xs">
     <span class="text-slate-400 font-medium">Proyek:</span>
     <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100 border border-slate-200 font-bold text-slate-800">
      <i class="fa-solid fa-folder-tree text-slate-400 text-[10px]"></i>
      <?= htmlspecialchars($store['store_name']) ?> - <?= htmlspecialchars($isTikTok?'TikTok':'Shopee') ?>
     </span>
     <button type="button" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 font-semibold text-slate-600 transition shadow-sm text-xs cursor-pointer">
      + Tambah <i class="fa-solid fa-chevron-down text-[8px] text-slate-400"></i>
     </button>
    </div>

    <!-- Baris 4: Tiga Toggle Switch Fitur (FinKami: Update Status 5 Menit, Realtime Baru, Belum Dibayar) -->
    <div class="pt-1">
     <?php require __DIR__.'/marketplace_auto_sync_controls.php'; ?>
    </div>

    <!-- Baris 5: Sub-Box Akun Iklan Terhubung (Gaya FinKami di Bawah Kartu) -->
    <div class="mt-2.5 p-3.5 rounded-xl border border-slate-200/80 bg-slate-50/60">
     <div class="flex items-center justify-between text-xs pb-2 border-b border-slate-200/60">
      <div class="flex items-center gap-1.5 font-bold text-slate-700">
       <span class="text-slate-400 font-semibold">@</span>
       <span><?= $isTikTok?'Akun Iklan (GMV Max)':'Shopee Ads' ?></span>
      </div>
      <div>
       <?php if($isTikTok): ?>
        <a href="marketplace.php?store=<?= rawurlencode($store['id']) ?>&amp;tab=marketing&amp;mkt_sub=gmv_max" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:text-blue-700 font-bold text-xs inline-flex items-center gap-1">
         Ganti <i class="fa-solid fa-arrow-up-right-from-square text-[9px]"></i>
        </a>
       <?php else: ?>
        <a href="marketplace.php?store=<?= rawurlencode($store['id']) ?>&amp;tab=marketing&amp;mkt_sub=shopee" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:text-blue-700 font-bold text-xs inline-flex items-center gap-1">
         Kelola <i class="fa-solid fa-arrow-up-right-from-square text-[9px]"></i>
        </a>
       <?php endif; ?>
      </div>
     </div>
     <div class="pt-2.5 flex flex-wrap items-center justify-between gap-2">
      <div>
       <p class="font-extrabold text-xs text-slate-900"><?= htmlspecialchars($store['store_name']) ?><?= $isTikTok?' Official':'' ?></p>
       <p class="text-[11px] text-slate-500 mt-0.5">
        Shop ID: <?= htmlspecialchars($extShopId) ?> <?= $isTikTok?' &nbsp;|&nbsp; PPA: 15% &nbsp;|&nbsp; TikTok Ads Standby':' &nbsp;|&nbsp; Biaya &amp; ROAS Terhubung' ?>
       </p>
      </div>
      <div class="flex items-center gap-1 text-[11px] font-bold text-emerald-600">
       <i class="fa-solid fa-circle-check text-emerald-500"></i> Aktif
      </div>
     </div>
    </div>

    <!-- Baris 6: Tombol Aksi Sekunder (Buka Toko, Sinkronkan, Otorisasi Ulang, Arsipkan) -->
    <div class="flex flex-wrap items-center gap-2 pt-2 text-xs">
     <a href="marketplace.php?store=<?= rawurlencode($store['id']) ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 font-bold text-slate-700 transition shadow-sm">
      <i class="fa-solid fa-store text-slate-400 text-[11px]"></i> Buka Toko
     </a>
     <?php if($admin&&$health['connection_status']==='verified'): ?>
      <button type="button" data-integration-sync class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-brand-200 bg-brand-50 hover:bg-brand-100 font-bold text-brand-700 transition cursor-pointer shadow-sm">
       <i class="fa-solid fa-arrows-rotate text-[11px]"></i> Sinkronkan
      </button>
     <?php endif; ?>
     <?php if($admin&&$ready): ?>
      <button type="button" data-settings-connect="<?= htmlspecialchars($platform) ?>" data-new-tab="true" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 font-bold text-slate-700 transition cursor-pointer shadow-sm" title="Otorisasi Ulang">
       <i class="fa-solid fa-key text-slate-400 text-[11px]"></i> Otorisasi Ulang
      </button>
     <?php endif; ?>
     <?php if($admin&&!empty($canManageStoreArchive)): ?>
      <button type="button" data-store-archive="<?= htmlspecialchars($store['id'],ENT_QUOTES) ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 font-bold text-slate-600 transition cursor-pointer shadow-sm" title="Arsipkan Toko">
       <i class="fa-solid fa-box-archive text-slate-400 text-[11px]"></i> Arsipkan
      </button>
     <?php endif; ?>
    </div>

    <!-- Accordion Rincian Hak Akses & Kapabilitas API Marketplace -->
    <details class="text-xs text-slate-500 pt-1 group">
     <summary class="cursor-pointer font-bold text-slate-600 hover:text-slate-900 flex items-center gap-2 py-1 select-none">
      <i class="fa-solid fa-chevron-right text-[10px] text-slate-400 transition-transform group-open:rotate-90"></i>
      <span>Rincian Hak Akses &amp; Status Kapabilitas API Marketplace</span>
      <button class="ih-icon-button ml-auto" type="button" data-integration-refresh aria-label="Perbarui status akses">
       <i class="fa-solid fa-rotate" aria-hidden="true"></i>
      </button>
     </summary>
     <div class="mt-3 space-y-2">
      <div data-capability-grid class="ih-capability-grid"></div>
      <p data-integration-message class="ih-message text-xs" role="status" aria-live="polite"></p>
     </div>
    </details>

    <script type="application/json" data-integration-initial><?= json_encode($health,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR) ?></script>
   </article>
   <?php endforeach; ?>

   <?php if(!$platformStores): ?>
   <div class="text-center py-12 px-4 space-y-3">
    <div class="w-12 h-12 rounded-2xl bg-slate-50 text-slate-400 mx-auto flex items-center justify-center">
     <i class="fa-solid fa-store text-xl"></i>
    </div>
    <h4 class="font-bold text-sm text-slate-800">Belum ada toko <?= htmlspecialchars($platform) ?> terhubung</h4>
    <p class="text-xs text-slate-500 max-w-sm mx-auto leading-relaxed">
     Hubungkan toko Anda melalui otorisasi resmi untuk mulai menarik pesanan, katalog produk, dan laporan keuangan.
    </p>
    <?php if($admin&&$ready): ?>
     <div class="pt-2">
      <button type="button" data-settings-connect="<?= htmlspecialchars($platform) ?>" data-new-tab="true" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl font-bold text-xs text-white transition shadow-sm cursor-pointer <?= $isTikTok?'bg-[#FE2C55] hover:bg-[#E11D48]':'bg-[#EE4D2D] hover:bg-[#D83E20]' ?>">
       <i class="fa-solid fa-plus text-[10px]"></i> Hubungkan <?= htmlspecialchars($platform) ?> Sekarang
      </button>
     </div>
    <?php endif; ?>
   </div>
   <?php endif; ?>
  </div>
 </section>
 <?php endforeach; ?>
</section>

<!-- Dialog Konfirmasi Hapus Integrasi & Data Toko (Standar RBAC Jago Laba) -->
<?php if(!empty($canManageStoreArchive)): ?>
<style>
#store-delete-dialog{border:0;border-radius:20px;padding:0;width:min(640px,calc(100vw - 24px));max-height:90vh;color:#17243b;box-shadow:0 24px 80px #10204044}#store-delete-dialog::backdrop{background:#10203888;backdrop-filter:blur(3px)}#store-delete-dialog .delete-body{padding:24px;overflow:auto;max-height:65vh}#store-delete-dialog header,#store-delete-dialog footer{padding:20px 24px;border-bottom:1px solid #e5eaf1;display:flex;gap:12px;justify-content:space-between;align-items:center}#store-delete-dialog footer{border-top:1px solid #e5eaf1;flex-wrap:wrap}#store-delete-dialog button{padding:10px 16px;border:1px solid #dce3ed;border-radius:10px}#store-delete-dialog button:disabled{opacity:.5;cursor:not-allowed}#delete-submit{background:#c92b38;color:white}#delete-confirm{width:100%;border:1px solid #cbd5e1;border-radius:10px;padding:12px;margin-top:8px}#delete-counts{margin:16px 0;padding:12px;background:#f7f9fc;border-radius:12px;font-size:12px;max-height:180px;overflow:auto}#delete-counts li{display:flex;justify-content:space-between;padding:5px}#delete-state{margin:12px 0;color:#9c2632;font-size:13px}
</style>
<dialog id="store-delete-dialog" aria-labelledby="delete-title">
 <header><h2 id="delete-title" class="font-bold text-lg"><i class="fa-solid fa-trash-can text-red-600 mr-2" aria-hidden="true"></i>Hapus Integrasi &amp; Data Toko</h2><button type="button" data-delete-close aria-label="Tutup" class="text-xl cursor-pointer">×</button></header>
 <div class="delete-body">
  <div class="flex items-center gap-3">
   <div id="delete-logo-container"></div>
   <div>
    <h3 id="delete-name" class="font-bold text-base text-slate-900"></h3>
    <p id="delete-platform" class="text-sm text-slate-500"></p>
   </div>
  </div>
  <p class="text-sm mt-4 text-slate-700">Data toko ini akan dihapus permanen dari Jago Laba. Toko, produk, dan pesanan asli di marketplace tetap tersedia. Jika dihubungkan kembali, data akan diambil ulang sesuai izin dan ketersediaan API.</p>
  <ul id="delete-counts"></ul><p class="text-xs text-slate-500">Penghapusan penyimpanan aktif tidak otomatis menghapus backup lama. Tidak ada backup tambahan yang dibuat.</p>
  <div id="delete-state" role="status" aria-live="polite"></div><label class="block text-sm font-semibold mt-4 text-slate-800">Ketik nama toko persis untuk mengonfirmasi<input id="delete-confirm" autocomplete="off" placeholder="Nama toko persis"></label></div>
 <footer><button type="button" data-delete-close class="hover:bg-slate-50 transition cursor-pointer">Batal</button><button type="button" id="delete-submit" disabled class="transition cursor-pointer font-bold">Hapus Integrasi &amp; Data</button></footer>
</dialog>
<script src="assets/marketplace/js/marketplace-store-deletion.js?v=3" defer></script>
<?php endif; ?>

<script src="assets/marketplace/js/marketplace-auto-sync.js?v=20261029_2" defer></script>
<script src="assets/marketplace/js/marketplace-integration-health.js?v=20261027" defer></script>

<!-- Dialog Otorisasi Marketplace -->
<dialog id="settings-connect-dialog" class="ih-dialog" aria-labelledby="settings-dialog-title">
 <header>
  <h2 id="settings-dialog-title" class="font-extrabold text-base text-slate-900">Koneksi marketplace</h2>
  <button type="button" data-close-settings-dialog aria-label="Tutup modal" class="text-slate-400 hover:text-slate-600 text-lg cursor-pointer">×</button>
 </header>
 <div id="settings-dialog-content" class="p-6 text-xs text-slate-600 space-y-4"></div>
</dialog>
