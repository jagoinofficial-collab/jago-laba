<?php
declare(strict_types=1);

// OAuth settings are read from the server environment. Secret values are never
// returned to the browser, logged, or committed to this repository.
$env = static function (string $name, string $default = ''): string {
    $value = getenv($name);
    return $value === false ? $default : trim((string)$value);
};
$baseUrl = rtrim($env('JL_APP_BASE_URL'), '/');
$callbackUrl = $baseUrl === '' ? '' : $baseUrl . '/public/api/marketplace-oauth.php';
$shopeeRegion = strtolower($env('JL_SHOPEE_REGION', 'live'));
$shopeeAuthDefault = $shopeeRegion === 'sandbox' ? 'https://open.sandbox.test-stable.shopee.com/auth' : 'https://open.shopee.com/auth';
$shopeeApiDefault = $shopeeRegion === 'sandbox' ? 'https://openplatform.sandbox.test-stable.shopee.sg' : 'https://partner.shopeemobile.com';
$tiktokRegion = strtolower($env('JL_TIKTOK_SHOP_REGION', 'row'));
$tiktokAuthHost = $tiktokRegion === 'us' ? 'services.us.tiktokshop.com' : 'services.tiktokshop.com';

return [
    'supported_platforms' => ['Shopee', 'TikTok Shop', 'Tokopedia', 'Lazada', 'Blibli'],
    'credentials_source' => 'server_environment_or_encrypted_storage',
    'credentials_exposed_to_client' => false,
    'oauth' => [
        'base_url' => $baseUrl,
        'callback_url' => $callbackUrl,
        'webhook_url' => $env('JL_MARKETPLACE_WEBHOOK_URL'),
        'state_ttl_seconds' => 600,
        'return_path' => 'marketplace.php?scope=global&tab=integrasi',
        'shopee' => [
            'partner_id' => $env('JL_SHOPEE_PARTNER_ID'),
            'partner_key' => $env('JL_SHOPEE_PARTNER_KEY'),
            'region' => $shopeeRegion,
            'auth_url' => $env('JL_SHOPEE_AUTH_URL', $shopeeAuthDefault),
            'api_base_url' => rtrim($env('JL_SHOPEE_API_BASE_URL', $shopeeApiDefault), '/'),
        ],
        'tiktok_shop' => [
            'app_key' => $env('JL_TIKTOK_SHOP_APP_KEY'),
            'app_secret' => $env('JL_TIKTOK_SHOP_APP_SECRET'),
            'service_id' => $env('JL_TIKTOK_SHOP_SERVICE_ID'),
            'market' => $env('JL_TIKTOK_SHOP_MARKET'),
            'region' => $tiktokRegion,
            'auth_url' => $env('JL_TIKTOK_SHOP_AUTH_URL', 'https://' . $tiktokAuthHost . '/open/authorize'),
            'api_base_url' => rtrim($env('JL_TIKTOK_SHOP_API_BASE_URL', 'https://open-api.tiktokglobalshop.com'), '/'),
            'required_scopes' => array_values(array_filter(array_map('trim', explode(',', $env('JL_TIKTOK_SHOP_REQUIRED_SCOPES', 'seller.authorization.info'))))),
        ],
    ],
];
