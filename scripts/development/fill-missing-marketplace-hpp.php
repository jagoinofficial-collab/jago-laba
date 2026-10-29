<?php
if(PHP_SAPI!=='cli')exit;
require_once (dirname(__DIR__, 2) . '/app/Bootstrap/bootstrap.php');MarketplaceModule::boot();
$args=getopt('',['store:']);$store=(string)($args['store']??'');$pdo=Database::pdo();$q=$pdo->prepare('SELECT company_key FROM marketplace_stores WHERE id=? AND archived_at IS NULL');$q->execute([$store]);$company=$q->fetchColumn();if(!$company)exit(2);
$_SESSION['company_key']=$company;$_SESSION['user_id']='OWNER-HPP-RECONCILIATION';$_SESSION['role']='OWNER';session_write_close();
echo json_encode(['updated_orders'=>(new MarketplaceCatalogService())->fillMissingOrderHpp($store)]).PHP_EOL;
$q=$pdo->prepare('SELECT * FROM marketplace_orders WHERE store_id=? AND id=?');$q->execute([$store,'586782981146117834']);$row=$q->fetch(PDO::FETCH_ASSOC);if($row){$p=MarketplaceOrderPresentation::build($row);echo json_encode(['order_id'=>$row['id'],'hpp'=>$p['total_cogs'],'net'=>$p['net_income'],'profit'=>$p['net_profit'],'estimated'=>$p['is_estimated']]).PHP_EOL;}
