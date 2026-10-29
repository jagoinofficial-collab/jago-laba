<?php
if(PHP_SAPI!=='cli')exit;
require_once (dirname(__DIR__, 2) . '/app/Bootstrap/bootstrap.php');MarketplaceModule::boot();
$pdo=Database::pdo();
foreach($pdo->query("SELECT s.id,s.company_key,s.platform,s.store_name,c.status,COUNT(v.id) AS variants FROM marketplace_stores s LEFT JOIN marketplace_oauth_connections c ON c.store_id=s.id AND c.company_key=s.company_key LEFT JOIN marketplace_catalog_variants v ON v.store_id=s.id WHERE s.archived_at IS NULL GROUP BY s.id,s.company_key,s.platform,s.store_name,c.status")->fetchAll(PDO::FETCH_ASSOC) as $row)echo json_encode($row,JSON_UNESCAPED_UNICODE).PHP_EOL;
foreach($pdo->query('SELECT job_type,status,last_error_code,COUNT(*) AS count FROM marketplace_sync_jobs GROUP BY job_type,status,last_error_code')->fetchAll(PDO::FETCH_ASSOC) as $row)echo json_encode($row).PHP_EOL;
$_SESSION['company_key']='LOCAL-COMPANY';$_SESSION['user_id']='CATALOG-CHECK';$_SESSION['role']='OWNER';
$service=new MarketplaceService();foreach($service->getStores() as $activeStore){
 $activeStoreId=$activeStore['id'];$catalogService=new MarketplaceCatalogService();$local=$catalogService->listingForStore($activeStoreId);$global=$catalogService->listing(['store_id'=>$activeStoreId,'official_only'=>true]);
 if(count($local['products'])!==count($global['products']))throw new RuntimeException('Store/global catalog mismatch');
 if(!function_exists('formatRp')){function formatRp($value):string{return $value===null?'Belum tersedia':'Rp '.number_format((float)$value,0,',','.');}}
 ob_start();require (dirname(__DIR__, 2) . '/app/Modules/Marketplace/Views/marketplace_store_products.php');$html=ob_get_clean();
 if($local['products']&&str_contains($html,'Katalog API belum tersedia.'))throw new RuntimeException('Catalog rendered as empty');
 echo json_encode(['store'=>$activeStore['store_name'],'rendered_products'=>count($local['products']),'rendered_variants'=>array_sum(array_map(static fn($p)=>count($p['variants']),$local['products'])),'matches_global'=>true]).PHP_EOL;
}
