<?php
require_once (dirname(__DIR__, 3) . '/app/Bootstrap/bootstrap.php');$pdo=Database::pdo();
$columns=$pdo->query('SHOW COLUMNS FROM marketplace_catalog_variants')->fetchAll(PDO::FETCH_COLUMN);
if(!in_array('base_price',$columns,true))$pdo->exec('ALTER TABLE marketplace_catalog_variants ADD base_price DECIMAL(15,2) NULL');
$pdo->exec("CREATE TABLE IF NOT EXISTS marketplace_price_actions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,store_id VARCHAR(50) NOT NULL,product_id VARCHAR(100) NOT NULL,
 operation_key VARCHAR(64) NOT NULL,mode VARCHAR(20) NOT NULL,request_hash VARCHAR(64) NOT NULL,payload_json LONGTEXT NOT NULL,
 status VARCHAR(30) NOT NULL,remote_activity_id VARCHAR(100) NULL,result_json LONGTEXT NULL,error_code VARCHAR(100) NULL,
 changed_by VARCHAR(120) NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY operation_identity(store_id,operation_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "Marketplace price editor schema ready.\n";
