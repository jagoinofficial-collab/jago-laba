<?php
require_once (dirname(__DIR__, 3) . '/app/Bootstrap/bootstrap.php');
$pdo = Database::pdo();

echo "=== MIGRATION: SETTINGS AND MULTI-STORE ===\n";

try {
    // 1. Modifikasi / Perluas kolom pada marketplace_stores
    $alterQueries = [
        "ALTER TABLE marketplace_stores MODIFY COLUMN platform VARCHAR(50) NOT NULL DEFAULT 'Shopee'",
        "ALTER TABLE marketplace_stores MODIFY COLUMN sync_mode VARCHAR(50) NOT NULL DEFAULT 'API'",
        "ALTER TABLE marketplace_stores MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'Terhubung'",
        "ALTER TABLE marketplace_stores ADD COLUMN IF NOT EXISTS default_admin_fee_pct DECIMAL(5,2) NOT NULL DEFAULT 11.00 AFTER default_account_id",
        "ALTER TABLE marketplace_stores ADD COLUMN IF NOT EXISTS default_free_shipping_pct DECIMAL(5,2) NOT NULL DEFAULT 4.00 AFTER default_admin_fee_pct",
        "ALTER TABLE marketplace_stores ADD COLUMN IF NOT EXISTS default_payment_fee_pct DECIMAL(5,2) NOT NULL DEFAULT 1.00 AFTER default_free_shipping_pct",
        "ALTER TABLE marketplace_stores ADD COLUMN IF NOT EXISTS updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at"
    ];

    foreach ($alterQueries as $q) {
        try {
            $pdo->exec($q);
            echo "[OK] " . substr($q, 0, 70) . "...\n";
        } catch (Exception $e) {
            echo "[SKIP/WARN] " . $e->getMessage() . "\n";
        }
    }

    // 2. Buat tabel marketplace_app_settings jika belum ada
    $pdo->exec("CREATE TABLE IF NOT EXISTS marketplace_app_settings (
        setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
        setting_value TEXT NULL,
        setting_group VARCHAR(50) NOT NULL DEFAULT 'financial',
        description VARCHAR(255) NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "[OK] Created table marketplace_app_settings\n";

    // 3. Seed default configurations
    $defaultSettings = [
        // Financial Settings
        ['default_admin_fee_pct', '11.0', 'financial', 'Persentase biaya komisi standar Star Seller / TikTok Mall (%)'],
        ['default_free_shipping_pct', '4.0', 'financial', 'Persentase program Gratis Ongkir Xtra / Cashback Xtra (%)'],
        ['default_payment_fee_pct', '1.0', 'financial', 'Persentase biaya penanganan transaksi / payment fee (%)'],
        ['shipping_discrepancy_tolerance_gram', '100', 'financial', 'Toleransi selisih timbangan kurir sebelum memicu peringatan (gram)'],
        ['lost_return_days_threshold', '14', 'financial', 'Ambang batas hari paket retur belum tiba fisik untuk diajukan klaim (hari)'],
        ['journal_escrow_account_code', '1103', 'financial', 'Kode Akun COA Piutang Escrow Marketplace'],
        ['journal_sales_account_code', '4101', 'financial', 'Kode Akun COA Pendapatan Penjualan Marketplace'],
        ['journal_cogs_account_code', '5101', 'financial', 'Kode Akun COA Beban Pokok Penjualan (HPP Konveksi)'],
        ['journal_platform_fee_code', '6101', 'financial', 'Kode Akun COA Beban Komisi & Administrasi Platform'],
        ['journal_ad_spend_code', '6102', 'financial', 'Kode Akun COA Beban Iklan Berbayar Marketplace'],

        // Inventory Settings
        ['default_safety_buffer_threshold', '3', 'inventory', 'Batas minimal stok fisik pabrik sebelum kuota marketplace otomatis di-0-kan (pcs)'],
        ['default_stock_allocation_shopee', '60', 'inventory', 'Alokasi default kuota penjualan Shopee (%)'],
        ['default_stock_allocation_tiktok', '30', 'inventory', 'Alokasi default kuota penjualan TikTok Shop (%)'],
        ['default_stock_allocation_offline', '10', 'inventory', 'Alokasi default kuota grosir offline pabrik (%)'],
        ['auto_zero_on_safety_buffer', '1', 'inventory', 'Otomatis ubah stok marketplace menjadi 0 saat stok fisik menyentuh batas aman (1=Ya, 0=Tidak)'],

        // Marketing Settings
        ['bocor_ad_spend_threshold', '100000', 'marketing', 'Ambang batas biaya iklan 24 jam dengan 0 order untuk deteksi bocor (Rp)'],
        ['bocor_min_roas_multiplier', '1.8', 'marketing', 'Batas minimal ROAS untuk peringatan dini iklan performa rendah'],
        ['ppn_tax_rate', '11.0', 'marketing', 'Tarif Pajak Pertambahan Nilai (PPN) tagihan iklan (%)'],
        ['auto_pause_bocor_ads', '0', 'marketing', 'Otomatis kirim perintah pause bid iklan bocor via API (1=Ya, 0=Tidak)'],

        // Sync & Cron Settings
        ['cron_order_sync_interval_min', '15', 'sync', 'Interval pengecekan status pesanan dan auto-deduct stok (menit)'],
        ['oauth_token_warn_days', '7', 'sync', 'Batas hari pengingat masa kedaluwarsa token OAuth marketplace (hari)'],
        ['cron_auto_refresh_token', '1', 'sync', 'Otomatis refresh token OAuth di background saat masa berlaku < 30 hari (1=Ya, 0=Tidak)'],
        ['webhook_global_secret', 'JAGOLABA_MKT_WEBHOOK_SECRET_KEY_2026', 'sync', 'Kunci rahasia global verifikasi webhook Shopee & TikTok Partner']
    ];

    $stmtInsert = $pdo->prepare("INSERT INTO marketplace_app_settings 
        (setting_key, setting_value, setting_group, description, updated_at)
        VALUES (?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
            setting_group = VALUES(setting_group),
            description = VALUES(description)");

    foreach ($defaultSettings as $ds) {
        $stmtInsert->execute($ds);
    }
    echo "[OK] Seeded default app settings (" . count($defaultSettings) . " keys)\n";

    // 4. Update toko yang sudah ada agar memiliki fee default
    $pdo->exec("UPDATE marketplace_stores 
        SET default_admin_fee_pct = 11.00,
            default_free_shipping_pct = 4.00,
            default_payment_fee_pct = 1.00
        WHERE default_admin_fee_pct IS NULL OR default_admin_fee_pct = 0");
    echo "[OK] Updated existing stores with default fee percentages\n";

    echo "=== MIGRATION COMPLETED SUCCESSFULLY ===\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

