<?php
declare(strict_types=1);

/**
 * Additive marketplace scope metadata.
 *
 * Legacy marketplace rows are retained. Existing stores are assigned to the
 * current local company boundary and deliberately remain `pending`; the old
 * `Terhubung` flag is not evidence of a verified platform connection.
 */
require_once (dirname(__DIR__, 3) . '/app/Bootstrap/bootstrap.php');

$pdo = Database::pdo();

$columns = [];
foreach ($pdo->query('DESCRIBE marketplace_stores')->fetchAll(PDO::FETCH_ASSOC) as $column) {
    $columns[(string)$column['Field']] = true;
}

$additions = [];
if (!isset($columns['company_key'])) {
    $additions[] = "ADD COLUMN company_key VARCHAR(120) NOT NULL DEFAULT 'LOCAL-COMPANY' AFTER id";
}
if (!isset($columns['authorization_status'])) {
    $additions[] = "ADD COLUMN authorization_status ENUM('pending','verified','reauthorize','disconnected') NOT NULL DEFAULT 'pending' AFTER status";
}
if (!isset($columns['last_successful_sync_at'])) {
    $additions[] = 'ADD COLUMN last_successful_sync_at DATETIME NULL AFTER last_synced_at';
}
if (!isset($columns['last_error_code'])) {
    $additions[] = 'ADD COLUMN last_error_code VARCHAR(100) NULL AFTER last_successful_sync_at';
}
if ($additions) {
    $pdo->exec('ALTER TABLE marketplace_stores ' . implode(', ', $additions));
}

$orderColumns = [];
foreach ($pdo->query('DESCRIBE marketplace_orders')->fetchAll(PDO::FETCH_ASSOC) as $column) {
    $orderColumns[(string)$column['Field']] = true;
}
$orderAdditions = [];
if (!isset($orderColumns['internal_state'])) {
    $orderAdditions[] = "ADD COLUMN internal_state ENUM('active','archived') NOT NULL DEFAULT 'active' AFTER created_at";
}
if (!isset($orderColumns['archived_at'])) {
    $orderAdditions[] = 'ADD COLUMN archived_at DATETIME NULL AFTER internal_state';
}
if (!isset($orderColumns['archived_by'])) {
    $orderAdditions[] = 'ADD COLUMN archived_by VARCHAR(120) NULL AFTER archived_at';
}
if (!isset($orderColumns['archive_reason'])) {
    $orderAdditions[] = 'ADD COLUMN archive_reason VARCHAR(255) NULL AFTER archived_by';
}
if (!isset($orderColumns['source_kind'])) {
    $orderAdditions[] = "ADD COLUMN source_kind ENUM('api','import','manual') NOT NULL DEFAULT 'api' AFTER archive_reason";
}
if ($orderAdditions) {
    $pdo->exec('ALTER TABLE marketplace_orders ' . implode(', ', $orderAdditions));
}

$pdo->exec("UPDATE marketplace_stores
    SET company_key = COALESCE(NULLIF(company_key, ''), 'LOCAL-COMPANY'),
        authorization_status = CASE
            WHEN authorization_status = 'verified' AND last_successful_sync_at IS NOT NULL THEN 'verified'
            WHEN status = 'Terputus' THEN 'disconnected'
            ELSE 'pending'
        END
    WHERE company_key = '' OR company_key IS NULL OR authorization_status IS NULL OR authorization_status = 'verified'");

$indexes = $pdo->query("SHOW INDEX FROM marketplace_stores WHERE Key_name = 'idx_marketplace_store_company'")->fetchAll(PDO::FETCH_ASSOC);
if (!$indexes) {
    $pdo->exec('ALTER TABLE marketplace_stores ADD INDEX idx_marketplace_store_company (company_key, platform, status)');
}

$pdo->exec("CREATE TABLE IF NOT EXISTS marketplace_hpp_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id VARCHAR(50) NOT NULL,
    marketplace_sku VARCHAR(100) NOT NULL,
    product_variant_id VARCHAR(60) NULL,
    unit_hpp DECIMAL(15,2) NOT NULL,
    effective_from DATE NOT NULL,
    source VARCHAR(80) NOT NULL DEFAULT 'MANUAL',
    reason VARCHAR(255) NULL,
    changed_by VARCHAR(120) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_marketplace_hpp_effective (store_id, marketplace_sku, effective_from),
    KEY idx_marketplace_hpp_lookup (store_id, marketplace_sku, effective_from),
    CONSTRAINT fk_marketplace_hpp_store FOREIGN KEY (store_id) REFERENCES marketplace_stores (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

echo "Marketplace scope migration selesai. Legacy data dipertahankan dan status koneksi tetap menunggu verifikasi resmi.\n";
