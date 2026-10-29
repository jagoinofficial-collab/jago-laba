<?php
require_once (dirname(__DIR__, 3) . '/app/Bootstrap/bootstrap.php');
Database::pdo()->exec("CREATE TABLE IF NOT EXISTS marketplace_catalog_variants (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, store_id VARCHAR(50) NOT NULL,
 external_product_id VARCHAR(100) NOT NULL, external_variant_id VARCHAR(100) NOT NULL,
 product_name VARCHAR(255) NOT NULL, parent_sku VARCHAR(100) NULL, sku VARCHAR(100) NOT NULL,
 variant_name VARCHAR(255) NULL, image_url TEXT NULL,
 price DECIMAL(15,2) NULL, original_price DECIMAL(15,2) NULL, stock_qty DECIMAL(15,3) NULL,
 catalog_visible TINYINT NOT NULL DEFAULT 1, source_status VARCHAR(80) NULL, source_updated_at DATETIME NOT NULL,
 UNIQUE KEY catalog_identity(store_id,external_product_id,external_variant_id), INDEX catalog_sku(store_id,sku)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
Database::pdo()->exec("CREATE TABLE IF NOT EXISTS marketplace_hpp_changes (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, store_id VARCHAR(50) NOT NULL, sku VARCHAR(100) NOT NULL,
 old_hpp DECIMAL(15,2) NULL, new_hpp DECIMAL(15,2) NOT NULL, changed_by VARCHAR(120) NOT NULL,
 affected_orders INT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "Marketplace catalog schema ready.\n";
