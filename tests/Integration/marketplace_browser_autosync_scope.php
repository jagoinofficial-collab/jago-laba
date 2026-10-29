<?php
declare(strict_types=1);
require_once (dirname(__DIR__, 2) . '/app/Bootstrap/bootstrap.php');MarketplaceModule::boot();
require_once (dirname(__DIR__, 2) . '/app/Modules/Marketplace/Jobs/MarketplaceAutoSync.php');
$original=Database::config();$sessionBefore=$_SESSION??[];$server=Database::serverPdo();
$source=$original['db']['name'];if(!preg_match('/^[A-Za-z0-9_]+$/',$source))throw new RuntimeException('Unsafe source schema');
$name='jl_browser_autosync_test_'.bin2hex(random_bytes(6));
$server->exec("CREATE DATABASE `$name`");
try{
 $config=$original;$config['db']['name']=$name;Database::setConfig($config);Database::reset();$pdo=Database::pdo();
 foreach(['marketplace_stores','marketplace_oauth_connections','marketplace_auto_sync','marketplace_auto_sync_runtime','marketplace_sync_jobs'] as $table)$pdo->exec("CREATE TABLE `$table` LIKE `$source`.`$table`");
 $pdo->exec("INSERT INTO marketplace_stores(id,company_key,platform,store_name,store_identifier,authorization_status) VALUES ('A-STORE','A','TikTok Shop','Test A','a','verified'),('B-STORE','B','TikTok Shop','Test B','b','verified')");
 $pdo->exec("INSERT INTO marketplace_oauth_connections(company_key,store_id,platform,external_store_id,external_shop_cipher,external_store_name,access_token_encrypted,status) VALUES ('A','A-STORE','TikTok Shop','a','a','Test A','not-a-real-token','verified'),('B','B-STORE','TikTok Shop','b','b','Test B','not-a-real-token','verified')");
 $pdo->exec("INSERT INTO marketplace_auto_sync(store_id,company_key,mode,enabled,next_run_at) VALUES ('A-STORE','A','new',1,'2020-01-01 00:00:00'),('B-STORE','B','new',1,'2020-01-01 00:00:00')");
 $_SESSION=['user_id'=>'BROWSER-TEST','role'=>'OWNER','company_key'=>'A'];$identity=$_SESSION;
 $service=new MarketplaceAutoSync($pdo,static function(){throw new RuntimeException('Network disabled in test');});
 $service->browserPulse(1);
 if($_SESSION!==$identity)throw new RuntimeException('Browser identity changed');
 $foreign=$pdo->query("SELECT next_run_at,last_error_code FROM marketplace_auto_sync WHERE company_key='B'")->fetch(PDO::FETCH_ASSOC);
 if($foreign['next_run_at']!=='2020-01-01 00:00:00'||$foreign['last_error_code']!==null)throw new RuntimeException('Foreign company was processed');
 $own=$pdo->query("SELECT next_run_at FROM marketplace_auto_sync WHERE company_key='A'")->fetchColumn();
 if($own==='2020-01-01 00:00:00')throw new RuntimeException('Authenticated company was not processed');
 $_SESSION=[];$rejected=false;try{$service->browserPulse(1);}catch(DomainException){$rejected=true;}
 if(!$rejected)throw new RuntimeException('Anonymous pulse accepted');
 echo "PASS: browser company isolation, session identity restoration, anonymous rejection; no external API calls\n";
}finally{Database::reset();Database::setConfig($original);$_SESSION=$sessionBefore;$server->exec("DROP DATABASE `$name`");}
