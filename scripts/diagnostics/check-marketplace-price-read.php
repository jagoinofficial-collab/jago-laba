<?php
require_once (dirname(__DIR__, 2) . '/app/Bootstrap/bootstrap.php');MarketplaceModule::boot();
$pdo=Database::pdo();$row=$pdo->query("SELECT c.store_id,s.company_key,v.external_product_id FROM marketplace_oauth_connections c JOIN marketplace_stores s ON s.id=c.store_id JOIN marketplace_catalog_variants v ON v.store_id=c.store_id WHERE c.status='verified' AND c.platform='TikTok Shop' AND c.external_store_name LIKE 'SANDBOX%' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if(!$row)exit("No connected Sandbox catalog.\n");
$_SESSION=['company_key'=>$row['company_key'],'user_id'=>'OWNER','role'=>'OWNER'];
if(in_array('--refresh',$argv,true))echo json_encode((new MarketplaceOAuthService())->refreshConnection($row['store_id']))."\n";
$stmt=$pdo->prepare('SELECT granted_scopes,token_expires_at,authorized_at FROM marketplace_oauth_connections WHERE store_id=?');$stmt->execute([$row['store_id']]);echo json_encode($stmt->fetch(PDO::FETCH_ASSOC))."\n";
try{$stmt=$pdo->prepare('SELECT access_token_encrypted,external_shop_cipher FROM marketplace_oauth_connections WHERE store_id=?');$stmt->execute([$row['store_id']]);$c=$stmt->fetch(PDO::FETCH_ASSOC);$client=new TikTokDataClient((new MarketplacePartnerSettings())->configFor('TikTok Shop'));$r=$client->call('POST','/promotion/202309/activities/search',['shop_cipher'=>$c['external_shop_cipher']],MarketplaceTokenVault::decrypt($c['access_token_encrypted']),['status'=>'ONGOING','page_size'=>20]);echo 'Promotion read accepted: '.count($r['activities']??[])."\n";}catch(MarketplaceIntegrationException $e){echo $e->getMessage()."\n";}
try{$data=(new MarketplacePriceService())->prepare($row['store_id'],$row['external_product_id']);echo json_encode(['rows'=>count($data['rows']),'product_status'=>$data['product_status'],'promotion_error'=>$data['promotion_error']],JSON_THROW_ON_ERROR)."\n";}catch(Throwable $e){echo get_class($e).': '.($e instanceof MarketplaceIntegrationException?$e->errorCode:$e->getMessage())."\n";exit(1);}
if(in_array('--test-base',$argv,true)){
 $variant=$data['rows'][0];
 $result=(new MarketplacePriceService())->save($row['store_id'],$row['external_product_id'],['mode'=>'base','operation_key'=>'diagnostic-'.bin2hex(random_bytes(12)),'changes'=>[['variant_id'=>$variant['external_variant_id'],'price'=>(string)$variant['base_price']]]]);
 echo 'Same-price write check: '.json_encode($result,JSON_THROW_ON_ERROR)."\n";
}
