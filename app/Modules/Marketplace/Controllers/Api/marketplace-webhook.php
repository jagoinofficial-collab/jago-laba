<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
// No callback is registered. Acknowledging a push before platform-specific
// verification and a durable inbox would silently lose orders and refunds.
http_response_code(503);
echo json_encode(['success' => false, 'message' => 'Webhook belum diaktifkan. Otorisasi dan verifikasi platform diperlukan.']);
