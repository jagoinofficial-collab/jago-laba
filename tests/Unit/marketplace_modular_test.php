<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/Bootstrap/bootstrap.php';
MarketplaceModule::boot();

require_once dirname(__DIR__, 2) . '/app/Modules/Marketplace/Models/MarketplaceStore.php';
require_once dirname(__DIR__, 2) . '/app/Modules/Marketplace/Models/MarketplaceOrder.php';
require_once dirname(__DIR__, 2) . '/app/Modules/Marketplace/Repositories/MarketplaceStoreRepository.php';
require_once dirname(__DIR__, 2) . '/app/Modules/Marketplace/Requests/MarketplaceActionRequest.php';

function assertMod(bool $cond, string $msg): void
{
    if (!$cond) {
        throw new RuntimeException("Assertion failed: " . $msg);
    }
}

// 1. Model: MarketplaceStore
$store = MarketplaceStore::fromArray([
    'id' => 'STORE-101',
    'company_key' => 'COMPANY-A',
    'platform' => 'TikTok Shop',
    'store_name' => 'Toko Resmi Jagoin',
    'store_identifier' => '12345',
    'authorization_status' => 'verified',
]);
assertMod($store->id === 'STORE-101', 'Store ID mapped');
assertMod($store->isVerified(), 'Store is verified');
assertMod(!$store->isArchived(), 'Store is not archived');

// 2. Model: MarketplaceOrder
$order = MarketplaceOrder::fromArray([
    'id' => 'ORD-999',
    'store_id' => 'STORE-101',
    'order_date' => '2026-10-25 10:00:00',
    'order_status' => 'COMPLETED',
    'gross_sales' => 150000,
    'net_income' => 120000,
    'transaction_detail_json' => json_encode(['is_estimated' => false]),
]);
assertMod($order->id === 'ORD-999', 'Order ID mapped');
assertMod($order->grossSales === 150000.0, 'Gross sales mapped');
assertMod($order->netIncome === 120000.0, 'Net income mapped');
assertMod(!$order->isEstimated, 'Final order marked not estimated');

// 3. Request Authorization & Role Enforcement
$_SESSION = [
    'user_id' => 'TEST-OWNER',
    'company_key' => 'COMPANY-A',
    'role' => 'OWNER',
];
Workspace::select(Workspace::MARKETPLACE);

// With Owner role, HPP edit should be allowed
$caught = false;
try {
    MarketplaceActionRequest::authorize(RolePolicy::ACTION_HPP_EDIT);
} catch (DomainException $e) {
    $caught = true;
}
assertMod(!$caught, 'Owner can edit HPP');

// Test that RolePolicy rejects unauthorized actions
assertMod(!RolePolicy::can(RolePolicy::ACTION_HPP_EDIT, RolePolicy::ROLE_PEMBACA), 'Pembaca role cannot edit HPP in policy');
assertMod(RolePolicy::can(RolePolicy::ACTION_HPP_EDIT, RolePolicy::ROLE_KEUANGAN), 'Keuangan role can edit HPP in policy');

echo "PASS: Marketplace Modular Models, Repositories, and Requests.\n";

