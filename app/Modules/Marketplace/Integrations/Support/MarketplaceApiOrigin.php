<?php
declare(strict_types=1);

/** Never send signed requests or shop tokens to a configurable arbitrary host. */
final class MarketplaceApiOrigin
{
    public static function validate(string $platform, string $base): string
    {
        $base = rtrim($base, '/');
        $url = parse_url($base);
        $hosts = $platform === 'Shopee'
            ? ['partner.shopeemobile.com', 'openplatform.sandbox.test-stable.shopee.sg']
            : ['open-api.tiktokglobalshop.com'];
        if (!$url || ($url['scheme'] ?? '') !== 'https' || !in_array($url['host'] ?? '', $hosts, true)
            || isset($url['user']) || isset($url['pass'])
            || isset($url['port']) || isset($url['query']) || isset($url['fragment']) || !empty($url['path'])) {
            throw new MarketplaceIntegrationException('Host API marketplace tidak valid.', 'API_HOST_INVALID');
        }
        return $base;
    }

    public static function shopeeError(string $error): never
    {
        // Preserve a safe machine identifier; never include provider bodies or signed URLs.
        $error = strtoupper($error);
        $error = preg_match('/^[A-Z0-9_.]{1,80}$/D', $error) ? $error : 'REJECTED';
        throw new MarketplaceIntegrationException('Permintaan Shopee ditolak. Periksa kode API dan izin sumber terkait.', 'SHOPEE_' . $error);
    }
}
