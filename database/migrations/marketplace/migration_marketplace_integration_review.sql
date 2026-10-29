-- REVIEW ONLY: additive empty tables. Run in a test database first.
-- No legacy row is classified, copied, changed or removed by this file.
CREATE TABLE IF NOT EXISTS marketplace_connected_stores (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_key VARCHAR(120) NOT NULL,
    platform ENUM('Shopee','TikTok Shop') NOT NULL,
    external_store_id VARCHAR(190) NOT NULL,
    display_name VARCHAR(190) NOT NULL,
    authorization_status ENUM('pending','verified','reauthorize','disconnected') NOT NULL DEFAULT 'pending',
    granted_scopes JSON NULL,
    token_expires_at DATETIME NULL,
    last_successful_sync_at DATETIME NULL,
    last_error_code VARCHAR(100) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_company_store (company_key,platform,external_store_id),
    -- Claiming one official shop for multiple companies needs an explicit delegation feature.
    UNIQUE KEY uq_global_store (platform,external_store_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS marketplace_oauth_attempts (
    state_hash CHAR(64) PRIMARY KEY,
    company_key VARCHAR(120) NOT NULL,
    user_key VARCHAR(120) NOT NULL,
    platform ENUM('Shopee','TikTok Shop') NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    consumed_at DATETIME NULL,
    KEY idx_oauth_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS marketplace_webhook_inbox (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id BIGINT UNSIGNED NOT NULL,
    external_event_id VARCHAR(190) NOT NULL,
    event_type VARCHAR(120) NOT NULL,
    source_at DATETIME NULL,
    received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,
    next_attempt_at DATETIME NULL,
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('pending','processing','processed','retry','dead') NOT NULL DEFAULT 'pending',
    lease_token CHAR(64) NULL,
    payload_json JSON NOT NULL,
    last_error_code VARCHAR(100) NULL,
    UNIQUE KEY uq_store_event (store_id,external_event_id),
    KEY idx_inbox_work (status,next_attempt_at),
    CONSTRAINT fk_inbox_store FOREIGN KEY (store_id) REFERENCES marketplace_connected_stores(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS marketplace_sync_checkpoints (
    store_id BIGINT UNSIGNED NOT NULL,
    resource_type VARCHAR(80) NOT NULL,
    cursor_value TEXT NULL,
    source_updated_until DATETIME NULL,
    last_success_at DATETIME NULL,
    PRIMARY KEY (store_id,resource_type),
    CONSTRAINT fk_checkpoint_store FOREIGN KEY (store_id) REFERENCES marketplace_connected_stores(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS marketplace_listings_v3 (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id BIGINT UNSIGNED NOT NULL,
    external_product_id VARCHAR(190) NOT NULL,
    external_variant_id VARCHAR(190) NOT NULL,
    seller_sku VARCHAR(190) NULL,
    product_name VARCHAR(255) NOT NULL,
    variant_name VARCHAR(255) NULL,
    listing_status VARCHAR(80) NULL,
    price DECIMAL(18,2) NULL,
    platform_stock DECIMAL(18,3) NULL,
    source_updated_at DATETIME NULL,
    synced_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_store_variant (store_id,external_product_id,external_variant_id),
    CONSTRAINT fk_listing_store FOREIGN KEY (store_id) REFERENCES marketplace_connected_stores(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS marketplace_orders_v3 (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id BIGINT UNSIGNED NOT NULL,
    external_order_id VARCHAR(190) NOT NULL,
    order_status VARCHAR(80) NOT NULL,
    source_updated_at DATETIME NOT NULL,
    source_order_at DATETIME NULL,
    gross_amount DECIMAL(18,2) NULL,
    currency CHAR(3) NOT NULL DEFAULT 'IDR',
    data_complete TINYINT(1) NOT NULL DEFAULT 0,
    source_kind ENUM('api','webhook','import','manual') NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_store_order (store_id,external_order_id),
    KEY idx_order_store_time (store_id,source_order_at),
    CONSTRAINT fk_order_store_v3 FOREIGN KEY (store_id) REFERENCES marketplace_connected_stores(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS marketplace_order_items_v3 (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    external_item_id VARCHAR(190) NOT NULL,
    external_product_id VARCHAR(190) NULL,
    external_variant_id VARCHAR(190) NULL,
    seller_sku_snapshot VARCHAR(190) NULL,
    name_snapshot VARCHAR(255) NOT NULL,
    quantity DECIMAL(18,3) NOT NULL,
    unit_price DECIMAL(18,2) NULL,
    unit_cogs_snapshot DECIMAL(18,2) NULL,
    mapping_status ENUM('unmapped','mapped','not_required') NOT NULL DEFAULT 'unmapped',
    UNIQUE KEY uq_order_item (order_id,external_item_id),
    CONSTRAINT fk_order_item_v3 FOREIGN KEY (order_id) REFERENCES marketplace_orders_v3(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS marketplace_fulfillment_mappings_v3 (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    listing_id BIGINT UNSIGNED NOT NULL,
    fulfillment_mode ENUM('store_stock','konveksi_request') NOT NULL,
    internal_variant_id VARCHAR(120) NULL,
    warehouse_id VARCHAR(120) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_listing_mapping (listing_id),
    CONSTRAINT fk_mapping_listing_v3 FOREIGN KEY (listing_id) REFERENCES marketplace_listings_v3(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS marketplace_business_effects_v3 (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id BIGINT UNSIGNED NOT NULL,
    business_event_key VARCHAR(190) NOT NULL,
    effect_type VARCHAR(80) NOT NULL,
    reference_id VARCHAR(190) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_effect_once (store_id,business_event_key,effect_type),
    CONSTRAINT fk_effect_store_v3 FOREIGN KEY (store_id) REFERENCES marketplace_connected_stores(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS marketplace_financial_events_v3 (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id BIGINT UNSIGNED NOT NULL,
    event_type ENUM('payment','fee','refund','settlement','payout','bank_movement','adjustment') NOT NULL,
    external_transaction_id VARCHAR(190) NOT NULL,
    order_id BIGINT UNSIGNED NULL,
    amount DECIMAL(18,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'IDR',
    source_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_financial_event (store_id,event_type,external_transaction_id),
    CONSTRAINT fk_fin_store_v3 FOREIGN KEY (store_id) REFERENCES marketplace_connected_stores(id),
    CONSTRAINT fk_fin_order_v3 FOREIGN KEY (order_id) REFERENCES marketplace_orders_v3(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS marketplace_payout_order_links_v3 (
    payout_event_id BIGINT UNSIGNED NOT NULL,
    order_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(18,2) NOT NULL,
    PRIMARY KEY (payout_event_id,order_id),
    CONSTRAINT fk_payout_event_v3 FOREIGN KEY (payout_event_id) REFERENCES marketplace_financial_events_v3(id),
    CONSTRAINT fk_payout_order_v3 FOREIGN KEY (order_id) REFERENCES marketplace_orders_v3(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
