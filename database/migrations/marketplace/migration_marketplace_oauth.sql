-- Additive OAuth storage for official Shopee and TikTok Shop seller connections.
-- No legacy credentials or transaction history are modified by this migration.

CREATE TABLE IF NOT EXISTS marketplace_partner_settings (
    platform VARCHAR(40) NOT NULL,
    settings_encrypted MEDIUMTEXT NOT NULL,
    last_test_status VARCHAR(20) NULL,
    last_tested_at DATETIME NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (platform)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS marketplace_oauth_states (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    state_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    company_key VARCHAR(191) NOT NULL,
    user_key VARCHAR(191) NOT NULL,
    platform VARCHAR(40) NOT NULL,
    return_path VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    consumed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_marketplace_oauth_state_hash (state_hash),
    KEY idx_marketplace_oauth_state_lookup (company_key, user_key, platform, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS marketplace_oauth_pending (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pending_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    company_key VARCHAR(191) NOT NULL,
    user_key VARCHAR(191) NOT NULL,
    platform VARCHAR(40) NOT NULL,
    access_token_encrypted MEDIUMTEXT NOT NULL,
    refresh_token_encrypted MEDIUMTEXT NULL,
    token_expires_at DATETIME NULL,
    refresh_token_expires_at DATETIME NULL,
    granted_scopes TEXT NULL,
    shops_json MEDIUMTEXT NOT NULL,
    return_path VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    consumed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_marketplace_oauth_pending_hash (pending_hash),
    KEY idx_marketplace_oauth_pending_lookup (company_key, user_key, platform, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS marketplace_oauth_connections (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_key VARCHAR(191) NOT NULL,
    store_id VARCHAR(50) NOT NULL,
    platform VARCHAR(40) NOT NULL,
    external_store_id VARCHAR(191) NOT NULL,
    external_shop_cipher VARCHAR(191) NULL,
    external_store_name VARCHAR(191) NOT NULL,
    access_token_encrypted MEDIUMTEXT NOT NULL,
    refresh_token_encrypted MEDIUMTEXT NULL,
    token_expires_at DATETIME NULL,
    refresh_token_expires_at DATETIME NULL,
    granted_scopes TEXT NULL,
    authorized_at DATETIME NOT NULL,
    verified_at DATETIME NOT NULL,
    last_successful_sync_at DATETIME NULL,
    last_sync_attempt_at DATETIME NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'verified',
    last_error_code VARCHAR(100) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_marketplace_oauth_connection_store (store_id),
    UNIQUE KEY uq_marketplace_oauth_connection_external (platform, external_store_id),
    KEY idx_marketplace_oauth_connection_company (company_key, platform, status),
    CONSTRAINT fk_marketplace_oauth_connection_store
        FOREIGN KEY (store_id) REFERENCES marketplace_stores (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS marketplace_sync_jobs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    connection_id BIGINT UNSIGNED NOT NULL,
    company_key VARCHAR(191) NOT NULL,
    store_id VARCHAR(50) NOT NULL,
    platform VARCHAR(40) NOT NULL,
    job_type VARCHAR(30) NOT NULL,
    run_key VARCHAR(100) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'queued',
    checkpoint TEXT NULL,
    success_count INT UNSIGNED NOT NULL DEFAULT 0,
    failure_count INT UNSIGNED NOT NULL DEFAULT 0,
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    started_at DATETIME NULL,
    finished_at DATETIME NULL,
    last_error_code VARCHAR(100) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_marketplace_sync_job_run (connection_id, job_type, run_key),
    KEY idx_marketplace_sync_job_queue (status, requested_at),
    KEY idx_marketplace_sync_job_store (company_key, store_id, status),
    CONSTRAINT fk_marketplace_sync_job_connection
        FOREIGN KEY (connection_id) REFERENCES marketplace_oauth_connections (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
