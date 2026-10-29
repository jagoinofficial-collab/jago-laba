<?php
declare(strict_types=1);

require_once (dirname(__DIR__, 2) . '/app/Bootstrap/bootstrap.php');
require_once (dirname(__DIR__, 2) . '/app/Shared/Security/MarketplaceTokenVault.php');
require_once (dirname(__DIR__, 2) . '/app/Modules/Marketplace/Integrations/Contracts/MarketplaceOAuthClient.php');
require_once (dirname(__DIR__, 2) . '/app/Modules/Marketplace/Integrations/Support/MarketplaceIntegrationException.php');
require_once (dirname(__DIR__, 2) . '/app/Shared/Companies/MarketplaceScopeService.php');
require_once (dirname(__DIR__, 2) . '/app/Modules/Marketplace/Services/MarketplaceOAuthService.php');
require_once (dirname(__DIR__, 2) . '/app/Modules/Marketplace/Integrations/Support/MarketplaceHttpClient.php');
require_once (dirname(__DIR__, 2) . '/app/Modules/Marketplace/Services/MarketplacePartnerSettings.php');

putenv('JL_MARKETPLACE_TOKEN_ENCRYPTION_KEY=' . base64_encode(random_bytes(32)));
$_SESSION['user_id'] = 'USER-1';
$_SESSION['role'] = 'OWNER';
$_SESSION['company_key'] = 'COMPANY-A';

final class MarketplaceOAuthFakeClient implements MarketplaceOAuthClient
{
    public function __construct(private array $shops = [['id' => 'SHOP-1', 'name' => 'Toko Uji', 'cipher' => null, 'region' => 'ID']]) {}
    public function platform(): string { return 'Shopee'; }
    public function configurationMissing(): array { return []; }
    public function authorizationUrl(string $state, string $redirectUri): string { return 'https://official.example.test/authorize?state=' . rawurlencode($state); }
    public function exchangeCode(string $code, array $callback): array { return ['access_token' => 'ACCESS-REAL-SHAPED', 'refresh_token' => 'REFRESH-REAL-SHAPED', 'token_expires_at' => date('Y-m-d H:i:s', time() + 3600), 'refresh_token_expires_at' => date('Y-m-d H:i:s', time() + 7200), 'granted_scopes' => ['shop.read'], 'shops' => $this->shops]; }
    public function refreshToken(string $refreshToken, ?string $externalStoreId = null, ?string $shopCipher = null): array { return ['access_token' => 'ACCESS-REFRESHED', 'refresh_token' => $refreshToken, 'token_expires_at' => date('Y-m-d H:i:s', time() + 3600), 'refresh_token_expires_at' => date('Y-m-d H:i:s', time() + 7200), 'granted_scopes' => ['shop.read'], 'shops' => $this->shops]; }
}

