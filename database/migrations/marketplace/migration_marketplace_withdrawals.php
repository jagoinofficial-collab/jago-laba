<?php
require_once (dirname(__DIR__, 3) . '/app/Bootstrap/bootstrap.php');
Database::pdo()->exec("CREATE TABLE IF NOT EXISTS marketplace_withdrawals (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, company_key VARCHAR(120) NOT NULL, store_id VARCHAR(120) NOT NULL,
 external_id VARCHAR(120) NOT NULL, withdrawal_type VARCHAR(30) NULL, requested_at DATETIME NOT NULL, completed_at DATETIME NULL,
 amount DECIMAL(18,2) NOT NULL, fee DECIMAL(18,2) NULL, net_amount DECIMAL(18,2) NULL, currency CHAR(3) NOT NULL,
 source_status VARCHAR(100) NOT NULL, normalized_status VARCHAR(20) NOT NULL, bank_name VARCHAR(100) NULL, account_mask VARCHAR(30) NULL,
 failure_reason VARCHAR(500) NULL, source_kind VARCHAR(20) NOT NULL DEFAULT 'import', source_hash CHAR(64) NOT NULL, row_hash CHAR(64) NOT NULL,
 imported_by VARCHAR(120) NOT NULL, imported_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
 UNIQUE KEY withdrawal_identity(company_key,store_id,external_id), KEY withdrawal_period(company_key,requested_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
Database::pdo()->exec("CREATE TABLE IF NOT EXISTS marketplace_withdrawal_audit (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, company_key VARCHAR(120) NOT NULL, store_id VARCHAR(120) NOT NULL, external_id VARCHAR(120) NOT NULL,
 actor VARCHAR(120) NOT NULL, previous_hash CHAR(64) NULL, new_hash CHAR(64) NOT NULL, source_hash CHAR(64) NOT NULL, created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
foreach(['marketplace_withdrawals','marketplace_withdrawal_audit'] as $t)Database::pdo()->exec("ALTER TABLE `$t` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
echo "Withdrawal import schema ready.\n";
