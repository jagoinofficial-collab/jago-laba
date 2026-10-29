<?php
declare(strict_types=1);

final class TikTokShopOAuthClient implements MarketplaceOAuthClient
{
    private MarketplaceHttpClient $http;
    private array $config;

    public function __construct(?MarketplaceHttpClient $http = null, ?array $config = null)
    {
        $marketplace = $config === null ? (new MarketplacePartnerSettings())->configFor('TikTok Shop') : [];
        $this->config = $config ?? $marketplace;
        $this->http = $http ?: new MarketplaceHttpClient();
    }

    public function platform(): string
    {
        return 'TikTok Shop';
    }

    public function authorizationContext(): array
    {
        require_once dirname(__DIR__, 2) . '/Services/MarketplaceIntegrationPolicy.php';
        return ['fingerprint'=>MarketplaceIntegrationPolicy::fingerprint($this->platform(),$this->config),'environment'=>MarketplaceIntegrationPolicy::environment($this->platform(),$this->config)];
    }

    public function configurationMissing(): array
    {
        $missing = [];
        foreach (['app_key', 'app_secret', 'service_id'] as $key) {
            if (trim((string)($this->config[$key] ?? '')) === '') {
                $missing[] = 'JL_TIKTOK_SHOP_' . strtoupper($key);
            }
        }
        if (trim((string)($this->config['auth_url'] ?? '')) === '') $missing[] = 'JL_TIKTOK_SHOP_AUTH_URL';
        if (trim((string)($this->config['api_base_url'] ?? '')) === '') $missing[] = 'JL_TIKTOK_SHOP_API_BASE_URL';
        return array_values(array_unique($missing));
    }

