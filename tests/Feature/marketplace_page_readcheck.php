<?php
require (dirname(__DIR__, 2) . '/app/Bootstrap/bootstrap.php');MarketplaceModule::boot();
$_SESSION=['user_id'=>'UI-READ-TEST','role'=>'OWNER','company_key'=>'LOCAL-COMPANY','workspace'=>'marketplace'];
$s=new MarketplaceService();$stores=array_values(array_filter($s->getStores(false),fn($v)=>!empty($v['oauth_connection_id'])&&!empty($v['oauth_authorized_at'])));
if(!$stores)throw new RuntimeException('No integrated store');
$testTab=$argv[1]??'transaksi';$tab=$testTab;$_GET=['store'=>$stores[0]['id'],'tab'=>$tab];$_SERVER['REQUEST_METHOD']='GET';$_SERVER['REQUEST_URI']='marketplace.php?'.http_build_query($_GET);
if($tab==='pengaturan')$_GET['peng_sub']='toko';if($testTab==='integrasi'){$_GET['tab']='pengaturan';$_GET['peng_sub']='integrasi';}if($testTab==='identitas'){$_GET['tab']='pengaturan';$_GET['peng_sub']='identitas';}
set_error_handler(function($severity,$message,$file,$line){if(error_reporting()&$severity)throw new ErrorException($message,0,$severity,$file,$line);});
$checkStart=microtime(true);ob_start();
register_shutdown_function(function()use($testTab,$checkStart){$html=ob_get_contents();if(ob_get_level())ob_end_clean();fwrite(STDERR,json_encode(['tab'=>$testTab,'seconds'=>round(microtime(true)-$checkStart,3),'peak_mb'=>round(memory_get_peak_usage(true)/1048576,1),'bytes'=>strlen($html?:'')])."\n");});
if($testTab==='orders-api'||$testTab==='orders-csv'){if($testTab==='orders-csv')$_GET['format']='csv';require (dirname(__DIR__, 2) . '/app/Modules/Marketplace/Controllers/Api/marketplace-orders.php');}elseif($testTab==='dashboard-api'||$testTab==='dashboard-csv'){if($testTab==='dashboard-csv')$_GET['format']='csv';require (dirname(__DIR__, 2) . '/app/Modules/Marketplace/Controllers/Api/marketplace-dashboard.php');}else require (dirname(__DIR__, 2) . '/app/Modules/Marketplace/Controllers/marketplace.php');
