<?php
declare(strict_types=1);

/** Durable queue state only. Platform payload processing is deliberately separate. */
final class MarketplaceInboxService
{
    public function __construct(private PDO $pdo) {}

    public function claimNext(int $storeId): ?array
    {
        if ($storeId < 1) throw new InvalidArgumentException('Toko tidak valid.');
        $this->pdo->beginTransaction();
        try {
            // Per-store lock prevents two workers from racing on this shop.
            $lock = $this->pdo->prepare('SELECT id FROM marketplace_connected_stores WHERE id=? FOR UPDATE');
            $lock->execute([$storeId]);
            if (!$lock->fetchColumn()) throw new DomainException('Toko tidak ditemukan.');
            $stmt = $this->pdo->prepare("SELECT * FROM marketplace_webhook_inbox
                WHERE store_id=? AND ((status IN ('pending','retry') AND (next_attempt_at IS NULL OR next_attempt_at<=NOW()))
                OR (status='processing' AND next_attempt_at<=NOW()))
                ORDER BY received_at,id LIMIT 1 FOR UPDATE");
            $stmt->execute([$storeId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $lease = bin2hex(random_bytes(32));
                $this->pdo->prepare("UPDATE marketplace_webhook_inbox SET status='processing',attempts=attempts+1,next_attempt_at=DATE_ADD(NOW(),INTERVAL 10 MINUTE),lease_token=? WHERE id=?")
                    ->execute([$lease,$row['id']]);
                $row['attempts'] = (int)$row['attempts'] + 1;
                $row['lease_token'] = $lease;
            }
            $this->pdo->commit();
            return $row ?: null;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    public function markProcessed(int $eventId, string $leaseToken): void
    {
        $stmt = $this->pdo->prepare("UPDATE marketplace_webhook_inbox SET status='processed',processed_at=NOW(),next_attempt_at=NULL,last_error_code=NULL,lease_token=NULL WHERE id=? AND status='processing' AND lease_token=?");
        $stmt->execute([$eventId,$leaseToken]);
        if ($stmt->rowCount() !== 1) throw new LogicException('Event tidak sedang diproses.');
    }

    public function markFailed(int $eventId, string $leaseToken, string $errorCode): void
    {
        if ($errorCode === '' || strlen($errorCode) > 100) throw new InvalidArgumentException('Kode kesalahan tidak valid.');
        $stmt = $this->pdo->prepare("UPDATE marketplace_webhook_inbox SET
            status=IF(attempts>=5,'dead','retry'),
            next_attempt_at=IF(attempts>=5,NULL,DATE_ADD(NOW(),INTERVAL LEAST(900,POW(2,attempts)*15 + FLOOR(RAND()*15)) SECOND)),
            last_error_code=?,lease_token=NULL WHERE id=? AND status='processing' AND lease_token=?");
        $stmt->execute([$errorCode,$eventId,$leaseToken]);
        if ($stmt->rowCount() !== 1) throw new LogicException('Event tidak sedang diproses.');
    }
}
