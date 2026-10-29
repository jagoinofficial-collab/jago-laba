<?php
declare(strict_types=1);

final class MarketplaceHttpClient
{
    public function request(string $method, string $url, array $headers = [], ?array $jsonBody = null): array
    {
        if (!function_exists('curl_init')) {
            throw new MarketplaceIntegrationException('Ekstensi HTTP server belum tersedia.', 'HTTP_CLIENT_UNAVAILABLE');
        }
        $curl = curl_init($url);
        if ($curl === false) {
            throw new MarketplaceIntegrationException('Koneksi ke platform tidak dapat dimulai.', 'HTTP_CLIENT_UNAVAILABLE');
        }
        $requestHeaders = array_merge(['Accept: application/json'], $headers);
        $retryAfter = null;
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADERFUNCTION => static function($handle,string $line) use (&$retryAfter): int {
                if (stripos($line,'Retry-After:') === 0) {
                    $value=trim(substr($line,12));
                    $seconds=ctype_digit($value)?(int)$value:max(0,(strtotime($value)?:time())-time());
                    $retryAfter=min(86400,max(0,$seconds));
                }
                return strlen($line);
            },
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $requestHeaders,
        ];
        if ($jsonBody !== null) {
            $options[CURLOPT_POSTFIELDS] = json_encode($jsonBody, JSON_THROW_ON_ERROR);
            // A duplicate Content-Type may be combined by the gateway and rejected as HTTP 415.
            $options[CURLOPT_HTTPHEADER] = array_values(array_filter($requestHeaders, static fn(string $header): bool => stripos($header,'Content-Type:') !== 0));
            $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
        }
        curl_setopt_array($curl, $options);
        $body = curl_exec($curl);
        $httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);
        if ($body === false || $curlError !== '') {
            throw new MarketplaceIntegrationException('Platform tidak dapat dihubungi.', 'HTTP_TRANSPORT_ERROR');
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            $failure = json_decode((string)$body, true);
            $details = [];
            if (is_array($failure)) {
                foreach (['error' => 'Error', 'code' => 'Code', 'request_id' => 'Request ID'] as $field => $label) {
                    $value = $failure[$field] ?? null;
                    if (is_scalar($value) && preg_match('/^[a-zA-Z0-9_.:-]{1,100}$/', (string)$value)) $details[] = $label . ': ' . $value;
                }
            }
            // Only machine identifiers are exposed. Messages/bodies can contain credentials or signed URLs.
            $detail = $details ? ' ' . implode('; ', $details) . '.' : ' Respons tidak menyertakan kode error API yang dapat ditampilkan.';
            $hint = match ($httpCode) {
                401, 403 => 'Periksa pasangan ID/key, lingkungan Sandbox/Live, izin endpoint, dan pembatasan IP pada platform.',
                404 => 'Periksa alamat host dan endpoint untuk lingkungan yang dipilih.',
                429 => 'Batas permintaan platform tercapai; coba lagi setelah beberapa saat.',
                default => $httpCode >= 500 ? 'Layanan platform bermasalah; coba lagi setelah beberapa saat.' : 'Periksa konfigurasi permintaan dan akses aplikasi.',
            };
            if((string)($failure['code']??'')==='36009004'){
                $reason=strtolower((string)($failure['message']??''));
                if(str_contains($reason,'version'))$hint='Versi API pada endpoint tidak valid. Periksa path endpoint promo.';
                elseif(str_contains($reason,'timestamp'))$hint='Timestamp permintaan tidak valid. Periksa waktu server dan zona waktu.';
                elseif(str_contains($reason,'param'))$hint='Parameter permintaan tidak valid. Periksa format data promo.';
            }
            throw new MarketplaceIntegrationException("Platform menolak permintaan koneksi (HTTP $httpCode).$detail $hint", 'HTTP_' . $httpCode, $retryAfter);
        }
        try {
            $decoded = json_decode((string)$body, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new MarketplaceIntegrationException('Respons platform tidak lengkap.', 'INVALID_PLATFORM_RESPONSE');
        }
        if (!is_array($decoded)) {
            throw new MarketplaceIntegrationException('Respons platform tidak lengkap.', 'INVALID_PLATFORM_RESPONSE');
        }
        return $decoded;
    }
}
