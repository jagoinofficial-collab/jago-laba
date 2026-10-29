<?php
declare(strict_types=1);

/**
 * Domain Model for Marketplace Store.
 * PRD Section 1 & 5: Store identity, multi-platform, tenant-bound.
 */
final class MarketplaceStore
{
    public function __construct(
        public readonly string $id,
        public readonly string $companyKey,
        public readonly string $platform,
        public readonly string $storeName,
        public readonly string $storeIdentifier,
        public readonly ?string $authorizationStatus = null,
        public readonly ?string $oauthConnectionId = null,
        public readonly ?string $archivedAt = null,
        public readonly ?string $createdAt = null
    ) {}

    public static function fromArray(array $row): self
    {
        return new self(
            id: (string)($row['id'] ?? ''),
            companyKey: (string)($row['company_key'] ?? ''),
            platform: (string)($row['platform'] ?? ''),
            storeName: (string)($row['store_name'] ?? ''),
            storeIdentifier: (string)($row['store_identifier'] ?? ''),
            authorizationStatus: isset($row['authorization_status']) ? (string)$row['authorization_status'] : null,
            oauthConnectionId: isset($row['oauth_connection_id']) ? (string)$row['oauth_connection_id'] : null,
            archivedAt: isset($row['archived_at']) ? (string)$row['archived_at'] : null,
            createdAt: isset($row['created_at']) ? (string)$row['created_at'] : null
        );
    }

    public function isVerified(): bool
    {
        return $this->authorizationStatus === 'verified' || !empty($this->oauthConnectionId);
    }

    public function isArchived(): bool
    {
        return !empty($this->archivedAt);
    }
}

