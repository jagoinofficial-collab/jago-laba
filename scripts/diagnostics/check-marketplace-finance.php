<?php
if(PHP_SAPI!=='cli')exit;
require_once (dirname(__DIR__, 2) . '/app/Bootstrap/bootstrap.php');MarketplaceModule::boot();
require_once (dirname(__DIR__, 2) . '/app/Modules/Marketplace/Integrations/TikTokShop/TikTokDataClient.php');
$args=getopt('',['store:','order:']);$pdo=Database::pdo();$q=$pdo->prepare("SELECT * FROM marketplace_oauth_connections WHERE store_id=? AND platform='TikTok Shop' AND status='verified'");$q->execute([$args['store']??'']);$c=$q->fetch(PDO::FETCH_ASSOC);if(!$c)exit(2);
$_SESSION['company_key']=$c['company_key'];$_SESSION['user_id']='FINANCE-CHECK';$_SESSION['role']='OWNER';session_write_close();
if(strtotime($c['token_expires_at'])<time()+60){(new MarketplaceOAuthService())->refreshConnection($c['store_id']);$q->execute([$c['store_id']]);$c=$q->fetch(PDO::FETCH_ASSOC);}
$client=new TikTokDataClient((new MarketplacePartnerSettings())->configFor('TikTok Shop'));$token=MarketplaceTokenVault::decrypt($c['access_token_encrypted']);$query=['shop_cipher'=>$c['external_shop_cipher']];
try{$f=$client->call('GET','/finance/202501/orders/'.rawurlencode($args['order']).'/statement_transactions',$query,$token);echo json_encode(['source'=>'statement','keys'=>array_keys($f),'settlement'=>$f['settlement_amount']??null,'sku_count'=>count($f['sku_transactions']??[])]).PHP_EOL;}catch(MarketplaceIntegrationException $e){echo json_encode(['source'=>'statement','error'=>$e->errorCode]).PHP_EOL;}
$from=(new DateTimeImmutable('first day of this month',new DateTimeZone('Asia/Jakarta')))->setTime(0,0)->getTimestamp();$page='';
for($i=0;$i<20;$i++){
 try{$data=$client->call('GET','/finance/202507/orders/unsettled',$query+['page_size'=>100,'sort_field'=>'order_create_time','sort_order'=>'DESC','search_time_ge'=>$from,'search_time_lt'=>time()+1]+($page?['page_token'=>$page]:[]),$token);}catch(MarketplaceIntegrationException $e){echo json_encode(['source'=>'unsettled','error'=>$e->errorCode]).PHP_EOL;break;}
 echo json_encode(['source'=>'unsettled','page'=>$i+1,'count'=>count($data['transactions']??[])]).PHP_EOL;
 foreach($data['transactions']??[] as $f)if((string)($f['order_id']??'')===$args['order']){echo json_encode(['match'=>true,'keys'=>array_keys($f),'revenue'=>$f['est_revenue_amount']??null,'settlement'=>$f['est_settlement_amount']??null,'fee_tax'=>$f['est_fee_tax_amount']??null,'revenue_breakdown'=>$f['revenue_breakdown']??null,'fee_tax_breakdown'=>$f['fee_tax_breakdown']??null,'shipping_cost_breakdown'=>$f['shipping_cost_breakdown']??null],JSON_UNESCAPED_UNICODE).PHP_EOL;exit;}
 $next=(string)($data['next_page_token']??'');if(!$next||$next===$page)break;$page=$next;usleep(300000);
}
