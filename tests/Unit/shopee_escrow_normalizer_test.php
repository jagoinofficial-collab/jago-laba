<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/Bootstrap/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/Modules/Marketplace/Services/MarketplaceFinanceNormalizer.php';
require_once dirname(__DIR__, 2) . '/app/Modules/Marketplace/Services/MarketplaceOrderPresentation.php';

function assertShopee(bool $cond, string $msg): void
{
    if (!$cond) {
        throw new RuntimeException("Assertion failed: " . $msg);
    }
}

// Real payload sample matching Shopee Open API v2 get_escrow_detail
$shopeeEscrowPayload = [
    'order_sn' => '246847161287478',
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

// Snapshot with internal HPP set to Rp 35.000
$initialSnapshot = [
    'gross_amount' => 76937.0,
    'total_cogs' => 35000.0,
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
];

$normalized = MarketplaceFinanceNormalizer::applyShopeeEscrow($initialSnapshot, $shopeeEscrowPayload);

assertShopee($normalized['gross_amount'] === 76937.0, 'Gross amount is 76937');
assertShopee($normalized['net_income'] === 56452.0, 'Net income (escrow) is 56452');
assertShopee($normalized['total_fees'] === 20485.0, 'Total fees is 20485');
assertShopee($normalized['net_profit'] === 21452.0, 'Net profit is 56452 - 35000 = 21452');
assertShopee($normalized['finance_schema'] === 'shopee_escrow_v2', 'Schema set to shopee_escrow_v2');

// Presentation verification
$presentation = MarketplaceOrderPresentation::build([
    'id' => '246847161287478',
    'platform' => 'Shopee',
    'transaction_detail_json' => json_encode($normalized),
]);

assertShopee($presentation['gross_amount'] === 76937.0, 'Presentation gross amount');
assertShopee($presentation['net_income'] === 56452.0, 'Presentation net income');
assertShopee($presentation['total_fees'] === 20485.0, 'Presentation total fees');
assertShopee($presentation['total_cogs'] === 35000.0, 'Presentation total COGS');
assertShopee($presentation['net_profit'] === 21452.0, 'Presentation net profit');
assertShopee($presentation['profit_percent'] === 27.9, 'Presentation profit margin is 27.9%');
assertShopee($presentation['component_difference'] === 0.0, 'No component difference');
assertShopee($presentation['reconciliation_difference'] === 0.0, 'No reconciliation difference');

// Verify individual component labels exist
$labels = array_column($presentation['components'], 'label');
assertShopee(in_array('Biaya Administrasi (termasuk PPN 11%)', $labels, true), 'Biaya Administrasi label');
assertShopee(in_array('Biaya Proses Pesanan', $labels, true), 'Biaya Proses Pesanan label');
assertShopee(in_array('Biaya Pembayaran', $labels, true), 'Biaya Pembayaran label');
assertShopee(in_array('Biaya Gratis Ongkir XTRA - Ukuran Biasa (Kategori G)', $labels, true), 'Gratis Ongkir XTRA label');
assertShopee(in_array('Biaya Layanan Promo XTRA', $labels, true), 'Promo XTRA label');
assertShopee(in_array('Biaya Kampanye & Promosi', $labels, true), 'Campaign fee label');
assertShopee(in_array('Pajak Pemotongan PPh', $labels, true), 'Withholding tax label');

echo "PASS: Shopee Escrow Normalization and Net Profit Calculation with real dynamic API data.\n";

