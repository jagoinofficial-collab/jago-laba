<?php
if(PHP_SAPI!=='cli')exit;
require_once (dirname(__DIR__, 2) . '/app/Bootstrap/bootstrap.php');MarketplaceModule::boot();
$pdo=Database::pdo();
foreach($pdo->query("SELECT h.store_id,h.marketplace_sku,h.unit_hpp,h.effective_from,h.source FROM marketplace_hpp_history h JOIN marketplace_stores s ON s.id=h.store_id WHERE s.archived_at IS NULL ORDER BY h.id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC) as $row)echo json_encode($row).PHP_EOL;
foreach($pdo->query("SELECT i.sku,MIN(DATE(o.order_date)) first_order,MAX(DATE(o.order_date)) last_order,COUNT(*) count FROM marketplace_order_items i JOIN marketplace_orders o ON o.id=i.order_id JOIN marketplace_stores s ON s.id=o.store_id WHERE s.archived_at IS NULL AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(o.transaction_detail_json,'$.total_cogs')),'null')='null' GROUP BY i.sku LIMIT 15")->fetchAll(PDO::FETCH_ASSOC) as $row)echo json_encode($row).PHP_EOL;
