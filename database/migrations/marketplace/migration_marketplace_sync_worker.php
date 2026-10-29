<?php
require_once (dirname(__DIR__, 3) . '/app/Bootstrap/bootstrap.php');
$pdo=Database::pdo();$columns=$pdo->query('SHOW COLUMNS FROM marketplace_sync_jobs')->fetchAll(PDO::FETCH_COLUMN);
if(!in_array('lease_token',$columns,true))$pdo->exec('ALTER TABLE marketplace_sync_jobs ADD lease_token VARCHAR(64) NULL, ADD lease_until DATETIME NULL');
$pdo->exec("CREATE TABLE IF NOT EXISTS marketplace_sync_records (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,store_id VARCHAR(50) NOT NULL,resource_type VARCHAR(30) NOT NULL,
 external_id VARCHAR(150) NOT NULL,payload_json LONGTEXT NOT NULL,source_updated_at DATETIME NULL,
 fetched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY resource_identity(store_id,resource_type,external_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "Sync worker schema ready.\n";
