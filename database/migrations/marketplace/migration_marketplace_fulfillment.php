<?php
require (dirname(__DIR__, 3) . '/app/Bootstrap/bootstrap.php');
Database::pdo()->exec("CREATE TABLE IF NOT EXISTS marketplace_fulfillment_actions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 company_key VARCHAR(120) NOT NULL,store_id VARCHAR(120) NOT NULL,package_id VARCHAR(120) NOT NULL,
 order_id VARCHAR(120) NOT NULL,user_key VARCHAR(120) NOT NULL,
 state VARCHAR(30) NOT NULL,offer_hash CHAR(64) NULL,offer_json TEXT NULL,offer_expires_at DATETIME NULL,
 handover_method VARCHAR(20) NULL,last_error VARCHAR(80) NULL,requested_at DATETIME NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY fulfillment_package(company_key,store_id,package_id)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "Fulfillment state schema ready.\n";
