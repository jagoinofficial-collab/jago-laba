<?php

/** Boundary for official APIs. No connector is enabled before platform verification. */
final class MarketplaceApiService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?: Database::pdo();
    }

    public function getCredentials(string $storeId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM marketplace_api_credentials WHERE store_id = ?');
        $stmt->execute([$storeId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function saveCredentials(array $data): bool
    {
        throw new LogicException('Penyimpanan kredensial langsung dinonaktifkan. Otorisasi toko harus melalui OAuth resmi.');
    }

    public function testConnection(string $storeId): array
    {
        return ['success' => false, 'message' => 'Koneksi belum diverifikasi ke API resmi. Konfigurasi lama bukan bukti otorisasi toko.'];
    }

    public function syncStoreData(string $storeId): array
    {
        (new MarketplaceSyncService($this->pdo))->enqueueAll($storeId);
        return ['success' => true, 'message' => 'Antrean Sandbox TikTok siap. Buka menu Sinkronisasi Seluruhnya untuk memproses produk, order, keuangan, dan retur.'];
    }

    public function syncMarketingData(string $storeId): array
    {
        return ['success' => false, 'message' => 'Sinkronisasi iklan belum aktif karena sumber resmi belum terhubung.'];
    }

    public function refreshOAuthToken(string $storeId): array
    {
        return ['success' => false, 'message' => 'Refresh token belum aktif; konektor resmi perlu disiapkan.'];
    }

    public function pullDailyAdSpend(string $storeId, ?string $date = null): array
    {
        return ['success' => false, 'message' => 'Pengambilan biaya iklan belum aktif karena API resmi belum terhubung.'];
    }

    public function syncOrderStatuses(string $storeId): array
    {
        return ['success' => false, 'orders_checked' => 0, 'orders_completed' => 0,
            'message' => 'Status pesanan belum dapat diambil dari API resmi.'];
    }

    public function updateMarketplaceStock(string $storeId, string $sku, int $availableStock): array
    {
        return ['success' => false, 'message' => 'Stok platform tidak diubah; konektor resmi belum aktif.'];
    }

    public function handleIncomingWebhook(string $platform, array $data): array
    {
        throw new LogicException('Webhook belum dapat diproses tanpa verifikasi platform dan identitas toko resmi.');
    }
}
