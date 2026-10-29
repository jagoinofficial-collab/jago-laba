<?php
declare(strict_types=1);

// Transitional composition point. New code should read from /config; the application
// still exposes the historical nested shape to preserve existing service contracts.
return [
    'app' => require (dirname(__DIR__, 2) . '/config/app.php'),
    'db' => require (dirname(__DIR__, 2) . '/config/database.php'),
    'backup' => require (dirname(__DIR__, 2) . '/config/backup.php'),
    'storage' => require dirname(__DIR__, 2) . '/config/storage.php',
    'queue' => require dirname(__DIR__, 2) . '/config/queue.php',
    'security' => require (dirname(__DIR__, 2) . '/config/security.php'),
    'marketplace' => require (dirname(__DIR__, 2) . '/config/integrations.php'),
];
