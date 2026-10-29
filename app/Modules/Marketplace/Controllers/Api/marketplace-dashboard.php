<?php
declare(strict_types=1);
require (dirname(__DIR__, 5) . '/app/Bootstrap/bootstrap.php');MarketplaceModule::boot();
header('Cache-Control: no-store, private');
try{
 Workspace::require(Workspace::MARKETPLACE);
 require_once (dirname(__DIR__, 5) . '/app/Modules/Marketplace/Services/MarketplaceDashboardService.php');
 $activeStoreId=trim((string)($_GET['store']??''));$scope=new MarketplaceScopeService(Database::pdo());$activeStore=$scope->assertStoreAccess($activeStoreId);
 foreach((new MarketplaceService())->getStores(false) as $store)if($store['id']===$activeStoreId){$activeStore=$store;break;}
 $storeDashboard=(new MarketplaceDashboardService(Database::pdo()))->report($activeStoreId,$_GET);
 if(($_GET['format']??'')==='csv'){
  header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="rincian-dashboard.csv"');$out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,['Pesanan','Keuangan','Penjualan','Untuk Dibayar','HPP Produk','Laba setelah HPP']);
  foreach($storeDashboard['rows'] as $r)fputcsv($out,["'".$r['id'],$r['state'],$r['sales'],$r['net'],$r['hpp'],$r['profit']]);fclose($out);exit;
 }
 header('Content-Type: text/html; charset=utf-8');require (dirname(__DIR__, 5) . '/app/Modules/Marketplace/Views/marketplace_store_dashboard.php');
}catch(Throwable $e){http_response_code($e instanceof InvalidArgumentException?422:403);echo 'Dashboard tidak dapat dimuat. Periksa akses toko dan periode.';}
