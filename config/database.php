<?php
declare(strict_types=1);

return [
    'host' => getenv('JL_DB_HOST') ?: '127.0.0.1',
    'port' => (int)(getenv('JL_DB_PORT') ?: 3306),
    'name' => getenv('JL_DB_NAME') ?: 'jago_laba',
    'user' => getenv('JL_DB_USER') ?: 'root',
    'pass' => getenv('JL_DB_PASS') ?: '',
    'charset' => 'utf8mb4',
];
