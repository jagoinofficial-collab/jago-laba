-- REVIEW ONLY. Do not run on operational data before approval and backup.
-- This DDL creates empty target tables. It does not classify or move old rows.
CREATE TABLE IF NOT EXISTS workspace_user_access (
    user_key VARCHAR(120) NOT NULL,
    workspace_code ENUM('konveksi','marketplace') NOT NULL,
    is_allowed TINYINT(1) NOT NULL DEFAULT 1,
    granted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_key, workspace_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS marketplace_accounts_v2 (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id VARCHAR(80) NOT NULL,
    code VARCHAR(80) NOT NULL,
    name VARCHAR(190) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'IDR',
    opening_balance DECIMAL(18,2) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_marketplace_account_code (store_id, code),
    KEY idx_marketplace_account_store (store_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS marketplace_cash_transactions_v2 (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id VARCHAR(80) NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    transaction_date DATETIME NOT NULL,
    direction ENUM('masuk','keluar') NOT NULL,
    amount DECIMAL(18,2) NOT NULL,
    reference_type VARCHAR(80) NOT NULL,
    reference_id VARCHAR(190) NOT NULL,
    description TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_marketplace_cash_reference (store_id, reference_type, reference_id, direction),
    KEY idx_marketplace_cash_store_date (store_id, transaction_date),
    CONSTRAINT fk_marketplace_cash_account FOREIGN KEY (account_id) REFERENCES marketplace_accounts_v2(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS marketplace_products_v2 (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id VARCHAR(80) NOT NULL,
    sku VARCHAR(190) NOT NULL,
    name VARCHAR(255) NOT NULL,
    unit_hpp DECIMAL(18,2) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_marketplace_sku (store_id, sku)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS marketplace_stock_movements_v2 (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id VARCHAR(80) NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    movement_at DATETIME NOT NULL,
    qty_delta DECIMAL(18,3) NOT NULL,
    reference_type VARCHAR(80) NOT NULL,
    reference_id VARCHAR(190) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_marketplace_stock_reference (store_id, product_id, reference_type, reference_id),
    KEY idx_marketplace_stock_store_time (store_id, movement_at),
    CONSTRAINT fk_marketplace_stock_product FOREIGN KEY (product_id) REFERENCES marketplace_products_v2(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
