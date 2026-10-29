<?php
require_once (dirname(__DIR__, 3) . '/app/Bootstrap/bootstrap.php');
Database::pdo()->exec("CREATE TABLE IF NOT EXISTS marketplace_sync_workers (
 store_id VARCHAR(50) NOT NULL, company_key VARCHAR(191) NOT NULL, launch_key VARCHAR(64) NOT NULL,
 status VARCHAR(20) NOT NULL, pid INT NULL, heartbeat_at DATETIME NOT NULL,
 started_at DATETIME NULL, finished_at DATETIME NULL, error_code VARCHAR(100) NULL,
 PRIMARY KEY(store_id,company_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "Background sync schema ready.\n";
