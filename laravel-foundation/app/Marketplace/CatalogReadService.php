<?php

namespace App\Marketplace;

use PDO;

final class CatalogReadService
{
    public function __construct(private readonly LegacyReadContext $context) {}

    public function listing(string $companyKey, string $storeId, array $filters): array
    {
        require_once dirname(__DIR__, 3).'/app/Modules/Marketplace/Services/MarketplaceCatalogService.php';

        return $this->context->run($companyKey, fn (PDO $pdo): array => (new \MarketplaceCatalogService($pdo))->listingForStore($storeId, $filters));
    }
}
