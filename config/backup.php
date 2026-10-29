<?php
declare(strict_types=1);

return [
    'local_directory' => getenv('JL_BACKUP_DIR') ?: (is_dir('D:/') ? 'D:/JAGO_LABA_BACKUP/database' : (dirname(__DIR__, 1) . '/storage/backups')),
    'external_directory' => getenv('JL_BACKUP_MIRROR_DIR') ?: '',
    'mysqldump' => getenv('JL_MYSQLDUMP') ?: 'D:/xampp/mysql/bin/mysqldump.exe',
    'retention_days' => 30,
];
