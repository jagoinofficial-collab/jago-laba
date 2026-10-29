<?php
declare(strict_types=1);

final class MarketplaceModule
{
    private static bool $booted = false;

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }

        $appRoot = (dirname(__DIR__, 3) . '/app');
        foreach ([
            '/Shared/Security/MarketplaceTokenVault.php',
            '/Modules/Marketplace/Integrations/Contracts/MarketplaceOAuthClient.php',
            '/Modules/Marketplace/Integrations/Support/MarketplaceIntegrationException.php',
            '/Modules/Marketplace/Integrations/Support/MarketplaceHttpClient.php',
            '/Modules/Marketplace/Integrations/Shopee/ShopeeOAuthClient.php',
            '/Modules/Marketplace/Integrations/TikTokShop/TikTokShopOAuthClient.php',
            '/Shared/Companies/MarketplaceScopeService.php',
            '/Modules/Marketplace/Services/MarketplacePartnerSettings.php',
            '/Modules/Marketplace/Services/MarketplaceApiService.php',
            '/Modules/Marketplace/Services/MarketplaceInboxService.php',
            '/Modules/Marketplace/Services/MarketplaceIngestionService.php',
            '/Modules/Marketplace/Services/MarketplaceOAuthService.php',
            '/Modules/Marketplace/Services/MarketplaceService.php',
        ] as $file) {
            require_once $appRoot . $file;
        }

        self::$booted = true;
    }
}
