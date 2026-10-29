-- =============================================================================
-- JAGO LABA ERP - MARKETPLACE COMMERCE & FINANCIAL ENGINE DDL
-- Integrated with Konveksi Manufacturing COGS / HPP
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. MARKETPLACE STORES
CREATE TABLE IF NOT EXISTS `marketplace_stores` (
    `id` VARCHAR(50) NOT NULL PRIMARY KEY,
    `platform` ENUM('Shopee', 'TikTok Shop', 'Tokopedia', 'Lazada') NOT NULL,
    `store_name` VARCHAR(100) NOT NULL,
    `store_identifier` VARCHAR(100) NULL,
    `api_app_key` VARCHAR(255) NULL,
    `api_access_token` TEXT NULL,
    `token_expires_at` DATETIME NULL,
    `default_account_id` BIGINT UNSIGNED NULL,
    `sync_mode` ENUM('API', 'CSV_IMPORT') NOT NULL DEFAULT 'API',
    `total_transactions` INT NOT NULL DEFAULT 0,
    `last_synced_at` DATETIME NULL,
    `status` ENUM('Terhubung', 'Terputus') NOT NULL DEFAULT 'Terhubung',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_store_account` FOREIGN KEY (`default_account_id`) REFERENCES `accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. SKU MAPPINGS (Marketplace SKU -> Product Variant Konveksi)
CREATE TABLE IF NOT EXISTS `marketplace_sku_mappings` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `store_id` VARCHAR(50) NOT NULL,
    `marketplace_sku` VARCHAR(100) NOT NULL,
    `marketplace_product_name` VARCHAR(255) NOT NULL,
    `product_variant_id` VARCHAR(60) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_store_sku` (`store_id`, `marketplace_sku`),
    CONSTRAINT `fk_map_store` FOREIGN KEY (`store_id`) REFERENCES `marketplace_stores` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_map_variant` FOREIGN KEY (`product_variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. ORDERS & FINANCIAL SETTLEMENTS
CREATE TABLE IF NOT EXISTS `marketplace_orders` (
    `id` VARCHAR(100) NOT NULL PRIMARY KEY, -- Nomor Pesanan (Order SN)
    `store_id` VARCHAR(50) NOT NULL,
    `order_date` DATETIME NOT NULL,
    `completion_date` DATETIME NULL,
    `buyer_username` VARCHAR(100) NULL,
    `order_status` ENUM('Pending', 'Terkirim', 'Selesai', 'Dibatalkan', 'Retur') NOT NULL DEFAULT 'Selesai',
    
    -- Struktur Omzet & Potongan
    `gross_sales` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `seller_discount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `platform_admin_fee` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `service_fee` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `payment_fee` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `shipping_adjustment` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `other_deductions` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    
    -- Pencairan Bersih & HPP Konveksi
    `net_settlement_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total_cogs_hpp` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `net_margin_profit` DECIMAL(15,2) GENERATED ALWAYS AS (`net_settlement_amount` - `total_cogs_hpp`) STORED,
    
    `escrow_status` ENUM('Tertahan', 'Sudah Dicairkan') NOT NULL DEFAULT 'Tertahan',
    `payout_batch_id` VARCHAR(100) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_mord_date` (`order_date`),
    INDEX `idx_mord_store` (`store_id`, `order_status`),
    CONSTRAINT `fk_mord_store` FOREIGN KEY (`store_id`) REFERENCES `marketplace_stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. ORDER ITEMS
CREATE TABLE IF NOT EXISTS `marketplace_order_items` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` VARCHAR(100) NOT NULL,
    `product_variant_id` VARCHAR(60) NULL,
    `sku` VARCHAR(100) NOT NULL,
    `item_name` VARCHAR(255) NOT NULL,
    `qty` INT NOT NULL DEFAULT 1,
    `selling_price` DECIMAL(15,2) NOT NULL,
    `unit_cogs_hpp` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `subtotal_price` DECIMAL(15,2) NOT NULL,
    `subtotal_cogs` DECIMAL(15,2) NOT NULL,
    CONSTRAINT `fk_mitem_order` FOREIGN KEY (`order_id`) REFERENCES `marketplace_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. REKONSILIASI PENCAIRAN SALDO KE BANK
CREATE TABLE IF NOT EXISTS `marketplace_payout_reconciliations` (
    `id` VARCHAR(100) NOT NULL PRIMARY KEY,
    `store_id` VARCHAR(50) NOT NULL,
    `account_id` BIGINT UNSIGNED NOT NULL,
    `payout_date` DATE NOT NULL,
    `total_payout_amount` DECIMAL(15,2) NOT NULL,
    `bank_cash_transaction_id` BIGINT UNSIGNED NULL,
    `matched_orders_count` INT NOT NULL DEFAULT 0,
    `status` ENUM('Matched', 'Discrepancy', 'Pending') NOT NULL DEFAULT 'Matched',
    `notes` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_payout_store` FOREIGN KEY (`store_id`) REFERENCES `marketplace_stores` (`id`),
    CONSTRAINT `fk_payout_acc` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. BIAYA IKLAN / ADS TRACKING (Shopee Ads, TikTok Ads)
CREATE TABLE IF NOT EXISTS `marketplace_ad_spends` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `store_id` VARCHAR(50) NOT NULL,
    `campaign_date` DATE NOT NULL,
    `campaign_name` VARCHAR(150) NOT NULL,
    `spend_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `gross_sales_generated` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `roas` DECIMAL(8,2) GENERATED ALWAYS AS (
        CASE WHEN `spend_amount` > 0 THEN `gross_sales_generated` / `spend_amount` ELSE 0 END
    ) STORED,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_ads_store_date` (`store_id`, `campaign_date`),
    CONSTRAINT `fk_ads_store` FOREIGN KEY (`store_id`) REFERENCES `marketplace_stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

