<?php
declare(strict_types=1);

/**
 * Domain Model for Marketplace Order.
 * PRD Section 4 & 7: Tracks order ID, dates, status, buyer payment vs net income.
 */
final class MarketplaceOrder
{
    public function __construct(
        public readonly string $id,
        public readonly string $storeId,
        public readonly string $orderDate,
        public readonly string $orderStatus,
        public readonly ?float $grossSales = null,
        public readonly ?float $netIncome = null,
        public readonly ?float $totalFees = null,
        public readonly ?float $totalCogs = null,
        public readonly ?float $netProfit = null,
        public readonly string $sourceKind = 'api',
        public readonly bool $isEstimated = true,
        public readonly ?string $trackingNumber = null,
        public readonly ?string $shippingCarrier = null,
        public readonly array $rawSnapshot = []
    ) {}

    public static function fromArray(array $row): self
    {
        $snapshot = json_decode((string)($row['transaction_detail_json'] ?? '{}'), true) ?: [];
        $isEstimated = ($snapshot['is_estimated'] ?? true) !== false;

        return new self(
            id: (string)($row['id'] ?? ''),
            storeId: (string)($row['store_id'] ?? ''),
            orderDate: (string)($row['order_date'] ?? ''),
            orderStatus: (string)($row['order_status'] ?? ''),
            grossSales: isset($row['gross_sales']) ? (float)$row['gross_sales'] : null,
            netIncome: isset($row['net_income']) ? (float)$row['net_income'] : null,
            totalFees: isset($row['total_fees']) ? (float)$row['total_fees'] : null,
            totalCogs: isset($row['total_cogs']) ? (float)$row['total_cogs'] : null,
            netProfit: isset($row['net_profit']) ? (float)$row['net_profit'] : null,
            sourceKind: (string)($row['source_kind'] ?? 'api'),
            isEstimated: $isEstimated,
            trackingNumber: isset($row['tracking_number']) ? (string)$row['tracking_number'] : null,
            shippingCarrier: isset($row['shipping_carrier']) ? (string)$row['shipping_carrier'] : null,
            rawSnapshot: $snapshot
        );
    }
}

