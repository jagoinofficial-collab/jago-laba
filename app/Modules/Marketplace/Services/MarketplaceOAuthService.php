<?php
declare(strict_types=1);
require_once (dirname(__DIR__, 4) . '/app/Modules/Marketplace/Services/MarketplaceSyncWindow.php');

require_once __DIR__ . '/MarketplaceIntegrationRuntime.php';
require_once __DIR__ . '/MarketplaceIncrementalSync.php';
final class MarketplaceOAuthService
{
    private PDO $pdo;
    private MarketplaceScopeService $scope;
    private array $config;
    /** @var array<string,MarketplaceOAuthClient> */
    private array $clients;
    private array $platformConfigurations=[];

    public function __construct(?PDO $pdo = null, ?array $clients = null, ?array $config = null)
    {
        $this->pdo = $pdo ?: Database::pdo();
        $this->scope = new MarketplaceScopeService($this->pdo);
        $appConfig = Database::config();
        $this->config = $config ?? ($appConfig['marketplace']['oauth'] ?? []);
        $this->clients = $clients ?? [
            'Shopee' => new ShopeeOAuthClient(),
            'TikTok Shop' => new TikTokShopOAuthClient(),
        ];
    }

    public function configurationStatus(): array
    {
        $platforms = [];
        foreach ($this->clients as $platform => $client) {
            $missing = $client->configurationMissing();
            if (trim((string)($this->config['base_url'] ?? '')) === '') $missing[] = 'JL_APP_BASE_URL';
            if (trim((string)($this->config['callback_url'] ?? '')) === '') $missing[] = 'JL_APP_BASE_URL';
            if (!MarketplaceTokenVault::isConfigured()) $missing[] = 'JL_MARKETPLACE_TOKEN_ENCRYPTION_KEY';
            $missing = array_values(array_unique($missing));
            $platforms[$platform] = [
                'platform' => $platform,
                'environment' => method_exists($client,'authorizationContext') ? $client->authorizationContext()['environment'] : 'unspecified',
                'ready' => $missing === [],
                'missing' => $missing,
                'label' => $missing === [] ? 'Siap memulai otorisasi' : 'Konfigurasi aplikasi belum lengkap',
                'authorization' => $platform === 'TikTok Shop'
                    ? 'Akses seller TikTok Shop untuk toko, produk, pesanan, dan status yang diizinkan.'
                    : 'Akses seller Shopee untuk identitas toko dan data yang diizinkan Partner Center.',
            ];
        }
        return [
            'platforms' => $platforms,
            'is_admin' => $this->isAdministrator(),
            'callback_urls' => [
                'Shopee' => $this->callbackUrl('Shopee'),
                'TikTok Shop' => $this->callbackUrl('TikTok Shop'),
            ],
        ];
    }

    public function startAuthorization(string $platform, ?string $returnPath = null): array
    {
        $client = $this->clientFor($platform);
        $status = $this->configurationStatus()['platforms'][$platform];
        if (!$status['ready']) {
            throw new MarketplaceIntegrationException('Konfigurasi aplikasi belum lengkap.', 'OAUTH_NOT_CONFIGURED');
        }
        $context = $this->context();
        $returnPath = $this->safeReturnPath($returnPath);
        $nonce = bin2hex(random_bytes(16));
        $state = $nonce . $this->stateTag($nonce,$platform,$context);
        $stmt = $this->pdo->prepare('INSERT INTO marketplace_oauth_states
            (state_hash, company_key, user_key, platform, return_path, expires_at)
            VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))');
        $stmt->execute([
            hash('sha256', $state),
            $context['company_key'],
            $context['user_key'],
            $platform,
            $returnPath,
            max(60, (int)($this->config['state_ttl_seconds'] ?? 600)),
        ]);

