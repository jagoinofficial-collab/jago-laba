<?php
require_once (dirname(__DIR__, 3) . '/app/Bootstrap/bootstrap.php');
Database::pdo()->exec("CREATE TABLE IF NOT EXISTS marketplace_auto_sync (
 store_id VARCHAR(50) NOT NULL, company_key VARCHAR(191) NOT NULL, mode VARCHAR(20) NOT NULL,
 enabled TINYINT NOT NULL DEFAULT 0, next_run_at DATETIME NULL, last_success_at DATETIME NULL,
 last_error_code VARCHAR(100) NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(store_id,company_key,mode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
Database::pdo()->exec("CREATE TABLE IF NOT EXISTS marketplace_auto_sync_runtime (
 id TINYINT PRIMARY KEY, heartbeat_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "Auto sync schema ready.\n";
