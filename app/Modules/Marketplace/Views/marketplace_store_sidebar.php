<?php
require_once (dirname(__DIR__, 4) . '/app/Modules/Marketplace/Views/marketplace_platform_logo.php');
require_once (dirname(__DIR__, 4) . '/app/Modules/Marketplace/Views/marketplace_icons.php');
require_once (dirname(__DIR__, 4) . '/app/Modules/Marketplace/Views/marketplace_brand.php');

$navSections = [
    'OPERASIONAL' => [
        'dashboard' => ['dashboard', 'Dashboard Toko'],
        'pesanan'   => ['package', 'Pesanan'],
        'transaksi' => ['receipt', 'Transaksi & Retur'],
        'produk'    => ['package', 'Produk'],
    ],
    'KEUANGAN & IKLAN' => [
        'pemasukan'   => ['wallet', 'Pemasukan & Settlement'],
        'pengeluaran' => ['expense', 'Pengeluaran'],
        'marketing'   => ['megaphone', 'Iklan & Marketing'],
    ],
    'ANALITIK & SISTEM' => [
        'analitik' => ['chart', 'Analitik & Laporan'],
        'sync'     => ['history', 'Sinkronisasi'],
    ],
];
?>
<div class="jl-mobile-topbar"><a href="marketplace.php?choose_store=1" class="font-extrabold"><?= jlMarketplaceBrand(true) ?></a><button type="button" data-jl-menu aria-controls="jl-navigation" aria-expanded="false" aria-label="Buka navigasi"><?= jlIcon('menu') ?></button></div><button class="jl-nav-overlay" data-jl-close aria-label="Tutup navigasi" hidden></button>
<aside id="jl-navigation" class="jl-sidebar w-64 bg-white border-r flex flex-col fixed inset-y-0 z-30">
 <div class="jl-sidebar-brand"><a href="marketplace.php?choose_store=1" class="flex items-center gap-3"><?= jlMarketplaceBrand() ?></a><button type="button" class="jl-mobile-close" data-jl-close aria-label="Tutup navigasi"><?= jlIcon('close') ?></button></div>
 <a class="jl-scope-label" href="marketplace.php?choose_store=1"><?= jlIcon('arrow-left') ?> Pilih Toko</a>
 <details class="jl-store-picker"><summary><?= jlMarketplacePlatformLogo((string)($activeStore['platform']??'')) ?><span><strong><?= htmlspecialchars($activeStore['store_name']) ?></strong><small><?= htmlspecialchars($activeStore['platform']) ?></small></span></summary><div><?php foreach($stores as $st): ?><a href="marketplace.php?<?= htmlspecialchars(http_build_query(['store'=>$st['id'],'tab'=>$activeTab]),ENT_QUOTES) ?>" <?= $st['id']===$activeStoreId?'aria-current="page"':'' ?>><span class="jl-store-option"><?= jlMarketplacePlatformLogo((string)$st['platform'],24) ?><span><?= htmlspecialchars($st['store_name']) ?><small><?= htmlspecialchars($st['platform']) ?></small></span></span></a><?php endforeach; ?></div></details>
 <nav class="jl-navigation" aria-label="Navigasi toko">
  <?php foreach($navSections as $heading => $items): ?>
   <p class="jl-nav-heading"><?= $heading ?></p>
   <?php foreach($items as $tab => [$icon, $label]): ?>
    <a class="jl-nav-link" href="marketplace.php?<?= htmlspecialchars(http_build_query(['store'=>$activeStoreId,'tab'=>$tab]),ENT_QUOTES) ?>" <?= ($activeTab===$tab||($tab==='sync'&&$activeTab==='riwayat_sinkronisasi'))?'aria-current="page"':'' ?>><?= jlIcon($icon) ?><span><?= $label ?></span></a>
   <?php endforeach; ?>
  <?php endforeach; ?>
  <p class="jl-nav-heading">PREFERENSI</p>
  <a class="jl-nav-link" href="marketplace.php?tab=pengaturan&amp;peng_sub=toko"><?= jlIcon('settings') ?><span>Pengaturan Toko</span></a>
 </nav>
 <footer class="jl-sidebar-footer"><div class="jl-scope-note"><strong><?= htmlspecialchars((string)($_SESSION['name']??$_SESSION['username']??'Pengguna Jago Laba')) ?></strong><p>Ruang kerja toko marketplace</p></div><a href="workspace.php" onclick="return confirmWorkspaceSwitch(event)"><?= jlIcon('compare') ?> Ganti Ruang Kerja</a><button type="button" onclick="logoutMarketplace()"><?= jlIcon('logout') ?> Keluar</button></footer>
</aside>