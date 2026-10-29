<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require dirname(__DIR__, 2) . '/app/bootstrap.php';
MarketplaceModule::boot();
$options = getopt('', ['start', 'store:', 'steps:']);
$pdo = Database::pdo();
$query = $pdo->query("SELECT s.id,s.company_key FROM marketplace_stores s JOIN marketplace_oauth_connections c ON c.store_id=s.id AND c.company_key=s.company_key WHERE s.platform='Shopee' AND c.platform='Shopee' AND s.archived_at IS NULL AND c.status='verified'");
$stores = $query->fetchAll(PDO::FETCH_ASSOC);
if (isset($options['store'])) $stores = array_values(array_filter($stores, static fn($s) => $s['id'] === $options['store']));
echo json_encode(['connected_shopee_stores' => count($stores)]) . PHP_EOL;
if (!isset($options['start'])) exit;
// Selection must be explicit when more than one company/store is present.
if (count($stores) !== 1) { fwrite(STDERR, "Pilih satu toko terverifikasi dengan --store.\n"); exit(2); }
$store = $stores[0];
$_SESSION = ['company_key'=>$store['company_key'],'user_id'=>'CATALOG-CLI','role'=>'OWNER','workspace'=>'marketplace'];
session_write_close();
$sync = new MarketplaceSyncService($pdo, null, true, true, true);
$run = $sync->enqueueCatalog($store['id']);
$limit = min(200, max(1, (int)($options['steps'] ?? 30)));
for ($i=0; $i<$limit; $i++) {
    $state = $sync->step($store['id'], $run);
    $jobs = array_values(array_filter($state['jobs'], static fn($j) => $j['run_key'] === $run));
    $job = $jobs[0] ?? [];
    echo json_encode(['step'=>$i+1,'status'=>$job['status']??'unknown','products_processed'=>(int)($job['success_count']??0),'error_code'=>$job['last_error_code']??null]) . PHP_EOL;
    if (in_array($job['status'] ?? '', ['failed','succeeded'],true)) break;
}
$count = $pdo->prepare("SELECT COUNT(DISTINCT external_product_id) products,COUNT(*) variants FROM marketplace_catalog_variants WHERE store_id=? AND catalog_visible=1 AND source_status='NORMAL'");
$count->execute([$store['id']]);
echo json_encode(['catalog'=>$count->fetch(PDO::FETCH_ASSOC)]) . PHP_EOL;
exit(($job['status']??'')==='failed'?1:0);
