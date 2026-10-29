<?php
if(PHP_SAPI!=='cli')exit;
require_once (dirname(__DIR__, 2) . '/app/Bootstrap/bootstrap.php');MarketplaceModule::boot();
$args=getopt('',['store:','order:']);$pdo=Database::pdo();$q=$pdo->prepare('SELECT company_key FROM marketplace_stores WHERE id=? AND archived_at IS NULL');$q->execute([$args['store']??'']);$company=$q->fetchColumn();if(!$company)exit(2);
$_SESSION['company_key']=$company;$_SESSION['user_id']='FINANCE-SYNC';$_SESSION['role']='OWNER';session_write_close();
$synced=(new MarketplaceSyncService($pdo,null,true))->refreshOrderFinance($args['store'],$args['order']);
$q=$pdo->prepare('SELECT * FROM marketplace_orders WHERE id=? AND store_id=?');$q->execute([$args['order'],$args['store']]);$p=MarketplaceOrderPresentation::build($q->fetch(PDO::FETCH_ASSOC));
echo json_encode(['synced'=>$synced,'gross'=>$p['gross_amount'],'fees'=>$p['total_fees'],'net'=>$p['net_income'],'hpp'=>$p['total_cogs'],'profit'=>$p['net_profit'],'estimated'=>$p['is_estimated'],'finance_schema'=>$p['finance_schema'],'components'=>array_values(array_filter($p['components'],static fn($c)=>$c['amount']!=0))],JSON_UNESCAPED_UNICODE).PHP_EOL;
