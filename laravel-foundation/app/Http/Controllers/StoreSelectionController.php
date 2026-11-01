<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StoreSelectionController
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'store_id' => $request->attributes->get('store_id'),
            'url' => route('pilot.marketplace', ['store' => $request->attributes->get('store_id')], false),
        ])->header('Cache-Control', 'no-store, private');
    }
}
