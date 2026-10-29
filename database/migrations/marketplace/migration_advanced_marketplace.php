<?php
/**
 * Migration Script: Advanced Marketplace Features
 * - Adds required columns to marketplace_orders & marketplace_product_ads
 * - Creates marketplace_return_claims, marketplace_stock_allocations, marketplace_price_simulations, marketplace_journal_entries
 * - Seeds demo data for accurate calculations and visualizations
 */
require_once (dirname(__DIR__, 3) . '/app/Bootstrap/bootstrap.php');
$pdo = Database::pdo();

echo "Starting Advanced Marketplace Migration...\n";

// 1. ALTER marketplace_orders
$orderCols = $pdo->query("DESCRIBE marketplace_orders")->fetchAll(PDO::FETCH_COLUMN);
$ordersToAlter = [];

if (!in_array('affiliate_commission', $orderCols)) {
    $ordersToAlter[] = "ADD COLUMN affiliate_commission DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER other_deductions";
}
if (!in_array('live_commission', $orderCols)) {
    $ordersToAlter[] = "ADD COLUMN live_commission DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER affiliate_commission";
}
if (!in_array('declared_weight_gram', $orderCols)) {
    $ordersToAlter[] = "ADD COLUMN declared_weight_gram INT NOT NULL DEFAULT 500 AFTER live_commission";
}
if (!in_array('actual_weight_gram', $orderCols)) {
    $ordersToAlter[] = "ADD COLUMN actual_weight_gram INT NOT NULL DEFAULT 500 AFTER declared_weight_gram";
}
if (!in_array('shipping_discrepancy_amount', $orderCols)) {
    $ordersToAlter[] = "ADD COLUMN shipping_discrepancy_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER actual_weight_gram";
}
if (!in_array('return_initiated_at', $orderCols)) {
    $ordersToAlter[] = "ADD COLUMN return_initiated_at DATETIME NULL AFTER shipping_discrepancy_amount";
}
if (!in_array('return_received_at', $orderCols)) {
    $ordersToAlter[] = "ADD COLUMN return_received_at DATETIME NULL AFTER return_initiated_at";
}
if (!in_array('return_claim_status', $orderCols)) {
    $ordersToAlter[] = "ADD COLUMN return_claim_status ENUM('NONE', 'MENUNGGU_FISIK', 'KLAIM_DIAJUKAN', 'DISETUJUI_GANTI_RUGI', 'DITOLAK', 'FISIK_DITERIMA') NOT NULL DEFAULT 'NONE' AFTER return_received_at";
}

if (!empty($ordersToAlter)) {
    $sql = "ALTER TABLE marketplace_orders " . implode(", ", $ordersToAlter);
    $pdo->exec($sql);
    echo "Added new columns to marketplace_orders.\n";
} else {
    echo "Columns in marketplace_orders already up to date.\n";
}

// 2. ALTER marketplace_product_ads
$adsCols = $pdo->query("DESCRIBE marketplace_product_ads")->fetchAll(PDO::FETCH_COLUMN);
$adsToAlter = [];

if (!in_array('ad_type', $adsCols)) {
    $adsToAlter[] = "ADD COLUMN ad_type ENUM('Produk', 'Grup_Toko', 'Discovery', 'Live_Affiliate') NOT NULL DEFAULT 'Produk' AFTER platform";
}
if (!in_array('campaign_name', $adsCols)) {
    $adsToAlter[] = "ADD COLUMN campaign_name VARCHAR(255) NULL AFTER ad_type";
}
if (!in_array('affiliate_fee', $adsCols)) {
    $adsToAlter[] = "ADD COLUMN affiliate_fee DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER platform_fee";
}
if (!in_array('ppn_rate', $adsCols)) {
    $adsToAlter[] = "ADD COLUMN ppn_rate DECIMAL(5,2) NOT NULL DEFAULT 11.00 AFTER affiliate_fee";
}
if (!in_array('is_bocor', $adsCols)) {
    $adsToAlter[] = "ADD COLUMN is_bocor TINYINT(1) NOT NULL DEFAULT 0 AFTER ppn_rate";
}
if (!in_array('bocor_reason', $adsCols)) {
    $adsToAlter[] = "ADD COLUMN bocor_reason VARCHAR(255) NULL AFTER is_bocor";
}

if (!empty($adsToAlter)) {
    $sql = "ALTER TABLE marketplace_product_ads " . implode(", ", $adsToAlter);
    $pdo->exec($sql);
    echo "Added new columns to marketplace_product_ads.\n";
} else {
    echo "Columns in marketplace_product_ads already up to date.\n";
}

