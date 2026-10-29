<?php
require_once (dirname(__DIR__, 2) . '/app/Database.php');

final class SeedExactFinkamiReference
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    public function run(): void
    {
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

        // 1. Ensure Stores
        $stmtStore = $this->pdo->prepare("INSERT INTO marketplace_stores 
            (id, platform, store_name, store_identifier, default_account_id, sync_mode, total_transactions, last_synced_at, status)
            VALUES (?, ?, ?, ?, ?, 'API', ?, NOW(), 'Terhubung')
            ON DUPLICATE KEY UPDATE 
                store_name=VALUES(store_name),
                total_transactions=VALUES(total_transactions),
                last_synced_at=NOW()");

        $stmtStore->execute([
            'STR-SHOPEE-JAGOIN',
            'Shopee',
            'JAGOIN Official Store - Shopee',
            'shopee_jagoin_official',
            1786634525412, // SeaBank
            2309
        ]);

        $stmtStore->execute([
            'STR-TIKTOK-JAGOIN',
            'TikTok Shop',
            'JAGOIN - TikTok',
            'tiktok_jagoin_official',
            1789113933482, // Mandiri
            1465
        ]);

        echo "1. Toko Shopee dan TikTok terdaftar.\n";

        // 2. Clear previous demo orders & ad spends
        $this->pdo->exec("DELETE FROM marketplace_orders");
        $this->pdo->exec("DELETE FROM marketplace_ad_spends");

        // 3. Seed Ad Spends
        $stmtAd = $this->pdo->prepare("INSERT INTO marketplace_ad_spends 
            (store_id, campaign_date, campaign_name, spend_amount, gross_sales_generated)
            VALUES (?, ?, ?, ?, ?)");

        // Shopee Ads total: 12.972.783
        $stmtAd->execute(['STR-SHOPEE-JAGOIN', '2026-10-14', 'Shopee Iklan Pencarian & Produk Serupa', 6972783.00, 65000000.00]);
        $stmtAd->execute(['STR-SHOPEE-JAGOIN', '2026-10-20', 'Shopee Iklan Toko & Flash Sale', 6000000.00, 56758145.00]);

        // TikTok Ads total: 10.374.000
        $stmtAd->execute(['STR-TIKTOK-JAGOIN', '2026-10-14', 'TikTok LIVE Shopping Ads', 5374000.00, 45000000.00]);
        $stmtAd->execute(['STR-TIKTOK-JAGOIN', '2026-10-20', 'TikTok Video Shopping Ads (VSA)', 5000000.00, 41450000.00]);

        echo "2. Biaya iklan Shopee & TikTok tercatat.\n";

        // 4. Seed Aggregated Orders for SHOPEE (Total 1348 Selesai, 9 Rugi, 10 Retur, 201 Batal)
        // Omzet: 121.758.145, HPP: 62.388.000, Potongan: 33.741.867, Penyesuaian: +65.496
        // Net Settlement = Omzet - Potongan + Penyesuaian = 121.758.145 - 33.741.867 + 65.496 = 88.081.774
        // Laba Bersih = Net Settlement - HPP - Ads = 88.081.774 - 62.388.000 - 12.972.783 = 12.720.991 (~12.721.549)
        
        $stmtOrder = $this->pdo->prepare("INSERT INTO marketplace_orders 
            (id, store_id, order_date, completion_date, buyer_username, order_status, gross_sales, seller_discount, platform_admin_fee, service_fee, payment_fee, shipping_adjustment, other_deductions, operational_expense, adjustment_amount, net_settlement_amount, total_cogs_hpp, escrow_status, payout_batch_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, 0, 0, ?, ?, ?, ?, 'Sudah Dicairkan', ?)");

        // Order Normal 1 (Representing batch completed orders)
        $stmtOrder->execute([
            '261021SHP-BATCH-NORMAL-01',
            'STR-SHOPEE-JAGOIN',
            '2026-10-15 10:00:00',
            '2026-10-16 14:00:00',
            'shopee_buyers_batch',
            'Selesai',
            119800000.00,
            20000000.00, // admin fee
            11000000.00, // service fee
            2200000.00,  // payment fee
            0.00,
            65496.00,    // Penyesuaian +65.496
            86665496.00, // net settlement
            61000000.00, // HPP konveksi
            'BATCH-SHP-20261021'
        ]);

        // Order Normal 2 (Small tail)
        $stmtOrder->execute([
            '261021SHP-BATCH-NORMAL-02',
            'STR-SHOPEE-JAGOIN',
            '2026-10-20 11:30:00',
            '2026-10-21 16:00:00',
            'hendra_tas_reseller',
            'Selesai',
            1958145.00,
            330000.00,
            180000.00,
            31867.00,
            0.00,
            0.00,
            1416278.00,
            1388000.00,
            'BATCH-SHP-20261021'
        ]);

        // 9 Transaksi Rugi (Laba bersih negatif karena promo flash sale / komisi tinggi)
        for ($i = 1; $i <= 9; $i++) {
            $stmtOrder->execute([
                "2610SHP-RUGI-00{$i}",
                'STR-SHOPEE-JAGOIN',
                "2026-10-18 1{$i}:20:00",
                "2026-10-19 1{$i}:20:00",
                "buyer_promo_{$i}",
                'Selesai',
                45000.00,
                5500.00,
                3800.00,
                900.00,
                0.00,
                0.00,
                34800.00,
                45000.00, // HPP 45.000 > Net settlement 34.800 (RUGI -10.200 per pesanan)
                'BATCH-SHP-20261021'
            ]);
        }

        // 10 Transaksi Retur
        for ($i = 1; $i <= 10; $i++) {
            $stmtOrder->execute([
                "2610SHP-RETUR-00{$i}",
                'STR-SHOPEE-JAGOIN',
                "2026-10-17 0{$i}:15:00",
                null,
                "buyer_retur_{$i}",
                'Retur',
                89000.00,
                0.00,
                0.00,
                0.00,
                0.00,
                0.00,
                0.00,
                45000.00,
                null
            ]);
        }

        // 201 Transaksi Batal
        for ($i = 1; $i <= 201; $i++) {
            $stmtOrder->execute([
                "2610SHP-BATAL-" . str_pad($i, 4, '0', STR_PAD_LEFT),
                'STR-SHOPEE-JAGOIN',
                "2026-10-16 12:00:00",
                null,
                "buyer_batal_{$i}",
                'Dibatalkan',
                95000.00,
                0.00,
                0.00,
                0.00,
                0.00,
                0.00,
                0.00,
                45000.00,
                null
            ]);
        }

        echo "3. Data transaksi Shopee berhasil disimulasikan sesuai foto referensi FinKami.\n";

        // 5. Seed Orders for TIKTOK SHOP
        // Omzet: 86.450.000, HPP: 43.225.000, Potongan: 21.612.500, Iklan: 10.374.000, Laba: 11.238.500
        $stmtOrder->execute([
            '579182390123910-BATCH-01',
            'STR-TIKTOK-JAGOIN',
            '2026-10-15 15:00:00',
            '2026-10-16 18:00:00',
            'tiktok_live_buyers',
            'Selesai',
            86450000.00,
            12967500.00, // 15% komisi
            6916000.00,  // 8% service
            1729000.00,  // 2% payment
            0.00,
            0.00,
            64837500.00,
            43225000.00,
            'BATCH-TTK-20261021'
        ]);

        // 4 Transaksi Rugi di TikTok
        for ($i = 1; $i <= 4; $i++) {
            $stmtOrder->execute([
                "579TTK-RUGI-00{$i}",
                'STR-TIKTOK-JAGOIN',
                "2026-10-18 2{$i}:00:00",
                "2026-10-19 2{$i}:00:00",
                "tiktok_buyer_affiliate_{$i}",
                'Selesai',
                40000.00,
                6000.00,
                3200.00,
                800.00,
                0.00,
                0.00,
                30000.00,
                45000.00, // Rugi -15.000
                'BATCH-TTK-20261021'
            ]);
        }

        // 6 Transaksi Retur di TikTok
        for ($i = 1; $i <= 6; $i++) {
            $stmtOrder->execute([
                "579TTK-RETUR-00{$i}",
                'STR-TIKTOK-JAGOIN',
                "2026-10-19 1{$i}:00:00",
                null,
                "tiktok_buyer_retur_{$i}",
                'Retur',
                85000.00,
                0.00,
                0.00,
                0.00,
                0.00,
                0.00,
                0.00,
                45000.00,
                null
            ]);
        }

        // 112 Transaksi Batal di TikTok
        for ($i = 1; $i <= 112; $i++) {
            $stmtOrder->execute([
                "579TTK-BATAL-" . str_pad($i, 4, '0', STR_PAD_LEFT),
                'STR-TIKTOK-JAGOIN',
                "2026-10-17 14:00:00",
                null,
                "tiktok_batal_{$i}",
                'Dibatalkan',
                85000.00,
                0.00,
                0.00,
                0.00,
                0.00,
                0.00,
                0.00,
                45000.00,
                null
            ]);
        }

        echo "4. Data transaksi TikTok Shop berhasil disimulasikan.\n";

        // 6. Seed Payouts
        $stmtPay = $this->pdo->prepare("INSERT INTO marketplace_payout_reconciliations 
            (id, store_id, account_id, payout_date, total_payout_amount, matched_orders_count, status, notes)
            VALUES (?, ?, ?, ?, ?, ?, 'Matched', ?)
            ON DUPLICATE KEY UPDATE total_payout_amount=VALUES(total_payout_amount)");

        $stmtPay->execute([
            'BATCH-SHP-20261021',
            'STR-SHOPEE-JAGOIN',
            1786634525412, // SeaBank
            '2026-10-21',
            88081774.00,
            1348,
            'Pencairan Saldo Shopee JAGOIN Official Store ke SeaBank PT JAGO LABA'
        ]);

        $stmtPay->execute([
            'BATCH-TTK-20261021',
            'STR-TIKTOK-JAGOIN',
            1789113933482, // Mandiri
            '2026-10-21',
            64837500.00,
            965,
            'Penarikan Dana TikTok Shop JAGOIN ke Rekening Mandiri PT JAGO LABA'
        ]);

        echo "5. Rekonsiliasi pencairan bank diperbarui.\n";

        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    }
}

$s = new SeedExactFinkamiReference();
$s->run();
echo "=== SEEDING REFERENSI SELESAI ===\n";

