<?php
require_once (dirname(__DIR__, 4) . '/app/Bootstrap/bootstrap.php');
MarketplaceModule::boot();
if (empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }
if (Workspace::active() === null) { header('Location: workspace.php'); exit; }
Workspace::require(Workspace::MARKETPLACE);
header('Cache-Control: no-store, private');
$service = new MarketplaceService();
$apiService = new MarketplaceApiService();
$oauthService = new MarketplaceOAuthService();
$stores=array_values(array_filter($service->getStores(false),static fn(array $s):bool=>!empty($s['oauth_connection_id'])&&!empty($s['oauth_authorized_at'])));
$archivedStores=[];$deletionStatuses=[];
$canManageStoreArchive=preg_match('/OWNER|ADMIN/i',(string)($_SESSION['role']??''))===1;
$globalSettings=false;
if (!function_exists('formatRp')) {
    function formatRp($num): string {
        if($num===null)return 'Menunggu data keuangan/HPP';
        return 'Rp ' . number_format((float)$num, 0, ',', '.');
    }
}

// Central settings; each operational request has a validated store identity.
if(($_GET['tab']??'')==='integrasi'){$_GET['tab']='pengaturan';$_GET['peng_sub']='integrasi';}
if(($_GET['tab']??'')==='pengaturan'){
 if(($_GET['peng_sub']??'')==='aplikasi')$_GET['peng_sub']='tambah_toko';
 require_once (dirname(__DIR__, 4) . '/app/Modules/Marketplace/Jobs/MarketplaceAutoSync.php');
 $autoSyncService=new MarketplaceAutoSync();$stores=$service->getStores();$archivedStores=$service->getArchivedStores();
 if($canManageStoreArchive){$deletionService=new MarketplaceStoreDeletion(Database::pdo());if($deletionService->ready())$deletionStatuses=$deletionService->recentStatuses();}
 $oauthConfig=$oauthService->configurationStatus();$partnerVaultReady=MarketplaceTokenVault::isConfigured();$partnerSettings=new MarketplacePartnerSettings();
 $partnerConfigStatus=!empty($oauthConfig['is_admin'])?['Shopee'=>$partnerSettings->status('Shopee'),'TikTok Shop'=>$partnerSettings->status('TikTok Shop')]:[];
 require (dirname(__DIR__, 4) . '/app/Modules/Marketplace/Views/marketplace_integration_page.php');exit;
}
$requestedTab=(string)($_GET['tab']??'dashboard');$requestedStore=(string)($_GET['store']??$_GET['store_filter']??'');$storeIds=array_column($stores,'id');
require_once (dirname(__DIR__, 4) . '/app/Modules/Marketplace/Services/MarketplaceStoreSelection.php');
try{$activeStoreId=MarketplaceStoreSelection::resolve($stores,$requestedStore,(string)($_SESSION['marketplace_active_store']??''));}
catch(DomainException $e){http_response_code(404);exit(htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8'));}
if(!$stores){header('Location: marketplace.php?tab=pengaturan&peng_sub=tambah_toko',true,303);exit;}
if(($_GET['choose_store']??'')==='1'||$activeStoreId===''){require (dirname(__DIR__, 4) . '/app/Modules/Marketplace/Views/marketplace_store_picker.php');exit;}
$_SESSION['marketplace_active_store']=$activeStoreId;
$activeTab=in_array($requestedTab,['dashboard','pesanan','transaksi','pengeluaran','pemasukan','analitik','produk','marketing','inventori','sync','riwayat_sinkronisasi'],true)?$requestedTab:'dashboard';if($activeTab==='riwayat_sinkronisasi')$activeTab='sync';
if(($_GET['scope']??'')==='global'||!isset($_GET['store'])||$activeTab==='inventori'){
 $destination=$_GET;unset($destination['scope'],$destination['store_filter'],$destination['analytics_stores'],$destination['include_disconnected']);
 $destination['store']=$activeStoreId;$destination['tab']=$activeTab==='inventori'?'produk':$activeTab;
 if($activeTab==='inventori'){$destination['product_section']='stok';$destination['stock_search']=$_GET['inv_search']??'';}
 header('Location: marketplace.php?'.http_build_query($destination),true,303);exit;
}
$activeStore=$stores[array_search($activeStoreId,$storeIds,true)];
// Load only the data required by the active menu.
if($activeTab==='dashboard'){
 require_once (dirname(__DIR__, 4) . '/app/Modules/Marketplace/Services/MarketplaceDashboardService.php');
 try{$storeDashboard=(new MarketplaceDashboardService(Database::pdo()))->report($activeStoreId,$_GET);}catch(InvalidArgumentException $e){http_response_code(422);exit(htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8'));}
}
if($activeTab==='pesanan'){
 require_once (dirname(__DIR__, 4) . '/app/Modules/Marketplace/Services/MarketplaceOrdersService.php');
 try{$ordersPage=(new MarketplaceOrdersService(Database::pdo()))->listing($activeStoreId,$_GET);}catch(InvalidArgumentException $e){http_response_code(422);exit(htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8'));}
}
if($activeTab==='analitik'){
 require_once dirname(__DIR__).'/Services/MarketplaceDashboardService.php';
 try{
  $analyticsPeriod=MarketplaceDashboardService::period($_GET);
  $globalFilters=['store_id'=>$activeStoreId,'date_start'=>$analyticsPeriod['start'],'date_end'=>$analyticsPeriod['end'],'date_basis'=>$_GET['date_basis']??'created','analytics_mode'=>$_GET['analytics_mode']??'all'];
  $analyticsReport=(new MarketplaceAnalyticsService())->report($globalFilters);
 }catch(InvalidArgumentException $e){http_response_code(422);exit(htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8'));}
}
if($activeTab==='sync'){if(empty($_GET['sync_store']))$_GET['sync_store']=$activeStoreId;}
$transFilter=$_GET['filter']??'semua';$transSearch=$_GET['search']??null;$transStatus=$_GET['status']??null;$transPage=max(1,(int)($_GET['page']??1));
$returnClaimsData=['summary'=>['pending_over_14_days'=>0],'claims'=>[]];
if($activeTab==='transaksi'){
 $transactionsData=$service->getTransactions($activeStoreId,$transFilter,$transSearch,$transStatus,$transPage,50,(string)($_GET['search_by']??'all'));
 $returnClaimsData=$service->getReturnClaims($activeStoreId,$_GET['claim_status']??null);
}
$expCategory=$_GET['exp_category']??null;$expSearch=$_GET['exp_search']??null;$expDate=$_GET['exp_date']??null;
if($activeTab==='pengeluaran')$expensesData=$service->getExpenses($activeStoreId,$expCategory,$expSearch,$expDate);
$mktSub=$_GET['mkt_sub']??'ringkasan';$mktFilter=$_GET['mkt_filter']??null;$mktSearch=$_GET['mkt_search']??null;$mktPlatform=$_GET['mkt_platform']??($mktSub==='shopee'?'Shopee':($mktSub==='tiktok'?'TikTok Shop':'Semua Platform'));$mktAdType=$_GET['mkt_ad_type']??'Semua';$mktIncludePpn=($_GET['ppn']??'')==='1';
if($activeTab==='marketing')$marketingData=$service->getMarketingDashboard($activeStoreId,$mktFilter,$mktSearch,$mktPlatform,$mktAdType,$mktIncludePpn,['date_start'=>$_GET['date_start']??'','date_end'=>$_GET['date_end']??'']);
if($activeTab==='pemasukan'&&($_GET['pem_sub']??'')==='jurnal')$journalData=$service->getJournalEntries($activeStoreId);
$pengSubTab='toko';
$apiCredentials = [];

// Master Data pendukung untuk Modal Form
$accounts = []; // Shared konveksi accounts are not marketplace accounts.
$productVariants = []; // Konveksi master products require explicit transfer/mapping.

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($activeStore['store_name']) ?> • Jago Laba Marketplace</title>
    <?php if($activeTab==='dashboard'): ?><link rel="stylesheet" href="assets/marketplace/css/marketplace-dashboard.css"><script src="assets/marketplace/js/marketplace-dashboard.js" defer></script><?php endif; ?>
    <?php if($activeTab==='pesanan'): ?><link rel="stylesheet" href="assets/marketplace/css/marketplace-orders.css?v=<?= filemtime((dirname(__DIR__, 4) . '/public/assets/marketplace/css/marketplace-orders.css')) ?>"><script src="assets/marketplace/js/marketplace-orders.js?v=<?= filemtime((dirname(__DIR__, 4) . '/public/assets/marketplace/js/marketplace-orders.js')) ?>" defer></script><?php endif; ?>
    <?php if($activeTab==='marketing'): ?><link rel="stylesheet" href="assets/marketplace/css/marketplace-advertising.css?v=<?= filemtime((dirname(__DIR__, 4) . '/public/assets/marketplace/css/marketplace-advertising.css')) ?>"><script src="assets/marketplace/js/marketplace-advertising.js?v=<?= filemtime((dirname(__DIR__, 4) . '/public/assets/marketplace/js/marketplace-advertising.js')) ?>" defer></script><?php endif; ?>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/shared/css/jl-design-tokens.css">
    <link rel="stylesheet" href="assets/marketplace/css/marketplace-ui.css">
    <link rel="stylesheet" href="assets/shared/css/jl-components.css">
<link rel="stylesheet" href="assets/marketplace/css/marketplace-system.css?v=20261027-platform-logos"><script src="assets/marketplace/js/marketplace-system.js" defer></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Inter"', '"Plus Jakarta Sans"', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#fff7ed',
                            100: '#ffedd5',
                            500: '#f97316',
                            600: '#ea580c',
                            700: '#c2410c',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; }
        
        /* Funnel Arrow Chevron CSS persis FinKami */
        .funnel-step {
            position: relative;
            clip-path: polygon(0% 0%, calc(100% - 14px) 0%, 100% 50%, calc(100% - 14px) 100%, 0% 100%, 14px 50%);
            margin-left: -10px;
        }
        .funnel-step:first-child {
            clip-path: polygon(0% 0%, calc(100% - 14px) 0%, 100% 50%, calc(100% - 14px) 100%, 0% 100%);
            margin-left: 0;
            border-top-left-radius: 8px;
            border-bottom-left-radius: 8px;
        }
        .funnel-step:last-child {
            clip-path: polygon(0% 0%, 100% 0%, 100% 100%, 0% 100%, 14px 50%);
            border-top-right-radius: 8px;
            border-bottom-right-radius: 8px;
        }
    </style>
</head>
<body class="jl-marketplace text-gray-800 flex min-h-screen">
    <script>
    const jlCsrf = <?= json_encode(Workspace::csrf()) ?>;
    const jlFetch = window.fetch.bind(window);
    window.fetch = (url, options = {}) => {
        if (String(url).startsWith('api/marketplace-ajax.php')) {
            options = {...options, headers: {...(options.headers || {}), 'X-CSRF-Token': jlCsrf}};
        }
        return jlFetch(url, options);
    };
    let jlUnsaved = false;
    document.addEventListener('input', e => { if (e.target.closest('form')) jlUnsaved = true; });
    document.addEventListener('submit', () => { jlUnsaved = false; });
    function confirmWorkspaceSwitch(e) {
        if (jlUnsaved && !confirm('Ada perubahan formulir yang belum disimpan. Ganti ruang kerja?')) { e.preventDefault(); return false; }
        sessionStorage.removeItem('jl_marketplace_store');
        return true;
    }
    async function logoutMarketplace() {
        if (jlUnsaved && !confirm('Ada perubahan formulir yang belum disimpan. Keluar?')) return;
        const res = await jlFetch('api/logout.php',{method:'POST',credentials:'same-origin',headers:{'X-CSRF-Token':jlCsrf}});
        if (!res.ok) { alert('Gagal keluar. Muat ulang halaman lalu coba lagi.'); return; }
        sessionStorage.clear();
        localStorage.removeItem('jlToken');localStorage.removeItem('jlTime');localStorage.removeItem('jlRole');
        location.assign('index.php');
    }
    document.addEventListener('DOMContentLoaded', () => {
        const toggle = document.getElementById('marketplace-mobile-menu');
        const sidebar = toggle?.closest('.jl-sidebar');
        toggle?.addEventListener('click', () => {
            const open = sidebar.classList.toggle('jl-nav-open');
            toggle.setAttribute('aria-expanded', String(open));
            toggle.textContent = open ? 'Tutup menu toko dan akun' : 'Menu toko dan akun';
        });
    });
    </script>

    <!-- TOAST NOTIFICATION CONTAINER -->
    <div id="toastContainer" class="fixed top-5 right-5 z-50 flex flex-col gap-2 pointer-events-none"></div>

    <!-- 1. SIDEBAR KIRI (Identik FinKami) -->
    <?php if($globalSettings):
        $globalTab='pengaturan';require (dirname(__DIR__, 4) . '/app/Modules/Marketplace/Views/marketplace_global_navigation_config.php');
        $query=static fn(array $changes=[]):string=>http_build_query(array_replace(['scope'=>'global','tab'=>'pengaturan','settings_store'=>$activeStoreId],$changes));
        require (dirname(__DIR__, 4) . '/app/Modules/Marketplace/Views/marketplace_global_sidebar.php');
    else: ?>
    <?php require (dirname(__DIR__, 4) . '/app/Modules/Marketplace/Views/marketplace_store_sidebar.php'); ?>
    <?php endif; ?>

    <!-- 2. KONTEN UTAMA DENGAN TAB DINAMIS -->
    <main class="jl-main <?= $globalSettings?'md:ml-64 p-4 sm:p-6 lg:p-8':'ml-64 p-8' ?> flex-1 min-w-0 pb-20">
        <?php require (dirname(__DIR__, 4) . '/app/Modules/Marketplace/Views/marketplace_background_sync.php'); ?>

        <!-- ==================================================================== -->
        <!-- TAB 1: DASHBOARD PROYEK / TOKO (IDENTIK FIN-KAMI)                   -->
        <!-- ==================================================================== -->
        <?php if ($activeTab === 'produk'): ?>
            <?php require (dirname(__DIR__, 4) . '/app/Modules/Marketplace/Views/marketplace_store_products.php'); ?>
        <?php endif; ?>
        <?php if($activeTab==='pesanan'): require (dirname(__DIR__, 4) . '/app/Modules/Marketplace/Views/marketplace_orders.php'); endif; ?>
        <?php if ($activeTab === 'dashboard'): require (dirname(__DIR__, 4) . '/app/Modules/Marketplace/Views/marketplace_store_dashboard.php'); endif; ?>
        <?php if ($activeTab === 'transaksi'): ?>
        <div class="space-y-6">
            <!-- Header Bar -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <a href="marketplace.php?store=<?= urlencode($activeStoreId) ?>&tab=dashboard" class="text-xs font-semibold text-gray-500 hover:text-brand-600 mb-1 inline-flex items-center gap-1">
                        &larr; Kembali ke Proyek/toko
                    </a>
                    <h1 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                        <span class="w-8 h-8 rounded-lg bg-brand-100 text-brand-600 flex items-center justify-center font-bold text-sm">&#128717;</span>
                        Transaksi
                    </h1>
                    <p class="text-xs text-gray-500">Proyek/Toko: <?= htmlspecialchars($activeStore['store_name']) ?></p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button onclick="openAddOrderModal()" class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-sm flex items-center gap-1.5 transition">
                        <span>+ Tambah Manual</span>
                    </button>
                    <button onclick="openImportSettlementModal()" class="px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-sm transition flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        <span>Impor Settlement CSV</span>
                    </button>
                    <button onclick="openReturnClaimsModal()" class="px-3.5 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold shadow-sm transition flex items-center gap-1.5">
                        <span>Klaim Retur Hilang (<?= count($returnClaimsData['claims']) ?>)</span>
                    </button>
                    <button onclick="window.print()" class="px-3.5 py-2 bg-brand-600 text-white hover:bg-brand-700 rounded-xl text-xs font-bold transition shadow-sm">
                        Ekspor &darr;
                    </button>
                </div>
            </div>

            <!-- Green Connection Banner (Persis Foto Image 1) -->
            <div class="p-3.5 bg-emerald-50 border border-emerald-200 rounded-2xl flex items-center justify-between text-xs text-emerald-900 shadow-xs">
                <div class="flex items-center gap-2.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span><b>Toko <?= htmlspecialchars($activeStore['store_name']) ?>.</b> Sinkronisasi Data mengambil produk, pesanan, dan transaksi sesuai izin API. Data iklan memerlukan konektor terpisah.</span>
                </div>
                <div class="flex items-center gap-2">
                    <?php if (!empty($activeStore['is_connection_verified'])): ?><button onclick="triggerSync('<?= htmlspecialchars($activeStoreId, ENT_QUOTES, 'UTF-8') ?>')" class="px-3 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-bold text-[11px] flex items-center gap-1 transition shadow-xs">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Sinkronisasi Data
                    </button><?php else: ?><a href="marketplace.php?tab=pengaturan&amp;peng_sub=tambah_toko" class="rounded-lg bg-emerald-600 px-3 py-2 font-bold text-white">Hubungkan toko</a><?php endif; ?>
                </div>
            </div>

            <!-- Counter Bar -->
            <div class="text-xs text-gray-500 flex items-center justify-between">
                <span>
                    Menampilkan <?= count($transactionsData['transactions']) ?> dari <?= number_format((int)$transactionsData['total_count'], 0, ',', '.') ?> transaksi · Halaman <?= $transactionsData['pagination']['page'] ?> / <?= $transactionsData['pagination']['total_pages'] ?> - Total laba halaman <b class="<?= $transactionsData['total_laba'] < 0 ? 'text-red-600' : 'text-emerald-600' ?>"><?= $transactionsData['total_laba']===null?'Menunggu data keuangan/HPP':formatRp($transactionsData['total_laba']) ?></b>
                </span>
                <span class="text-gray-400">Filter Aktif: <b class="uppercase text-gray-700"><?= htmlspecialchars($transFilter) ?></b></span>
            </div>

            <!-- Filter Controls & Quick Pills (Persis Foto Image 1) -->
            <div class="bg-white p-4 rounded-2xl border border-gray-200 space-y-3 shadow-xs">
                <div class="flex flex-col md:flex-row gap-3 items-center justify-between">
                    <div class="flex items-center gap-2 w-full md:w-auto">
                        <select id="transSearchBy" class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-semibold text-gray-700">
                            <?php foreach(['all'=>'Semua Kolom','order'=>'No Pesanan','buyer'=>'Nama Pembeli','tracking'=>'No Resi'] as $key=>$label): ?><option value="<?= $key ?>" <?= ($_GET['search_by']??'all')===$key?'selected':'' ?>><?= $label ?></option><?php endforeach; ?>
                        </select>
                        <div class="relative flex-1 md:w-72">
                            <input type="text" id="transSearchInput" value="<?= htmlspecialchars($transSearch ?? '') ?>" placeholder="Cari No Pesanan / Pembeli..." onkeyup="if(event.key === 'Enter') applyFilterSearch()" class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:outline-none focus:ring-1 focus:ring-brand-500">
                        </div>
                        <button onclick="applyFilterSearch()" class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white font-bold rounded-xl text-xs transition shadow-xs">Cari</button>
                    </div>

                    <div class="flex items-center gap-2">
                        <select onchange="window.location.href='marketplace.php?store='+this.value+'&tab=transaksi'" class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs text-gray-700 font-medium">
                            <?php foreach ($stores as $st): ?>
                            <option value="<?= htmlspecialchars($st['id']) ?>" <?= $st['id'] === $activeStoreId ? 'selected' : '' ?>><?= htmlspecialchars($st['platform']) ?> - <?= htmlspecialchars($st['store_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select onchange="applyStatusFilter(this.value)" class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs text-gray-700 font-medium">
                            <option value="Semua Status" <?= empty($transStatus) || $transStatus === 'Semua Status' ? 'selected' : '' ?>>Semua Status</option>
                            <?php foreach (['Perlu Dikirim','Menunggu Pickup','Sedang Dikirim','Dikirim Sebagian','Sudah Diterima','Selesai','Batal','Pengajuan Pembatalan','Belum Dibayar','Menunggu Konfirmasi','Retur'] as $statusLabel): ?><option value="<?= htmlspecialchars($statusLabel) ?>" <?= $transStatus===$statusLabel?'selected':'' ?>><?= htmlspecialchars($statusLabel) ?></option><?php endforeach; ?>                        </select>
                    </div>
                </div>

                <p class="text-[11px] text-slate-500">Menunggu Pickup: pengiriman sudah diatur, tetapi kurir belum mengambil paket. Pengajuan pembatalan ditampilkan terpisah sampai marketplace mengonfirmasi Batal.</p>
                <!-- Quick Filter Pills -->
                <div class="flex items-center gap-2 overflow-x-auto pt-2 pb-1 text-xs">
                    <a href="marketplace.php?store=<?= urlencode($activeStoreId) ?>&tab=transaksi&filter=semua" class="px-3 py-1.5 rounded-lg border border-gray-200 font-semibold whitespace-nowrap transition <?= $transFilter === 'semua' ? 'bg-brand-50 text-brand-600 border-brand-500 font-bold' : 'text-gray-600 hover:bg-gray-50' ?>">Semua</a>
                    <a href="marketplace.php?store=<?= urlencode($activeStoreId) ?>&tab=transaksi&filter=pengembalian" class="px-3 py-1.5 rounded-lg border border-gray-200 font-semibold whitespace-nowrap transition <?= $transFilter === 'pengembalian' ? 'bg-purple-50 text-purple-700 border-purple-500 font-bold' : 'text-gray-600 hover:bg-gray-50' ?>">Pengembalian</a>
                    <a href="marketplace.php?store=<?= urlencode($activeStoreId) ?>&tab=transaksi&filter=kerugian" class="px-3 py-1.5 rounded-lg border border-red-200 font-bold whitespace-nowrap transition <?= $transFilter === 'kerugian' ? 'bg-red-50 text-red-600 border-red-500' : 'text-red-500 hover:bg-red-50' ?>">Kerugian</a>
                    <a href="marketplace.php?store=<?= urlencode($activeStoreId) ?>&tab=transaksi&filter=selisih_ongkir" class="px-3 py-1.5 rounded-lg border border-amber-300 font-bold whitespace-nowrap transition <?= $transFilter === 'selisih_ongkir' ? 'bg-amber-100 text-amber-900 border-amber-500' : 'text-amber-700 bg-amber-50/70 hover:bg-amber-100' ?>">⚠️ Selisih Berat Kurir</a>
                    <a href="marketplace.php?store=<?= urlencode($activeStoreId) ?>&tab=transaksi&filter=klaim_retur" class="px-3 py-1.5 rounded-lg border border-rose-300 font-bold whitespace-nowrap transition <?= $transFilter === 'klaim_retur' ? 'bg-rose-100 text-rose-900 border-rose-500' : 'text-rose-700 bg-rose-50/70 hover:bg-rose-100' ?>">📦 Klaim Retur Hilang (&gt;14 Hari)</a>
                    <a href="marketplace.php?store=<?= urlencode($activeStoreId) ?>&tab=transaksi&filter=penyesuaian" class="px-3 py-1.5 rounded-lg border border-gray-200 font-semibold whitespace-nowrap transition <?= $transFilter === 'penyesuaian' ? 'bg-blue-50 text-blue-700 border-blue-500 font-bold' : 'text-gray-600 hover:bg-gray-50' ?>">Penyesuaian</a>
                    <a href="marketplace.php?store=<?= urlencode($activeStoreId) ?>&tab=transaksi&filter=belum_cair" class="px-3 py-1.5 rounded-lg border border-gray-200 font-semibold whitespace-nowrap transition <?= $transFilter === 'belum_cair' ? 'bg-orange-50 text-orange-700 border-orange-500 font-bold' : 'text-gray-600 hover:bg-gray-50' ?>">Belum Cair</a>
                    <a href="marketplace.php?store=<?= urlencode($activeStoreId) ?>&tab=transaksi&filter=cod" class="px-3 py-1.5 rounded-lg border border-gray-200 font-semibold whitespace-nowrap transition <?= $transFilter === 'cod' ? 'bg-gray-100 text-gray-800 border-gray-400 font-bold' : 'text-gray-600 hover:bg-gray-50' ?>">COD</a>
                    <a href="marketplace.php?store=<?= urlencode($activeStoreId) ?>&tab=transaksi&filter=cod_batal" class="px-3 py-1.5 rounded-lg border border-gray-200 font-semibold whitespace-nowrap transition <?= $transFilter === 'cod_batal' ? 'bg-red-50 text-red-600 border-red-400 font-bold' : 'text-gray-600 hover:bg-gray-50' ?>">COD Batal</a>
                    <a href="marketplace.php?store=<?= urlencode($activeStoreId) ?>&tab=transaksi&filter=gagal_kirim" class="px-3 py-1.5 rounded-lg border border-gray-200 font-semibold whitespace-nowrap transition <?= $transFilter === 'gagal_kirim' ? 'bg-rose-50 text-rose-700 border-rose-400 font-bold' : 'text-gray-600 hover:bg-gray-50' ?>">Gagal Kirim</a>                    <?php foreach (['barang_hilang'=>'Barang Hilang','sampel'=>'Sampel','affiliate'=>'Affiliate'] as $key=>$label): ?><a href="<?= htmlspecialchars('marketplace.php?'.http_build_query(['store'=>$activeStoreId,'tab'=>'transaksi','filter'=>$key,'search'=>$transSearch,'status'=>$transStatus])) ?>" class="px-3 py-1.5 rounded-lg border font-semibold whitespace-nowrap <?= $transFilter===$key?'bg-brand-50 text-brand-600 border-brand-500':'text-gray-600 border-gray-200' ?>"><?= $label ?></a><?php endforeach; ?>
                </div>
            </div>

            <!-- Table Transaksi (Tampilan Persis Image 1) -->
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider font-bold">
                            <tr>
                                <th class="p-3 pl-4">DETAIL PESANAN</th>
                                <th class="p-3">TGL DIBUAT</th>
                                <th class="p-3">TGL DILEPAS</th>
                                <th class="p-3">PLATFORM</th>
                                <th class="p-3 text-right">JUMLAH</th>
                                <th class="p-3 text-right">BIAYA</th>
                                <th class="p-3 text-right">UNTUK DIBAYAR</th>
                                <th class="p-3 text-right">HPP Produk</th>
                                <th class="p-3 text-right">LABA BERSIH</th>
                                <th class="p-3 text-center">STATUS</th>
                                <th class="p-3 text-center pr-4">AKSI</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($transactionsData['transactions'])): ?>
                            <tr>
                                <td colspan="11" class="p-8 text-center text-gray-400">
                                    Tidak ada data transaksi yang sesuai dengan filter pencarian ini.
                                </td>
                            </tr>
                            <?php endif; ?>

                            <?php foreach ($transactionsData['transactions'] as $t): ?>
                            <?php 
                                $isLoss = $t['calculated_laba'] < 0; 
                                $isManual = ($t['source_kind'] ?? 'api') === 'manual';
                                $hasShippingDisc = (float)($t['shipping_adjustment'] ?? 0) > 0 || (float)($t['shipping_discrepancy_amount'] ?? 0) > 0;
                                $hasReturnClaim = !empty($t['return_claim_status']) && $t['return_claim_status'] !== 'NONE';
                            ?>
                            <tr data-order-id="<?= htmlspecialchars($t['id']) ?>" class="<?= $isLoss ? 'bg-red-50/40 hover:bg-red-50/70' : 'hover:bg-gray-50' ?> transition">
                                <td class="p-3 pl-4">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <button onclick="inspectOrder(<?= htmlspecialchars(json_encode((string)$t['id']), ENT_QUOTES, 'UTF-8') ?>)" aria-expanded="false" class="font-bold text-gray-900 font-mono hover:text-brand-600 underline text-left">
                                            <?= htmlspecialchars($t['id']) ?>
                                        </button>
                                        <?php if ($t['presentation']['is_estimated'] === true): ?><span class="text-[10px] bg-amber-100 text-amber-800 rounded px-2">Estimasi Penghasilan</span><?php endif; ?>
                                        <?php if ($t['presentation']['is_cod'] === true): ?><span class="text-[10px] bg-orange-100 text-orange-800 rounded px-2">COD</span><?php endif; ?>
                                        <?php if ($hasShippingDisc): ?>
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-900 border border-amber-300" title="Selisih volume ekspedisi (Dinyatakan <?= $t['declared_weight_gram'] ?? 500 ?>g vs Timbang <?= $t['actual_weight_gram'] ?? 1200 ?>g)">
                                            ⚠️ Selisih Berat <?= $t['actual_weight_gram'] ?? 1200 ?>g (-<?= formatRp($t['shipping_adjustment']) ?>)
                                        </span>
                                        <?php endif; ?>
                                        <?php if ($hasReturnClaim): ?>
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-300">
                                            📦 Klaim Retur: <?= htmlspecialchars($t['return_claim_status']) ?>
                                        </span>
                                        <?php endif; ?>
                                        <?php if (str_starts_with($t['id'], 'SHOPEE ADJ') || str_starts_with($t['id'], 'TIKTOK ADJ')): ?>
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-700">Adjustment</span>
                                        <?php elseif (str_contains($t['buyer_username'] ?? '', 'Kilat')): ?>
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800">Pengiriman Kilat</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex items-center gap-2 mt-0.5 text-[11px] text-gray-500">
                                        <span class="line-clamp-1"><?= htmlspecialchars($t['buyer_username'] ?? '-') ?></span>
                                        <?php if ((float)($t['affiliate_commission'] ?? 0) > 0): ?>
                                        <span class="text-purple-700 font-semibold">• Affiliate: <?= formatRp($t['affiliate_commission']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($t['presentation']['tracking_number'] !== ''): ?><p class="text-[11px] text-gray-500 mt-1">Resi: <b><?= htmlspecialchars($t['presentation']['tracking_number']) ?></b></p><?php endif; ?>
                                    <?php if ($t['presentation']['payment_method'] !== '' && $t['presentation']['is_cod'] !== true): ?><p class="text-[11px] text-gray-400"><?= htmlspecialchars($t['presentation']['payment_method']) ?></p><?php endif; ?>
                                    <?php foreach ($t['presentation']['items'] as $item): ?><p class="text-[11px] text-gray-600 mt-1 max-w-[320px] truncate"><?= htmlspecialchars($item['name']) ?><span class="block text-gray-400">SKU: <?= htmlspecialchars($item['sku']) ?></span></p><?php endforeach; ?>
                                </td>
                                <td class="p-3 text-gray-600"><?= substr($t['order_date'], 0, 10) ?></td>
                                <td class="p-3 text-gray-600"><?= htmlspecialchars(substr($t['presentation']['released_at'] ?? '', 0, 10) ?: '—') ?></td>
                                <td class="p-3">
                                    <span class="font-semibold text-gray-800 flex items-center gap-1">
                                        <?php if ($activeStore['platform'] === 'Shopee'): ?>
                                        <span class="w-4 h-4 rounded-full bg-orange-500 text-white flex items-center justify-center text-[9px] font-bold">S</span>
                                        Shopee
                                        <?php else: ?>
                                        <span class="w-4 h-4 rounded-full bg-black text-white flex items-center justify-center text-[9px] font-bold">T</span>
                                        TikTok
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td class="p-3 text-right font-medium">
                                    <?= formatRp($t['gross_sales']) ?>
                                    <span class="block text-[10px] text-gray-400"><?= array_sum(array_column($t['presentation']['items'], 'quantity')) ?> qty</span>
                                </td>
                                <td class="p-3 text-right font-medium text-gray-700">
                                    <?= $t['calculated_fee']===null?'Menunggu data keuangan':formatRp($t['calculated_fee']) ?>
                                    <span class="block text-[10px] text-gray-400"><?= $t['presentation']['fee_percent']===null?'—':$t['presentation']['fee_percent'].'%' ?></span>
                                </td>
                                <td class="p-3 text-right font-semibold text-emerald-600">
                                    <?= $t['net_settlement_amount']===null?'Menunggu data keuangan':formatRp($t['net_settlement_amount']) ?>
                                </td>
                                <td class="p-3 text-right font-medium text-blue-600">
                                    <?= $t['total_cogs_hpp']===null?'HPP belum lengkap':formatRp($t['total_cogs_hpp']) ?>
                                </td>
                                <td class="p-3 text-right font-bold <?= $isLoss ? 'text-red-600' : 'text-emerald-600' ?>">
                                    <?= $t['calculated_laba']===null?($t['presentation']['net_income']===null?'Menunggu data keuangan':'HPP belum lengkap'):formatRp($t['calculated_laba']) ?>
                                    <span class="block text-[10px] font-normal"><?= $t['presentation']['profit_percent'] !== null ? $t['presentation']['profit_percent'] . '%' : 'Laba belum lengkap' ?></span>
                                </td>
                                <td class="p-3 text-center">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= $t['order_status'] === 'Retur' ? 'bg-purple-100 text-purple-700' : ($t['presentation']['status'] === 'Batal' ? 'bg-red-100 text-red-700' : ($t['presentation']['status']==='Perlu Dikirim'?'bg-blue-100 text-blue-700':(in_array($t['presentation']['status'],['Menunggu Pickup','Pengajuan Pembatalan'])?'bg-amber-100 text-amber-800':'bg-emerald-100 text-emerald-800'))) ?>">
                                        <?= htmlspecialchars($t['presentation']['status']) ?>
                                    </span>
                                </td>
                                <td class="p-3 text-center pr-4">
                                    <button onclick="inspectOrder(<?= htmlspecialchars(json_encode((string)$t['id']), ENT_QUOTES, 'UTF-8') ?>)" aria-label="Lihat rincian pesanan <?= htmlspecialchars($t['id']) ?>" title="Lihat rincian pesanan" class="text-gray-400 hover:text-brand-600 p-1 transition">
                                        <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </button>
                                    <button onclick="editOrder(<?= htmlspecialchars(json_encode((string)$t['id']), ENT_QUOTES, 'UTF-8') ?>, <?= $isManual ? 'true' : 'false' ?>)" aria-label="Edit transaksi <?= htmlspecialchars($t['id']) ?>" title="<?= $isManual ? 'Edit transaksi manual' : 'Pesanan API: hanya penyesuaian internal' ?>" class="text-gray-400 hover:text-blue-600 p-1 transition">
                                        <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 20h9"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.5 3.5a2.121 2.121 0 013 3L8 18l-4 1 1-4 11.5-11.5z"/></svg>
                                    </button>
                                    <button onclick="archiveOrder(<?= htmlspecialchars(json_encode((string)$t['id']), ENT_QUOTES, 'UTF-8') ?>)" aria-label="Arsipkan transaksi <?= htmlspecialchars($t['id']) ?>" title="Arsipkan dari laporan internal" class="text-gray-400 hover:text-rose-600 p-1 transition">
                                        <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M5 7l1 12h12l1-12M9 11v4m6-4v4M9 7V4h6v3"/></svg>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($transactionsData['pagination']['total_pages'] > 1): ?>
                <div class="flex items-center justify-between border-t border-gray-100 px-4 py-3 text-xs">
                    <span class="text-gray-500">Data diambil per halaman dari database; filter tetap aktif.</span>
                    <div class="flex items-center gap-2">
                        <?php if ($transactionsData['pagination']['page'] > 1): ?><a class="px-3 py-1.5 rounded-lg border border-gray-200 font-bold text-gray-600" href="marketplace.php?store=<?= urlencode($activeStoreId) ?>&tab=transaksi&filter=<?= urlencode($transFilter) ?>&page=<?= $transactionsData['pagination']['page'] - 1 ?><?= $transSearch ? '&search=' . urlencode($transSearch) : '' ?><?= $transStatus ? '&status=' . urlencode($transStatus) : '' ?>">Sebelumnya</a><?php endif; ?>
                        <?php if ($transactionsData['pagination']['page'] < $transactionsData['pagination']['total_pages']): ?><a class="px-3 py-1.5 rounded-lg bg-brand-600 text-white font-bold" href="marketplace.php?store=<?= urlencode($activeStoreId) ?>&tab=transaksi&filter=<?= urlencode($transFilter) ?>&page=<?= $transactionsData['pagination']['page'] + 1 ?><?= $transSearch ? '&search=' . urlencode($transSearch) : '' ?><?= $transStatus ? '&status=' . urlencode($transStatus) : '' ?>">Berikutnya</a><?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- ==================================================================== -->
        <!-- TAB 3: PENGELUARAN (SESUAI IMAGE 2 FIN-KAMI)                        -->
        <!-- ==================================================================== -->
        <?php if ($activeTab === 'pengeluaran'): ?>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <a href="marketplace.php?store=<?= urlencode($activeStoreId) ?>&tab=dashboard" class="text-xs font-semibold text-gray-500 hover:text-brand-600 mb-1 inline-flex items-center gap-1">
                        &larr; Kembali ke Proyek/toko
                    </a>
                    <h1 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                        <span class="w-8 h-8 rounded-lg bg-red-100 text-red-600 flex items-center justify-center font-bold text-sm">&#36;</span>
                        Pengeluaran
                    </h1>
                    <p class="text-xs text-gray-500">Proyek/Toko: <?= htmlspecialchars($activeStore['store_name']) ?></p>
                </div>

                <div class="flex items-center gap-2">
                    <button onclick="openAddExpenseModal()" class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-sm flex items-center gap-1.5 transition">
                        <span>+ Tambah Pengeluaran</span>
                    </button>
                    <button onclick="window.print()" class="px-3.5 py-2 bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 rounded-xl text-xs font-bold transition shadow-sm">
                        Ekspor &darr;
                    </button>
                </div>
            </div>

            <!-- 3 KPI Cards Pengeluaran (Persis Image 2) -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm">
                    <span class="text-xs text-gray-400 font-semibold block">Total Pengeluaran</span>
                    <p class="text-2xl font-bold text-gray-900 mt-2"><?= formatRp($expensesData['total_amount']) ?></p>
                    <span class="text-xs text-gray-400 mt-1 block"><?= $expensesData['count'] ?> entri tercatat</span>
                </div>
                <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm border-l-4 border-l-blue-500">
                    <span class="text-xs text-blue-600 font-semibold block">Mempengaruhi Profit</span>
                    <p class="text-2xl font-bold text-gray-900 mt-2"><?= formatRp($expensesData['affects_profit']) ?></p>
                    <span class="text-xs text-gray-400 mt-1 block">Operasional dan iklan marketplace</span>
                </div>
                <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm border-l-4 border-l-emerald-500">
                    <span class="text-xs text-emerald-600 font-semibold block">Hanya Arus Kas</span>
                    <p class="text-2xl font-bold text-gray-900 mt-2"><?= formatRp($expensesData['cashflow_only']) ?></p>
                    <span class="text-xs text-gray-400 mt-1 block">HPP &amp; inventori, pemindahan saldo</span>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white p-4 rounded-2xl border border-gray-200 flex flex-col md:flex-row gap-3 items-center justify-between text-xs shadow-xs">
                <div class="relative flex-1 max-w-md w-full">
                    <input type="text" id="expSearchInput" value="<?= htmlspecialchars($expSearch ?? '') ?>" placeholder="Cari berdasarkan deskripsi, vendor, catatan..." onkeyup="if(event.key === 'Enter') applyExpFilter()" class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-brand-500">
                </div>
                <div class="flex items-center gap-2">
                    <select id="expCategorySelect" onchange="applyExpFilter()" class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-gray-700">
                        <option value="Semua Kategori">Semua Kategori</option>
                        <option value="Biaya Iklan/Advertising Marketplace" <?= $expCategory === 'Biaya Iklan/Advertising Marketplace' ? 'selected' : '' ?>>Biaya Iklan/Advertising</option>
                        <option value="Operasional & Kemasan" <?= $expCategory === 'Operasional & Kemasan' ? 'selected' : '' ?>>Operasional &amp; Kemasan</option>
                    </select>
                    <input type="date" id="expDateInput" value="<?= htmlspecialchars($expDate ?? '') ?>" onchange="applyExpFilter()" class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-gray-700">
                    <button onclick="applyExpFilter()" class="px-3.5 py-2 bg-brand-600 text-white rounded-xl font-bold hover:bg-brand-700 transition">Filter</button>
                </div>
            </div>

            <!-- Table Pengeluaran (Tampilan Persis Image 2) -->
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider font-bold">
                            <tr>
                                <th class="p-3 pl-4">DESKRIPSI</th>
                                <th class="p-3">TANGGAL &darr;</th>
                                <th class="p-3">KATEGORI</th>
                                <th class="p-3 text-right">JUMLAH</th>
                                <th class="p-3">PEMBAYARAN / REKENING</th>
                                <th class="p-3">CATATAN</th>
                                <th class="p-3 text-center pr-4">AKSI</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($expensesData['expenses'])): ?>
                            <tr>
                                <td colspan="7" class="p-8 text-center text-gray-400">
                                    Tidak ada pengeluaran yang sesuai kriteria pencarian.
                                </td>
                            </tr>
                            <?php endif; ?>

                            <?php foreach ($expensesData['expenses'] as $exp): ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="p-3 pl-4">
                                    <span class="font-bold text-gray-900 block"><?= htmlspecialchars($exp['description']) ?></span>
                                    <span class="text-[11px] text-gray-400 block"><?= htmlspecialchars($exp['vendor_name'] ?? 'Vendor: Shopee Ads') ?></span>
                                </td>
                                <td class="p-3 text-gray-600 font-medium"><?= htmlspecialchars($exp['expense_date']) ?></td>
                                <td class="p-3">
                                    <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-gray-100 text-gray-700">
                                        <?= htmlspecialchars($exp['category']) ?>
                                    </span>
                                </td>
                                <td class="p-3 text-right font-bold text-rose-600">
                                    -<?= formatRp($exp['amount']) ?>
                                </td>
                                <td class="p-3 text-gray-700 font-medium">
                                    <?= htmlspecialchars($exp['payment_method']) ?>
                                    <?php if (!empty($exp['account_name'])): ?>
                                    <span class="block text-[10px] text-gray-400"><?= htmlspecialchars($exp['account_name']) ?> (<?= htmlspecialchars($exp['account_code']) ?>)</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3 text-gray-500"><?= htmlspecialchars($exp['notes'] ?? '-') ?></td>
                                <td class="p-3 text-center pr-4 text-gray-400">
                                    <button onclick="deleteExpense(<?= $exp['id'] ?>)" title="Hapus Pengeluaran" class="text-red-400 hover:text-red-600 p-1 transition">
                                        <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ==================================================================== -->
        <?php if ($activeTab === 'inventori'): ?>
        <?php $allocationAvailable = false; $invSubTab = $allocationAvailable ? ($_GET['inv_sub'] ?? 'stok') : 'stok'; ?>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <a href="marketplace.php?store=<?= urlencode($activeStoreId) ?>&tab=dashboard" class="text-xs font-semibold text-gray-500 hover:text-brand-600 mb-1 inline-flex items-center gap-1">
                        &larr; Kembali ke Proyek/toko
                    </a>
                    <h1 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                        <span class="w-8 h-8 rounded-lg bg-orange-100 text-orange-600 flex items-center justify-center font-bold text-sm">&#128230;</span>
                        Inventori &amp; Stok
                    </h1>
                    <p class="text-xs text-gray-500">Proyek/Toko: <?= htmlspecialchars($activeStore['store_name']) ?> &bull; Gudang Utama GDG-UTAMA</p>
                </div>

                <div class="flex items-center gap-2">
                    <?php if ($allocationAvailable): ?><button onclick="runSafetyBufferSync()" class="px-3.5 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold transition shadow-xs flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        <span>Sinkron Safety Buffer</span>
                    </button><?php endif; ?>
                    <button onclick="window.print()" class="px-3.5 py-2 bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 rounded-xl text-xs font-bold transition shadow-sm">
                        Ekspor &darr;
                    </button>
                </div>
            </div>

            <!-- Sub-Tab Switcher: Stok Fisik vs Alokasi Kuota Multi-Channel -->
            <div class="flex items-center gap-2 border-b border-gray-200 pb-3">
                <a href="marketplace.php?store=<?= urlencode($activeStoreId) ?>&tab=inventori&inv_sub=stok" class="px-4 py-2 rounded-xl text-xs font-bold transition <?= $invSubTab === 'stok' ? 'bg-brand-600 text-white shadow-xs' : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-50' ?>">
                    📦 Stok Fisik &amp; HPP Konveksi
                </a>
                <?php if ($allocationAvailable): ?><a href="marketplace.php?store=<?= urlencode($activeStoreId) ?>&tab=inventori&inv_sub=alokasi" class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 <?= $invSubTab === 'alokasi' ? 'bg-brand-600 text-white shadow-xs' : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-50' ?>">
                    <span>🛡️ Alokasi Kuota Multi-Channel &amp; Safety Stock Buffer</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] <?= $invSubTab === 'alokasi' ? 'bg-white/20 text-white' : 'bg-amber-100 text-amber-800' ?> font-extrabold"><?= $stockAllocationsData['summary']['buffered_count'] ?> SKU Ter-Buffer</span>
                </a><?php endif; ?>
            </div>

            <?php if ($invSubTab === 'alokasi'): ?>
            <!-- SUB-VIEW: ALOKASI KUOTA MULTI-CHANNEL & SAFETY BUFFER -->
            <div class="space-y-4">
                <!-- 4 Ringkasan Kuota Multi-Channel -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm">
                        <span class="text-xs text-gray-400 font-semibold block">Total Fisik GDG-UTAMA</span>
                        <p class="text-2xl font-bold text-gray-900 mt-2"><?= number_format($stockAllocationsData['summary']['total_physical'], 0, ',', '.') ?> pcs</p>
                        <span class="text-xs text-gray-500 font-medium mt-1 block"><?= $stockAllocationsData['summary']['total_sku'] ?> SKU terdaftar di pabrik</span>
                    </div>
                    <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm">
                        <span class="text-xs text-gray-400 font-semibold block">Alokasi Shopee (60%)</span>
                        <p class="text-2xl font-bold text-orange-600 mt-2"><?= number_format($stockAllocationsData['summary']['total_shopee'], 0, ',', '.') ?> pcs</p>
                        <span class="text-xs text-emerald-600 font-semibold mt-1 block">&bull; Kuota Toko Online Shopee</span>
                    </div>
                    <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm">
                        <span class="text-xs text-gray-400 font-semibold block">Alokasi TikTok Shop (30%)</span>
                        <p class="text-2xl font-bold text-gray-900 mt-2"><?= number_format($stockAllocationsData['summary']['total_tiktok'], 0, ',', '.') ?> pcs</p>
                        <span class="text-xs text-emerald-600 font-semibold mt-1 block">&bull; Kuota Toko Online TikTok</span>
                    </div>
                    <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm">
                        <span class="text-xs text-gray-400 font-semibold block">Safety Buffer Aktif (Stok &le; 3)</span>
                        <p class="text-2xl font-bold text-amber-600 mt-2"><?= $stockAllocationsData['summary']['buffered_count'] ?> SKU</p>
                        <span class="text-xs text-amber-700 font-semibold mt-1 block">Otomatis Di-0-kan di Marketplace</span>
                    </div>
                </div>

                <!-- Banner Informasi Buffer -->
                <div class="p-4 bg-amber-50 border border-amber-200 rounded-2xl flex items-center justify-between text-xs text-amber-900 shadow-xs">
                    <div class="flex items-center gap-2.5">
                        <span class="w-6 h-6 rounded-full bg-amber-200 text-amber-800 flex items-center justify-center font-bold text-xs">🛡️</span>
                        <div>
                            <b>Safety Stock Buffer Guard Aktif:</b> Jika stok fisik di GDG-UTAMA tersisa &le; 3 pcs, sistem otomatis mengirimkan update stok online menjadi 0 ke Shopee &amp; TikTok API guna mencegah penalti keterlambatan pengiriman (overselling).
                        </div>
                    </div>
                    <button onclick="runSafetyBufferSync()" class="px-3.5 py-1.5 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-xl shadow-xs transition shrink-0 ml-3">
                        Evaluasi Ulang Buffer
                    </button>
                </div>

                <!-- Tabel Alokasi Multi-Channel -->
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs whitespace-nowrap">
                            <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider font-bold">
                                <tr>
                                    <th class="p-3 pl-4">SKU &amp; NAMA PRODUK</th>
                                    <th class="p-3 text-center">STOK FISIK PABRIK</th>
                                    <th class="p-3 text-center">KUOTA SHOPEE (60%)</th>
                                    <th class="p-3 text-center">KUOTA TIKTOK (30%)</th>
                                    <th class="p-3 text-center">OFFLINE (10%)</th>
                                    <th class="p-3 text-center">SAFETY BUFFER</th>
                                    <th class="p-3 text-center">STATUS ONLINE</th>
                                    <th class="p-3 text-center pr-4">AKSI</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach ($stockAllocationsData['allocations'] as $sa): ?>
                                <?php $isBuff = (int)$sa['is_safety_buffered'] === 1; ?>
                                <tr class="<?= $isBuff ? 'bg-amber-50/40 hover:bg-amber-50/70' : 'hover:bg-gray-50' ?> transition">
                                    <td class="p-3 pl-4">
                                        <span class="font-bold text-gray-900 block"><?= htmlspecialchars($sa['product_name']) ?></span>
                                        <span class="text-[11px] font-mono text-gray-400 block"><?= htmlspecialchars($sa['sku']) ?></span>
                                    </td>
                                    <td class="p-3 text-center">
                                        <span class="px-2.5 py-1 rounded-lg text-xs font-extrabold <?= $sa['total_physical_stock'] > 10 ? 'bg-emerald-100 text-emerald-800' : ($sa['total_physical_stock'] > 0 ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-700') ?>">
                                            <?= $sa['total_physical_stock'] ?> pcs
                                        </span>
                                    </td>
                                    <td class="p-3 text-center font-bold text-orange-600">
                                        <?= $sa['shopee_allocated_qty'] ?> pcs
                                        <span class="block text-[10px] text-gray-400"><?= number_format($sa['shopee_quota_pct'], 0) ?>%</span>
                                    </td>
                                    <td class="p-3 text-center font-bold text-gray-900">
                                        <?= $sa['tiktok_allocated_qty'] ?> pcs
                                        <span class="block text-[10px] text-gray-400"><?= number_format($sa['tiktok_quota_pct'], 0) ?>%</span>
                                    </td>
                                    <td class="p-3 text-center font-medium text-gray-600">
                                        <?= $sa['offline_allocated_qty'] ?> pcs
                                        <span class="block text-[10px] text-gray-400"><?= number_format($sa['offline_quota_pct'], 0) ?>%</span>
                                    </td>
                                    <td class="p-3 text-center">
                                        <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-gray-100 text-gray-700">
                                            Ambang: &le; <?= $sa['safety_buffer_threshold'] ?> pcs
                                        </span>
                                    </td>
                                    <td class="p-3 text-center">
                                        <?php if ($sa['status'] === 'BUFFERED_ZERO'): ?>
                                        <span class="px-2.5 py-0.5 rounded text-[10px] font-extrabold bg-amber-100 text-amber-900 border border-amber-300">
                                            🛡️ BUFFERED 0 DI ONLINE
                                        </span>
                                        <?php elseif ($sa['status'] === 'OUT_OF_STOCK'): ?>
                                        <span class="px-2.5 py-0.5 rounded text-[10px] font-extrabold bg-red-100 text-red-700 border border-red-300">
                                            HABIS (0 PCS)
                                        </span>
                                        <?php else: ?>
                                        <span class="px-2.5 py-0.5 rounded text-[10px] font-extrabold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                            AKTIF BERJUALAN
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-3 text-center pr-4">
                                        <button onclick="openStockAllocationModal(<?= $sa['id'] ?>, '<?= htmlspecialchars(addslashes($sa['product_name'])) ?>', '<?= htmlspecialchars($sa['sku']) ?>', <?= $sa['total_physical_stock'] ?>, <?= $sa['shopee_quota_pct'] ?>, <?= $sa['tiktok_quota_pct'] ?>, <?= $sa['offline_quota_pct'] ?>, <?= $sa['safety_buffer_threshold'] ?>)" class="px-2.5 py-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-lg text-xs transition">
                                            Atur Kuota
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php else: ?>
            <!-- SUB-VIEW: STOK FISIK ASLI & HPP (IDENTIK GAMBAR 3) -->
            <div class="space-y-4">
                <!-- Filter & Search Bar -->
                <div class="bg-white p-4 rounded-2xl border border-gray-200 flex flex-col md:flex-row gap-3 items-center justify-between text-xs shadow-xs">
                    <div class="flex items-center gap-3">
                        <input type="date" value="2026-10-07" class="px-3 py-1.5 bg-gray-50 border border-gray-200 rounded-xl text-xs">
                        <span class="text-gray-400 text-xs">s/d</span>
                        <input type="date" value="2026-10-21" class="px-3 py-1.5 bg-gray-50 border border-gray-200 rounded-xl text-xs">
                    </div>

                    <div class="flex items-center gap-2 w-full md:w-auto">
                        <input type="text" id="invSearchInput" value="<?= htmlspecialchars($invSearch ?? '') ?>" placeholder="Cari SKU atau Nama Produk..." onkeyup="if(event.key === 'Enter') applyInvFilter()" class="px-3 py-1.5 bg-gray-50 border border-gray-200 rounded-xl text-xs w-64 focus:outline-none focus:ring-1 focus:ring-brand-500">
                        <select id="invFilterSelect" onchange="applyInvFilter()" class="px-3 py-1.5 bg-gray-50 border border-gray-200 rounded-xl text-xs text-gray-700">
                            <option value="">Semua Level Stok</option>
                            <option value="stok_menipis" <?= $invFilter === 'stok_menipis' ? 'selected' : '' ?>>Stok Menipis (&lt; 10)</option>
                            <option value="habis" <?= $invFilter === 'habis' ? 'selected' : '' ?>>Stok Minus / Habis</option>
                            <option value="missing_hpp" <?= $invFilter === 'missing_hpp' ? 'selected' : '' ?>>HPP Kosong</option>
                        </select>
                        <button onclick="applyInvFilter()" class="px-3.5 py-1.5 bg-brand-600 text-white rounded-xl font-bold hover:bg-brand-700 transition">Cari</button>
                    </div>
                </div>

                <!-- Alert Yellow (Persis Image 3) -->
                <?php if ($inventoryData['missing_hpp_count'] > 0): ?>
                <div class="p-4 bg-amber-50 border border-amber-200 rounded-2xl flex items-center justify-between text-xs text-amber-900 shadow-xs">
                    <div class="flex items-center gap-2.5">
                        <span class="w-6 h-6 rounded-full bg-amber-200 text-amber-800 flex items-center justify-center font-bold text-xs">!</span>
                        <div>
                            <b><?= $inventoryData['missing_hpp_count'] ?> produk kekurangan data HPP</b>
                            <p class="text-[11px] text-amber-800">Klik nilai HPP di bawah atau gunakan tombol Edit untuk menambahkan data HPP.</p>
                        </div>
                    </div>
                    <button onclick="openHppModal('RANSEL-CARRIER-60L', 'Tas Ransel Carrier Gunung Outdoor 60L', 0)" class="px-3 py-1.5 bg-white border border-amber-300 rounded-xl font-bold text-amber-900 shadow-xs hover:bg-amber-100 transition">
                        Lihat HPP yang Hilang
                    </button>
                </div>
                <?php endif; ?>

                <!-- Table Inventori (Tampilan Persis Image 3) -->
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider font-bold">
                                <tr>
                                    <th class="p-3 pl-4">PRODUK</th>
                                    <th class="p-3 text-center">LEVEL STOK</th>
                                    <th class="p-3 text-center">MIN</th>
                                    <th class="p-3 text-center">MAX</th>
                                    <th class="p-3 text-center">TERJUAL</th>
                                    <th class="p-3 text-center">RETUR</th>
                                    <th class="p-3 text-center">CURRENT</th>
                                    <th class="p-3 text-center">STATUS</th>
                                    <th class="p-3 text-center pr-4">AKSI</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach ($inventoryData['items'] as $inv): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="p-3 pl-4">
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold text-gray-900"><?= htmlspecialchars($inv['product_name']) ?></span>
                                        </div>
                                        <div class="flex items-center gap-2 mt-0.5 text-[11px]">
                                            <span class="text-gray-400 font-mono"><?= htmlspecialchars($inv['sku']) ?></span>
                                            <span>&bull;</span>
                                            <span class="text-gray-500"><?= $inv['variants_display'] ?></span>
                                            <span>&bull;</span>
                                            <button onclick="openHppModal('<?= htmlspecialchars($inv['sku']) ?>', '<?= htmlspecialchars(addslashes($inv['product_name'])) ?>', <?= (float)$inv['active_hpp'] ?>, '<?= $inv['variant_id'] ?>')" class="font-bold <?= $inv['has_hpp'] ? 'text-emerald-600 hover:underline' : 'text-amber-600 font-black hover:underline' ?>">
                                                HPP: <?= $inv['has_hpp'] ? formatRp($inv['active_hpp']) : 'Belum Diatur &#9888;' ?>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="p-3 text-center">
                                        <span class="px-2 py-0.5 rounded text-[11px] font-bold <?= $inv['current_warehouse_stock'] > 10 ? 'bg-emerald-100 text-emerald-800' : ($inv['current_warehouse_stock'] > 0 ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-700') ?>">
                                            <?= $inv['current_warehouse_stock'] ?> pcs
                                        </span>
                                    </td>
                                    <td class="p-3 text-center font-medium text-gray-500">0</td>
                                    <td class="p-3 text-center text-gray-400">-</td>
                                    <td class="p-3 text-center font-bold text-gray-800"><?= $inv['terjual'] ?></td>
                                    <td class="p-3 text-center text-gray-500"><?= $inv['retur'] ?></td>
                                    <td class="p-3 text-center font-bold <?= $inv['current'] < 0 ? 'text-rose-600' : 'text-gray-800' ?>"><?= $inv['current'] ?></td>
                                    <td class="p-3 text-center">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-gray-100 text-gray-700"><?= $inv['status'] ?></span>
                                    </td>
                                    <td class="p-3 text-center pr-4">
                                        <button onclick="openHppModal('<?= htmlspecialchars($inv['sku']) ?>', '<?= htmlspecialchars(addslashes($inv['product_name'])) ?>', <?= (float)$inv['active_hpp'] ?>, '<?= $inv['variant_id'] ?>')" title="Atur HPP &amp; Mapping SKU" class="text-gray-400 hover:text-brand-600 p-1 transition">
                                            <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- ==================================================================== -->
        <?php if ($activeTab === 'pemasukan' && ($_GET['pem_sub']??'')!=='jurnal'): ?>
        <?php require (dirname(__DIR__, 4) . '/app/Modules/Marketplace/Views/marketplace_income.php'); ?>
        <?php elseif ($activeTab === 'pemasukan'): ?>
        <?php $pemSubTab = $_GET['pem_sub'] ?? 'escrow'; ?>
        <div class="space-y-6">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <a href="marketplace.php?store=<?= urlencode($activeStoreId) ?>&tab=dashboard" class="text-xs font-semibold text-gray-500 hover:text-brand-600 mb-1 inline-flex items-center gap-1">
                        &larr; Kembali ke Proyek/toko
                    </a>
                    <h1 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                        <span class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-600 flex items-center justify-center font-bold text-sm">&#128176;</span>
                        Pemasukan, Escrow &amp; Buku Jurnal Berpasangan
                    </h1>
                    <p class="text-xs text-gray-500">Rekonsiliasi escrow dan jurnal khusus Marketplace</p>
                </div>

                <div class="flex items-center gap-2">
                    <span class="rounded-xl border border-amber-200 bg-amber-50 px-3.5 py-2 text-xs font-semibold text-amber-900">Impor settlement menunggu rekening Marketplace terpisah</span>
                    <button onclick="window.print()" class="px-3.5 py-2 bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 rounded-xl text-xs font-bold transition shadow-sm">
                        Ekspor &darr;
                    </button>
                </div>
            </div>

            <!-- Sub-Tab Switcher: Escrow vs Buku Jurnal -->
            <div class="flex items-center gap-2 border-b border-gray-200 pb-3">
                <a href="marketplace.php?store=<?= urlencode($activeStoreId) ?>&tab=pemasukan&pem_sub=escrow" class="px-4 py-2 rounded-xl text-xs font-bold transition <?= $pemSubTab === 'escrow' ? 'bg-brand-600 text-white shadow-xs' : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-50' ?>">
                    🏦 Rekonsiliasi Pencairan Escrow Bank
                </a>
                <a href="marketplace.php?store=<?= urlencode($activeStoreId) ?>&tab=pemasukan&pem_sub=jurnal" class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 <?= $pemSubTab === 'jurnal' ? 'bg-brand-600 text-white shadow-xs' : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-50' ?>">
                    <span>📖 Buku Jurnal Berpasangan (Double-Entry Ledger)</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] <?= $pemSubTab === 'jurnal' ? 'bg-white/20 text-white' : 'bg-emerald-100 text-emerald-800' ?> font-extrabold">Buku Jurnal</span>
                </a>
            </div>

            <?php if ($pemSubTab === 'jurnal'): ?>
            <!-- SUB-VIEW: BUKU JURNAL BERPASANGAN -->
            <div class="space-y-4">
                <!-- 3 Kartu Integritas Akuntansi -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm">
                        <span class="text-xs text-gray-400 font-semibold block">Total Saldo Debit</span>
                        <p class="text-2xl font-bold text-gray-900 mt-2"><?= formatRp($journalData['total_debit']) ?></p>
                        <span class="text-xs text-gray-500 font-medium mt-1 block">Kas/Bank, Beban Pokok &amp; Admin</span>
                    </div>
                    <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm">
                        <span class="text-xs text-gray-400 font-semibold block">Total Saldo Kredit</span>
                        <p class="text-2xl font-bold text-gray-900 mt-2"><?= formatRp($journalData['total_credit']) ?></p>
                        <span class="text-xs text-gray-500 font-medium mt-1 block">Pendapatan Bruto &amp; Persediaan</span>
                    </div>
                    <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm">
                        <span class="text-xs text-gray-400 font-semibold block">Status Keseimbangan (Audit)</span>
                        <p class="text-xl font-extrabold mt-2 flex items-center gap-1.5 <?= $journalData['is_balanced'] ? 'text-emerald-600' : 'text-rose-600' ?>">
                            <span class="w-3 h-3 rounded-full <?= $journalData['is_balanced'] ? 'bg-emerald-500' : 'bg-rose-500' ?>"></span>
                            <?= $journalData['is_balanced'] ? 'Seimbang' : 'Perlu rekonsiliasi' ?>
                        </p>
                        <span class="text-xs text-gray-600 font-semibold mt-1 block">Selisih: <?= formatRp(abs((float)$journalData['total_debit'] - (float)$journalData['total_credit'])) ?> dari <?= (int)$journalData['count'] ?> baris jurnal yang ditampilkan</span>
                    </div>
                </div>

                <!-- Info Box SOP Jurnal ERP Konveksi -->
                <div class="p-4 bg-blue-50 border border-blue-200 rounded-2xl text-xs text-blue-900 leading-relaxed shadow-xs">
                    <b>Standar Integrasi Jurnal Berpasangan (Double-Entry Bookkeeping):</b>
                    <ul class="list-disc list-inside mt-1.5 space-y-1 text-[11px] text-blue-800">
                        <li><b>Saat Pesanan Marketplace Selesai:</b> Debit [1102 Piutang Escrow], Kredit [4101 Pendapatan Penjualan Bruto], Debit [5101 Beban Pokok Penjualan HPP], Kredit [1104 Persediaan Barang Jadi Gudang].</li>
                        <li><b>Saat Dana Escrow Cair Masuk Rekening (SeaBank/Mandiri):</b> Debit [1101 Kas/Bank Toko], Debit [5201 Beban Admin Platform], Debit [5202 Selisih Ongkir / Biaya Layanan], Debit [5301 Beban Iklan Marketplace], Kredit [1102 Piutang Escrow Marketplace].</li>
                    </ul>
                </div>

                <!-- Tabel Jurnal Berpasangan -->
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs whitespace-nowrap">
                            <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider font-bold">
                                <tr>
                                    <th class="p-3 pl-4">NO. JURNAL</th>
                                    <th class="p-3">TANGGAL</th>
                                    <th class="p-3">TIPE &amp; REF</th>
                                    <th class="p-3">KODE AKUN</th>
                                    <th class="p-3">NAMA AKUN</th>
                                    <th class="p-3 text-right">DEBIT (RP)</th>
                                    <th class="p-3 text-right">KREDIT (RP)</th>
                                    <th class="p-3 pr-4">MEMO / KETERANGAN</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 font-mono text-[11px]">
                                <?php foreach ($journalData['entries'] as $j): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="p-3 pl-4 font-bold text-gray-900"><?= htmlspecialchars($j['journal_no']) ?></td>
                                    <td class="p-3 text-gray-600"><?= htmlspecialchars($j['entry_date']) ?></td>
                                    <td class="p-3">
                                        <span class="px-2 py-0.5 rounded font-bold text-[10px] <?= $j['ref_type'] === 'ORDER_COMPLETED' ? 'bg-blue-100 text-blue-800' : ($j['ref_type'] === 'ESCROW_PAYOUT' ? 'bg-emerald-100 text-emerald-800' : 'bg-purple-100 text-purple-800') ?>">
                                            <?= htmlspecialchars($j['ref_type']) ?>
                                        </span>
                                        <span class="block text-[10px] text-gray-400 mt-0.5"><?= htmlspecialchars(substr($j['ref_id'], 0, 18)) ?></span>
                                    </td>
                                    <td class="p-3 font-bold text-gray-700"><?= htmlspecialchars($j['account_code']) ?></td>
                                    <td class="p-3 text-gray-900 font-sans font-medium"><?= htmlspecialchars($j['account_name']) ?></td>
                                    <td class="p-3 text-right font-bold text-emerald-600">
                                        <?= (float)$j['debit'] > 0 ? formatRp($j['debit']) : '-' ?>
                                    </td>
                                    <td class="p-3 text-right font-bold text-blue-600">
                                        <?= (float)$j['credit'] > 0 ? formatRp($j['credit']) : '-' ?>
                                    </td>
                                    <td class="p-3 pr-4 text-gray-500 font-sans"><?= htmlspecialchars($j['memo'] ?? '-') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php else: ?>
            <!-- SUB-VIEW: ESCROW BANK RECONCILIATION -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
                    <span class="text-xs font-bold text-gray-400 uppercase">Total Dana Masuk Settlement</span>
                    <p class="text-3xl font-extrabold text-emerald-600 mt-2"><?= formatRp($funnel['laba_bersih']['val']===null?null:$funnel['laba_bersih']['val'] + $funnel['hpp']['val']) ?></p>
                    <p class="text-xs text-gray-500 mt-1">Perkiraan dari catatan Marketplace; verifikasi dengan mutasi bank</p>
                </div>
                <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
                    <span class="text-xs font-bold text-gray-400 uppercase">Rekening Bank Tujuan</span>
                    <p class="text-xl font-extrabold text-gray-900 mt-2"><?= htmlspecialchars((string)($activeStore['default_account_name'] ?? 'Belum diatur')) ?><?= !empty($activeStore['default_account_code']) ? ' (' . htmlspecialchars((string)$activeStore['default_account_code']) . ')' : '' ?></p>
                    <span class="text-xs text-amber-700 font-bold mt-1 block">&bull; Belum direkonsiliasi dengan rekening marketplace</span>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- ==================================================================== -->
        <?php if ($activeTab === 'analitik') require dirname(__DIR__).'/Views/marketplace_store_analytics.php'; ?>

        <?php if ($activeTab === 'marketing'): ?>
        <?php require (dirname(__DIR__, 4) . '/app/Modules/Marketplace/Views/marketplace_advertising_center.php'); ?>
        <?php endif; ?>

        <!-- ==================================================================== -->
        <!-- TAB: RIWAYAT SINKRONISASI                                           -->
        <!-- ==================================================================== -->
        <?php if ($activeTab === 'sync'): ?>
            <?php require (dirname(__DIR__, 4) . '/app/Modules/Marketplace/Views/marketplace_sync.php'); ?>
        <?php endif; ?>

        <!-- TAB 8: PENGATURAN KEUANGAN & MANAJEMEN MULTI-TOKO MARKETPLACE        -->
        <!-- ==================================================================== -->
        <?php if ($activeTab === 'pengaturan'): ?>
        <?php $cfg = $appSettingsData['flat']; ?>
        <div class="space-y-6">
            <!-- Header Bar -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-gray-200">
                <div>
                    <h2 class="text-xl font-black text-gray-900 tracking-tight flex items-center gap-2">
                        <svg class="w-6 h-6 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        Pengaturan Keuangan &amp; Toko Marketplace
                    </h2>
                    <p class="text-xs text-gray-500 mt-0.5">Kelola integrasi multi-toko (Shopee &amp; TikTok Shop), tambah channel penjualan, dan atur parameter akuntansi &amp; stok gudang pabrik</p>
                </div>

                <div class="flex items-center gap-2">
                    <a href="marketplace.php?tab=pengaturan&peng_sub=tambah_toko" class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-xl text-xs font-bold transition shadow-xs flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span>Tambah Toko Baru</span>
                    </a>
                </div>
            </div>

            <?php require (dirname(__DIR__, 4) . '/app/Modules/Marketplace/Views/marketplace_settings_tabs.php'); ?>

            <!-- ================================================================ -->
            <!-- SUB-TAB 1: DAFTAR & MANAJEMEN TOKO                               -->
            <!-- ================================================================ -->
            <?php if ($pengSubTab === 'toko'): ?>
            <div class="space-y-6">
                <!-- 4 KPI Summary Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="p-4 bg-white rounded-2xl border border-gray-200 shadow-2xs space-y-1">
                        <span class="text-xs text-gray-500 font-bold block uppercase tracking-wider">Toko Terverifikasi</span>
                        <div class="flex items-baseline gap-2">
                            <span class="text-2xl font-black text-gray-900"><?= $allStoresData['summary']['total_stores'] ?></span>
                            <span class="text-xs text-gray-500 font-semibold">Toko Multi-Channel</span>
                        </div>
                        <span class="text-[11px] text-emerald-600 font-semibold block">Shopee &amp; TikTok Shop</span>
                    </div>

                    <div class="p-4 bg-white rounded-2xl border border-gray-200 shadow-2xs space-y-1">
                        <span class="text-xs text-gray-500 font-bold block uppercase tracking-wider">Toko Aktif Beroperasi</span>
                        <div class="flex items-baseline gap-2">
                            <span class="text-2xl font-black text-emerald-600"><?= $allStoresData['summary']['active_stores'] ?></span>
                            <span class="text-xs text-emerald-700 font-semibold">/ <?= $allStoresData['summary']['total_stores'] ?> Online</span>
                        </div>
                        <span class="text-[11px] text-gray-400 block">Status koneksi verified</span>
                    </div>

                    <div class="p-4 bg-white rounded-2xl border border-gray-200 shadow-2xs space-y-1">
                        <span class="text-xs text-gray-500 font-bold block uppercase tracking-wider">Akumulasi Transaksi</span>
                        <div class="flex items-baseline gap-2">
                            <span class="text-2xl font-black text-blue-600"><?= number_format($allStoresData['summary']['total_orders']) ?></span>
                            <span class="text-xs text-gray-500 font-semibold">Pesanan Masuk</span>
                        </div>
                        <span class="text-[11px] text-blue-500 block">Catatan pesanan Marketplace; tidak mengubah stok Konveksi</span>
                    </div>

                    <div class="p-4 bg-white rounded-2xl border border-gray-200 shadow-2xs space-y-1">
                        <span class="text-xs text-gray-500 font-bold block uppercase tracking-wider">Total Settlement Multi-Toko</span>
                        <div class="flex items-baseline gap-1">
                            <span class="text-xl font-black text-gray-900 font-mono"><?= formatRp($allStoresData['summary']['total_settlement']) ?></span>
                        </div>
                        <span class="text-[11px] text-gray-400 block">Tidak otomatis masuk kas/bank Konveksi</span>
                    </div>
                </div>

                <!-- Tabel Toko Marketplace -->
                <div class="bg-white rounded-3xl border border-gray-200 overflow-hidden shadow-2xs">
                    <div class="p-4 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div>
                            <h3 class="font-extrabold text-gray-900 text-sm">Channel Penjualan Marketplace Aktif</h3>
                            <p class="text-[11px] text-gray-500">Daftar toko Marketplace; status koneksi API ditampilkan per toko</p>
                        </div>
                        <a href="marketplace.php?tab=pengaturan&peng_sub=tambah_toko" class="px-3.5 py-1.5 bg-brand-50 hover:bg-brand-100 text-brand-700 font-bold rounded-xl text-xs transition flex items-center gap-1.5 shrink-0">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            <span>Tambah Toko</span>
                        </a>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs whitespace-nowrap">
                            <thead class="bg-gray-50/75 text-gray-500 uppercase tracking-wider font-bold border-b border-gray-100">
                                <tr>
                                    <th class="p-3.5 pl-6">TOKO &amp; PLATFORM</th>
                                    <th class="p-3.5">REKENING PENCAIRAN DEFAULT</th>
                                    <th class="p-3.5 text-center">KOMISI PLATFORM</th>
                                    <th class="p-3.5 text-center">MODE &amp; STATUS KONEKSI</th>
                                    <th class="p-3.5 text-center">TOTAL PESANAN</th>
                                    <th class="p-3.5 text-center pr-6">AKSI &amp; KELOLA</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach ($allStoresData['stores'] as $st): ?>
                                <?php 
                                    $isShopee = $st['platform'] === 'Shopee';
                                    $isTikTok = $st['platform'] === 'TikTok Shop';
                                    $isTokopedia = $st['platform'] === 'Tokopedia';
                                    $badgeBg = $isShopee ? 'bg-orange-500' : ($isTikTok ? 'bg-black' : ($isTokopedia ? 'bg-emerald-600' : 'bg-blue-600'));
                                    $isConnected = false; // Legacy status is not an API verification result.
                                ?>
                                <tr class="hover:bg-gray-50/80 transition">
                                    <td class="p-3.5 pl-6">
                                        <div class="flex items-center gap-3">
                                            <div class="w-9 h-9 rounded-xl <?= $badgeBg ?> text-white flex items-center justify-center font-black text-xs shadow-xs shrink-0">
                                                <?= $isShopee ? 'S' : ($isTikTok ? 'TT' : substr($st['platform'], 0, 2)) ?>
                                            </div>
                                            <div>
                                                <div class="flex items-center gap-1.5">
                                                    <span class="font-extrabold text-gray-900 block"><?= htmlspecialchars($st['store_name']) ?></span>
                                                    <?php if ($st['id'] === $activeStoreId): ?>
                                                    <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-brand-100 text-brand-700">Aktif Dipilih</span>
                                                    <?php endif; ?>
                                                </div>
                                                <span class="text-[11px] text-gray-400 font-mono block">ID: <?= htmlspecialchars($st['id']) ?> &bull; @<?= htmlspecialchars($st['store_identifier']) ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-3.5">
                                        <div>
                                            <span class="font-bold text-gray-800 block flex items-center gap-1">
                                                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                                                <?= htmlspecialchars($st['default_account_name'] ?? 'SeaBank / Kas Toko') ?>
                                            </span>
                                            <span class="text-[11px] text-gray-400 font-mono block">Kode Akun: <?= htmlspecialchars($st['default_account_code'] ?? '1101') ?></span>
                                        </div>
                                    </td>
                                    <td class="p-3.5 text-center">
                                        <div class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-gray-50 border border-gray-200 rounded-lg text-[11px]">
                                            <span class="text-gray-600" title="Admin Star">Adm: <b><?= (float)$st['default_admin_fee_pct'] ?>%</b></span>
                                            <span class="text-gray-300">|</span>
                                            <span class="text-blue-600" title="Gratis Ongkir Xtra">Ongkir: <b><?= (float)$st['default_free_shipping_pct'] ?>%</b></span>
                                            <span class="text-gray-300">|</span>
                                            <span class="text-purple-600" title="Payment Fee">Pay: <b><?= (float)$st['default_payment_fee_pct'] ?>%</b></span>
                                        </div>
                                    </td>
                                    <td class="p-3.5 text-center">
                                        <div class="space-y-1">
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold <?= $isConnected ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-red-100 text-red-800 border border-red-200' ?>">
                                                <span class="w-1.5 h-1.5 rounded-full <?= $isConnected ? 'bg-emerald-500' : 'bg-red-500' ?>"></span>
                                                <?= htmlspecialchars($st['status']) ?>
                                            </span>
                                            <span class="block text-[10px] text-gray-400 font-mono"><?= htmlspecialchars($st['sync_mode']) ?></span>
                                        </div>
                                    </td>
                                    <td class="p-3.5 text-center font-bold text-gray-800 font-mono">
                                        <?= number_format($st['total_orders_display']) ?>
                                    </td>
                                    <td class="p-3.5 text-center pr-6">
                                        <div class="inline-flex items-center gap-1.5">
                                            <button onclick="openEditStoreModal('<?= htmlspecialchars($st['id']) ?>')" class="px-2.5 py-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-lg text-xs transition" title="Edit Rincian Toko">
                                                Edit
                                            </button>
                                            <button onclick="openApiModal()" class="px-2.5 py-1 bg-blue-50 hover:bg-blue-100 text-blue-700 font-bold rounded-lg text-xs transition" title="Atur Kredensial API &amp; Webhook">
                                                API
                                            </button>
                                            <button onclick="pingApi('<?= htmlspecialchars($st['id']) ?>')" class="px-2.5 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-bold rounded-lg text-xs transition" title="Uji Koneksi API Real-Time">
                                                Ping
                                            </button>
                                            <button onclick="toggleStoreStatus('<?= htmlspecialchars($st['id']) ?>', '<?= htmlspecialchars($st['status']) ?>')" class="px-2.5 py-1 rounded-lg text-xs font-bold transition <?= $isConnected ? 'bg-rose-50 text-rose-600 hover:bg-rose-100' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' ?>" title="Ubah status toko">
                                                <?= $isConnected ? 'Nonaktifkan' : 'Aktifkan' ?>
                                            </button>
                                            <?php if ($canManageStoreArchive): ?>
                                            <button type="button" onclick="archiveStore(this, <?= htmlspecialchars(json_encode((string)$st['id']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode((string)$st['store_name']), ENT_QUOTES, 'UTF-8') ?>)" class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-rose-600 bg-rose-50 hover:bg-rose-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-600 disabled:opacity-50" aria-label="Hapus toko <?= htmlspecialchars($st['store_name']) ?> dari daftar aktif" title="Hapus toko dari daftar aktif">
                                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2m3 0-1 14H6L5 6m5 4v7m4-7v7"/></svg>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php if ($canManageStoreArchive && $archivedStores): ?>
                <details class="bg-white rounded-2xl border border-gray-200 p-4 text-xs">
                    <summary class="cursor-pointer font-bold text-gray-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-600">Toko dihapus dari daftar (<?= count($archivedStores) ?>)</summary>
                    <div class="mt-3 space-y-2">
                        <?php foreach ($archivedStores as $archivedStore): ?>
                        <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-gray-100 p-3">
                            <div><strong><?= htmlspecialchars($archivedStore['store_name']) ?></strong> <span class="text-gray-500">&bull; <?= htmlspecialchars($archivedStore['platform']) ?> &bull; <?= htmlspecialchars($archivedStore['archived_at']) ?></span></div>
                            <button type="button" onclick="restoreStore(this, <?= htmlspecialchars(json_encode((string)$archivedStore['id']), ENT_QUOTES, 'UTF-8') ?>)" class="rounded-lg border border-gray-200 px-3 py-2 font-bold hover:bg-gray-50 disabled:opacity-50">Tampilkan kembali</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </details>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- ================================================================ -->
            <!-- SUB-TAB 2: FORM TAMBAH TOKO BARU                                -->
            <!-- ================================================================ -->
            <?php if ($pengSubTab === 'tambah_toko'): ?>
            <div class="max-w-4xl mx-auto space-y-6">
                <form id="formAddStore" onsubmit="submitAddStore(event)" class="bg-white rounded-3xl border border-gray-200 p-6 sm:p-8 shadow-2xs space-y-6 text-xs">
                    <div class="border-b border-gray-100 pb-4">
                        <h3 class="text-base font-extrabold text-gray-900">Form Pendaftaran Toko Marketplace Baru</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Daftarkan toko online baru di Shopee, TikTok Shop atau Tokopedia untuk dihubungkan ke pembukuan Jago Laba</p>
                    </div>

                    <!-- Informasi Dasar Toko -->
                    <div class="space-y-4">
                        <span class="font-extrabold text-gray-900 text-xs uppercase tracking-wider block border-l-4 border-brand-600 pl-2">
                            1. Informasi Identitas Toko
                        </span>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="font-bold text-gray-700 block mb-1">Platform Marketplace *</label>
                                <select name="platform" required class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl font-bold text-gray-900 focus:ring-1 focus:ring-brand-500">
                                    <option value="Shopee">Shopee Marketplace</option>
                                    <option value="TikTok Shop">TikTok Shop</option>
                                    <option value="Tokopedia">Tokopedia / ShopTokopedia</option>
                                    <option value="Lazada">Lazada Indonesia</option>
                                    <option value="Blibli">Blibli Marketplace</option>
                                    <option value="Offline Store">Penjualan Grosir Offline Pabrik</option>
                                </select>
                            </div>
                            <div>
                                <label class="font-bold text-gray-700 block mb-1">Kode / ID Toko (Opsional, Sistem akan auto-generate jika kosong)</label>
                                <input type="text" name="id" placeholder="Contoh: STR-SHOPEE-BAGS-02" class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl font-mono text-xs uppercase focus:ring-1 focus:ring-brand-500">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="font-bold text-gray-700 block mb-1">Nama Toko Display *</label>
                                <input type="text" name="store_name" placeholder="Contoh: JAGOIN Apparel &amp; Bags Official" required class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl font-bold text-gray-900 focus:ring-1 focus:ring-brand-500">
                            </div>
                            <div>
                                <label class="font-bold text-gray-700 block mb-1">Username Seller Center / Identifier *</label>
                                <input type="text" name="store_identifier" placeholder="Contoh: jagoin_official_garut" required class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl font-mono text-xs focus:ring-1 focus:ring-brand-500">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="font-bold text-gray-700 block mb-1">Rekening Kas/Bank Penampung Hasil Pencairan Escrow *</label>
                                <select name="default_account_id" required class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl font-bold text-emerald-800 focus:ring-1 focus:ring-brand-500">
                                    <option value="">-- Pilih Rekening Kas / Bank Pabrik --</option>
                                    <?php foreach ($accounts as $acc): ?>
                                    <option value="<?= $acc['id'] ?>">
                                        <?= htmlspecialchars($acc['name']) ?> (<?= htmlspecialchars($acc['code']) ?>) - Saldo: <?= formatRp($acc['current_balance']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="text-[11px] text-gray-400 mt-1 block">Semua dana cair pesanan toko ini otomatis dibukukan ke rekening ini.</span>
                            </div>
                            <div>
                                <label class="font-bold text-gray-700 block mb-1">Mode Sinkronisasi Awal *</label>
                                <select name="sync_mode" required class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl font-semibold focus:ring-1 focus:ring-brand-500">
                                    <option value="API">API Resmi (OAuth2.0 Token Sync)</option>
                                    <option value="EXCEL_SETTLEMENT">Impor File Settlement CSV / Excel</option>
                                    <option value="HYBRID">Hybrid (API Realtime + Backup Settlement Excel)</option>
                                </select>
                                <span class="text-[11px] text-gray-400 mt-1 block">Dapat disesuaikan sewaktu-waktu sesuai kesiapan koneksi API toko.</span>
                            </div>
                        </div>
                    </div>

                    <!-- Parameter Komisi Default Toko -->
                    <div class="space-y-4 pt-2 border-t border-gray-100">
                        <span class="font-extrabold text-gray-900 text-xs uppercase tracking-wider block border-l-4 border-brand-600 pl-2">
                            2. Estimasi Komisi &amp; Biaya Platform Bawaan
                        </span>

                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="font-bold text-gray-700 block mb-1">Biaya Administrasi (%)</label>
                                <div class="flex items-center">
                                    <input type="number" name="default_admin_fee_pct" value="11.0" step="0.1" required class="w-full p-2 bg-gray-50 border border-gray-200 rounded-xl text-center font-bold text-gray-900">
                                    <span class="ml-1.5 text-gray-400 font-bold">%</span>
                                </div>
                                <span class="text-[10px] text-gray-400 block mt-0.5">Komisi Star/Mall</span>
                            </div>
                            <div>
                                <label class="font-bold text-gray-700 block mb-1">Gratis Ongkir Xtra (%)</label>
                                <div class="flex items-center">
                                    <input type="number" name="default_free_shipping_pct" value="4.0" step="0.1" required class="w-full p-2 bg-gray-50 border border-gray-200 rounded-xl text-center font-bold text-blue-600">
                                    <span class="ml-1.5 text-gray-400 font-bold">%</span>
                                </div>
                                <span class="text-[10px] text-gray-400 block mt-0.5">Biaya Xtra Ongkir</span>
                            </div>
                            <div>
                                <label class="font-bold text-gray-700 block mb-1">Biaya Layanan/Pay (%)</label>
                                <div class="flex items-center">
                                    <input type="number" name="default_payment_fee_pct" value="1.0" step="0.1" required class="w-full p-2 bg-gray-50 border border-gray-200 rounded-xl text-center font-bold text-purple-600">
                                    <span class="ml-1.5 text-gray-400 font-bold">%</span>
                                </div>
                                <span class="text-[10px] text-gray-400 block mt-0.5">Penanganan Payment</span>
                            </div>
                        </div>
                    </div>

                    <!-- Kredensial API manual dinonaktifkan; koneksi baru memakai modal OAuth global. -->
                    <div class="hidden space-y-4 pt-2 border-t border-gray-100" aria-hidden="true">
                        <span class="font-extrabold text-gray-900 text-xs uppercase tracking-wider block border-l-4 border-brand-600 pl-2">
                            3. Kredensial API Resmi (Opsional / Dapat Diisi Nanti)
                        </span>

                        <div class="p-4 bg-gray-50 rounded-2xl border border-gray-200 space-y-3">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="font-semibold text-gray-700 block mb-1">Partner ID / App Key</label>
                                    <input type="text" name="partner_id" placeholder="Contoh: 1084428" class="w-full p-2 bg-white border border-gray-200 rounded-xl font-mono text-xs">
                                </div>
                                <div>
                                    <label class="font-semibold text-gray-700 block mb-1">Partner Key / App Secret</label>
                                    <input type="password" name="partner_key" placeholder="••••••••••••••••" class="w-full p-2 bg-white border border-gray-200 rounded-xl font-mono text-xs">
                                </div>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="font-semibold text-gray-700 block mb-1">Shop ID / Seller Cipher</label>
                                    <input type="text" name="shop_id" placeholder="Contoh: 9845112" class="w-full p-2 bg-white border border-gray-200 rounded-xl font-mono text-xs">
                                </div>
                                <div>
                                    <label class="font-semibold text-gray-700 block mb-1">Access Token OAuth2 (Jika sudah ada)</label>
                                    <input type="password" name="access_token" placeholder="Bearer access_token" class="w-full p-2 bg-white border border-gray-200 rounded-xl font-mono text-xs">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="pt-4 border-t border-gray-100 flex items-center justify-end gap-3">
                        <a href="marketplace.php?tab=pengaturan&peng_sub=toko" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-xl transition">
                            Batal
                        </a>
                        <button type="submit" id="btnSubmitAddStore" class="px-6 py-2.5 bg-brand-600 hover:bg-brand-700 text-white font-bold rounded-xl shadow-xs transition flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <span>Daftarkan Toko Baru</span>
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- ================================================================ -->
            <!-- SUB-TAB 3: PENGATURAN APLIKASI GLOBAL                           -->
            <!-- ================================================================ -->
            <?php if ($pengSubTab === 'aplikasi'): ?>
            <div class="space-y-6">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 text-xs">
                    <!-- CARD 1: MANAJEMEN STOK PABRIK & SAFETY BUFFER -->
                    <form onsubmit="submitAppSettings(event)" class="bg-white rounded-3xl border border-gray-200 p-6 shadow-2xs space-y-4 flex flex-col justify-between">
                        <div class="space-y-4">
                            <div class="flex items-center gap-2.5 border-b pb-3">
                                <div class="w-8 h-8 rounded-lg bg-orange-100 text-orange-600 flex items-center justify-center font-bold text-sm">
                                    &#128230;
                                </div>
                                <div>
                                    <h4 class="font-extrabold text-gray-900 text-sm">Manajemen Stok &amp; Safety Buffer Gudang</h4>
                                    <p class="text-[11px] text-gray-500">Penyelarasan stok fisik Garut (GDG-UTAMA) dengan kuota online</p>
                                </div>
                            </div>

                            <div class="space-y-3">
                                <div>
                                    <label class="font-bold text-gray-700 block mb-1">Ambang Batas Safety Buffer (pcs)</label>
                                    <input type="number" name="default_safety_buffer_threshold" value="<?= htmlspecialchars($cfg['default_safety_buffer_threshold'] ?? '3') ?>" required min="1" max="50" class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl font-bold text-amber-700">
                                    <span class="text-[10px] text-gray-400 mt-1 block">Jika stok fisik gudang &le; nilai ini, kuota Shopee &amp; TikTok otomatis di-0-kan.</span>
                                </div>

                                <div class="space-y-1">
                                    <label class="font-bold text-gray-700 block">Alokasi Default Multi-Channel (%):</label>
                                    <div class="grid grid-cols-3 gap-2">
                                        <div>
                                            <span class="text-[10px] text-gray-500 block">Shopee (%)</span>
                                            <input type="number" name="default_stock_allocation_shopee" value="<?= htmlspecialchars($cfg['default_stock_allocation_shopee'] ?? '60') ?>" required class="w-full p-2 bg-gray-50 border border-gray-200 rounded-xl font-bold text-orange-600 text-center">
                                        </div>
                                        <div>
                                            <span class="text-[10px] text-gray-500 block">TikTok (%)</span>
                                            <input type="number" name="default_stock_allocation_tiktok" value="<?= htmlspecialchars($cfg['default_stock_allocation_tiktok'] ?? '30') ?>" required class="w-full p-2 bg-gray-50 border border-gray-200 rounded-xl font-bold text-gray-900 text-center">
                                        </div>
                                        <div>
                                            <span class="text-[10px] text-gray-500 block">Offline (%)</span>
                                            <input type="number" name="default_stock_allocation_offline" value="<?= htmlspecialchars($cfg['default_stock_allocation_offline'] ?? '10') ?>" required class="w-full p-2 bg-gray-50 border border-gray-200 rounded-xl font-bold text-blue-600 text-center">
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label class="font-bold text-gray-700 block mb-1">Otomatis Zeroing Stok Online di Marketplace</label>
                                    <select name="auto_zero_on_safety_buffer" class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl font-bold text-gray-800">
                                        <option value="1" <?= ($cfg['auto_zero_on_safety_buffer'] ?? '1') == '1' ? 'selected' : '' ?>>Aktif (Kirim Perintah Stock = 0 ke API Saat Menyentuh Buffer)</option>
                                        <option value="0" <?= ($cfg['auto_zero_on_safety_buffer'] ?? '1') == '0' ? 'selected' : '' ?>>Nonaktif (Hanya Beri Peringatan di Dashboard)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="pt-3 border-t flex justify-end">
                            <button type="submit" class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white font-bold rounded-xl transition shadow-xs">
                                Simpan Parameter Stok
                            </button>
                        </div>
                    </form>

                    <!-- CARD 2: DETEKSI BONCOS & EARLY WARNING MARKETING -->
                    <form onsubmit="submitAppSettings(event)" class="bg-white rounded-3xl border border-gray-200 p-6 shadow-2xs space-y-4 flex flex-col justify-between">
                        <div class="space-y-4">
                            <div class="flex items-center gap-2.5 border-b pb-3">
                                <div class="w-8 h-8 rounded-lg bg-rose-100 text-rose-600 flex items-center justify-center font-bold text-sm">
                                    &#128680;
                                </div>
                                <div>
                                    <h4 class="font-extrabold text-gray-900 text-sm">Deteksi Boncos &amp; Early Warning Iklan</h4>
                                    <p class="text-[11px] text-gray-500">Aturan pencegahan kebocoran budget Shopee Ads &amp; TikTok Ads</p>
                                </div>
                            </div>

                            <div class="space-y-3">
                                <div>
                                    <label class="font-bold text-gray-700 block mb-1">Ambang Batas Biaya Iklan Tanpa Order 24 Jam (Rp)</label>
                                    <input type="number" name="bocor_ad_spend_threshold" value="<?= htmlspecialchars($cfg['bocor_ad_spend_threshold'] ?? '100000') ?>" required step="10000" class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl font-bold text-rose-600 font-mono">
                                    <span class="text-[10px] text-gray-400 mt-1 block">Jika spend mencapai batas ini dengan 0 pesanan, langsung ditandai "Produk Bocor".</span>
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="font-bold text-gray-700 block mb-1">Batas Min ROAS Bocor</label>
                                        <input type="number" name="bocor_min_roas_multiplier" value="<?= htmlspecialchars($cfg['bocor_min_roas_multiplier'] ?? '1.8') ?>" step="0.1" required class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl font-bold text-amber-700 text-center">
                                    </div>
                                    <div>
                                        <label class="font-bold text-gray-700 block mb-1">Tarif Pajak PPN Iklan (%)</label>
                                        <input type="number" name="ppn_tax_rate" value="<?= htmlspecialchars($cfg['ppn_tax_rate'] ?? '11.0') ?>" step="0.1" required class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl font-bold text-indigo-700 text-center">
                                    </div>
                                </div>

                                <div>
                                    <label class="font-bold text-gray-700 block mb-1">Otomatisasi Pause Bid Iklan Bocor</label>
                                    <select name="auto_pause_bocor_ads" class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl font-bold text-gray-800">
                                        <option value="0" <?= ($cfg['auto_pause_bocor_ads'] ?? '0') == '0' ? 'selected' : '' ?>>Nonaktif (Hanya Tampilkan Badge Merah &amp; Notifikasi)</option>
                                        <option value="1" <?= ($cfg['auto_pause_bocor_ads'] ?? '0') == '1' ? 'selected' : '' ?>>Aktif (Otomatis Kirim Sinyal Pause Campaign via API)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="pt-3 border-t flex justify-end">
                            <button type="submit" class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white font-bold rounded-xl transition shadow-xs">
                                Simpan Parameter Iklan
                            </button>
                        </div>
                    </form>

                    <!-- CARD 3: LOGISTIK, RETUR & SELISIH ONGKIR KURIR -->
                    <form onsubmit="submitAppSettings(event)" class="bg-white rounded-3xl border border-gray-200 p-6 shadow-2xs space-y-4 flex flex-col justify-between">
                        <div class="space-y-4">
                            <div class="flex items-center gap-2.5 border-b pb-3">
                                <div class="w-8 h-8 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center font-bold text-sm">
                                    &#128666;
                                </div>
                                <div>
                                    <h4 class="font-extrabold text-gray-900 text-sm">Logistik, Selisih Ongkir &amp; Retur</h4>
                                    <p class="text-[11px] text-gray-500">Toleransi gramasi ekspedisi dan ambang batas klaim ganti rugi</p>
                                </div>
                            </div>

                            <div class="space-y-3">
                                <div>
                                    <label class="font-bold text-gray-700 block mb-1">Ambang Batas Hari Paket Retur Belum Tiba Fisik (Hari)</label>
                                    <input type="number" name="lost_return_days_threshold" value="<?= htmlspecialchars($cfg['lost_return_days_threshold'] ?? '14') ?>" required min="3" max="60" class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl font-bold text-rose-700">
                                    <span class="text-[10px] text-gray-400 mt-1 block">Paket retur buyer yang belum sampai &gt; angka ini wajib diajukan ganti rugi kurir.</span>
                                </div>

                                <div>
                                    <label class="font-bold text-gray-700 block mb-1">Toleransi Selisih Berat Kurir vs Sistem (Gram)</label>
                                    <input type="number" name="shipping_discrepancy_tolerance_gram" value="<?= htmlspecialchars($cfg['shipping_discrepancy_tolerance_gram'] ?? '100') ?>" required min="0" step="50" class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl font-bold text-gray-900">
                                    <span class="text-[10px] text-gray-400 mt-1 block">Selisih timbangan di atas batas ini akan memicu badge 'Selisih Ongkir Kurir'.</span>
                                </div>

                                <div>
                                    <label class="font-bold text-gray-700 block mb-1">Interval Cron Auto-Sync Pesanan (Menit)</label>
                                    <input type="number" name="cron_order_sync_interval_min" value="<?= htmlspecialchars($cfg['cron_order_sync_interval_min'] ?? '15') ?>" required min="5" max="180" class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl font-bold text-gray-900">
                                </div>
                            </div>
                        </div>

                        <div class="pt-3 border-t flex justify-end">
                            <button type="submit" class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white font-bold rounded-xl transition shadow-xs">
                                Simpan Parameter Logistik
                            </button>
                        </div>
                    </form>

                    <!-- CARD 4: PEMETAAN JURNAL BERPASANGAN (COA INTEGRATION) -->
                    <form onsubmit="submitAppSettings(event)" class="bg-white rounded-3xl border border-gray-200 p-6 shadow-2xs space-y-4 flex flex-col justify-between">
                        <div class="space-y-4">
                            <div class="flex items-center gap-2.5 border-b pb-3">
                                <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-600 flex items-center justify-center font-bold text-sm">
                                    &#128214;
                                </div>
                                <div>
                                    <h4 class="font-extrabold text-gray-900 text-sm">Pemetaan Jurnal Berpasangan (COA)</h4>
                                    <p class="text-[11px] text-gray-500">Integrasi kode akun buku besar pabrik konveksi Jago Laba</p>
                                </div>
                            </div>

                            <div class="space-y-2.5">
                                <div class="grid grid-cols-2 gap-2 text-[11px]">
                                    <div>
                                        <label class="font-bold text-gray-700 block mb-0.5">Kode Piutang Escrow</label>
                                        <input type="text" name="journal_escrow_account_code" value="<?= htmlspecialchars($cfg['journal_escrow_account_code'] ?? '1103') ?>" required class="w-full p-2 bg-gray-50 border border-gray-200 rounded-xl font-mono text-center font-bold">
                                    </div>
                                    <div>
                                        <label class="font-bold text-gray-700 block mb-0.5">Kode Penjualan Bruto</label>
                                        <input type="text" name="journal_sales_account_code" value="<?= htmlspecialchars($cfg['journal_sales_account_code'] ?? '4101') ?>" required class="w-full p-2 bg-gray-50 border border-gray-200 rounded-xl font-mono text-center font-bold">
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-2 text-[11px]">
                                    <div>
                                        <label class="font-bold text-gray-700 block mb-0.5">Kode HPP Konveksi</label>
                                        <input type="text" name="journal_cogs_account_code" value="<?= htmlspecialchars($cfg['journal_cogs_account_code'] ?? '5101') ?>" required class="w-full p-2 bg-gray-50 border border-gray-200 rounded-xl font-mono text-center font-bold text-blue-600">
                                    </div>
                                    <div>
                                        <label class="font-bold text-gray-700 block mb-0.5">Kode Biaya Komisi</label>
                                        <input type="text" name="journal_platform_fee_code" value="<?= htmlspecialchars($cfg['journal_platform_fee_code'] ?? '6101') ?>" required class="w-full p-2 bg-gray-50 border border-gray-200 rounded-xl font-mono text-center font-bold text-red-600">
                                    </div>
                                </div>

                                <div>
                                    <label class="font-bold text-gray-700 block mb-0.5">Kode Beban Iklan Berbayar</label>
                                    <input type="text" name="journal_ad_spend_code" value="<?= htmlspecialchars($cfg['journal_ad_spend_code'] ?? '6102') ?>" required class="w-full p-2 bg-gray-50 border border-gray-200 rounded-xl font-mono font-bold text-rose-700">
                                </div>

                                <div>
                                    <label class="font-bold text-gray-700 block mb-0.5">Kunci Rahasia Global Webhook API</label>
                                    <input type="text" name="webhook_global_secret" value="<?= htmlspecialchars($cfg['webhook_global_secret'] ?? 'JAGOLABA_MKT_WEBHOOK_SECRET_KEY_2026') ?>" required class="w-full p-2 bg-gray-50 border border-gray-200 rounded-xl font-mono text-[11px]">
                                </div>
                            </div>
                        </div>

                        <div class="pt-3 border-t flex justify-end">
                            <button type="submit" class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white font-bold rounded-xl transition shadow-xs">
                                Simpan Parameter Akuntansi
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </main>

    <?php if(!in_array($activeTab,['dashboard','pesanan'],true)): ?>
    <!-- ==================================================================== -->
    <!-- MODAL 1: RINCIAN DETAIL TRANSAKSI                                    -->
    <!-- ==================================================================== -->
    <div id="orderDetailModal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-3xl max-w-2xl w-full p-6 shadow-2xl space-y-5 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b pb-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-orange-100 text-orange-600 flex items-center justify-center font-bold text-lg">
                        &#128717;
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-900 text-base" id="modOrderSn">Detail Pesanan</h3>
                        <p class="text-xs text-gray-500" id="modOrderSub">Memuat rincian transaksi...</p>
                    </div>
                </div>
                <button onclick="closeModal('orderDetailModal')" class="text-gray-400 hover:text-gray-600 text-2xl font-bold">&times;</button>
            </div>

            <div id="modOrderBody" class="space-y-4 text-xs">
                <!-- Diisi otomatis oleh JavaScript inspectOrder -->
            </div>

            <div class="pt-4 border-t flex justify-end">
                <button onclick="closeModal('orderDetailModal')" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-xl transition">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <!-- ==================================================================== -->
    <!-- MODAL 2: TAMBAH PENGELUARAN BARU                                     -->
    <!-- ==================================================================== -->
    <div id="addExpenseModal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-3xl max-w-xl w-full p-6 shadow-2xl space-y-5 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b pb-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-red-100 text-red-600 flex items-center justify-center font-bold text-lg">
                        &#36;
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-900 text-base">Tambah Pengeluaran Toko</h3>
                        <p class="text-xs text-gray-500">Pengeluaran dicatat dalam ruang kerja Marketplace</p>
                    </div>
                </div>
                <button onclick="closeModal('addExpenseModal')" class="text-gray-400 hover:text-gray-600 text-2xl font-bold">&times;</button>
            </div>

            <form id="formAddExpense" onsubmit="submitAddExpense(event)" class="space-y-4 text-xs">
                <input type="hidden" name="store_id" value="<?= htmlspecialchars($activeStoreId) ?>">
                
                <div>
                    <label class="font-bold text-gray-700 block mb-1">Deskripsi Pengeluaran *</label>
                    <input type="text" name="description" required placeholder="Contoh: Shopee Ads Harian, Lakban 1 Dus, Plastik Polymailer..." class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl focus:ring-1 focus:ring-brand-500">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="font-bold text-gray-700 block mb-1">Jumlah Pengeluaran (Rp) *</label>
                        <input type="number" name="amount" required min="1" step="any" placeholder="Contoh: 850000" class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl font-bold text-red-600 focus:ring-1 focus:ring-brand-500">
                    </div>
                    <div>
                        <label class="font-bold text-gray-700 block mb-1">Tanggal *</label>
                        <input type="date" name="expense_date" required value="<?= date('Y-m-d') ?>" class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl focus:ring-1 focus:ring-brand-500">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="font-bold text-gray-700 block mb-1">Kategori Pengeluaran</label>
                        <select name="category" class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl">
                            <option value="Biaya Iklan/Advertising Marketplace">Biaya Iklan/Advertising Marketplace</option>
                            <option value="Operasional & Kemasan">Operasional &amp; Kemasan</option>
                            <option value="Gaji Admin & Sortir">Gaji Admin &amp; Sortir</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                    </div>
                    <div>
                        <label class="font-bold text-gray-700 block mb-1">Rekening Pembayaran (Bank)</label>
                        <select name="account_id" class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl font-semibold">
                            <?php foreach ($accounts as $acc): ?>
                            <option value="<?= $acc['id'] ?>" <?= $acc['id'] == ($activeStore['default_account_id'] ?? 0) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($acc['name']) ?> (<?= htmlspecialchars($acc['code']) ?>) - Sisa <?= formatRp($acc['current_balance']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="font-bold text-gray-700 block mb-1">Nama Vendor / Penyedia</label>
                    <input type="text" name="vendor_name" placeholder="Shopee Ads Manager / Toko Surya Kemasan" class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl">
                </div>

                <div>
                    <label class="font-bold text-gray-700 block mb-1">Catatan Tambahan</label>
                    <textarea name="notes" rows="2" placeholder="Catatan internal..." class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl"></textarea>
                </div>

                <div class="p-3 bg-gray-50 rounded-xl border border-gray-200 flex items-center gap-4">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="affects_profit" checked value="1" class="rounded text-brand-600">
                        <span class="font-semibold text-gray-700">Mempengaruhi Profit (Masuk Laba Rugi)</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_cashflow_only" value="1" class="rounded text-brand-600">
                        <span class="font-semibold text-gray-700">Hanya Arus Kas</span>
                    </label>
                </div>

                <div class="pt-4 border-t flex justify-end gap-2">
                    <button type="button" onclick="closeModal('addExpenseModal')" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-xl transition">
                        Batal
                    </button>
                    <button type="submit" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl shadow-xs transition">
                        Simpan &amp; Potong Kas
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================================================================== -->
    <!-- MODAL 3: TAMBAH PESANAN MANUAL                                       -->
    <!-- ==================================================================== -->
    <div id="addOrderModal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-3xl max-w-2xl w-full p-6 shadow-2xl space-y-5 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b pb-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center font-bold text-lg">
                        +
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-900 text-base">Input Pesanan Marketplace Manual</h3>
                        <p class="text-xs text-gray-500">Otomatis mengurangi stok fisik di Gudang Utama</p>
                    </div>
                </div>
                <button onclick="closeModal('addOrderModal')" class="text-gray-400 hover:text-gray-600 text-2xl font-bold">&times;</button>
            </div>

            <form id="formAddOrder" onsubmit="submitAddOrder(event)" class="space-y-4 text-xs">
                <input type="hidden" name="store_id" value="<?= htmlspecialchars($activeStoreId) ?>">

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="font-bold text-gray-700 block mb-1">Nomor Pesanan (Order SN) *</label>
                        <input type="text" name="order_id" required value="MANUAL-<?= strtoupper(substr(md5(microtime()), 0, 10)) ?>" class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl font-mono focus:ring-1 focus:ring-brand-500">
                    </div>
                    <div>
                        <label class="font-bold text-gray-700 block mb-1">Username Pembeli</label>
                        <input type="text" name="buyer_username" placeholder="Contoh: ahmad_fadillah" class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl">
                    </div>
                </div>

                <div class="p-3 bg-brand-50/50 rounded-xl border border-brand-100 space-y-3">
                    <span class="font-bold text-brand-900 block text-xs">Hubungkan Produk Konveksi &amp; Stok Fisik</span>
                    <div>
                        <label class="font-semibold text-gray-700 block mb-1">Pilih Produk Varian Konveksi</label>
                        <select name="product_variant_id" onchange="autoFillCogs(this)" class="w-full p-2 bg-white border border-gray-200 rounded-xl">
                            <option value="">-- Pilih Produk Varian --</option>
                            <?php foreach ($productVariants as $pv): ?>
                            <option value="<?= $pv['variant_id'] ?>" data-hpp="<?= (float)$pv['standard_hpp'] ?>" data-sku="<?= htmlspecialchars($pv['sku']) ?>" data-name="<?= htmlspecialchars($pv['product_name']) ?>">
                                <?= htmlspecialchars($pv['product_name']) ?> (<?= htmlspecialchars($pv['sku']) ?>) - HPP <?= formatRp($pv['standard_hpp']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <label class="font-semibold text-gray-700 block mb-1">SKU</label>
                            <input type="text" id="orderSku" name="sku" value="SKU-DIRECT" class="w-full p-2 bg-white border border-gray-200 rounded-xl font-mono">
                        </div>
                        <div>
                            <label class="font-semibold text-gray-700 block mb-1">Qty</label>
                            <input type="number" name="qty" min="1" value="1" class="w-full p-2 bg-white border border-gray-200 rounded-xl text-center font-bold">
                        </div>
                        <div>
                            <label class="font-semibold text-gray-700 block mb-1">HPP Konveksi (Rp)</label>
                            <input type="number" id="orderHpp" name="total_cogs_hpp" value="45000" class="w-full p-2 bg-white border border-gray-200 rounded-xl font-bold text-blue-600">
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="font-bold text-gray-700 block mb-1">Nilai Penjualan (Gross Omzet Rp) *</label>
                        <input type="number" name="gross_sales" required placeholder="79000" class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl font-bold text-gray-900">
                    </div>
                    <div>
                        <label class="font-bold text-gray-700 block mb-1">Status Pesanan</label>
                        <select name="order_status" class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl font-bold">
                            <option value="Selesai">Selesai (Potong Stok Sekarang)</option>
                            <option value="Pending">Pending</option>
                            <option value="Retur">Retur</option>
                            <option value="Dibatalkan">Dibatalkan</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-2">
                    <div>
                        <label class="text-gray-600 font-semibold block mb-1">Biaya Admin (Rp)</label>
                        <input type="number" name="platform_admin_fee" value="5000" class="w-full p-2 bg-gray-50 border border-gray-200 rounded-xl">
                    </div>
                    <div>
                        <label class="text-gray-600 font-semibold block mb-1">Biaya Layanan (Rp)</label>
                        <input type="number" name="service_fee" value="2500" class="w-full p-2 bg-gray-50 border border-gray-200 rounded-xl">
                    </div>
                    <div>
                        <label class="text-gray-600 font-semibold block mb-1">Biaya Transaksi (Rp)</label>
                        <input type="number" name="payment_fee" value="1500" class="w-full p-2 bg-gray-50 border border-gray-200 rounded-xl">
                    </div>
                </div>

                <div class="pt-4 border-t flex justify-end gap-2">
                    <button type="button" onclick="closeModal('addOrderModal')" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-xl transition">
                        Batal
                    </button>
                    <button type="submit" class="px-5 py-2 bg-brand-600 hover:bg-brand-700 text-white font-bold rounded-xl shadow-xs transition">
                        Simpan Pesanan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================================================================== -->
    <!-- MODAL 4: ATUR HPP & MAPPING SKU GUDANG                               -->
    <!-- ==================================================================== -->
    <div id="hppModal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl space-y-5">
            <div class="flex items-center justify-between border-b pb-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center font-bold text-lg">
                        &#9998;
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-900 text-base">Atur HPP &amp; Mapping Gudang</h3>
                        <p class="text-xs text-gray-500">Hubungkan SKU Marketplace ke Pabrik Konveksi</p>
                    </div>
                </div>
                <button onclick="closeModal('hppModal')" class="text-gray-400 hover:text-gray-600 text-2xl font-bold">&times;</button>
            </div>

            <form id="formUpdateHpp" onsubmit="submitUpdateHpp(event)" class="space-y-4 text-xs">
                <input type="hidden" name="store_id" value="<?= htmlspecialchars($activeStoreId) ?>">
                <input type="hidden" id="hppVariantId" name="product_variant_id" value="">

                <div>
                    <label class="font-bold text-gray-700 block mb-1">SKU Marketplace *</label>
                    <input type="text" id="hppSku" name="sku" required readonly class="w-full p-2.5 bg-gray-100 border border-gray-200 rounded-xl font-mono text-gray-700">
                </div>

                <div>
                    <label class="font-bold text-gray-700 block mb-1">Nama Produk</label>
                    <input type="text" id="hppProductName" readonly class="w-full p-2.5 bg-gray-100 border border-gray-200 rounded-xl text-gray-700">
                </div>

                <div>
                    <label class="font-bold text-gray-700 block mb-1">Hubungkan ke Varian Produk Konveksi</label>
                    <select id="hppSelectVariant" onchange="onSelectVariantForHpp(this)" class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl">
                        <option value="">-- Pilih Varian Pabrik (Opsional) --</option>
                        <?php foreach ($productVariants as $pv): ?>
                        <option value="<?= $pv['variant_id'] ?>" data-hpp="<?= (float)$pv['standard_hpp'] ?>">
                            <?= htmlspecialchars($pv['product_name']) ?> (<?= htmlspecialchars($pv['sku']) ?>) - HPP Pabrik: <?= formatRp($pv['standard_hpp']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="font-bold text-gray-700 block mb-1">Nilai HPP Baru (Rp) *</label>
                    <input type="number" id="hppValue" name="hpp" required min="1" step="any" placeholder="Contoh: 45000" class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl font-bold text-emerald-600 text-base">
                    <span class="text-[11px] text-gray-400 mt-1 block">Nilai ini berlaku untuk perhitungan pesanan marketplace.</span>
                </div>

                <div class="pt-4 border-t flex justify-end gap-2">
                    <button type="button" onclick="closeModal('hppModal')" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-xl transition">
                        Batal
                    </button>
                    <button type="submit" class="px-5 py-2 bg-brand-600 hover:bg-brand-700 text-white font-bold rounded-xl shadow-xs transition">
                        Simpan HPP
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================================================================== -->
    <!-- MODAL 5: INTEGRASI API RESMI (SHOPEE & TIKTOK SHOP)                  -->
    <!-- ==================================================================== -->
    <div id="apiModal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-3xl max-w-2xl w-full p-6 shadow-2xl space-y-5 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b pb-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-brand-50 text-brand-600 flex items-center justify-center font-bold text-lg">
                        &#9881;
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-900 text-base">Konfigurasi API Resmi Marketplace</h3>
                        <p class="text-xs text-gray-500">TikTok Shop Partner Open API &amp; Shopee Open Platform v2</p>
                    </div>
                </div>
                <button onclick="closeModal('apiModal')" class="text-gray-400 hover:text-gray-600 text-2xl font-bold">&times;</button>
            </div>

            <!-- Credentials Status Banner -->
            <div class="space-y-4 text-xs">
                <div class="p-4 bg-amber-50 border border-amber-200 rounded-2xl flex items-center justify-between">
                    <div>
                        <span class="font-bold text-amber-900 block text-sm" id="apiStatusText">Status: belum diverifikasi</span>
                        <span class="text-amber-800 text-xs" id="apiPingText">Koneksi resmi belum tersedia. Data lama tetap tersimpan.</span>
                    </div>
                    <button onclick="pingApi('<?= $activeStoreId ?>')" id="btnPingApi" class="px-3.5 py-1.5 bg-amber-700 hover:bg-amber-800 text-white font-bold rounded-xl shadow-xs transition">
                        Uji Ping API
                    </button>
                </div>

                <p class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700">Masukkan dan uji kredensial partner melalui <a class="font-bold text-brand-700 underline" href="marketplace.php?tab=pengaturan&amp;peng_sub=integrasi">Integrasi API</a>. Pengaturan di halaman ini adalah data lama dan bukan bukti koneksi API. Jangan daftarkan URL webhook lama di Seller Center.</p>
                <form id="formApiCredentials" onsubmit="submitApiCredentials(event)" class="hidden space-y-4">
                    <input type="hidden" name="store_id" value="<?= htmlspecialchars($activeStoreId) ?>">
                    <input type="hidden" name="platform" value="<?= htmlspecialchars($activeStore['platform']) ?>">

                    <?php if ($activeStore['platform'] === 'Shopee'): ?>
                    <!-- Form Shopee Open Platform -->
                    <div class="space-y-3">
                        <span class="font-bold text-gray-900 block text-sm">Parameter Shopee Open Platform v2</span>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-gray-600 font-semibold block mb-1">Partner ID</label>
                                <input type="text" name="partner_id" value="" class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl font-mono">
                            </div>
                            <div>
                                <label class="text-gray-600 font-semibold block mb-1">Shop ID</label>
                                <input type="text" name="shop_id" value="" class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl font-mono">
                            </div>
                        </div>
                        <div>
                            <label class="text-gray-600 font-semibold block mb-1">Partner Key (Secret)</label>
                            <input type="password" name="partner_key" value="" class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl font-mono" autocomplete="new-password">
                        </div>
                        <div>
                            <label class="text-gray-600 font-semibold block mb-1">Webhook URL Otomatis (Shopee Push Mechanism)</label>
                            <div class="flex items-center gap-2">
                                <input type="text" id="shopeeWebhookUrl" readonly value="http://localhost/jago-laba/public/api/marketplace-webhook.php?platform=shopee" class="w-full p-2.5 bg-gray-100 border border-gray-200 rounded-xl text-gray-600 font-mono text-[11px]">
                                <button type="button" onclick="copyWebhook('shopeeWebhookUrl')" class="px-3 py-2.5 bg-gray-200 hover:bg-gray-300 rounded-xl font-bold shrink-0">Salin</button>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <!-- Form TikTok Shop Partner API -->
                    <div class="space-y-3">
                        <span class="font-bold text-gray-900 block text-sm">Parameter TikTok Shop Partner Open API</span>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-gray-600 font-semibold block mb-1">App Key</label>
                                <input type="text" name="app_key" value="" class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl font-mono">
                            </div>
                            <div>
                                <label class="text-gray-600 font-semibold block mb-1">Shop Cipher</label>
                                <input type="text" name="cipher" value="" class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl font-mono">
                            </div>
                        </div>
                        <div>
                            <label class="text-gray-600 font-semibold block mb-1">App Secret</label>
                            <input type="password" name="app_secret" value="" class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl font-mono" autocomplete="new-password">
                        </div>
                        <div>
                            <label class="text-gray-600 font-semibold block mb-1">Webhook URL Otomatis (TikTok Shop Partner Webhook)</label>
                            <div class="flex items-center gap-2">
                                <input type="text" id="tiktokWebhookUrl" readonly value="http://localhost/jago-laba/public/api/marketplace-webhook.php?platform=tiktok" class="w-full p-2.5 bg-gray-100 border border-gray-200 rounded-xl text-gray-600 font-mono text-[11px]">
                                <button type="button" onclick="copyWebhook('tiktokWebhookUrl')" class="px-3 py-2.5 bg-gray-200 hover:bg-gray-300 rounded-xl font-bold shrink-0">Salin</button>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="pt-4 border-t flex justify-between items-center text-xs">
                        <span class="text-gray-400">Autentikasi OAuth2.0 Token Auto-Refresh aktif.</span>
                        <div class="flex gap-2">
                            <button type="button" onclick="closeModal('apiModal')" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-xl transition">
                                Tutup
                            </button>
                            <button type="submit" class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white font-bold rounded-xl shadow-xs transition">
                                Simpan &amp; Otorisasi
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ==================================================================== -->
    <!-- MODAL 6: PANDUAN ROAS & FORMULA MARKETING                            -->
    <!-- ==================================================================== -->
    <div id="mktHelpModal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-3xl max-w-xl w-full p-6 shadow-2xl space-y-4 text-xs">
            <div class="flex items-center justify-between border-b pb-3">
                <div class="flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-lg bg-orange-100 text-brand-600 flex items-center justify-center font-bold text-sm">&#128227;</span>
                    <h3 class="font-bold text-gray-900 text-base">Panduan Metrik Marketing &amp; ROAS</h3>
                </div>
                <button onclick="closeModal('mktHelpModal')" class="text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
            </div>

            <div class="space-y-3 leading-relaxed text-gray-700">
                <div class="p-3 bg-gray-50 rounded-xl border border-gray-200">
                    <b class="text-gray-900 block mb-1">1. Gross ROAS</b>
                    <p class="text-[11px] font-mono text-gray-600">Formula: Pendapatan Bruto / Biaya Iklan</p>
                    <p class="text-[11px] text-gray-500 mt-1">Mengukur efisiensi omzet terhadap setiap Rp 1 belanja iklan.</p>
                </div>
                <div class="p-3 bg-gray-50 rounded-xl border border-gray-200">
                    <b class="text-gray-900 block mb-1">2. Actual ROAS</b>
                    <p class="text-[11px] font-mono text-gray-600">Formula: (Pendapatan - Biaya Platform - HPP Konveksi) / Biaya Iklan</p>
                    <p class="text-[11px] text-gray-500 mt-1">Mengukur laba riil yang tersisa sebelum dipotong biaya iklan.</p>
                </div>
                <div class="p-3 bg-gray-50 rounded-xl border border-gray-200">
                    <b class="text-gray-900 block mb-1">3. Min. ROAS (Break-Even ROAS)</b>
                    <p class="text-[11px] font-mono text-gray-600">Formula: Pendapatan / (Pendapatan - Biaya Platform - HPP Konveksi)</p>
                    <p class="text-[11px] text-gray-500 mt-1">Batas minimum ROAS agar toko tidak rugi. Jika Gross ROAS &lt; Min ROAS, maka status menjadi <b>✗ rugi</b>.</p>
                </div>
                <div class="p-3 bg-emerald-50 rounded-xl border border-emerald-200">
                    <b class="text-emerald-900 block mb-1">4. Net Profit Akhir</b>
                    <p class="text-[11px] font-mono text-emerald-800">Formula: Pendapatan - Biaya Platform - Total HPP Konveksi - Biaya Iklan</p>
                    <p class="text-[11px] text-emerald-700 mt-1">Laba bersih final yang masuk ke rekening kas/bank pabrik Anda.</p>
                </div>
            </div>

            <div class="pt-3 border-t flex justify-end">
                <button onclick="closeModal('mktHelpModal')" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 font-bold rounded-xl">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <!-- ==================================================================== -->
    <!-- MODAL 7: DETAIL IKLAN PRODUK & ANALISIS ROAS                         -->
    <!-- ==================================================================== -->
    <div id="productAdModal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-3xl max-w-xl w-full p-6 shadow-2xl space-y-4 text-xs max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b pb-3">
                <div class="flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-lg bg-orange-100 text-brand-600 flex items-center justify-center font-bold text-sm">&#128200;</span>
                    <div>
                        <h3 class="font-bold text-gray-900 text-base" id="adModalTitle">Detail Performa Iklan</h3>
                        <p class="text-[11px] text-gray-400" id="adModalSku">SKU</p>
                    </div>
                </div>
                <button onclick="closeModal('productAdModal')" class="text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
            </div>

            <div id="adModalBody" class="space-y-3">
                <!-- Diisi otomatis oleh JavaScript openProductAdDetail -->
            </div>

            <div class="pt-3 border-t flex justify-end">
                <button onclick="closeModal('productAdModal')" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 font-bold rounded-xl">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <!-- ==================================================================== -->
    <!-- MODAL 8: KALKULATOR SIMULASI HARGA & BREAK-EVEN ROAS                 -->
    <!-- ==================================================================== -->
    <div id="priceSimulatorModal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-3xl max-w-4xl w-full p-6 shadow-2xl space-y-5 max-h-[92vh] overflow-y-auto text-xs">
            <div class="flex items-center justify-between border-b pb-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-purple-100 text-purple-700 flex items-center justify-center font-bold text-lg">
                        &#9878;
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-900 text-base">Kalkulator Simulasi Harga Jual &amp; Break-Even ROAS</h3>
                        <p class="text-xs text-gray-500">Kalkulasi harga jual promo, flash sale &amp; ambang batas minimal ROAS anti-boncos</p>
                    </div>
                </div>
                <button onclick="closeModal('priceSimulatorModal')" class="text-gray-400 hover:text-gray-600 text-2xl font-bold">&times;</button>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <!-- Form Input (6 Kolom) -->
                <form id="formPriceSimulator" onsubmit="submitPriceSimulation(event)" class="lg:col-span-6 space-y-3.5">
                    <div>
                        <label class="font-bold text-gray-700 block mb-1">Pilih Model / SKU Konveksi (Atau Ketik Baru)</label>
                        <select onchange="applyVariantPreset(this)" class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl font-medium focus:ring-1 focus:ring-brand-500 mb-2">
                            <option value="">-- Pilih Template SKU Pabrik --</option>
                            <?php foreach ($productVariants as $pv): ?>
                            <option value="<?= htmlspecialchars($pv['sku']) ?>" data-name="<?= htmlspecialchars($pv['product_name']) ?>" data-hpp="<?= (float)$pv['standard_hpp'] ?>">
                                <?= htmlspecialchars($pv['product_name']) ?> (<?= htmlspecialchars($pv['sku']) ?>) - HPP: <?= formatRp($pv['standard_hpp']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" id="simProductName" name="product_name" value="Jaket Coach Waterproof Taslan JN-01" required class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl font-medium focus:ring-1 focus:ring-brand-500">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="font-bold text-gray-700 block mb-1">HPP Pokok Produk (Rp) *</label>
                            <input type="number" id="simCogsHpp" name="cogs_hpp" value="45000" min="1000" step="500" required oninput="calcLivePriceSimulation()" class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl font-bold text-blue-600 text-sm">
                        </div>
                        <div>
                            <label class="font-bold text-gray-700 block mb-1">Target Laba Bersih (%)</label>
                            <input type="number" id="simTargetMargin" name="target_net_margin_pct" value="25" min="1" max="80" step="0.5" required oninput="calcLivePriceSimulation()" class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl font-bold text-emerald-600 text-sm">
                        </div>
                    </div>

                    <div class="p-3 bg-gray-50 rounded-2xl border border-gray-200 space-y-2.5">
                        <span class="font-bold text-gray-800 block text-[11px] uppercase tracking-wider">Komponen Potongan Marketplace &amp; Iklan:</span>
                        <div class="grid grid-cols-3 gap-2 text-[11px]">
                            <div>
                                <label class="text-gray-500 block mb-0.5">Admin (Star/TT)</label>
                                <div class="flex items-center">
                                    <input type="number" id="simAdminFee" name="admin_fee_pct" value="11" step="0.5" oninput="calcLivePriceSimulation()" class="w-full p-1.5 bg-white border border-gray-200 rounded-lg text-center font-bold">
                                    <span class="ml-1 text-gray-400">%</span>
                                </div>
                            </div>
                            <div>
                                <label class="text-gray-500 block mb-0.5">Gratis Ongkir Xtra</label>
                                <div class="flex items-center">
                                    <input type="number" id="simFreeShipping" name="free_shipping_xtra_pct" value="4" step="0.5" oninput="calcLivePriceSimulation()" class="w-full p-1.5 bg-white border border-gray-200 rounded-lg text-center font-bold">
                                    <span class="ml-1 text-gray-400">%</span>
                                </div>
                            </div>
                            <div>
                                <label class="text-gray-500 block mb-0.5">Biaya Layanan/Pay</label>
                                <div class="flex items-center">
                                    <input type="number" id="simPaymentFee" name="payment_fee_pct" value="1" step="0.5" oninput="calcLivePriceSimulation()" class="w-full p-1.5 bg-white border border-gray-200 rounded-lg text-center font-bold">
                                    <span class="ml-1 text-gray-400">%</span>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="text-gray-500 block mb-0.5">Alokasi Budget Iklan Target (%)</label>
                            <div class="flex items-center">
                                <input type="number" id="simAdBudget" name="ad_budget_pct" value="10" step="0.5" oninput="calcLivePriceSimulation()" class="w-full p-1.5 bg-white border border-gray-200 rounded-lg font-bold text-rose-600">
                                <span class="ml-2 text-gray-400 text-xs">% dari harga</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 pt-2">
                        <button type="submit" class="flex-1 py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl transition shadow-xs text-center">
                            Simpan Preset Simulasi
                        </button>
                        <button type="button" onclick="calcLivePriceSimulation()" class="px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-xl transition">
                            Hitung Ulang
                        </button>
                    </div>
                </form>

                <!-- Hasil Kalkulasi Real-Time (6 Kolom) -->
                <div class="lg:col-span-6 space-y-3.5 flex flex-col justify-between">
                    <div class="space-y-3">
                        <div class="p-4 bg-purple-50/70 border border-purple-200 rounded-2xl flex items-center justify-between">
                            <div>
                                <span class="text-purple-800 font-bold uppercase tracking-wider text-[11px] block">Rekomendasi Harga Jual Normal</span>
                                <p class="text-3xl font-extrabold text-purple-900 mt-1 font-mono" id="resNormalPrice">Rp 76.500</p>
                                <span class="text-[11px] text-purple-700 mt-0.5 block">Menutup HPP + Admin/Ongkir (16%) + Iklan (10%) + Laba 25%</span>
                            </div>
                            <button type="button" onclick="copySimPrice('resNormalPrice')" class="px-3 py-1.5 bg-purple-200 hover:bg-purple-300 text-purple-900 rounded-lg font-bold text-xs">Salin</button>
                        </div>

                        <div class="p-4 bg-emerald-50/70 border border-emerald-200 rounded-2xl flex items-center justify-between">
                            <div>
                                <span class="text-emerald-800 font-bold uppercase tracking-wider text-[11px] block">Rekomendasi Harga Flash Sale</span>
                                <p class="text-2xl font-extrabold text-emerald-900 mt-1 font-mono" id="resFlashSalePrice">Rp 59.900</p>
                                <span class="text-[11px] text-emerald-700 mt-0.5 block">Traffic organik promo (Margin minimal 5% tanpa potong iklan)</span>
                            </div>
                            <button type="button" onclick="copySimPrice('resFlashSalePrice')" class="px-3 py-1.5 bg-emerald-200 hover:bg-emerald-300 text-emerald-900 rounded-lg font-bold text-xs">Salin</button>
                        </div>

                        <div class="p-4 bg-amber-50/80 border border-amber-300 rounded-2xl space-y-1.5">
                            <div class="flex items-center justify-between">
                                <span class="text-amber-900 font-bold text-xs flex items-center gap-1.5">
                                    <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                                    Batas Minimal ROAS Iklan (Break-Even ROAS)
                                </span>
                                <span class="text-xl font-black text-amber-900 font-mono" id="resBreakEvenRoas">1.85x</span>
                            </div>
                            <p class="text-[11px] text-amber-800" id="resRoasExplanation">
                                Iklan Shopee/TikTok Ads hanya boleh dipertahankan jika ROAS aktual &ge; <b>1.85x</b>. Jika ROAS harian &lt; 1.85x, produk masuk kategori <b>BOCOR</b> dan segera kurangi bid kata kunci!
                            </p>
                        </div>

                        <div class="p-3 bg-gray-50 border border-gray-200 rounded-2xl flex items-center justify-between text-xs">
                            <span class="text-gray-600">Estimasi Laba Bersih Riil per Pcs:</span>
                            <span class="font-extrabold text-emerald-600 font-mono text-sm" id="resNetProfitPerPcs">Rp 19.125 / pcs</span>
                        </div>
                    </div>

                    <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl text-[11px] text-slate-600">
                        &#128161; <b>Tips Konveksi Jago Laba:</b> Gunakan harga flash sale untuk momentum campaign tanggal kembar (10.10, 11.11, 12.12) guna mengerek rating toko tanpa risiko margin minus.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================================================================== -->
    <!-- MODAL 9: IMPOR SETTLEMENT CSV / EXCEL SELLER CENTER                  -->
    <!-- ==================================================================== -->
    <div id="importSettlementModal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-3xl max-w-xl w-full p-6 shadow-2xl space-y-5 text-xs">
            <div class="flex items-center justify-between border-b pb-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-lg">
                        &#128196;
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-900 text-base">Impor Settlement CSV / Excel Seller Center</h3>
                        <p class="text-xs text-gray-500">Laporan Penghasilan Shopee &amp; Settlement Statement TikTok Shop</p>
                    </div>
                </div>
                <button onclick="closeModal('importSettlementModal')" class="text-gray-400 hover:text-gray-600 text-2xl font-bold">&times;</button>
            </div>

            <form id="formImportSettlement" onsubmit="submitImportSettlement(event)" class="space-y-4">
                <input type="hidden" name="store_id" value="<?= htmlspecialchars($activeStoreId) ?>">

                <div class="p-4 bg-blue-50 border border-blue-200 rounded-2xl text-blue-900 text-[11px] leading-relaxed">
                    <b>Cara Mengambil File dari Seller Center:</b>
                    <ul class="list-disc list-inside mt-1 space-y-0.5 text-blue-800">
                        <li><b>Shopee:</b> Buka <i>Shopee Seller Center &rarr; Keuangan &rarr; Penghasilan Saya &rarr; Rincian Penghasilan &rarr; Ekspor CSV</i>.</li>
                        <li><b>TikTok Shop:</b> Buka <i>TikTok Shop Partner &rarr; Finance &rarr; Settlements &rarr; Export Statement</i>.</li>
                    </ul>
                </div>

                <div class="border-2 border-dashed border-gray-300 hover:border-blue-500 rounded-2xl p-6 text-center cursor-pointer transition" onclick="document.getElementById('settlementFileInput').click()">
                    <div class="w-12 h-12 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center mx-auto text-xl mb-2">
                        &#128194;
                    </div>
                    <span class="font-bold text-gray-800 block text-sm" id="importFileNameDisplay">Klik untuk memilih file CSV / Excel</span>
                    <span class="text-gray-400 text-xs block mt-1">Mendukung format .csv, .txt, .xlsx settlement</span>
                    <input type="file" id="settlementFileInput" name="settlement_file" accept=".csv,.txt,.xlsx" required class="hidden" onchange="onSettlementFileSelected(this)">
                </div>

                <div id="importProgressContainer" class="hidden space-y-2">
                    <div class="flex justify-between text-[11px] font-bold text-gray-700">
                        <span>Memproses baris data &amp; mencatat jurnal buku besar...</span>
                        <span id="importProgressPct">100%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div id="importProgressBar" class="bg-blue-600 h-2 rounded-full transition-all duration-300" style="width: 100%"></div>
                    </div>
                </div>

                <div class="pt-4 border-t flex justify-end gap-2">
                    <button type="button" onclick="closeModal('importSettlementModal')" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-xl transition">
                        Batal
                    </button>
                    <button type="submit" id="btnSubmitImport" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-xs transition flex items-center gap-1.5">
                        <span>Mulai Impor &amp; Rekonsiliasi</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================================================================== -->
    <!-- MODAL 10: ATUR ALOKASI KUOTA MULTI-CHANNEL & SAFETY BUFFER           -->
    <!-- ==================================================================== -->
    <div id="stockAllocationModal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl space-y-5 text-xs">
            <div class="flex items-center justify-between border-b pb-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-orange-100 text-orange-600 flex items-center justify-center font-bold text-lg">
                        &#128230;
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-900 text-base" id="allocModalTitle">Atur Alokasi Kuota &amp; Safety Buffer</h3>
                        <p class="text-xs text-gray-500 font-mono" id="allocModalSku">SKU</p>
                    </div>
                </div>
                <button onclick="closeModal('stockAllocationModal')" class="text-gray-400 hover:text-gray-600 text-2xl font-bold">&times;</button>
            </div>

            <form id="formStockAllocation" onsubmit="submitStockAllocation(event)" class="space-y-4">
                <input type="hidden" id="allocId" name="id">

                <div class="p-3 bg-gray-50 rounded-xl border border-gray-200 flex items-center justify-between">
                    <span class="text-gray-600 font-medium">Stok Fisik di Gudang Garut (GDG-UTAMA):</span>
                    <span class="font-bold text-gray-900 text-sm font-mono" id="allocPhysicalStock">0 pcs</span>
                </div>

                <div class="space-y-3">
                    <span class="font-bold text-gray-800 block text-[11px] uppercase tracking-wider">Persentase Kuota Kanal (Total Harus 100%):</span>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="text-gray-600 font-semibold block mb-1">Shopee (%)</label>
                            <input type="number" id="allocShopeePct" name="shopee_quota_pct" min="0" max="100" step="1" required class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl font-bold text-orange-600 text-center">
                        </div>
                        <div>
                            <label class="text-gray-600 font-semibold block mb-1">TikTok (%)</label>
                            <input type="number" id="allocTiktokPct" name="tiktok_quota_pct" min="0" max="100" step="1" required class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl font-bold text-gray-900 text-center">
                        </div>
                        <div>
                            <label class="text-gray-600 font-semibold block mb-1">Offline (%)</label>
                            <input type="number" id="allocOfflinePct" name="offline_quota_pct" min="0" max="100" step="1" required class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl font-bold text-blue-600 text-center">
                        </div>
                    </div>
                </div>

                <div>
                    <label class="font-bold text-gray-700 block mb-1">Ambang Batas Safety Buffer (pcs)</label>
                    <input type="number" id="allocSafetyThreshold" name="safety_buffer_threshold" min="1" max="50" step="1" required class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl font-bold text-amber-700">
                    <span class="text-[11px] text-gray-500 mt-1 block">🛡️ Jika stok fisik gudang tersisa &le; angka ini, kuota Shopee &amp; TikTok otomatis di-0-kan untuk mencegah penalti toko.</span>
                </div>

                <div class="pt-4 border-t flex justify-end gap-2">
                    <button type="button" onclick="closeModal('stockAllocationModal')" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-xl transition">
                        Batal
                    </button>
                    <button type="submit" class="px-5 py-2 bg-brand-600 hover:bg-brand-700 text-white font-bold rounded-xl shadow-xs transition">
                        Simpan Alokasi Kuota
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================================================================== -->
    <!-- MODAL 11: KLAIM RETUR & GANTI RUGI (>14 HARI)                         -->
    <!-- ==================================================================== -->
    <div id="returnClaimsModal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-3xl max-w-4xl w-full p-6 shadow-2xl space-y-5 max-h-[90vh] overflow-y-auto text-xs">
            <div class="flex items-center justify-between border-b pb-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center font-bold text-lg">
                        &#128230;
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-900 text-base">Monitoring Klaim Paket Retur Hilang (&gt;14 Hari)</h3>
                        <p class="text-xs text-gray-500">Pelacakan paket buyer yang belum diterima fisik gudang Garut untuk diajukan ganti rugi logistik</p>
                    </div>
                </div>
                <button onclick="closeModal('returnClaimsModal')" class="text-gray-400 hover:text-gray-600 text-2xl font-bold">&times;</button>
            </div>

            <div class="p-4 bg-rose-50 border border-rose-200 rounded-2xl flex items-center justify-between">
                <div>
                    <span class="font-bold text-rose-900 block">SOP Ganti Rugi Paket Retur Hilang Ekspedisi:</span>
                    <p class="text-[11px] text-rose-800 mt-0.5">Paket berstatus retur yang belum tiba di konveksi &gt;14 hari kerja wajib diajukan klaim penggantian dana ke CS Shopee / TikTok Shop dengan melampirkan nomor resi kurir.</p>
                </div>
                <span class="px-3 py-1 bg-rose-600 text-white font-bold rounded-xl text-xs shrink-0 ml-3"><?= $returnClaimsData['summary']['pending_over_14_days'] ?> Paket Kritis</span>
            </div>

            <!-- Tabel Klaim Retur -->
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-xs">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs whitespace-nowrap">
                        <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider font-bold">
                            <tr>
                                <th class="p-3 pl-4">ORDER &amp; PEMBELI</th>
                                <th class="p-3">EKSPEDISI &amp; RESI</th>
                                <th class="p-3 text-center">TGL RETUR</th>
                                <th class="p-3 text-center">HARI TERTINGGAL</th>
                                <th class="p-3 text-right">NILAI KLAIM</th>
                                <th class="p-3 text-center">STATUS KLAIM</th>
                                <th class="p-3 text-center pr-4">AKSI</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($returnClaimsData['claims'] as $rc): ?>
                            <?php $isOver14 = (int)$rc['days_pending'] >= 14 && in_array($rc['claim_status'], ['MENUNGGU_FISIK', 'KLAIM_DIAJUKAN']); ?>
                            <tr class="<?= $isOver14 ? 'bg-rose-50/50 hover:bg-rose-50/80' : 'hover:bg-gray-50' ?> transition">
                                <td class="p-3 pl-4">
                                    <span class="font-bold text-gray-900 block font-mono"><?= htmlspecialchars($rc['order_id']) ?></span>
                                    <span class="text-[11px] text-gray-500 block"><?= htmlspecialchars($rc['buyer_username'] ?? 'Buyer') ?></span>
                                </td>
                                <td class="p-3">
                                    <span class="font-semibold text-gray-800 block"><?= htmlspecialchars($rc['courier_name']) ?></span>
                                    <span class="font-mono text-gray-400 text-[11px] block"><?= htmlspecialchars($rc['tracking_number'] ?? '-') ?></span>
                                </td>
                                <td class="p-3 text-center text-gray-600"><?= substr($rc['return_initiated_at'], 0, 10) ?></td>
                                <td class="p-3 text-center">
                                    <span class="px-2 py-0.5 rounded text-[11px] font-extrabold <?= $isOver14 ? 'bg-red-100 text-red-700 border border-red-300' : 'bg-gray-100 text-gray-700' ?>">
                                        <?= $rc['days_pending'] ?> hari
                                    </span>
                                </td>
                                <td class="p-3 text-right font-bold text-rose-600"><?= formatRp($rc['claim_amount']) ?></td>
                                <td class="p-3 text-center">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= $rc['claim_status'] === 'DISETUJUI_GANTI_RUGI' ? 'bg-emerald-100 text-emerald-800' : ($rc['claim_status'] === 'KLAIM_DIAJUKAN' ? 'bg-blue-100 text-blue-800' : 'bg-amber-100 text-amber-800') ?>">
                                        <?= htmlspecialchars($rc['claim_status']) ?>
                                    </span>
                                </td>
                                <td class="p-3 text-center pr-4">
                                    <button onclick="promptUpdateClaim(<?= $rc['id'] ?>, '<?= htmlspecialchars($rc['order_id']) ?>', <?= (float)$rc['claim_amount'] ?>)" class="px-2.5 py-1 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded-lg text-xs transition shadow-xs">
                                        Update Status
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="pt-3 border-t flex justify-end">
                <button onclick="closeModal('returnClaimsModal')" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 font-bold rounded-xl">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <!-- ==================================================================== -->
    <!-- MODAL 12: BACKGROUND AUTO-SYNC CRON WORKER TRIGGER                   -->
    <!-- ==================================================================== -->
    <div id="cronWorkerModal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-3xl max-w-2xl w-full p-6 shadow-2xl space-y-4 text-xs">
            <div class="flex items-center justify-between border-b pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold text-base">
                        &#9851;
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-900 text-base">Background Auto-Sync Cron Worker</h3>
                        <p class="text-[11px] text-gray-500">Eksekusi scheduler otomatis sinkronisasi API, status pesanan &amp; buffer gudang</p>
                    </div>
                </div>
                <button onclick="closeModal('cronWorkerModal')" class="text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                <button onclick="triggerCronTask('all')" class="p-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-center transition shadow-xs">
                    Jalankan Semua Task
                </button>
                <button onclick="triggerCronTask('order_sync')" class="p-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-xl text-center transition">
                    Cek Pesanan (15 min)
                </button>
                <button onclick="triggerCronTask('daily_ads')" class="p-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-xl text-center transition">
                    Iklan &amp; Deteksi Bocor
                </button>
                <button onclick="triggerCronTask('safety_buffer')" class="p-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-xl text-center transition">
                    Enforce Safety Buffer
                </button>
            </div>

            <div class="space-y-1">
                <div class="flex items-center justify-between text-[11px] text-gray-500">
                    <span class="font-bold">Terminal Output / Execution Log:</span>
                    <span id="cronStatusLabel" class="text-gray-400">Siap</span>
                </div>
                <pre id="cronLogPre" class="p-4 bg-slate-900 text-emerald-400 rounded-2xl font-mono text-[11px] h-64 overflow-y-auto leading-relaxed shadow-inner">Klik tombol di atas untuk menjalankan task cron worker...</pre>
            </div>

            <div class="pt-3 border-t flex justify-end">
                <button onclick="closeModal('cronWorkerModal')" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 font-bold rounded-xl">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <!-- ==================================================================== -->
    <!-- MODAL 13: EDIT RINCIAN TOKO MARKETPLACE                              -->
    <!-- ==================================================================== -->
    <div id="editStoreModal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-3xl max-w-xl w-full p-6 shadow-2xl space-y-4 max-h-[90vh] overflow-y-auto text-xs">
            <div class="flex items-center justify-between border-b pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-brand-100 text-brand-600 flex items-center justify-center font-bold text-base">
                        &#9881;
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-900 text-base">Edit Rincian Toko Marketplace</h3>
                        <p class="text-[11px] text-gray-500 font-mono" id="editStoreIdDisplay">STR-ID</p>
                    </div>
                </div>
                <button onclick="closeModal('editStoreModal')" class="text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
            </div>

            <form id="formEditStore" onsubmit="submitEditStore(event)" class="space-y-3.5">
                <input type="hidden" id="editStoreId" name="store_id">

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="font-bold text-gray-700 block mb-1">Platform Marketplace</label>
                        <select id="editStorePlatform" name="platform" class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl font-bold text-gray-900">
                            <option value="Shopee">Shopee</option>
                            <option value="TikTok Shop">TikTok Shop</option>
                            <option value="Tokopedia">Tokopedia</option>
                            <option value="Lazada">Lazada</option>
                            <option value="Blibli">Blibli</option>
                            <option value="Offline Store">Offline Store</option>
                        </select>
                    </div>
                    <div>
                        <label class="font-bold text-gray-700 block mb-1">Status Toko</label>
                        <select id="editStoreStatus" name="status" class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl font-bold text-gray-900">
                            <option value="Terhubung">Terhubung (Aktif)</option>
                            <option value="Terputus">Terputus</option>
                            <option value="Token Expired">Token Expired</option>
                            <option value="Nonaktif">Nonaktif</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="font-bold text-gray-700 block mb-1">Nama Toko Display *</label>
                    <input type="text" id="editStoreName" name="store_name" required class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl font-bold text-gray-900">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="font-bold text-gray-700 block mb-1">Username Seller Center</label>
                        <input type="text" id="editStoreIdentifier" name="store_identifier" class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl font-mono text-xs">
                    </div>
                    <div>
                        <label class="font-bold text-gray-700 block mb-1">Mode Sinkronisasi</label>
                        <select id="editStoreSyncMode" name="sync_mode" class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl font-semibold">
                            <option value="API">API Resmi (OAuth2)</option>
                            <option value="EXCEL_SETTLEMENT">Impor Settlement CSV / Excel</option>
                            <option value="HYBRID">Hybrid (API + Impor)</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="font-bold text-gray-700 block mb-1">Rekening Kas/Bank Penampung Hasil Escrow *</label>
                    <select id="editStoreAccountId" name="default_account_id" required class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl font-bold text-emerald-800">
                        <?php foreach ($accounts as $acc): ?>
                        <option value="<?= $acc['id'] ?>">
                            <?= htmlspecialchars($acc['name']) ?> (<?= htmlspecialchars($acc['code']) ?>) - Saldo: <?= formatRp($acc['current_balance']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="p-3 bg-gray-50 rounded-2xl border border-gray-200 space-y-2">
                    <span class="font-bold text-gray-800 block text-[11px] uppercase tracking-wider">Default Potongan Komisi Toko:</span>
                    <div class="grid grid-cols-3 gap-2 text-[11px]">
                        <div>
                            <label class="text-gray-500 block mb-0.5">Admin Fee (%)</label>
                            <input type="number" id="editStoreAdminFee" name="default_admin_fee_pct" step="0.1" class="w-full p-1.5 bg-white border border-gray-200 rounded-lg text-center font-bold">
                        </div>
                        <div>
                            <label class="text-gray-500 block mb-0.5">Gratis Ongkir (%)</label>
                            <input type="number" id="editStoreFreeShipping" name="default_free_shipping_pct" step="0.1" class="w-full p-1.5 bg-white border border-gray-200 rounded-lg text-center font-bold">
                        </div>
                        <div>
                            <label class="text-gray-500 block mb-0.5">Layanan / Pay (%)</label>
                            <input type="number" id="editStorePaymentFee" name="default_payment_fee_pct" step="0.1" class="w-full p-1.5 bg-white border border-gray-200 rounded-lg text-center font-bold">
                        </div>
                    </div>
                </div>

                <div class="pt-3 border-t flex justify-end gap-2">
                    <button type="button" onclick="closeModal('editStoreModal')" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 font-bold rounded-xl text-gray-700">
                        Batal
                    </button>
                    <button type="submit" class="px-5 py-2 bg-brand-600 hover:bg-brand-700 font-bold rounded-xl text-white shadow-xs">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- WHATSAPP FLOATING BUTTON (Persis Foto) -->
    <a href="https://wa.me/6281228811797" target="_blank" class="fixed right-6 bottom-6 w-12 h-12 bg-emerald-500 text-white rounded-full flex items-center justify-center shadow-lg shadow-emerald-500/30 hover:bg-emerald-600 transition z-40">
        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
    </a>

    <script src="assets/marketplace/js/order-transaction-detail.js" defer></script>
    <!-- SCRIPT CLIENT-SIDE INTERAKTIF -->
    <script>
        const STORE_ID = '<?= htmlspecialchars($activeStoreId) ?>';

        function showToast(msg, type = 'success') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `p-4 rounded-2xl shadow-xl text-xs font-bold text-white flex items-center gap-3 transition-all duration-300 pointer-events-auto ${type === 'success' ? 'bg-emerald-600' : 'bg-red-600'}`;
            toast.innerHTML = `
                <span>${type === 'success' ? '&#10003;' : '&#9888;'}</span>
                <span>${msg}</span>
            `;
            container.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 300);
            }, 3500);
        }

        function toggleStoreDropdown() {
            document.getElementById('storeDropdown').classList.toggle('hidden');
        }

        function openModal(id) {
            document.getElementById(id).classList.remove('hidden');
        }

        function closeModal(id) {
            document.getElementById(id).classList.add('hidden');
        }

        function openApiModal() { window.location.href = 'marketplace.php?tab=pengaturan&peng_sub=integrasi'; }
        function openAddExpenseModal() { openModal('addExpenseModal'); }
        function openAddOrderModal() { openModal('addOrderModal'); }
        function openImportModal() { 
            openImportSettlementModal();
        }

        // Inspeksi Rincian Transaksi
        async function inspectOrder(orderId) {
            return window.jlInspectOrder(orderId, STORE_ID, <?= json_encode(Workspace::csrf()) ?>);
        }
        async function editOrder(orderId, isManual) {
            if (!isManual) {
                showToast('Pesanan API tidak dapat ditimpa. Gunakan penyesuaian internal setelah alasan dan audit tersedia.', 'error');
                return;
            }
            const buyer = prompt('Nama pengguna/pembeli internal:', '');
            if (buyer === null) return;
            const status = prompt('Status (Pending, Terkirim, Selesai, Dibatalkan, Retur):', 'Selesai');
            if (status === null) return;
            const hpp = prompt('Total HPP transaksi (Rp):', '0');
            if (hpp === null) return;
            const fd = new FormData();
            fd.append('action', 'update_manual_order');
            fd.append('store_id', <?= json_encode($activeStoreId) ?>);
            fd.append('order_id', orderId);
            fd.append('buyer_username', buyer);
            fd.append('order_status', status);
            fd.append('total_cogs_hpp', hpp);
            try {
                const res = await fetch('api/marketplace-ajax.php', {method:'POST', body:fd});
                const json = await res.json();
                if (!json.success) throw new Error(json.message);
                showToast(json.message, 'success');
                setTimeout(() => location.reload(), 700);
            } catch (err) { showToast(err.message, 'error'); }
        }

        async function archiveOrder(orderId) {
            const reason = prompt('Alasan arsip dari laporan internal (wajib):', 'Duplikat / koreksi internal');
            if (reason === null || !reason.trim()) return;
            if (!confirm('Pesanan tidak dihapus dari platform. Pesanan hanya dikeluarkan dari laporan internal dan tindakan ini dicatat. Lanjutkan?')) return;
            const fd = new FormData();
            fd.append('action', 'archive_order');
            fd.append('store_id', <?= json_encode($activeStoreId) ?>);
            fd.append('order_id', orderId);
            fd.append('reason', reason.trim());
            try {
                const res = await fetch('api/marketplace-ajax.php', {method:'POST', body:fd});
                const json = await res.json();
                if (!json.success) throw new Error(json.message);
                showToast(json.message, 'success');
                setTimeout(() => location.reload(), 700);
            } catch (err) { showToast(err.message, 'error'); }
        }

        // Detail Iklan Produk Modal
        function openProductAdDetail(p) {
            document.getElementById('adModalTitle').innerText = p.product_name;
            document.getElementById('adModalSku').innerText = `ID: ${p.marketplace_item_id} | SKU: ${p.sku} | Platform: ${p.platform}`;
            
            const isAman = p.is_aman;
            const netProfit = Number(p.net_profit);
            const isLoss = netProfit < 0;

            document.getElementById('adModalBody').innerHTML = `
                <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl border border-gray-100">
                    <img src="${p.image_url}" class="w-14 h-14 rounded-lg object-cover">
                    <div class="flex-1">
                        <span class="font-bold text-gray-900 block">${p.product_name}</span>
                        <span class="text-[11px] text-gray-500">${p.orders_count} Pesanan (${p.qty_sold} Qty) &bull; Biaya/Pesanan: Rp ${Number(p.cost_per_order).toLocaleString('id-ID')}</span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2 text-center">
                    <div class="p-3 bg-rose-50/70 border border-rose-100 rounded-xl">
                        <span class="text-gray-500 block text-[11px]">Biaya Iklan</span>
                        <span class="font-extrabold text-rose-600 text-base">Rp ${Number(p.ad_spend).toLocaleString('id-ID')}</span>
                    </div>
                    <div class="p-3 bg-blue-50/70 border border-blue-100 rounded-xl">
                        <span class="text-gray-500 block text-[11px]">Omzet Bruto</span>
                        <span class="font-extrabold text-blue-600 text-base">Rp ${Number(p.gross_revenue).toLocaleString('id-ID')}</span>
                    </div>
                </div>

                <div class="p-3 bg-gray-50 rounded-xl border border-gray-200 space-y-1.5">
                    <div class="flex justify-between font-medium">
                        <span>Gross ROAS</span>
                        <span class="font-bold text-gray-900">${p.gross_roas}x</span>
                    </div>
                    <div class="flex justify-between font-medium">
                        <span>Net ROAS (Setelah Potongan Platform)</span>
                        <span class="font-bold text-gray-700">${p.net_roas}x</span>
                    </div>
                    <div class="flex justify-between font-medium">
                        <span>Actual ROAS (Setelah HPP Konveksi)</span>
                        <span class="font-bold text-gray-800">${p.actual_roas}x</span>
                    </div>
                    <div class="flex justify-between font-medium pt-1 border-t">
                        <span>Min. ROAS (Break-Even Target)</span>
                        <span class="font-bold ${isAman ? 'text-emerald-600' : 'text-rose-600'}">${p.min_roas}x (${isAman ? '✓ AMAN' : '✗ RUGI'})</span>
                    </div>
                </div>

                <div class="p-3 bg-emerald-50 rounded-xl border border-emerald-200 space-y-1">
                    <div class="flex justify-between font-semibold">
                        <span class="text-emerald-900">Profit Riil Sebelum Iklan</span>
                        <span class="text-emerald-700 font-bold">Rp ${Number(p.profit_riil).toLocaleString('id-ID')} (${p.profit_riil_pct}%)</span>
                    </div>
                    <div class="flex justify-between font-semibold">
                        <span class="text-rose-800">Beban Biaya Iklan</span>
                        <span class="text-rose-600 font-bold">-Rp ${Number(p.ad_spend).toLocaleString('id-ID')}</span>
                    </div>
                    <div class="flex justify-between text-sm font-extrabold pt-1.5 border-t border-emerald-200">
                        <span class="text-gray-900">Net Profit Akhir</span>
                        <span class="${isLoss ? 'text-rose-600' : 'text-emerald-700'}">${isLoss ? '-' : ''}Rp ${Math.abs(netProfit).toLocaleString('id-ID')} (${p.net_profit_pct}%)</span>
                    </div>
                </div>
            `;
            openModal('productAdModal');
        }

        // Sinkronisasi Iklan Real-time
        async function syncMarketingAds(storeId) {
            const btn = document.getElementById('btnRefreshMkt');
            if (btn) btn.innerHTML = 'Memproses...';
            showToast('Menghubungkan ke API Resmi Shopee Ads & TikTok Ads...', 'success');

            try {
                const res = await fetch(`api/marketplace-ajax.php?action=sync_marketing_ads&store_id=${encodeURIComponent(storeId)}`);
                const json = await res.json();
                if (!json.success) throw new Error(json.message);

                showToast(json.message, 'success');
                setTimeout(() => location.reload(), 1200);
            } catch (err) {
                showToast(err.message, 'error');
            } finally {
                if (btn) btn.innerHTML = '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg> Refresh';
            }
        }

        function applyMktFilter() {
            const q = document.getElementById('mktSearchInput').value;
            const plt = document.getElementById('mktPlatformSelect').value;
            const url = new URL(window.location.href);
            url.searchParams.set('mkt_search', q);
            url.searchParams.set('mkt_platform', plt);
            url.searchParams.delete('page');
            window.location.href = url.toString();
        }

        // Submit Tambah Pengeluaran
        async function submitAddExpense(e) {
            e.preventDefault();
            const form = e.target;
            const data = new FormData(form);
            data.append('action', 'add_expense');

            try {
                const res = await fetch('api/marketplace-ajax.php', { method: 'POST', body: data });
                const json = await res.json();
                if (!json.success) throw new Error(json.message);

                showToast(json.message, 'success');
                closeModal('addExpenseModal');
                setTimeout(() => location.reload(), 1000);
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        // Hapus Pengeluaran
        async function deleteExpense(id) {
            if (!confirm('Hapus pengeluaran marketplace ini? Mutasi kas lama tidak diubah otomatis.')) return;

            const fd = new FormData();
            fd.append('action', 'delete_expense');
            fd.append('id', id);
            fd.append('store_id', <?= json_encode($activeStoreId) ?>);

            try {
                const res = await fetch('api/marketplace-ajax.php', { method: 'POST', body: fd });
                const json = await res.json();
                if (!json.success) throw new Error(json.message);

                showToast(json.message, 'success');
                setTimeout(() => location.reload(), 1000);
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        // Auto-fill HPP saat pilih variant di order manual
        function autoFillCogs(select) {
            const opt = select.selectedOptions[0];
            if (opt) {
                const hpp = opt.getAttribute('data-hpp') || 0;
                const sku = opt.getAttribute('data-sku') || '';
                if (hpp > 0) document.getElementById('orderHpp').value = hpp;
                if (sku) document.getElementById('orderSku').value = sku;
            }
        }

        // Submit Tambah Pesanan Manual
        async function submitAddOrder(e) {
            e.preventDefault();
            const form = e.target;
            const data = new FormData(form);
            data.append('action', 'add_order');

            try {
                const res = await fetch('api/marketplace-ajax.php', { method: 'POST', body: data });
                const json = await res.json();
                if (!json.success) throw new Error(json.message);

                showToast(json.message, 'success');
                closeModal('addOrderModal');
                setTimeout(() => location.reload(), 1000);
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        // Buka Modal Atur HPP
        function openHppModal(sku, productName, currentHpp, variantId = '') {
            document.getElementById('hppSku').value = sku;
            document.getElementById('hppProductName').value = productName;
            document.getElementById('hppValue').value = currentHpp || '';
            document.getElementById('hppVariantId').value = variantId;
            openModal('hppModal');
        }

        function onSelectVariantForHpp(sel) {
            const opt = sel.selectedOptions[0];
            if (opt && opt.value) {
                document.getElementById('hppVariantId').value = opt.value;
                const hpp = opt.getAttribute('data-hpp');
                if (hpp) document.getElementById('hppValue').value = hpp;
            }
        }

        // Submit Update HPP
        async function submitUpdateHpp(e) {
            e.preventDefault();
            const form = e.target;
            const data = new FormData(form);
            data.append('action', 'update_sku_hpp');

            try {
                const res = await fetch('api/marketplace-ajax.php', { method: 'POST', body: data });
                const json = await res.json();
                if (!json.success) throw new Error(json.message);

                showToast(json.message, 'success');
                closeModal('hppModal');
                setTimeout(() => location.reload(), 1000);
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        // Uji Ping API Resmi
        async function pingApi(storeId) {
            const btn = document.getElementById('btnPingApi');
            btn.innerText = 'Menguji...';
            try {
                const res = await fetch(`api/marketplace-ajax.php?action=test_api_connection&store_id=${encodeURIComponent(storeId)}`);
                const json = await res.json();
                if (!json.success) throw new Error(json.message);

                document.getElementById('apiStatusText').innerText = `Status: ${json.status}`;
                document.getElementById('apiPingText').innerText = `Terakhir diverifikasi: ${json.timestamp}`;
                showToast(json.message, 'success');
            } catch (err) {
                showToast(err.message, 'error');
            } finally {
                btn.innerText = 'Uji Ping API';
            }
        }

        // Simpan Kredensial API
        async function submitApiCredentials(e) {
            e.preventDefault();
            const form = e.target;
            const data = new FormData(form);
            data.append('action', 'save_api_credentials');

            try {
                const res = await fetch('api/marketplace-ajax.php', { method: 'POST', body: data });
                const json = await res.json();
                if (!json.success) throw new Error(json.message);

                showToast(json.message, 'success');
                closeModal('apiModal');
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        // Trigger Sync
        let storeSyncPending = false;
        async function triggerSync(storeId) {
            try { await window.jlStartBackgroundSync(storeId); }
            catch(error) { showToast(error.message, 'error'); }
        }

        // Filters Helper
        function applyFilterSearch() {
            const q = document.getElementById('transSearchInput').value;
            const url=new URL(location.href);url.searchParams.set('search',q);url.searchParams.set('search_by',document.getElementById('transSearchBy').value);url.searchParams.delete('page');location.href=url.toString();
        }

        function applyStatusFilter(st) {
            const url=new URL(location.href);url.searchParams.set('status',st);url.searchParams.delete('page');location.href=url.toString();
        }

        function applyExpFilter() {
            const cat = document.getElementById('expCategorySelect').value;
            const q = document.getElementById('expSearchInput').value;
            const dt = document.getElementById('expDateInput').value;
            window.location.href = `marketplace.php?store=${encodeURIComponent(STORE_ID)}&tab=pengeluaran&exp_category=${encodeURIComponent(cat)}&exp_search=${encodeURIComponent(q)}&exp_date=${encodeURIComponent(dt)}`;
        }

        function applyInvFilter() {
            const q = document.getElementById('invSearchInput').value;
            const flt = document.getElementById('invFilterSelect').value;
            window.location.href = `marketplace.php?store=${encodeURIComponent(STORE_ID)}&tab=inventori&inv_filter=${encodeURIComponent(flt)}&inv_search=${encodeURIComponent(q)}`;
        }

        function copyWebhook(inputId) {
            const copyText = document.getElementById(inputId);
            copyText.select();
            copyText.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(copyText.value);
            showToast('URL Webhook berhasil disalin ke clipboard!', 'success');
        }

        // ==========================================================
        // FITUR BARU: SIMULASI HARGA & BREAK-EVEN ROAS
        // ==========================================================
        function openPriceSimulatorModal() {
            openModal('priceSimulatorModal');
            calcLivePriceSimulation();
        }

        function applyVariantPreset(select) {
            const opt = select.selectedOptions[0];
            if (opt && opt.value) {
                const name = opt.getAttribute('data-name');
                const hpp = opt.getAttribute('data-hpp');
                if (name) document.getElementById('simProductName').value = name;
                if (hpp) document.getElementById('simCogsHpp').value = hpp;
                calcLivePriceSimulation();
            }
        }

        function calcLivePriceSimulation() {
            const cogs = parseFloat(document.getElementById('simCogsHpp').value) || 0;
            const targetMargin = (parseFloat(document.getElementById('simTargetMargin').value) || 25) / 100;
            const admin = (parseFloat(document.getElementById('simAdminFee').value) || 11) / 100;
            const freeShipping = (parseFloat(document.getElementById('simFreeShipping').value) || 4) / 100;
            const payment = (parseFloat(document.getElementById('simPaymentFee').value) || 1) / 100;
            const adBudget = (parseFloat(document.getElementById('simAdBudget').value) || 10) / 100;

            let divisorNormal = 1 - (admin + freeShipping + payment + adBudget + targetMargin);
            if (divisorNormal <= 0.1) divisorNormal = 0.1;
            const recNormal = Math.ceil((cogs / divisorNormal) / 500) * 500;

            let divisorFs = 1 - (admin + freeShipping + payment + 0.05);
            if (divisorFs <= 0.1) divisorFs = 0.1;
            const recFs = Math.ceil((cogs / divisorFs) / 500) * 500;

            const grossMarginRatio = 1 - (cogs / (recNormal || 1)) - (admin + freeShipping + payment);
            const minRoas = grossMarginRatio > 0 ? (1 / grossMarginRatio) : 99.9;
            const netProfitPerPcs = Math.round(recNormal * targetMargin);

            const elNormal = document.getElementById('resNormalPrice');
            const elFs = document.getElementById('resFlashSalePrice');
            const elRoas = document.getElementById('resBreakEvenRoas');
            const elExp = document.getElementById('resRoasExplanation');
            const elProfit = document.getElementById('resNetProfitPerPcs');

            if (elNormal) elNormal.innerText = 'Rp ' + Number(recNormal).toLocaleString('id-ID');
            if (elFs) elFs.innerText = 'Rp ' + Number(recFs).toLocaleString('id-ID');
            if (elRoas) elRoas.innerText = minRoas.toFixed(2) + 'x';
            if (elExp) {
                elExp.innerHTML = `Iklan Shopee/TikTok Ads hanya boleh dipertahankan jika ROAS aktual &ge; <b>${minRoas.toFixed(2)}x</b>. Jika ROAS harian &lt; ${minRoas.toFixed(2)}x, produk masuk kategori <b>BOCOR</b> dan segera kurangi bid kata kunci!`;
            }
            if (elProfit) elProfit.innerText = 'Rp ' + Number(netProfitPerPcs).toLocaleString('id-ID') + ' / pcs';
        }

        function copySimPrice(elementId) {
            const el = document.getElementById(elementId);
            if (!el) return;
            const raw = el.innerText.replace(/[^0-9]/g, '');
            navigator.clipboard.writeText(raw);
            showToast(`Harga Rp ${Number(raw).toLocaleString('id-ID')} berhasil disalin ke clipboard!`, 'success');
        }

        async function submitPriceSimulation(e) {
            e.preventDefault();
            const form = e.target;
            const data = new FormData(form);
            data.append('action', 'simulate_price');

            try {
                const res = await fetch('api/marketplace-ajax.php', { method: 'POST', body: data });
                const json = await res.json();
                if (!json.success) throw new Error(json.message);

                showToast('Preset simulasi harga berhasil disimpan ke database!', 'success');
                closeModal('priceSimulatorModal');
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        // ==========================================================
        // FITUR BARU: IMPOR SETTLEMENT CSV / EXCEL
        // ==========================================================
        function openImportSettlementModal() {
            const fileInput = document.getElementById('settlementFileInput');
            if (fileInput) fileInput.value = '';
            const label = document.getElementById('importFileNameDisplay');
            if (label) label.innerText = 'Klik untuk memilih file CSV / Excel';
            const prog = document.getElementById('importProgressContainer');
            if (prog) prog.classList.add('hidden');
            openModal('importSettlementModal');
        }

        function onSettlementFileSelected(input) {
            if (input.files && input.files[0]) {
                document.getElementById('importFileNameDisplay').innerText = input.files[0].name;
            }
        }

        async function submitImportSettlement(e) {
            e.preventDefault();
            const form = e.target;
            const btn = document.getElementById('btnSubmitImport');
            const prog = document.getElementById('importProgressContainer');
            const progBar = document.getElementById('importProgressBar');
            const progPct = document.getElementById('importProgressPct');

            if (prog) prog.classList.remove('hidden');
            if (btn) btn.disabled = true;
            if (progBar) progBar.style.width = '65%';
            if (progPct) progPct.innerText = '65%';

            const data = new FormData(form);
            data.append('action', 'import_settlement_csv');

            try {
                const res = await fetch('api/marketplace-ajax.php', { method: 'POST', body: data });
                const json = await res.json();
                if (!json.success) throw new Error(json.message);

                if (progBar) progBar.style.width = '100%';
                if (progPct) progPct.innerText = '100%';
                showToast(json.message, 'success');
                closeModal('importSettlementModal');
                setTimeout(() => location.reload(), 1500);
            } catch (err) {
                showToast(err.message, 'error');
                if (prog) prog.classList.add('hidden');
            } finally {
                if (btn) btn.disabled = false;
            }
        }

        // ==========================================================
        // FITUR BARU: ALOKASI KUOTA MULTI-CHANNEL & SAFETY BUFFER
        // ==========================================================
        function openStockAllocationModal(id, name, sku, physical, shopee, tiktok, offline, threshold) {
            document.getElementById('allocId').value = id;
            document.getElementById('allocModalTitle').innerText = 'Atur Alokasi: ' + name;
            document.getElementById('allocModalSku').innerText = 'SKU: ' + sku;
            document.getElementById('allocPhysicalStock').innerText = physical + ' pcs';
            document.getElementById('allocShopeePct').value = shopee;
            document.getElementById('allocTiktokPct').value = tiktok;
            document.getElementById('allocOfflinePct').value = offline;
            document.getElementById('allocSafetyThreshold').value = threshold;
            openModal('stockAllocationModal');
        }

        async function submitStockAllocation(e) {
            e.preventDefault();
            const form = e.target;
            const shopee = parseFloat(document.getElementById('allocShopeePct').value) || 0;
            const tiktok = parseFloat(document.getElementById('allocTiktokPct').value) || 0;
            const offline = parseFloat(document.getElementById('allocOfflinePct').value) || 0;

            const total = shopee + tiktok + offline;
            if (Math.abs(total - 100) > 0.01) {
                showToast(`Total kuota harus tepat 100%! (Saat ini: ${total}%)`, 'error');
                return;
            }

            const data = new FormData(form);
            data.append('action', 'update_stock_allocation');

            try {
                const res = await fetch('api/marketplace-ajax.php', { method: 'POST', body: data });
                const json = await res.json();
                if (!json.success) throw new Error(json.message);

                showToast(json.message, 'success');
                closeModal('stockAllocationModal');
                setTimeout(() => location.reload(), 1000);
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        async function runSafetyBufferSync() {
            showToast('Mengevaluasi buffer gudang & kuota online...', 'success');
            try {
                const res = await fetch('api/marketplace-ajax.php?action=run_safety_buffer_sync');
                const json = await res.json();
                if (!json.success) throw new Error(json.message);
                showToast(json.message, 'success');
                setTimeout(() => location.reload(), 1500);
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        // ==========================================================
        // FITUR BARU: KLAIM PAKET RETUR HILANG EKSPEDISI (>14 HARI)
        // ==========================================================
        function openReturnClaimsModal() {
            openModal('returnClaimsModal');
        }

        async function promptUpdateClaim(claimId, orderId, defaultAmount) {
            const statusChoice = prompt(
                `Update Status Klaim Retur Order #${orderId}:\n\n` +
                `Pilih nomor:\n` +
                `1 = KLAIM_DIAJUKAN (Klaim terkirim ke CS ekspedisi)\n` +
                `2 = DISETUJUI_GANTI_RUGI (Ekspedisi menyetujui kompensasi)\n` +
                `3 = BARANG_DITERIMA_GUDANG (Fisik paket akhirnya tiba di Garut)\n` +
                `4 = DITOLAK_KURIR (Klaim ditolak kurir)`,
                "2"
            );
            if (!statusChoice) return;

            let status = 'KLAIM_DIAJUKAN';
            if (statusChoice === '1') status = 'KLAIM_DIAJUKAN';
            else if (statusChoice === '2') status = 'DISETUJUI_GANTI_RUGI';
            else if (statusChoice === '3') status = 'BARANG_DITERIMA_GUDANG';
            else if (statusChoice === '4') status = 'DITOLAK_KURIR';
            else status = statusChoice.trim().toUpperCase();

            let amount = 0;
            if (status === 'DISETUJUI_GANTI_RUGI') {
                const amtInput = prompt(`Nominal Ganti Rugi yang Disetujui Pihak Kurir (Rp):`, defaultAmount);
                if (amtInput === null) return;
                amount = parseFloat(amtInput.replace(/[^0-9.]/g, '')) || 0;
            }

            const notes = prompt(`Catatan Tambahan untuk Rekam Jejak Audit:`, `Pembaruan status klaim via monitoring dashboard`);
            if (notes === null) return;

            const fd = new FormData();
            fd.append('action', 'update_return_claim');
            fd.append('claim_id', claimId);
            fd.append('store_id', <?= json_encode($activeStoreId) ?>);
            fd.append('claim_status', status);
            fd.append('reimbursement_amount', amount);
            fd.append('notes', notes);

            try {
                const res = await fetch('api/marketplace-ajax.php', { method: 'POST', body: fd });
                const json = await res.json();
                if (!json.success) throw new Error(json.message);
                showToast(json.message, 'success');
                setTimeout(() => location.reload(), 1200);
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        // ==========================================================
        // FITUR BARU: BACKGROUND AUTO-SYNC CRON WORKER
        // ==========================================================
        function runCronWorker() {
            openModal('cronWorkerModal');
        }

        async function triggerCronTask(task = 'all') {
            const lbl = document.getElementById('cronStatusLabel');
            const pre = document.getElementById('cronLogPre');
            if (lbl) lbl.innerText = `Menjalankan task [${task}]...`;
            if (pre) pre.innerText = `[CRON RUNNER] Memulai eksekusi task: ${task}...\nMenghubungi worker background PHP CLI...\nHarap tunggu proses sinkronisasi database & API...`;

            const fd = new FormData();
            fd.append('action', 'trigger_cron_worker');
            fd.append('task', task);

            try {
                const res = await fetch('api/marketplace-ajax.php', { method: 'POST', body: fd });
                const json = await res.json();
                if (lbl) lbl.innerText = json.success ? 'Selesai Sukses' : 'Gagal';
                if (pre) {
                    pre.innerText = json.log_output || json.message;
                }
                showToast(json.message, json.success ? 'success' : 'error');
            } catch (err) {
                if (lbl) lbl.innerText = 'Error';
                if (pre) pre.innerText = `Error: ${err.message}`;
                showToast(err.message, 'error');
            }
        }

        // ==========================================================
        // FITUR BARU: MARKETING TOGGLE PPN 11%
        // ==========================================================
        function togglePpn() {
            const url = new URL(window.location.href);
            const cur = url.searchParams.get('ppn');
            if (cur === '1') {
                url.searchParams.delete('ppn');
            } else {
                url.searchParams.set('ppn', '1');
            }
            url.searchParams.set('tab', 'marketing');
            window.location.href = url.toString();
        }

        // ==========================================================
        // FITUR BARU: PENGATURAN TOKO & APLIKASI MARKETPLACE
        // ==========================================================
        async function submitAddStore(e) {
            e.preventDefault();
            const btn = document.getElementById('btnSubmitAddStore');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = 'Menyimpan Toko...';
            }

            const form = e.target;
            const data = new FormData(form);
            data.append('action', 'add_store');

            try {
                const res = await fetch('api/marketplace-ajax.php', { method: 'POST', body: data });
                const json = await res.json();
                if (!json.success) throw new Error(json.message);

                showToast(json.message, 'success');
                setTimeout(() => {
                    window.location.href = `marketplace.php?tab=pengaturan&peng_sub=toko`;
                }, 1200);
            } catch (err) {
                showToast(err.message, 'error');
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = `
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>Daftarkan Toko Baru</span>
                    `;
                }
            }
        }

        async function openEditStoreModal(storeId) {
            try {
                showToast('Mengambil rincian toko...', 'success');
                const res = await fetch(`api/marketplace-ajax.php?action=get_store_detail&store_id=${encodeURIComponent(storeId)}`);
                const json = await res.json();
                if (!json.success) throw new Error(json.message);

                const d = json.data;
                document.getElementById('editStoreId').value = d.id;
                document.getElementById('editStoreIdDisplay').innerText = `ID: ${d.id}`;
                document.getElementById('editStoreName').value = d.store_name;
                document.getElementById('editStorePlatform').value = d.platform;
                document.getElementById('editStoreIdentifier').value = d.store_identifier || '';
                document.getElementById('editStoreAccountId').value = d.default_account_id || '';
                document.getElementById('editStoreAdminFee').value = d.default_admin_fee_pct || '11.0';
                document.getElementById('editStoreFreeShipping').value = d.default_free_shipping_pct || '4.0';
                document.getElementById('editStorePaymentFee').value = d.default_payment_fee_pct || '1.0';
                document.getElementById('editStoreSyncMode').value = d.sync_mode || 'API';
                document.getElementById('editStoreStatus').value = d.status || 'Terhubung';

                openModal('editStoreModal');
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        async function submitEditStore(e) {
            e.preventDefault();
            const form = e.target;
            const data = new FormData(form);
            data.append('action', 'update_store');

            try {
                const res = await fetch('api/marketplace-ajax.php', { method: 'POST', body: data });
                const json = await res.json();
                if (!json.success) throw new Error(json.message);

                showToast(json.message, 'success');
                closeModal('editStoreModal');
                setTimeout(() => location.reload(), 1000);
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        async function toggleStoreStatus(storeId, currentStatus) {
            const newStatus = currentStatus === 'Terhubung' ? 'Nonaktif' : 'Terhubung';
            if (!confirm(`Apakah Anda yakin ingin mengubah status toko "${storeId}" menjadi "${newStatus}"?`)) return;

            const fd = new FormData();
            fd.append('action', 'toggle_store_status');
            fd.append('store_id', storeId);
            fd.append('status', newStatus);

            try {
                const res = await fetch('api/marketplace-ajax.php', { method: 'POST', body: fd });
                const json = await res.json();
                if (!json.success) throw new Error(json.message);

                showToast(json.message, 'success');
                setTimeout(() => location.reload(), 1000);
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        async function archiveStore(button, storeId, storeName) {
            if (!confirm(`Hapus "${storeName}" dari daftar toko aktif? Koneksi internal dan sinkronisasi dihentikan. Riwayat transaksi tetap tersimpan. Izin di Seller Center perlu dicabut terpisah.`)) return;
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            try {
                const body = new FormData();
                body.append('action', 'archive_store');
                body.append('store_id', storeId);
                const response = await fetch('api/marketplace-ajax.php', {method:'POST', body});
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'Toko belum dapat dihapus.');
                showToast(result.message, 'success');
                if (sessionStorage.getItem('jl_marketplace_store') === storeId) sessionStorage.removeItem('jl_marketplace_store');
                if (storeId === <?= json_encode($activeStoreId) ?>) {
                    location.assign('marketplace.php?tab=pengaturan&peng_sub=integrasi');
                } else {
                    location.reload();
                }
            } catch (error) {
                showToast(error.message || 'Gagal menghapus toko. Coba lagi.', 'error');
                button.disabled = false;
                button.removeAttribute('aria-busy');
            }
        }

        async function restoreStore(button, storeId) {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            try {
                const body = new FormData();
                body.append('action', 'restore_store');
                body.append('store_id', storeId);
                const response = await fetch('api/marketplace-ajax.php', {method:'POST', body});
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'Toko belum dapat ditampilkan kembali.');
                showToast(result.message, 'success');
                location.reload();
            } catch (error) {
                showToast(error.message || 'Gagal menampilkan toko. Coba lagi.', 'error');
                button.disabled = false;
                button.removeAttribute('aria-busy');
            }
        }

        async function submitAppSettings(e) {
            e.preventDefault();
            const form = e.target;
            const data = new FormData(form);
            data.append('action', 'save_app_settings');

            try {
                const res = await fetch('api/marketplace-ajax.php', { method: 'POST', body: data });
                const json = await res.json();
                if (!json.success) throw new Error(json.message);

                showToast(json.message, 'success');
            } catch (err) {
                showToast(err.message, 'error');
            }
        }
    </script>
<?php endif; ?>
<?php if(!$globalSettings&&$activeTab==='transaksi'): ?><script src="assets/marketplace/js/marketplace-auto-refresh.js" data-store-id="<?= htmlspecialchars($activeStoreId,ENT_QUOTES) ?>" defer></script><?php endif; ?>
<?php require (dirname(__DIR__, 4) . '/app/Modules/Marketplace/Views/browser_auto_sync_driver.php'); ?></body>
</html>
