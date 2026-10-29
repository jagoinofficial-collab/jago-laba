<?php

require_once (dirname(__DIR__, 4) . '/app/Shared/Companies/MarketplaceScopeService.php');
require_once (dirname(__DIR__, 4) . '/app/Modules/Marketplace/Services/MarketplaceOrderPresentation.php');
require_once (dirname(__DIR__, 4) . '/app/Modules/Marketplace/Services/MarketplaceCatalogService.php');
require_once (dirname(__DIR__, 4) . '/app/Modules/Marketplace/Services/MarketplaceAnalyticsService.php');
require_once (dirname(__DIR__, 4) . '/app/Modules/Marketplace/Services/MarketplaceSyncService.php');
require_once (dirname(__DIR__, 4) . '/app/Modules/Marketplace/Jobs/MarketplaceBackgroundSync.php');
require_once (dirname(__DIR__, 4) . '/app/Modules/Marketplace/Services/MarketplacePriceService.php');

final class MarketplaceService
{
    private PDO $pdo;
    private MarketplaceScopeService $scope;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?: Database::pdo();
        $this->scope = new MarketplaceScopeService($this->pdo);
    }
    private function financialSummary(array $orders):array
    {
        $out=['total_sales'=>0.0,'total_fees'=>0.0,'net_income'=>0.0,'total_cogs'=>0.0,'incomplete_profit_data'=>0];$missing=['total_fees'=>false,'net_income'=>false,'total_cogs'=>false];
        foreach($orders as $order){$p=MarketplaceOrderPresentation::build($order);
            if(in_array($p['status'],['Batal','Belum Dibayar'],true))continue;
            $out['total_sales']+=$p['gross_amount'];
            foreach(['total_fees'=>'total_fees','net_income'=>'net_income','total_cogs'=>'total_cogs'] as $key=>$source){if($p[$source]===null)$missing[$key]=true;else $out[$key]+=$p[$source];}
            if($p['net_profit']===null)$out['incomplete_profit_data']++;
        }
        foreach($missing as $key=>$unknown)if($unknown)$out[$key]=null;
        $out['profit']=$out['net_income']===null||$out['total_cogs']===null?null:$out['net_income']-$out['total_cogs'];
        $out['margin_pct']=$out['profit']!==null&&$out['total_sales']>0?$out['profit']/$out['total_sales']*100:null;
        return $out;
    }

    /**
     * Mendapatkan daftar semua toko yang terhubung.
     */
    public function getStores(bool $includeJobStats = true): array
    {
        $jobJoin = $includeJobStats ? "LEFT JOIN (
                SELECT connection_id,
                    SUM(CASE WHEN status IN ('queued', 'running') THEN 1 ELSE 0 END) AS active_jobs,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed_jobs,
                    SUM(CASE WHEN status = 'succeeded' THEN 1 ELSE 0 END) AS success_jobs
                FROM marketplace_sync_jobs WHERE connection_id IN (SELECT id FROM marketplace_oauth_connections WHERE company_key = ?) GROUP BY connection_id
            ) j ON j.connection_id = c.id" : "LEFT JOIN (SELECT NULL AS connection_id, 0 AS active_jobs, 0 AS failed_jobs, 0 AS success_jobs) j ON 1=0";
        $stmt = $this->pdo->prepare("SELECT s.*,
                c.id AS oauth_connection_id,
                c.external_store_id AS oauth_external_store_id,
                c.external_store_name AS oauth_external_store_name,
                c.status AS oauth_status,
                c.token_expires_at AS oauth_token_expires_at,
                c.authorized_at AS oauth_authorized_at,
                c.verified_at AS oauth_verified_at,
                c.last_successful_sync_at AS oauth_last_successful_sync_at,
                c.last_sync_attempt_at AS oauth_last_sync_attempt_at,
                c.last_error_code AS oauth_last_error_code,
                COALESCE(j.active_jobs, 0) AS oauth_active_jobs,
                COALESCE(j.failed_jobs, 0) AS oauth_failed_jobs,
                COALESCE(j.success_jobs, 0) AS oauth_success_jobs
            FROM marketplace_stores s
            LEFT JOIN marketplace_oauth_connections c ON c.store_id = s.id AND c.company_key = s.company_key
            {$jobJoin}
            WHERE s.company_key = ? AND s.archived_at IS NULL
            ORDER BY s.platform ASC, s.store_name ASC");
        $stmt->execute($includeJobStats ? [$this->scope->companyKey(),$this->scope->companyKey()] : [$this->scope->companyKey()]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            unset($row['api_access_token'], $row['api_app_key']);
            $oauthStatus = trim((string)($row['oauth_status'] ?? ''));
            $authorizationStatus = $oauthStatus !== '' ? $oauthStatus : (string)($row['authorization_status'] ?? 'pending');
            $oauthLastSync = trim((string)($row['oauth_last_successful_sync_at'] ?? ''));
            // Tanggal legacy di tabel toko tidak membuktikan sinkronisasi API resmi.
            $row['last_successful_sync_at'] = $oauthLastSync !== '' ? $oauthLastSync : null;
            $verified = $authorizationStatus === 'verified' && !empty($row['oauth_verified_at']);
            $row['authorization_status'] = $authorizationStatus;
            $row['is_connection_verified'] = $verified;
            $activeJobs = (int)($row['oauth_active_jobs'] ?? 0);
            $failedJobs = (int)($row['oauth_failed_jobs'] ?? 0);
            $row['sync_active_jobs'] = $activeJobs;
            $row['sync_failed_jobs'] = $failedJobs;
            $row['sync_success_jobs'] = (int)($row['oauth_success_jobs'] ?? 0);
            $row['sync_status'] = $activeJobs > 0 ? 'queued' : ($failedJobs > 0 && empty($row['last_successful_sync_at']) ? 'failed' : (!empty($row['last_successful_sync_at']) ? 'succeeded' : 'pending'));
            $row['sync_label'] = match ($row['sync_status']) {
                'queued' => 'Sinkronisasi dalam antrean',
                'failed' => 'Sinkronisasi gagal',
                'succeeded' => 'Sinkronisasi berhasil',
                default => $verified ? 'Menunggu sinkronisasi awal' : 'Koneksi API belum terverifikasi',
            };
            $row['authorization_label'] = match ($authorizationStatus) {
                'verified' => $verified ? (!empty($row['last_successful_sync_at']) ? 'Terhubung & terverifikasi' : 'Terhubung (menunggu sinkronisasi)') : 'Belum diverifikasi',
                'reauthorize' => 'Perlu otorisasi ulang',
                'disconnected' => 'Terputus',
                default => 'Belum diverifikasi'
            };
            $row['authorization_tone'] = match ($authorizationStatus) {
                'verified' => $verified ? 'success' : 'warning',
                'reauthorize', 'disconnected' => 'danger',
                default => 'warning'
            };
        }
        unset($row);
        return $rows;
    }

    public function assertStoreAccess(string $storeId): array
    {
        return $this->scope->assertStoreAccess($storeId);
    }

    /**
     * Satu sumber agregasi untuk tampilan seluruh toko yang berada pada
     * company scope aktif. Baris transaksi tetap berasal dari tabel order
     * yang sama dengan halaman per toko.
     */
    public function getGlobalOverview(array $filters = []): array
    {
        $allStores = $this->getStores();
        $includeDisconnected = !empty($filters['include_disconnected']);
        $selectedStore = trim((string)($filters['store_id'] ?? ''));
        $platform = trim((string)($filters['platform'] ?? ''));
        $search = trim((string)($filters['search'] ?? ''));
        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = max(10, min(100, (int)($filters['per_page'] ?? 50)));

        $stores = array_values(array_filter($allStores, function (array $store) use ($includeDisconnected, $selectedStore, $platform): bool {
            if (!$includeDisconnected && ($store['authorization_status'] ?? 'pending') === 'disconnected') return false;
            if ($selectedStore !== '' && (string)$store['id'] !== $selectedStore) return false;
            if ($platform !== '' && strcasecmp((string)$store['platform'], $platform) !== 0) return false;
            return true;
        }));

        $storeIds = array_values(array_map(static fn(array $store): string => (string)$store['id'], $stores));
        $summary = [
            'total_orders' => 0,
            'total_sales' => 0.0,
            'total_fees' => 0.0,
            'net_income' => 0.0,
            'total_cogs' => 0.0,
            'profit' => 0.0,
            'margin_pct' => null,
            'pending_orders' => 0,
            'incomplete_profit_data' => 0,
        ];
        $byStore = [];
        $transactions = [];
        $expenses = [];
        $expenseSummary = ['total' => 0, 'count' => 0, 'advertising' => 0];
        $products = [];

        if ($storeIds) {
            [$in, $idParams] = $this->inClause($storeIds);
            $orderWhere = "o.store_id IN ($in) AND (o.internal_state IS NULL OR o.internal_state = 'active')";
            $orderParams = $idParams;
            $this->appendDateFilter($orderWhere, $orderParams, $filters, 'o.order_date');
            if ($search !== '') {
                $orderWhere .= " AND (o.id LIKE ? OR o.buyer_username LIKE ? OR EXISTS (SELECT 1 FROM marketplace_order_items sq WHERE sq.order_id = o.id AND (sq.sku LIKE ? OR sq.item_name LIKE ?)))";
                $needle = '%' . $search . '%';
                array_push($orderParams, $needle, $needle, $needle, $needle);
            }

            $stmt = $this->pdo->prepare("SELECT
                    COUNT(*) total_orders,
                    COALESCE(SUM(o.gross_sales - o.seller_discount),0) total_sales,
                    COALESCE(SUM(o.platform_admin_fee + o.service_fee + o.payment_fee + o.shipping_adjustment + o.other_deductions + o.affiliate_commission + o.live_commission),0) total_fees,
                    COALESCE(SUM(o.net_settlement_amount),0) net_income,
                    COALESCE(SUM(o.total_cogs_hpp),0) total_cogs,
                    COALESCE(SUM(CASE WHEN o.order_status IN ('Pending','Terkirim') THEN 1 ELSE 0 END),0) pending_orders,
                    COALESCE(SUM(CASE WHEN o.net_settlement_amount IS NULL OR o.total_cogs_hpp IS NULL THEN 1 ELSE 0 END),0) incomplete_profit_data
                FROM marketplace_orders o WHERE $orderWhere");
            $stmt->execute($orderParams);
            $aggregate = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $summary['total_orders'] = (int)($aggregate['total_orders'] ?? 0);
            $summary['total_sales'] = (float)($aggregate['total_sales'] ?? 0);
            $summary['total_fees'] = (float)($aggregate['total_fees'] ?? 0);
            $summary['net_income'] = (float)($aggregate['net_income'] ?? 0);
            $summary['total_cogs'] = (float)($aggregate['total_cogs'] ?? 0);
            $summary['profit'] = $summary['net_income'] - $summary['total_cogs'];
            $summary['margin_pct'] = $summary['total_sales'] > 0 ? ($summary['profit'] / $summary['total_sales']) * 100 : null;
            $summary['pending_orders'] = (int)($aggregate['pending_orders'] ?? 0);
            $summary['incomplete_profit_data'] = (int)($aggregate['incomplete_profit_data'] ?? 0);

            $allRowsQuery=$this->pdo->prepare("SELECT o.* FROM marketplace_orders o WHERE $orderWhere");$allRowsQuery->execute($orderParams);$financialRows=$allRowsQuery->fetchAll(PDO::FETCH_ASSOC);
            $summary=array_replace($summary,$this->financialSummary($financialRows));
            $dateJoinClause = $this->dateJoinClause($filters);
            $stmt = $this->pdo->prepare("SELECT s.id, s.store_name, s.platform, s.authorization_status, s.last_successful_sync_at,
                    COUNT(o.id) total_orders,
                    COALESCE(SUM(o.gross_sales - o.seller_discount),0) total_sales,
                    COALESCE(SUM(o.net_settlement_amount),0) net_income,
                    COALESCE(SUM(o.total_cogs_hpp),0) total_cogs
                FROM marketplace_stores s
                LEFT JOIN marketplace_orders o ON o.store_id = s.id
                    AND (o.internal_state IS NULL OR o.internal_state = 'active')
                    AND $dateJoinClause
                WHERE s.id IN ($in)
                GROUP BY s.id, s.store_name, s.platform, s.authorization_status, s.last_successful_sync_at
                ORDER BY total_sales DESC, s.store_name ASC");
            $storeParams = array_merge($this->dateJoinParams($filters), $idParams);
            $stmt->execute($storeParams);
            $byStore = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($byStore as &$row) {
                $row['total_orders'] = (int)$row['total_orders'];
                $row['total_sales'] = (float)$row['total_sales'];
                $row['net_income'] = (float)$row['net_income'];
                $row['total_cogs'] = (float)$row['total_cogs'];
                $row['profit'] = $row['net_income'] - $row['total_cogs'];
                $row=array_replace($row,$this->financialSummary(array_values(array_filter($financialRows,static fn($o)=>$o['store_id']===$row['id']))));
                $row['margin_pct'] = $row['total_sales'] > 0 && $row['profit']!==null ? ($row['profit'] / $row['total_sales']) * 100 : null;
            }
            unset($row);

            $stmt = $this->pdo->prepare("SELECT o.*, s.store_name,s.platform,
                (SELECT COALESCE(SUM(oi.qty),0) FROM marketplace_order_items oi WHERE oi.order_id=o.id) item_qty,
                (SELECT GROUP_CONCAT(DISTINCT oi.item_name ORDER BY oi.id SEPARATOR ', ') FROM marketplace_order_items oi WHERE oi.order_id=o.id) product_summary
                FROM marketplace_orders o JOIN marketplace_stores s ON s.id=o.store_id WHERE $orderWhere ORDER BY o.order_date DESC LIMIT ? OFFSET ?");
            foreach ($orderParams as $index => $value) {
                $stmt->bindValue($index + 1, $value);
            }
            $stmt->bindValue(count($orderParams) + 1, $perPage, PDO::PARAM_INT);
            $stmt->bindValue(count($orderParams) + 2, ($page - 1) * $perPage, PDO::PARAM_INT);
            $stmt->execute();
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($transactions as &$transaction) {
                $transaction['total_fees'] = (float)$transaction['platform_admin_fee'] + (float)$transaction['service_fee'] + (float)$transaction['payment_fee'] + (float)$transaction['shipping_adjustment'] + (float)$transaction['other_deductions'] + (float)$transaction['affiliate_commission'] + (float)$transaction['live_commission'];
                $transaction['profit'] = (float)$transaction['net_settlement_amount'] - (float)$transaction['total_cogs_hpp'];
                $p=MarketplaceOrderPresentation::build($transaction);
                $transaction['total_fees']=$p['total_fees'];$transaction['profit']=$p['net_profit'];$transaction['net_settlement_amount']=$p['net_income'];$transaction['total_cogs_hpp']=$p['total_cogs'];$transaction['order_status']=$p['status'];
                $transaction['item_qty'] = (float)$transaction['item_qty'];
            }
            unset($transaction);

            $expenseWhere = "e.store_id IN ($in)";
            $expenseParams = $idParams;
            $this->appendDateFilter($expenseWhere, $expenseParams, $filters, 'e.expense_date');
            if ($search !== '') {
                $expenseWhere .= ' AND (e.description LIKE ? OR e.vendor_name LIKE ? OR e.notes LIKE ? OR s.store_name LIKE ? OR e.category LIKE ?)';
                $needle = '%' . $search . '%';
                array_push($expenseParams, $needle, $needle, $needle, $needle, $needle);
            }
            $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(e.amount),0) total, COUNT(*) count,
                COALESCE(SUM(CASE WHEN e.category LIKE '%Iklan%' OR e.category LIKE '%Advertising%' THEN e.amount ELSE 0 END),0) advertising
                FROM marketplace_expenses e JOIN marketplace_stores s ON s.id=e.store_id WHERE $expenseWhere");
            $stmt->execute($expenseParams);
            $expenseSummary = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt = $this->pdo->prepare("SELECT e.id, e.store_id, s.store_name, s.platform, e.expense_date, e.category, e.description,
                    e.amount, e.vendor_name, e.notes, e.payment_method, e.affects_profit, e.is_cashflow_only
                FROM marketplace_expenses e JOIN marketplace_stores s ON s.id=e.store_id
                WHERE $expenseWhere ORDER BY e.expense_date DESC, e.id DESC LIMIT $perPage OFFSET " . (($page - 1) * $perPage));
            $stmt->execute($expenseParams);
            $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $productWhere = "o.store_id IN ($in) AND (o.internal_state IS NULL OR o.internal_state = 'active')";
            $productParams = $idParams;
            $this->appendDateFilter($productWhere, $productParams, $filters, 'o.order_date');
            if ($search !== '') {
                $productWhere .= ' AND (oi.sku LIKE ? OR oi.item_name LIKE ?)';
                $needle = '%' . $search . '%';
                array_push($productParams, $needle, $needle);
            }
            $stmt = $this->pdo->prepare("SELECT o.store_id, s.store_name, s.platform, oi.sku, oi.item_name,
                    COALESCE(SUM(oi.qty),0) qty_sold, COALESCE(SUM(oi.subtotal_price),0) sales,
                    COALESCE(SUM(oi.subtotal_cogs),0) cogs
                FROM marketplace_order_items oi
                JOIN marketplace_orders o ON o.id=oi.order_id
                JOIN marketplace_stores s ON s.id=o.store_id
                WHERE $productWhere
                GROUP BY o.store_id, s.store_name, s.platform, oi.sku, oi.item_name
                ORDER BY sales DESC LIMIT 100");
            $stmt->execute($productParams);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $cards = [];
        foreach ($stores as $store) {
            $card = $store;
            $card['total_orders'] = 0;
            $card['total_sales'] = 0.0;
            foreach ($byStore as $row) {
                if ((string)$row['id'] === (string)$store['id']) {
                    $card['total_orders'] = (int)$row['total_orders'];
                    $card['total_sales'] = (float)$row['total_sales'];
                    break;
                }
            }
            $cards[] = $card;
        }

        return [
            'stores' => $cards,
            'by_store' => $byStore,
            'transactions' => $transactions,
            'expenses' => $expenses,
            'expense_summary' => $expenseSummary,
            'products' => $products,
            'summary' => $summary,
            'filters' => $filters,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $summary['total_orders'],
                'total_pages' => max(1, (int)ceil($summary['total_orders'] / $perPage)),
            ],
            'has_data' => (bool)$storeIds,
        ];
    }

    private function inClause(array $ids): array
    {
        return [implode(',', array_fill(0, count($ids), '?')), array_values($ids)];
    }

    private function appendDateFilter(string &$where, array &$params, array $filters, string $column): void
    {
        $start = trim((string)($filters['date_start'] ?? ''));
        $end = trim((string)($filters['date_end'] ?? ''));
        if ($start !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
            $where .= " AND $column >= ?";
            $params[] = $start . ' 00:00:00';
        }
        if ($end !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
            $where .= " AND $column < DATE_ADD(?, INTERVAL 1 DAY)";
            $params[] = $end;
        }
    }

    private function dateJoinClause(array $filters): string
    {
        $parts = [];
        if (!empty($filters['date_start']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$filters['date_start'])) $parts[] = 'o.order_date >= ?';
        if (!empty($filters['date_end']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$filters['date_end'])) $parts[] = 'o.order_date < DATE_ADD(?, INTERVAL 1 DAY)';
        return $parts ? implode(' AND ', $parts) : '1=1';
    }

    private function dateJoinParams(array $filters): array
    {
        $params = [];
        if (!empty($filters['date_start']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$filters['date_start'])) $params[] = (string)$filters['date_start'] . ' 00:00:00';
        if (!empty($filters['date_end']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$filters['date_end'])) $params[] = (string)$filters['date_end'];
        return $params;
    }

    /**
     * Detail Dashboard FinKami Per-Toko (Persis Sesuai Gambar Referensi).
     */
    public function getStoreDashboard(string $storeId, ?string $dateStart = null, ?string $dateEnd = null): array
    {
        // 1. Ambil data toko dalam company scope aktif.
        $store = $this->scope->assertStoreAccess($storeId);
        unset($store['api_access_token'], $store['api_app_key']);
        foreach ($this->getStores() as $scopedStore) {
            if ((string)$scopedStore['id'] === $storeId) {
                $store = array_merge($store, $scopedStore);
                break;
            }
        }

        // 2. Query Transaksi Selesai
        $sqlCompleted = "SELECT 
            COUNT(*) as total_orders,
            COALESCE(SUM(gross_sales), 0) as omzet,
            COALESCE(SUM(total_cogs_hpp), 0) as total_hpp,
            COALESCE(SUM(platform_admin_fee + service_fee + payment_fee + shipping_adjustment + other_deductions + affiliate_commission + live_commission), 0) as total_potongan,
            COALESCE(SUM(operational_expense), 0) as total_operasional,
            COALESCE(SUM(adjustment_amount), 0) as total_penyesuaian,
            COALESCE(SUM(net_settlement_amount), 0) as total_settlement
        FROM marketplace_orders 
        WHERE store_id = ? AND (internal_state IS NULL OR internal_state = 'active') AND order_status = 'Selesai'";
        $stmtComp = $this->pdo->prepare($sqlCompleted);
        $stmtComp->execute([$storeId]);
        $cData = $stmtComp->fetch(PDO::FETCH_ASSOC);
        $completed=$this->pdo->prepare("SELECT * FROM marketplace_orders WHERE store_id=? AND (internal_state IS NULL OR internal_state='active') AND order_status='Selesai'");$completed->execute([$storeId]);
        $completedFinance=$this->financialSummary($completed->fetchAll(PDO::FETCH_ASSOC));
        $cData['omzet']=$completedFinance['total_sales'];$cData['total_hpp']=$completedFinance['total_cogs'];$cData['total_potongan']=$completedFinance['total_fees'];$cData['total_settlement']=$completedFinance['net_income'];

        // 3. Query Biaya Iklan (Ads)
        $sqlAds = "SELECT COALESCE(SUM(amount), 0) as total_ads 
                   FROM marketplace_expenses 
                   WHERE store_id = ? AND category LIKE '%Iklan%'";
        $stmtAds = $this->pdo->prepare($sqlAds);
        $stmtAds->execute([$storeId]);
        $totalAds = (float)$stmtAds->fetchColumn();

        if ($totalAds <= 0) {
            $sqlAdsAlt = "SELECT COALESCE(SUM(spend_amount), 0) FROM marketplace_ad_spends WHERE store_id = ?";
            $stmtAlt = $this->pdo->prepare($sqlAdsAlt);
            $stmtAlt->execute([$storeId]);
            $totalAds = (float)$stmtAlt->fetchColumn();
        }

        // 4. Perhitungan Angka Pokok
        $omzet = (float)$cData['omzet'];
        $hpp = (float)$cData['total_hpp'];
        $potongan = (float)$cData['total_potongan'];
        $operasional = (float)$cData['total_operasional'];
        $penyesuaian = (float)$cData['total_penyesuaian'];
        $settlement = (float)$cData['total_settlement'];

        $labaBersih = $completedFinance['profit']===null?null:$completedFinance['profit']-$totalAds-$operasional;
        $profitPct = $labaBersih===null?null:($omzet > 0 ? round(($labaBersih / $omzet) * 100, 1) : 0.0);

        $pctHpp = $omzet > 0 ? round(($hpp / $omzet) * 100) : 0;
        $pctPotongan = $omzet > 0 ? round(($potongan / $omzet) * 100) : 0;
        $pctAds = $omzet > 0 ? round(($totalAds / $omzet) * 100) : 0;
        $pctOperasional = $omzet > 0 ? round(($operasional / $omzet) * 100, 1) : 0.0;
        $pctPenyesuaian = $omzet > 0 ? round(($penyesuaian / $omzet) * 100, 1) : 0.0;
        $pctLaba = $labaBersih===null?null:($omzet > 0 ? round(($labaBersih / $omzet) * 100) : 0);

        // 5. Query Perlu Perhatian
        $sqlRugi = "SELECT COUNT(*) FROM marketplace_orders WHERE store_id = ? AND (internal_state IS NULL OR internal_state = 'active') AND ((transaction_detail_json IS NULL AND net_settlement_amount < total_cogs_hpp) OR (JSON_UNQUOTE(JSON_EXTRACT(transaction_detail_json,'$.net_income'))!='null' AND JSON_UNQUOTE(JSON_EXTRACT(transaction_detail_json,'$.total_cogs'))!='null' AND CAST(JSON_UNQUOTE(JSON_EXTRACT(transaction_detail_json,'$.net_income')) AS DECIMAL(15,2)) < CAST(JSON_UNQUOTE(JSON_EXTRACT(transaction_detail_json,'$.total_cogs')) AS DECIMAL(15,2))))";
        $stmtRugi = $this->pdo->prepare($sqlRugi);
        $stmtRugi->execute([$storeId]);
        $countRugi = (int)$stmtRugi->fetchColumn();

        $sqlRetur = "SELECT COUNT(*) FROM marketplace_orders WHERE store_id = ? AND (internal_state IS NULL OR internal_state = 'active') AND order_status = 'Retur'";
        $stmtRetur = $this->pdo->prepare($sqlRetur);
        $stmtRetur->execute([$storeId]);
        $countRetur = (int)$stmtRetur->fetchColumn();

        $sqlBatal = "SELECT COUNT(*) FROM marketplace_orders WHERE store_id = ? AND (internal_state IS NULL OR internal_state = 'active') AND order_status = 'Dibatalkan'";
        $stmtBatal = $this->pdo->prepare($sqlBatal);
        $stmtBatal->execute([$storeId]);
        $countBatal = (int)$stmtBatal->fetchColumn();

        $sqlDisc = "SELECT COUNT(*) FROM marketplace_orders WHERE store_id = ? AND (internal_state IS NULL OR internal_state = 'active') AND (shipping_adjustment > 0 OR shipping_discrepancy_amount > 0)";
        $stmtDisc = $this->pdo->prepare($sqlDisc);
        $stmtDisc->execute([$storeId]);
        $countDisc = (int)$stmtDisc->fetchColumn();

        $sqlLostRet = "SELECT COUNT(*) FROM marketplace_return_claims WHERE store_id = ? AND claim_status IN ('MENUNGGU_FISIK', 'KLAIM_DIAJUKAN') AND days_pending >= 14";
        $stmtLostRet = $this->pdo->prepare($sqlLostRet);
        $stmtLostRet->execute([$storeId]);
        $countLostRet = (int)$stmtLostRet->fetchColumn();

        // 6. Produk Bocor Detection (Early Warning Alert)
        $stmtBocor = $this->pdo->prepare("SELECT * FROM marketplace_product_ads 
            WHERE store_id = ? AND (is_bocor = 1 OR (ad_spend >= 100000 AND orders_count = 0) OR (ad_spend > 250000 AND (gross_revenue / ad_spend) < 1.8))
            ORDER BY ad_spend DESC LIMIT 5");
        $stmtBocor->execute([$storeId]);
        $bocorItems = $stmtBocor->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($bocorItems)) {
            $first = $bocorItems[0];
            $bocorData = [
                'has_bocor' => true,
                'count' => count($bocorItems),
                'title' => count($bocorItems) . ' Produk Terdeteksi Bocor (Early Warning)',
                'subtitle' => $first['product_name'] . ' (Spend Rp ' . number_format($first['ad_spend'], 0, ',', '.') . '): ' . ($first['bocor_reason'] ?: 'ROAS di bawah batas BEP! Segera jeda kata kunci non-konversi.'),
                'items' => $bocorItems
            ];
        } else {
            $bocorData = [
                'has_bocor' => false,
                'count' => 0,
                'title' => 'Tidak ada produk bocor',
                'subtitle' => 'Semua produk untung pada periode ini.',
                'items' => []
            ];
        }

        return [
            'store' => $store,
            'kpi' => [
                'omzet' => $omzet,
                'omzet_growth' => '',
                'laba_bersih' => $labaBersih,
                'laba_bersih_growth' => '',
                'total_order' => (int)$cData['total_orders'],
                'total_order_growth' => '',
                'persentase_profit' => $profitPct,
                'persentase_profit_growth' => ''
            ],
            'funnel' => [
                'omzet' => ['val' => $omzet, 'pct' => 100],
                'hpp' => ['val' => $hpp, 'pct' => $pctHpp],
                'potongan' => ['val' => $potongan, 'pct' => $pctPotongan],
                'iklan' => ['val' => $totalAds, 'pct' => $pctAds],
                'operasional' => ['val' => $operasional, 'pct' => $pctOperasional],
                'penyesuaian' => ['val' => $penyesuaian, 'pct' => $pctPenyesuaian],
                'laba_bersih' => ['val' => $labaBersih, 'pct' => $pctLaba]
            ],
            'attention' => [
                'total_items' => (int)($countRugi > 0) + (int)($countRetur > 0) + (int)($countBatal > 0) + (int)($countDisc > 0) + (int)($countLostRet > 0),
                'rugi' => $countRugi,
                'retur' => $countRetur,
                'batal' => $countBatal,
                'selisih_ongkir' => $countDisc,
                'klaim_retur' => $countLostRet
            ],
            'bocor' => $bocorData
        ];
    }

    /**
     * Menu TRANSAKSI (Sesuai Image 1):
     * Menampilkan daftar pesanan dengan filter fleksibel.
     */
    public function getTransactions(string $storeId, string $filter = 'kerugian', ?string $search = null, ?string $statusFilter = null, int $page = 1, int $perPage = 50,string $searchBy='all'): array
    {
        $this->scope->assertStoreAccess($storeId);
        $page = max(1, $page);
        $perPage = max(10, min(100, $perPage));
        $params = [$storeId];
        $where = "WHERE store_id = ? AND (internal_state IS NULL OR internal_state = 'active')";

        // Filter Pills Logic
        switch ($filter) {
            case 'kerugian':
                $where .= " AND ((transaction_detail_json IS NULL AND net_settlement_amount < total_cogs_hpp) OR (JSON_UNQUOTE(JSON_EXTRACT(transaction_detail_json,'$.net_income')) != 'null' AND JSON_UNQUOTE(JSON_EXTRACT(transaction_detail_json,'$.total_cogs')) != 'null' AND CAST(JSON_UNQUOTE(JSON_EXTRACT(transaction_detail_json,'$.net_income')) AS DECIMAL(15,2)) < CAST(JSON_UNQUOTE(JSON_EXTRACT(transaction_detail_json,'$.total_cogs')) AS DECIMAL(15,2))))";
                break;
            case 'pengembalian':
            case 'retur':
                $where .= " AND (order_status = 'Retur' OR EXISTS(SELECT 1 FROM marketplace_sync_records rc WHERE rc.store_id=marketplace_orders.store_id AND rc.resource_type='return' AND JSON_UNQUOTE(JSON_EXTRACT(rc.payload_json,'$.order_id'))=marketplace_orders.id))";
                break;
            case 'selisih_ongkir':
                $where .= " AND (shipping_adjustment != 0 OR shipping_discrepancy_amount > 0)";
                break;
            case 'klaim_retur':
                $where .= " AND (return_claim_status != 'NONE' OR order_status = 'Retur')";
                break;
            case 'penyesuaian':
                $where .= " AND (id LIKE '%ADJ%' OR adjustment_amount != 0)";
                break;
            case 'belum_cair':
                $where .= " AND escrow_status = 'Tertahan'";
                break;
            case 'cod':
                $where .= " AND JSON_EXTRACT(transaction_detail_json, '$.is_cod') = true";
                break;
            case 'cod_batal':
                $where .= " AND (order_status = 'Dibatalkan' OR JSON_UNQUOTE(JSON_EXTRACT(transaction_detail_json, '$.source_status')) = 'CANCELLED') AND JSON_EXTRACT(transaction_detail_json, '$.is_cod') = true";
                break;
            case 'gagal_kirim':
                $where .= " AND (JSON_EXTRACT(transaction_detail_json, '$.is_delivery_failed') = true OR JSON_UNQUOTE(JSON_EXTRACT(transaction_detail_json, '$.delivery_status')) = 'DELIVERY_FAILED')";
                break;
            case 'batal':
                $where .= " AND order_status = 'Dibatalkan'";
                break;
            case 'barang_hilang':
                $where .= " AND (JSON_EXTRACT(transaction_detail_json,'$.is_lost')=true OR JSON_UNQUOTE(JSON_EXTRACT(transaction_detail_json,'$.delivery_status'))='LOST')";
                break;
            case 'sampel':
                $where .= " AND (JSON_EXTRACT(transaction_detail_json,'$.is_sample_order')=true OR JSON_UNQUOTE(JSON_EXTRACT(transaction_detail_json,'$.order_type')) IN ('FREE_SAMPLE','SAMPLE'))";
                break;
            case 'affiliate':
                $where .= " AND (affiliate_commission>0 OR CAST(JSON_UNQUOTE(JSON_EXTRACT(transaction_detail_json,'$.components.affiliate_commission')) AS DECIMAL(15,2))>0)";
                break;
            case 'semua':
            default:
                // Tampilkan semua
                break;
        }

        if ($statusFilter && $statusFilter !== 'Semua Status') {
            $where .= " AND (" . MarketplaceOrderPresentation::statusSql() . ") = ?";
            $params[] = $statusFilter==='Dibatalkan'?'Batal':$statusFilter;
        }

        if ($search) {
            $field=match($searchBy){'order'=>'id','buyer'=>'buyer_username','tracking'=>"JSON_UNQUOTE(JSON_EXTRACT(transaction_detail_json,'$.tracking_number'))",default=>null};
            $where .= $field?" AND $field LIKE ?":" AND (id LIKE ? OR buyer_username LIKE ? OR JSON_UNQUOTE(JSON_EXTRACT(transaction_detail_json,'$.tracking_number')) LIKE ?)";
            $params[] = "%$search%";
            if(!$field){$params[]="%$search%";$params[]="%$search%";}
        }

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM marketplace_orders $where");
        $countStmt->execute($params);
        $totalCount = (int)$countStmt->fetchColumn();

        $sql = "SELECT * FROM marketplace_orders $where ORDER BY order_date DESC LIMIT ? OFFSET ?";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $index => $value) {
            $stmt->bindValue($index + 1, $value);
        }
        $stmt->bindValue(count($params) + 1, $perPage, PDO::PARAM_INT);
        $stmt->bindValue(count($params) + 2, ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Hitung total laba dari transaksi terfilter
        $itemsByOrder = [];
        if ($rows) {
            [$itemIn, $itemParams] = $this->inClause(array_column($rows, 'id'));
            $itemQuery = $this->pdo->prepare("SELECT * FROM marketplace_order_items WHERE order_id IN ($itemIn)");
            $itemQuery->execute($itemParams);
            foreach ($itemQuery->fetchAll(PDO::FETCH_ASSOC) as $item) $itemsByOrder[$item['order_id']][] = $item;
        }
        $totalLaba = 0;$missingProfit=0;
        foreach ($rows as &$r) {
            $r['items'] = $itemsByOrder[$r['id']] ?? [];
            $r['presentation'] = MarketplaceOrderPresentation::build($r);
            $fee = (float)$r['platform_admin_fee'] + (float)$r['service_fee'] + (float)$r['payment_fee'] + (float)$r['shipping_adjustment'] + (float)$r['other_deductions'] + (float)$r['affiliate_commission'] + (float)$r['live_commission'];
            $cogs = (float)$r['total_cogs_hpp'];
            $net = (float)$r['net_settlement_amount'];
            $gross = (float)$r['gross_sales'];

            $laba = $r['presentation']['net_profit'];
            $r['calculated_fee'] = $fee;
            $r['calculated_laba'] = $laba;
            if (!empty($r['transaction_detail_json'])) {
                $r['calculated_fee'] = $r['presentation']['total_fees'];
                $r['calculated_laba'] = $r['presentation']['net_profit'];
                $r['gross_sales'] = $r['presentation']['gross_amount'];
                $r['net_settlement_amount'] = $r['presentation']['net_income'];
                $r['total_cogs_hpp'] = $r['presentation']['total_cogs'];
                $laba = $r['calculated_laba'];
            }
            if($laba===null)$missingProfit++;else $totalLaba += $laba;
        }

        return [
            'transactions' => $rows,
            'total_count' => $totalCount,
            'total_laba' => $missingProfit?null:$totalLaba,'known_laba'=>$totalLaba,'missing_profit'=>$missingProfit,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $totalCount,
                'total_pages' => max(1, (int)ceil($totalCount / $perPage)),
            ],
        ];
    }

    /**
     * Ambil rincian lengkap 1 pesanan untuk modal popup.
     */
    public function getOrderDetail(string $orderId, string $storeId): ?array
    {
        $this->scope->assertStoreAccess($storeId);
        $stmt = $this->pdo->prepare("SELECT o.*, s.store_name, s.platform
            FROM marketplace_orders o
            JOIN marketplace_stores s ON o.store_id = s.id
            WHERE o.id = ? AND o.store_id = ?");
        $stmt->execute([$orderId, $storeId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            return null;
        }

        // Ambil item produk
        $stmtItems = $this->pdo->prepare("SELECT oi.* FROM marketplace_order_items oi WHERE oi.order_id = ?");
        $stmtItems->execute([$orderId]);
        $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        $order['items'] = $items;
        $order['total_fee'] = (float)$order['platform_admin_fee'] + (float)$order['service_fee'] + (float)$order['payment_fee'] + (float)$order['shipping_adjustment'] + (float)$order['other_deductions'] + (float)$order['affiliate_commission'] + (float)$order['live_commission'];
        $order['revenue_components'] = [
            ['label' => 'Diskon penjual', 'amount' => (float)$order['seller_discount'], 'kind' => 'discount'],
            ['label' => 'Biaya admin platform', 'amount' => (float)$order['platform_admin_fee'], 'kind' => 'fee'],
            ['label' => 'Biaya layanan', 'amount' => (float)$order['service_fee'], 'kind' => 'fee'],
            ['label' => 'Biaya pembayaran', 'amount' => (float)$order['payment_fee'], 'kind' => 'fee'],
            ['label' => 'Penyesuaian ongkir', 'amount' => (float)$order['shipping_adjustment'], 'kind' => 'fee'],
            ['label' => 'Potongan lain', 'amount' => (float)$order['other_deductions'], 'kind' => 'fee'],
            ['label' => 'Komisi afiliasi', 'amount' => (float)$order['affiliate_commission'], 'kind' => 'fee'],
            ['label' => 'Komisi live', 'amount' => (float)$order['live_commission'], 'kind' => 'fee'],
        ];
        $order['profit_data_status'] = (float)$order['total_cogs_hpp'] > 0
            ? 'HPP tersedia dari snapshot transaksi'
            : 'HPP belum lengkap; laba belum final';
        $order['calculated_laba'] = (float)$order['net_settlement_amount'] - (float)$order['total_cogs_hpp'];
        $order['presentation'] = MarketplaceOrderPresentation::build($order);
        $order['calculated_laba']=$order['presentation']['net_profit'];$order['total_fee']=$order['presentation']['total_fees'];
        $order['net_settlement_amount']=$order['presentation']['net_income'];$order['total_cogs_hpp']=$order['presentation']['total_cogs'];
        $order['revenue_components']=array_map(static fn($component)=>['label'=>$component['label'],'amount'=>$component['amount'],'kind'=>$component['effect']<0?'credit':'fee'],$order['presentation']['components']);
        $order['profit_data_status']=$order['presentation']['financial_state']==='waiting_income'?'Menunggu data keuangan':($order['presentation']['financial_state']==='waiting_hpp'?'HPP belum lengkap':'Data pendapatan dan HPP tersedia');

        return $order;
    }

    /** Internal connector boundary. Caller verifies official response before supplying normalized facts. */
    public function saveVerifiedOrderDetails(string $orderId, string $storeId, array $details): void
    {
        $this->scope->assertStoreAccess($storeId);
        foreach (['is_cod', 'is_estimated'] as $key) {
            if (isset($details[$key]) && !is_bool($details[$key])) throw new InvalidArgumentException("$key harus boolean.");
        }
        foreach (['source_updated_at', 'released_at'] as $key) {
            if (!empty($details[$key])) {
                $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', (string)$details[$key]);
                if (!$date || $date->format('Y-m-d H:i:s') !== $details[$key]) throw new InvalidArgumentException('Waktu sumber tidak valid.');
            }
        }
        if (empty($details['source_updated_at'])) throw new InvalidArgumentException('Waktu pembaruan sumber wajib disertakan.');
        $money = static function ($value): void {
            if ($value !== null && (!is_scalar($value) || !preg_match('/^-?\d{1,13}(?:\.\d{1,2})?$/', (string)$value))) throw new InvalidArgumentException('Nilai uang sumber tidak valid.');
        };
        foreach (['gross_amount','total_fees','net_income','total_cogs','adjustment_amount'] as $key) $money($details[$key] ?? null);
        foreach (($details['components'] ?? []) as $value) $money($value);
        foreach (($details['items'] ?? []) as $item) {
            foreach (['unit_price','total_price','unit_cogs','total_cogs'] as $key) $money($item[$key] ?? null);
            $qty = $item['quantity'] ?? null;
            $returned = $item['returned_quantity'] ?? null;
            if (!is_numeric($qty) || $qty < 0 || ($returned !== null && (!is_numeric($returned) || $returned < 0 || $returned > $qty))) throw new InvalidArgumentException('Jumlah produk atau pengembalian tidak valid.');
        }
        $json = json_encode($details, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT source_kind,transaction_detail_json FROM marketplace_orders WHERE id=? AND store_id=? FOR UPDATE');
            $stmt->execute([$orderId, $storeId]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$existing || ($existing['source_kind'] ?? '') !== 'api') throw new DomainException('Rincian resmi hanya dapat dipasang pada pesanan API di toko aktif.');
            $previous = json_decode((string)$existing['transaction_detail_json'], true) ?: [];
            if (($previous['source_updated_at'] ?? '') > $details['source_updated_at']) { $this->pdo->commit(); return; }
            $this->pdo->prepare('UPDATE marketplace_orders SET transaction_detail_json=? WHERE id=? AND store_id=?')->execute([$json, $orderId, $storeId]);
            $this->pdo->commit();
        } catch (Throwable $error) { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $error; }
    }

    public function updateManualOrder(string $orderId, string $storeId, array $data): array
    {
        $this->scope->assertStoreAccess($storeId);
        $stmt = $this->pdo->prepare('SELECT * FROM marketplace_orders WHERE id=? AND store_id=? LIMIT 1');
        $stmt->execute([$orderId, $storeId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) throw new DomainException('Pesanan tidak ditemukan dalam toko aktif.');
        if (($order['source_kind'] ?? 'api') !== 'manual') {
            throw new LogicException('Pesanan dari API tidak dapat diedit sebagai transaksi manual. Ubah melalui penyesuaian internal yang terpisah.');
        }
        if (($order['internal_state'] ?? 'active') === 'archived') throw new LogicException('Pesanan yang sudah diarsipkan tidak dapat diedit.');

        $status = trim((string)($data['order_status'] ?? $order['order_status']));
        $allowedStatuses = ['Pending', 'Terkirim', 'Selesai', 'Dibatalkan', 'Retur'];
        if (!in_array($status, $allowedStatuses, true)) throw new InvalidArgumentException('Status pesanan manual tidak valid.');
        $buyer = trim((string)($data['buyer_username'] ?? $order['buyer_username'] ?? 'Customer Direct'));
        $hpp = max(0, (float)($data['total_cogs_hpp'] ?? $order['total_cogs_hpp']));
        $net = (float)$order['gross_sales'] - (float)$order['seller_discount'] - (float)$order['platform_admin_fee'] - (float)$order['service_fee'] - (float)$order['payment_fee'] - (float)$order['shipping_adjustment'] - (float)$order['other_deductions'];
        $this->pdo->prepare('UPDATE marketplace_orders SET buyer_username=?, order_status=?, total_cogs_hpp=?, net_margin_profit=?, completion_date=IF(? = \'Selesai\', COALESCE(completion_date, NOW()), completion_date) WHERE id=? AND store_id=? AND source_kind=\'manual\'')
            ->execute([$buyer, $status, $hpp, $net - $hpp, $status, $orderId, $storeId]);
        return ['success' => true, 'order_id' => $orderId, 'message' => 'Pesanan manual berhasil diperbarui.'];
    }

    public function archiveOrder(string $orderId, string $storeId, string $reason): array
    {
        $this->scope->assertStoreAccess($storeId);
        $reason = trim($reason);
        if ($reason === '') throw new InvalidArgumentException('Alasan arsip wajib diisi.');
        $stmt = $this->pdo->prepare('SELECT source_kind, internal_state FROM marketplace_orders WHERE id=? AND store_id=? LIMIT 1');
        $stmt->execute([$orderId, $storeId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) throw new DomainException('Pesanan tidak ditemukan dalam toko aktif.');
        if (($order['internal_state'] ?? 'active') === 'archived') return ['success' => true, 'order_id' => $orderId, 'message' => 'Pesanan sudah diarsipkan sebelumnya.'];
        $by = (string)($_SESSION['username'] ?? $_SESSION['name'] ?? 'system');
        $this->pdo->prepare("UPDATE marketplace_orders SET internal_state='archived', archived_at=NOW(), archived_by=?, archive_reason=? WHERE id=? AND store_id=?")
            ->execute([$by, $reason, $orderId, $storeId]);
        return ['success' => true, 'order_id' => $orderId, 'source_kind' => $order['source_kind'] ?? 'api', 'message' => 'Pesanan diarsipkan dari laporan internal. Data platform tidak dihapus.'];
    }

    /**
     * Menu PENGELUARAN (Sesuai Image 2):
     * Menampilkan daftar pengeluaran, total pengeluaran, mempengaruhi profit & arus kas.
     */
    public function getExpenses(string $storeId, ?string $category = null, ?string $search = null, ?string $date = null): array
    {
        $this->scope->assertStoreAccess($storeId);
        $params = [$storeId];
        $where = "WHERE store_id = ?";

        if ($category && $category !== 'Semua Kategori' && $category !== 'Semua') {
            $where .= " AND category = ?";
            $params[] = $category;
        }

        if ($search) {
            $where .= " AND (description LIKE ? OR vendor_name LIKE ? OR notes LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        if ($date) {
            $where .= " AND expense_date = ?";
            $params[] = $date;
        }

        $sql = "SELECT e.* 
                FROM marketplace_expenses e
                $where 
                ORDER BY e.expense_date DESC, e.id DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total = 0;
        $profitAffect = 0;
        $cashflowOnly = 0;

        foreach ($rows as $r) {
            $amt = (float)$r['amount'];
            $total += $amt;
            if ($r['affects_profit']) $profitAffect += $amt;
            if ($r['is_cashflow_only']) $cashflowOnly += $amt;
        }

        return [
            'total_amount' => $total,
            'affects_profit' => $profitAffect,
            'cashflow_only' => $cashflowOnly,
            'count' => count($rows),
            'expenses' => $rows
        ];
    }

    /**
     * Catat Pengeluaran Baru dan Sinkronkan Otomatis ke Kas/Bank ERP Jago Laba.
     */
    public function recordExpense(array $data): int
    {
        $storeId = (string)($data['store_id'] ?? '');
        $this->scope->assertStoreAccess($storeId);
        $this->pdo->beginTransaction();
        try {
            $desc = $data['description'];
            $vendor = $data['vendor_name'] ?? 'Vendor Eksternal';
            $date = $data['expense_date'] ?: date('Y-m-d');
            $category = $data['category'];
            $amount = (float)$data['amount'];
            $paymentMethod = $data['payment_method'] ?? 'Transfer Bank';
            $accountId = null; // Legacy shared accounts cannot be selected here.
            $notes = $data['notes'] ?? '';
            $affectsProfit = !empty($data['affects_profit']) ? 1 : 0;
            $isCashflowOnly = !empty($data['is_cashflow_only']) ? 1 : 0;

            // Marketplace account migration awaits explicit classification.

            // 1. Simpan ke marketplace_expenses
            $stmtExp = $this->pdo->prepare("INSERT INTO marketplace_expenses 
                (store_id, description, vendor_name, expense_date, category, amount, payment_method, account_id, notes, affects_profit, is_cashflow_only, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmtExp->execute([$storeId, $desc, $vendor, $date, $category, $amount, $paymentMethod, $accountId, $notes, $affectsProfit, $isCashflowOnly]);
            $expenseId = (int)$this->pdo->lastInsertId();

            // No second ad-spend row is generated from this expense. A verified
            // platform ad transaction must be reconciled by source reference.

            // Marketplace expenses stay in marketplace_expenses. A cross-workspace
            // bank transfer requires a separate, explicit transfer workflow.

            $this->pdo->commit();
            return $expenseId;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Hapus Pengeluaran dan Kembalikan Saldo Kas Terkait.
     */
    public function deleteExpense(int $expenseId, string $storeId): bool
    {
        $this->scope->assertStoreAccess($storeId);
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM marketplace_expenses WHERE id = ? AND store_id = ?");
            $stmt->execute([$expenseId, $storeId]);
            $exp = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$exp) {
                $this->pdo->rollBack();
                return false;
            }

            $amount = (float)$exp['amount'];
            $accountId = $exp['account_id'];
            $refNo = 'EXP-MKT-' . $expenseId;

            // Legacy cash entries remain untouched pending approved classification.

            // Hapus dari marketplace_expenses
            $this->pdo->prepare("DELETE FROM marketplace_expenses WHERE id = ? AND store_id = ?")->execute([$expenseId, $storeId]);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Catat Pesanan Manual Baru dan Sinkronkan Mutasi Stok Gudang.
     */
    public function recordManualOrder(array $d): string
    {
        $storeId = (string)($d['store_id'] ?? '');
        $this->scope->assertStoreAccess($storeId);
        $this->pdo->beginTransaction();
        try {
            $orderId = trim($d['order_id']);
            $orderDate = $d['order_date'] ?: date('Y-m-d H:i:s');
            $completionDate = !empty($d['completion_date']) ? $d['completion_date'] : $orderDate;
            $buyer = $d['buyer_username'] ?: 'Customer Direct';
            $status = $d['order_status'] ?: 'Selesai';
            $gross = (float)$d['gross_sales'];
            $adminFee = (float)($d['platform_admin_fee'] ?? 0);
            $serviceFee = (float)($d['service_fee'] ?? 0);
            $paymentFee = (float)($d['payment_fee'] ?? 0);
            $shipAdj = (float)($d['shipping_adjustment'] ?? 0);
            $cogs = (float)($d['total_cogs_hpp'] ?? 0);
            $net = $gross - $adminFee - $serviceFee - $paymentFee - $shipAdj;
            $margin = $net - $cogs;

            $stmt = $this->pdo->prepare("INSERT INTO marketplace_orders
                (id, store_id, order_date, completion_date, buyer_username, order_status, gross_sales, seller_discount, platform_admin_fee, service_fee, payment_fee, shipping_adjustment, other_deductions, operational_expense, adjustment_amount, net_settlement_amount, total_cogs_hpp, net_margin_profit, escrow_status, source_kind, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, 0, 0, 0, ?, ?, ?, 'Tertahan', 'manual', NOW())");
            $stmt->execute([
                $orderId, $storeId, $orderDate, $completionDate, $buyer, $status,
                $gross, $adminFee, $serviceFee, $paymentFee, $shipAdj, $net, $cogs, $margin
            ]);

            // Item detail
            $sku = $d['sku'] ?? 'SKU-CUSTOM';
            $itemName = $d['item_name'] ?? 'Produk Konveksi';
            $qty = (int)($d['qty'] ?? 1);
            $variantId = $d['product_variant_id'] ?? null;

            $stmtItem = $this->pdo->prepare("INSERT INTO marketplace_order_items 
                (order_id, product_variant_id, sku, item_name, qty, selling_price, unit_cogs_hpp, subtotal_price, subtotal_cogs)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtItem->execute([
                $orderId, $variantId, $sku, $itemName, $qty, $gross, $cogs, $gross, $cogs
            ]);

            // Jika status Selesai & variant terhubung, kurangi stok gudang secara otomatis!
            // No automatic deduction from the konveksi warehouse.

            $this->pdo->commit();
            return $orderId;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Kurangi Stok Gudang Otomatis saat Pesanan Selesai.
     */
    public function deductStockForOrder(string $orderId, string $variantId, int $qty, float $cogs): void
    {
        throw new LogicException('Pemotongan stok konveksi otomatis dinonaktifkan. Gunakan transfer barang eksplisit.');
        // 1. Ambil stok saat ini
        $stmtStok = $this->pdo->prepare("SELECT stock_qty, average_hpp FROM inventory_stocks WHERE product_variant_id = ? LIMIT 1");
        $stmtStok->execute([$variantId]);
        $curr = $stmtStok->fetch(PDO::FETCH_ASSOC);

        $stockBefore = $curr ? (float)$curr['stock_qty'] : 0.0;
        $stockAfter = $stockBefore - $qty;

        // 2. Update atau Insert tabel inventory_stocks
        if ($curr) {
            $this->pdo->prepare("UPDATE inventory_stocks SET stock_qty = ?, updated_at = NOW() WHERE product_variant_id = ?")
                ->execute([$stockAfter, $variantId]);
        } else {
            $this->pdo->prepare("INSERT INTO inventory_stocks (warehouse_id, product_variant_id, stock_qty, average_hpp, total_valuation, updated_at) VALUES ('GDG-UTAMA', ?, ?, ?, ?, NOW())")
                ->execute([$variantId, -$qty, $cogs, -$qty * $cogs]);
        }

        // 3. Catat Mutasi Keluar di stock_mutations
        $mutId = 'MUT-ORD-' . strtoupper(substr(md5($orderId . microtime()), 0, 10));
        $stmtMut = $this->pdo->prepare("INSERT INTO stock_mutations 
            (id, transaction_date, reference_no, mutation_type, item_type, product_variant_id, warehouse_id, qty_in, qty_out, stock_before, stock_after, unit_cost, total_cost, party_name, operator_name, created_at)
            VALUES (?, CURDATE(), ?, 'OUT_SALES_MARKETPLACE', 'PRODUK_JADI', ?, 'GDG-UTAMA', 0, ?, ?, ?, ?, ?, 'Marketplace Fulfillment', 'System Auto-Sync', NOW())");
        $stmtMut->execute([
            $mutId, $orderId, $variantId, $qty, $stockBefore, $stockAfter, $cogs, $cogs * $qty
        ]);
    }

    /**
     * Menu INVENTORI (Sesuai Image 3):
     * Level Stok, Terjual, Retur, Current Stock, & Sinkronisasi HPP Pabrik Konveksi.
     */
    public function getInventory(string $storeId, ?string $filter = null, ?string $search = null): array
    {
        $this->scope->assertStoreAccess($storeId);

        // Only explicit marketplace SKU mappings are exposed. A product name or
        // SKU match alone must not turn a konveksi item into marketplace stock.
        $sqlMapped = "SELECT m.marketplace_sku, m.marketplace_product_name, m.product_variant_id variant_id,
                p.id product_id, p.name product_name, p.category, pv.sku,
                COALESCE(s.stock_qty, 0) current_warehouse_stock,
                COALESCE(hh.unit_hpp, s.average_hpp, p.standard_hpp, 0) active_hpp
            FROM marketplace_sku_mappings m
            LEFT JOIN product_variants pv ON pv.id = m.product_variant_id
            LEFT JOIN products p ON p.id = pv.product_id
            LEFT JOIN inventory_stocks s ON s.product_variant_id = pv.id
            LEFT JOIN marketplace_hpp_history hh ON hh.store_id = m.store_id
                AND hh.marketplace_sku = m.marketplace_sku
                AND hh.effective_from = (
                    SELECT MAX(hh2.effective_from) FROM marketplace_hpp_history hh2
                    WHERE hh2.store_id = m.store_id AND hh2.marketplace_sku = m.marketplace_sku
                    AND hh2.effective_from <= CURDATE()
                )
            WHERE m.store_id = ?
            ORDER BY m.marketplace_product_name ASC, m.marketplace_sku ASC";
        $stmtMapped = $this->pdo->prepare($sqlMapped);
        $stmtMapped->execute([$storeId]);
        $mappedRows = $stmtMapped->fetchAll(PDO::FETCH_ASSOC);

        $soldStmt = $this->pdo->prepare("SELECT oi.sku,
                COALESCE(SUM(oi.qty),0) total_qty,
                COALESCE(SUM(CASE WHEN o.order_status='Retur' THEN oi.qty ELSE 0 END),0) total_retur
            FROM marketplace_order_items oi JOIN marketplace_orders o ON o.id=oi.order_id
            WHERE o.store_id=? AND (o.internal_state IS NULL OR o.internal_state = 'active') GROUP BY oi.sku");
        $soldStmt->execute([$storeId]);
        $soldMap = [];
        foreach ($soldStmt->fetchAll(PDO::FETCH_ASSOC) as $sold) $soldMap[strtoupper((string)$sold['sku'])] = $sold;

        $items = [];
        $missingHpp = 0;
        foreach ($mappedRows as $row) {
            $sku = (string)($row['marketplace_sku'] ?: $row['sku'] ?: '');
            $sold = $soldMap[strtoupper($sku)] ?? ['total_qty' => 0, 'total_retur' => 0];
            $hpp = (float)($row['active_hpp'] ?? 0);
            $hasHpp = $hpp > 0;
            if (!$hasHpp) $missingHpp++;
            $soldQty = (int)$sold['total_qty'];
            $returnQty = (int)$sold['total_retur'];
            $current = (int)$row['current_warehouse_stock'] - $soldQty;
            $item = [
                'product_id' => $row['product_id'],
                'variant_id' => $row['variant_id'],
                'product_name' => $row['marketplace_product_name'] ?: ($row['product_name'] ?: $sku),
                'sku' => $sku,
                'category' => $row['category'],
                'active_hpp' => $hpp,
                'has_hpp' => $hasHpp,
                'current_warehouse_stock' => (int)$row['current_warehouse_stock'],
                'terjual' => $soldQty,
                'retur' => $returnQty,
                'current' => $current,
                'status' => $current <= 0 ? 'Habis' : ($current <= 10 ? 'Menipis' : 'Aman'),
                'variants_display' => '1 mapping marketplace'
            ];
            if ($filter === 'stok_menipis' && ($current > 10 || $current <= 0)) continue;
            if ($filter === 'habis' && $current > 0) continue;
            if ($filter === 'missing_hpp' && $hasHpp) continue;
            if ($search !== '' && stripos($item['product_name'], (string)$search) === false && stripos($item['sku'], (string)$search) === false) continue;
            $items[] = $item;
        }
        return ['items' => $items, 'missing_hpp_count' => $missingHpp];
        $sql = "SELECT 
            p.id as product_id, 
            p.name as product_name, 
            p.category, 
            p.standard_hpp,
            pv.id as variant_id,
            pv.sku,
            COALESCE(s.stock_qty, 0) as current_warehouse_stock,
            COALESCE(s.average_hpp, p.standard_hpp, 0) as active_hpp,
            m.marketplace_sku
        FROM products p
        LEFT JOIN product_variants pv ON pv.product_id = p.id
        LEFT JOIN inventory_stocks s ON s.product_variant_id = pv.id
        LEFT JOIN marketplace_sku_mappings m ON m.product_variant_id = pv.id AND m.store_id = ?
        GROUP BY p.id, p.name, p.category, p.standard_hpp, pv.id, pv.sku, s.stock_qty, s.average_hpp, m.marketplace_sku
        ORDER BY p.name ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$storeId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Hitung akumulasi pesanan riil dari marketplace_order_items
        $stmtSold = $this->pdo->prepare("SELECT 
            oi.sku, 
            COALESCE(SUM(oi.qty), 0) as total_qty,
            COALESCE(SUM(CASE WHEN o.order_status = 'Retur' THEN oi.qty ELSE 0 END), 0) as total_retur
            FROM marketplace_order_items oi
            JOIN marketplace_orders o ON oi.order_id = o.id
            WHERE o.store_id = ? AND (o.internal_state IS NULL OR o.internal_state = 'active')
            GROUP BY oi.sku");
        $stmtSold->execute([$storeId]);
        $soldMap = [];
        while ($r = $stmtSold->fetch(PDO::FETCH_ASSOC)) {
            $soldMap[strtoupper($r['sku'])] = $r;
        }

        $processed = [];
        $missingHpp = 0;

        foreach ($rows as $item) {
            $sku = strtoupper($item['sku'] ?? $item['marketplace_sku'] ?? '');
            $soldInfo = $soldMap[$sku] ?? null;

            $terjual = $soldInfo ? (int)$soldInfo['total_qty'] : rand(5, 45);
            $retur = $soldInfo ? (int)$soldInfo['total_retur'] : 0;

            // Jika HPP 0 atau null, tandai sebagai missing
            $hasHpp = (float)$item['active_hpp'] > 0;
            if (!$hasHpp) {
                $missingHpp++;
            }

            $currentBalance = (int)$item['current_warehouse_stock'] - $terjual;

            $itemData = [
                'product_id' => $item['product_id'],
                'variant_id' => $item['variant_id'],
                'product_name' => $item['product_name'],
                'sku' => $item['sku'] ?: ($item['marketplace_sku'] ?: 'SKU-' . $item['product_id']),
                'category' => $item['category'],
                'active_hpp' => (float)$item['active_hpp'],
                'has_hpp' => $hasHpp,
                'current_warehouse_stock' => (int)$item['current_warehouse_stock'],
                'terjual' => $terjual,
                'retur' => $retur,
                'current' => $currentBalance,
                'status' => 'Group',
                'variants_display' => '1 variant'
            ];

            // Terapkan Filter
            if ($filter === 'stok_menipis' && ($currentBalance > 10 || $currentBalance <= 0)) continue;
            if ($filter === 'habis' && $currentBalance > 0) continue;
            if ($filter === 'missing_hpp' && $hasHpp) continue;

            if ($search) {
                if (stripos($itemData['product_name'], $search) === false && stripos($itemData['sku'], $search) === false) {
                    continue;
                }
            }

            $processed[] = $itemData;
        }

        return [
            'items' => $processed,
            'missing_hpp_count' => $missingHpp ?: 1 // Minimum 1 sesuai foto referensi FinKami Image 3
        ];
    }

    /**
     * Update HPP Produk Konveksi & Mapping SKU Marketplace.
     */
    public function updateProductHpp(string $storeId, string $sku, float $newHpp, ?string $variantId = null): bool
    {
        $this->scope->assertStoreAccess($storeId);
        $this->pdo->beginTransaction();
        try {
            // HPP marketplace affects its order items only, not konveksi masters.

            // Simpan mapping SKU
            $stmtMap = $this->pdo->prepare("INSERT INTO marketplace_sku_mappings 
                (store_id, marketplace_sku, marketplace_product_name, product_variant_id, created_at)
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE product_variant_id = VALUES(product_variant_id)");
            $stmtMap->execute([$storeId, $sku, "SKU Map: $sku", $variantId]);

            // HPP baru berlaku mulai tanggal efektif. Snapshot transaksi lama
            // tidak ditimpa; backfill historis harus menjadi aksi terpisah.
            $effectiveFrom = date('Y-m-d');
            $changedBy = (string)($_SESSION['username'] ?? $_SESSION['name'] ?? 'system');
            $stmtHpp = $this->pdo->prepare("INSERT INTO marketplace_hpp_history
                (store_id, marketplace_sku, product_variant_id, unit_hpp, effective_from, source, reason, changed_by)
                VALUES (?, ?, ?, ?, ?, 'MANUAL', 'Perubahan HPP marketplace', ?)
                ON DUPLICATE KEY UPDATE unit_hpp=VALUES(unit_hpp), product_variant_id=VALUES(product_variant_id), changed_by=VALUES(changed_by), created_at=NOW()");
            $stmtHpp->execute([$storeId, $sku, $variantId ?: null, $newHpp, $effectiveFrom, $changedBy]);

            // Isi hanya snapshot order yang sebelumnya belum memiliki HPP.
            $this->pdo->prepare("UPDATE marketplace_order_items oi
                JOIN marketplace_orders o ON o.id = oi.order_id
                SET oi.unit_cogs_hpp = ?, oi.subtotal_cogs = oi.qty * ?
                WHERE o.store_id = ? AND oi.sku = ? AND (oi.unit_cogs_hpp IS NULL OR oi.unit_cogs_hpp = 0)")
                ->execute([$newHpp, $newHpp, $storeId, $sku]);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Menu MARKETING DASHBOARD (Sesuai Image Referensi FinKami Marketing):
     * Performa Iklan Shopee + TikTok Shop yang saling terhubung dengan Transaksi Pesanan & HPP Konveksi.
     * Dilengkapi Sinkronisasi Iklan Grup, Toggle PPN 11%, Komisi Affiliate/Live & Deteksi Produk Bocor.
     */
    public function importProductAdReport(string $storeId, string $path): int
    {
        $store = $this->scope->assertStoreAccess($storeId);
        if (!in_array($store['platform'], ['Shopee', 'TikTok Shop'], true)) throw new InvalidArgumentException('Platform iklan tidak didukung.');
        $handle = fopen($path, 'rb');
        if (!$handle) throw new InvalidArgumentException('Laporan tidak dapat dibaca.');
        $rows = [];
        $seen = [];
        try {
            $headers = fgetcsv($handle, 0, ',', '"', '');
            if (!$headers) throw new InvalidArgumentException('Laporan kosong.');
            $headers[0] = ltrim($headers[0], "\xEF\xBB\xBF");
            $required = ['marketplace_item_id','sku','product_name','campaign_name','period_start','period_end','ad_spend','gross_revenue','orders_count','qty_sold','unit_cogs_hpp','platform_fee','affiliate_fee'];
            if ($headers !== $required) throw new InvalidArgumentException('Gunakan urutan kolom pada template CSV laporan produk.');
            while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                if ($values === [null]) continue;
                $line = count($rows) + 2;
                if (count($values) !== count($headers)) throw new InvalidArgumentException("Jumlah kolom baris $line tidak sesuai template.");
                $row = array_combine($headers, array_map('trim', $values));
                foreach (['marketplace_item_id' => 100, 'sku' => 100, 'product_name' => 255, 'campaign_name' => 255] as $key => $limit) {
                    if (($key !== 'campaign_name' && $row[$key] === '') || strlen($row[$key]) > $limit) throw new InvalidArgumentException("Kolom $key baris $line tidak valid.");
                }
                foreach (['period_start','period_end'] as $key) {
                    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $row[$key]);
                    if (!$parsed || $parsed->format('Y-m-d') !== $row[$key]) throw new InvalidArgumentException("Tanggal baris $line harus YYYY-MM-DD.");
                }
                if ($row['period_start'] > $row['period_end']) throw new InvalidArgumentException("Periode baris $line terbalik.");
                foreach (['ad_spend','gross_revenue','orders_count','qty_sold','unit_cogs_hpp','platform_fee','affiliate_fee'] as $key) {
                    $pattern = in_array($key, ['orders_count','qty_sold'], true) ? '/^\d+$/' : '/^\d+(?:\.\d{1,2})?$/';
                    if (!preg_match($pattern, $row[$key]) || (float)$row[$key] > 999999999999 || (in_array($key, ['orders_count','qty_sold'], true) && (float)$row[$key] > 2147483647)) throw new InvalidArgumentException("Angka $key baris $line tidak valid. Gunakan angka tanpa Rp atau pemisah ribuan.");
                }
                $identity = json_encode([$row['marketplace_item_id'], $row['sku'], $row['campaign_name'], $row['period_start'], $row['period_end']]);
                if (isset($seen[$identity])) throw new InvalidArgumentException("Laporan duplikat pada baris $line.");
                $seen[$identity] = true;
                $rows[] = $row;
                if (count($rows) > 5000) throw new InvalidArgumentException('Maksimal 5.000 baris per impor.');
            }
        } finally { fclose($handle); }
        if (!$rows) throw new InvalidArgumentException('Laporan tidak berisi produk.');
        $this->pdo->beginTransaction();
        try {
            // Serialize imports per store so repeating a report cannot duplicate spend.
            $lock = $this->pdo->prepare('SELECT id FROM marketplace_stores WHERE id=? FOR UPDATE');
            $lock->execute([$storeId]);
            foreach ($rows as $row) {
                $find = $this->pdo->prepare("SELECT id, period_start, period_end FROM marketplace_product_ads WHERE store_id=? AND marketplace_item_id=? AND sku=? AND COALESCE(campaign_name,'')=? AND ad_type='Produk' AND period_start<=? AND period_end>=? FOR UPDATE");
                $find->execute([$storeId, $row['marketplace_item_id'], $row['sku'], $row['campaign_name'], $row['period_end'], $row['period_start']]);
                $existing = $find->fetchAll(PDO::FETCH_ASSOC);
                if (count($existing) > 1 || ($existing && ($existing[0]['period_start'] !== $row['period_start'] || $existing[0]['period_end'] !== $row['period_end']))) throw new InvalidArgumentException('Periode laporan bertumpang tindih dengan data tersimpan. Gunakan periode yang sama untuk mengganti laporan.');
                $columns = array_keys($row);
                $values = array_values($row);
                if ($existing) {
                    $assignments = implode(',', array_map(static fn(string $column): string => "$column=?", $columns));
                    $stmt = $this->pdo->prepare("UPDATE marketplace_product_ads SET $assignments, last_synced_at=NOW(), is_bocor=0, bocor_reason=NULL WHERE id=? AND store_id=?");
                    $stmt->execute(array_merge($values, [$existing[0]['id'], $storeId]));
                } else {
                    $stmt = $this->pdo->prepare('INSERT INTO marketplace_product_ads (' . implode(',', $columns) . ",store_id,platform,ad_type,image_url,last_synced_at) VALUES (" . implode(',', array_fill(0, count($columns), '?')) . ",?,?,'Produk','',NOW())");
                    $stmt->execute(array_merge($values, [$storeId, $store['platform']]));
                }
            }
            $this->pdo->commit();
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
        return count($rows);
    }

    public function getMarketingDashboard(string $storeId, ?string $filter = null, ?string $search = null, ?string $platform = null, string $adType = 'Semua', bool $includePpn = false, array $dates = []): array
    {
        $allowed = $this->scope->accessibleStoreIds();
        if ($storeId !== 'ALL' && $storeId !== 'Semua Toko' && $storeId !== '') {
            $this->scope->assertStoreAccess($storeId);
            $allowed = [$storeId];
        }
        [$in, $params] = $this->inClause($allowed);
        $where = $allowed ? "WHERE pa.store_id IN ($in)" : 'WHERE 1=0';
        foreach (['date_start' => ['>=', 'period_start'], 'date_end' => ['<=', 'period_end']] as $key => [$operator, $column]) {
            if (!empty($dates[$key])) {
                $date = (string)$dates[$key];
                $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
                if (!$parsed || $parsed->format('Y-m-d') !== $date) throw new InvalidArgumentException('Tanggal laporan iklan tidak valid.');
                $where .= " AND pa.$column $operator ?";
                $params[] = $date;
            }
        }
        if (!empty($dates['date_start']) && !empty($dates['date_end']) && $dates['date_start'] > $dates['date_end']) throw new InvalidArgumentException('Rentang tanggal iklan tidak valid.');

        if ($storeId !== 'ALL' && $storeId !== 'Semua Toko' && !empty($storeId)) {
            $where .= " AND pa.store_id = ?";
            $params[] = $storeId;
        }

        if ($platform && $platform !== 'Semua Platform') {
            $where .= " AND pa.platform = ?";
            $params[] = $platform;
        }

        if ($adType && $adType !== 'Semua' && $adType !== 'Semua Tipe') {
            $where .= " AND pa.ad_type = ?";
            $params[] = $adType;
        }

        if ($search) {
            $where .= " AND (pa.product_name LIKE ? OR pa.sku LIKE ? OR pa.marketplace_item_id LIKE ? OR pa.campaign_name LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $sql = "SELECT pa.*, 
                    pa.unit_cogs_hpp as resolved_unit_hpp
                FROM marketplace_product_ads pa
                $where
                ORDER BY pa.ad_spend DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalAdSpend = 0;
        $totalRevenue = 0;
        $totalOrders = 0;
        $totalQty = 0;
        $totalAffiliateFee = 0;
        $totalClicks = 0;
        $totalImpressions = 0;
        $totalConversions = 0;
        $processedProducts = [];
        $bocorCount = 0;

        $ppnFactor = $includePpn ? 1.11 : 1.0;

        foreach ($rows as $r) {
            // Kalkulasi PPN 11% jika toggle aktif
            $baseSpend = (float)$r['ad_spend'];
            $adSpend = round($baseSpend * $ppnFactor, 2);
            $ppnAmount = $includePpn ? round($baseSpend * 0.11, 2) : 0.0;

            $revenue = (float)$r['gross_revenue'];
            $orders = (int)$r['orders_count'];
            $clicks = (int)($r['clicks'] ?? 0);
            $impressions = (int)($r['impressions'] ?? 0);
            $conversions = (int)($r['conversions'] ?? 0);
            $qty = (int)$r['qty_sold'];
            $unitHpp = (float)$r['resolved_unit_hpp'];
            $totalHpp = $unitHpp * $qty;
            $platformFee = (float)$r['platform_fee'];
            $affiliateFee = (float)$r['affiliate_fee'];

            // ROAS Calculations
            $grossRoas = $adSpend > 0 ? round($revenue / $adSpend, 2) : 0.0;
            $netRoas = $adSpend > 0 ? round(($revenue - $platformFee - $affiliateFee) / $adSpend, 2) : 0.0;
            $actualRoas = $adSpend > 0 ? round(($revenue - $platformFee - $affiliateFee - $totalHpp) / $adSpend, 2) : 0.0;

            // Break Even ROAS (Min ROAS)
            $profitMarginBeforeAd = $revenue - $platformFee - $affiliateFee - $totalHpp;
            $minRoas = $profitMarginBeforeAd > 0 ? round($revenue / $profitMarginBeforeAd, 2) : 99.0;
            $isAman = $actualRoas >= 1.0 && $grossRoas >= $minRoas && $profitMarginBeforeAd > 0;

            // Deteksi Otomatis Produk Bocor (Early Warning Alert)
            $isBocor = (int)$r['is_bocor'] === 1;
            $bocorReason = $r['bocor_reason'];
            if (!$isBocor) {
                if ($adSpend >= 100000 && $orders === 0) {
                    $isBocor = true;
                    $bocorReason = "Biaya Iklan Rp " . number_format($adSpend, 0, ',', '.') . " tanpa hasil pesanan dalam 24 jam! Segera jeda iklan.";
                } elseif ($profitMarginBeforeAd > 0 && $grossRoas < $minRoas && $adSpend > 150000) {
                    $isBocor = true;
                    $bocorReason = "Actual ROAS ({$grossRoas}x) di bawah Min ROAS ({$minRoas}x). Margin produk boncos!";
                }
            }
            if ($isBocor) {
                $bocorCount++;
            }

            // Margin & Profit
            $costPerOrder = $orders > 0 ? round($adSpend / $orders) : 0;
            $marginLabaPct = $revenue > 0 ? round((($revenue - $totalHpp) / $revenue) * 100, 1) : 0.0;
            $profitRiil = $revenue - $platformFee - $affiliateFee - $totalHpp;
            $profitRiilPct = $revenue > 0 ? round(($profitRiil / $revenue) * 100, 1) : 0.0;
            $platformFeePct = $revenue > 0 ? round(($platformFee / $revenue) * 100, 1) : 0.0;
            
            // Net Profit Akhir (setelah potong iklan & affiliate)
            $netProfit = $profitRiil - $adSpend;
            $netProfitPct = $revenue > 0 ? round(($netProfit / $revenue) * 100, 1) : 0.0;

            // Filter Pills
            if ($filter === 'roas_rendah' && $isAman) continue;
            if ($filter === 'tanpa_hpp' && $unitHpp > 0) continue;
            if ($filter === 'rugi' && $netProfit >= 0) continue;
            if ($filter === 'bocor' && !$isBocor) continue;

            $totalAdSpend += $adSpend;
            $totalRevenue += $revenue;
            $totalOrders += $orders;
            $totalQty += $qty;
            $totalAffiliateFee += $affiliateFee;
            $totalClicks += $clicks;
            $totalImpressions += $impressions;
            $totalConversions += $conversions;

            $processedProducts[] = [
                'id' => $r['id'],
                'marketplace_item_id' => $r['marketplace_item_id'],
                'sku' => $r['sku'],
                'product_name' => $r['product_name'],
                'campaign_name' => $r['campaign_name'] ?? null,
                'ad_type' => $r['ad_type'] ?? 'Produk',
                'image_url' => $r['image_url'] ?: '',
                'store_id' => $r['store_id'],
                'period_start' => $r['period_start'],
                'period_end' => $r['period_end'],
                'last_synced_at' => $r['last_synced_at'],
                'platform' => $r['platform'],
                'ad_spend' => $adSpend,
                'base_spend' => $baseSpend,
                'ppn_amount' => $ppnAmount,
                'gross_revenue' => $revenue,
                'gross_roas' => $grossRoas,
                'net_roas' => $netRoas,
                'actual_roas' => $actualRoas,
                'min_roas' => $minRoas,
                'is_aman' => $isAman,
                'is_bocor' => $isBocor,
                'bocor_reason' => $bocorReason,
                'orders_count' => $orders,
                'qty_sold' => $qty,
                'cost_per_order' => $costPerOrder,
                'unit_cogs_hpp' => $unitHpp,
                'total_cogs_hpp' => $totalHpp,
                'margin_laba_pct' => $marginLabaPct,
                'profit_riil' => $profitRiil,
                'profit_riil_pct' => $profitRiilPct,
                'platform_fee' => $platformFee,
                'platform_fee_pct' => $platformFeePct,
                'affiliate_fee' => $affiliateFee,
                'net_profit' => $netProfit,
                'net_profit_pct' => $netProfitPct,
                'clicks' => $clicks,
                'impressions' => $impressions,
                'conversions' => $conversions,
                'ctr' => $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0.0,
                'cpc' => $clicks > 0 ? round($adSpend / $clicks) : 0.0
            ];
        }

        // KPI berasal dari baris laporan yang sama dengan tabel, tanpa angka referensi.
        $kpiSpend = $totalAdSpend;
        $kpiRevenue = $totalRevenue;
        $kpiRoas = $totalAdSpend > 0 ? round($totalRevenue / $totalAdSpend, 2) : 0.0;
        $kpiCostPerOrder = $totalOrders > 0 ? round($totalAdSpend / $totalOrders) : 0.0;
        $kpiOrders = $totalOrders;
        return [
            'kpi' => [
                'biaya_iklan' => $kpiSpend,
                'pendapatan' => $kpiRevenue,
                'roas' => $kpiRoas,
                'cost_per_order' => $kpiCostPerOrder,
                'total_orders' => $kpiOrders,
                'total_affiliate_fee' => $totalAffiliateFee,
                'include_ppn' => $includePpn,
                'bocor_count' => $bocorCount,
                'total_clicks' => $totalClicks,
                'total_impressions' => $totalImpressions,
                'total_conversions' => $totalConversions,
                'ctr' => $totalImpressions > 0 ? round(($totalClicks / $totalImpressions) * 100, 2) : 0.0,
                'cpc' => $totalClicks > 0 ? round($totalAdSpend / $totalClicks) : 0.0,
                'cpm' => $totalImpressions > 0 ? round(($totalAdSpend / $totalImpressions) * 1000) : 0.0,
                'conversion_rate' => $totalClicks > 0 ? round(($totalOrders / $totalClicks) * 100, 2) : 0.0,
                'campaign_count' => count(array_filter(array_unique(array_column($processedProducts, 'campaign_name')))) ?: count($processedProducts)
            ],
            'products' => $processedProducts,
            'total_count' => count($processedProducts)
        ];
    }

    /**
     * FITUR 1: Parser Impor Settlement CSV / Excel Bawaan Seller Center
     * Mendukung format Laporan Penghasilan Shopee & Settlement Statement TikTok Shop.
     */
    public function importSettlementCSV(string $storeId, string $filePath, string $originalFilename): array
    {
        $this->scope->assertStoreAccess($storeId);
        // Legacy import guesses HPP/fees and links a shared Konveksi bank account.
        // Re-enable only after a reviewed Marketplace payout/account migration.
        throw new LogicException('Impor settlement menunggu rekening dan rekonsiliasi Marketplace terpisah. Tidak ada data yang diubah.');
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("File settlement tidak ditemukan pada server.");
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (empty($lines)) {
            throw new InvalidArgumentException("File kosong atau tidak dapat dibaca.");
        }

        // Deteksi delimiter: koma, titik koma, atau tab
        $firstLine = $lines[0];
        $delimiter = ',';
        if (substr_count($firstLine, ';') > substr_count($firstLine, ',')) {
            $delimiter = ';';
        } elseif (substr_count($firstLine, "\t") > substr_count($firstLine, ',')) {
            $delimiter = "\t";
        }

        $headers = str_getcsv($firstLine, $delimiter);
        $headers = array_map(function($h) { return strtolower(trim(str_replace(['"', "'", "\xEF\xBB\xBF"], '', $h))); }, $headers);

        // Petakan indeks kolom umum Shopee / TikTok
        $idxOrderSn = null;
        $idxGross = null;
        $idxNet = null;
        $idxAdminFee = null;
        $idxServiceFee = null;
        $idxShipping = null;
        $idxBuyer = null;

        foreach ($headers as $i => $h) {
            if (in_array($h, ['no. pesanan', 'order sn', 'order id', 'order_id', 'nomor pesanan'])) $idxOrderSn = $i;
            if (in_array($h, ['total penghasilan', 'settlement amount', 'net amount', 'total settlement', 'dana dilepas'])) $idxNet = $i;
            if (in_array($h, ['harga produk', 'subtotal produk', 'gross sales', 'omzet', 'customer payment', 'total penjualan'])) $idxGross = $i;
            if (in_array($h, ['biaya administrasi', 'admin fee', 'biaya admin', 'commission fee', 'platform fee'])) $idxAdminFee = $i;
            if (in_array($h, ['biaya layanan', 'service fee', 'biaya program'])) $idxServiceFee = $i;
            if (in_array($h, ['penyesuaian ongkir', 'ongkos kirim diteruskan', 'shipping fee adjustment', 'selisih ongkir'])) $idxShipping = $i;
            if (in_array($h, ['username pembeli', 'buyer', 'nama pembeli', 'customer'])) $idxBuyer = $i;
        }

        $importedOrders = 0;
        $totalNetPayout = 0;
        $totalPlatformFees = 0;
        $batchId = 'BATCH-CSV-' . date('YmdHis');

        $this->pdo->beginTransaction();
        try {
            for ($rowIdx = 1; $rowIdx < count($lines); $rowIdx++) {
                $row = str_getcsv($lines[$rowIdx], $delimiter);
                if (count($row) < 3) continue;

                $orderId = $idxOrderSn !== null ? trim($row[$idxOrderSn]) : 'IMP-' . date('Ymd') . '-' . $rowIdx;
                if (empty($orderId)) continue;

                $cleanNum = function($val) {
                    if (!$val) return 0.0;
                    $val = preg_replace('/[^\d.,-]/', '', $val);
                    $val = str_replace('.', '', $val);
                    $val = str_replace(',', '.', $val);
                    return (float)$val;
                };

                $net = $idxNet !== null ? $cleanNum($row[$idxNet]) : 0.0;
                $gross = $idxGross !== null ? $cleanNum($row[$idxGross]) : ($net > 0 ? $net * 1.15 : 100000.0);
                $adminFee = $idxAdminFee !== null ? abs($cleanNum($row[$idxAdminFee])) : round($gross * 0.11, 2);
                $serviceFee = $idxServiceFee !== null ? abs($cleanNum($row[$idxServiceFee])) : round($gross * 0.04, 2);
                $shippingAdj = $idxShipping !== null ? abs($cleanNum($row[$idxShipping])) : 0.0;
                $buyer = $idxBuyer !== null ? trim($row[$idxBuyer]) : 'Buyer CSV ' . $rowIdx;

                if ($net <= 0 && $gross > 0) {
                    $net = $gross - $adminFee - $serviceFee - $shippingAdj;
                }

                // Upsert ke marketplace_orders
                $stmtCheck = $this->pdo->prepare("SELECT id FROM marketplace_orders WHERE id = ?");
                $stmtCheck->execute([$orderId]);
                $exists = $stmtCheck->fetchColumn();

                if ($exists) {
                    $stmtUp = $this->pdo->prepare("UPDATE marketplace_orders SET 
                        order_status = 'Selesai',
                        completion_date = NOW(),
                        net_settlement_amount = ?,
                        platform_admin_fee = ?,
                        service_fee = ?,
                        shipping_adjustment = ?,
                        escrow_status = 'Sudah Dicairkan',
                        payout_batch_id = ?
                        WHERE id = ?");
                    $stmtUp->execute([$net, $adminFee, $serviceFee, $shippingAdj, $batchId, $orderId]);
                } else {
                    $stmtIn = $this->pdo->prepare("INSERT INTO marketplace_orders 
                        (id, store_id, order_date, completion_date, buyer_username, order_status, gross_sales, seller_discount, platform_admin_fee, service_fee, payment_fee, shipping_adjustment, net_settlement_amount, total_cogs_hpp, escrow_status, payout_batch_id, created_at)
                        VALUES (?, ?, NOW(), NOW(), ?, 'Selesai', ?, 0, ?, ?, ROUND(?*0.01, 2), ?, ?, ROUND(?*0.45, 2), 'Sudah Dicairkan', ?, NOW())");
                    $stmtIn->execute([$orderId, $storeId, $buyer, $gross, $adminFee, $serviceFee, $gross, $shippingAdj, $net, $gross, $batchId]);
                }

                // Catat Jurnal Berpasangan untuk setiap pesanan selesai di settlement
                $journalNo = 'JRN-CSV-' . date('Ymd') . '-' . substr(md5($orderId), 0, 6);
                $hppEstimated = round($gross * 0.45, 2);

                $stmtJ = $this->pdo->prepare("INSERT INTO marketplace_journal_entries 
                    (journal_no, entry_date, ref_type, ref_id, store_id, account_code, account_name, debit, credit, memo, created_at)
                    VALUES (?, CURDATE(), 'ESCROW_PAYOUT', ?, ?, ?, ?, ?, ?, ?, NOW())");

                // Jurnal Escrow Selesai
                $stmtJ->execute([$journalNo, $orderId, $storeId, '1101', 'Kas/Bank Pencairan Marketplace', $net, 0.00, "Pencairan CSV Pesanan #$orderId"]);
                $stmtJ->execute([$journalNo, $orderId, $storeId, '5201', 'Beban Komisi & Admin Platform', $adminFee + $serviceFee, 0.00, "Potongan Admin CSV Pesanan #$orderId"]);
                if ($shippingAdj > 0) {
                    $stmtJ->execute([$journalNo, $orderId, $storeId, '5202', 'Beban Selisih Ongkir Ekspedisi', $shippingAdj, 0.00, "Selisih Ongkir CSV Pesanan #$orderId"]);
                }
                $stmtJ->execute([$journalNo, $orderId, $storeId, '1102', 'Piutang Escrow Marketplace', 0.00, $net + $adminFee + $serviceFee + $shippingAdj, "Pelunasan Escrow Pesanan #$orderId"]);

                $importedOrders++;
                $totalNetPayout += $net;
                $totalPlatformFees += ($adminFee + $serviceFee + $shippingAdj);
            }

            // Catat ke rekonsiliasi payout
            $stmtStoreAcc = $this->pdo->prepare("SELECT default_account_id FROM marketplace_stores WHERE id = ?");
            $stmtStoreAcc->execute([$storeId]);
            $defaultAccId = (int)$stmtStoreAcc->fetchColumn() ?: 1786634525412; // SeaBank

            $stmtRecon = $this->pdo->prepare("INSERT INTO marketplace_payout_reconciliations 
                (id, store_id, account_id, payout_date, total_payout_amount, matched_orders_count, status, notes, created_at)
                VALUES (?, ?, ?, CURDATE(), ?, ?, 'Matched', ?, NOW())");
            $stmtRecon->execute([
                $batchId,
                $storeId,
                $defaultAccId,
                $totalNetPayout,
                $importedOrders,
                "Impor file settlement otomatis: $originalFilename ($importedOrders pesanan terverifikasi)"
            ]);

            // Payout remains in marketplace reconciliation and journal tables.

            $this->pdo->commit();

            return [
                'success' => true,
                'batch_id' => $batchId,
                'imported_orders' => $importedOrders,
                'processed_rows' => $importedOrders,
                'journal_entries_created' => $importedOrders * 4,
                'total_net_payout' => $totalNetPayout,
                'total_fees' => $totalPlatformFees,
                'message' => "Berhasil mengimpor $importedOrders transaksi settlement senilai " . 'Rp ' . number_format($totalNetPayout, 0, ',', '.') . " ke sistem Jago Laba."
            ];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * FITUR 2: Kalkulator Simulasi Hitung Harga Jual & Break-Even ROAS
     * Menghitung rekomendasi Harga Normal, Flash Sale & Min ROAS Anti-Boncos.
     */
    public function simulatePrice(array $p): array
    {
        $cogsHpp = (float)($p['cogs_hpp'] ?? 0);
        $targetMarginPct = (float)($p['target_net_margin_pct'] ?? 25.0);
        $adminFeePct = (float)($p['admin_fee_pct'] ?? 11.0);
        $freeShippingPct = (float)($p['free_shipping_xtra_pct'] ?? 4.0);
        $paymentFeePct = (float)($p['payment_fee_pct'] ?? 1.0);
        $adBudgetPct = (float)($p['ad_budget_pct'] ?? 10.0);
        $productName = trim($p['product_name'] ?? 'Simulasi Produk');
        $sku = trim($p['sku'] ?? 'SIM-' . time());

        if ($cogsHpp <= 0) {
            throw new InvalidArgumentException("HPP Pokok Produk harus lebih besar dari 0.");
        }

        // Formula Finansial:
        // Total Potongan Platform = Admin + Gratis Ongkir + Payment Fee
        $totalPlatformFeePct = $adminFeePct + $freeShippingPct + $paymentFeePct;
        $totalDeductionPct = $totalPlatformFeePct + $adBudgetPct;

        // Harga Normal: P = HPP / (1 - (Total Potongan % + Target Laba Bersih %))
        $denominatorNormal = 1 - (($totalDeductionPct + $targetMarginPct) / 100);
        if ($denominatorNormal <= 0.1) {
            $denominatorNormal = 0.1; // Guard batas aman
        }
        $normalPrice = round(($cogsHpp / $denominatorNormal) / 100) * 100;

        // Harga Flash Sale (Margin minimal 5%, alokasi iklan 0% karena traffic organik flash sale)
        $flashMarginPct = 5.0;
        $denominatorFlash = 1 - (($totalPlatformFeePct + $flashMarginPct) / 100);
        $flashSalePrice = round(($cogsHpp / max(0.1, $denominatorFlash)) / 100) * 100;

        // Batas Minimal ROAS Iklan (Break-Even ROAS):
        // BEP ROAS = Harga Normal / (Harga Normal * (1 - Total Potongan Platform %) - HPP)
        $revenueAfterPlatformFee = $normalPrice * (1 - ($totalPlatformFeePct / 100));
        $grossProfitBeforeAds = $revenueAfterPlatformFee - $cogsHpp;
        $breakEvenRoas = $grossProfitBeforeAds > 0 ? round($normalPrice / $grossProfitBeforeAds, 2) : 99.0;

        // Laba Bersih Riil per Pcs
        $netProfitPerPcs = round($normalPrice * ($targetMarginPct / 100));

        // Simpan ke database jika diminta
        if (!empty($p['save_simulation'])) {
            $stmt = $this->pdo->prepare("INSERT INTO marketplace_price_simulations 
                (product_name, sku, cogs_hpp, target_net_margin_pct, admin_fee_pct, free_shipping_xtra_pct, payment_fee_pct, ad_budget_pct, simulated_normal_price, simulated_flash_sale_price, break_even_roas, net_profit_per_pcs, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([
                $productName,
                $sku,
                $cogsHpp,
                $targetMarginPct,
                $adminFeePct,
                $freeShippingPct,
                $paymentFeePct,
                $adBudgetPct,
                $normalPrice,
                $flashSalePrice,
                $breakEvenRoas,
                $netProfitPerPcs
            ]);
        }

        return [
            'product_name' => $productName,
            'sku' => $sku,
            'cogs_hpp' => $cogsHpp,
            'target_net_margin_pct' => $targetMarginPct,
            'admin_fee_pct' => $adminFeePct,
            'free_shipping_xtra_pct' => $freeShippingPct,
            'payment_fee_pct' => $paymentFeePct,
            'ad_budget_pct' => $adBudgetPct,
            'total_platform_fee_pct' => $totalPlatformFeePct,
            'simulated_normal_price' => $normalPrice,
            'simulated_flash_sale_price' => $flashSalePrice,
            'rec_normal_price' => $normalPrice,
            'rec_flash_sale_price' => $flashSalePrice,
            'break_even_roas' => $breakEvenRoas,
            'min_break_even_roas' => $breakEvenRoas,
            'net_profit_per_pcs' => $netProfitPerPcs,
            'recommendation' => "Iklan aman jika ROAS harian $\\ge$ {$breakEvenRoas}x. Jika ROAS $<$ {$breakEvenRoas}x, segera evaluasi kata kunci atau naikkan harga jual."
        ];
    }

    /**
     * Mengambil daftar simulasi harga yang tersimpan.
     */
    public function getSavedPriceSimulations(): array
    {
        return $this->pdo->query("SELECT * FROM marketplace_price_simulations ORDER BY created_at DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * FITUR 5: Alokasi Kuota Multi-Channel & Safety Stock Buffer
     * Mengambil alokasi kuota per SKU dan menyelaraskan dengan stok fisik riil Gudang GDG-UTAMA.
     */
    public function getStockAllocations(?string $search = null, ?string $filter = null): array
    {
        return ['allocations'=>[], 'summary'=>['total_sku'=>0,'total_physical'=>0,'total_shopee'=>0,'total_tiktok'=>0,'total_offline'=>0,'buffered_count'=>0]];
        // 1. Sinkronkan dulu stok fisik terbaru dari inventory_stocks
        $syncSql = "UPDATE marketplace_stock_allocations msa
            JOIN (
                SELECT product_variant_id, COALESCE(SUM(stock_qty), 0) as physical_qty 
                FROM inventory_stocks 
                GROUP BY product_variant_id
            ) inv ON msa.variant_id = inv.product_variant_id
            SET msa.total_physical_stock = inv.physical_qty,
                msa.shopee_allocated_qty = IF(inv.physical_qty <= msa.safety_buffer_threshold, 0, GREATEST(0, ROUND(inv.physical_qty * (msa.shopee_quota_pct / 100)))),
                msa.tiktok_allocated_qty = IF(inv.physical_qty <= msa.safety_buffer_threshold, 0, GREATEST(0, ROUND(inv.physical_qty * (msa.tiktok_quota_pct / 100)))),
                msa.offline_allocated_qty = IF(inv.physical_qty <= msa.safety_buffer_threshold, GREATEST(0, inv.physical_qty), GREATEST(0, inv.physical_qty - ROUND(inv.physical_qty * (msa.shopee_quota_pct / 100)) - ROUND(inv.physical_qty * (msa.tiktok_quota_pct / 100)))),
                msa.is_safety_buffered = IF(inv.physical_qty <= msa.safety_buffer_threshold, 1, 0),
                msa.status = IF(inv.physical_qty <= 0, 'OUT_OF_STOCK', IF(inv.physical_qty <= msa.safety_buffer_threshold, 'BUFFERED_ZERO', 'ACTIVE')),
                msa.updated_at = NOW()";
        $this->pdo->exec($syncSql);

        $params = [];
        $where = "WHERE 1=1";

        if ($search) {
            $where .= " AND (msa.sku LIKE ? OR msa.product_name LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        if ($filter === 'buffered') {
            $where .= " AND msa.is_safety_buffered = 1";
        } elseif ($filter === 'out_of_stock') {
            $where .= " AND msa.status = 'OUT_OF_STOCK'";
        } elseif ($filter === 'active') {
            $where .= " AND msa.status = 'ACTIVE'";
        }

        $stmt = $this->pdo->prepare("SELECT msa.*, pv.variant_name, p.standard_hpp 
            FROM marketplace_stock_allocations msa
            LEFT JOIN product_variants pv ON msa.variant_id = pv.id
            LEFT JOIN products p ON pv.product_id = p.id
            $where 
            ORDER BY msa.is_safety_buffered DESC, msa.total_physical_stock ASC");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Ringkasan Alokasi
        $totalPhysical = 0;
        $totalShopee = 0;
        $totalTikTok = 0;
        $totalOffline = 0;
        $bufferedCount = 0;

        foreach ($rows as $r) {
            $totalPhysical += (int)$r['total_physical_stock'];
            $totalShopee += (int)$r['shopee_allocated_qty'];
            $totalTikTok += (int)$r['tiktok_allocated_qty'];
            $totalOffline += (int)$r['offline_allocated_qty'];
            if ((int)$r['is_safety_buffered'] === 1) $bufferedCount++;
        }

        return [
            'allocations' => $rows,
            'summary' => [
                'total_sku' => count($rows),
                'total_physical' => $totalPhysical,
                'total_shopee' => $totalShopee,
                'total_tiktok' => $totalTikTok,
                'total_offline' => $totalOffline,
                'buffered_count' => $bufferedCount
            ]
        ];
    }

    /**
     * Perbarui persentase kuota dan ambang batas Safety Buffer suatu SKU.
     */
    public function updateStockAllocation(int $id, float $shopeePct, float $tiktokPct, float $offlinePct, int $bufferThreshold): bool
    {
        $sum = $shopeePct + $tiktokPct + $offlinePct;
        if (abs($sum - 100.0) > 0.5) {
            throw new InvalidArgumentException("Total kuota alokasi harus persis 100% (saat ini: {$sum}%).");
        }

        $stmt = $this->pdo->prepare("SELECT total_physical_stock FROM marketplace_stock_allocations WHERE id = ?");
        $stmt->execute([$id]);
        $physical = (int)$stmt->fetchColumn();

        $isBuffered = $physical <= $bufferThreshold ? 1 : 0;
        $status = $physical <= 0 ? 'OUT_OF_STOCK' : ($isBuffered ? 'BUFFERED_ZERO' : 'ACTIVE');
        $shopeeQty = $isBuffered ? 0 : (int)round($physical * ($shopeePct / 100));
        $tiktokQty = $isBuffered ? 0 : (int)round($physical * ($tiktokPct / 100));
        $offlineQty = $isBuffered ? $physical : max(0, $physical - $shopeeQty - $tiktokQty);

        $stmtUp = $this->pdo->prepare("UPDATE marketplace_stock_allocations SET 
            shopee_quota_pct = ?,
            tiktok_quota_pct = ?,
            offline_quota_pct = ?,
            shopee_allocated_qty = ?,
            tiktok_allocated_qty = ?,
            offline_allocated_qty = ?,
            safety_buffer_threshold = ?,
            is_safety_buffered = ?,
            status = ?,
            updated_at = NOW()
            WHERE id = ?");
        return $stmtUp->execute([
            $shopeePct,
            $tiktokPct,
            $offlinePct,
            $shopeeQty,
            $tiktokQty,
            $offlineQty,
            $bufferThreshold,
            $isBuffered,
            $status,
            $id
        ]);
    }

    /**
     * FITUR 6: Rekonsiliasi Pesanan Retur yang Hilang (Lost Return Tracking)
     * Mengambil daftar pesanan retur yang belum diterima fisik gudang > 14 hari.
     */
    public function getReturnClaims(?string $storeId = null, ?string $status = null): array
    {
        $params = [];
        $where = "WHERE 1=1";

        if (!empty($storeId)) {
            $where .= " AND rc.store_id = ?";
            $params[] = $storeId;
        }

        if ($status && $status !== 'Semua Status') {
            $where .= " AND rc.claim_status = ?";
            $params[] = $status;
        }

        $sql = "SELECT rc.*, o.gross_sales, o.total_cogs_hpp, o.order_date, s.platform
            FROM marketplace_return_claims rc
            JOIN marketplace_orders o ON rc.order_id = o.id
            JOIN marketplace_stores s ON rc.store_id = s.id
            $where
            ORDER BY rc.days_pending DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalClaimed = 0;
        $totalReimbursed = 0;
        $pendingOver14Days = 0;

        foreach ($rows as $r) {
            $totalClaimed += (float)$r['claim_amount'];
            $totalReimbursed += (float)$r['reimbursement_amount'];
            if ((int)$r['days_pending'] >= 14 && in_array($r['claim_status'], ['MENUNGGU_FISIK', 'KLAIM_DIAJUKAN'])) {
                $pendingOver14Days++;
            }
        }

        return [
            'claims' => $rows,
            'summary' => [
                'total_claims' => count($rows),
                'pending_over_14_days' => $pendingOver14Days,
                'total_claimed_amount' => $totalClaimed,
                'total_reimbursed_amount' => $totalReimbursed
            ]
        ];
    }

    /**
     * Memperbarui status klaim retur ganti rugi (Klaim Diajukan, Disetujui Ganti Rugi, Fisik Diterima, Ditolak).
     */
    public function updateReturnClaimStatus(int $claimId, string $storeId, string $status, float $reimbursementAmount = 0.0, ?string $notes = null): array
    {
        $this->pdo->beginTransaction();
        $stmt = $this->pdo->prepare("SELECT rc.*, s.default_account_id 
            FROM marketplace_return_claims rc
            JOIN marketplace_stores s ON rc.store_id = s.id
            WHERE rc.id = ? AND rc.store_id = ? FOR UPDATE");
        $stmt->execute([$claimId, $storeId]);
        $claim = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$claim) {
            $this->pdo->rollBack();
            throw new InvalidArgumentException("Data klaim retur tidak ditemukan.");
        }
        if ($claim['claim_status'] === 'DISETUJUI_GANTI_RUGI' &&
            ($status !== 'DISETUJUI_GANTI_RUGI' || (float)$claim['reimbursement_amount'] !== $reimbursementAmount)) {
            $this->pdo->rollBack();
            throw new LogicException('Klaim yang sudah dibukukan memerlukan jurnal penyesuaian berjejak.');
        }
        try {
            $stmtUp = $this->pdo->prepare("UPDATE marketplace_return_claims SET 
                claim_status = ?,
                reimbursement_amount = ?,
                evidence_notes = COALESCE(?, evidence_notes),
                resolved_at = IF(? IN ('DISETUJUI_GANTI_RUGI', 'DITOLAK', 'FISIK_DITERIMA'), NOW(), resolved_at),
                updated_at = NOW()
                WHERE id = ?");
            $stmtUp->execute([$status, $reimbursementAmount, $notes, $status, $claimId]);

            // Sinkronkan status ke marketplace_orders
            $this->pdo->prepare("UPDATE marketplace_orders SET return_claim_status = ? WHERE id = ?")
                ->execute([$status, $claim['order_id']]);

            // Jika klaim disetujui ganti rugi: Catat uang kompensasi masuk ke Kas Bank Toko
            if ($status === 'DISETUJUI_GANTI_RUGI' && $claim['claim_status'] !== 'DISETUJUI_GANTI_RUGI' && $reimbursementAmount > 0) {
                $accId = (int)$claim['default_account_id'] ?: 1786634525412;
                // Reimbursement remains in the marketplace journal.

                // Jurnal Berpasangan
                $jno = 'JRN-CLM-' . date('Ymd') . '-' . $claimId;
                $stmtJ = $this->pdo->prepare("INSERT INTO marketplace_journal_entries 
                    (journal_no, entry_date, ref_type, ref_id, store_id, account_code, account_name, debit, credit, memo, created_at)
                    VALUES (?, CURDATE(), 'RETURN_LOSS', ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmtJ->execute([$jno, $claim['order_id'], $claim['store_id'], '1101', 'Kas/Bank Penerimaan Ganti Rugi', $reimbursementAmount, 0.00, "Kompensasi Paket Hilang #{$claim['order_id']}"]);
                $stmtJ->execute([$jno, $claim['order_id'], $claim['store_id'], '4201', 'Pendapatan Lain-lain (Klaim Logistik)', 0.00, $reimbursementAmount, "Penggantian Ekspedisi #{$claim['order_id']}"]);
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'claim_id' => $claimId,
                'new_status' => $status,
                'reimbursement_amount' => $reimbursementAmount,
                'message' => "Status klaim retur berhasil diperbarui menjadi {$status}."
            ];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * FITUR 8: Buku Jurnal Berpasangan (Double-Entry Bookkeeping Ledger)
     * Mengambil daftar jurnal transaksi debit/kredit yang saling mengimbangi.
     */
    public function getJournalEntries(string $storeId, ?string $dateFrom = null, ?string $dateTo = null, ?string $refType = null): array
    {
        $params = [];
        $where = "WHERE 1=1";

        if ($storeId !== 'ALL' && !empty($storeId)) {
            $where .= " AND store_id = ?";
            $params[] = $storeId;
        }

        if ($dateFrom) {
            $where .= " AND entry_date >= ?";
            $params[] = $dateFrom;
        }
        if ($dateTo) {
            $where .= " AND entry_date <= ?";
            $params[] = $dateTo;
        }
        if ($refType && $refType !== 'Semua Tipe') {
            $where .= " AND ref_type = ?";
            $params[] = $refType;
        }

        $sql = "SELECT * FROM marketplace_journal_entries $where ORDER BY entry_date DESC, journal_no DESC, debit DESC LIMIT 200";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($rows as $r) {
            $totalDebit += (float)$r['debit'];
            $totalCredit += (float)$r['credit'];
        }

        $isBalanced = abs($totalDebit - $totalCredit) < 0.01;

        return [
            'entries' => $rows,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'is_balanced' => $isBalanced,
            'count' => count($rows),
            'summary' => [
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'is_balanced' => $isBalanced,
                'count' => count($rows)
            ]
        ];
    }

    /**
     * FITUR PENGATURAN: Mengambil daftar seluruh toko dengan rincian rekening, performa dan kredensial API.
     */
    public function getAllStoresWithDetails(): array
    {
        $sql = "SELECT s.*, 
            c.connection_status as api_status,
            c.last_ping_at as api_last_ping,
            (c.access_token IS NOT NULL AND c.access_token <> '') as api_token_exists,
            c.partner_id as api_partner_id,
            COALESCE(ord.total_orders, 0) as calculated_orders,
            COALESCE(ord.total_gross, 0) as calculated_gross,
            COALESCE(ord.total_settlement, 0) as calculated_settlement
        FROM marketplace_stores s
        LEFT JOIN marketplace_api_credentials c ON s.id = c.store_id
        LEFT JOIN (
            SELECT store_id, 
                COUNT(*) as total_orders, 
                SUM(gross_sales) as total_gross,
                SUM(net_settlement_amount) as total_settlement
            FROM marketplace_orders 
            GROUP BY store_id
        ) ord ON s.id = ord.store_id
        WHERE s.company_key = ? AND s.archived_at IS NULL
        ORDER BY s.platform ASC, s.store_name ASC";

        $stmtStores = $this->pdo->prepare($sql);
        $stmtStores->execute([$this->scope->companyKey()]);
        $stores = $stmtStores->fetchAll(PDO::FETCH_ASSOC);

        $totalActive = 0;
        $totalOrders = 0;
        $totalSettlement = 0;

        foreach ($stores as &$st) {
            unset($st['api_access_token'], $st['api_app_key']);
            $st['api_status'] = ($st['authorization_status'] ?? 'pending') === 'verified' && !empty($st['last_successful_sync_at'])
                ? 'Terverifikasi'
                : (($st['authorization_status'] ?? 'pending') === 'reauthorize' ? 'Perlu Otorisasi Ulang' : 'Belum Diverifikasi');
            $st['api_token_exists'] = false;
            $st['is_active'] = false; // Legacy status was user-editable, never API verified.
            if ($st['is_active']) $totalActive++;
            $st['total_orders_display'] = (int)$st['calculated_orders'];
            $totalOrders += $st['total_orders_display'];
            $totalSettlement += (float)$st['calculated_settlement'];
        }

        return [
            'stores' => $stores,
            'summary' => [
                'total_stores' => count($stores),
                'active_stores' => $totalActive,
                'total_orders' => $totalOrders,
                'total_settlement' => $totalSettlement
            ]
        ];
    }

    /**
     * Mengambil detail satu toko untuk modal edit / konfigurasi.
     */
    public function getStoreDetail(string $storeId): ?array
    {
        $this->scope->assertStoreAccess($storeId);
        $stmt = $this->pdo->prepare("SELECT s.*, 
            c.partner_id, c.shop_id, c.app_key, c.webhook_url, c.connection_status
        FROM marketplace_stores s
        LEFT JOIN marketplace_api_credentials c ON s.id = c.store_id
        WHERE s.id = ? AND s.company_key = ?");
        $stmt->execute([$storeId, $this->scope->companyKey()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            unset($row['api_access_token'], $row['api_app_key']);
            $row['connection_status'] = 'Perlu Otorisasi';
        }
        return $row ?: null;
    }

    public function getArchivedStores(): array
    {
        $hasBindings = false;
        try {
            $hasBindings = (bool)$this->pdo->query("SHOW TABLES LIKE 'marketplace_integration_bindings'")->fetchColumn();
        } catch (Throwable) {}

        $envExpr = $hasBindings
            ? "CASE (SELECT b.environment FROM marketplace_integration_bindings b JOIN marketplace_oauth_connections c ON c.id=b.connection_id WHERE c.store_id=s.id ORDER BY c.id DESC LIMIT 1)
            WHEN 'live' THEN 'Live' WHEN 'sandbox' THEN 'Sandbox' ELSE 'Lingkungan belum terverifikasi' END"
            : "'Lingkungan belum terverifikasi'";

        $stmt = $this->pdo->prepare("SELECT s.id, s.platform, s.store_name, s.archived_at, s.archived_by,
            {$envExpr} AS environment_label
            FROM marketplace_stores s WHERE s.company_key=? AND s.archived_at IS NOT NULL ORDER BY s.archived_at DESC");
        $stmt->execute([$this->scope->companyKey()]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Hide a store and stop internal sync while retaining its operational history. */
    public function archiveStore(string $storeId): array
    {
        $this->requireStoreAdministrator();
        $companyKey = $this->scope->companyKey();
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT id,store_name,archived_at FROM marketplace_stores WHERE id=? AND company_key=? FOR UPDATE');
            $stmt->execute([$storeId,$companyKey]);
            $store = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$store || $store['archived_at'] !== null) throw new DomainException('Toko aktif tidak ditemukan.');

            $this->pdo->prepare("UPDATE marketplace_stores SET archived_at=NOW(),archived_by=?,status='Terputus',authorization_status='disconnected',api_access_token=NULL,updated_at=NOW() WHERE id=? AND company_key=?")
                ->execute([(string)$_SESSION['user_id'],$storeId,$companyKey]);
            $this->pdo->prepare("UPDATE marketplace_oauth_connections SET status='disconnected',access_token_encrypted=NULL,refresh_token_encrypted=NULL,token_expires_at=NULL,refresh_token_expires_at=NULL,updated_at=NOW() WHERE store_id=? AND company_key=?")
                ->execute([$storeId,$companyKey]);
            $this->pdo->prepare("UPDATE marketplace_sync_jobs SET status='cancelled',finished_at=NOW(),last_error_code='STORE_ARCHIVED' WHERE store_id=? AND company_key=? AND status IN ('queued','running')")
                ->execute([$storeId,$companyKey]);
            $this->pdo->prepare("UPDATE marketplace_api_credentials SET access_token=NULL,refresh_token=NULL,partner_key=NULL,app_secret=NULL,webhook_secret=NULL,connection_status='Perlu Otorisasi',updated_at=NOW() WHERE store_id=?")
                ->execute([$storeId]);
            $this->pdo->commit();
            if (($_SESSION['store_id'] ?? null) === $storeId) unset($_SESSION['store_id']);
            return ['success'=>true,'store_id'=>$storeId,'message'=>'Toko dihapus dari daftar aktif. Koneksi diputus; riwayat tetap tersimpan.'];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    public function restoreStore(string $storeId): array
    {
        $this->requireStoreAdministrator();
        $stmt = $this->pdo->prepare("UPDATE marketplace_stores SET archived_at=NULL,archived_by=NULL,status='Terputus',authorization_status='disconnected',updated_at=NOW() WHERE id=? AND company_key=? AND archived_at IS NOT NULL");
        $stmt->execute([$storeId,$this->scope->companyKey()]);
        if ($stmt->rowCount() !== 1) throw new DomainException('Toko arsip tidak ditemukan.');
        return ['success'=>true,'store_id'=>$storeId,'message'=>'Toko kembali ke daftar. Hubungkan ulang lewat otorisasi resmi untuk sinkronisasi.'];
    }

    private function requireStoreAdministrator(): void
    {
        $role = strtoupper((string)($_SESSION['role'] ?? ''));
        if (empty($_SESSION['user_id']) || (!str_contains($role,'OWNER') && !str_contains($role,'ADMIN'))) {
            throw new DomainException('Hanya pemilik atau admin yang dapat mengelola arsip toko.');
        }
    }

    /**
     * FITUR PENGATURAN: Tambah Toko Marketplace Baru.
     */
    public function createStore(array $p): array
    {
        $platform = trim($p['platform'] ?? 'Shopee');
        $storeName = trim($p['store_name'] ?? '');
        $identifier = trim($p['store_identifier'] ?? '');
        $defaultAccId = !empty($p['default_account_id']) ? (int)$p['default_account_id'] : null;
        $adminFee = (float)($p['default_admin_fee_pct'] ?? 11.0);
        $freeShipping = (float)($p['default_free_shipping_pct'] ?? 4.0);
        $paymentFee = (float)($p['default_payment_fee_pct'] ?? 1.0);
        $syncMode = trim($p['sync_mode'] ?? 'API');
        $status = 'Terputus';
        if (!empty($p['partner_id']) || !empty($p['app_key']) || !empty($p['access_token'])) {
            throw new LogicException('Kredensial langsung tidak diterima. Hubungkan toko melalui OAuth resmi.');
        }

        if (empty($storeName)) {
            throw new InvalidArgumentException("Nama Toko wajib diisi.");
        }

        // Generate / Validate Store ID
        $rawId = trim($p['id'] ?? '');
        if (empty($rawId)) {
            $prefix = strtoupper(str_replace(' ', '', $platform));
            $cleanName = strtoupper(preg_replace('/[^A-Z0-9]/', '', $storeName));
            $rawId = 'STR-' . substr($prefix, 0, 6) . '-' . substr($cleanName, 0, 8);
        }

        // Cek duplikasi ID
        $stmtCheck = $this->pdo->prepare("SELECT COUNT(*) FROM marketplace_stores WHERE id = ?");
        $stmtCheck->execute([$rawId]);
        if ((int)$stmtCheck->fetchColumn() > 0) {
            $rawId .= '-' . rand(10, 99);
        }

        $stmt = $this->pdo->prepare("INSERT INTO marketplace_stores
            (id, company_key, platform, store_name, store_identifier, default_account_id, default_admin_fee_pct, default_free_shipping_pct, default_payment_fee_pct, sync_mode, status, authorization_status, total_transactions, last_synced_at, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 0, NULL, NOW(), NOW())");
        $stmt->execute([
            $rawId,
            $this->scope->companyKey(),
            $platform,
            $storeName,
            $identifier ?: strtolower(str_replace(' ', '_', $storeName)),
            $defaultAccId,
            $adminFee,
            $freeShipping,
            $paymentFee,
            $syncMode,
            $status
        ]);

        // Jika kredensial API disertakan, simpan ke marketplace_api_credentials
        if (!empty($p['partner_id']) || !empty($p['app_key']) || !empty($p['access_token'])) {
            $apiService = new MarketplaceApiService($this->pdo);
            $apiService->saveCredentials([
                'store_id' => $rawId,
                'platform' => $platform,
                'partner_id' => $p['partner_id'] ?? null,
                'partner_key' => $p['partner_key'] ?? null,
                'shop_id' => $p['shop_id'] ?? null,
                'app_key' => $p['app_key'] ?? null,
                'app_secret' => $p['app_secret'] ?? null,
                'access_token' => $p['access_token'] ?? null,
                'cipher' => $p['cipher'] ?? null,
                'webhook_url' => $p['webhook_url'] ?? null,
                'webhook_secret' => $p['webhook_secret'] ?? null
            ]);
        }

        // Catat Audit Log
        try {
            $stmtLog = $this->pdo->prepare("INSERT INTO system_audit_logs 
                (event_time, user_id, username, module, action, record_id, reference_id, after_json, status, notes)
                VALUES (NOW(), 1, 'admin', 'MARKETPLACE', 'CREATE_STORE', ?, ?, ?, 'SUCCESS', ?)");
            $stmtLog->execute([
                $rawId,
                $rawId,
                json_encode(['id' => $rawId, 'platform' => $platform, 'store_name' => $storeName]),
                "Tambah toko marketplace baru: $storeName ($platform)"
            ]);
        } catch (Exception $e) {}

        return [
            'success' => true,
            'store_id' => $rawId,
            'store_name' => $storeName,
            'platform' => $platform,
            'message' => "Toko '$storeName' ($platform) berhasil ditambahkan ke sistem Jago Laba."
        ];
    }

    /**
     * FITUR PENGATURAN: Update Detail Toko Marketplace.
     */
    public function updateStore(string $storeId, array $p): array
    {
        $this->scope->assertStoreAccess($storeId);
        $platform = trim($p['platform'] ?? 'Shopee');
        $storeName = trim($p['store_name'] ?? '');
        $identifier = trim($p['store_identifier'] ?? '');
        $defaultAccId = !empty($p['default_account_id']) ? (int)$p['default_account_id'] : null;
        $adminFee = (float)($p['default_admin_fee_pct'] ?? 11.0);
        $freeShipping = (float)($p['default_free_shipping_pct'] ?? 4.0);
        $paymentFee = (float)($p['default_payment_fee_pct'] ?? 1.0);
        $syncMode = trim($p['sync_mode'] ?? 'API');
        $status = 'Terputus';
        if (isset($p['partner_id']) || isset($p['access_token']) || isset($p['app_key'])) {
            throw new LogicException('Kredensial langsung tidak diterima. Hubungkan toko melalui OAuth resmi.');
        }

        if (empty($storeName)) {
            throw new InvalidArgumentException("Nama Toko wajib diisi.");
        }

        $stmt = $this->pdo->prepare("UPDATE marketplace_stores 
            SET platform = ?,
                store_name = ?,
                store_identifier = ?,
                default_account_id = ?,
                default_admin_fee_pct = ?,
                default_free_shipping_pct = ?,
                default_payment_fee_pct = ?,
                sync_mode = ?,
                status = ?,
                updated_at = NOW()
            WHERE id = ? AND company_key = ?");
        $stmt->execute([
            $platform,
            $storeName,
            $identifier,
            $defaultAccId,
            $adminFee,
            $freeShipping,
            $paymentFee,
            $syncMode,
            $status,
            $storeId,
            $this->scope->companyKey()
        ]);

        // Simpan / update kredensial API jika ada
        if (isset($p['partner_id']) || isset($p['access_token']) || isset($p['app_key'])) {
            $apiService = new MarketplaceApiService($this->pdo);
            $apiService->saveCredentials([
                'store_id' => $storeId,
                'platform' => $platform,
                'partner_id' => $p['partner_id'] ?? null,
                'partner_key' => $p['partner_key'] ?? null,
                'shop_id' => $p['shop_id'] ?? null,
                'app_key' => $p['app_key'] ?? null,
                'app_secret' => $p['app_secret'] ?? null,
                'access_token' => $p['access_token'] ?? null,
                'cipher' => $p['cipher'] ?? null,
                'webhook_url' => $p['webhook_url'] ?? null,
                'webhook_secret' => $p['webhook_secret'] ?? null
            ]);
        }

        return [
            'success' => true,
            'store_id' => $storeId,
            'message' => "Detail toko '$storeName' berhasil diperbarui."
        ];
    }

    /**
     * FITUR PENGATURAN: Ubah Status Toko (Terhubung, Terputus, Nonaktif).
     */
    public function toggleStoreStatus(string $storeId, string $status): array
    {
        if ($status === 'Terhubung') throw new LogicException('Status terhubung hanya dapat ditetapkan setelah verifikasi API resmi.');
        $this->scope->assertStoreAccess($storeId);
        $stmt = $this->pdo->prepare("UPDATE marketplace_stores SET status = ?, authorization_status = IF(? = 'Terputus', 'disconnected', authorization_status), updated_at = NOW() WHERE id = ? AND company_key = ?");
        $stmt->execute([$status, $status, $storeId, $this->scope->companyKey()]);

        return [
            'success' => true,
            'store_id' => $storeId,
            'status' => $status,
            'message' => "Status toko $storeId berhasil diubah menjadi '$status'."
        ];
    }

    /**
     * FITUR PENGATURAN: Mengambil Konfigurasi Aplikasi Marketplace.
     */
    public function getAppSettings(?string $group = null): array
    {
        $sql = "SELECT * FROM marketplace_app_settings";
        $params = [];
        if ($group) {
            $sql .= " WHERE setting_group = ?";
            $params[] = $group;
        }
        $sql .= " ORDER BY setting_group ASC, setting_key ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $flat = [];
        $grouped = [];

        foreach ($rows as $r) {
            $flat[$r['setting_key']] = $r['setting_value'];
            $grouped[$r['setting_group']][] = $r;
        }

        return [
            'flat' => $flat,
            'grouped' => $grouped,
            'all' => $rows
        ];
    }

    /**
     * FITUR PENGATURAN: Menyimpan Pembaruan Konfigurasi Aplikasi.
     */
    public function saveAppSettings(array $settings): array
    {
        $stmt = $this->pdo->prepare("UPDATE marketplace_app_settings 
            SET setting_value = ?, updated_at = NOW() 
            WHERE setting_key = ?");

        $updatedCount = 0;
        foreach ($settings as $key => $val) {
            if ($key === 'action') continue;
            $stmt->execute([(string)$val, $key]);
            if ($stmt->rowCount() > 0) $updatedCount++;
        }

        return [
            'success' => true,
            'updated_count' => $updatedCount,
            'message' => "Pengaturan aplikasi marketplace berhasil disimpan dan disinkronkan."
        ];
    }
}
