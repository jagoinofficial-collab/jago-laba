<?php
declare(strict_types=1);
require_once (dirname(__DIR__, 2) . '/app/Bootstrap/bootstrap.php');
MarketplaceModule::boot();

$testName = 'jl_store_archive_test_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3));
Database::serverPdo()->exec("CREATE DATABASE `$testName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$config = Database::config();
$config['db']['name'] = $testName;
Database::setConfig($config);
Database::reset();
$pdo = Database::pdo();
$pdo->exec("CREATE TABLE marketplace_stores (
    id VARCHAR(50) PRIMARY KEY, company_key VARCHAR(120) NOT NULL, platform VARCHAR(40) NOT NULL,
    store_name VARCHAR(100) NOT NULL, store_identifier VARCHAR(100) NULL,
    api_app_key VARCHAR(255) NULL, api_access_token TEXT NULL, status VARCHAR(40) NOT NULL,
    authorization_status VARCHAR(40) NOT NULL, last_successful_sync_at DATETIME NULL,
    last_error_code VARCHAR(100) NULL, updated_at DATETIME NULL
) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE marketplace_oauth_connections (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, company_key VARCHAR(120) NOT NULL,
    store_id VARCHAR(50) NOT NULL, external_store_id VARCHAR(100) NOT NULL,
    external_store_name VARCHAR(100) NOT NULL, status VARCHAR(40) NOT NULL,
    access_token_encrypted TEXT NULL, refresh_token_encrypted TEXT NULL,
    token_expires_at DATETIME NULL, refresh_token_expires_at DATETIME NULL,
    authorized_at DATETIME NULL, verified_at DATETIME NULL,
    last_successful_sync_at DATETIME NULL, last_sync_attempt_at DATETIME NULL,
    last_error_code VARCHAR(100) NULL, updated_at DATETIME NULL
) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE marketplace_sync_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, connection_id BIGINT UNSIGNED NOT NULL,
    store_id VARCHAR(50) NOT NULL, company_key VARCHAR(120) NOT NULL,
    status VARCHAR(20) NOT NULL, finished_at DATETIME NULL, last_error_code VARCHAR(100) NULL
) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE marketplace_api_credentials (
    store_id VARCHAR(50) PRIMARY KEY, access_token TEXT NULL, refresh_token TEXT NULL,
    partner_key TEXT NULL, app_secret TEXT NULL, webhook_secret TEXT NULL,
    connection_status VARCHAR(40) NOT NULL, updated_at DATETIME NULL
) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE marketplace_orders (id VARCHAR(100) PRIMARY KEY, store_id VARCHAR(50) NOT NULL, gross_sales DECIMAL(18,2) NOT NULL DEFAULT 0, net_settlement_amount DECIMAL(18,2) NOT NULL DEFAULT 0) ENGINE=InnoDB");
$pdo->exec("INSERT INTO marketplace_stores (id,company_key,platform,store_name,status,authorization_status,api_access_token) VALUES
    ('A-1','COMPANY-A','Shopee','Toko A','Terhubung','verified','OLD-TOKEN'),
    ('B-1','COMPANY-B','Shopee','Toko B','Terhubung','verified','OTHER-TOKEN')");
$pdo->exec("INSERT INTO marketplace_oauth_connections (id,company_key,store_id,external_store_id,external_store_name,status,access_token_encrypted,refresh_token_encrypted) VALUES (1,'COMPANY-A','A-1','EXT-A','Toko A','verified','ENC-ACCESS','ENC-REFRESH')");
$pdo->exec("INSERT INTO marketplace_sync_jobs (connection_id,store_id,company_key,status) VALUES (1,'A-1','COMPANY-A','queued')");
$pdo->exec("INSERT INTO marketplace_api_credentials (store_id,access_token,connection_status) VALUES ('A-1','LEGACY-TOKEN','Terhubung')");
$pdo->exec("INSERT INTO marketplace_orders (id,store_id,gross_sales) VALUES ('ORDER-A','A-1',100)");

require (dirname(__DIR__, 2) . '/database/migrations/marketplace/migration_marketplace_store_archive.php');
require (dirname(__DIR__, 2) . '/database/migrations/marketplace/migration_marketplace_store_archive.php');
if ((int)$pdo->query('SELECT COUNT(*) FROM marketplace_stores WHERE archived_at IS NOT NULL')->fetchColumn() !== 0) throw new RuntimeException('Migrasi mengarsipkan data lama.');

$_SESSION['user_id'] = 'USER-OWNER';
$_SESSION['role'] = 'OWNER';
$_SESSION['company_key'] = 'COMPANY-A';
$service = new MarketplaceService($pdo);
try { $service->archiveStore('B-1'); throw new RuntimeException('Toko perusahaan lain dapat diarsipkan.'); }
catch (DomainException $expected) {}
$_SESSION['role'] = 'STAFF';
try { $service->archiveStore('A-1'); throw new RuntimeException('Staf dapat mengarsipkan toko.'); }
catch (DomainException $expected) {}
$_SESSION['role'] = 'OWNER';
$result = $service->archiveStore('A-1');
if (!$result['success'] || $service->getStores() !== [] || count($service->getArchivedStores()) !== 1) throw new RuntimeException('Toko masih ada di daftar aktif.');
try { $service->assertStoreAccess('A-1'); throw new RuntimeException('URL toko arsip masih dapat diakses.'); }
catch (DomainException $expected) {}
if ((int)$pdo->query("SELECT COUNT(*) FROM marketplace_orders WHERE id='ORDER-A'")->fetchColumn() !== 1) throw new RuntimeException('Riwayat pesanan hilang.');
if ($pdo->query("SELECT access_token_encrypted FROM marketplace_oauth_connections WHERE store_id='A-1'")->fetchColumn() !== null) throw new RuntimeException('Token OAuth belum dibersihkan.');
if ($pdo->query("SELECT status FROM marketplace_sync_jobs WHERE store_id='A-1'")->fetchColumn() !== 'cancelled') throw new RuntimeException('Pekerjaan sinkronisasi masih aktif.');
if ($pdo->query("SELECT api_access_token FROM marketplace_stores WHERE id='B-1'")->fetchColumn() !== 'OTHER-TOKEN') throw new RuntimeException('Toko perusahaan lain berubah.');
$service->restoreStore('A-1');
if (count($service->getStores()) !== 1 || $service->getArchivedStores() !== []) throw new RuntimeException('Pemulihan toko gagal.');
if ($pdo->query("SELECT authorization_status FROM marketplace_stores WHERE id='A-1'")->fetchColumn() !== 'disconnected') throw new RuntimeException('Toko dipulihkan seolah terhubung.');
echo "TEST_DATABASE=$testName\nPASS: migrasi idempoten; arsip hanya oleh owner perusahaan, data lama tetap, akses URL diblokir, token dibersihkan, pekerjaan dibatalkan, pemulihan tetap terputus.\n";
