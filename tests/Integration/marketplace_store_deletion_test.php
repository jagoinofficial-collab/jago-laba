<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php'; MarketplaceModule::boot();
function checkDelete(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);}
$original=Database::config();$source=$original['db']['name'];if(!preg_match('/^\w+$/',$source))exit(2);
$name='jl_delete_test_'.bin2hex(random_bytes(6));$server=Database::serverPdo();$server->exec("CREATE DATABASE `$name`");$cfg=$original;$cfg['db']['name']=$name;Database::setConfig($cfg);Database::reset();
$dir=dirname(__DIR__,2).'/storage/temporary/deletion-test-'.bin2hex(random_bytes(6));mkdir($dir,0700,true);
try {
$p=Database::pdo();foreach($server->query("SHOW TABLES FROM `$source`")->fetchAll(PDO::FETCH_COLUMN) as $t){if(!preg_match('/^\w+$/',$t))continue;$p->exec("CREATE TABLE `$t` LIKE `$source`.`$t`");}
MarketplaceStoreDeletion::install($p);
$_SESSION=['user_id'=>'TEST','company_key'=>'DELETE-A','role'=>'OWNER','workspace'=>'marketplace'];
$p->exec("INSERT INTO marketplace_stores(id,company_key,platform,store_name,store_identifier,archived_at) VALUES('DELETE-A','DELETE-A','Shopee','Delete Fixture','100',NOW()),('KEEP-A','DELETE-A','Shopee','Keep Fixture','101',NULL),('KEEP-B','DELETE-B','TikTok Shop','Other Fixture','102',NOW())");
$p->exec("INSERT INTO marketplace_sync_records(store_id,resource_type,external_id,payload_json,fetched_at) VALUES('DELETE-A','orders','1','{}',NOW()),('KEEP-A','orders','2','{}',NOW()),('KEEP-B','orders','3','{}',NOW())");
$s=new MarketplaceStoreDeletion($p,$dir);$_SESSION['role']='STAFF';$denied=false;try{$s->preview('DELETE-A');}catch(DomainException){$denied=true;}checkDelete($denied,'Role bypass');$_SESSION['role']='OWNER';
foreach(['KEEP-B'] as $id){$denied=false;try{$s->preview($id);}catch(DomainException){$denied=true;}checkDelete($denied,'Tenant bypass');}
checkDelete($s->preview('KEEP-A')['counts']['marketplace_sync_records']===1,'Active store preview failed');
checkDelete($s->preview('DELETE-A')['counts']['marketplace_sync_records']===1,'Wrong counts');
$denied=false;try{$s->request('DELETE-A','Wrong');}catch(DomainException){$denied=true;}checkDelete($denied,'Name bypass');
$file='temporary/marketplace/delete-test-'.bin2hex(random_bytes(6)).'.txt';$root=dirname(__DIR__,2).'/storage/';if(!is_dir(dirname($root.$file)))mkdir(dirname($root.$file),0700,true);file_put_contents($root.$file,'fixture');$p->prepare('INSERT INTO marketplace_store_files VALUES (?,?)')->execute(['DELETE-A',$file]);
$s->request('DELETE-A','Delete Fixture');checkDelete(MarketplaceStoreDeletion::blocked($p,'DELETE-A'),'Missing barrier');
$denied=false;try{$p->exec("INSERT INTO marketplace_sync_records(store_id,resource_type,external_id,payload_json,fetched_at) VALUES('DELETE-A','orders','stale','{}',NOW())");}catch(PDOException){$denied=true;}checkDelete($denied,'Stale worker write accepted');
$denied=false;try{MarketplaceStoreDeletion::guardAuthorization($p,['company_key'=>'DELETE-A','authorization_started'=>'2020-01-01'],'Shopee','100');}catch(DomainException){$denied=true;}checkDelete($denied,'Stale OAuth accepted');
for($i=0;$i<100&&!$s->step('DELETE-A');$i++){}checkDelete($s->status('DELETE-A')['status']==='completed','Not completed');checkDelete(!file_exists($root.$file),'File retained');checkDelete($s->step('DELETE-A'),'Retry not idempotent');
checkDelete((int)$p->query('SELECT COUNT(*) FROM marketplace_stores')->fetchColumn()===2,'Other stores damaged');checkDelete((int)$p->query('SELECT COUNT(*) FROM marketplace_sync_records')->fetchColumn()===2,'Other records damaged');
checkDelete(MarketplaceStoreDeletion::guardAuthorization($p,['company_key'=>'DELETE-A','authorization_started'=>'2099-01-01'],'Shopee','100'),'Fresh OAuth denied');
echo "PASS: role, tenant, connected/archived preview and name confirmation, real counts, stale writes/OAuth blocked, file cleanup, idempotency and other-store preservation.\n";
}finally{Database::reset();Database::setConfig($original);$server->exec("DROP DATABASE `$name`");foreach(glob($dir.'/*.json') as $f)unlink($f);rmdir($dir);}
