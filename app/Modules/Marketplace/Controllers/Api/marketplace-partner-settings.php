<?php
declare(strict_types=1);

require_once (dirname(__DIR__, 5) . '/app/Bootstrap/bootstrap.php');
MarketplaceModule::boot();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

$reply = static function (array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
};

try {
    Workspace::require(Workspace::MARKETPLACE);
    $role = strtoupper((string)($_SESSION['role'] ?? ''));
    if (!str_contains($role, 'OWNER') && !str_contains($role, 'ADMIN')) {
        $reply(['success' => false, 'message' => 'Hanya administrator yang dapat mengubah pengaturan API.'], 403);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $reply(['success' => false, 'message' => 'Metode permintaan tidak didukung.'], 405);
    }
    Workspace::verifyCsrf();
    $platform = trim((string)($_POST['platform'] ?? ''));
    $settings = new MarketplacePartnerSettings();
    $result = $settings->saveAndTest($platform, $_POST);
    $reply(['success' => true, 'data' => $result]);
} catch (Throwable $error) {
    $message = $error instanceof MarketplaceIntegrationException ? $error->getMessage() : 'Pengaturan API belum dapat disimpan. Periksa database dan konfigurasi server.';
    $reply(['success' => false, 'message' => $message], 400);
}
