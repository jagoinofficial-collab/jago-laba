<?php
declare(strict_types=1);
// Filesystem and class-loading regression: no database or marketplace calls.
$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
$manifest = json_decode(file_get_contents($root . '/docs/architecture/applied-structure.json'), true, 512, JSON_THROW_ON_ERROR);
$seen = [];
foreach ($manifest['moves'] as $move) {
    if (!is_file($root . '/' . $move['target'])) {
        throw new RuntimeException('Missing canonical file: ' . $move['target']);
    }
    if (isset($seen[$move['target']])) {
        throw new RuntimeException('Duplicate target: ' . $move['target']);
    }
    $seen[$move['target']] = true;
}
foreach (['Database','Workspace','MarketplaceScopeService','MarketplaceOrdersService',
    'MarketplaceCatalogService','MarketplaceFulfillmentService','MarketplaceTokenVault',
    'InventoryService','HppService','RpcService','BackupService'] as $class) {
    if (!class_exists($class)) {
        throw new RuntimeException('Class cannot autoload: ' . $class);
    }
}
$old = require $root . '/config/marketplace.php';
$new = require $root . '/config/integrations.php';
if ($old !== $new) throw new RuntimeException('Integration config alias changed values.');
foreach (['web','api','konveksi','marketplace'] as $name) {
    if (!is_array(require $root . '/routes/' . $name . '.php')) {
        throw new RuntimeException('Invalid route catalog.');
    }
}
$logo = file_get_contents($root . '/app/Modules/Marketplace/Views/marketplace_platform_logo.php');
if (!str_contains($logo, 'assets/marketplace/images/marketplaces/')) {
    throw new RuntimeException('Platform logos do not use the canonical asset directory.');
}
echo 'PASS: ' . count($seen) . " canonical files, module autoload, config aliases and preserved route catalogs.\n";
