<?php
require_once (dirname(__DIR__, 2) . '/app/Bootstrap/bootstrap.php');
$pdo = Database::pdo();
$tables = [
    'marketplace_stock_allocations',
    'marketplace_return_claims',
    'marketplace_price_simulations',
    'marketplace_journal_entries',
    'marketplace_product_ads',
    'marketplace_orders'
];
foreach ($tables as $t) {
    echo "Converting $t to utf8mb4_unicode_ci...\n";
    $pdo->exec("ALTER TABLE $t CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
}
echo "All tables converted successfully!\n";

