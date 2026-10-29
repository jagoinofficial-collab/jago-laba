<?php
require_once (dirname(__DIR__, 2) . '/app/Bootstrap/bootstrap.php');
$pdo = Database::pdo();
$tables = ['marketplace_orders', 'marketplace_product_ads', 'marketplace_payout_reconciliations'];
foreach ($tables as $t) {
    echo "=== TABLE: $t ===\n";
    $cols = $pdo->query("DESCRIBE $t")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        echo "  " . $c['Field'] . " (" . $c['Type'] . ") Null:" . $c['Null'] . "\n";
    }
}

