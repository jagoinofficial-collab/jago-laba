<?php
declare(strict_types=1);
require_once (dirname(__DIR__, 2) . '/app/Modules/Marketplace/Services/MarketplaceService.php');
function checkTransaction(bool $value, string $message): void { if (!$value) throw new RuntimeException($message); }
$details = [
    'tracking_number' => 'SPXID06868686954A', 'payment_method' => 'COD', 'is_cod' => true,
    'is_estimated' => true, 'source_status' => 'SHIPPED', 'source_updated_at' => '2026-10-24 19:58:00',
    'released_at' => null, 'gross_amount' => 125494, 'total_fees' => 31035,
    'net_income' => 94459, 'total_cogs' => 59000, 'adjustment_amount' => 0,
    'components' => ['admin_fee' => 12596, 'service_fee' => 14572, 'actual_shipping' => 8000,
        'withholding_tax' => 617, 'seller_voucher' => 2000, 'shipping_subsidy' => 8000, 'processing_fee' => 1250],
    'items' => [['name' => 'JAGOIN Tas Ransel Pria Waterproof Tas Backpack Sekolah SMP SMA Kuliah Kerja Anti Air',
        'variant' => 'Black Metro List', 'sku' => 'JN METRO LIST', 'quantity' => 1, 'returned_quantity' => 0,
        'unit_price' => 125494, 'total_price' => 125494, 'unit_cogs' => 59000, 'total_cogs' => 59000]],
];
$order = ['id' => '261023BYMUQD32', 'order_date' => '2026-10-23', 'transaction_detail_json' => json_encode($details)];
$p = MarketplaceOrderPresentation::build($order);
checkTransaction($p['total_fees'] === 31035.0 && $p['net_income'] === 94459.0 && $p['net_profit'] === 35459.0, 'reference amounts differ');
checkTransaction($p['fee_percent'] === 24.7 && $p['profit_percent'] === 28.3, 'percentage incorrect');
checkTransaction($p['component_difference'] === 0.0 && $p['reconciliation_difference'] === 0.0, 'subsidy counted incorrectly');
checkTransaction($p['status'] === 'Sedang Dikirim' && $p['is_cod'] === true && $p['is_estimated'] === true, 'shipping/payment flags lost');
checkTransaction($p['items'][0]['actual_quantity'] === 1.0 && $p['released_at'] === null, 'quantity/release incorrect');
$details['source_status'] = 'READY_TO_SHIP';
$order['transaction_detail_json'] = json_encode($details);
checkTransaction(MarketplaceOrderPresentation::build($order)['status'] === 'Perlu Dikirim', 'ready-to-ship mapping incorrect');
$legacy = MarketplaceOrderPresentation::build(['id' => 'legacy', 'other_deductions' => 1250, 'order_status' => 'Pending']);
checkTransaction($legacy['is_cod'] === null && $legacy['net_profit'] === null && $legacy['released_at'] === null, 'missing fields fabricated');

final class TransactionTestPdo extends PDO {
    public function prepare(string $query, array $options = []): PDOStatement|false { return parent::prepare(str_replace(' FOR UPDATE', '', $query), $options); }
}
$pdo = new TransactionTestPdo('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE marketplace_stores (id TEXT, company_key TEXT, archived_at TEXT)');
$pdo->exec("INSERT INTO marketplace_stores VALUES ('S','A',NULL),('X','B',NULL)");
$pdo->exec('CREATE TABLE marketplace_orders (id TEXT, store_id TEXT, source_kind TEXT, transaction_detail_json TEXT)');
$pdo->exec("INSERT INTO marketplace_orders VALUES ('261023BYMUQD32','S','api',NULL),('manual','S','manual',NULL)");
$_SESSION = ['company_key' => 'A'];
$service = new MarketplaceService($pdo);
$service->saveVerifiedOrderDetails('261023BYMUQD32', 'S', $details);
$details['source_updated_at'] = '2026-10-23 19:58:00';
$details['tracking_number'] = 'OLDER';
$service->saveVerifiedOrderDetails('261023BYMUQD32', 'S', $details);
$saved = json_decode($pdo->query("SELECT transaction_detail_json FROM marketplace_orders WHERE id='261023BYMUQD32'")->fetchColumn(), true);
checkTransaction($saved['tracking_number'] === 'SPXID06868686954A', 'stale sync overwrote newer data');
try { $service->saveVerifiedOrderDetails('261023BYMUQD32', 'X', $details); throw new RuntimeException('foreign store permitted'); } catch (DomainException $expected) {}
try { $service->saveVerifiedOrderDetails('manual', 'S', $details); throw new RuntimeException('manual order overwritten'); } catch (DomainException $expected) {}
$details['items'][0]['returned_quantity'] = 2;
try { $service->saveVerifiedOrderDetails('261023BYMUQD32', 'S', $details); throw new RuntimeException('invalid return permitted'); } catch (InvalidArgumentException $expected) {}
echo "PASS: reference totals, subsidy, percentages, items, COD, shipping statuses, missing data, store isolation, stale sync and manual protection\n";
