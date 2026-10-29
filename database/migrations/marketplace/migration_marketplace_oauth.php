<?php
declare(strict_types=1);

require_once (dirname(__DIR__, 3) . '/app/Bootstrap/bootstrap.php');

$pdo = Database::pdo();
$sql = file_get_contents((dirname(__DIR__, 3) . '/database/migrations/marketplace/migration_marketplace_oauth.sql'));
if ($sql === false) {
    throw new RuntimeException('File migrasi OAuth tidak dapat dibaca.');
}

$statements = array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: []));
foreach ($statements as $statement) {
    if ($statement !== '') $pdo->exec($statement);
}

echo "Marketplace OAuth migration applied.\n";