        return [
            'authorization_url' => $client->authorizationUrl($state, $this->callbackUrl($platform)),
            'expires_in' => max(60, (int)($this->config['state_ttl_seconds'] ?? 600)),
        ];
    }

    /** @return array{status:string,return_path:string,pending_id?:string,store_id?:string} */
    public function handleCallback(array $callback): array
    {
        $stateValue = trim((string)($callback['state'] ?? ''));
        if (!preg_match('/^[a-f0-9]{64}$/', $stateValue)) {
            throw new MarketplaceIntegrationException('Sesi otorisasi tidak valid atau sudah kedaluwarsa.', 'INVALID_OAUTH_STATE');
        }
        $context = $this->context();
        $state = $this->loadState($stateValue, $context);
        $context['authorization_started']=(string)$state['created_at'];
        $platform = (string)$state['platform'];
        if (!empty($callback['platform']) && (string)$callback['platform'] !== $platform) {
            $this->consumeState($state, $context);
            throw new MarketplaceIntegrationException('Sesi otorisasi tidak valid.', 'INVALID_OAUTH_PLATFORM');
        }
        $this->consumeState($state, $context);
        if (!hash_equals($this->stateTag(substr($stateValue,0,32),$platform,$context),substr($stateValue,32))) throw new MarketplaceIntegrationException('Konfigurasi atau lingkungan berubah. Mulai otorisasi toko kembali.','INTEGRATION_CONTEXT_CHANGED');

        if (trim((string)($callback['error'] ?? '')) !== '' || trim((string)($callback['code'] ?? '')) === '') {
            throw new MarketplaceIntegrationException('Otorisasi dibatalkan.', 'AUTH_CANCELLED');
        }

        $client = $this->clientFor($platform);
        try {
            $tokens = $client->exchangeCode((string)$callback['code'], $callback);
        } catch (MarketplaceIntegrationException $e) {
            throw $e;
        } catch (Throwable) {
            throw new MarketplaceIntegrationException('Pertukaran token gagal.', 'TOKEN_EXCHANGE_FAILED');
        }

        $shops = $this->normalizeShops($tokens['shops'] ?? []);
        if (!$shops) {
            throw new MarketplaceIntegrationException('Respons platform tidak memuat identitas toko.', 'SHOP_IDENTITY_MISSING');
        }
        if (count($shops) > 1) {
            $pendingId = bin2hex(random_bytes(24));
            $this->storePending($pendingId, $context, $platform, $tokens, $shops, (string)$state['return_path']);
            return ['status' => 'choose', 'return_path' => (string)$state['return_path'], 'pending_id' => $pendingId];
        }

        $this->platformConfiguration($platform);
        $this->pdo->beginTransaction();
        try {
            $storeId = $this->persistVerifiedConnection($context, $platform, $tokens, $shops[0]);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $this->safeConnectionException($e);
        }
        return ['status' => 'success', 'return_path' => (string)$state['return_path'], 'store_id' => $storeId];
    }

    public function pendingShops(string $pendingId): array
    {
        $context = $this->context();
        $row = $this->loadPending($pendingId, $context, false);
        $shops = $this->pendingPayload($row);
        return [
            'platform' => (string)$row['platform'],
            'shops' => is_array($shops) ? $shops : [],
            'expires_at' => (string)$row['expires_at'],
        ];
    }

    public function selectPendingShop(string $pendingId, string $externalStoreId): array
    {
        $context = $this->context();
        $pending=$this->loadPending($pendingId,$context,false);$this->platformConfiguration((string)$pending['platform']);
        $this->pdo->beginTransaction();
        try {
            $row = $this->loadPending($pendingId, $context, true);
            $context['authorization_started']=(string)(json_decode($row['shops_json'],true)['authorization_started']??$row['created_at']);
            $shops = $this->pendingPayload($row);
            $selected = null;
            foreach (is_array($shops) ? $shops : [] as $shop) {
                if ((string)($shop['id'] ?? '') === $externalStoreId) {
                    $selected = $shop;
                    break;
                }
            }
            if (!is_array($selected)) {
                throw new MarketplaceIntegrationException('Toko yang dipilih tidak tersedia dari respons platform.', 'SHOP_SELECTION_INVALID');
            }
            $tokens = [
                'access_token' => MarketplaceTokenVault::decrypt((string)$row['access_token_encrypted']),
                'refresh_token' => $row['refresh_token_encrypted'] ? MarketplaceTokenVault::decrypt((string)$row['refresh_token_encrypted']) : null,
                'token_expires_at' => $row['token_expires_at'],
                'refresh_token_expires_at' => $row['refresh_token_expires_at'],
                'granted_scopes' => $this->decodeScopes($row['granted_scopes']),
            ];
            $storeId = $this->persistVerifiedConnection($context, (string)$row['platform'], $tokens, $selected);
            $this->pdo->prepare('UPDATE marketplace_oauth_pending SET consumed_at = NOW() WHERE id = ?')->execute([(int)$row['id']]);
            $this->pdo->commit();
            return ['status' => 'success', 'return_path' => (string)$row['return_path'], 'store_id' => $storeId];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $this->safeConnectionException($e);
        }
    }

    public function enqueueSync(string $storeId): array
    {
        $context = $this->context();
        $this->scope->assertStoreAccess($storeId);
        $stmt = $this->pdo->prepare('SELECT c.id, c.platform, c.status, c.company_key
            FROM marketplace_oauth_connections c
            WHERE c.store_id = ? AND c.company_key = ? LIMIT 1');
        $stmt->execute([$storeId, $context['company_key']]);
        $connection = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$connection || (string)$connection['status'] !== 'verified') {
            throw new MarketplaceIntegrationException('Toko belum terverifikasi atau memerlukan otorisasi ulang.', 'STORE_NOT_VERIFIED');
        }
        $active = $this->pdo->prepare("SELECT COUNT(*) FROM marketplace_sync_jobs WHERE connection_id = ? AND status IN ('queued','running')");
        $active->execute([(int)$connection['id']]);
        if ((int)$active->fetchColumn() > 0) {
            return ['status' => 'queued', 'message' => 'Sinkronisasi toko sudah berada dalam antrean.'];
        }
        $runKey = 'manual-' . date('YmdHis');
        $this->enqueueJobs((int)$connection['id'], $context['company_key'], $storeId, (string)$connection['platform'], $runKey);
        $this->pdo->prepare('UPDATE marketplace_oauth_connections SET last_sync_attempt_at = NOW(), last_error_code = NULL WHERE id = ?')->execute([(int)$connection['id']]);
        return ['status' => 'queued', 'message' => 'Sinkronisasi toko masuk antrean. Data belum dianggap masuk sampai worker berhasil.'];
    }

    public function refreshConnection(string $storeId,bool $force=true): array
    {
        $context = $this->context();
        $store=$this->scope->assertStoreAccess($storeId);
        $this->platformConfiguration($store['platform']);
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT c.*, s.company_key FROM marketplace_oauth_connections c JOIN marketplace_stores s ON s.id=c.store_id AND s.company_key=c.company_key WHERE c.store_id=? AND s.company_key=? FOR UPDATE');
            $stmt->execute([$storeId, $context['company_key']]);
            $connection = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$connection) throw new MarketplaceIntegrationException('Koneksi toko tidak ditemukan.', 'CONNECTION_NOT_FOUND');
            if(!$force && !empty($connection['token_expires_at']) && strtotime($connection['token_expires_at'])>time()+60){$this->pdo->commit();return ['status'=>'verified','message'=>'Token sudah diperbarui proses lain.'];}
            (new MarketplaceIntegrationRuntime($this->pdo))->assertContext($connection,$this->platformConfiguration($connection['platform']));
            $refreshToken = $connection['refresh_token_encrypted'] ? MarketplaceTokenVault::decrypt((string)$connection['refresh_token_encrypted']) : '';
            if ($refreshToken === '') throw new MarketplaceIntegrationException('Toko perlu dihubungkan ulang.', 'REAUTHORIZE_REQUIRED');
            $client = $this->clientFor((string)$connection['platform']);
            $tokens = $client->refreshToken($refreshToken, (string)$connection['external_store_id'], (string)($connection['external_shop_cipher'] ?? ''));
            $accessEncrypted = MarketplaceTokenVault::encrypt((string)$tokens['access_token']);
            $refreshEncrypted = !empty($tokens['refresh_token']) ? MarketplaceTokenVault::encrypt((string)$tokens['refresh_token']) : (string)$connection['refresh_token_encrypted'];
            $this->pdo->prepare('UPDATE marketplace_oauth_connections SET access_token_encrypted=?, refresh_token_encrypted=?, token_expires_at=?, refresh_token_expires_at=?, granted_scopes=?, status="verified", last_error_code=NULL, verified_at=NOW(), updated_at=NOW() WHERE id=?')
                ->execute([$accessEncrypted, $refreshEncrypted, $tokens['token_expires_at'], $tokens['refresh_token_expires_at'], json_encode($tokens['granted_scopes'] ?? []), (int)$connection['id']]);
            $this->pdo->commit();
            return ['status' => 'verified', 'message' => 'Token toko berhasil diperbarui.'];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                if ($e instanceof MarketplaceIntegrationException && in_array($e->errorCode, ['HTTP_401','TIKTOK_REFRESH_REJECTED','SHOPEE_API_REJECTED','REAUTHORIZE_REQUIRED'], true)) {
                    $this->pdo->prepare('UPDATE marketplace_oauth_connections SET status="reauthorize", last_error_code=? WHERE store_id=? AND company_key=?')->execute([$e->errorCode, $storeId, $context['company_key']]);
                }
                $this->pdo->commit();
            }
            throw $this->safeConnectionException($e);
        }
    }

    public function disconnect(string $storeId): array
    {
        $context = $this->context();
        $this->scope->assertStoreAccess($storeId);
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('UPDATE marketplace_oauth_connections SET status="disconnected", last_error_code=NULL, updated_at=NOW() WHERE store_id=? AND company_key=?')->execute([$storeId, $context['company_key']]);
            $this->pdo->prepare('UPDATE marketplace_stores SET status="Terputus", authorization_status="disconnected", updated_at=NOW() WHERE id=? AND company_key=?')->execute([$storeId, $context['company_key']]);
            $this->pdo->commit();
            return ['status' => 'disconnected', 'message' => 'Koneksi internal diputus. Histori transaksi tetap disimpan; izin di platform belum otomatis dicabut.'];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $this->safeConnectionException($e);
        }
    }

    public function callbackUrl(string $platform): string
    {
        $base = rtrim((string)($this->config['callback_url'] ?? ''), '?&');
        return $base . ($base !== '' && str_contains($base, '?') ? '&' : '?') . 'platform=' . rawurlencode($platform);
    }

    private function persistVerifiedConnection(array $context, string $platform, array $tokens, array $shop): string
    {
        $externalId = trim((string)($shop['id'] ?? ''));
        $name = trim((string)($shop['name'] ?? ''));
        if ($externalId === '' || $name === '') throw new MarketplaceIntegrationException('Respons platform tidak lengkap.', 'SHOP_IDENTITY_INCOMPLETE');

        $stmt = $this->pdo->prepare('SELECT c.*, s.company_key FROM marketplace_oauth_connections c JOIN marketplace_stores s ON s.id=c.store_id AND s.company_key=c.company_key WHERE c.platform=? AND c.external_store_id=? FOR UPDATE');
        $stmt->execute([$platform, $externalId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        MarketplaceStoreDeletion::guardAuthorization($this->pdo,$context,$platform,$externalId);
        if ($existing && (string)$existing['company_key'] !== $context['company_key']) {
            throw new MarketplaceIntegrationException('Toko sudah terhubung ke perusahaan lain.', 'STORE_OWNED_BY_OTHER_COMPANY');
        }

        if($existing && (new MarketplaceIntegrationRuntime($this->pdo))->ready()){$runtime=new MarketplaceIntegrationRuntime($this->pdo);$binding=$runtime->binding((int)$existing['id']);$configuration=$this->platformConfiguration($platform);$environment=MarketplaceIntegrationPolicy::environment($platform,$configuration);if($binding && $binding['environment']!=='unspecified' && $environment!=='unspecified' && $binding['environment']!==$environment)throw new MarketplaceIntegrationException('Identitas toko ini sudah terikat ke lingkungan lain. Koneksi Live dan Sandbox tidak boleh saling menimpa.','ENVIRONMENT_ID_CONFLICT');}
        $storeId = $existing ? (string)$existing['store_id'] : $this->findOrCreateStore($context['company_key'], $platform, $externalId, $name);
        $accessEncrypted = MarketplaceTokenVault::encrypt((string)$tokens['access_token']);
        $refreshEncrypted = !empty($tokens['refresh_token']) ? MarketplaceTokenVault::encrypt((string)$tokens['refresh_token']) : null;
        $scopes = json_encode(array_values($tokens['granted_scopes'] ?? []), JSON_THROW_ON_ERROR);

        if ($existing) {
            $this->pdo->prepare('UPDATE marketplace_oauth_connections SET company_key=?, store_id=?, external_shop_cipher=?, external_store_name=?, access_token_encrypted=?, refresh_token_encrypted=?, token_expires_at=?, refresh_token_expires_at=?, granted_scopes=?, authorized_at=NOW(), verified_at=NOW(), status="verified", last_error_code=NULL, updated_at=NOW() WHERE id=?')
                ->execute([$context['company_key'], $storeId, $shop['cipher'] ?? null, $name, $accessEncrypted, $refreshEncrypted, $tokens['token_expires_at'], $tokens['refresh_token_expires_at'], $scopes, (int)$existing['id']]);
            $connectionId = (int)$existing['id'];
        } else {
            $this->pdo->prepare('INSERT INTO marketplace_oauth_connections
                (company_key, store_id, platform, external_store_id, external_shop_cipher, external_store_name, access_token_encrypted, refresh_token_encrypted, token_expires_at, refresh_token_expires_at, granted_scopes, authorized_at, verified_at, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), "verified")')
                ->execute([$context['company_key'], $storeId, $platform, $externalId, $shop['cipher'] ?? null, $name, $accessEncrypted, $refreshEncrypted, $tokens['token_expires_at'], $tokens['refresh_token_expires_at'], $scopes]);
            $connectionId = (int)$this->pdo->lastInsertId();
        }

        $this->pdo->prepare('UPDATE marketplace_stores SET store_name=?, store_identifier=?, status="Terhubung", authorization_status="verified", archived_at=NULL, archived_by=NULL, last_error_code=NULL, updated_at=NOW() WHERE id=? AND company_key=?')
            ->execute([$name, $externalId, $storeId, $context['company_key']]);
        $runtime=new MarketplaceIntegrationRuntime($this->pdo);if($runtime->ready())$runtime->bind(['id'=>$connectionId,'platform'=>$platform],$this->platformConfiguration($platform),true);
        $this->enqueueJobs($connectionId, $context['company_key'], $storeId, $platform, 'initial');
        return $storeId;
    }

    private function findOrCreateStore(string $companyKey, string $platform, string $externalId, string $name): string
    {
        $stmt = $this->pdo->prepare('SELECT id, company_key FROM marketplace_stores WHERE platform=? AND store_identifier=? LIMIT 1 FOR UPDATE');
        $stmt->execute([$platform, $externalId]);
        $store = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($store && (string)$store['company_key'] !== $companyKey) {
            throw new MarketplaceIntegrationException('Toko sudah terhubung ke perusahaan lain.', 'STORE_OWNED_BY_OTHER_COMPANY');
        }
        if ($store) return (string)$store['id'];

        $prefix = $platform === 'Shopee' ? 'SHP' : 'TTS';
        $storeId = 'OAUTH-' . $prefix . '-' . strtoupper(bin2hex(random_bytes(16)));
        $this->pdo->prepare('INSERT INTO marketplace_stores (id, company_key, platform, store_name, store_identifier, sync_mode, status, authorization_status, total_transactions, created_at, updated_at) VALUES (?, ?, ?, ?, ?, "API", "Terhubung", "verified", 0, NOW(), NOW())')
            ->execute([$storeId, $companyKey, $platform, $name, $externalId]);
        return $storeId;
    }

    private function enqueueJobs(int $connectionId, string $companyKey, string $storeId, string $platform, string $runKey): void
    {
        $stmt = $this->pdo->prepare('INSERT IGNORE INTO marketplace_sync_jobs (connection_id, company_key, store_id, platform, job_type, run_key, status, checkpoint) VALUES (?, ?, ?, ?, ?, ?, "queued", ?)');
        if($platform==='TikTok Shop'&&$this->pdo->query("SHOW TABLES LIKE 'marketplace_auto_sync'")->fetchColumn())$this->pdo->prepare("INSERT IGNORE INTO marketplace_auto_sync(store_id,company_key,mode,enabled,next_run_at) VALUES (?,?,'finance',1,NOW())")->execute([$storeId,$companyKey]);
        $connection=$this->pdo->prepare('SELECT * FROM marketplace_oauth_connections WHERE id=? AND company_key=? AND store_id=?');
        $connection->execute([$connectionId,$companyKey,$storeId]);$identity=$connection->fetch(PDO::FETCH_ASSOC);
        if(!$identity)throw new MarketplaceIntegrationException('Identitas toko tidak tersedia.','STORE_NOT_VERIFIED');
        $incremental=new MarketplaceIncrementalSync($this->pdo);
        $anchor=MarketplaceSyncWindow::forStore($this->pdo,$storeId);
        // A product job already fetches every variant. A second variants job
        // would repeat the entire catalog without adding another dataset.
        foreach (($platform==='Shopee'?['products','orders','transactions']:['products','orders','transactions','returns']) as $jobType) {
            $checkpoint=$incremental->plan($identity,$jobType,$anchor);
            $stmt->execute([$connectionId, $companyKey, $storeId, $platform, $jobType, $runKey, json_encode($checkpoint, JSON_THROW_ON_ERROR)]);
        }
    }

    private function storePending(string $pendingId, array $context, string $platform, array $tokens, array $shops, string $returnPath): void
    {
        $this->pdo->beginTransaction();
        try {
        foreach($shops as $shop){
            $lock=$this->pdo->prepare('SELECT id FROM marketplace_stores WHERE company_key=? AND platform=? AND store_identifier=? FOR UPDATE');$lock->execute([$context['company_key'],$platform,$shop['id']]);$lock->fetchAll();
            MarketplaceStoreDeletion::guardAuthorization($this->pdo,$context,$platform,(string)$shop['id']);
        }
        $stmt = $this->pdo->prepare('INSERT INTO marketplace_oauth_pending
            (pending_hash, company_key, user_key, platform, access_token_encrypted, refresh_token_encrypted, token_expires_at, refresh_token_expires_at, granted_scopes, shops_json, return_path, expires_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))');
        $stmt->execute([
            hash('sha256', $pendingId),
            $context['company_key'],
            $context['user_key'],
            $platform,
            MarketplaceTokenVault::encrypt((string)$tokens['access_token']),
            !empty($tokens['refresh_token']) ? MarketplaceTokenVault::encrypt((string)$tokens['refresh_token']) : null,
            $tokens['token_expires_at'],
            $tokens['refresh_token_expires_at'],
            json_encode(array_values($tokens['granted_scopes'] ?? []), JSON_THROW_ON_ERROR),
            json_encode(['shops'=>$shops,'authorization_started'=>$context['authorization_started']??null,'authorization_context'=>$this->authorizationContext($platform)], JSON_THROW_ON_ERROR),
            $returnPath,
            max(60, (int)($this->config['state_ttl_seconds'] ?? 600)),
        ]);
        $this->pdo->commit();
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    private function platformConfiguration(string $platform):array
    {
        return $this->platformConfigurations[$platform] ??= (new MarketplacePartnerSettings($this->pdo))->configFor($platform);
    }
    private function authorizationContext(string $platform):string
    {
        $client=$this->clientFor($platform);
        return method_exists($client,'authorizationContext') ? (string)$client->authorizationContext()['fingerprint'] : hash('sha256',get_class($client).json_encode($this->config));
    }
    private function stateTag(string $nonce,string $platform,array $context):string
    {
        return substr(hash_hmac('sha256',implode('|',[$nonce,$platform,$context['company_key'],$context['user_key'],$this->callbackUrl($platform)]),$this->authorizationContext($platform)),0,32);
    }
    private function pendingPayload(array $row):array
    {
        $payload=json_decode((string)$row['shops_json'],true)?:[];
        if(!is_string($payload['authorization_context']??null)||!hash_equals($this->authorizationContext($row['platform']),$payload['authorization_context']))throw new MarketplaceIntegrationException('Konfigurasi otorisasi berubah atau pilihan toko kedaluwarsa. Mulai otorisasi kembali.','INTEGRATION_CONTEXT_CHANGED');
        return is_array($payload['shops']??null)?$payload['shops']:[];
    }

    private function loadState(string $stateValue, array $context): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM marketplace_oauth_states WHERE state_hash=? LIMIT 1');
        $stmt->execute([hash('sha256', $stateValue)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || (string)$row['company_key'] !== $context['company_key'] || (string)$row['user_key'] !== $context['user_key'] || strtotime((string)$row['expires_at']) < time() || $row['consumed_at'] !== null) {
            throw new MarketplaceIntegrationException('Sesi otorisasi tidak valid atau sudah kedaluwarsa.', 'INVALID_OAUTH_STATE');
        }
        return $row;
    }

    private function consumeState(array $state, array $context): void
    {
        $stmt = $this->pdo->prepare('UPDATE marketplace_oauth_states SET consumed_at=NOW() WHERE id=? AND company_key=? AND user_key=? AND consumed_at IS NULL AND expires_at>NOW()');
        $stmt->execute([(int)$state['id'], $context['company_key'], $context['user_key']]);
        if ($stmt->rowCount() !== 1) throw new MarketplaceIntegrationException('Sesi otorisasi tidak valid atau sudah digunakan.', 'INVALID_OAUTH_STATE');
    }

    private function loadPending(string $pendingId, array $context, bool $forUpdate): array
    {
        if (!preg_match('/^[a-f0-9]{48}$/', $pendingId)) throw new MarketplaceIntegrationException('Pilihan toko tidak valid.', 'INVALID_PENDING_SELECTION');
        $sql = 'SELECT * FROM marketplace_oauth_pending WHERE pending_hash=? AND company_key=? AND user_key=? AND consumed_at IS NULL AND expires_at>NOW() LIMIT 1';
        if ($forUpdate) $sql .= ' FOR UPDATE';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([hash('sha256', $pendingId), $context['company_key'], $context['user_key']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new MarketplaceIntegrationException('Pilihan toko sudah kedaluwarsa atau tidak tersedia.', 'INVALID_PENDING_SELECTION');
        return $row;
    }

    private function normalizeShops(mixed $shops): array
    {
        if (!is_array($shops)) return [];
        $result = [];
        foreach ($shops as $shop) {
            if (!is_array($shop)) continue;
            $id = trim((string)($shop['id'] ?? ''));
            $name = trim((string)($shop['name'] ?? ''));
            if ($id === '' || $name === '') continue;
            $result[] = ['id' => $id, 'name' => $name, 'cipher' => $shop['cipher'] ?? null, 'region' => $shop['region'] ?? null];
        }
        return $result;
    }

    private function decodeScopes(mixed $value): array
    {
        $decoded = json_decode((string)$value, true);
        return is_array($decoded) ? array_values(array_map('strval', $decoded)) : [];
    }

    private function context(): array
    {
        $userKey = trim((string)($_SESSION['user_id'] ?? ''));
        if ($userKey === '') throw new MarketplaceIntegrationException('Silakan login terlebih dahulu.', 'AUTH_REQUIRED');
        return ['user_key' => $userKey, 'company_key' => $this->scope->companyKey()];
    }

    private function clientFor(string $platform): MarketplaceOAuthClient
    {
        if (!isset($this->clients[$platform])) throw new InvalidArgumentException('Platform OAuth tidak didukung.');
        return $this->clients[$platform];
    }

    private function safeReturnPath(?string $returnPath): string
    {
        $returnPath = trim((string)($returnPath ?: ($this->config['return_path'] ?? 'marketplace.php?tab=pengaturan&peng_sub=tambah_toko')));
        $parsed = parse_url($returnPath);
        if (($parsed['scheme'] ?? '') !== '' || ($parsed['host'] ?? '') !== '' || ($parsed['path'] ?? 'marketplace.php') !== 'marketplace.php') {
            throw new InvalidArgumentException('Tujuan kembali tidak diizinkan.');
        }
        return ltrim($returnPath, '/');
    }

    private function isAdministrator(): bool
    {
        $role = strtoupper((string)($_SESSION['role'] ?? ''));
        return str_contains($role, 'OWNER') || str_contains($role, 'ADMIN');
    }

    private function safeConnectionException(Throwable $e): Throwable
    {
        if ($e instanceof MarketplaceIntegrationException || $e instanceof InvalidArgumentException) return $e;
        return new MarketplaceIntegrationException('Koneksi toko belum dapat diverifikasi.', 'CONNECTION_NOT_VERIFIED');
    }
}
