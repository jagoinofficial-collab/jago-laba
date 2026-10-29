<?php
declare(strict_types=1);

interface MarketplaceOAuthClient
{
    public function platform(): string;

    public function configurationMissing(): array;

    public function authorizationUrl(string $state, string $redirectUri): string;

    /** @return array{access_token:string,refresh_token:?string,token_expires_at:?string,refresh_token_expires_at:?string,granted_scopes:array,shops:array} */
    public function exchangeCode(string $code, array $callback): array;

    /** @return array{access_token:string,refresh_token:?string,token_expires_at:?string,refresh_token_expires_at:?string,granted_scopes:array,shops:array} */
    public function refreshToken(string $refreshToken, ?string $externalStoreId = null, ?string $shopCipher = null): array;
}
