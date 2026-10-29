<?php
declare(strict_types=1);

require_once (dirname(__DIR__, 5) . '/app/Bootstrap/bootstrap.php');
MarketplaceModule::boot();
require_once (dirname(__DIR__, 5) . '/app/Modules/Marketplace/Services/MarketplaceOAuthOrigin.php');

$oauth = new MarketplaceOAuthService();
$action = (string)($_GET['action'] ?? $_POST['action'] ?? 'callback');

$redirectToMarketplace = static function (string $status, string $message = '', ?string $pendingId = null, ?string $returnPath = null): never {
    $config = Database::config()['marketplace']['oauth'] ?? [];
    $base = rtrim((string)($config['base_url'] ?? ''), '/');
    $path = $returnPath ?: (string)($config['return_path'] ?? 'marketplace.php?scope=global&tab=integrasi');
    $parsed = parse_url($path);
    if (($parsed['scheme'] ?? '') !== '' || ($parsed['host'] ?? '') !== '' || ($parsed['path'] ?? 'marketplace.php') !== 'marketplace.php') {
        $path = 'marketplace.php?scope=global&tab=integrasi';
    }
    $separator = str_contains($path, '?') ? '&' : '?';
    $target = ($base !== '' ? $base . '/public/' : '../../') . ltrim($path, '/');
    $target .= $separator . 'oauth_status=' . rawurlencode($status);
    if ($pendingId !== null) $target .= '&pending=' . rawurlencode($pendingId);
    if ($message !== '') $target .= '&oauth_message=' . rawurlencode($message);
    header('Location: ' . $target, true, 303);
    exit;
};

$safeMessage = static function (string $code): string {
    return match ($code) {
        'AUTH_CANCELLED' => 'Otorisasi dibatalkan.',
        'INVALID_OAUTH_STATE', 'INVALID_OAUTH_PLATFORM' => 'Sesi otorisasi tidak valid atau sudah kedaluwarsa.',
        'OAUTH_NOT_CONFIGURED' => 'Konfigurasi aplikasi belum lengkap.',
        'OAUTH_ORIGIN_MISMATCH' => 'Mulai Hubungkan Toko dari domain publik yang sama dengan URL callback. Login melalui alamat ngrok aplikasi, bukan localhost.',
        'STORE_OWNED_BY_OTHER_COMPANY' => 'Toko sudah terhubung ke perusahaan lain.',
        'STORE_NOT_VERIFIED', 'REAUTHORIZE_REQUIRED' => 'Izin toko kedaluwarsa. Hubungkan ulang.',
        'TOKEN_EXCHANGE_FAILED', 'SHOPEE_TOKEN_INCOMPLETE', 'TIKTOK_TOKEN_INCOMPLETE' => 'Pertukaran token gagal.',
        'SHOPEE_API_REJECTED', 'TIKTOK_SHOPS_REJECTED', 'TIKTOK_TOKEN_REJECTED' => 'Izin API tidak cukup atau platform menolak permintaan.',
        'CONNECTION_NOT_VERIFIED', 'SHOP_IDENTITY_MISSING', 'SHOPEE_IDENTITY_INCOMPLETE', 'TIKTOK_IDENTITY_INCOMPLETE' => 'Koneksi toko belum dapat diverifikasi.',
        default => 'Koneksi toko belum dapat diproses. Periksa konfigurasi dan coba lagi.',
    };
};

$json = static function (array $payload, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
};

$isCallback = $action === 'callback' && (isset($_GET['state']) || isset($_GET['code']) || isset($_GET['error']));
if ($isCallback) {
    try {
        Workspace::require(Workspace::MARKETPLACE);
        $result = $oauth->handleCallback($_GET);
        if ($result['status'] === 'choose') {
            $redirectToMarketplace('choose', '', (string)$result['pending_id'], (string)$result['return_path']);
        }
        $redirectToMarketplace('success', '', null, (string)$result['return_path']);
    } catch (Throwable $e) {
        $code = $e instanceof MarketplaceIntegrationException ? $e->errorCode : 'CONNECTION_NOT_VERIFIED';
        $redirectToMarketplace($code === 'AUTH_CANCELLED' ? 'cancelled' : 'error', $safeMessage($code));
    }
}

try {
    Workspace::require(Workspace::MARKETPLACE);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') Workspace::verifyCsrf();

    switch ($action) {
        case 'status':
            $json(['success' => true, 'data' => $oauth->configurationStatus()]);
        case 'start':
            $platform = trim((string)($_POST['platform'] ?? ''));
            $origin=(string)($_SERVER['HTTP_ORIGIN']??'');
            if($origin!==''&&!MarketplaceOAuthOrigin::matches($oauth->callbackUrl($platform),$origin)) {
                throw new MarketplaceIntegrationException('Domain sesi berbeda dari callback.','OAUTH_ORIGIN_MISMATCH');
            }
            $json(['success' => true, 'data' => $oauth->startAuthorization($platform, (string)($_POST['return_path'] ?? ''))]);
        case 'pending':
            $pendingId = trim((string)($_GET['pending_id'] ?? ''));
            $json(['success' => true, 'data' => $oauth->pendingShops($pendingId)]);
        case 'select':
            $result = $oauth->selectPendingShop(trim((string)($_POST['pending_id'] ?? '')), trim((string)($_POST['external_store_id'] ?? '')));
            $json(['success' => true, 'data' => $result, 'message' => 'Koneksi toko berhasil diverifikasi. Sinkronisasi awal masuk antrean dan menunggu worker.']);
        case 'sync':
            $result = $oauth->enqueueSync(trim((string)($_POST['store_id'] ?? '')));
            $json(['success' => true, 'data' => $result]);
        case 'refresh':
            $result = $oauth->refreshConnection(trim((string)($_POST['store_id'] ?? '')));
            $json(['success' => true, 'data' => $result]);
        case 'disconnect':
            $result = $oauth->disconnect(trim((string)($_POST['store_id'] ?? '')));
            $json(['success' => true, 'data' => $result]);
        default:
            $json(['success' => false, 'message' => 'Aksi OAuth tidak dikenali.'], 400);
    }
} catch (Throwable $e) {
    $code = $e instanceof MarketplaceIntegrationException ? $e->errorCode : 'CONNECTION_NOT_VERIFIED';
    $status = in_array($code, ['AUTH_REQUIRED'], true) ? 401 : 400;
    $json(['success' => false, 'message' => $safeMessage($code)], $status);
}
