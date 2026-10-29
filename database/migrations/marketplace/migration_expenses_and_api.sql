-- =============================================================================
-- JAGO LABA ERP - EXPENSES & OFFICIAL API CREDENTIALS DDL
-- For Shopee Open Platform & TikTok Shop Partner API Integration
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. PENGELUARAN MARKETPLACE (OPERASIONAL & IKLAN)
CREATE TABLE IF NOT EXISTS `marketplace_expenses` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `store_id` VARCHAR(50) NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `vendor_name` VARCHAR(150) NULL,
    `expense_date` DATE NOT NULL,
    `category` VARCHAR(100) NOT NULL DEFAULT 'Biaya Iklan/Advertising Marketplace',
    `amount` DECIMAL(15,2) NOT NULL,
    `payment_method` VARCHAR(50) NOT NULL DEFAULT 'Transfer Bank',
    `account_id` BIGINT UNSIGNED NULL,
    `notes` TEXT NULL,
    `affects_profit` TINYINT(1) NOT NULL DEFAULT 1,
    `is_cashflow_only` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_exp_store_date` (`store_id`, `expense_date`),
    CONSTRAINT `fk_exp_store` FOREIGN KEY (`store_id`) REFERENCES `marketplace_stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. KREDENSIAL API RESMI (SHOPEE OPEN PLATFORM & TIKTOK PARTNER)
CREATE TABLE IF NOT EXISTS `marketplace_api_credentials` (
    `id` VARCHAR(50) NOT NULL PRIMARY KEY,
    `store_id` VARCHAR(50) NOT NULL UNIQUE,
    `platform` ENUM('Shopee', 'TikTok Shop') NOT NULL,
    
    -- Shopee Open Platform v2
    `partner_id` VARCHAR(100) NULL,
    `partner_key` VARCHAR(255) NULL,
    `shop_id` VARCHAR(100) NULL,
    
    -- TikTok Shop Partner Open API
    `app_key` VARCHAR(100) NULL,
    `app_secret` VARCHAR(255) NULL,
    `access_token` TEXT NULL,
    `refresh_token` TEXT NULL,
    `token_expires_at` DATETIME NULL,
    `cipher` VARCHAR(255) NULL,
    
    -- Webhook & Status
    `webhook_url` VARCHAR(255) NULL,
    `webhook_secret` VARCHAR(255) NULL,
    `connection_status` ENUM('Terhubung', 'Perlu Otorisasi', 'Token Expired', 'Error') NOT NULL DEFAULT 'Terhubung',
    `last_ping_at` DATETIME NULL,
    `last_sync_orders_at` DATETIME NULL,
    `last_sync_ads_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_cred_store` FOREIGN KEY (`store_id`) REFERENCES `marketplace_stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

