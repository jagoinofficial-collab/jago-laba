<?php
require_once (dirname(__DIR__, 2) . '/app/Bootstrap/bootstrap.php');
$pdo = Database::pdo();
echo "=== WAREHOUSES ===\n";
print_r($pdo->query("SELECT * FROM warehouses")->fetchAll(PDO::FETCH_ASSOC));
echo "=== SAMPLE INVENTORY STOCKS ===\n";
print_r($pdo->query("SELECT * FROM inventory_stocks LIMIT 5")->fetchAll(PDO::FETCH_ASSOC));

