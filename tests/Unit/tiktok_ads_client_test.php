<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/Bootstrap/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/Modules/Marketplace/Integrations/TikTokAds/TikTokAdsClient.php';

function assertAds(bool $cond, string $msg): void
{
    if (!$cond) {
        throw new RuntimeException("Assertion failed: " . $msg);
    }
}

// 1. Authorization URL creation
$client = new TikTokAdsClient(['app_id' => 'TIKTOK-APP-123']);
$url = $client->getAuthorizationUrl('https://example.com/callback', 'state-xyz');
assertAds(str_contains($url, 'app_id=TIKTOK-APP-123'), 'Auth URL contains app_id');
assertAds(str_contains($url, 'state=state-xyz'), 'Auth URL contains state');
assertAds(str_contains($url, 'https://business-api.tiktok.com/portal/auth'), 'Auth URL uses official portal endpoint');

// 2. Missing config rejection
$unconfigured = new TikTokAdsClient([]);
$caught = false;
try {
    $unconfigured->getAuthorizationUrl('https://example.com', 'xyz');
} catch (MarketplaceIntegrationException $e) {
    $caught = true;
    assertAds($e->errorCode === 'TIKTOK_ADS_CONFIG_MISSING', 'Throws correct error code');
}
assertAds($caught, 'Rejects unconfigured auth attempt');

// 3. Missing advertiser ID rejection
$noAdvertiser = new TikTokAdsClient(['app_id' => '123'], null);
$caught = false;
try {
    $noAdvertiser->getPerformanceReport('token-abc', '2026-10-01', '2026-10-25');
} catch (MarketplaceIntegrationException $e) {
    $caught = true;
    assertAds($e->errorCode === 'TIKTOK_ADS_ADVERTISER_ID_MISSING', 'Advertiser ID strictly required');
}
assertAds($caught, 'Rejects call without advertiser ID');

// 4. Normalization and metric separation
$mockTransport = function ($method, $url, $headers, $body) {
    return [
        'code' => 0,
        'message' => 'OK',
        'data' => [
            'list' => [
                [
                    'dimensions' => [
                        'stat_time_day' => '2026-10-20 00:00:00',
                        'campaign_id' => 'CAMP-9988',
                        'campaign_name' => 'TikTok Live Flash Sale',
                    ],
                    'metrics' => [
                        'spend' => '150000.00',
                        'stat_cost' => '150000.00',
                        'conversion' => '15',
                        'total_purchase_value' => '1250000.00',
                    ],
                ],
            ],
        ],
    ];
};

$liveClient = new TikTokAdsClient(
    ['app_id' => '123', 'secret' => '456'],
    'ADV-776655',
    null,
    $mockTransport
);

$rows = $liveClient->getPerformanceReport('token-mock', '2026-10-20', '2026-10-20');
assertAds(count($rows) === 1, 'Returns 1 normalized campaign row');
$row = $rows[0];
assertAds($row['advertiser_id'] === 'ADV-776655', 'Preserves advertiser ID');
assertAds($row['spend_amount'] === 150000.0, 'Spend matches stat_cost');
assertAds($row['attributed_revenue'] === 1250000.0, 'Attributed revenue matches purchase value');
assertAds($row['conversions'] === 15, 'Conversions match orders');
assertAds($row['roas'] === 8.33, 'ROAS computed as 1250000 / 150000 = 8.33');
assertAds($row['campaign_date'] === '2026-10-20', 'Date formatted to YYYY-MM-DD');

echo "PASS: TikTokAdsClient authorization, advertiser separation, error handling and metrics normalization.\n";

