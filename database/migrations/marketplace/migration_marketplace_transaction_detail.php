<?php
declare(strict_types=1);
require_once (dirname(__DIR__, 3) . '/app/Bootstrap/bootstrap.php');
$pdo = Database::pdo();
$columns = $pdo->query('SHOW COLUMNS FROM marketplace_orders')->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('transaction_detail_json', $columns, true)) {
    $pdo->exec('ALTER TABLE marketplace_orders ADD COLUMN transaction_detail_json LONGTEXT NULL');
}
echo "Marketplace transaction detail schema ready.\n";
