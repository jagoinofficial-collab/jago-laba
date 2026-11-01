<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Inertia\Inertia;

final class MarketplaceReportResponse
{
    public static function make(Request $request, string $title, array $data, string $rowKey, array $columns): mixed
    {
        $rows = $data[$rowKey] ?? [];
        if ($request->input('format') === 'csv') {
            return response()->streamDownload(function () use ($rows, $columns): void {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, array_values($columns), ',', '"', '');
                foreach ($rows as $row) {
                    $cells = [];
                    foreach ($columns as $key => $label) {
                        $value = $row[$key] ?? '';
                        $cells[] = is_string($value) && preg_match('/^[=+@\-\t\r\n]/', $value) ? "'".$value : $value;
                    }
                    fputcsv($out, $cells, ',', '"', '');
                }
                fclose($out);
            }, 'laporan-marketplace.csv', ['Content-Type' => 'text/csv; charset=utf-8', 'Cache-Control' => 'no-store, private']);
        }
        $props = ['title' => $title, 'data' => $data, 'rows' => $rows, 'columns' => $columns, 'filters' => $request->validated(), 'read_only' => true];
        if ($request->expectsJson() || $request->input('format') === 'json') {
            return response()->json($props)->header('Cache-Control', 'no-store, private');
        }

        return Inertia::render('Marketplace/ReadReport', $props);
    }
}
