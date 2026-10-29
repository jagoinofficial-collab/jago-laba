<?php
declare(strict_types=1);

final class ShopeeOAuthClient implements MarketplaceOAuthClient
{
    private MarketplaceHttpClient $http;
    private array $config;
    private $transport;

    public function __construct(?MarketplaceHttpClient $http = null, ?array $config = null, ?callable $transport = null)
    {
        $marketplace = $config === null ? (new MarketplacePartnerSettings())->configFor('Shopee') : [];
        $this->config = $config ?? $marketplace;
        $this->http = $http ?: new MarketplaceHttpClient();
        $this->transport = $transport;
    }

    public function platform(): string
    {
        return 'Shopee';
    }

    public function authorizationContext(): array
    {
        require_once dirname(__DIR__, 2) . '/Services/MarketplaceIntegrationPolicy.php';
        return ['fingerprint'=>MarketplaceIntegrationPolicy::fingerprint($this->platform(),$this->config),'environment'=>MarketplaceIntegrationPolicy::environment($this->platform(),$this->config)];
    }

    public function configurationMissing(): array
    {
        $missing = [];
        foreach (['partner_id', 'partner_key'] as $key) {
            if (trim((string)($this->config[$key] ?? '')) === '') {
                $missing[] = 'JL_SHOPEE_' . strtoupper($key);
            }
        }
        if (trim((string)($this->config['auth_url'] ?? '')) === '') $missing[] = 'JL_SHOPEE_AUTH_URL';
        if (trim((string)($this->config['api_base_url'] ?? '')) === '') $missing[] = 'JL_SHOPEE_API_BASE_URL';
        $mode=(string)($this->config['region']??'live');
        $hosts=$mode==='sandbox'?['open.sandbox.test-stable.shopee.com','openplatform.sandbox.test-stable.shopee.sg']:['open.shopee.com','partner.shopeemobile.com'];
        if(!in_array($mode,['live','sandbox'],true)||parse_url($this->config['auth_url']??'',PHP_URL_HOST)!==$hosts[0]||parse_url($this->config['api_base_url']??'',PHP_URL_HOST)!==$hosts[1]||parse_url($this->config['auth_url']??'',PHP_URL_SCHEME)!=='https'||parse_url($this->config['api_base_url']??'',PHP_URL_SCHEME)!=='https')$missing[]='JL_SHOPEE_ENVIRONMENT_URLS';
        return array_values(array_unique($missing));
    }

    public function authorizationUrl(string $state, string $redirectUri): string
    {
        $this->assertConfigured();
        $params = [
            'partner_id' => (string)$this->config['partner_id'],
            'response_type' => 'code',
            'auth_type' => 'seller',
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ];
        return (string)$this->config['auth_url'] . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public function exchangeCode(string $code, array $callback): array
    {
        $this->assertConfigured();
        $path = '/api/v2/auth/token/get';
        $timestamp = time();
        $shopId = trim((string)($callback['shop_id'] ?? ''));
        $body = [
            'code' => $code,
            'shop_id' => $this->numericId($shopId),
            'partner_id' => $this->numericId((string)$this->config['partner_id']),
        ];
        if ($shopId === '') {
            throw new MarketplaceIntegrationException('Respons otorisasi Shopee tidak lengkap.', 'SHOPEE_MISSING_SHOP');
        }

        $response = $this->request('POST', $this->publicUrl($path, $timestamp), ['Content-Type: application/json'], $body);
        $this->assertShopeeResponse($response);
        $payload = is_array($response['response'] ?? null) ? $response['response'] : $response;
        $accessToken = trim((string)($payload['access_token'] ?? ''));
        $refreshToken = trim((string)($payload['refresh_token'] ?? ''));
        if ($accessToken === '' || $refreshToken === '') {
            throw new MarketplaceIntegrationException('Token Shopee tidak diterima lengkap.', 'SHOPEE_TOKEN_INCOMPLETE');
        }

        $shops = $this->verifyShop($accessToken, $shopId);
        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_expires_at' => $this->expiryFromSeconds($payload['expire_in'] ?? null),
            'refresh_token_expires_at' => $this->expiryFromSeconds($payload['refresh_token_expire_in'] ?? null),
            'granted_scopes' => is_array($payload['granted_scopes'] ?? null) ? $payload['granted_scopes'] : [],
            'shops' => $shops,
        ];
    }

