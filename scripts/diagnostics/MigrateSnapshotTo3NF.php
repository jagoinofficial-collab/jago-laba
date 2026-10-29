<?php
require_once (dirname(__DIR__, 2) . '/app/Database.php');

final class MigrateSnapshotTo3NF
{
    private PDO $pdo;
    private array $data;
    private array $report = [];

    public function __construct(string $snapshotPath)
    {
        $this->pdo = Database::pdo();
        if (!file_exists($snapshotPath)) {
            throw new RuntimeException("Snapshot file not found: $snapshotPath");
        }
        $raw = file_get_contents($snapshotPath);
        $this->data = json_decode($raw, true);
        if (!$this->data || !isset($this->data['sheets'])) {
            throw new RuntimeException("Invalid snapshot JSON format.");
        }
    }

    public static function parseDate($val): string
    {
        if ($val === null || $val === '') {
            return date('Y-m-d');
        }
        if (is_numeric($val)) {
            // Excel serial day
            $serial = (int)$val;
            if ($serial > 10000 && $serial < 100000) {
                $base = new DateTime('1899-12-30');
                $base->modify("+{$serial} days");
                return $base->format('Y-m-d');
            }
        }
        try {
            $dt = new DateTime((string)$val);
            return $dt->format('Y-m-d');
        } catch (Exception $e) {
            return date('Y-m-d');
        }
    }

    public static function parseDateTime($val): string
    {
        if ($val === null || $val === '') {
            return date('Y-m-d H:i:s');
        }
        if (is_numeric($val)) {
            $date = self::parseDate($val);
            return $date . ' 00:00:00';
        }
        try {
            $dt = new DateTime((string)$val);
            return $dt->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return date('Y-m-d H:i:s');
        }
    }

    public static function cleanDec($val, int $dec = 2): float
    {
        if ($val === null || $val === '') return 0.0;
        if (is_numeric($val)) return round((float)$val, $dec);
        $cleaned = preg_replace('/[^0-9.-]/', '', (string)$val);
        return round((float)$cleaned, $dec);
    }

    public static function cleanInt($val): int
    {
        if ($val === null || $val === '') return 0;
        return (int)$val;
    }

    public function run(): array
    {
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

        $this->migrateRoles();
        $this->migrateUsers();
        $this->migrateAccounts();
        $this->migrateWarehouses();
        $this->migrateSuppliers();
        $this->migrateEmployees();
        $this->migrateMaterials();
        $this->migrateMaterialAliases();
        $this->migrateProductsAndVariants();
        $this->migrateInventoryStocks();
        $this->migrateStockMutations();
        $this->migratePurchases();
        $this->migrateSpkCuttings();
        $this->migrateSpkSewings();
        $this->migrateEmployeeLoans();
        $this->migrateSewingDebts();
        $this->migrateCashTransactions();
        $this->migrateSales();

        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

        return $this->report;
    }

    private function migrateRoles(): void
    {
        $rows = $this->data['sheets']['Hak_Akses']['rows'] ?? [];
        $stmt = $this->pdo->prepare("INSERT INTO roles (id, name, description, permissions) 
            VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name), permissions=VALUES(permissions)");
        $count = 0;
        foreach ($rows as $r) {
            $id = trim((string)($r['role_id'] ?? ''));
            if (!$id) continue;
            $name = trim((string)($r['nama_peran'] ?? $id));
            $desc = $r['deskripsi'] ?? null;
            $perm = is_array($r['permissions_json'] ?? null) ? json_encode($r['permissions_json']) : ($r['permissions_json'] ?? null);
            $stmt->execute([$id, $name, $desc, $perm]);
            $count++;
        }
        $this->report['roles'] = $count;
    }

    private function migrateUsers(): void
    {
        $rows = $this->data['sheets']['Pengguna']['rows'] ?? [];
        $stmt = $this->pdo->prepare("INSERT INTO users (user_uuid, role_id, username, name, email, password_hash, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name), role_id=VALUES(role_id)");
        $count = 0;
        foreach ($rows as $r) {
            $username = trim((string)($r['username'] ?? ''));
            if (!$username) continue;
            $uuid = trim((string)($r['id'] ?? 'USR-' . strtoupper(substr(hash('sha256', $username), 0, 10))));
            $role = trim((string)($r['role_id'] ?? 'ROLE-OWNER_SUPER_ADMIN'));
            $name = trim((string)($r['nama'] ?? $username));
            $email = $r['email'] ?? null;
            $pwd = (string)($r['password'] ?? 'ChangeMe123');
            $hash = password_hash($pwd, PASSWORD_DEFAULT);
            $status = ($r['status'] ?? 'Aktif') === 'Aktif' ? 'Aktif' : 'Nonaktif';

            $stmt->execute([$uuid, $role, $username, $name, $email, $hash, $status]);
            $count++;
        }
        $this->report['users'] = $count;
    }

