<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

final class HealthController
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::select('SELECT 1');

            return response()->json(['status' => 'ok'])
                ->header('Cache-Control', 'no-store');
        } catch (Throwable) {
            return response()->json(['status' => 'error'], 503)
                ->header('Cache-Control', 'no-store');
        }
    }
}
