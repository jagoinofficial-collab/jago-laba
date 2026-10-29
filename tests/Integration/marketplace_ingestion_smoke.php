<?php
declare(strict_types=1);
require_once (dirname(__DIR__, 2) . '/app/Bootstrap/bootstrap.php');
require_once (dirname(__DIR__, 2) . '/app/Modules/Marketplace/Services/MarketplaceIngestionService.php');
require_once (dirname(__DIR__, 2) . '/app/Modules/Marketplace/Services/MarketplaceInboxService.php');

$name = 'jl_marketplace_test_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3));
$server = Database::serverPdo();
$server->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$cfg = Database::config()['db'];
$pdo = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $cfg['host'], $cfg['port'], $name), $cfg['user'], $cfg['pass'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$sql = preg_replace('/^--.*$/m', '', file_get_contents((dirname(__DIR__, 2) . '/database/migrations/marketplace/migration_marketplace_integration_review.sql')));
foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) $pdo->exec($statement);
$ingest = new MarketplaceIngestionService($pdo);
$shopee = $ingest->registerVerifiedStore('COMPANY-A','Shopee','SHOP-100','Toko A');
$same = $ingest->registerVerifiedStore('COMPANY-A','Shopee','SHOP-100','Toko A Baru');
if ($shopee !== $same) throw new RuntimeException('Toko ganda.');
try {
    $ingest->registerVerifiedStore('COMPANY-B','Shopee','SHOP-100','Toko B');
    throw new RuntimeException('Klaim perusahaan lain lolos.');
} catch (DomainException $expected) {}
$tiktok = $ingest->registerVerifiedStore('COMPANY-A','TikTok Shop','SHOP-100','Toko TikTok');
if ($tiktok === $shopee) throw new RuntimeException('Platform berbeda digabung.');

$state = $ingest->beginAuthorization('COMPANY-A','USER-1','Shopee');
if ($ingest->consumeAuthorization($state,'COMPANY-B','USER-1','Shopee')) throw new RuntimeException('State lolos lintas perusahaan.');
if (!$ingest->consumeAuthorization($state,'COMPANY-A','USER-1','Shopee')) throw new RuntimeException('State gagal.');
if ($ingest->consumeAuthorization($state,'COMPANY-A','USER-1','Shopee')) throw new RuntimeException('State dapat dipakai ulang.');

$eventA = $ingest->enqueueVerifiedEvent($shopee,'EVENT-1','order_changed',['order_id'=>'ORDER-1']);
$eventB = $ingest->enqueueVerifiedEvent($shopee,'EVENT-1','order_changed',['order_id'=>'ORDER-1']);
if ($eventA !== $eventB) throw new RuntimeException('Webhook ganda.');
$inbox = new MarketplaceInboxService($pdo);
$lease1 = $inbox->claimNext($shopee);
if (!$lease1 || (int)$lease1['id'] !== $eventA || $inbox->claimNext($shopee) !== null) throw new RuntimeException('Klaim antrean ganda.');
$inbox->markFailed($eventA,$lease1['lease_token'],'TEMPORARY');
$pdo->exec("UPDATE marketplace_webhook_inbox SET next_attempt_at=DATE_SUB(NOW(),INTERVAL 1 SECOND) WHERE id=$eventA");
$lease2 = $inbox->claimNext($shopee);
if (!$lease2 || $lease2['lease_token'] === $lease1['lease_token']) throw new RuntimeException('Retry tanpa lease baru.');
try {
    $inbox->markProcessed($eventA,$lease1['lease_token']);
    throw new RuntimeException('Worker lama menyelesaikan lease baru.');
} catch (LogicException $expected) {}
$inbox->markProcessed($eventA,$lease2['lease_token']);
if ($inbox->claimNext($shopee) !== null) throw new RuntimeException('Event selesai diproses ulang.');

$order = ['external_order_id'=>'ORDER-1','status'=>'Pending','source_updated_at'=>'2026-10-20 10:00:00','gross_amount'=>'100.00'];
$item = ['external_item_id'=>'ITEM-1','external_product_id'=>'P-1','external_variant_id'=>'V-1','seller_sku'=>'SKU-1','name'=>'Produk A','quantity'=>'2','unit_price'=>'50.00','unit_cogs_snapshot'=>'20.00'];
$orderId = $ingest->upsertOrder($shopee,'api',$order,[$item]);
if ($orderId !== $ingest->upsertOrder($shopee,'webhook',$order,[$item])) throw new RuntimeException('Order ganda.');
$new = $order; $new['status']='Completed'; $new['source_updated_at']='2026-10-21 10:00:00';
$item['seller_sku']='SKU-CHANGED'; $item['unit_cogs_snapshot']='999.00';
$ingest->upsertOrder($shopee,'api',$new,[$item]);
$ingest->upsertOrder($shopee,'webhook',$order,[$item]);
$refund = $new; $refund['status']='Refunded'; $refund['source_updated_at']='2026-10-22 10:00:00';
$ingest->upsertOrder($shopee,'api',$refund,[]);
$otherOrder = $ingest->upsertOrder($tiktok,'api',$order,[$item]);
if ($otherOrder === $orderId) throw new RuntimeException('Order lintas toko tergabung.');
$beforeInvalid = (int)$pdo->query('SELECT COUNT(*) FROM marketplace_orders_v3')->fetchColumn();
try {
    $bad = $order; $bad['external_order_id']='INVALID-ORDER';
    $ingest->upsertOrder($shopee,'api',$bad,[['name'=>'Tanpa ID','quantity'=>'1']]);
    throw new RuntimeException('Item tanpa ID diterima.');
} catch (InvalidArgumentException $expected) {}
if ((int)$pdo->query('SELECT COUNT(*) FROM marketplace_orders_v3')->fetchColumn() !== $beforeInvalid) throw new RuntimeException('Transaksi invalid tidak rollback.');
$row = $pdo->query('SELECT order_status,gross_amount FROM marketplace_orders_v3 WHERE id=' . $orderId)->fetch(PDO::FETCH_ASSOC);
if ($row['order_status'] !== 'Refunded' || $row['gross_amount'] !== '100.00') throw new RuntimeException('Urutan pembaruan/refund order salah.');
$itemRow = $pdo->query('SELECT unit_cogs_snapshot,seller_sku_snapshot FROM marketplace_order_items_v3 WHERE order_id=' . $orderId)->fetch(PDO::FETCH_ASSOC);
if ($itemRow['unit_cogs_snapshot'] !== '20.00' || $itemRow['seller_sku_snapshot'] !== 'SKU-CHANGED') throw new RuntimeException('Snapshot HPP tertimpa.');
if ((int)$pdo->query('SELECT COUNT(*) FROM marketplace_order_items_v3 WHERE order_id=' . $orderId)->fetchColumn() !== 1) throw new RuntimeException('Item ganda.');
if ((int)$pdo->query('SELECT COUNT(*) FROM marketplace_financial_events_v3')->fetchColumn() !== 0 || (int)$pdo->query('SELECT COUNT(*) FROM marketplace_business_effects_v3')->fetchColumn() !== 0) throw new RuntimeException('Efek bisnis prematur.');
echo "TEST_DATABASE=$name\nPASS: toko unik, lintas perusahaan ditolak, OAuth state satu kali, inbox dedup/retry/lease, order idempoten, order lintas toko terpisah, refund terlambat, rollback input invalid, HPP historis tetap, tanpa efek stok/keuangan.\n";
