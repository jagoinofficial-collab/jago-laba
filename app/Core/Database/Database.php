<?php
declare(strict_types=1);

final class Database
{
    private static ?PDO $pdo = null;
    private static ?array $config = null;

    public static function setConfig(array $config): void
    {
        self::$config = $config;
    }

    public static function config(): array
    {
        if (self::$config === null) {
            self::$config = require (dirname(__DIR__, 3) . '/app/config.php');
        }

        return self::$config;
    }

    public static function serverPdo(): PDO
    {
        $c = self::config()['db'];
        $dsn = sprintf('mysql:host=%s;port=%d;charset=%s', $c['host'], $c['port'], $c['charset']);

        return new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $c = self::config()['db'];
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $c['host'], $c['port'], $c['name'], $c['charset']);
        self::$pdo = new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        self::$pdo->exec("SET time_zone = '+07:00'");

        return self::$pdo;
    }

    public static function reset(): void
    {
        self::$pdo = null;
    }
}