// 3. CREATE TABLE marketplace_return_claims
$pdo->exec("CREATE TABLE IF NOT EXISTS marketplace_return_claims (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id VARCHAR(50) NOT NULL,
    order_id VARCHAR(100) NOT NULL,
    buyer_username VARCHAR(100) NULL,
    product_summary VARCHAR(255) NULL,
    courier_name VARCHAR(50) NOT NULL DEFAULT 'J&T Express',
    tracking_number VARCHAR(100) NULL,
    return_initiated_at DATETIME NOT NULL,
    days_pending INT NOT NULL DEFAULT 0,
    claim_status ENUM('MENUNGGU_FISIK', 'KLAIM_DIAJUKAN', 'DISETUJUI_GANTI_RUGI', 'DITOLAK', 'FISIK_DITERIMA') NOT NULL DEFAULT 'MENUNGGU_FISIK',
    claim_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    reimbursement_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    evidence_notes TEXT NULL,
    resolved_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    INDEX idx_store_status (store_id, claim_status),
    INDEX idx_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "Table marketplace_return_claims created or verified.\n";

// 4. CREATE TABLE marketplace_stock_allocations
$pdo->exec("CREATE TABLE IF NOT EXISTS marketplace_stock_allocations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    variant_id VARCHAR(60) NOT NULL,
    sku VARCHAR(100) NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    total_physical_stock INT NOT NULL DEFAULT 0,
    shopee_quota_pct DECIMAL(5,2) NOT NULL DEFAULT 60.00,
    tiktok_quota_pct DECIMAL(5,2) NOT NULL DEFAULT 30.00,
    offline_quota_pct DECIMAL(5,2) NOT NULL DEFAULT 10.00,
    shopee_allocated_qty INT NOT NULL DEFAULT 0,
    tiktok_allocated_qty INT NOT NULL DEFAULT 0,
    offline_allocated_qty INT NOT NULL DEFAULT 0,
    safety_buffer_threshold INT NOT NULL DEFAULT 3,
    is_safety_buffered TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('ACTIVE', 'BUFFERED_ZERO', 'OUT_OF_STOCK') NOT NULL DEFAULT 'ACTIVE',
    last_synced_at DATETIME NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uk_sku (sku),
    INDEX idx_variant (variant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "Table marketplace_stock_allocations created or verified.\n";

// 5. CREATE TABLE marketplace_price_simulations
$pdo->exec("CREATE TABLE IF NOT EXISTS marketplace_price_simulations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(255) NOT NULL,
    sku VARCHAR(100) NULL,
    cogs_hpp DECIMAL(15,2) NOT NULL,
    target_net_margin_pct DECIMAL(5,2) NOT NULL,
    admin_fee_pct DECIMAL(5,2) NOT NULL DEFAULT 11.00,
    free_shipping_xtra_pct DECIMAL(5,2) NOT NULL DEFAULT 4.00,
    payment_fee_pct DECIMAL(5,2) NOT NULL DEFAULT 1.00,
    ad_budget_pct DECIMAL(5,2) NOT NULL DEFAULT 10.00,
    simulated_normal_price DECIMAL(15,2) NOT NULL,
    simulated_flash_sale_price DECIMAL(15,2) NOT NULL,
    break_even_roas DECIMAL(6,2) NOT NULL,
    net_profit_per_pcs DECIMAL(15,2) NOT NULL,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "Table marketplace_price_simulations created or verified.\n";

// 6. CREATE TABLE marketplace_journal_entries
$pdo->exec("CREATE TABLE IF NOT EXISTS marketplace_journal_entries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    journal_no VARCHAR(50) NOT NULL,
    entry_date DATE NOT NULL,
    ref_type ENUM('ORDER_COMPLETED', 'ESCROW_PAYOUT', 'AD_EXPENSE', 'RETURN_LOSS', 'MANUAL') NOT NULL,
    ref_id VARCHAR(100) NOT NULL,
    store_id VARCHAR(50) NOT NULL,
    account_code VARCHAR(20) NOT NULL,
    account_name VARCHAR(100) NOT NULL,
    debit DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    credit DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    memo VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_journal (journal_no),
    INDEX idx_ref (ref_id),
    INDEX idx_store_date (store_id, entry_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "Table marketplace_journal_entries created or verified.\n";

echo "Database schema successfully updated!\n";

