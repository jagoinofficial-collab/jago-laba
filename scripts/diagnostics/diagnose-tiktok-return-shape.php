<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require_once (dirname(__DIR__, 2) . '/app/Bootstrap/bootstrap.php');MarketplaceModule::boot();
$args=getopt('',['store:','company:']);
if(empty($args['store'])||empty($args['company']))exit(2);
$_SESSION['company_key']=$args['company'];
try{
    (new MarketplaceSyncService())->status($args['store']);
    $stmt=Database::pdo()->prepare("SELECT access_token_encrypted,external_shop_cipher FROM marketplace_oauth_connections WHERE store_id=? AND company_key=? AND status='verified'");$stmt->execute([$args['store'],$args['company']]);$c=$stmt->fetch(PDO::FETCH_ASSOC);
    $client=new TikTokDataClient((new MarketplacePartnerSettings())->configFor('TikTok Shop'));
    $data=$client->call('POST','/return_refund/202602/returns/search',['shop_cipher'=>$c['external_shop_cipher'],'page_size'=>20,'sort_order'=>'ASC','sort_field'=>'create_time'],MarketplaceTokenVault::decrypt($c['access_token_encrypted']),['return_types'=>['REFUND','RETURN_AND_REFUND','REPLACEMENT','EXCHANGE']]);
    foreach($data as $key=>$value)echo $key.': '.gettype($value).(is_array($value)?' count='.count($value).(isset($value[0])&&is_array($value[0])?' item_fields='.implode(',',array_keys($value[0])):''):'')."\n";
}catch(Throwable $e){fwrite(STDERR,$e instanceof MarketplaceIntegrationException?$e->errorCode:"DIAGNOSTIC_FAILED");exit(1);}
