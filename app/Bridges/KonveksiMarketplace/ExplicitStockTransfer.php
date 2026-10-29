<?php
declare(strict_types=1);

final class ExplicitStockTransfer
{
    public function __construct(
        public readonly string $reference,
        public readonly string $variantId,
        public readonly int $quantity,
        public readonly string $sourceWarehouse,
        public readonly string $destinationStore,
    ) {
        if ($this->reference === '' || $this->variantId === '' || $this->sourceWarehouse === '' || $this->destinationStore === '') {
            throw new InvalidArgumentException('Referensi, varian, gudang asal, dan toko tujuan wajib diisi.');
        }
        if ($this->quantity <= 0) {
            throw new InvalidArgumentException('Jumlah transfer stok harus lebih besar dari nol.');
        }
    }

    public static function fromArray(array $payload): self
    {
        return new self(
            trim((string)($payload['reference'] ?? '')),
            trim((string)($payload['variant_id'] ?? '')),
            (int)($payload['quantity'] ?? 0),
            trim((string)($payload['source_warehouse'] ?? '')),
            trim((string)($payload['destination_store'] ?? '')),
        );
    }

    public function toArray(): array
    {
        return [
            'reference' => $this->reference,
            'variant_id' => $this->variantId,
            'quantity' => $this->quantity,
            'source_warehouse' => $this->sourceWarehouse,
            'destination_store' => $this->destinationStore,
        ];
    }
}
