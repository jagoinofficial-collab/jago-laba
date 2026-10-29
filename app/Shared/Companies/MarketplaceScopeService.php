<?php
declare(strict_types=1);

final class MarketplaceScopeService
{
    public function __construct(private PDO $pdo) {}

    public function companyKey(): string
    {
        $candidate = $_SESSION['company_key']
            ?? $_SESSION['company_id']
            ?? getenv('JL_COMPANY_KEY')
            ?: 'LOCAL-COMPANY';

        $key = trim((string)$candidate);
        return $key !== '' ? substr($key, 0, 120) : 'LOCAL-COMPANY';
    }

    public function assertStoreAccess(string $storeId): array
    {
        $storeId = trim($storeId);
        if ($storeId === '') {
            throw new InvalidArgumentException('Toko marketplace wajib dipilih.');
        }

        $stmt = $this->pdo->prepare('SELECT * FROM marketplace_stores WHERE id = ? AND company_key = ? AND archived_at IS NULL LIMIT 1');
        $stmt->execute([$storeId, $this->companyKey()]);
        $store = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$store) {
            throw new DomainException('Toko marketplace tidak ditemukan dalam perusahaan aktif.');
        }

        return $store;
    }

    public function accessibleStoreIds(bool $includeDisconnected = true): array
    {
        $sql = 'SELECT id FROM marketplace_stores WHERE company_key = ? AND archived_at IS NULL';
        $params = [$this->companyKey()];
        if (!$includeDisconnected) {
            $sql .= " AND authorization_status <> 'disconnected'";
        }
        $sql .= ' ORDER BY id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
}
