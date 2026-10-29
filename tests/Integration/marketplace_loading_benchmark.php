<?php
require (dirname(__DIR__, 2) . '/app/Bootstrap/bootstrap.php');MarketplaceModule::boot();
$_SESSION=['user_id'=>'READ-BENCH','role'=>'OWNER','company_key'=>'LOCAL-COMPANY','workspace'=>'marketplace'];
$s=new MarketplaceService();$list=array_values(array_filter($s->getStores(false),fn($v)=>!empty($v['oauth_connection_id'])&&!empty($v['oauth_authorized_at'])));if(!$list)exit('No connected store');$id=$list[0]['id'];$pdo=Database::pdo();
function questions($pdo){return (int)$pdo->query("SHOW SESSION STATUS LIKE 'Questions'")->fetch(PDO::FETCH_NUM)[1];}
$startQ=questions($pdo);$benchStart=microtime(true);
if(($argv[1]??'')==='before'){
 $s->getStores();$s->getArchivedStores();$s->getStoreDashboard($id);$s->getTransactions($id,'semua',null,null,1,50,'all');$s->getExpenses($id);$s->getInventory($id);$s->getStockAllocations();$s->getMarketingDashboard($id);$s->getReturnClaims($id);$s->getJournalEntries($id);$s->getSavedPriceSimulations();$s->getAllStoresWithDetails();$s->getAppSettings();
}else{$s->getStores(false);$s->getTransactions($id,'semua',null,null,1,50,'all');$s->getReturnClaims($id);}
$ms=round((microtime(true)-$benchStart)*1000,1);$count=questions($pdo)-$startQ;
echo json_encode(['mode'=>$argv[1]??'after','data_load_ms'=>$ms,'connection_statements'=>$count,'peak_mb'=>round(memory_get_peak_usage(true)/1048576,1)])."\n";
