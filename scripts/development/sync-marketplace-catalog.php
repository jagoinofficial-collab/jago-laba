<?php
if(PHP_SAPI!=='cli')exit;
require_once (dirname(__DIR__, 2) . '/app/Bootstrap/bootstrap.php');MarketplaceModule::boot();
$args=getopt('',['store:']);$store=(string)($args['store']??'');
$q=Database::pdo()->prepare('SELECT company_key FROM marketplace_stores WHERE id=? AND archived_at IS NULL');$q->execute([$store]);$company=$q->fetchColumn();if(!$company)exit(2);
$_SESSION['company_key']=$company;$_SESSION['user_id']='CATALOG-WORKER';$_SESSION['role']='OWNER';session_write_close();
$state=(new MarketplaceBackgroundSync())->start($store,true);
echo json_encode(['worker'=>$state['worker'],'pending'=>$state['pending']],JSON_UNESCAPED_UNICODE).PHP_EOL;
