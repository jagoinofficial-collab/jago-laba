<?php
declare(strict_types=1);

interface MarketplacePlatformAdapter
{
    public function platform(): string;

    public function buildAuthorizationUrl(string $state, string $redirectUri): string;

    public function exchangeAuthorizationCode(string $code, string $redirectUri): array;

    public function verifyWebhook(string $payload, array $headers): bool;

    public function fetchOrders(string $storeIdentifier, array $cursor = []): array;
}
