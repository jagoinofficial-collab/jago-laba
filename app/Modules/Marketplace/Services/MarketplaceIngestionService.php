<?php
declare(strict_types=1);

/** Verified connector, reviewed import, and manual workflows share this writer. */
final class MarketplaceIngestionService
{
    public function __construct(private PDO $pdo) {}

    public function beginAuthorization(string $companyKey, string $userKey, string $platform): string
    {
        if ($companyKey === '' || $userKey === '' || !in_array($platform, ['Shopee','TikTok Shop'], true)) {
            throw new InvalidArgumentException('Pemilik dan platform otorisasi wajib valid.');
        }
        $state = bin2hex(random_bytes(32));
        $stmt = $this->pdo->prepare('INSERT INTO marketplace_oauth_attempts (state_hash,company_key,user_key,platform,expires_at) VALUES (?,?,?,?,DATE_ADD(NOW(), INTERVAL 10 MINUTE))');
        $stmt->execute([hash('sha256',$state),$companyKey,$userKey,$platform]);
        return $state;
    }

    public function consumeAuthorization(string $state, string $companyKey, string $userKey, string $platform): bool
    {
        if (strlen($state) !== 64 || !ctype_xdigit($state)) return false;
        $stmt = $this->pdo->prepare('UPDATE marketplace_oauth_attempts SET consumed_at=NOW() WHERE state_hash=? AND company_key=? AND user_key=? AND platform=? AND consumed_at IS NULL AND expires_at>NOW()');
        $stmt->execute([hash('sha256',$state),$companyKey,$userKey,$platform]);
        return $stmt->rowCount() === 1;
    }

