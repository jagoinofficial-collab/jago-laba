<?php
declare(strict_types=1);

require_once (dirname(__DIR__, 3) . '/Shared/Authorization/RolePolicy.php');
require_once (dirname(__DIR__, 3) . '/Shared/Authorization/Workspace.php');
require_once (dirname(__DIR__, 3) . '/Shared/Companies/MarketplaceScopeService.php');

/**
 * Validates incoming request authorization, company context, and store boundaries.
 * PRD Section 2: "Setiap permintaan server wajib memeriksa perusahaan, toko, dan izin tindakan."
 */
final class MarketplaceActionRequest
{
    public static function authorize(string $action, ?string $storeId = null, ?PDO $pdo = null): void
    {
        // 1. Check workspace
        Workspace::require(Workspace::MARKETPLACE);

        // 2. Enforce Role-Based Access Control
        RolePolicy::require($action);

        // 3. Verify tenant store boundary if storeId provided
        if ($storeId !== null && $storeId !== '' && $pdo !== null) {
            $scope = new MarketplaceScopeService($pdo);
            $scope->assertStoreAccess($storeId);
        }
    }

    public static function verifyCsrf(): void
    {
        Workspace::verifyCsrf();
    }
}