$name = 'jl_marketplace_oauth_test_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3));
$server = Database::serverPdo();
$server->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$cfg = Database::config()['db'];
$pdo = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $cfg['host'], $cfg['port'], $name), $cfg['user'], $cfg['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec("CREATE TABLE marketplace_stores (
    id VARCHAR(50) PRIMARY KEY, company_key VARCHAR(191) NOT NULL, platform VARCHAR(40) NOT NULL,
    store_name VARCHAR(191) NOT NULL, store_identifier VARCHAR(191) NOT NULL, sync_mode VARCHAR(40) NOT NULL DEFAULT 'API',
    status VARCHAR(40) NOT NULL DEFAULT 'Terputus', authorization_status VARCHAR(30) NOT NULL DEFAULT 'pending',
    total_transactions INT NOT NULL DEFAULT 0, last_successful_sync_at DATETIME NULL, last_error_code VARCHAR(100) NULL,
    created_at DATETIME NOT NULL, updated_at DATETIME NULL, sync_history_start_at DATETIME NULL,
    archived_at DATETIME NULL, archived_by VARCHAR(120) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$sql = preg_replace('/^--.*$/m', '', file_get_contents((dirname(__DIR__, 2) . '/database/migrations/marketplace/migration_marketplace_oauth.sql')));
foreach (array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', (string)$sql) ?: [])) as $statement) $pdo->exec($statement);
MarketplaceIncrementalSync::install($pdo);

$config = [
    'base_url' => 'https://jago-laba.example.test',
    'callback_url' => 'https://jago-laba.example.test/public/api/marketplace-oauth.php',
    'state_ttl_seconds' => 600,
    'return_path' => 'marketplace.php?scope=global&tab=projects',
];
$client = new MarketplaceOAuthFakeClient();
$service = new MarketplaceOAuthService($pdo, ['Shopee' => $client], $config);
$started = $service->startAuthorization('Shopee');
parse_str((string)parse_url($started['authorization_url'], PHP_URL_QUERY), $authQuery);
$result = $service->handleCallback(['state' => $authQuery['state'], 'code' => 'ONE-TIME-CODE', 'platform' => 'Shopee']);
if ($result['status'] !== 'success') throw new RuntimeException('Callback sukses tidak menghasilkan koneksi.');
$storeId = (string)$result['store_id'];
if ((int)$pdo->query("SELECT COUNT(*) FROM marketplace_oauth_connections WHERE store_id=" . $pdo->quote($storeId))->fetchColumn() !== 1) throw new RuntimeException('Koneksi tidak tersimpan.');
if ((int)$pdo->query('SELECT COUNT(*) FROM marketplace_sync_jobs WHERE store_id=' . $pdo->quote($storeId) . ' AND status="queued"')->fetchColumn() !== 3) throw new RuntimeException('Antrean sinkronisasi awal tidak lengkap.');
try {
    $service->handleCallback(['state' => $authQuery['state'], 'code' => 'REPLAY', 'platform' => 'Shopee']);
    throw new RuntimeException('State dapat digunakan ulang.');
} catch (MarketplaceIntegrationException $expected) {
    if ($expected->errorCode !== 'INVALID_OAUTH_STATE') throw $expected;
}

$second = $service->startAuthorization('Shopee');
parse_str((string)parse_url($second['authorization_url'], PHP_URL_QUERY), $secondQuery);
$_SESSION['company_key'] = 'COMPANY-B';
try {
    $service->handleCallback(['state' => $secondQuery['state'], 'code' => 'CROSS-COMPANY', 'platform' => 'Shopee']);
    throw new RuntimeException('State lolos lintas perusahaan.');
} catch (MarketplaceIntegrationException $expected) {
    if ($expected->errorCode !== 'INVALID_OAUTH_STATE') throw $expected;
}

foreach($pdo->query("SELECT checkpoint FROM marketplace_sync_jobs WHERE job_type='orders'")->fetchAll(PDO::FETCH_COLUMN) as $checkpoint){$window=json_decode($checkpoint,true);if(($window['filter']['create_time_ge']??0)<MarketplaceSyncWindow::checkpoint()['window_start'])throw new RuntimeException('OAuth initial sync must be bounded to integration month');}
$calls = [];
$fakeRequest = static function (string $method, string $url, array $headers = []) use (&$calls): array {
    $calls[] = [$method, $url, $headers];
    return str_contains($url, 'shopeemobile.com') ? ['error' => '', 'response' => ['authed_shop_list' => []]] : ['code' => 0, 'data' => ['shops' => []]];
};
$partnerSettings = new MarketplacePartnerSettings($pdo, null, $fakeRequest);
$shopee = $partnerSettings->saveAndTest('Shopee', ['partner_id' => '123456', 'partner_key' => 'TEST-PARTNER-SECRET', 'mode' => 'sandbox']);
if (!$shopee['test']['ok'] || !str_contains($calls[0][1], 'openplatform.sandbox.test-stable.shopee.sg/api/v2/public/get_shops_by_partner')) throw new RuntimeException('Uji Shopee tidak memakai sandbox API.');
$shopeeStored = (string)$pdo->query("SELECT settings_encrypted FROM marketplace_partner_settings WHERE platform='Shopee'")->fetchColumn();
if (str_contains($shopeeStored, 'TEST-PARTNER-SECRET') || ($partnerSettings->status('Shopee')['partner_key'] ?? null) !== null) throw new RuntimeException('Partner Key bocor dari penyimpanan atau status.');
$tiktok = $partnerSettings->saveAndTest('TikTok Shop', ['app_key' => 'TEST-APP-KEY', 'app_secret' => 'TEST-APP-SECRET', 'test_access_token' => 'EPHEMERAL-TEST-TOKEN']);
if (!$tiktok['test']['ok'] || !in_array('x-tts-access-token: EPHEMERAL-TEST-TOKEN', $calls[1][2], true)) throw new RuntimeException('Uji TikTok tidak memakai token uji.');
$tiktokStored = (string)$pdo->query("SELECT settings_encrypted FROM marketplace_partner_settings WHERE platform='TikTok Shop'")->fetchColumn();
if (str_contains(MarketplaceTokenVault::decrypt($tiktokStored), 'EPHEMERAL-TEST-TOKEN')) throw new RuntimeException('Token uji TikTok tersimpan.');

echo "TEST_DATABASE=$name\nPASS: OAuth dan pengaturan partner terenkripsi; uji Shopee/TikTok memakai endpoint yang benar tanpa menyimpan token uji.\n";
