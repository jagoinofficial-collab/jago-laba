<?php

namespace App\Authorization;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\DatabaseManager;
use Throwable;

final class StoreContext
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly CompanyContext $company,
    ) {}

    public function canAccess(?Authenticatable $user, string $storeId): bool
    {
        $storeId = trim($storeId);
        $companyKey = $this->company->key($user);

        if ($storeId === '' || $companyKey === null) {
            return false;
        }

        try {
            return $this->database->connection()->table('marketplace_stores')
                ->where('id', $storeId)
                ->where('company_key', $companyKey)
                ->whereNull('archived_at')
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }
}
