<?php

namespace App\Http\Controllers;

use App\Http\Requests\MarketplaceCatalogRequest;
use App\Marketplace\CatalogReadService;
use Inertia\Inertia;

final class MarketplaceCatalogController
{
    public function __invoke(MarketplaceCatalogRequest $request, CatalogReadService $catalog): mixed
    {
        $filters = $request->validated();
        try {
            $data = $catalog->listing((string) $request->attributes->get('company_key'), $filters['store_id'], $filters);
        } catch (\DomainException $exception) {
            abort(403, $exception->getMessage());
        }
        if (($filters['format'] ?? '') === 'csv') {
            return response()->streamDownload(function () use ($data): void {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Produk', 'SKU', 'Varian', 'Harga', 'Stok', 'HPP', 'Terjual'], ',', '"', '');
                foreach ($data['products'] as $product) {
                    foreach ($product['variants'] as $variant) {
                        $text = static fn ($value): string => preg_match('/^[=+@\-\t\r]/', (string) $value) ? "'".$value : (string) $value;
                        fputcsv($out, [$text($product['name']), $text($variant['sku']), $text($variant['variant_name']), $variant['price'] ?? '', $variant['stock_qty'] ?? '', $variant['hpp'] ?? '', $variant['sold']], ',', '"', '');
                    }
                }
                fclose($out);
            }, 'katalog-marketplace.csv', ['Content-Type' => 'text/csv; charset=utf-8', 'Cache-Control' => 'no-store, private']);
        }
        $props = ['catalog' => $data, 'filters' => $filters, 'read_only' => true];
        if ($request->expectsJson() || ($filters['format'] ?? '') === 'json') {
            return response()->json($props)->header('Cache-Control', 'no-store, private');
        }

        return Inertia::render('Marketplace/Catalog', $props);
    }
}
