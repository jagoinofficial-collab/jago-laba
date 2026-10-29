<?php
require_once (dirname(__DIR__, 2) . '/app/Bootstrap/bootstrap.php');
$pdo = Database::pdo();
$tables = ['inventory_stocks', 'product_variants', 'marketplace_stock_allocations'];
foreach ($tables as $t) {
    $res = $pdo->query("SHOW FULL COLUMNS FROM $t")->fetchAll(PDO::FETCH_ASSOC);
    echo "=== TABLE: $t ===\n";
    foreach ($res as $r) {
        if ($r['Collation']) {
            echo "  {$r['Field']}: {$r['Collation']}\n";
        }
    }
}

