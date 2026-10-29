<?php
declare(strict_types=1);

// Additive metadata only. Existing stores remain visible; no operational row
// is deleted, reclassified, or marked archived by this migration.
require_once (dirname(__DIR__, 3) . '/app/Bootstrap/bootstrap.php');
$pdo = Database::pdo();
$columns = [];
foreach ($pdo->query('SHOW COLUMNS FROM marketplace_stores')->fetchAll(PDO::FETCH_ASSOC) as $column) {
    $columns[(string)$column['Field']] = true;
}
$add = [];
if (!isset($columns['archived_at'])) $add[] = 'ADD COLUMN archived_at DATETIME NULL';
if (!isset($columns['archived_by'])) $add[] = 'ADD COLUMN archived_by VARCHAR(120) NULL';
if ($add) $pdo->exec('ALTER TABLE marketplace_stores ' . implode(', ', $add));
echo "Metadata arsip toko siap; tidak ada toko yang diarsipkan oleh migrasi.\n";
