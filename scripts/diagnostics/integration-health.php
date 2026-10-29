<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require dirname(__DIR__,2).'/app/Bootstrap/bootstrap.php';MarketplaceModule::boot();
require_once dirname(__DIR__,2).'/app/Modules/Marketplace/Services/MarketplaceIntegrationHealth.php';
$pdo=Database::pdo();
$stores=$pdo->query("SELECT s.id,s.company_key FROM marketplace_stores s JOIN marketplace_oauth_connections c ON c.store_id=s.id AND c.company_key=s.company_key WHERE s.archived_at IS NULL AND c.status='verified'")->fetchAll();
foreach($stores as $store){
    $_SESSION=['company_key'=>$store['company_key'],'user_id'=>'HEALTH-CLI','role'=>'OWNER','workspace'=>'marketplace'];
    $report=(new MarketplaceIntegrationHealth($pdo))->forStore($store['id']);
    // Deliberately omit shop names, marketplace IDs, tokens, payloads and customer data.
    echo json_encode(['platform'=>$report['platform'],'connection'=>$report['connection_label'],'environment'=>$report['environment_label'],'counts'=>$report['counts'],'resources'=>array_map(static fn($r)=>['resource'=>$r['key'],'state'=>$r['state'],'last_verified_at'=>$r['last_verified_at'],'error_code'=>$r['error_code']],$report['resources'])],JSON_UNESCAPED_SLASHES).PHP_EOL;
}
