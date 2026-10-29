<?php
declare(strict_types=1);
require_once __DIR__ . '/MarketplaceIntegrationPolicy.php';

/** Additive metadata only. Schema creation is restricted to explicit CLI migrations. */
final class MarketplaceIntegrationRuntime
{
    private ?bool $ready = null;
    public function __construct(private PDO $pdo) {}
    public function ready(): bool
    {
        return $this->ready ??= (bool)$this->pdo->query("SHOW TABLES LIKE 'marketplace_integration_bindings'")->fetchColumn();
    }
    public static function install(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS marketplace_integration_bindings (
            connection_id BIGINT UNSIGNED PRIMARY KEY, environment VARCHAR(20) NOT NULL,
            context_hash CHAR(64) NOT NULL, verified_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS marketplace_api_capabilities (
            connection_id BIGINT UNSIGNED NOT NULL, resource VARCHAR(30) NOT NULL,
            state VARCHAR(30) NOT NULL, last_verified_at DATETIME NULL, last_attempt_at DATETIME NOT NULL,
            last_error_code VARCHAR(100) NULL, PRIMARY KEY(connection_id,resource)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS marketplace_platform_cooldowns (
            scope_hash CHAR(64) PRIMARY KEY, next_allowed_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    public function binding(int $connection): ?array
    {
        if (!$this->ready()) return null;
        $q=$this->pdo->prepare('SELECT environment,context_hash,verified_at FROM marketplace_integration_bindings WHERE connection_id=?');$q->execute([$connection]);
        return $q->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    public function assertContext(array $connection, array $config): void
    {
        $binding=$this->binding((int)$connection['id']);
        if ($binding && !hash_equals($binding['context_hash'], MarketplaceIntegrationPolicy::fingerprint($connection['platform'],$config))) {
            throw new MarketplaceIntegrationException('Konfigurasi atau lingkungan aplikasi berubah. Otorisasi ulang toko sebelum melanjutkan.', 'INTEGRATION_CONTEXT_CHANGED');
        }
    }
    public function bind(array $connection, array $config, bool $reauthorized=false): void
    {
        if (!$this->ready()) return;
        if (!$reauthorized) $this->assertContext($connection,$config);
        $this->pdo->prepare('INSERT INTO marketplace_integration_bindings(connection_id,environment,context_hash,verified_at) VALUES (?,?,?,NOW()) ON DUPLICATE KEY UPDATE environment=VALUES(environment),context_hash=VALUES(context_hash),verified_at=NOW()')->execute([
            $connection['id'],MarketplaceIntegrationPolicy::environment($connection['platform'],$config),MarketplaceIntegrationPolicy::fingerprint($connection['platform'],$config)]);
        if ($reauthorized) $this->pdo->prepare('DELETE FROM marketplace_api_capabilities WHERE connection_id=?')->execute([$connection['id']]);
    }
    public function observe(int $connection, string $resource, ?string $code=null): void
    {
        if (!$this->ready()) return;
        $state=$code ? match(MarketplaceIntegrationPolicy::classify($code)) {'authorization'=>'reauthorize','permission'=>'permission','unsupported'=>'unsupported',default=>'temporary'} : 'available';
        $this->pdo->prepare('INSERT INTO marketplace_api_capabilities(connection_id,resource,state,last_verified_at,last_attempt_at,last_error_code) VALUES (?,?,?,IF(?=1,NOW(),NULL),NOW(),?) ON DUPLICATE KEY UPDATE state=VALUES(state),last_verified_at=IF(?=1,NOW(),last_verified_at),last_attempt_at=NOW(),last_error_code=VALUES(last_error_code)')->execute([$connection,$resource,$state,$code===null?1:0,$code,$code===null?1:0]);
    }
    public function evidence(int $connection): array
    {
        if (!$this->ready()) return [];
        $q=$this->pdo->prepare('SELECT resource,state,last_verified_at,last_attempt_at,last_error_code FROM marketplace_api_capabilities WHERE connection_id=?');$q->execute([$connection]);
        return array_column($q->fetchAll(PDO::FETCH_ASSOC),null,'resource');
    }
    public static function scope(string $platform,array $config): string
    {
        return hash('sha256',$platform.':'.MarketplaceIntegrationPolicy::environment($platform,$config).':'.($config['partner_id']??$config['app_key']??''));
    }
    public function remainingCooldown(string $scope): int
    {
        if (!$this->ready()) return 0;
        $q=$this->pdo->prepare('SELECT GREATEST(0,TIMESTAMPDIFF(SECOND,NOW(),next_allowed_at)) FROM marketplace_platform_cooldowns WHERE scope_hash=?');$q->execute([$scope]);return (int)$q->fetchColumn();
    }
    public function cooldown(string $scope,int $seconds): void
    {
        if (!$this->ready()) return;
        $this->pdo->prepare('INSERT INTO marketplace_platform_cooldowns(scope_hash,next_allowed_at) VALUES (?,DATE_ADD(NOW(),INTERVAL ? SECOND)) ON DUPLICATE KEY UPDATE next_allowed_at=GREATEST(next_allowed_at,VALUES(next_allowed_at))')->execute([$scope,$seconds]);
    }
}
