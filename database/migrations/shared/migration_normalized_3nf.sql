-- =============================================================================
-- JAGO LABA ERP - NORMALIZED DATABASE SCHEMA (3NF DDL)
-- Generated for MySQL 8.0+ / MariaDB 10.5+
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. ROLE & USER AUTHENTICATION
CREATE TABLE IF NOT EXISTS `roles` (
    `id` VARCHAR(50) NOT NULL PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) NULL,
    `permissions` LONGTEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_uuid` VARCHAR(60) NOT NULL UNIQUE,
    `role_id` VARCHAR(50) NOT NULL,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `status` ENUM('Aktif', 'Nonaktif') NOT NULL DEFAULT 'Aktif',
    `last_login_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. MASTER ENTITIES (Pegawai, Supplier, Rekening)
CREATE TABLE IF NOT EXISTS `employees` (
    `id` BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    `code` VARCHAR(30) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(30) NULL,
    `division` VARCHAR(50) NOT NULL,
    `type` ENUM('Harian', 'Borongan', 'Tetap') NOT NULL DEFAULT 'Borongan',
    `status` ENUM('Aktif', 'Nonaktif') NOT NULL DEFAULT 'Aktif',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_emp_division` (`division`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `suppliers` (
    `id` BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    `code` VARCHAR(30) NULL,
    `name` VARCHAR(150) NOT NULL,
    `type` VARCHAR(50) NOT NULL DEFAULT 'Supplier Bahan',
    `phone` VARCHAR(30) NULL,
    `address` TEXT NULL,
    `notes` TEXT NULL,
    `status` ENUM('Aktif', 'Nonaktif') NOT NULL DEFAULT 'Aktif',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `accounts` (
    `id` BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    `code` VARCHAR(30) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `type` VARCHAR(50) NOT NULL DEFAULT 'Bank',
    `initial_balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `current_balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. BAHAN BAKU & MATERIAL ALIAS
CREATE TABLE IF NOT EXISTS `materials` (
    `id` BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `category` VARCHAR(50) NULL,
    `unit_buy` VARCHAR(30) NOT NULL DEFAULT 'Pcs',
    `unit_use` VARCHAR(30) NOT NULL DEFAULT 'Pcs',
    `ratio_buy_to_use` DECIMAL(10,4) NOT NULL DEFAULT 1.0000,
    `last_purchase_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `cost_per_use_unit` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `stock_minimum` DECIMAL(10,3) NOT NULL DEFAULT 0.000,
    `current_stock` DECIMAL(10,3) NOT NULL DEFAULT 0.000,
    `status` ENUM('Aktif', 'Nonaktif') NOT NULL DEFAULT 'Aktif',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_mat_cat` (`category`),
    INDEX `idx_mat_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `material_aliases` (
    `id` VARCHAR(50) NOT NULL PRIMARY KEY,
    `material_id` BIGINT UNSIGNED NOT NULL,
    `supplier_id` BIGINT UNSIGNED NULL,
    `alias_name` VARCHAR(150) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_alias_material` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_alias_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
    INDEX `idx_alias_search` (`alias_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. MASTER PRODUK & VARIAN
CREATE TABLE IF NOT EXISTS `products` (
    `id` VARCHAR(60) NOT NULL PRIMARY KEY,
    `model_code` VARCHAR(50) NULL,
    `name` VARCHAR(150) NOT NULL,
    `category` VARCHAR(50) NOT NULL,
    `brand` VARCHAR(50) NOT NULL DEFAULT 'JAGOIN',
    `description` TEXT NULL,
    `wage_cutting` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `wage_sewing` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `wage_finishing` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `wage_packing` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `standard_hpp` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `status` ENUM('Aktif', 'Nonaktif') NOT NULL DEFAULT 'Aktif',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_variants` (
    `id` VARCHAR(60) NOT NULL PRIMARY KEY,
    `product_id` VARCHAR(60) NOT NULL,
    `sku` VARCHAR(60) NOT NULL UNIQUE,
    `variant_name` VARCHAR(100) NOT NULL,
    `color` VARCHAR(50) NULL,
    `size` VARCHAR(20) NULL,
    `barcode` VARCHAR(100) NULL,
    `min_alert` INT NOT NULL DEFAULT 5,
    `status` ENUM('Aktif', 'Nonaktif') NOT NULL DEFAULT 'Aktif',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_variant_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
    INDEX `idx_var_sku` (`sku`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. BOM & KALKULASI HPP
CREATE TABLE IF NOT EXISTS `hpp_models` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `product_id` VARCHAR(60) NOT NULL,
    `version_name` VARCHAR(50) NOT NULL DEFAULT 'V1',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `total_material_cost` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total_labor_cost` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `overhead_cost` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `final_hpp` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_bom_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hpp_bom_items` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `hpp_model_id` BIGINT UNSIGNED NOT NULL,
    `material_id` BIGINT UNSIGNED NOT NULL,
    `qty_usage_per_pcs` DECIMAL(10,4) NOT NULL,
    `waste_pct` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `qty_effective_per_pcs` DECIMAL(10,4) NOT NULL,
    `price_per_use_unit` DECIMAL(15,4) NOT NULL,
    `subtotal_cost` DECIMAL(15,2) NOT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    CONSTRAINT `fk_bom_header` FOREIGN KEY (`hpp_model_id`) REFERENCES `hpp_models` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_bom_material` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. GUDANG & INVENTORI STOK
CREATE TABLE IF NOT EXISTS `warehouses` (
    `id` VARCHAR(30) NOT NULL PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `location` VARCHAR(150) NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `inventory_stocks` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `warehouse_id` VARCHAR(30) NOT NULL DEFAULT 'GDG-UTAMA',
    `product_variant_id` VARCHAR(60) NOT NULL,
    `stock_qty` INT NOT NULL DEFAULT 0,
    `average_hpp` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total_valuation` DECIMAL(15,2) GENERATED ALWAYS AS (`stock_qty` * `average_hpp`) STORED,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_stock_wh_variant` (`warehouse_id`, `product_variant_id`),
    CONSTRAINT `fk_stock_wh` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`),
    CONSTRAINT `fk_stock_variant` FOREIGN KEY (`product_variant_id`) REFERENCES `product_variants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stock_mutations` (
    `id` VARCHAR(60) NOT NULL PRIMARY KEY,
    `transaction_date` DATE NOT NULL,
    `reference_no` VARCHAR(60) NOT NULL,
    `mutation_type` VARCHAR(50) NOT NULL,
    `item_type` ENUM('BAHAN_BAKU', 'PRODUK_JADI') NOT NULL,
    `material_id` BIGINT UNSIGNED NULL,
    `product_variant_id` VARCHAR(60) NULL,
    `warehouse_id` VARCHAR(30) NOT NULL DEFAULT 'GDG-UTAMA',
    `qty_in` DECIMAL(10,3) NOT NULL DEFAULT 0.000,
    `qty_out` DECIMAL(10,3) NOT NULL DEFAULT 0.000,
    `stock_before` DECIMAL(10,3) NOT NULL DEFAULT 0.000,
    `stock_after` DECIMAL(10,3) NOT NULL DEFAULT 0.000,
    `unit_cost` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total_cost` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `party_name` VARCHAR(150) NULL,
    `operator_name` VARCHAR(100) NULL,
    `idempotency_key` VARCHAR(150) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_mut_ref` (`reference_no`),
    INDEX `idx_mut_date` (`transaction_date`),
    CONSTRAINT `fk_mut_mat` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`),
    CONSTRAINT `fk_mut_var` FOREIGN KEY (`product_variant_id`) REFERENCES `product_variants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. PENGADAAN (PURCHASES)
CREATE TABLE IF NOT EXISTS `purchase_invoices` (
    `id` BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    `invoice_no` VARCHAR(50) NOT NULL,
    `supplier_invoice_no` VARCHAR(80) NULL,
    `supplier_id` BIGINT UNSIGNED NOT NULL,
    `account_id` BIGINT UNSIGNED NULL,
    `invoice_date` DATE NOT NULL,
    `due_date` DATE NULL,
    `subtotal` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `discount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `tax` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `shipping_fee` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `grand_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `amount_paid` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `remaining_debt` DECIMAL(15,2) GENERATED ALWAYS AS (`grand_total` - `amount_paid`) STORED,
    `payment_status` VARCHAR(50) NOT NULL DEFAULT 'Belum Bayar',
    `idempotency_key` VARCHAR(150) NULL,
    `verified_by` VARCHAR(100) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_po_invno` (`invoice_no`),
    CONSTRAINT `fk_po_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
    CONSTRAINT `fk_po_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `purchase_items` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `purchase_invoice_id` BIGINT UNSIGNED NOT NULL,
    `material_id` BIGINT UNSIGNED NULL,
    `material_name_raw` VARCHAR(150) NULL,
    `qty_buy` DECIMAL(10,3) NOT NULL,
    `unit_buy` VARCHAR(30) NOT NULL,
    `unit_price` DECIMAL(15,2) NOT NULL,
    `subtotal` DECIMAL(15,2) NOT NULL,
    CONSTRAINT `fk_pbi_invoice` FOREIGN KEY (`purchase_invoice_id`) REFERENCES `purchase_invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. MANUFAKTUR & ALUR SPK PRODUKSI
CREATE TABLE IF NOT EXISTS `spk_cuttings` (
    `id` BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    `spk_no` VARCHAR(50) NOT NULL,
    `spk_date` DATE NOT NULL,
    `target_finish_date` DATE NULL,
    `cutter_employee_id` BIGINT UNSIGNED NOT NULL,
    `total_target_pcs` INT NOT NULL DEFAULT 0,
    `total_cut_actual_pcs` INT NOT NULL DEFAULT 0,
    `total_wage` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `loan_deduction` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `net_wage` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `wage_disposition` VARCHAR(50) NOT NULL DEFAULT 'Disimpan Hutang',
    `status` VARCHAR(50) NOT NULL DEFAULT 'Proses',
    `completed_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_cut_spkno` (`spk_no`),
    CONSTRAINT `fk_cut_cutter` FOREIGN KEY (`cutter_employee_id`) REFERENCES `employees` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `spk_cutting_items` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `spk_cutting_id` BIGINT UNSIGNED NOT NULL,
    `product_id` VARCHAR(60) NULL,
    `qty_target` INT NOT NULL DEFAULT 0,
    `qty_actual` INT NOT NULL DEFAULT 0,
    `notes` VARCHAR(255) NULL,
    CONSTRAINT `fk_cutitem_header` FOREIGN KEY (`spk_cutting_id`) REFERENCES `spk_cuttings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `spk_sewings` (
    `id` BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    `spk_no` VARCHAR(50) NOT NULL,
    `spk_cutting_id` BIGINT UNSIGNED NULL,
    `spk_date` DATE NOT NULL,
    `estimate_days` INT NOT NULL DEFAULT 7,
    `sewer_employee_id` BIGINT UNSIGNED NULL,
    `sewer_vendor_id` BIGINT UNSIGNED NULL,
    `is_makloon` TINYINT(1) NOT NULL DEFAULT 0,
    `total_assigned_pcs` INT NOT NULL DEFAULT 0,
    `total_completed_pcs` INT NOT NULL DEFAULT 0,
    `total_wage` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `loan_deduction` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `net_wage` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `wage_disposition` VARCHAR(50) NOT NULL DEFAULT 'Disimpan Hutang',
    `status` VARCHAR(50) NOT NULL DEFAULT 'Proses',
    `completed_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_sew_spkno` (`spk_no`),
    CONSTRAINT `fk_sew_employee` FOREIGN KEY (`sewer_employee_id`) REFERENCES `employees` (`id`),
    CONSTRAINT `fk_sew_vendor` FOREIGN KEY (`sewer_vendor_id`) REFERENCES `suppliers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `spk_sewing_items` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `spk_sewing_id` BIGINT UNSIGNED NOT NULL,
    `cutting_ref_id` VARCHAR(60) NULL,
    `product_id` VARCHAR(60) NULL,
    `qty_assigned` INT NOT NULL DEFAULT 0,
    `qty_completed` INT NOT NULL DEFAULT 0,
    CONSTRAINT `fk_sewitem_header` FOREIGN KEY (`spk_sewing_id`) REFERENCES `spk_sewings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `quality_controls` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `qc_no` VARCHAR(50) NOT NULL,
    `spk_sewing_id` BIGINT UNSIGNED NULL,
    `product_variant_id` VARCHAR(60) NULL,
    `inspection_date` DATE NOT NULL,
    `qty_in` INT NOT NULL DEFAULT 0,
    `qty_passed` INT NOT NULL DEFAULT 0,
    `qty_rework` INT NOT NULL DEFAULT 0,
    `qty_defect` INT NOT NULL DEFAULT 0,
    `inspector_id` BIGINT UNSIGNED NULL,
    `notes` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. HUTANG KONVEKSI & KASBON (PAYABLES & PAYROLL LOANS)
CREATE TABLE IF NOT EXISTS `employee_loans` (
    `id` VARCHAR(60) NOT NULL PRIMARY KEY,
    `loan_code` VARCHAR(50) NOT NULL,
    `loan_date` DATE NOT NULL,
    `employee_id` BIGINT UNSIGNED NOT NULL,
    `account_id` BIGINT UNSIGNED NULL,
    `principal_amount` DECIMAL(15,2) NOT NULL,
    `total_paid` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `balance_due` DECIMAL(15,2) GENERATED ALWAYS AS (`principal_amount` - `total_paid`) STORED,
    `status` ENUM('Aktif', 'Lunas') NOT NULL DEFAULT 'Aktif',
    `notes` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_loan_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sewing_debts` (
    `id` VARCHAR(60) NOT NULL PRIMARY KEY,
    `debt_code` VARCHAR(50) NOT NULL,
    `debt_date` DATE NOT NULL,
    `source_type` VARCHAR(50) NOT NULL DEFAULT 'SPK_JAHIT',
    `reference_spk_no` VARCHAR(50) NULL,
    `employee_id` BIGINT UNSIGNED NULL,
    `vendor_id` BIGINT UNSIGNED NULL,
    `total_wage_earned` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `loan_deduction_applied` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `net_wage_payable` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total_paid` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `balance_due` DECIMAL(15,2) GENERATED ALWAYS AS (`net_wage_payable` - `total_paid`) STORED,
    `status` VARCHAR(50) NOT NULL DEFAULT 'Belum Bayar',
    `idempotency_key` VARCHAR(150) NULL,
    `notes` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_debt_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`),
    CONSTRAINT `fk_debt_vnd` FOREIGN KEY (`vendor_id`) REFERENCES `suppliers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sewing_debt_payments` (
    `id` VARCHAR(60) NOT NULL PRIMARY KEY,
    `payment_code` VARCHAR(50) NOT NULL,
    `sewing_debt_id` VARCHAR(60) NOT NULL,
    `account_id` BIGINT UNSIGNED NULL,
    `payment_date` DATE NOT NULL,
    `amount_paid` DECIMAL(15,2) NOT NULL,
    `balance_before` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `balance_after` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `operator_name` VARCHAR(100) NULL,
    `notes` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_pay_debt` FOREIGN KEY (`sewing_debt_id`) REFERENCES `sewing_debts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. KAS & TRANSAKSI KEUANGAN UMUM
CREATE TABLE IF NOT EXISTS `cash_transactions` (
    `id` BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    `transaction_date` DATE NOT NULL,
    `type` ENUM('Masuk', 'Keluar', 'Transfer') NOT NULL,
    `account_id` BIGINT UNSIGNED NOT NULL,
    `destination_account_id` BIGINT UNSIGNED NULL,
    `amount` DECIMAL(15,2) NOT NULL,
    `category` VARCHAR(80) NULL,
    `reference_id` VARCHAR(100) NULL,
    `description` TEXT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_cash_date` (`transaction_date`),
    CONSTRAINT `fk_cash_acc` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. PENJUALAN & PESANAN (SALES)
CREATE TABLE IF NOT EXISTS `sales_orders` (
    `id` VARCHAR(60) NOT NULL PRIMARY KEY,
    `order_no` VARCHAR(50) NOT NULL,
    `order_date` DATE NOT NULL,
    `customer_name` VARCHAR(150) NULL,
    `customer_phone` VARCHAR(30) NULL,
    `customer_address` TEXT NULL,
    `customer_type` VARCHAR(50) NOT NULL DEFAULT 'Retail',
    `sales_channel` VARCHAR(50) NOT NULL DEFAULT 'Offline',
    `payment_method` VARCHAR(50) NOT NULL DEFAULT 'Tunai',
    `payment_status` VARCHAR(50) NOT NULL DEFAULT 'Lunas',
    `account_id` BIGINT UNSIGNED NULL,
    `subtotal` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `discount_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `shipping_fee` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `other_cost` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `grand_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `paid_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `remaining_bill` DECIMAL(15,2) GENERATED ALWAYS AS (`grand_total` - `paid_amount`) STORED,
    `total_hpp` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `gross_profit` DECIMAL(15,2) GENERATED ALWAYS AS (`grand_total` - `total_hpp`) STORED,
    `status` VARCHAR(50) NOT NULL DEFAULT 'Posted',
    `idempotency_key` VARCHAR(150) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_so_orderno` (`order_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sales_items` (
    `id` VARCHAR(60) NOT NULL PRIMARY KEY,
    `sales_order_id` VARCHAR(60) NOT NULL,
    `product_variant_id` VARCHAR(60) NULL,
    `warehouse_id` VARCHAR(30) NOT NULL DEFAULT 'GDG-UTAMA',
    `qty` INT NOT NULL,
    `unit_price` DECIMAL(15,2) NOT NULL,
    `discount_item` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `subtotal` DECIMAL(15,2) NOT NULL,
    `unit_hpp` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total_hpp` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    CONSTRAINT `fk_sli_order` FOREIGN KEY (`sales_order_id`) REFERENCES `sales_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

