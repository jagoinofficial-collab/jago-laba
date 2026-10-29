<?php
declare(strict_types=1);
// Catalog of preserved routes. Existing entry points still enforce their middleware.
return [
    'konveksi' => require __DIR__ . '/konveksi.php',
    'marketplace' => require __DIR__ . '/marketplace.php',
    'workspace' => '/workspace.php',
];
