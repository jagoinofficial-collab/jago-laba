<?php
require_once (dirname(__DIR__, 2) . '/app/Bootstrap/bootstrap.php');
$pdo = Database::pdo();

echo "Seeding Advanced Marketplace Data...\n";

// 1. SEED STOCK ALLOCATIONS
$pdo->exec("DELETE FROM marketplace_stock_allocations");

$variants = $pdo->query("SELECT pv.id as variant_id, pv.sku, p.name as product_name, COALESCE(s.stock_qty, 0) as physical_stock
    FROM product_variants pv
    JOIN products p ON pv.product_id = p.id
    LEFT JOIN inventory_stocks s ON s.product_variant_id = pv.id
    ORDER BY p.name ASC, pv.sku ASC")->fetchAll(PDO::FETCH_ASSOC);

$allocStmt = $pdo->prepare("INSERT INTO marketplace_stock_allocations 
    (variant_id, sku, product_name, total_physical_stock, shopee_quota_pct, tiktok_quota_pct, offline_quota_pct, 
     shopee_allocated_qty, tiktok_allocated_qty, offline_allocated_qty, safety_buffer_threshold, is_safety_buffered, status, last_synced_at, updated_at)
    VALUES (?, ?, ?, ?, 60.00, 30.00, 10.00, ?, ?, ?, 3, ?, ?, NOW(), NOW())");

foreach ($variants as $v) {
    $stock = (int)$v['physical_stock'];
    $bufferThreshold = 3;
    
    if ($stock <= $bufferThreshold) {
        $isBuffered = 1;
        $status = $stock <= 0 ? 'OUT_OF_STOCK' : 'BUFFERED_ZERO';
        $shopeeQty = 0;
        $tiktokQty = 0;
        $offlineQty = $stock; // Sisakan untuk offline saja jika tipis
    } else {
        $isBuffered = 0;
        $status = 'ACTIVE';
        $shopeeQty = (int)round($stock * 0.60);
        $tiktokQty = (int)round($stock * 0.30);
        $offlineQty = max(0, $stock - $shopeeQty - $tiktokQty);
    }

    $allocStmt->execute([
        $v['variant_id'],
        $v['sku'],
        $v['product_name'] . ' - ' . $v['sku'],
        $stock,
        $shopeeQty,
        $tiktokQty,
        $offlineQty,
        $isBuffered,
        $status
    ]);
}
echo "Seeded " . count($variants) . " stock allocations with safety buffers.\n";

// 2. UPDATE ORDERS WITH SHIPPING DISCREPANCY & AFFILIATE COMMISSIONS
$pdo->exec("UPDATE marketplace_orders SET 
    declared_weight_gram = 500,
    actual_weight_gram = 500,
    shipping_discrepancy_amount = 0.00,
    affiliate_commission = 0.00,
    live_commission = 0.00");

// Update some orders to have real shipping discrepancies (volume adjustment by courier)
$pdo->exec("UPDATE marketplace_orders SET 
    declared_weight_gram = 450,
    actual_weight_gram = 1350,
    shipping_adjustment = 9500.00,
    shipping_discrepancy_amount = 9500.00,
    operational_expense = operational_expense + 9500.00
    WHERE id LIKE '%254420784036972300%' OR id LIKE 'SHOPEE ADJ%selisih%' OR id LIKE '%ORD-SHP-20261018-0012%'");

// Update a few more orders for discrepancy filters
$discrepancyOrders = $pdo->query("SELECT id FROM marketplace_orders WHERE order_status = 'Selesai' LIMIT 5")->fetchAll(PDO::FETCH_COLUMN);
foreach ($discrepancyOrders as $idx => $oid) {
    if ($idx < 3) {
        $extraWeight = 750 + ($idx * 200);
        $discFee = 8000.00 + ($idx * 3500.00);
        $pdo->prepare("UPDATE marketplace_orders SET 
            declared_weight_gram = 500, 
            actual_weight_gram = ?, 
            shipping_adjustment = ?, 
            shipping_discrepancy_amount = ? 
            WHERE id = ?")->execute([$extraWeight, $discFee, $discFee, $oid]);
    }
}

// Update TikTok orders with creator affiliate & live commissions
$pdo->exec("UPDATE marketplace_orders SET 
    affiliate_commission = ROUND(gross_sales * 0.07, 2),
    live_commission = ROUND(gross_sales * 0.03, 2)
    WHERE store_id = 'STR-TIKTOK-JAGOIN' AND order_status IN ('Selesai', 'Terkirim')");

// Shopee affiliate for some orders
$pdo->exec("UPDATE marketplace_orders SET 
    affiliate_commission = ROUND(gross_sales * 0.05, 2)
    WHERE store_id = 'STR-SHOPEE-JAGOIN' AND id LIKE '%8%' AND order_status = 'Selesai'");

echo "Updated orders with shipping discrepancies & creator/live affiliate fees.\n";

// 3. SEED RETURN CLAIMS (Pesanan Retur > 14 Hari Tanpa Fisik)
$pdo->exec("DELETE FROM marketplace_return_claims");

$retOrders = $pdo->query("SELECT id, store_id, buyer_username, gross_sales, order_date 
    FROM marketplace_orders 
    WHERE order_status = 'Retur' OR id LIKE '%ADJ%' 
    LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);

$claimStmt = $pdo->prepare("INSERT INTO marketplace_return_claims 
    (store_id, order_id, buyer_username, product_summary, courier_name, tracking_number, return_initiated_at, days_pending, claim_status, claim_amount, reimbursement_amount, evidence_notes, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

$couriers = ['J&T Express', 'SPX Standard', 'SiCepat REG', 'J&T Cargo'];
$statuses = ['MENUNGGU_FISIK', 'KLAIM_DIAJUKAN', 'DISETUJUI_GANTI_RUGI', 'MENUNGGU_FISIK', 'KLAIM_DIAJUKAN'];

foreach ($retOrders as $i => $ro) {
    $days = 15 + ($i * 3); // 15, 18, 21, 24, 27 days (>14 days)
    $retDate = date('Y-m-d H:i:s', strtotime("-$days days"));
    $courier = $couriers[$i % count($couriers)];
    $resi = 'JP' . rand(1000000000, 9999999999);
    $status = $statuses[$i % count($statuses)];
    $amount = (float)$ro['gross_sales'] ?: 125000.00;
    $reimburse = $status === 'DISETUJUI_GANTI_RUGI' ? $amount : 0.00;
    $notes = "Paket retur buyer belum tiba di Gudang Garut melebihi batas 14 hari kerja. Posisi resi terakhir tertahan di Hub transit {$courier}.";

    $claimStmt->execute([
        $ro['store_id'],
        $ro['id'],
        $ro['buyer_username'] ?: 'CustomerRetur_' . ($i + 1),
        'Jaket Coach Taslan / Kemeja Tactical',
        $courier,
        $resi,
        $retDate,
        $days,
        $status,
        $amount,
        $reimburse,
        $notes
    ]);

    // Update order status reference
    $pdo->prepare("UPDATE marketplace_orders SET return_initiated_at = ?, return_claim_status = ? WHERE id = ?")
        ->execute([$retDate, $status, $ro['id']]);
}
echo "Seeded " . count($retOrders) . " return claims (>14 days pending tracking).\n";

// 4. SEED PRICE SIMULATIONS
$pdo->exec("DELETE FROM marketplace_price_simulations");

$simulations = [
    [
        'product_name' => 'Jaket Coach Waterproof Taslan JN-01',
        'sku' => 'JKT-COACH-TASLAN-01',
        'cogs_hpp' => 45000.00,
        'target_net_margin_pct' => 25.00,
        'admin_fee_pct' => 11.00,
        'free_shipping_xtra_pct' => 4.00,
        'payment_fee_pct' => 1.00,
        'ad_budget_pct' => 10.00,
        'simulated_normal_price' => 76500.00,
        'simulated_flash_sale_price' => 59900.00,
        'break_even_roas' => 1.85,
        'net_profit_per_pcs' => 19125.00
    ],
    [
        'product_name' => 'Kemeja Tactical W-Tac Ripstop Outdoor',
        'sku' => 'KMJ-WTAC-RIPSTOP',
        'cogs_hpp' => 52000.00,
        'target_net_margin_pct' => 30.00,
        'admin_fee_pct' => 11.00,
        'free_shipping_xtra_pct' => 4.00,
        'payment_fee_pct' => 1.00,
        'ad_budget_pct' => 12.00,
        'simulated_normal_price' => 92500.00,
        'simulated_flash_sale_price' => 72000.00,
        'break_even_roas' => 2.15,
        'net_profit_per_pcs' => 27750.00
    ],
    [
        'product_name' => 'Celana Cargo Tactical PDL Ripstop',
        'sku' => 'CLN-CARGO-PDL-03',
        'cogs_hpp' => 58000.00,
        'target_net_margin_pct' => 28.00,
        'admin_fee_pct' => 11.00,
        'free_shipping_xtra_pct' => 4.00,
        'payment_fee_pct' => 1.00,
        'ad_budget_pct' => 10.00,
        'simulated_normal_price' => 98000.00,
        'simulated_flash_sale_price' => 78500.00,
        'break_even_roas' => 1.95,
        'net_profit_per_pcs' => 27440.00
    ],
    [
        'product_name' => 'Kaos Polos Cotton Combed 24s Heavyweight',
        'sku' => 'KOS-COMBED-24S',
        'cogs_hpp' => 24000.00,
        'target_net_margin_pct' => 22.00,
        'admin_fee_pct' => 11.00,
        'free_shipping_xtra_pct' => 4.00,
        'payment_fee_pct' => 1.00,
        'ad_budget_pct' => 10.00,
        'simulated_normal_price' => 43500.00,
        'simulated_flash_sale_price' => 34500.00,
        'break_even_roas' => 2.45,
        'net_profit_per_pcs' => 9570.00
    ]
];

$simStmt = $pdo->prepare("INSERT INTO marketplace_price_simulations 
    (product_name, sku, cogs_hpp, target_net_margin_pct, admin_fee_pct, free_shipping_xtra_pct, payment_fee_pct, ad_budget_pct, 
     simulated_normal_price, simulated_flash_sale_price, break_even_roas, net_profit_per_pcs, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

foreach ($simulations as $s) {
    $simStmt->execute(array_values($s));
}
echo "Seeded " . count($simulations) . " price simulations.\n";

// 5. UPDATE PRODUK BOCOR FLAGS & SEED GROUP ADS
// Reset first
$pdo->exec("UPDATE marketplace_product_ads SET is_bocor = 0, bocor_reason = NULL, ad_type = 'Produk'");

// Flag 2 products as bocor
$bocorProducts = $pdo->query("SELECT id, product_name, ad_spend, gross_revenue, orders_count 
    FROM marketplace_product_ads 
    WHERE ad_spend >= 500000 
    ORDER BY (gross_revenue / ad_spend) ASC 
    LIMIT 2")->fetchAll(PDO::FETCH_ASSOC);

foreach ($bocorProducts as $bp) {
    $reason = "Biaya Iklan Rp " . number_format($bp['ad_spend'], 0, ',', '.') . " hanya menghasilkan " . $bp['orders_count'] . " order. ROAS di bawah batas BEP! Segera jeda iklan kata kunci non-konversi.";
    $pdo->prepare("UPDATE marketplace_product_ads SET is_bocor = 1, bocor_reason = ? WHERE id = ?")
        ->execute([$reason, $bp['id']]);
}

// Also add a zero-order bocor product if none
$pdo->exec("UPDATE marketplace_product_ads SET 
    is_bocor = 1, 
    bocor_reason = 'Spend Rp 215.000 dalam 24 jam dengan 0 pesanan! Segera matikan bid luas kata kunci.'
    WHERE ad_spend > 0 AND orders_count = 0 LIMIT 1");

// Seed Group Ads / Iklan Toko & Discovery
$storeAds = [
    [
        'store_id' => 'STR-SHOPEE-JAGOIN',
        'platform' => 'Shopee',
        'ad_type' => 'Grup_Toko',
        'campaign_name' => 'Iklan Toko Shopee - Brand JAGOIN Konveksi Official',
        'sku' => 'STORE-AD-SHP-01',
        'product_name' => 'Campaign Toko: Diskon Ramadhan & Grosir Seragam',
        'ad_spend' => 3200000.00,
        'gross_revenue' => 24500000.00,
        'orders_count' => 195,
        'qty_sold' => 240,
        'impressions' => 145000,
        'clicks' => 6200,
        'conversions' => 195,
        'unit_cogs_hpp' => 45000.00,
        'platform_fee' => 2695000.00,
        'affiliate_fee' => 0.00
    ],
    [
        'store_id' => 'STR-SHOPEE-JAGOIN',
        'platform' => 'Shopee',
        'ad_type' => 'Discovery',
        'campaign_name' => 'Discovery Ads Shopee - Rekomendasi Feed & Serupa',
        'sku' => 'DISC-AD-SHP-02',
        'product_name' => 'Discovery: Jaket & Kemeja Tactical Best Seller',
        'ad_spend' => 1850000.00,
        'gross_revenue' => 15200000.00,
        'orders_count' => 124,
        'qty_sold' => 155,
        'impressions' => 98000,
        'clicks' => 3800,
        'conversions' => 124,
        'unit_cogs_hpp' => 48000.00,
        'platform_fee' => 1672000.00,
        'affiliate_fee' => 0.00
    ],
    [
        'store_id' => 'STR-TIKTOK-JAGOIN',
        'platform' => 'TikTok Shop',
        'ad_type' => 'Live_Affiliate',
        'campaign_name' => 'Live Shopping Special Creator Affiliate & Host',
        'sku' => 'LIVE-TT-CREATOR-01',
        'product_name' => 'Live Stream Weekend Promo Rompi & Jaket Coach',
        'ad_spend' => 2450000.00,
        'gross_revenue' => 21800000.00,
        'orders_count' => 180,
        'qty_sold' => 210,
        'impressions' => 180000,
        'clicks' => 8500,
        'conversions' => 180,
        'unit_cogs_hpp' => 45000.00,
        'platform_fee' => 2398000.00,
        'affiliate_fee' => 1526000.00
    ]
];

$groupStmt = $pdo->prepare("INSERT INTO marketplace_product_ads 
    (store_id, platform, ad_type, campaign_name, marketplace_item_id, sku, product_name, image_url, ad_spend, gross_revenue, 
     orders_count, qty_sold, impressions, clicks, conversions, unit_cogs_hpp, platform_fee, affiliate_fee, ppn_rate, is_bocor, period_start, period_end, last_synced_at, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, 'https://images.unsplash.com/photo-1441986300917-64674bd600d8?w=100&q=80', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 11.00, 0, '2026-10-01', '2026-10-31', NOW(), NOW())");

foreach ($storeAds as $ga) {
    $itemId = 'GRP-' . strtoupper(substr(md5($ga['sku']), 0, 10));
    $groupStmt->execute([
        $ga['store_id'],
        $ga['platform'],
        $ga['ad_type'],
        $ga['campaign_name'],
        $itemId,
        $ga['sku'],
        $ga['product_name'],
        $ga['ad_spend'],
        $ga['gross_revenue'],
        $ga['orders_count'],
        $ga['qty_sold'],
        $ga['impressions'],
        $ga['clicks'],
        $ga['conversions'],
        $ga['unit_cogs_hpp'],
        $ga['platform_fee'],
        $ga['affiliate_fee']
    ]);
}
echo "Seeded group ads and discovery campaigns.\n";

// 6. SEED DOUBLE-ENTRY JOURNAL ENTRIES
$pdo->exec("DELETE FROM marketplace_journal_entries");

// Generate sample journal for a batch of completed orders & payouts
$recentOrders = $pdo->query("SELECT id, store_id, order_date, gross_sales, total_cogs_hpp, platform_admin_fee, service_fee, payment_fee, net_settlement_amount 
    FROM marketplace_orders 
    WHERE order_status = 'Selesai' 
    ORDER BY order_date DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

$journalStmt = $pdo->prepare("INSERT INTO marketplace_journal_entries 
    (journal_no, entry_date, ref_type, ref_id, store_id, account_code, account_name, debit, credit, memo, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

$jNum = 1001;
foreach ($recentOrders as $ro) {
    $jno = 'JRN-' . date('Ymd', strtotime($ro['order_date'])) . '-' . $jNum++;
    $dt = substr($ro['order_date'], 0, 10);
    $store = $ro['store_id'];
    $gross = (float)$ro['gross_sales'];
    $hpp = (float)$ro['total_cogs_hpp'];
    $oid = $ro['id'];

    // 1. Pesanan Selesai: Piutang Escrow (Debit) & Pendapatan Bruto (Kredit)
    $journalStmt->execute([$jno, $dt, 'ORDER_COMPLETED', $oid, $store, '1102', 'Piutang Escrow Marketplace', $gross, 0.00, "Pengakuan Piutang Escrow Penjualan Pesanan #$oid"]);
    $journalStmt->execute([$jno, $dt, 'ORDER_COMPLETED', $oid, $store, '4101', 'Pendapatan Penjualan Bruto', 0.00, $gross, "Pendapatan Bruto Penjualan Pesanan #$oid"]);

    // 2. HPP & Persediaan: Beban Pokok Penjualan (Debit) & Persediaan Barang Jadi (Kredit)
    $journalStmt->execute([$jno, $dt, 'ORDER_COMPLETED', $oid, $store, '5101', 'Beban Pokok Penjualan (HPP)', $hpp, 0.00, "HPP Barang Keluar Pesanan #$oid"]);
    $journalStmt->execute([$jno, $dt, 'ORDER_COMPLETED', $oid, $store, '1104', 'Persediaan Barang Jadi Gudang', 0.00, $hpp, "Pengurangan Persediaan Gudang Pesanan #$oid"]);
}

// Sample Payout Settlement Journal (Escrow Cair ke Bank SeaBank)
$payoutJno = 'JRN-' . date('Ymd') . '-PAYOUT-01';
$pDate = date('Y-m-d');
$pStore = 'STR-SHOPEE-JAGOIN';
$payoutNet = 15420000.00;
$payoutFeeAdmin = 1850000.00;
$payoutFeeOngkir = 620000.00;
$payoutAdsDeduction = 1500000.00;
$totalEscrowSettled = $payoutNet + $payoutFeeAdmin + $payoutFeeOngkir + $payoutAdsDeduction;

$journalStmt->execute([$payoutJno, $pDate, 'ESCROW_PAYOUT', 'PAYOUT-SHP-20261022', $pStore, '1101', 'Kas / Bank SeaBank (KS002SB)', $payoutNet, 0.00, "Pencairan Dana Escrow Shopee Masuk Rekening"]);
$journalStmt->execute([$payoutJno, $pDate, 'ESCROW_PAYOUT', 'PAYOUT-SHP-20261022', $pStore, '5201', 'Beban Komisi & Admin Platform', $payoutFeeAdmin, 0.00, "Potongan Biaya Admin Shopee"]);
$journalStmt->execute([$payoutJno, $pDate, 'ESCROW_PAYOUT', 'PAYOUT-SHP-20261022', $pStore, '5202', 'Beban Layanan & Ongkir Xtra', $payoutFeeOngkir, 0.00, "Potongan Program Gratis Ongkir Xtra"]);
$journalStmt->execute([$payoutJno, $pDate, 'ESCROW_PAYOUT', 'PAYOUT-SHP-20261022', $pStore, '5301', 'Beban Iklan Berbayar Marketplace', $payoutAdsDeduction, 0.00, "Potongan Saldo Iklan Shopee Ads"]);
$journalStmt->execute([$payoutJno, $pDate, 'ESCROW_PAYOUT', 'PAYOUT-SHP-20261022', $pStore, '1102', 'Piutang Escrow Marketplace', 0.00, $totalEscrowSettled, "Pelunasan Escrow Shopee Batch Settlement"]);

echo "Seeded double-entry journal entries for orders and bank escrow payout.\n";
echo "All advanced data seeded successfully!\n";

