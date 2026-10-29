<?php
declare(strict_types=1);

require_once (dirname(__DIR__, 3) . '/Shared/Companies/MarketplaceScopeService.php');
require_once __DIR__ . '/../Models/MarketplaceStore.php';

/**
 * Repository for accessing Marketplace Store domain entities.
 * Strictly guarantees tenant isolation.
 */
final class MarketplaceStoreRepository
{
    private MarketplaceScopeService $scope;

    public function __construct(private PDO $pdo)
    {
        $this->scope = new MarketplaceScopeService($this->pdo);
    }

    /**
     * @return MarketplaceStore[]
     */
    public function findActiveStores(): array
    {
        $companyKey = $this->scope->companyKey();
        $stmt = $this->pdo->prepare(
            'SELECT s.*, c.id AS oauth_connection_id 
             FROM marketplace_stores s 
             LEFT JOIN marketplace_oauth_connections c 
               ON c.store_id = s.id AND c.company_key = s.company_key
             WHERE s.company_key = ? AND s.archived_at IS NULL 
             ORDER BY s.store_name ASC'
        );
        $stmt->execute([$companyKey]);

        $stores = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $stores[] = MarketplaceStore::fromArray($row);
        }

        return $stores;
    }

    public function findById(string $storeId): ?MarketplaceStore
    {
        $this->scope->assertStoreAccess($storeId);
        $companyKey = $this->scope->companyKey();

        $stmt = $this->pdo->prepare(
            'SELECT s.*, c.id AS oauth_connection_id 
             FROM marketplace_stores s 
             LEFT JOIN marketplace_oauth_connections c 
               ON c.store_id = s.id AND c.company_key = s.company_key
             WHERE s.id = ? AND s.company_key = ?'
        );
        $stmt->execute([$storeId, $companyKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? MarketplaceStore::fromArray($row) : null;
    }
}

