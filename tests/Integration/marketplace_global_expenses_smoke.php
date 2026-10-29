<?php
declare(strict_types=1);
function formatRp($amount): string { return 'Rp ' . number_format((float)$amount, 0, ',', '.'); }
$globalFilters = ['page' => 1];
$query = static fn(array $changes): string => http_build_query($changes);
$globalData = [
    'expense_summary' => ['total' => 1817975, 'count' => 2, 'advertising' => 1817975],
    'pagination' => ['per_page' => 50],
    'expenses' => [],
];
foreach ([['TikTok Shop', 'TikTok', 'TikTok Business', 821697], ['Shopee', 'Shopee', 'Shopee', 996278]] as $index => [$platform, $name, $vendor, $amount]) {
    $globalData['expenses'][] = ['id' => $index + 1, 'platform' => $platform, 'category' => 'Biaya Iklan/Advertising Marketplace', 'description' => $name . ' Ads - Pengeluaran Iklan Harian', 'store_name' => 'JAGOIN <script>test</script>', 'vendor_name' => $vendor, 'expense_date' => '2026-10-23', 'amount' => $amount, 'payment_method' => 'Transfer Bank', 'notes' => 'Termasuk PPN 11%'];
}
ob_start();
require (dirname(__DIR__, 2) . '/app/Modules/Marketplace/Views/marketplace_global_expenses.php');
$html = ob_get_clean();
foreach (['-Rp 821.697', '-Rp 996.278', '23 Okt 2026', 'TikTok Ads Manager', 'Shopee Ads Manager', 'Rp 1.817.975', 'Termasuk PPN 11%', 'aria-expanded="false"', '&lt;script&gt;test&lt;/script&gt;'] as $expected) {
    if (!str_contains($html, $expected)) throw new RuntimeException('Missing expected render: ' . $expected);
}
if (str_contains($html, '<script>test</script>')) throw new RuntimeException('Unsafe output');
$globalData['expenses'] = [];
ob_start();
require (dirname(__DIR__, 2) . '/app/Modules/Marketplace/Views/marketplace_global_expenses.php');
$empty = ob_get_clean();
if (!str_contains($empty, 'Belum ada pengeluaran')) throw new RuntimeException('Empty state missing');
echo "PASS: reference values, details, dates, totals, escaped output and empty state\n";
