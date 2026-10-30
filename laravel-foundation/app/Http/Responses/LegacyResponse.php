<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

final class LegacyResponse
{
    public static function unavailable(string $contract): JsonResponse
    {
        $requestId = bin2hex(random_bytes(8));
        $body = $contract === 'rpc'
            ? ['ok' => false, 'error' => ['message' => 'Endpoint belum dialihkan ke Laravel.', 'request_id' => $requestId]]
            : ['success' => false, 'message' => 'Endpoint belum dialihkan ke Laravel.', 'request_id' => $requestId];

        return response()->json($body, 503)
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Request-Id', $requestId);
    }
}