    public function refreshToken(string $refreshToken, ?string $externalStoreId = null, ?string $shopCipher = null): array
    {
        $this->assertConfigured();
        $shopId = trim((string)$externalStoreId);
        if ($shopId === '') {
            throw new MarketplaceIntegrationException('Identitas toko Shopee tidak tersedia.', 'SHOPEE_MISSING_SHOP');
        }
        $path = '/api/v2/auth/access_token/get';
        $timestamp = time();
        $body = [
            'refresh_token' => $refreshToken,
            'shop_id' => $this->numericId($shopId),
            'partner_id' => $this->numericId((string)$this->config['partner_id']),
        ];
        $response = $this->request('POST', $this->publicUrl($path, $timestamp), ['Content-Type: application/json'], $body);
        $this->assertShopeeResponse($response);
        $payload = is_array($response['response'] ?? null) ? $response['response'] : $response;
        $accessToken = trim((string)($payload['access_token'] ?? ''));
        if ($accessToken === '') {
            throw new MarketplaceIntegrationException('Token Shopee baru tidak diterima.', 'SHOPEE_REFRESH_INCOMPLETE');
        }
        return [
            'access_token' => $accessToken,
            'refresh_token' => trim((string)($payload['refresh_token'] ?? '')) ?: $refreshToken,
            'token_expires_at' => $this->expiryFromSeconds($payload['expire_in'] ?? null),
            'refresh_token_expires_at' => $this->expiryFromSeconds($payload['refresh_token_expire_in'] ?? null),
            'granted_scopes' => is_array($payload['granted_scopes'] ?? null) ? $payload['granted_scopes'] : [],
            'shops' => $this->verifyShop($accessToken, $shopId),
        ];
    }

    private function verifyShop(string $accessToken, string $shopId): array
    {
        $path = '/api/v2/shop/get_shop_info';
        $timestamp = time();
        $query = [
            'partner_id' => (string)$this->config['partner_id'],
            'timestamp' => $timestamp,
            'access_token' => $accessToken,
            'shop_id' => $shopId,
        ];
        $query['sign'] = $this->sign($path, $timestamp, $accessToken, $shopId);
        $response = $this->request('GET', rtrim((string)$this->config['api_base_url'], '/') . $path . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986));
        $this->assertShopeeResponse($response);
        $payload = is_array($response['response'] ?? null) ? $response['response'] : $response;
        $returnedId = trim((string)($payload['shop_id'] ?? $shopId));
        $name = trim((string)($payload['shop_name'] ?? $payload['name'] ?? ''));
        if ($returnedId === '' || $returnedId !== $shopId || $name === '') {
            throw new MarketplaceIntegrationException('Identitas toko Shopee tidak dapat diverifikasi.', 'SHOPEE_IDENTITY_INCOMPLETE');
        }
        return [['id' => $returnedId, 'name' => $name, 'cipher' => null, 'region' => null]];
    }

    private function numericId(string $value): int
    {
        if (!ctype_digit($value) || filter_var($value, FILTER_VALIDATE_INT) === false || (int)$value < 1) throw new MarketplaceIntegrationException('Identitas Partner atau toko Shopee tidak valid.', 'SHOPEE_ID_INVALID');
        return (int)$value;
    }

    private function publicUrl(string $path, int $timestamp): string
    {
        return rtrim((string)$this->config['api_base_url'], '/') . $path . '?' . http_build_query(['partner_id'=>(string)$this->config['partner_id'],'timestamp'=>$timestamp,'sign'=>$this->sign($path,$timestamp)], '', '&', PHP_QUERY_RFC3986);
    }

    private function request(string $method, string $url, array $headers=[], ?array $body=null): array
    {
        return $this->transport ? ($this->transport)($method,$url,$headers,$body) : $this->http->request($method,$url,$headers,$body);
    }

    private function sign(string $path, int $timestamp, ?string $accessToken = null, ?string $shopId = null): string
    {
        $base = (string)$this->config['partner_id'] . $path . $timestamp;
        if ($accessToken !== null) $base .= $accessToken;
        if ($shopId !== null) $base .= $shopId;
        return hash_hmac('sha256', $base, (string)$this->config['partner_key']);
    }

    private function assertConfigured(): void
    {
        if ($this->configurationMissing()) {
            throw new MarketplaceIntegrationException('Konfigurasi aplikasi belum lengkap.', 'OAUTH_NOT_CONFIGURED');
        }
    }

    private function assertShopeeResponse(array $response): void
    {
        $error = trim((string)($response['error'] ?? ''));
        if ($error !== '') {
            throw new MarketplaceIntegrationException('Shopee menolak otorisasi atau izin API tidak cukup.', 'SHOPEE_API_REJECTED');
        }
    }

    private function expiryFromSeconds(mixed $seconds): ?string
    {
        $seconds = (int)$seconds;
        return $seconds > 0 ? date('Y-m-d H:i:s', time() + $seconds) : null;
    }
}
