<?php
declare(strict_types=1);
require_once (dirname(__DIR__, 2) . '/app/Bootstrap/bootstrap.php');MarketplaceModule::boot();
function syncCheck(bool $condition,string $message):void{if(!$condition)throw new RuntimeException($message);}
$name='jl_sync_test_'.bin2hex(random_bytes(6));$server=Database::serverPdo();$original=Database::config();$source=$original['db']['name'];
if(!preg_match('/^[A-Za-z0-9_]+$/',$source))throw new RuntimeException('Unexpected test source schema');
$server->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");$config=$original;$config['db']['name']=$name;Database::setConfig($config);Database::reset();
$keyBefore=getenv('JL_MARKETPLACE_TOKEN_ENCRYPTION_KEY');putenv('JL_MARKETPLACE_TOKEN_ENCRYPTION_KEY='.base64_encode(random_bytes(32)));
try{
 $pdo=Database::pdo();foreach(['marketplace_stores','marketplace_oauth_connections','marketplace_sync_jobs','marketplace_partner_settings','marketplace_catalog_variants','marketplace_orders','marketplace_order_items','marketplace_hpp_history','marketplace_sync_records','marketplace_sync_workers','marketplace_price_actions'] as $table)$pdo->exec("CREATE TABLE `$table` LIKE `$source`.`$table`");
 $pdo->exec("INSERT INTO marketplace_stores(id,company_key,platform,store_name,store_identifier,authorization_status) VALUES ('S','A','TikTok Shop','SANDBOX_ID_TEST','shop','verified'),('X','B','TikTok Shop','SANDBOX_OTHER','other','verified'),('LIVE','A','TikTok Shop','Actual Shop','live','verified')");
 $stmt=$pdo->prepare("INSERT INTO marketplace_oauth_connections(company_key,store_id,platform,external_store_id,external_shop_cipher,external_store_name,access_token_encrypted,token_expires_at,authorized_at,verified_at,status) VALUES ('A','S','TikTok Shop','shop','cipher','SANDBOX_ID_TEST',?,DATE_ADD(NOW(),INTERVAL 1 HOUR),NOW(),NOW(),'verified')");$stmt->execute([MarketplaceTokenVault::encrypt('TEST-TOKEN')]);
 $pdo->prepare('INSERT INTO marketplace_partner_settings(platform,settings_encrypted) VALUES (?,?)')->execute(['TikTok Shop',MarketplaceTokenVault::encrypt(json_encode(['app_key'=>'TESTKEY','app_secret'=>'TESTSECRET','service_id'=>'TESTSERVICE','region'=>'row']))]);
 $pdo->exec("INSERT INTO marketplace_hpp_history(store_id,marketplace_sku,unit_hpp,effective_from,source,changed_by) VALUES ('S','SKU',20,CURDATE(),'MANUAL','test')");
 $_SESSION=['company_key'=>'A','user_id'=>'TEST','role'=>'OWNER'];
 $pdo->exec("INSERT INTO marketplace_catalog_variants(store_id,external_product_id,external_variant_id,product_name,sku,variant_name,price,source_status) VALUES ('S','P','V1','Tas','SKU','Hitam',100,'ACTIVATE'),('S','P','V2','Tas','BLUE','Biru',100,'ACTIVATE')");
 $fake=new class {
   public array $prices=['V1'=>'100.00','V2'=>'100.00'];public array $activities=[];public int $writes=0;public bool $deny=false;
   public function call($method,$path,$query,$token,$body=null):array {
     if($method==='GET'&&$path==='/product/202309/products/P')return ['id'=>'P','title'=>'Tas','status'=>'ACTIVATE','skus'=>array_map(fn($id)=>['id'=>$id,'price'=>['currency'=>'IDR','tax_exclusive_price'=>$this->prices[$id]]],array_keys($this->prices))];
     if(str_ends_with($path,'/activities/search'))return ['activities'=>array_values(array_filter($this->activities,fn($p)=>$p['status']===$body['status'])),'total_count'=>0];
     if($method==='GET'&&str_contains($path,'/activities/'))return $this->activities[basename($path)];
     $this->writes++;if($this->deny)throw new MarketplaceIntegrationException('Denied','TIKTOK_API_105005');
     if(str_ends_with($path,'/prices/update')){foreach($body['skus'] as $s){syncCheck($s['price']['currency']==='IDR','wrong currency');$this->prices[$s['id']]=$s['price']['amount'];}return [];}
     if($path==='/promotion/202309/activities'){$id='A'.count($this->activities);$this->activities[$id]=$body+['id'=>$id,'status'=>'NOT_START','products'=>[]];return ['activity_id'=>$id];}
     if($method==='PUT'){$id=basename(dirname($path));$p=$body['products'][0];syncCheck(isset($p['quantity_limit'],$p['quantity_per_user'])&&$p['quantity_limit']===-1&&$p['quantity_per_user']===-1,'product-level promotion limits missing');$p['skus']=array_map(fn($s)=>$s+['activity_price'=>['amount'=>$s['activity_price_amount']]],$p['skus']);$this->activities[$id]['products']=[$p];return [];}
     if($method==='DELETE'){$id=basename(dirname($path));$this->activities[$id]['products'][0]['skus']=array_values(array_filter($this->activities[$id]['products'][0]['skus'],fn($s)=>!in_array($s['id'],$body['sku_ids'])));return [];}
     throw new RuntimeException('Unexpected write');
   }
 };
 $service=new MarketplacePriceService($pdo,fn($config)=>$fake);
 $display=['external_variant_id'=>'V1','promotions'=>[['status'=>'ONGOING','product_level'=>'VARIATION','product'=>['skus'=>[['id'=>'V1','activity_price'=>['amount'=>'148500']],['id'=>'V2','activity_price'=>['amount'=>'50000']]]]]]];
 syncCheck(MarketplacePriceService::promotionCaption($display)==='sedang promo Rp 148.500','caption did not use matching SKU shop price');
 $display['promotions'][0]['status']='NOT_START';syncCheck(MarketplacePriceService::promotionCaption($display)==='Promo terjadwal Rp 148.500','scheduled promo shown as active');
 syncCheck(MarketplacePriceService::promotionCaption($display,true)==='Data promo belum tersedia','failed lookup shown as a real promo');
 $display['promotions'][0]['status']='ONGOING';unset($display['promotions'][0]['product']['skus'][0]['activity_price']);syncCheck(MarketplacePriceService::promotionCaption($display)==='Sedang promo · harga belum tersedia','missing SKU price replaced with unrelated SKU price');
 $prepared=$service->prepare('S','P');syncCheck(count($prepared['rows'])===2&&$prepared['rows'][0]['base_price']==='100.00','prepare failed');
 $pdo->exec("INSERT INTO marketplace_oauth_connections(company_key,store_id,platform,external_store_id,external_shop_cipher,external_store_name,access_token_encrypted,token_expires_at,authorized_at,verified_at,status) SELECT company_key,'LIVE',platform,'live-id',external_shop_cipher,'Actual Shop',access_token_encrypted,token_expires_at,NOW(),NOW(),'verified' FROM marketplace_oauth_connections WHERE store_id='S'");
 $pdo->exec("INSERT INTO marketplace_catalog_variants(store_id,external_product_id,external_variant_id,product_name,sku,variant_name,price,source_status) VALUES ('LIVE','P','V1','Live Bag','SKU','Hitam',90,'ACTIVATE')");
 $writes=$fake->writes;$live=$service->prepare('LIVE','P');syncCheck($live['store_name']==='Actual Shop'&&$live['rows'][0]['base_price']==='100.00','live store did not load remote price');syncCheck($fake->writes===$writes,'prepare changed marketplace prices');
 $pdo->exec("UPDATE marketplace_oauth_connections SET status='revoked' WHERE store_id='LIVE'");$blocked=false;try{$service->prepare('LIVE','P');}catch(DomainException $e){$blocked=true;}syncCheck($blocked,'revoked store read allowed');
 $base=['mode'=>'base','operation_key'=>'base-test-123456789','changes'=>[['variant_id'=>'V1','price'=>'120']]];
 $result=$service->save('S','P',$base);syncCheck($result['status']==='verified'&&$fake->prices['V2']==='100.00','base modified unselected SKU');
 syncCheck((float)$pdo->query("SELECT price FROM marketplace_catalog_variants WHERE external_variant_id='V1'")->fetchColumn()===120.0,'catalog not updated after verification');
 $count=$fake->writes;$service->save('S','P',$base);syncCheck($fake->writes===$count,'duplicate price write');
 $discount=['mode'=>'discount','operation_key'=>'promo-test-123456789','changes'=>[['variant_id'=>'V1','price'=>'80']],'title'=>'Promo test','begin_time'=>time()+600,'end_time'=>time()+3600];
 $result=$service->save('S','P',$discount);syncCheck($result['status']==='verified'&&$fake->prices['V1']==='120.00','promo changed base price');
 $count=$fake->writes;$service->save('S','P',$discount);syncCheck($fake->writes===$count,'duplicate promo created');
 $replace=$discount;$replace['operation_key']='replace-test-123456789';$replace['changes'][0]['price']='70';
 $reject=false;try{$service->save('S','P',$replace);}catch(DomainException $e){$reject=true;}syncCheck($reject&&$fake->writes===$count,'promo replaced without consent');
 $replace['replace_existing']=true;$result=$service->save('S','P',$replace);syncCheck($result['status']==='verified'&&count($fake->activities['A0']['products'][0]['skus'])===0,'selected promo replacement failed');
 $reject=false;try{$service->save('S','P',array_replace($base,['operation_key'=>'blocked-test-123456789']));}catch(DomainException $e){$reject=true;}syncCheck($reject,'base changed despite promo');
 $reject=false;try{$service->save('X','P',$base);}catch(DomainException $e){$reject=true;}syncCheck($reject,'cross-company write allowed');
 $bad=$base;$bad['operation_key']='bad-sku-test-123456789';$bad['changes'][0]['variant_id']='OTHER';$reject=false;try{$service->save('S','P',$bad);}catch(DomainException $e){$reject=true;}syncCheck($reject,'wrong variant allowed');
 $fake->activities=[];$fake->deny=true;$failed=$service->save('S','P',array_replace($base,['operation_key'=>'denied-test-123456789']));syncCheck($failed['status']==='failed','uncertain API response called success');
 echo "Marketplace price smoke passed: scope, selected variants, base/promo separation, verification and duplicate protection.\n";
} finally {Database::setConfig($original);Database::reset();$server->exec("DROP DATABASE `$name`");if($keyBefore===false)putenv('JL_MARKETPLACE_TOKEN_ENCRYPTION_KEY');else putenv('JL_MARKETPLACE_TOKEN_ENCRYPTION_KEY='.$keyBefore);}