    public function authorizationUrl(string $state, string $redirectUri): string
    {
        $this->assertConfigured();
        $params = [
            'service_id' => (string)$this->config['service_id'],
            'state' => $state,
        ];
        return (string)$this->config['auth_url'] . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public function exchangeCode(string $code, array $callback): array
    {
        $this->assertConfigured();
        $query = [
            'app_key' => (string)$this->config['app_key'],
            'app_secret' => (string)$this->config['app_secret'],
            'auth_code' => $code,
            'grant_type' => 'authorized_code',
        ];
        $response = $this->http->request('GET', 'https://auth.tiktok-shops.com/api/v2/token/get?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986));
        $payload = $this->tokenPayload($response);
        if ((int)($response['code'] ?? 0) !== 0) {
            throw new MarketplaceIntegrationException('TikTok Shop menolak otorisasi atau izin API tidak cukup.', 'TIKTOK_TOKEN_REJECTED');
        }
        if ((int)($payload['user_type'] ?? -1) !== 0) {
            throw new MarketplaceIntegrationException('Token TikTok bukan token seller TikTok Shop.', 'TIKTOK_NOT_SELLER_TOKEN');
        }
        $accessToken = trim((string)($payload['access_token'] ?? ''));
        $refreshToken = trim((string)($payload['refresh_token'] ?? ''));
        if ($accessToken === '' || $refreshToken === '') {
            throw new MarketplaceIntegrationException('Token TikTok Shop tidak diterima lengkap.', 'TIKTOK_TOKEN_INCOMPLETE');
        }

        $shops = $this->getAuthorizedShops($accessToken);
        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_expires_at' => $this->expiryFromSeconds($payload['access_token_expire_in'] ?? null),
            'refresh_token_expires_at' => $this->expiryFromSeconds($payload['refresh_token_expire_in'] ?? null),
            'granted_scopes' => $this->normalizeScopes($payload['granted_scopes'] ?? []),
            'shops' => $shops,
        ];
    }

    public function refreshToken(string $refreshToken, ?string $externalStoreId = null, ?string $shopCipher = null): array
    {
        $this->assertConfigured();
        $query = [
            'app_key' => (string)$this->config['app_key'],
            'app_secret' => (string)$this->config['app_secret'],
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ];
        $response = $this->http->request('GET', 'https://auth.tiktok-shops.com/api/v2/token/refresh?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986));
        $payload = $this->tokenPayload($response);
        if ((int)($response['code'] ?? 0) !== 0) {
            throw new MarketplaceIntegrationException('Token TikTok Shop tidak dapat diperbarui.', 'TIKTOK_REFRESH_REJECTED');
        }
        if ((int)($payload['user_type'] ?? -1) !== 0) {
            throw new MarketplaceIntegrationException('Token TikTok bukan token seller TikTok Shop.', 'TIKTOK_NOT_SELLER_TOKEN');
        }
        $accessToken = trim((string)($payload['access_token'] ?? ''));
        if ($accessToken === '') {
            throw new MarketplaceIntegrationException('Token TikTok Shop baru tidak diterima.', 'TIKTOK_REFRESH_INCOMPLETE');
        }
        return [
            'access_token' => $accessToken,
            'refresh_token' => trim((string)($payload['refresh_token'] ?? '')) ?: $refreshToken,
            'token_expires_at' => $this->expiryFromSeconds($payload['access_token_expire_in'] ?? null),
            'refresh_token_expires_at' => $this->expiryFromSeconds($payload['refresh_token_expire_in'] ?? null),
            'granted_scopes' => $this->normalizeScopes($payload['granted_scopes'] ?? []),
            'shops' => $this->getAuthorizedShops($accessToken),
        ];
    }

    private function getAuthorizedShops(string $accessToken): array
    {
        $path = '/authorization/202309/shops';
        $query = ['app_key' => (string)$this->config['app_key'], 'timestamp' => time()];
        $query['sign'] = $this->sign($path, $query);
        $response = $this->http->request('GET', rtrim((string)$this->config['api_base_url'], '/') . $path . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986), [
            'Content-Type: application/json',
            'x-tts-access-token: ' . $accessToken,
        ]);
        if ((int)($response['code'] ?? 0) !== 0) {
            throw new MarketplaceIntegrationException('TikTok Shop menolak akses toko atau izin API tidak cukup.', 'TIKTOK_SHOPS_REJECTED');
        }
        $shops = $response['data']['shops'] ?? $response['shops'] ?? [];
        if (!is_array($shops) || !$shops) {
            throw new MarketplaceIntegrationException('Respons TikTok Shop tidak memuat toko terotorisasi.', 'TIKTOK_SHOPS_EMPTY');
        }
        $normalized = [];
        foreach ($shops as $shop) {
            if (!is_array($shop)) continue;
            $id = trim((string)($shop['id'] ?? $shop['shop_id'] ?? ''));
            $name = trim((string)($shop['name'] ?? $shop['shop_name'] ?? $shop['seller_name'] ?? ''));
            $cipher = trim((string)($shop['cipher'] ?? $shop['shop_cipher'] ?? ''));
            if ($id === '' || $name === '') continue;
            $normalized[] = ['id' => $id, 'name' => $name, 'cipher' => $cipher !== '' ? $cipher : null, 'region' => $shop['region'] ?? null];
        }
        if (!$normalized) {
            throw new MarketplaceIntegrationException('Identitas toko TikTok Shop tidak dapat diverifikasi.', 'TIKTOK_IDENTITY_INCOMPLETE');
        }
        return $normalized;
    }

    private function sign(string $path, array $query): string
    {
        unset($query['sign']);
        ksort($query);
        $input = $path;
        foreach ($query as $key => $value) {
            if ($value === null || $value === '') continue;
            $input .= $key . (string)$value;
        }
        $input = (string)$this->config['app_secret'] . $input . (string)$this->config['app_secret'];
        return hash_hmac('sha256', $input, (string)$this->config['app_secret']);
    }

    private function tokenPayload(array $response): array
    {
        $payload = is_array($response['data'] ?? null) ? $response['data'] : $response;
        return $payload;
    }

    private function normalizeScopes(mixed $scopes): array
    {
        if (is_string($scopes)) return array_values(array_filter(array_map('trim', explode(',', $scopes))));
        return is_array($scopes) ? array_values(array_map('strval', $scopes)) : [];
    }

    private function assertConfigured(): void
    {
        if ($this->configurationMissing()) {
            throw new MarketplaceIntegrationException('Konfigurasi aplikasi belum lengkap.', 'OAUTH_NOT_CONFIGURED');
        }
    }

    private function expiryFromSeconds(mixed $seconds): ?string
    {
        $seconds = (int)$seconds;
        return $seconds > 0 ? date('Y-m-d H:i:s', time() + $seconds) : null;
    }
}
