<?php
header('Content-Type: application/json; charset=utf-8');

require_once (dirname(__DIR__, 5) . '/app/Bootstrap/bootstrap.php');
MarketplaceModule::boot();
try {
    Workspace::require(Workspace::MARKETPLACE);
    Workspace::verifyCsrf();
} catch (Throwable $e) {
    if (http_response_code() < 400) http_response_code(403);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]); exit;
}
$service = new MarketplaceService();
$apiService = new MarketplaceApiService();

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

$storeScopedActions = [
    'get_order_detail', 'update_manual_order', 'archive_order', 'add_expense', 'delete_expense', 'add_order', 'update_sku_hpp',
    'test_api_connection', 'sync_store', 'sync_marketing_ads', 'import_settlement_csv',
    'update_return_claim', 'trigger_cron_worker', 'get_journal_entries', 'update_store',
    'toggle_store_status', 'get_store_detail', 'archive_store', 'import_product_ad_report', 'update_global_product_hpp'
];
$storeIdForAction = trim((string)($_POST['store_id'] ?? $_GET['store_id'] ?? ''));
try {
    if (in_array($action, $storeScopedActions, true)) {
        if ($storeIdForAction === '') {
            throw new InvalidArgumentException('Toko aktif wajib dipilih.');
        }
        $service->assertStoreAccess($storeIdForAction);
    }

    switch ($action) {
        case 'save_hpp_batch':
            if(($_SERVER['REQUEST_METHOD']??'')!=='POST')throw new InvalidArgumentException('Gunakan POST.');
            if(!preg_match('/OWNER|ADMIN/i',(string)($_SESSION['role']??'')))throw new DomainException('Memerlukan administrator.');
            $changes=json_decode((string)($_POST['changes']??''),true,512,JSON_THROW_ON_ERROR);
            if(!is_array($changes))throw new InvalidArgumentException('Format HPP tidak valid.');
            $result=(new MarketplaceCatalogService())->updateHppBatch($changes);
            echo json_encode(['success'=>true,'data'=>$result,'message'=>$result['sku_count'].' SKU tersimpan. HPP dan laba transaksi terkait sudah diperbarui.']);
            break;
        case 'update_global_product_hpp':
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') throw new InvalidArgumentException('Gunakan POST.');
            $all=($_POST['recalculate_history']??'')==='1';
            $catalog=new MarketplaceCatalogService();$amount=trim((string)($_POST['hpp']??''));$effective=trim((string)($_POST['effective_from']??''))?:null;
            $affected=($_POST['apply_all_variants']??'')==='1'?$catalog->updateProductHpp($storeIdForAction,trim((string)($_POST['product_id']??'')),$amount,$all,$effective):$catalog->updateHpp($storeIdForAction,trim((string)($_POST['sku']??'')),$amount,$all,$effective);
            echo json_encode(['success'=>true,'message'=>"HPP tersimpan. $affected transaksi diperbarui. ".($all?'Histori dihitung ulang sesuai pilihan Anda.':'Transaksi sebelum tanggal berlaku dipertahankan.')]);
            break;
        case 'import_product_ad_report':
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') throw new InvalidArgumentException('Gunakan POST untuk mengimpor laporan.');
            $file = $_FILES['report'] ?? [];
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 2097152 || !is_uploaded_file($file['tmp_name'] ?? '')) throw new InvalidArgumentException('Unggah CSV valid maksimal 2 MB.');
            $count = $service->importProductAdReport($storeIdForAction, $file['tmp_name']);
            echo json_encode(['success' => true, 'message' => "$count laporan produk berhasil diimpor. Data periode yang sama diperbarui tanpa menggandakan biaya."]);
            break;
        // 1. Ambil Detail Pesanan (Untuk Modal Transaksi)
        case 'get_order_detail':
            $orderId = $_GET['order_id'] ?? '';
            $storeId = (string)($_GET['store_id'] ?? '');
            if (!$orderId) {
                throw new InvalidArgumentException("ID Pesanan wajib diisi.");
            }
            if ($storeId === '') throw new InvalidArgumentException('Toko aktif wajib dipilih.');
            $data = $service->getOrderDetail($orderId, $storeId);
            if (!$data) {
                throw new RuntimeException("Data pesanan $orderId tidak ditemukan.");
            }
            require_once dirname(__DIR__,2).'/Services/MarketplaceReturnsService.php';
            $store=(new MarketplaceScopeService(Database::pdo()))->assertStoreAccess($storeId);
            $data['presentation']['return_cases']=(new MarketplaceReturnsService(Database::pdo()))->forOrders($storeId,[$orderId],$store['platform'])[$orderId]??[];
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        case 'update_manual_order':
            $storeId = $storeIdForAction;
            $orderId = trim((string)($_POST['order_id'] ?? ''));
            if ($orderId === '') throw new InvalidArgumentException('Nomor pesanan wajib diisi.');
            $res = $service->updateManualOrder($orderId, $storeId, [
                'buyer_username' => trim((string)($_POST['buyer_username'] ?? '')),
                'order_status' => trim((string)($_POST['order_status'] ?? '')),
                'total_cogs_hpp' => $_POST['total_cogs_hpp'] ?? null,
            ]);
            echo json_encode($res);
            break;

        case 'archive_order':
            $orderId = trim((string)($_POST['order_id'] ?? ''));
            $reason = trim((string)($_POST['reason'] ?? ''));
            if ($orderId === '') throw new InvalidArgumentException('Nomor pesanan wajib diisi.');
            echo json_encode($service->archiveOrder($orderId, $storeIdForAction, $reason));
            break;

        // 2. Tambah Pengeluaran Baru (Tersinkron ke Kas/Bank ERP)
        case 'add_expense':
            $storeId = $_POST['store_id'] ?? 'STR-SHOPEE-JAGOIN';
            $desc = trim($_POST['description'] ?? '');
            $amount = (float)str_replace(['Rp', '.', ' ', ','], ['', '', '', '.'], $_POST['amount'] ?? '0');
            $category = $_POST['category'] ?? 'Biaya Iklan/Advertising Marketplace';
            $vendor = trim($_POST['vendor_name'] ?? '');
            $date = $_POST['expense_date'] ?? date('Y-m-d');
            $method = $_POST['payment_method'] ?? 'Transfer Bank';
            $accountId = !empty($_POST['account_id']) ? (int)$_POST['account_id'] : null;
            $notes = trim($_POST['notes'] ?? '');
            $affectsProfit = isset($_POST['affects_profit']) ? 1 : 0;
            $isCashflowOnly = isset($_POST['is_cashflow_only']) ? 1 : 0;

            if (empty($desc) || $amount <= 0) {
                throw new InvalidArgumentException("Deskripsi dan Jumlah Pengeluaran harus valid.");
            }

            $id = $service->recordExpense([
                'store_id' => $storeId,
                'description' => $desc,
                'vendor_name' => $vendor ?: 'Vendor',
                'expense_date' => $date,
                'category' => $category,
                'amount' => $amount,
                'payment_method' => $method,
                'account_id' => $accountId,
                'notes' => $notes,
                'affects_profit' => $affectsProfit,
                'is_cashflow_only' => $isCashflowOnly
            ]);

            echo json_encode([
                'success' => true,
                'expense_id' => $id,
                'message' => 'Pengeluaran marketplace berhasil dicatat.'
            ]);
            break;

        // 3. Hapus Pengeluaran
        case 'delete_expense':
            $id = (int)($_POST['id'] ?? 0);
            $storeId = (string)($_POST['store_id'] ?? '');
            if (!$id) {
                throw new InvalidArgumentException("ID Pengeluaran tidak valid.");
            }
            if ($storeId === '') throw new InvalidArgumentException('Toko aktif wajib dipilih.');
            $res = $service->deleteExpense($id, $storeId);
            echo json_encode([
                'success' => $res,
                'message' => $res ? 'Pengeluaran marketplace berhasil dihapus.' : 'Gagal menghapus pengeluaran.'
            ]);
            break;

        // 4. Tambah Pesanan Manual (Tersinkron ke Stok Gudang)
        case 'add_order':
            $storeId = $_POST['store_id'] ?? 'STR-SHOPEE-JAGOIN';
            $orderId = trim($_POST['order_id'] ?? '');
            $buyer = trim($_POST['buyer_username'] ?? 'Customer Direct');
            $gross = (float)str_replace(['Rp', '.', ' ', ','], ['', '', '', '.'], $_POST['gross_sales'] ?? '0');
            $cogs = (float)str_replace(['Rp', '.', ' ', ','], ['', '', '', '.'], $_POST['total_cogs_hpp'] ?? '0');
            $adminFee = (float)str_replace(['Rp', '.', ' ', ','], ['', '', '', '.'], $_POST['platform_admin_fee'] ?? '0');
            $serviceFee = (float)str_replace(['Rp', '.', ' ', ','], ['', '', '', '.'], $_POST['service_fee'] ?? '0');
            $paymentFee = (float)str_replace(['Rp', '.', ' ', ','], ['', '', '', '.'], $_POST['payment_fee'] ?? '0');
            $shippingAdj = (float)str_replace(['Rp', '.', ' ', ','], ['', '', '', '.'], $_POST['shipping_adjustment'] ?? '0');
            $status = $_POST['order_status'] ?? 'Selesai';
            $orderDate = $_POST['order_date'] ?? date('Y-m-d H:i:s');
            $sku = trim($_POST['sku'] ?? 'SKU-DIRECT');
            $variantId = $_POST['product_variant_id'] ?? null;
            $qty = (int)($_POST['qty'] ?? 1);

            if (empty($orderId) || $gross <= 0) {
                throw new InvalidArgumentException("Nomor Pesanan dan Nilai Penjualan harus valid.");
            }

            $orderRes = $service->recordManualOrder([
                'order_id' => $orderId,
                'store_id' => $storeId,
                'order_date' => $orderDate,
                'completion_date' => $orderDate,
                'buyer_username' => $buyer,
                'order_status' => $status,
                'gross_sales' => $gross,
                'platform_admin_fee' => $adminFee,
                'service_fee' => $serviceFee,
                'payment_fee' => $paymentFee,
                'shipping_adjustment' => $shippingAdj,
                'total_cogs_hpp' => $cogs,
                'sku' => $sku,
                'product_variant_id' => $variantId,
                'qty' => $qty
            ]);

            echo json_encode([
                'success' => true,
                'order_id' => $orderRes,
                'message' => "Pesanan $orderRes berhasil dicatat dan stok gudang konveksi telah diperbarui."
            ]);
            break;

        // 5. Update / Atur HPP & Mapping SKU
        case 'update_sku_hpp':
            $storeId = $_POST['store_id'] ?? 'STR-SHOPEE-JAGOIN';
            $sku = trim($_POST['sku'] ?? '');
            $newHpp = (float)str_replace(['Rp', '.', ' ', ','], ['', '', '', '.'], $_POST['hpp'] ?? '0');
            $variantId = $_POST['product_variant_id'] ?? null;

            if (empty($sku) || $newHpp <= 0) {
                throw new InvalidArgumentException("SKU dan HPP harus valid.");
            }

            $service->updateProductHpp($storeId, $sku, $newHpp, $variantId);

            echo json_encode([
                'success' => true,
                'message' => "HPP untuk SKU $sku berhasil diperbarui ke Rp " . number_format($newHpp, 0, ',', '.') . " dan disinkronkan ke master stok."
            ]);
            break;

        // 6. Uji Ping Koneksi API Resmi
        case 'test_api_connection':
            $storeId = $_POST['store_id'] ?? ($_GET['store_id'] ?? 'STR-SHOPEE-JAGOIN');
            $res = $apiService->testConnection($storeId);
            echo json_encode($res);
            break;

        // 7. Simpan Kredensial API Resmi
        case 'save_api_credentials':
            throw new RuntimeException('Kredensial tidak boleh dimasukkan langsung. Integrasi memerlukan OAuth resmi dan enkripsi token.');
            $storeId = $_POST['store_id'] ?? 'STR-SHOPEE-JAGOIN';
            $platform = $_POST['platform'] ?? 'Shopee';
            
            $res = $apiService->saveCredentials([
                'store_id' => $storeId,
                'platform' => $platform,
                'partner_id' => $_POST['partner_id'] ?? null,
                'partner_key' => $_POST['partner_key'] ?? null,
                'shop_id' => $_POST['shop_id'] ?? null,
                'app_key' => $_POST['app_key'] ?? null,
                'app_secret' => $_POST['app_secret'] ?? null,
                'access_token' => $_POST['access_token'] ?? null,
                'cipher' => $_POST['cipher'] ?? null,
                'webhook_url' => $_POST['webhook_url'] ?? null,
                'webhook_secret' => $_POST['webhook_secret'] ?? null
            ]);

            echo json_encode([
                'success' => $res,
                'message' => "Kredensial API resmi {$platform} berhasil disimpan dan token auto-refresh telah diaktifkan."
            ]);
            break;

        // 8. Trigger Sinkronisasi Otomatis
        case 'sync_store':
            $storeId = $_POST['store_id'] ?? ($_GET['store_id'] ?? 'STR-SHOPEE-JAGOIN');
            $res = $apiService->syncStoreData($storeId);
            echo json_encode($res);
            break;

        // 9. Sinkronisasi Iklan Marketing Real-Time (Shopee Ads & TikTok Ads)
        case 'sync_marketing_ads':
            $storeId = $_POST['store_id'] ?? ($_GET['store_id'] ?? 'STR-SHOPEE-JAGOIN');
            $res = $apiService->syncMarketingData($storeId);
            echo json_encode($res);
            break;

        // 10. Impor File Settlement CSV / Excel Seller Center
        case 'import_settlement_csv':
            $storeId = $_POST['store_id'] ?? 'STR-SHOPEE-JAGOIN';
            if (empty($_FILES['settlement_file']['tmp_name'])) {
                throw new InvalidArgumentException("Silakan pilih file CSV/Excel settlement untuk diunggah.");
            }
            $tmpPath = $_FILES['settlement_file']['tmp_name'];
            $origName = $_FILES['settlement_file']['name'];
            $res = $service->importSettlementCSV($storeId, $tmpPath, $origName);
            echo json_encode($res);
            break;

        // 11. Simulasi Hitung Harga Jual & Break-Even ROAS
        case 'simulate_price':
            $res = $service->simulatePrice($_POST);
            echo json_encode(['success' => true, 'data' => $res]);
            break;

        // 12. Daftar Simulasi Harga Tersimpan
        case 'get_saved_simulations':
            $data = $service->getSavedPriceSimulations();
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        // 13. Perbarui Alokasi Kuota Multi-Channel & Safety Buffer
        case 'update_stock_allocation':
            throw new RuntimeException('Alokasi stok marketplace menunggu migrasi inventori terpisah.');
            $id = (int)($_POST['id'] ?? 0);
            $shopeePct = (float)($_POST['shopee_quota_pct'] ?? 60.0);
            $tiktokPct = (float)($_POST['tiktok_quota_pct'] ?? 30.0);
            $offlinePct = (float)($_POST['offline_quota_pct'] ?? 10.0);
            $bufferThreshold = (int)($_POST['safety_buffer_threshold'] ?? 3);

            if (!$id) {
                throw new InvalidArgumentException("ID Alokasi Stok tidak valid.");
            }

            $service->updateStockAllocation($id, $shopeePct, $tiktokPct, $offlinePct, $bufferThreshold);
            echo json_encode([
                'success' => true,
                'message' => 'Alokasi kuota multi-channel dan safety buffer berhasil disimpan.'
            ]);
            break;

        // 14. Jalankan Pengecekan Safety Buffer & Sinkron ke API
        case 'run_safety_buffer_sync':
            throw new RuntimeException('Alokasi stok marketplace menunggu migrasi inventori terpisah.');
            break;

        // 15. Perbarui Status Klaim Retur & Ganti Rugi
        case 'update_return_claim':
            $claimId = (int)($_POST['claim_id'] ?? 0);
            $storeId = (string)($_POST['store_id'] ?? '');
            $newStatus = $_POST['claim_status'] ?? 'MENUNGGU_FISIK';
            $reimburse = (float)str_replace(['Rp', '.', ' ', ','], ['', '', '', '.'], $_POST['reimbursement_amount'] ?? '0');
            $notes = trim($_POST['notes'] ?? '');

            if (!$claimId) {
                throw new InvalidArgumentException("ID Klaim Retur tidak valid.");
            }

            if ($storeId === '') throw new InvalidArgumentException('Toko aktif wajib dipilih.');
            $res = $service->updateReturnClaimStatus($claimId, $storeId, $newStatus, $reimburse, $notes);
            echo json_encode($res);
            break;

        // 16. Trigger Background Cron Worker Manual
        case 'trigger_cron_worker':
            throw new RuntimeException('Worker konektor resmi belum aktif. Tidak ada sinkronisasi yang dijalankan.');
            $task = $_POST['task'] ?? 'all';
            if (!in_array($task, ['all','order_sync','daily_ads','safety_buffer','refresh_oauth'], true)) {
                throw new InvalidArgumentException('Jenis pekerjaan tidak diizinkan.');
            }
            $output = [];
            $retCode = 0;
            $phpBin = 'C:\\xampp\\php\\php.exe';
            $scriptPath = (dirname(__DIR__, 5) . '/bin/workers/marketplace-cron.php');
            exec(escapeshellarg($phpBin).' '.escapeshellarg($scriptPath).' '.escapeshellarg('--task='.$task).' 2>&1', $output, $retCode);
            echo json_encode([
                'success' => $retCode === 0,
                'task' => $task,
                'log_output' => implode("\n", $output),
                'message' => $retCode === 0 ? 'Background Cron Worker berhasil dijalankan.' : 'Terjadi kendala saat menjalankan worker.'
            ]);
            break;

        // 17. Ambil Buku Jurnal Berpasangan (Double-Entry Bookkeeping)
        case 'get_journal_entries':
            $storeId = $_GET['store_id'] ?? 'STR-SHOPEE-JAGOIN';
            $dateFrom = $_GET['date_from'] ?? null;
            $dateTo = $_GET['date_to'] ?? null;
            $refType = $_GET['ref_type'] ?? null;
            $res = $service->getJournalEntries($storeId, $dateFrom, $dateTo, $refType);
            echo json_encode(['success' => true, 'data' => $res]);
            break;

        // 18. Tambah Toko Marketplace Baru
        case 'add_store':
            $res = $service->createStore($_POST);
            echo json_encode($res);
            break;

        // 19. Update Detail Toko Marketplace
        case 'update_store':
            $storeId = $_POST['store_id'] ?? '';
            if (empty($storeId)) {
                throw new InvalidArgumentException("ID Toko wajib diisi.");
            }
            $res = $service->updateStore($storeId, $_POST);
            echo json_encode($res);
            break;

        // 20. Ubah Status Toko (Terhubung, Terputus, Nonaktif)
        case 'toggle_store_status':
            $storeId = $_POST['store_id'] ?? '';
            $status = $_POST['status'] ?? 'Terhubung';
            if (empty($storeId)) {
                throw new InvalidArgumentException("ID Toko wajib diisi.");
            }
            $res = $service->toggleStoreStatus($storeId, $status);
            echo json_encode($res);
            break;

        case 'archive_store':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new InvalidArgumentException('Metode tidak diizinkan.');
            echo json_encode($service->archiveStore($storeIdForAction));
            break;

        case 'restore_store':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new InvalidArgumentException('Metode tidak diizinkan.');
            $storeId = trim((string)($_POST['store_id'] ?? ''));
            if ($storeId === '') throw new InvalidArgumentException('ID toko wajib diisi.');
            echo json_encode($service->restoreStore($storeId));
            break;

        // 21. Ambil Detail Toko untuk Modal Edit
        case 'get_store_detail':
            $storeId = $_GET['store_id'] ?? ($_POST['store_id'] ?? '');
            if (empty($storeId)) {
                throw new InvalidArgumentException("ID Toko wajib diisi.");
            }
            $detail = $service->getStoreDetail($storeId);
            if (!$detail) {
                throw new RuntimeException("Toko dengan ID '$storeId' tidak ditemukan.");
            }
            echo json_encode(['success' => true, 'data' => $detail]);
            break;

        // 22. Simpan Pengaturan Konfigurasi Aplikasi Marketplace
        case 'save_app_settings':
            $res = $service->saveAppSettings($_POST);
            echo json_encode($res);
            break;

        default:
            throw new BadMethodCallException("Action '$action' tidak dikenali.");
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

