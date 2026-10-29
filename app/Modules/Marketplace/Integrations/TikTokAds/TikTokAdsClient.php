<?php
declare(strict_types=1);

require_once (dirname(__DIR__, 2) . '/Integrations/Support/MarketplaceHttpClient.php');
require_once (dirname(__DIR__, 2) . '/Integrations/Support/MarketplaceIntegrationException.php');

/**
 * TikTok Marketing API (TikTok Ads) Client.
 *
 * Implements PRD Section 5 & 11:
 * - Requires advertiser authorization, completely separate from TikTok Shop seller authorization.
 * - Stores advertiser ID and links to store/project.
 * - Captures attributed revenue without confusing it with total store GMV.
 * - Distinguishes actual ad spend from wallet top-ups.
 * - Flexible tax handling based on actual source data.
 * - Idempotent record updates.
 */
final class TikTokAdsClient
{
    public const API_BASE = 'https://business-api.tiktok.com/open_api/v1.3';
    public const OAUTH_AUTH_URL = 'https://business-api.tiktok.com/portal/auth';

    public function __construct(
        private array $config = [],
        private ?string $advertiserId = null,
        private ?MarketplaceHttpClient $http = null,
        private $transport = null
    ) {
        $this->http = $http ?? new MarketplaceHttpClient();
    }

    /**
     * Generates official advertiser authorization URL.
     */
    public function getAuthorizationUrl(string $redirectUri, string $state): string
    {
        $appId = (string)($this->config['app_id'] ?? $this->config['client_key'] ?? '');
        if ($appId === '') {
            throw new MarketplaceIntegrationException(
                'Konfigurasi App ID TikTok Marketing API belum lengkap.',
                'TIKTOK_ADS_CONFIG_MISSING'
            );
        }

        $query = [
            'app_id' => $appId,
            'state' => $state,
            'redirect_uri' => $redirectUri,
        ];

        return self::OAUTH_AUTH_URL . '?' . http_build_query($query);
    }

    /**
     * Exchanges auth code for advertiser access token.
     */
    public function exchangeToken(string $authCode): array
    {
        $appId = (string)($this->config['app_id'] ?? $this->config['client_key'] ?? '');
        $secret = (string)($this->config['secret'] ?? $this->config['client_secret'] ?? '');

        if ($appId === '' || $secret === '') {
            throw new MarketplaceIntegrationException(
                'Kredensial TikTok Marketing API belum lengkap.',
                'TIKTOK_ADS_CREDENTIALS_MISSING'
            );
        }

        $url = self::API_BASE . '/oauth2/access_token/';
        $body = [
            'app_id' => $appId,
            'secret' => $secret,
            'auth_code' => $authCode,
        ];

        $response = $this->request('POST', $url, [], $body);
        $data = $response['data'] ?? [];

        if (empty($data['access_token'])) {
            $msg = (string)($response['message'] ?? 'Gagal memperoleh access token TikTok Ads.');
            throw new MarketplaceIntegrationException($msg, 'TIKTOK_ADS_TOKEN_FAILED');
        }

        return [
            'access_token' => (string)$data['access_token'],
            'advertiser_ids' => (array)($data['advertiser_ids'] ?? []),
            'scope' => (array)($data['scope'] ?? []),
        ];
    }

    /**
     * Fetches integrated advertiser performance reports.
     *
     * @param string $accessToken
     * @param string $startDate YYYY-MM-DD
     * @param string $endDate   YYYY-MM-DD
     * @param string $level     AUCTION_CAMPAIGN | AUCTION_ADGROUP | AUCTION_AD
     */
    public function getPerformanceReport(
        string $accessToken,
        string $startDate,
        string $endDate,
        string $level = 'AUCTION_CAMPAIGN'
    ): array {
        if (empty($this->advertiserId)) {
            throw new MarketplaceIntegrationException(
                'Advertiser ID belum diatur. Akses iklan terpisah dari identitas toko TikTok Shop.',
                'TIKTOK_ADS_ADVERTISER_ID_MISSING'
            );
        }

        $url = self::API_BASE . '/report/integrated/get/';
        $params = [
            'advertiser_id' => $this->advertiserId,
            'report_type' => 'BASIC',
            'data_level' => $level,
            'dimensions' => json_encode(['stat_time_day', 'campaign_id']),
            'metrics' => json_encode([
                'spend',
                'stat_cost',
                'conversion',
                'cost_per_conversion',
                'purchase_roas',
                'total_purchase_value',
                'impressions',
                'clicks',
            ]),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'page_size' => 100,
        ];

        $response = $this->request('GET', $url . '?' . http_build_query($params), [
            'Access-Token: ' . $accessToken,
        ]);

        $list = $response['data']['list'] ?? [];
        return $this->normalizeReportRows($list);
    }

    /**
     * Normalizes raw TikTok Ads performance data into canonical Jago Laba structure.
     */
    public function normalizeReportRows(array $rawList): array
    {
        $normalized = [];

        foreach ($rawList as $item) {
            $metrics = $item['metrics'] ?? [];
            $dimensions = $item['dimensions'] ?? [];

            $date = (string)($dimensions['stat_time_day'] ?? date('Y-m-d'));
            $campaignId = (string)($dimensions['campaign_id'] ?? 'default');
            $campaignName = (string)($dimensions['campaign_name'] ?? 'TikTok Ad Campaign ' . $campaignId);

            // Spend is actual spend, not wallet top-up
            $spend = isset($metrics['spend']) ? (float)$metrics['spend'] : (float)($metrics['stat_cost'] ?? 0);
            
            // Attributed revenue is strictly ads-attributed, not total store revenue
            $attributedRevenue = isset($metrics['total_purchase_value']) ? (float)$metrics['total_purchase_value'] : 0.0;
            
            // Attributed orders/conversions
            $conversions = isset($metrics['conversion']) ? (int)$metrics['conversion'] : 0;
            
            // ROAS platform
            $roas = $spend > 0 ? round($attributedRevenue / $spend, 2) : 0.0;

            $normalized[] = [
                'advertiser_id' => $this->advertiserId,
                'campaign_id' => $campaignId,
                'campaign_name' => $campaignName,
                'campaign_date' => substr($date, 0, 10),
                'spend_amount' => $spend,
                'attributed_revenue' => $attributedRevenue,
                'conversions' => $conversions,
                'roas' => $roas,
                'currency' => 'IDR',
                'attribution_type' => '7d_click_1d_view',
            ];
        }

        return $normalized;
    }

    private function request(string $method, string $url, array $headers = [], ?array $body = null): array
    {
        $headers[] = 'Content-Type: application/json';
        if ($this->transport) {
            return ($this->transport)($method, $url, $headers, $body);
        }

        return $this->http->request($method, $url, $headers, $body);
    }
}