    private function migrateAccounts(): void
    {
        $rows = $this->data['sheets']['Rekening']['rows'] ?? [];
        $stmt = $this->pdo->prepare("INSERT INTO accounts (id, code, name, type, initial_balance, current_balance, is_active) 
            VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name), current_balance=VALUES(current_balance)");
        $count = 0;
        foreach ($rows as $r) {
            $id = (int)($r['id'] ?? 0);
            if (!$id) continue;
            $code = trim((string)($r['kode'] ?? 'ACC-' . $id));
            $name = trim((string)($r['nama'] ?? $code));
            $init = self::cleanDec($r['saldo_awal'] ?? 0);
            $type = (stripos($name, 'TUNAI') !== false || stripos($code, 'KES') !== false) ? 'Kas Tunai' : 'Bank';
            $stmt->execute([$id, $code, $name, $type, $init, $init, 1]);
            $count++;
        }
        $this->report['accounts'] = $count;
    }

    private function migrateWarehouses(): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO warehouses (id, name, location, is_active) 
            VALUES ('GDG-UTAMA', 'Gudang Utama JAGOIN', 'Sentra Produksi Garut', 1) 
            ON DUPLICATE KEY UPDATE name=VALUES(name)");
        $stmt->execute();
        $this->report['warehouses'] = 1;
    }

    private function migrateSuppliers(): void
    {
        $rows = $this->data['sheets']['Supplier']['rows'] ?? [];
        $stmt = $this->pdo->prepare("INSERT INTO suppliers (id, code, name, type, phone, address, notes, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Aktif') ON DUPLICATE KEY UPDATE name=VALUES(name), type=VALUES(type)");
        $count = 0;
        foreach ($rows as $r) {
            $id = (int)($r['id'] ?? 0);
            if (!$id) continue;
            $name = trim((string)($r['nama'] ?? 'Supplier ' . $id));
            $type = trim((string)($r['tipe'] ?? 'Supplier Bahan'));
            $phone = (string)($r['no_wa'] ?? '');
            $address = $r['alamat'] ?? null;
            $notes = $r['catatan'] ?? null;
            $code = 'SUP-' . $id;

            $stmt->execute([$id, $code, $name, $type, $phone, $address, $notes]);
            $count++;
        }
        $this->report['suppliers'] = $count;
    }

    private function migrateEmployees(): void
    {
        $rows = $this->data['sheets']['Pegawai']['rows'] ?? [];
        $stmt = $this->pdo->prepare("INSERT INTO employees (id, code, name, phone, division, type, status) 
            VALUES (?, ?, ?, ?, ?, 'Borongan', 'Aktif') ON DUPLICATE KEY UPDATE name=VALUES(name), division=VALUES(division)");
        $count = 0;
        foreach ($rows as $r) {
            $id = (int)($r['id'] ?? 0);
            if (!$id) continue;
            $code = trim((string)($r['kode'] ?? 'PGW-' . $id));
            $name = trim((string)($r['nama'] ?? 'Pegawai ' . $id));
            $phone = (string)($r['no_hp'] ?? '');
            $division = trim((string)($r['peran'] ?? 'Penjahit'));

            $stmt->execute([$id, $code, $name, $phone, $division]);
            $count++;
        }
        $this->report['employees'] = $count;
    }

    private function migrateMaterials(): void
    {
        $rows = $this->data['sheets']['Bahan']['rows'] ?? [];
        $stmt = $this->pdo->prepare("INSERT INTO materials (id, code, name, category, unit_buy, unit_use, ratio_buy_to_use, last_purchase_price, current_stock, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Aktif') 
            ON DUPLICATE KEY UPDATE name=VALUES(name), current_stock=VALUES(current_stock), last_purchase_price=VALUES(last_purchase_price)");
        $count = 0;
        foreach ($rows as $r) {
            $id = (int)($r['id'] ?? 0);
            if (!$id) continue;
            $code = trim((string)($r['kode'] ?? 'MAT-' . $id));
            $name = trim((string)($r['nama'] ?? $code));
            $category = $r['jenis'] ?? $r['kategori'] ?? 'Kain_Utama';
            $unitBuy = $r['satuan_beli'] ?? $r['satuan'] ?? 'Roll';
            $unitUse = $r['satuan_pakai'] ?? $r['satuan'] ?? 'Roll';
            $ratio = self::cleanDec($r['isi_satuan_beli'] ?? 1, 4);
            $price = self::cleanDec($r['harga_beli'] ?? 0);
            $stock = self::cleanDec($r['stok'] ?? 0, 3);

            $stmt->execute([$id, $code, $name, $category, $unitBuy, $unitUse, $ratio, $price, $stock]);
            $count++;
        }
        $this->report['materials'] = $count;
    }

    private function migrateMaterialAliases(): void
    {
        $rows = $this->data['sheets']['Material_Alias']['rows'] ?? [];
        $stmt = $this->pdo->prepare("INSERT INTO material_aliases (id, material_id, supplier_id, alias_name, created_at) 
            VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE alias_name=VALUES(alias_name)");
        $count = 0;
        foreach ($rows as $r) {
            $id = trim((string)($r['id_alias'] ?? 'ALS-' . uniqid()));
            $matId = (int)($r['id_bahan'] ?? 0);
            $supId = (int)($r['id_supplier'] ?? 0) ?: null;
            $name = trim((string)($r['nama_alias'] ?? ''));
            $created = self::parseDateTime($r['created_at'] ?? null);

            if ($matId > 0 && $name !== '') {
                $stmt->execute([$id, $matId, $supId, $name, $created]);
                $count++;
            }
        }
        $this->report['material_aliases'] = $count;
    }

    private function migrateProductsAndVariants(): void
    {
        $prodRows = $this->data['sheets']['Produk']['rows'] ?? [];
        $stockRows = $this->data['sheets']['Master_Stok']['rows'] ?? [];

        $stmtProd = $this->pdo->prepare("INSERT INTO products (id, model_code, name, category, brand, wage_cutting, wage_sewing, wage_finishing, wage_packing, standard_hpp, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Aktif') 
            ON DUPLICATE KEY UPDATE name=VALUES(name), standard_hpp=VALUES(standard_hpp)");

        $stmtVar = $this->pdo->prepare("INSERT INTO product_variants (id, product_id, sku, variant_name, color, min_alert, status) 
            VALUES (?, ?, ?, ?, ?, ?, 'Aktif') 
            ON DUPLICATE KEY UPDATE variant_name=VALUES(variant_name), color=VALUES(color)");

        $prodCount = 0;
        $varCount = 0;

        // Process products
        foreach ($prodRows as $r) {
            $id = trim((string)($r['id'] ?? ''));
            if (!$id) continue;
            $code = trim((string)($r['model_tas'] ?? $r['kode'] ?? ''));
            $name = trim((string)($r['nama'] ?? 'Produk ' . $id));
            $category = trim((string)($r['kategori'] ?? 'Ransel'));
            $brand = trim((string)($r['brand'] ?? 'JAGOIN'));
            $wCut = self::cleanDec($r['upah_potong'] ?? 0);
            $wSew = self::cleanDec($r['upah_jahit'] ?? 0);
            $wFin = self::cleanDec($r['upah_finishing'] ?? 0);
            $wPak = self::cleanDec($r['upah_packing'] ?? 0);
            $hpp = self::cleanDec($r['hpp_produk'] ?? 0);

            $stmtProd->execute([$id, $code, $name, $category, $brand, $wCut, $wSew, $wFin, $wPak, $hpp]);
            $prodCount++;

            // Create variant if specified
            $sku = trim((string)($r['sku_varian'] ?? $r['kode'] ?? ''));
            if ($sku !== '') {
                $varId = 'VAR-' . md5($sku);
                $color = $r['warna'] ?? 'Standar';
                $stmtVar->execute([$varId, $id, $sku, $name . ' (' . $color . ')', $color, 5]);
                $varCount++;
            }
        }

        // Process master stok variants (if not already added)
        foreach ($stockRows as $sr) {
            $sku = trim((string)($sr['kode_sku'] ?? ''));
            if (!$sku) continue;

            $varId = trim((string)($sr['id_stok_item'] ?? '')) ?: ('VAR-' . md5($sku));
            $name = trim((string)($sr['nama_produk'] ?? $sku));
            $color = trim((string)($sr['varian_warna'] ?? 'Standar'));
            $cat = trim((string)($sr['kategori'] ?? 'Ransel'));
            $minAlert = self::cleanInt($sr['min_alert'] ?? 5);

            // Determine parent product
            $parentId = trim((string)($sr['id_produk'] ?? ''));
            if (!$parentId || !in_array($parentId, array_column($prodRows, 'id'))) {
                // Ensure a base product exists
                $parentId = 'PRD-' . md5($name);
                $stmtProd->execute([$parentId, 'MOD-' . substr($sku, 0, 8), $name, $cat, 'JAGOIN', 500, 6700, 1000, 1000, self::cleanDec($sr['hpp_rata_rata'] ?? 0)]);
            }

            $stmtVar->execute([$varId, $parentId, $sku, $name . ' - ' . $color, $color, $minAlert]);
            $varCount++;
        }

        $this->report['products'] = $prodCount;
        $this->report['product_variants'] = $varCount;
    }

    private function migrateInventoryStocks(): void
    {
        $rows = $this->data['sheets']['Master_Stok']['rows'] ?? [];
        $stmt = $this->pdo->prepare("INSERT INTO inventory_stocks (warehouse_id, product_variant_id, stock_qty, average_hpp) 
            VALUES ('GDG-UTAMA', ?, ?, ?) 
            ON DUPLICATE KEY UPDATE stock_qty=VALUES(stock_qty), average_hpp=VALUES(average_hpp)");
        $count = 0;
        foreach ($rows as $r) {
            $sku = trim((string)($r['kode_sku'] ?? ''));
            if (!$sku) continue;
            $varId = trim((string)($r['id_stok_item'] ?? '')) ?: ('VAR-' . md5($sku));
            $qty = self::cleanInt($r['stok_akhir'] ?? 0);
            $hpp = self::cleanDec($r['hpp_rata_rata'] ?? 0);

            $stmt->execute([$varId, $qty, $hpp]);
            $count++;
        }
        $this->report['inventory_stocks'] = $count;
    }

    private function migrateStockMutations(): void
    {
        $rows = $this->data['sheets']['Mutasi_Stok']['rows'] ?? [];
        $stmt = $this->pdo->prepare("INSERT INTO stock_mutations 
            (id, transaction_date, reference_no, mutation_type, item_type, material_id, product_variant_id, warehouse_id, qty_in, qty_out, stock_before, stock_after, unit_cost, total_cost, party_name, operator_name, idempotency_key, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'GDG-UTAMA', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE total_cost=VALUES(total_cost)");
        $count = 0;
        foreach ($rows as $r) {
            $id = trim((string)($r['id_mutasi'] ?? 'MUT-' . uniqid()));
            $tDate = self::parseDate($r['tanggal'] ?? null);
            $refNo = trim((string)($r['no_referensi'] ?? $id));
            $mType = trim((string)($r['jenis_mutasi'] ?? 'ADJUSTMENT'));
            $itemType = (!empty($r['id_bahan']) || !empty($r['kode_bahan'])) ? 'BAHAN_BAKU' : 'PRODUK_JADI';
            $matId = (int)($r['id_bahan'] ?? 0) ?: null;

            $sku = trim((string)($r['kode_sku'] ?? ''));
            $varId = $sku !== '' ? ('VAR-' . md5($sku)) : null;

            $qtyIn = self::cleanDec($r['qty_masuk'] ?? 0, 3);
            $qtyOut = self::cleanDec($r['qty_keluar'] ?? 0, 3);
            $stockBefore = self::cleanDec($r['stok_sebelum'] ?? 0, 3);
            $stockAfter = self::cleanDec($r['saldo_berjalan'] ?? $r['stok_setelah'] ?? 0, 3);
            $cost = self::cleanDec($r['hpp_satuan'] ?? 0);
            $totalCost = self::cleanDec($r['total_nilai'] ?? 0);
            $party = $r['pihak_terkait'] ?? null;
            $operator = $r['operator'] ?? 'owner';
            $idem = $r['idempotency_key'] ?? null;
            $created = self::parseDateTime($r['created_at'] ?? null);

            $stmt->execute([$id, $tDate, $refNo, $mType, $itemType, $matId, $varId, $qtyIn, $qtyOut, $stockBefore, $stockAfter, $cost, $totalCost, $party, $operator, $idem, $created]);
            $count++;
        }
        $this->report['stock_mutations'] = $count;
    }

    private function migratePurchases(): void
    {
        $rows = $this->data['sheets']['Pembelian']['rows'] ?? [];
        $stmtPo = $this->pdo->prepare("INSERT INTO purchase_invoices 
            (id, invoice_no, supplier_invoice_no, supplier_id, account_id, invoice_date, subtotal, grand_total, amount_paid, payment_status, idempotency_key, verified_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE grand_total=VALUES(grand_total), amount_paid=VALUES(amount_paid)");

        $stmtItem = $this->pdo->prepare("INSERT INTO purchase_items 
            (purchase_invoice_id, material_id, material_name_raw, qty_buy, unit_buy, unit_price, subtotal)
            VALUES (?, ?, ?, ?, ?, ?, ?)");

        $poCount = 0;
        $itemCount = 0;

        foreach ($rows as $r) {
            $id = (int)($r['id'] ?? 0);
            if (!$id) continue;

            $invNo = trim((string)($r['no_faktur'] ?? 'PBL-' . $id));
            $supInvNo = $r['no_faktur_supplier'] ?? null;
            $supId = (int)($r['id_supplier'] ?? 0);
            $accId = (int)($r['id_rekening'] ?? 0) ?: null;
            $invDate = self::parseDate($r['tanggal'] ?? null);
            $subtotal = self::cleanDec($r['subtotal'] ?? $r['total_harga'] ?? 0);
            $grand = self::cleanDec($r['total_harga'] ?? $subtotal);
            $paid = self::cleanDec($r['nominal_bayar'] ?? 0);
            $status = trim((string)($r['status_bayar'] ?? 'Lunas'));
            $idem = $r['idempotency_key'] ?? null;
            $verified = $r['verified_by'] ?? 'owner';
            $created = self::parseDateTime($r['verified_at'] ?? null);

            $stmtPo->execute([$id, $invNo, $supInvNo, $supId, $accId, $invDate, $subtotal, $grand, $paid, $status, $idem, $verified, $created]);
            $poCount++;

            // Unpack items JSON
            $itemsJson = $r['items'] ?? '[]';
            $items = is_string($itemsJson) ? json_decode($itemsJson, true) : (array)$itemsJson;
            if (is_array($items)) {
                foreach ($items as $item) {
                    $matId = (int)($item['id_bahan'] ?? 0) ?: null;
                    $rawName = $item['nama_faktur_supplier'] ?? $item['raw_name'] ?? 'Bahan';
                    $qty = self::cleanDec($item['qty'] ?? 1, 3);
                    $unit = $item['satuan'] ?? $item['satuan_beli'] ?? 'Roll';
                    $price = self::cleanDec($item['harga_satuan'] ?? 0);
                    $sub = self::cleanDec($item['total_harga'] ?? ($qty * $price));

                    $stmtItem->execute([$id, $matId, $rawName, $qty, $unit, $price, $sub]);
                    $itemCount++;
                }
            }
        }

        // Also process Pembelian_Vendor (Makloon POs)
        $vendorRows = $this->data['sheets']['Pembelian_Vendor']['rows'] ?? [];
        foreach ($vendorRows as $vr) {
            $poNo = trim((string)($vr['no_po_vendor'] ?? ''));
            if (!$poNo) continue;

            $vId = (int)($vr['id_vendor'] ?? 0);
            $accId = (int)($vr['akun_kas'] ?? 0) ?: null;
            $tDate = self::parseDate($vr['tanggal_po'] ?? null);
            $sub = self::cleanDec($vr['subtotal'] ?? 0);
            $total = self::cleanDec($vr['total_pembelian'] ?? $sub);
            $paid = self::cleanDec($vr['nominal_bayar'] ?? 0);
            $status = trim((string)($vr['status_bayar'] ?? 'Tempo'));

            $fakeId = crc32($poNo);
            $stmtPo->execute([$fakeId, $poNo, null, $vId, $accId, $tDate, $sub, $total, $paid, $status, "POV|" . $poNo, "owner", date('Y-m-d H:i:s')]);
            $poCount++;
        }

        $this->report['purchase_invoices'] = $poCount;
        $this->report['purchase_items'] = $itemCount;
    }

    private function migrateSpkCuttings(): void
    {
        $rows = $this->data['sheets']['Potong']['rows'] ?? [];
        $stmtSpk = $this->pdo->prepare("INSERT INTO spk_cuttings 
            (id, spk_no, spk_date, cutter_employee_id, total_cut_actual_pcs, total_wage, loan_deduction, net_wage, wage_disposition, status, completed_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE total_wage=VALUES(total_wage)");

        $stmtItem = $this->pdo->prepare("INSERT INTO spk_cutting_items 
            (spk_cutting_id, product_id, qty_actual, notes) VALUES (?, ?, ?, ?)");

        $spkCount = 0;
        $itemCount = 0;

        foreach ($rows as $r) {
            $id = (int)($r['id'] ?? 0);
            if (!$id) continue;

            $spkNo = 'SPK-POT-' . substr((string)$id, -5);
            $spkDate = self::parseDate($r['tanggal'] ?? null);
            $cutterId = (int)($r['id_pegawai'] ?? 0);
            $wage = self::cleanDec($r['upah_bersih'] ?? 0);
            $deduct = self::cleanDec($r['kasbon'] ?? 0);
            $net = self::cleanDec($r['hak_upah_bersih'] ?? $wage);
            $disposition = trim((string)($r['metode_upah'] ?? 'simpan')) === 'simpan' ? 'Disimpan Hutang' : 'Pencairan Tunai';
            $status = trim((string)($r['status'] ?? 'Proses'));
            $compDate = !empty($r['tanggal_selesai']) ? self::parseDateTime($r['tanggal_selesai']) : null;

            // Calculate actual cut pcs from unpacked items
            $itemsJson = $r['items'] ?? '[]';
            $items = is_string($itemsJson) ? json_decode($itemsJson, true) : (array)$itemsJson;
            $totalPcs = 0;
            if (is_array($items)) {
                foreach ($items as $it) {
                    $totalPcs += self::cleanInt($it['qty_hasil_potong'] ?? $it['qty'] ?? 0);
                }
            }

            $stmtSpk->execute([$id, $spkNo, $spkDate, $cutterId, $totalPcs, $wage, $deduct, $net, $disposition, $status, $compDate]);
            $spkCount++;

            if (is_array($items)) {
                foreach ($items as $it) {
                    $pId = (string)($it['id_produk'] ?? '');
                    $pcs = self::cleanInt($it['qty_hasil_potong'] ?? $it['qty'] ?? 0);
                    $stmtItem->execute([$id, $pId, $pcs, null]);
                    $itemCount++;
                }
            }
        }

        $this->report['spk_cuttings'] = $spkCount;
        $this->report['spk_cutting_items'] = $itemCount;
    }

    private function migrateSpkSewings(): void
    {
        $rows = $this->data['sheets']['Jahit']['rows'] ?? [];
        $stmtSpk = $this->pdo->prepare("INSERT INTO spk_sewings 
            (id, spk_no, spk_cutting_id, spk_date, estimate_days, sewer_employee_id, is_makloon, total_assigned_pcs, total_wage, loan_deduction, net_wage, wage_disposition, status)
            VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE total_wage=VALUES(total_wage)");

        $stmtItem = $this->pdo->prepare("INSERT INTO spk_sewing_items 
            (spk_sewing_id, cutting_ref_id, product_id, qty_assigned) VALUES (?, ?, ?, ?)");

        $spkCount = 0;
        $itemCount = 0;

        foreach ($rows as $r) {
            $id = (int)($r['id'] ?? 0);
            if (!$id) continue;

            $spkNo = 'SPK-JHT-' . substr((string)$id, -5);
            $spkDate = self::parseDate($r['tanggal'] ?? null);
            $cutRef = (int)($r['id_potong'] ?? 0) ?: null;
            $days = self::cleanInt($r['estimasi_hari'] ?? 7);
            $sewerId = (int)($r['id_pegawai'] ?? 0) ?: null;
            $wage = self::cleanDec($r['upah_bersih'] ?? 0);
            $deduct = self::cleanDec($r['kasbon'] ?? 0);
            $net = self::cleanDec($r['hak_upah_bersih'] ?? $wage);
            $disposition = trim((string)($r['metode_upah'] ?? 'simpan')) === 'simpan' ? 'Disimpan Hutang' : 'Pencairan Tunai';
            $status = trim((string)($r['status'] ?? 'Proses'));

            // Parse items
            $itemsJson = $r['items'] ?? '[]';
            $items = is_string($itemsJson) ? json_decode($itemsJson, true) : (array)$itemsJson;
            $totalPcs = 0;
            if (is_array($items)) {
                foreach ($items as $it) {
                    $totalPcs += self::cleanInt($it['qty'] ?? 0);
                }
            }

            $stmtSpk->execute([$id, $spkNo, $cutRef, $spkDate, $days, $sewerId, $totalPcs, $wage, $deduct, $net, $disposition, $status]);
            $spkCount++;

            if (is_array($items)) {
                foreach ($items as $it) {
                    $pId = (string)($it['id_produk'] ?? '');
                    $cutId = (string)($it['id_potong'] ?? '');
                    $pcs = self::cleanInt($it['qty'] ?? 0);
                    $stmtItem->execute([$id, $cutId, $pId, $pcs]);
                    $itemCount++;
                }
            }
        }

        $this->report['spk_sewings'] = $spkCount;
        $this->report['spk_sewing_items'] = $itemCount;
    }

    private function migrateEmployeeLoans(): void
    {
        $rows = $this->data['sheets']['Kasbon']['rows'] ?? [];
        $stmt = $this->pdo->prepare("INSERT INTO employee_loans 
            (id, loan_code, loan_date, employee_id, account_id, principal_amount, total_paid, status, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE principal_amount=VALUES(principal_amount), total_paid=VALUES(total_paid)");
        $count = 0;
        foreach ($rows as $r) {
            $id = trim((string)($r['id'] ?? 'PIU-' . uniqid()));
            $code = $id;
            $tDate = self::parseDate($r['tanggal'] ?? null);
            $empId = (int)($r['id_pegawai'] ?? 0);
            $accId = (int)($r['id_rekening'] ?? 0) ?: null;
            $amount = self::cleanDec($r['nominal'] ?? 0);
            $paid = self::cleanDec($r['terbayar'] ?? 0);
            $status = trim((string)($r['status'] ?? 'Aktif')) === 'Lunas' ? 'Lunas' : 'Aktif';
            $notes = $r['keterangan'] ?? null;

            if ($empId > 0 && $amount > 0) {
                $stmt->execute([$id, $code, $tDate, $empId, $accId, $amount, $paid, $status, $notes]);
                $count++;
            }
        }
        $this->report['employee_loans'] = $count;
    }

    private function migrateSewingDebts(): void
    {
        $rows = $this->data['sheets']['Hutang_Konveksi']['rows'] ?? [];
        $payRows = $this->data['sheets']['Riwayat_Bayar_Hutang_Konveksi']['rows'] ?? [];

        $stmtDebt = $this->pdo->prepare("INSERT INTO sewing_debts 
            (id, debt_code, debt_date, source_type, reference_spk_no, employee_id, total_wage_earned, loan_deduction_applied, net_wage_payable, total_paid, status, idempotency_key, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE net_wage_payable=VALUES(net_wage_payable), total_paid=VALUES(total_paid)");

        $stmtPay = $this->pdo->prepare("INSERT INTO sewing_debt_payments 
            (id, payment_code, sewing_debt_id, account_id, payment_date, amount_paid, balance_before, balance_after, operator_name, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $debtCount = 0;
        $payCount = 0;

        foreach ($rows as $r) {
            $id = trim((string)($r['id_hutang'] ?? 'HTK-' . uniqid()));
            $code = $id;
            $tDate = self::parseDate($r['tanggal'] ?? null);
            $div = strtolower((string)($r['divisi'] ?? ''));
            $srcType = ($div === 'potong') ? 'SPK_POTONG' : 'SPK_JAHIT';
            $spkNo = $r['nomor_spk'] ?? null;
            $empId = (int)($r['id_pegawai'] ?? 0) ?: null;
            $totalWage = self::cleanDec($r['total_upah'] ?? 0);
            $deduct = self::cleanDec($r['kasbon_spk'] ?? 0);
            $net = self::cleanDec($r['hak_upah_bersih'] ?? $totalWage);
            $paid = self::cleanDec($r['total_dibayar'] ?? 0);
            $status = trim((string)($r['status'] ?? 'Belum Bayar'));
            $idem = $r['idempotency_key'] ?? null;
            $notes = $r['catatan'] ?? null;

            $stmtDebt->execute([$id, $code, $tDate, $srcType, $spkNo, $empId, $totalWage, $deduct, $net, $paid, $status, $idem, $notes]);
            $debtCount++;
        }

        foreach ($payRows as $pr) {
            $id = trim((string)($pr['id_bayar'] ?? 'BHTK-' . uniqid()));
            $debtId = trim((string)($pr['id_hutang'] ?? ''));
            $accId = (int)($pr['id_rekening'] ?? 0) ?: null;
            $pDate = self::parseDate($pr['tanggal_bayar'] ?? null);
            $amount = self::cleanDec($pr['nominal_bayar'] ?? 0);
            $bBefore = self::cleanDec($pr['saldo_sebelum'] ?? 0);
            $bAfter = self::cleanDec($pr['saldo_setelah'] ?? 0);
            $operator = $pr['operator'] ?? 'owner';
            $notes = $pr['catatan'] ?? null;

            if ($debtId !== '') {
                $stmtPay->execute([$id, $id, $debtId, $accId, $pDate, $amount, $bBefore, $bAfter, $operator, $notes]);
                $payCount++;
            }
        }

        $this->report['sewing_debts'] = $debtCount;
        $this->report['sewing_debt_payments'] = $payCount;
    }

    private function migrateCashTransactions(): void
    {
        $rows = $this->data['sheets']['Keuangan']['rows'] ?? [];
        $stmt = $this->pdo->prepare("INSERT INTO cash_transactions 
            (id, transaction_date, type, account_id, amount, category, reference_id, description)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE amount=VALUES(amount)");
        $count = 0;
        foreach ($rows as $r) {
            $id = (int)($r['id'] ?? 0);
            if (!$id) continue;

            $tDate = self::parseDate($r['tanggal'] ?? null);
            $type = trim((string)($r['tipe'] ?? 'Keluar')) === 'Masuk' ? 'Masuk' : 'Keluar';
            $accId = (int)($r['id_rekening'] ?? 0);
            $amount = self::cleanDec($r['nominal'] ?? 0);
            $cat = $r['kategori'] ?? 'Operasional';
            $refId = $r['id_referensi'] ?? null;
            $desc = trim((string)($r['keterangan'] ?? 'Transaksi Kas ' . $id));

            $stmt->execute([$id, $tDate, $type, $accId, $amount, $cat, $refId, $desc]);
            $count++;
        }
        $this->report['cash_transactions'] = $count;
    }

    private function migrateSales(): void
    {
        $rows = $this->data['sheets']['Penjualan']['rows'] ?? [];
        $itemRows = $this->data['sheets']['Penjualan_Item']['rows'] ?? [];

        $stmtSo = $this->pdo->prepare("INSERT INTO sales_orders 
            (id, order_no, order_date, customer_name, customer_phone, subtotal, grand_total, paid_amount, total_hpp, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE grand_total=VALUES(grand_total)");

        $stmtItem = $this->pdo->prepare("INSERT INTO sales_items 
            (id, sales_order_id, product_variant_id, qty, unit_price, subtotal, unit_hpp, total_hpp)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

        $soCount = 0;
        $itemCount = 0;

        foreach ($rows as $r) {
            $id = trim((string)($r['id_penjualan'] ?? ''));
            if (!$id) continue;
            $no = trim((string)($r['no_penjualan'] ?? $id));
            $date = self::parseDate($r['tanggal'] ?? null);
            $cust = $r['nama_pelanggan'] ?? 'Pelanggan';
            $phone = $r['no_wa'] ?? null;
            $sub = self::cleanDec($r['subtotal'] ?? 0);
            $grand = self::cleanDec($r['total_tagihan'] ?? $sub);
            $paid = self::cleanDec($r['nominal_dibayar'] ?? 0);
            $hpp = self::cleanDec($r['total_hpp'] ?? 0);
            $status = $r['status_transaksi'] ?? 'Posted';

            $stmtSo->execute([$id, $no, $date, $cust, $phone, $sub, $grand, $paid, $hpp, $status]);
            $soCount++;
        }

        foreach ($itemRows as $ir) {
            $id = trim((string)($ir['id_item'] ?? ''));
            if (!$id) continue;
            $soId = trim((string)($ir['id_penjualan'] ?? ''));
            $sku = trim((string)($ir['kode_sku'] ?? ''));
            $varId = $sku !== '' ? ('VAR-' . md5($sku)) : null;
            $qty = self::cleanInt($ir['qty'] ?? 1);
            $price = self::cleanDec($ir['harga_jual'] ?? 0);
            $sub = self::cleanDec($ir['subtotal'] ?? ($qty * $price));
            $hpp = self::cleanDec($ir['hpp_satuan'] ?? 0);
            $totHpp = self::cleanDec($ir['total_hpp'] ?? ($qty * $hpp));

            $stmtItem->execute([$id, $soId, $varId, $qty, $price, $sub, $hpp, $totHpp]);
            $itemCount++;
        }

        $this->report['sales_orders'] = $soCount;
        $this->report['sales_items'] = $itemCount;
    }
}

// Execute migration
$migrator = new MigrateSnapshotTo3NF('c:/xampp/htdocs/jago-laba/database/legacy/current_database_snapshot.json');
$result = $migrator->run();
echo "=== HASIL EKSEKUSI MIGRASI SNAPSHOT KE 3NF ===\n";
foreach ($result as $table => $cnt) {
    echo sprintf("- %-25s: %4d baris berhasil dimigrasikan\n", $table, $cnt);
}
echo "===============================================\n";

