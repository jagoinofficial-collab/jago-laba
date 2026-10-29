<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/Bootstrap/bootstrap.php';
MarketplaceModule::boot();

$pdo = Database::pdo();

function assertFinance(bool $cond, string $msg): void
{
    if (!$cond) {
        throw new RuntimeException("Assertion failed: " . $msg);
    }
}

$companyKey = 'CMP-TEST-SHOPEE';
$_SESSION['company_key'] = $companyKey;
$storeId = 'OAUTH-SHP-TEST-' . bin2hex(random_bytes(4));
$externalShopId = (string)random_int(100000000, 999999999);
$orderId = '246847161287478';

// Cleanup previous test leftovers
$pdo->prepare('DELETE FROM marketplace_orders WHERE id=?')->execute([$orderId]);
$pdo->prepare('DELETE FROM marketplace_oauth_connections WHERE id=?')->execute([$storeId]);
$pdo->prepare('DELETE FROM marketplace_stores WHERE id=?')->execute([$storeId]);

// Insert store
$pdo->prepare("INSERT INTO marketplace_stores (id, company_key, platform, store_name, status) VALUES (?, ?, 'Shopee', 'Shopee Test Store', 'active')")
    ->execute([$storeId, $companyKey]);

// Insert store connection
$pdo->prepare("INSERT INTO marketplace_oauth_connections (
    id, company_key, platform, store_id, external_store_name, status,
    access_token_encrypted, refresh_token_encrypted, token_expires_at, external_store_id
) VALUES (?, ?, 'Shopee', ?, 'Shopee Test Store', 'verified', ?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR), ?)")
->execute([
    $storeId,
    $companyKey,
    $storeId,
    MarketplaceTokenVault::encrypt('test_shopee_token'),
    MarketplaceTokenVault::encrypt('test_shopee_refresh'),
    $externalShopId,
]);

// Initial order without escrow data, HPP = 35.000
$initialSnapshot = [
    'gross_amount' => 76937.0,
    'total_cogs' => 35000.0,
    'net_income' => null,
    'total_fees' => null,
    'items' => [
        [
            'name' => 'JAGOIN Tas Ransel Pria Tas Backpack Sekolah SD SMP Waterproof Anti Air',
            'variant' => 'Navy',
            'sku' => 'Ransel Bimo',
            'quantity' => 1,
            'unit_price' => 76937.0,
            'total_price' => 76937.0,
            'unit_cogs' => 35000.0,
            'total_cogs' => 35000.0,
        ],
    ],
    'source_status' => 'DELIVERED',
];

$pdo->prepare("INSERT INTO marketplace_orders (
    id, store_id, order_date, order_status, gross_sales, total_cogs_hpp, source_kind, transaction_detail_json
) VALUES (?, ?, NOW(), 'Terkirim', 76937.00, 35000.00, 'api', ?)")
->execute([$orderId, $storeId, json_encode($initialSnapshot)]);

// Real Shopee API payload mock
$mockShopeeEscrow = [
    'order_sn' => $orderId,
    'buyer_user_name' => 'buyer_test',
    'order_income' => [
        'original_cost_of_goods_sold' => 76937.0,
        'cost_of_goods_sold' => 76937.0,
        'buyer_total_amount' => 76937.0,
        'buyer_paid_shipping_fee' => 0.0,
        'actual_shipping_fee' => 8000.0,
        'shopee_shipping_rebate' => 8000.0,
        'transaction_fee_list' => [
            ['fee_name' => 'Biaya Administrasi (termasuk PPN 11%)', 'fee_amount' => 7848.0],
            ['fee_name' => 'Biaya Proses Pesanan', 'fee_amount' => 1250.0],
            ['fee_name' => 'Biaya Pembayaran', 'fee_amount' => 1385.0],
        ],
        'service_fee_list' => [
            ['service_fee_name' => 'Biaya Gratis Ongkir XTRA - Ukuran Biasa (Kategori G)', 'service_fee' => 4232.0],
            ['service_fee_name' => 'Biaya Layanan Promo XTRA', 'service_fee' => 3462.0],
        ],
        'campaign_fee' => 1923.0,
        'withholding_tax' => 385.0,
        'escrow_amount' => 56452.0,
    ],
];

// Mock transport / adapter factory
$clientFactory = function(array $config) use ($mockShopeeEscrow) {
    return new class($mockShopeeEscrow) {
        public function __construct(private array $mock) {}
        public function call(string $method, string $path, array $query, string $token, ?array $body = null): array {
            return $this->mock;
        }
        public function escrowDetail(string $orderSn, string $token): array {
            return $this->mock;
        }
    };
};

$sync = new MarketplaceSyncService($pdo, $clientFactory, true);
$result = $sync->refreshOrderFinance($storeId, $orderId);

assertFinance($result === true, 'refreshOrderFinance returns true for Shopee escrow sync');

// Check database updates
$stmt = $pdo->prepare('SELECT net_settlement_amount, net_margin_profit, transaction_detail_json FROM marketplace_orders WHERE id=? AND store_id=?');
$stmt->execute([$orderId, $storeId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

assertFinance($row !== false, 'Order exists');
assertFinance((float)$row['net_settlement_amount'] === 56452.00, 'net_settlement_amount updated to 56452.00');
assertFinance((float)$row['net_margin_profit'] === 21452.00, 'net_margin_profit updated to 21452.00 (56452 - 35000)');

$savedJson = json_decode($row['transaction_detail_json'], true);
assertFinance($savedJson['finance_schema'] === 'shopee_escrow_v2', 'Schema is shopee_escrow_v2');
assertFinance((float)$savedJson['net_income'] === 56452.0, 'Net income is 56452');
assertFinance((float)$savedJson['net_profit'] === 21452.0, 'Net profit is 21452');
assertFinance((float)$savedJson['total_fees'] === 20485.0, 'Total fees is 20485');

// Cleanup
$pdo->prepare('DELETE FROM marketplace_orders WHERE id=?')->execute([$orderId]);
$pdo->prepare('DELETE FROM marketplace_oauth_connections WHERE id=?')->execute([$storeId]);
$pdo->prepare('DELETE FROM marketplace_stores WHERE id=?')->execute([$storeId]);

echo "PASS: Shopee escrow dynamic sync successfully updated order finance and net profit after COGS.\n";
