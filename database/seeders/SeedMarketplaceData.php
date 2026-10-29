<?php
require_once (dirname(__DIR__, 2) . '/app/Database.php');

final class SeedMarketplaceData
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    public function run(): void
    {
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

        // 1. Seed Stores
        $stmtStore = $this->pdo->prepare("INSERT INTO marketplace_stores 
            (id, platform, store_name, store_identifier, default_account_id, sync_mode, total_transactions, last_synced_at, status)
            VALUES (?, ?, ?, ?, ?, 'API', ?, NOW(), 'Terhubung')
            ON DUPLICATE KEY UPDATE total_transactions=VALUES(total_transactions), last_synced_at=NOW()");

        $stmtStore->execute([
            'STR-TIKTOK-JAGOIN',
            'TikTok Shop',
            'JAGOIN - TikTok',
            'tiktok_jagoin_official',
            1789113933482, // Mandiri
            1465
        ]);

        $stmtStore->execute([
            'STR-SHOPEE-JAGOIN',
            'Shopee',
            'JAGOIN Official Store - Shopee',
            'shopee_jagoin_official',
            1786634525412, // SeaBank
            2309
        ]);

        echo "1. Toko Marketplace berhasil didaftarkan (TikTok Shop & Shopee).\n";

        // 2. Seed SKU Mappings
        $variants = $this->pdo->query("SELECT pv.id, pv.sku, p.name, pv.color, s.average_hpp 
            FROM product_variants pv 
            JOIN products p ON pv.product_id = p.id 
            LEFT JOIN inventory_stocks s ON s.product_variant_id = pv.id
            WHERE pv.sku LIKE 'JAG-%' OR pv.sku LIKE 'DOMPET%' OR pv.sku LIKE 'TSRL%'
            LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

        $stmtMap = $this->pdo->prepare("INSERT INTO marketplace_sku_mappings 
            (store_id, marketplace_sku, marketplace_product_name, product_variant_id)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE marketplace_product_name=VALUES(marketplace_product_name)");

        foreach (['STR-TIKTOK-JAGOIN', 'STR-SHOPEE-JAGOIN'] as $storeId) {
            foreach ($variants as $v) {
                $mSku = ($storeId === 'STR-SHOPEE-JAGOIN' ? 'SHP-' : 'TTK-') . $v['sku'];
                $mName = $v['name'] . ' (' . ($v['color'] ?: 'Varian') . ')';
                $stmtMap->execute([$storeId, $mSku, $mName, $v['id']]);
            }
        }
        echo "2. Pemetaan SKU Varian Konveksi ke Marketplace berhasil dibuat.\n";

        // 3. Seed Sample Orders with Realistic Breakdown
        $stmtOrder = $this->pdo->prepare("INSERT INTO marketplace_orders 
            (id, store_id, order_date, completion_date, buyer_username, order_status, gross_sales, seller_discount, platform_admin_fee, service_fee, payment_fee, shipping_adjustment, other_deductions, net_settlement_amount, total_cogs_hpp, escrow_status, payout_batch_id)
            VALUES (?, ?, ?, ?, ?, 'Selesai', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Sudah Dicairkan', ?)
            ON DUPLICATE KEY UPDATE net_settlement_amount=VALUES(net_settlement_amount)");

        $stmtItem = $this->pdo->prepare("INSERT INTO marketplace_order_items 
            (order_id, product_variant_id, sku, item_name, qty, selling_price, unit_cogs_hpp, subtotal_price, subtotal_cogs)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $ordersSample = [
            // Shopee Orders
            [
                'store_id' => 'STR-SHOPEE-JAGOIN',
                'order_no' => '241022SHP-988102A',
                'date' => '2026-10-21 10:14:20',
                'buyer' => 'budi_hartono',
                'sku' => 'JAG-MONO',
                'selling' => 95000,
                'qty' => 1,
                'payout_batch' => 'BATCH-SHP-20261021'
            ],
            [
                'store_id' => 'STR-SHOPEE-JAGOIN',
                'order_no' => '241022SHP-988103B',
                'date' => '2026-10-21 11:32:05',
                'buyer' => 'dewi_kartika',
                'sku' => 'JAG-METR0',
                'selling' => 110000,
                'qty' => 1,
                'payout_batch' => 'BATCH-SHP-20261021'
            ],
            [
                'store_id' => 'STR-SHOPEE-JAGOIN',
                'order_no' => '241022SHP-988104C',
                'date' => '2026-10-22 09:20:11',
                'buyer' => 'hendra_wijaya',
                'sku' => 'JAG-HP',
                'selling' => 55000,
                'qty' => 2,
                'payout_batch' => 'BATCH-SHP-20261022'
            ],
            // TikTok Shop Orders
            [
                'store_id' => 'STR-TIKTOK-JAGOIN',
                'order_no' => '57918239012391029',
                'date' => '2026-10-21 14:05:40',
                'buyer' => 'rina_safitri_shop',
                'sku' => 'JAG-MONO-NAVY',
                'selling' => 95000,
                'qty' => 1,
                'payout_batch' => 'BATCH-TTK-20261021'
            ],
            [
                'store_id' => 'STR-TIKTOK-JAGOIN',
                'order_no' => '57918239012391030',
                'date' => '2026-10-22 13:45:18',
                'buyer' => 'andi_saputra99',
                'sku' => 'JAG-METR0',
                'selling' => 110000,
                'qty' => 2,
                'payout_batch' => 'BATCH-TTK-20261022'
            ]
        ];

        foreach ($ordersSample as $o) {
            $gross = $o['selling'] * $o['qty'];
            $discount = 0.00;
            // Fee structure: approx 6.5% admin, 4% service, 1% payment
            $adminFee = round($gross * 0.065, 2);
            $serviceFee = round($gross * 0.040, 2);
            $payFee = round($gross * 0.010, 2);
            $shipAdj = 0.00;
            $other = 0.00;
            $netSettlement = $gross - ($adminFee + $serviceFee + $payFee + $shipAdj + $other);

            // Fetch real Konveksi HPP from inventory_stocks / products
            $vQuery = $this->pdo->prepare("SELECT pv.id, pv.sku, p.name, COALESCE(s.average_hpp, p.standard_hpp, 45000) as hpp 
                FROM product_variants pv 
                JOIN products p ON pv.product_id = p.id 
                LEFT JOIN inventory_stocks s ON s.product_variant_id = pv.id 
                WHERE pv.sku = ? LIMIT 1");
            $vQuery->execute([$o['sku']]);
            $var = $vQuery->fetch(PDO::FETCH_ASSOC);

            $unitHpp = $var ? (float)$var['hpp'] : 45000.00;
            $totalHpp = $unitHpp * $o['qty'];
            $varId = $var ? $var['id'] : null;
            $prodName = $var ? $var['name'] : $o['sku'];

            $stmtOrder->execute([
                $o['order_no'],
                $o['store_id'],
                $o['date'],
                $o['date'],
                $o['buyer'],
                $gross,
                $discount,
                $adminFee,
                $serviceFee,
                $payFee,
                $shipAdj,
                $other,
                $netSettlement,
                $totalHpp,
                $o['payout_batch']
            ]);

            $stmtItem->execute([
                $o['order_no'],
                $varId,
                $o['sku'],
                $prodName,
                $o['qty'],
                $o['selling'],
                $unitHpp,
                $gross,
                $totalHpp
            ]);
        }

        echo "3. Sampel Transaksi Pesanan & Rekonsiliasi HPP berhasil dibuat.\n";

        // 4. Seed Ad Spends
        $stmtAds = $this->pdo->prepare("INSERT INTO marketplace_ad_spends 
            (store_id, campaign_date, campaign_name, spend_amount, gross_sales_generated)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE spend_amount=VALUES(spend_amount)");

        $stmtAds->execute(['STR-SHOPEE-JAGOIN', '2026-10-21', 'Shopee Iklan Pencarian & Produk Serupa', 350000, 2450000]);
        $stmtAds->execute(['STR-SHOPEE-JAGOIN', '2026-10-22', 'Shopee Flash Sale & Promo Puncak', 450000, 3150000]);
        $stmtAds->execute(['STR-TIKTOK-JAGOIN', '2026-10-21', 'TikTok Shop LIVE Shopping Ads', 250000, 1800000]);
        $stmtAds->execute(['STR-TIKTOK-JAGOIN', '2026-10-22', 'TikTok Video Shopping Ads (VSA)', 300000, 2250000]);

        echo "4. Data Biaya Iklan (Shopee Ads & TikTok Ads) berhasil dicatat.\n";

        // 5. Seed Payout Reconciliations to Bank Accounts
        $stmtPay = $this->pdo->prepare("INSERT INTO marketplace_payout_reconciliations 
            (id, store_id, account_id, payout_date, total_payout_amount, matched_orders_count, status, notes)
            VALUES (?, ?, ?, ?, ?, ?, 'Matched', ?)");

        $stmtPay->execute([
            'BATCH-SHP-20261021',
            'STR-SHOPEE-JAGOIN',
            1786634525412, // SeaBank
            '2026-10-21',
            181535.00,
            2,
            'Pencairan Saldo Shopee Otomatis ke SeaBank PT JAGO LABA'
        ]);

        $stmtPay->execute([
            'BATCH-TTK-20261021',
            'STR-TIKTOK-JAGOIN',
            1789113933482, // Mandiri
            '2026-10-21',
            84075.00,
            1,
            'Penarikan Dana TikTok Shop ke Rekening Mandiri PT JAGO LABA'
        ]);

        echo "5. Rekonsiliasi Pencairan Saldo ke Rekening Bank (SeaBank & Mandiri) berhasil.\n";

        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    }
}

$seeder = new SeedMarketplaceData();
$seeder->run();
echo "=== SEEDING MARKETPLACE SELESAI ===\n";