    public function registerVerifiedStore(string $companyKey, string $platform, string $externalId, string $name): int
    {
        if ($companyKey === '' || $externalId === '' || $name === '' || !in_array($platform, ['Shopee', 'TikTok Shop'], true)) {
            throw new InvalidArgumentException('Identitas toko resmi tidak lengkap.');
        }
        $stmt = $this->pdo->prepare("INSERT INTO marketplace_connected_stores
            (company_key,platform,external_store_id,display_name,authorization_status)
            VALUES (?,?,?,?,'verified')
            ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
        $stmt->execute([$companyKey, $platform, $externalId, $name]);
        $id = (int)$this->pdo->lastInsertId();
        $check = $this->pdo->prepare('SELECT company_key FROM marketplace_connected_stores WHERE id=?');
        $check->execute([$id]);
        if ($check->fetchColumn() !== $companyKey) throw new DomainException('Toko sudah dimiliki perusahaan lain.');
        $this->pdo->prepare('UPDATE marketplace_connected_stores SET display_name=? WHERE id=?')->execute([$name,$id]);
        return $id;
    }

    public function enqueueVerifiedEvent(int $storeId, string $eventId, string $type, array $payload, ?string $sourceAt = null): int
    {
        if ($storeId < 1 || trim($eventId) === '' || trim($type) === '') throw new InvalidArgumentException('Identitas event wajib diisi.');
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $stmt = $this->pdo->prepare("INSERT INTO marketplace_webhook_inbox
            (store_id,external_event_id,event_type,source_at,payload_json)
            VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
        $stmt->execute([$storeId, $eventId, $type, $sourceAt, $json]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Caller must first verify an API response or explicitly mark an import for review.
     * This records source facts only: no bank balance, stock or journal side effect.
     */
    public function upsertOrder(int $storeId, string $sourceKind, array $order, array $items): int
    {
        if (!in_array($sourceKind, ['api','webhook','import','manual'], true)) throw new InvalidArgumentException('Sumber pesanan tidak dikenal.');
        $externalId = trim((string)($order['external_order_id'] ?? ''));
        $status = trim((string)($order['status'] ?? ''));
        $sourceUpdatedAt = (string)($order['source_updated_at'] ?? '');
        if ($storeId < 1 || $externalId === '' || $status === '' || !$this->validDate($sourceUpdatedAt)) {
            throw new InvalidArgumentException('Identitas, status, atau waktu sumber pesanan tidak valid.');
        }
        $gross = $this->moneyOrNull($order['gross_amount'] ?? null);
        if (isset($order['source_order_at']) && !$this->validDate((string)$order['source_order_at'])) {
            throw new InvalidArgumentException('Waktu pesanan tidak valid.');
        }
        if ($this->pdo->inTransaction()) throw new LogicException('Pencatatan pesanan memerlukan transaksi tersendiri.');
        $this->pdo->beginTransaction();
        try {
            // Serializes writers for this store. Unique keys protect against other writers.
            $store = $this->pdo->prepare("SELECT authorization_status FROM marketplace_connected_stores WHERE id=? FOR UPDATE");
            $store->execute([$storeId]);
            if ($store->fetchColumn() === false) throw new DomainException('Toko tidak ditemukan.');
            $find = $this->pdo->prepare('SELECT id,source_updated_at FROM marketplace_orders_v3 WHERE store_id=? AND external_order_id=? FOR UPDATE');
            $find->execute([$storeId, $externalId]);
            $existing = $find->fetch(PDO::FETCH_ASSOC);
            if ($existing && strcmp((string)$existing['source_updated_at'], $sourceUpdatedAt) > 0) {
                $this->pdo->commit();
                return (int)$existing['id'];
            }
            if ($existing) {
                $orderId = (int)$existing['id'];
                $stmt = $this->pdo->prepare('UPDATE marketplace_orders_v3 SET order_status=?,source_updated_at=?,source_order_at=COALESCE(?,source_order_at),gross_amount=COALESCE(?,gross_amount),data_complete=?,source_kind=? WHERE id=?');
                $stmt->execute([$status,$sourceUpdatedAt,$order['source_order_at'] ?? null,$gross,!empty($order['data_complete']) ? 1 : 0,$sourceKind,$orderId]);
            } else {
                $stmt = $this->pdo->prepare('INSERT INTO marketplace_orders_v3 (store_id,external_order_id,order_status,source_updated_at,source_order_at,gross_amount,data_complete,source_kind) VALUES (?,?,?,?,?,?,?,?)');
                $stmt->execute([$storeId,$externalId,$status,$sourceUpdatedAt,$order['source_order_at'] ?? null,$gross,!empty($order['data_complete']) ? 1 : 0,$sourceKind]);
                $orderId = (int)$this->pdo->lastInsertId();
            }
            $itemStmt = $this->pdo->prepare('INSERT INTO marketplace_order_items_v3 (order_id,external_item_id,external_product_id,external_variant_id,seller_sku_snapshot,name_snapshot,quantity,unit_price,unit_cogs_snapshot) VALUES (?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE external_product_id=VALUES(external_product_id),external_variant_id=VALUES(external_variant_id),seller_sku_snapshot=VALUES(seller_sku_snapshot),name_snapshot=VALUES(name_snapshot),quantity=VALUES(quantity),unit_price=VALUES(unit_price)');
            foreach ($items as $item) {
                $itemId = trim((string)($item['external_item_id'] ?? ''));
                $name = trim((string)($item['name'] ?? ''));
                $qty = (string)($item['quantity'] ?? '');
                if ($itemId === '' || $name === '' || !preg_match('/^\d+(?:\.\d{1,3})?$/', $qty) || (float)$qty <= 0) {
                    throw new InvalidArgumentException('Item memerlukan ID sumber stabil, nama, dan jumlah valid.');
                }
                $itemStmt->execute([$orderId,$itemId,$item['external_product_id'] ?? null,$item['external_variant_id'] ?? null,$item['seller_sku'] ?? null,$name,$qty,$this->moneyOrNull($item['unit_price'] ?? null),$this->moneyOrNull($item['unit_cogs_snapshot'] ?? null)]);
            }
            $this->pdo->commit();
            return $orderId;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    private function moneyOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') return null;
        $text = (string)$value;
        if (!preg_match('/^-?\d{1,16}(?:\.\d{1,2})?$/', $text)) throw new InvalidArgumentException('Nilai uang harus desimal yang valid.');
        return $text;
    }

    private function validDate(string $value): bool
    {
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
        return $dt !== false && $dt->format('Y-m-d H:i:s') === $value;
    }
}
