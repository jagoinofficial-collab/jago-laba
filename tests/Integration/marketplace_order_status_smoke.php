<?php
declare(strict_types=1);
require_once (dirname(__DIR__, 2) . '/app/Bootstrap/bootstrap.php');MarketplaceModule::boot();
function syncCheck(bool $condition,string $message):void{if(!$condition)throw new RuntimeException($message);}
$name='jl_sync_test_'.bin2hex(random_bytes(6));$server=Database::serverPdo();$original=Database::config();$source=$original['db']['name'];
if(!preg_match('/^[A-Za-z0-9_]+$/',$source))throw new RuntimeException('Unexpected test source schema');
$server->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");$config=$original;$config['db']['name']=$name;Database::setConfig($config);Database::reset();
$keyBefore=getenv('JL_MARKETPLACE_TOKEN_ENCRYPTION_KEY');putenv('JL_MARKETPLACE_TOKEN_ENCRYPTION_KEY='.base64_encode(random_bytes(32)));
try{
 $pdo=Database::pdo();foreach(['marketplace_stores','marketplace_oauth_connections','marketplace_sync_jobs','marketplace_partner_settings','marketplace_catalog_variants','marketplace_orders','marketplace_order_items','marketplace_hpp_history','marketplace_sync_records','marketplace_sync_workers'] as $table)$pdo->exec("CREATE TABLE `$table` LIKE `$source`.`$table`");
 $pdo->exec("INSERT INTO marketplace_stores(id,company_key,platform,store_name,store_identifier,authorization_status) VALUES ('S','A','TikTok Shop','SANDBOX_ID_TEST','shop','verified'),('X','B','TikTok Shop','SANDBOX_OTHER','other','verified'),('LIVE','A','TikTok Shop','Actual Shop','live','verified')");
 $stmt=$pdo->prepare("INSERT INTO marketplace_oauth_connections(company_key,store_id,platform,external_store_id,external_shop_cipher,external_store_name,access_token_encrypted,token_expires_at,authorized_at,verified_at,status) VALUES ('A','S','TikTok Shop','shop','cipher','SANDBOX_ID_TEST',?,DATE_ADD(NOW(),INTERVAL 1 HOUR),NOW(),NOW(),'verified')");$stmt->execute([MarketplaceTokenVault::encrypt('TEST-TOKEN')]);
 $pdo->prepare('INSERT INTO marketplace_partner_settings(platform,settings_encrypted) VALUES (?,?)')->execute(['TikTok Shop',MarketplaceTokenVault::encrypt(json_encode(['app_key'=>'TESTKEY','app_secret'=>'TESTSECRET','service_id'=>'TESTSERVICE','region'=>'row']))]);
 $pdo->exec("INSERT INTO marketplace_hpp_history(store_id,marketplace_sku,unit_hpp,effective_from,source,changed_by) VALUES ('S','SKU',20,CURDATE(),'MANUAL','test')");
 $_SESSION=['company_key'=>'A','user_id'=>'TEST','role'=>'OWNER'];
 $fixtures=[
 'WAIT'=>['source_status'=>'AWAITING_SHIPMENT'],
 'PICKUP'=>['source_status'=>'AWAITING_COLLECTION','tracking_number'=>'TRACK-123'],
 'TRANSIT'=>['source_status'=>'IN_TRANSIT'],
 'CANCEL'=>['source_status'=>'CANCELLED','is_cod'=>true],
 'REQUEST'=>['source_status'=>'AWAITING_SHIPMENT','is_buyer_request_cancel'=>true],
 'FAIL'=>['source_status'=>'IN_TRANSIT','is_delivery_failed'=>true],
 'SAMPLE'=>['source_status'=>'AWAITING_SHIPMENT','is_sample_order'=>true],
 'AFFILIATE'=>['source_status'=>'AWAITING_SHIPMENT','components'=>['affiliate_commission'=>10]],
 'RETURN'=>['source_status'=>'COMPLETED','has_return'=>true],
 'PENDING_FINANCE'=>['source_status'=>'AWAITING_SHIPMENT','net_income'=>null,'total_cogs'=>100,'total_fees'=>null],
 'SHOPEE ADJ 254420784036972300'=>['source_status'=>'COMPLETED','gross_amount'=>1000,'net_income'=>700,'total_cogs'=>400,'total_fees'=>300],
 ];
 $insert=$pdo->prepare("INSERT INTO marketplace_orders(id,store_id,order_date,order_status,source_kind,transaction_detail_json) VALUES (?,'S',NOW(),'Pending','api',?)");
 foreach($fixtures as $id=>$snapshot)$insert->execute([$id,json_encode($snapshot)]);
 $service=new MarketplaceService($pdo);
 foreach(['WAIT'=>'Perlu Dikirim','PICKUP'=>'Menunggu Pickup','TRANSIT'=>'Sedang Dikirim','CANCEL'=>'Batal','REQUEST'=>'Pengajuan Pembatalan'] as $id=>$label){
   $view=MarketplaceOrderPresentation::build(['id'=>$id,'transaction_detail_json'=>json_encode($fixtures[$id])]);syncCheck($view['status']===$label,'label mismatch '.$id);
   $result=$service->getTransactions('S','semua',null,$label);syncCheck(in_array($id,array_column($result['transactions'],'id'),true),'SQL/UI status mismatch '.$id);
 }
 foreach(['cod_batal'=>'CANCEL','gagal_kirim'=>'FAIL','sampel'=>'SAMPLE','affiliate'=>'AFFILIATE','pengembalian'=>'RETURN'] as $filter=>$id){$result=$service->getTransactions('S',$filter);syncCheck(array_column($result['transactions'],'id')===[$id],'incorrect condition for '.$filter);}
 syncCheck($service->getTransactions('S','semua','TRACK-123',null,1,50,'tracking')['total_count']===1,'tracking search failed');
 $pending=$service->getTransactions('S','semua','PENDING_FINANCE',null,1,50,'order');syncCheck($pending['transactions'][0]['calculated_laba']===null&&$pending['total_laba']===null,'pending finance fabricated zero profit');
 $adjustment=$service->getTransactions('S','semua','SHOPEE ADJ 254420784036972300',null,1,50,'order');syncCheck($adjustment['transactions'][0]['calculated_laba']===300.0,'order ID changed real financial profit');
 syncCheck($service->getTransactions('S','kerugian','SHOPEE ADJ 254420784036972300',null,1,50,'order')['total_count']===0,'profitable adjustment identified as loss by order ID');
 echo "PASS: API status labels, matching status filters, cancellation request, pickup distinction, COD, delivery failure, returns, sample, affiliate and tracking search.\n";
} finally {Database::setConfig($original);Database::reset();$server->exec("DROP DATABASE `$name`");if($keyBefore===false)putenv('JL_MARKETPLACE_TOKEN_ENCRYPTION_KEY');else putenv('JL_MARKETPLACE_TOKEN_ENCRYPTION_KEY='.$keyBefore);}
