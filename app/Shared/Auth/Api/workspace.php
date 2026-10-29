<?php
require_once (dirname(__DIR__, 4) . '/app/Bootstrap/bootstrap.php');
header('Content-Type: application/json; charset=utf-8');
try {
    if (empty($_SESSION['user_id'])) { http_response_code(401); throw new RuntimeException('Sesi login berakhir. Silakan login kembali.'); }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); throw new RuntimeException('Metode tidak diizinkan.'); }
    Workspace::verifyCsrf();
    $workspace = (string)($_POST['workspace'] ?? '');
    Workspace::select($workspace);
    echo json_encode(['success' => true, 'url' => $workspace === Workspace::MARKETPLACE ? 'marketplace.php' : 'index.php']);
} catch (Throwable $e) {
    if (http_response_code() < 400) http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
