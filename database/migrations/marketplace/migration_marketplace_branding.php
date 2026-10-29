<?php
require (dirname(__DIR__, 3) . '/app/Bootstrap/bootstrap.php');
Database::pdo()->exec("CREATE TABLE IF NOT EXISTS marketplace_branding(company_key VARCHAR(120) NOT NULL PRIMARY KEY,logo_blob MEDIUMBLOB NULL,logo_mime VARCHAR(40) NULL,logo_size SMALLINT NOT NULL DEFAULT 32,logo_fit VARCHAR(12) NOT NULL DEFAULT 'contain',logo_hash VARCHAR(64) NULL,updated_by VARCHAR(120) NOT NULL,updated_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "Marketplace branding schema ready.\n";
