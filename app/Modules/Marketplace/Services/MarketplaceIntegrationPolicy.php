<?php
declare(strict_types=1);

/** Application policy, never a claim about a platform's official quota. */
final class MarketplaceIntegrationPolicy
{
    public static function resources(string $platform): array
    {
        $tiktok = $platform === 'TikTok Shop';
        return [
            'products' => ['label'=>'Produk & varian','job_types'=>['products','variants'],'supported'=>true],
            'prices' => ['label'=>'Harga (baca)','job_types'=>['products','variants'],'supported'=>true],
            'stock' => ['label'=>'Stok tersedia','job_types'=>['products','variants'],'supported'=>true],
            'orders' => ['label'=>'Pesanan & status','job_types'=>['orders'],'supported'=>true],
            'shipping' => ['label'=>'PDF resi & pengemasan','job_types'=>[],'supported'=>$tiktok],
            'returns' => ['label'=>'Retur / pembatalan','job_types'=>['returns'],'supported'=>$tiktok],
            'finance' => ['label'=>'Rincian keuangan','job_types'=>['transactions'],'supported'=>$tiktok],
            'withdrawals' => ['label'=>'Penarikan dana API','job_types'=>[],'supported'=>false],
            'ads' => ['label'=>'Data iklan API','job_types'=>[],'supported'=>false],
        ];
    }

    public static function environment(string $platform, array $config): string
    {
        if ($platform === 'Shopee') return ($config['region'] ?? '') === 'sandbox' ? 'sandbox' : 'live';
        return in_array($config['environment'] ?? '', ['live','development'], true) ? $config['environment'] : 'unspecified';
    }

    public static function fingerprint(string $platform, array $config): string
    {
        $fields = ['region','environment','api_base_url','auth_url','partner_id','partner_key','app_key','app_secret','service_id'];
        $values = ['platform'=>$platform];
        foreach ($fields as $key) $values[$key] = (string)($config[$key] ?? '');
        return hash('sha256', json_encode($values, JSON_THROW_ON_ERROR));
    }

    public static function classify(string $code): string
    {
        if (preg_match('/^SHOPEE_(?:ERROR_AUTH|ERROR_TOKEN|ERROR_ACCESS_TOKEN|ERROR_REFRESH_TOKEN)(?:[._]|$)/', $code)) return 'authorization';
        if (preg_match('/^SHOPEE_(?:ERROR_SERVER|ERROR_INTERNAL)(?:[._]|$)/', $code)) return 'temporary';
        if (in_array($code, ['HTTP_401','TIKTOK_API_105001','TIKTOK_API_105002','REAUTHORIZE_REQUIRED','INTEGRATION_CONTEXT_CHANGED'], true)) return 'authorization';
        if (in_array($code, ['HTTP_403','TIKTOK_API_105005'], true) || preg_match('/(?:PERMISSION|ACCESS_DENIED|SCOPE|CATALOG_REJECTED)/i', $code)) return 'permission';
        if ($code === 'HTTP_429' || preg_match('/(?:RATE_LIMIT|TOO_MANY)/i', $code)) return 'rate_limit';
        if (preg_match('/^HTTP_5\d\d$/', $code) || in_array($code, ['HTTP_TRANSPORT_ERROR','DOCUMENT_NOT_READY'], true)) return 'temporary';
        if (str_contains($code,'UNSUPPORTED') || str_contains($code,'NOT_SUPPORTED')) return 'unsupported';
        return 'request';
    }

    public static function retry(string $code, int $attempt, ?int $retryAfter = null): ?int
    {
        if ($attempt >= 3 || !in_array(self::classify($code), ['rate_limit','temporary'], true)) return null;
        return max($retryAfter ?? 0, min(300, 5 * (2 ** $attempt)) + random_int(0, 3));
    }
}
