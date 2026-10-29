<?php
declare(strict_types=1);
require_once (dirname(__DIR__, 5) . '/app/Bootstrap/bootstrap.php');
MarketplaceModule::boot();
header('Cache-Control: no-store, private');
try {
    Workspace::require(Workspace::MARKETPLACE);
    if (($_SERVER['REQUEST_METHOD']??'GET')!=='GET') {http_response_code(405);header('Allow: GET');exit;}
    $globalFilters=[
        'store_id'=>trim((string)($_GET['store_filter']??'')), 'platform'=>trim((string)($_GET['platform']??'')),
        'date_start'=>trim((string)($_GET['date_start']??'')), 'date_end'=>trim((string)($_GET['date_end']??'')),
        'date_basis'=>$_GET['date_basis']??'created','analytics_mode'=>$_GET['analytics_mode']??'all',
        'analytics_stores'=>$_GET['analytics_stores']??[], 'include_disconnected'=>($_GET['include_disconnected']??'')==='1',
    ];
    if($globalFilters['store_id']==='')throw new InvalidArgumentException('Pilih toko untuk membuka analitik.');
    $analyticsReport=(new MarketplaceAnalyticsService())->report($globalFilters);
    if(($_GET['format']??'')==='xlsx'){
        require_once dirname(__DIR__,2).'/Services/MarketplaceAnalyticsXlsx.php';
        $bytes=MarketplaceAnalyticsXlsx::bytes($analyticsReport);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="analitik-toko-'.$analyticsReport['start'].'-'.$analyticsReport['end'].'.xlsx"');
        header('Content-Length: '.strlen($bytes));echo $bytes;exit;
    }
    if(($_GET['format']??'')==='csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="analitik-global-'.$analyticsReport['start'].'-'.$analyticsReport['end'].'.csv"');
        require_once (dirname(__DIR__, 5) . '/app/Modules/Marketplace/Services/MarketplaceAnalyticsCsv.php');
        $out=fopen('php://output','wb');
        MarketplaceAnalyticsCsv::write($out,$analyticsReport);
        fclose($out);exit;
    }
    if(!function_exists('formatRp')){function formatRp($value):string{return 'Rp '.number_format((float)$value,0,',','.');}}
    $query=static function(array $changes=[])use($globalFilters,$analyticsReport):string {
        return http_build_query(array_merge(['store'=>$globalFilters['store_id'],'tab'=>'analitik','period'=>'custom','platform'=>$globalFilters['platform'],'date_start'=>$analyticsReport['start'],'date_end'=>$analyticsReport['end'],'date_basis'=>$analyticsReport['date_basis'],'analytics_mode'=>$analyticsReport['mode'],'analytics_stores'=>$globalFilters['analytics_stores'],'include_disconnected'=>$globalFilters['include_disconnected']?'1':''],$changes));
    };
    header('Content-Type: text/html; charset=utf-8');
    require (dirname(__DIR__, 5) . '/app/Modules/Marketplace/Views/marketplace_store_analytics.php');
} catch(Throwable $error) {
    if(http_response_code()<400)http_response_code($error instanceof DomainException?403:($error instanceof InvalidArgumentException?422:500));
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success'=>false,'message'=>$error instanceof DomainException||$error instanceof InvalidArgumentException?$error->getMessage():'Analitik belum dapat dimuat. Periksa sesi dan koneksi database.']);
}
