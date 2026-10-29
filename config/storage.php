<?php
declare(strict_types=1);
$root = dirname(__DIR__);
return [
    'root' => $root . '/storage',
    'private' => ['konveksi' => $root . '/storage/private/konveksi', 'marketplace' => $root . '/storage/private/marketplace'],
    'cache' => $root . '/storage/cache',
    'logs' => $root . '/storage/logs',
    'sessions' => $root . '/storage/sessions',
    'temporary' => $root . '/storage/temporary',
    // Existing documents retain their location until a separate data migration.
    'legacy_public_documents' => $root . '/public/storage/documents',
];
