<?php
declare(strict_types=1);

final class SharedRuntime
{
    private static bool $booted = false;

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }

        $appRoot = (dirname(__DIR__, 2) . '/app');
        foreach ([
            '/Shared/Support/SchemaManager.php',
            '/Shared/Support/SnapshotImporter.php',
            '/Shared/Support/CollectionStore.php',
            '/Shared/Support/AuditService.php',
            '/Shared/Authorization/Workspace.php',
            '/Shared/Authorization/RolePolicy.php',
        ] as $file) {
            require_once $appRoot . $file;
        }

        self::$booted = true;
    }
}
