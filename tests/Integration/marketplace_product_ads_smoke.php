<?php
declare(strict_types=1);
require_once (dirname(__DIR__, 2) . '/app/Modules/Marketplace/Services/MarketplaceService.php');
// Exercise query/data behavior in memory; adapt only MySQL locking/time syntax.
final class AdTestPdo extends PDO {
    public function prepare(string $query, array $options = []): PDOStatement|false {
        return parent::prepare(str_replace([' FOR UPDATE', 'NOW()'], ['', "datetime('now')"], $query), $options);
    }
}
function ensureAd(bool $value, string $message): void { if (!$value) throw new RuntimeException($message); }
$pdo = new AdTestPdo('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE marketplace_stores (id TEXT PRIMARY KEY, company_key TEXT, platform TEXT, archived_at TEXT, authorization_status TEXT)");
$pdo->exec("INSERT INTO marketplace_stores VALUES ('S','A','Shopee',NULL,'pending'),('T','A','TikTok Shop',NULL,'pending'),('X','B','Shopee',NULL,'pending')");
$pdo->exec("CREATE TABLE marketplace_product_ads (id INTEGER PRIMARY KEY AUTOINCREMENT, store_id TEXT, platform TEXT, ad_type TEXT, marketplace_item_id TEXT, sku TEXT, product_name TEXT, campaign_name TEXT, period_start TEXT, period_end TEXT, ad_spend REAL, gross_revenue REAL, orders_count INTEGER, qty_sold INTEGER, unit_cogs_hpp REAL, platform_fee REAL, affiliate_fee REAL, image_url TEXT, last_synced_at TEXT, is_bocor INTEGER DEFAULT 0, bocor_reason TEXT)");
$_SESSION = ['company_key' => 'A'];
$service = new MarketplaceService($pdo);
$path = tempnam(sys_get_temp_dir(), 'ads-test-');
$header = trim(file_get_contents((dirname(__DIR__, 2) . '/public/assets/marketplace/product-ad-report-template.csv')));
try {
    file_put_contents($path, $header . "\nP1,SKU1,Produk A,Campaign,2026-10-23,2026-10-23,100,1000,2,3,100,100,50\n");
    ensureAd($service->importProductAdReport('S', $path) === 1, 'import failed');
    $service->importProductAdReport('S', $path);
    ensureAd((int)$pdo->query('SELECT COUNT(*) FROM marketplace_product_ads')->fetchColumn() === 1, 'repeat import duplicated cost');
    $service->importProductAdReport('T', $path);
    try { $service->importProductAdReport('X', $path); throw new RuntimeException('foreign store allowed'); } catch (DomainException $expected) {}
    $data = $service->getMarketingDashboard('ALL', null, null, null, 'Produk');
    ensureAd($data['kpi']['biaya_iklan'] === 200.0 && $data['kpi']['pendapatan'] === 2000.0, 'KPI differs from products');
    $p = $data['products'][0];
    ensureAd($p['gross_roas'] === 10.0 && $p['net_roas'] === 8.5 && $p['actual_roas'] === 5.5, 'ROAS formula incorrect');
    ensureAd($p['net_profit'] === 450.0 && $p['cost_per_order'] === 50.0, 'profit or cost incorrect');
    ensureAd(count($service->getMarketingDashboard('ALL', null, null, 'TikTok Shop', 'Produk')['products']) === 1, 'platform filter failed');
    ensureAd($service->getMarketingDashboard('ALL', null, null, null, 'Produk', false, ['date_start' => '2026-10-24'])['products'] === [], 'period filter failed');
    try { $service->getMarketingDashboard('X'); throw new RuntimeException('foreign report allowed'); } catch (DomainException $expected) {}
    file_put_contents($path, $header . "\nP1,SKU1,Produk A,Campaign,2026-10-22,2026-10-24,100,1000,2,3,100,100,50\n");
    try { $service->importProductAdReport('S', $path); throw new RuntimeException('overlap accepted'); } catch (InvalidArgumentException $expected) {}
    ensureAd(!$pdo->inTransaction(), 'transaction left open');
    file_put_contents($path, $header . "\nP1,SKU1,Produk A,Campaign,2026-10-23,2026-10-23,-100,1000,2,3,100,100,50\n");
    try { $service->importProductAdReport('S', $path); throw new RuntimeException('negative spend accepted'); } catch (InvalidArgumentException $expected) {}
    ensureAd((int)$pdo->query('SELECT COUNT(*) FROM marketplace_product_ads')->fetchColumn() === 2, 'failed import changed database');
    if (!class_exists('Workspace')) { class Workspace { public static function csrf(): string { return 'test-token'; } } }
    function formatRp($value): string { return 'Rp ' . number_format((float)$value, 0, ',', '.'); }
    $marketingData = $data;
    $marketingData['products'][0]['product_name'] = '<script>unsafe</script>';
    $marketingFilter = '';
    $stores = [];
    $query = static fn(array $changes = []): string => http_build_query($changes);
    ob_start();
    require (dirname(__DIR__, 2) . '/app/Modules/Marketplace/Views/marketplace_global_marketing.php');
    $html = ob_get_clean();
    ensureAd(str_contains($html, 'Actual ROAS') && str_contains($html, 'Net Profit') && str_contains($html, '&lt;script&gt;unsafe&lt;/script&gt;') && !str_contains($html, '<script>unsafe</script>'), 'dashboard render or escaping failed');
    echo "PASS: import, repeat import, overlap rollback, numeric validation, company isolation, platform/date filters, KPI and profit formulas\n";
} finally { unlink($path); }
