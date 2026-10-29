<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/Bootstrap/bootstrap.php';

function assertPolicy(bool $cond, string $msg): void
{
    if (!$cond) {
        throw new RuntimeException("Assertion failed: " . $msg);
    }
}

// 1. Role Normalization
assertPolicy(RolePolicy::normalize('owner') === RolePolicy::ROLE_OWNER, 'Owner normalization');
assertPolicy(RolePolicy::normalize('ADMIN') === RolePolicy::ROLE_ADMINISTRATOR, 'Admin normalization');
assertPolicy(RolePolicy::normalize('keuangan') === RolePolicy::ROLE_KEUANGAN, 'Keuangan normalization');
assertPolicy(RolePolicy::normalize('finance') === RolePolicy::ROLE_KEUANGAN, 'Finance normalization');
assertPolicy(RolePolicy::normalize('operasional') === RolePolicy::ROLE_OPERASIONAL, 'Operasional normalization');
assertPolicy(RolePolicy::normalize('operational') === RolePolicy::ROLE_OPERASIONAL, 'Operational normalization');
assertPolicy(RolePolicy::normalize('staff') === 'STAFF', 'Staff normalization retains unprivileged STAFF');
assertPolicy(RolePolicy::normalize('pembaca') === RolePolicy::ROLE_PEMBACA, 'Pembaca normalization');
assertPolicy(RolePolicy::normalize('viewer') === RolePolicy::ROLE_PEMBACA, 'Viewer normalization');

// 2. Owner permissions
assertPolicy(RolePolicy::can(RolePolicy::ACTION_STORE_DELETE, RolePolicy::ROLE_OWNER), 'Owner can delete store');
assertPolicy(RolePolicy::can(RolePolicy::ACTION_HPP_EDIT, RolePolicy::ROLE_OWNER), 'Owner can edit HPP');
assertPolicy(RolePolicy::can(RolePolicy::ACTION_SHIPPING_FULFILL, RolePolicy::ROLE_OWNER), 'Owner can fulfill shipping');

// 3. Administrator permissions
assertPolicy(RolePolicy::can(RolePolicy::ACTION_SETTINGS_MANAGE, RolePolicy::ROLE_ADMINISTRATOR), 'Admin can manage settings');
assertPolicy(!RolePolicy::can(RolePolicy::ACTION_STORE_DELETE, RolePolicy::ROLE_ADMINISTRATOR), 'Admin cannot delete store');
assertPolicy(RolePolicy::can(RolePolicy::ACTION_USER_MANAGE, RolePolicy::ROLE_ADMINISTRATOR), 'Admin can manage users');

// 4. Keuangan permissions
assertPolicy(RolePolicy::can(RolePolicy::ACTION_HPP_EDIT, RolePolicy::ROLE_KEUANGAN), 'Keuangan can edit HPP');
assertPolicy(RolePolicy::can(RolePolicy::ACTION_FINANCE_MANAGE, RolePolicy::ROLE_KEUANGAN), 'Keuangan can manage finance');
assertPolicy(RolePolicy::can(RolePolicy::ACTION_WITHDRAWAL_MANAGE, RolePolicy::ROLE_KEUANGAN), 'Keuangan can manage withdrawals');
assertPolicy(RolePolicy::can(RolePolicy::ACTION_BANK_RECONCILE, RolePolicy::ROLE_KEUANGAN), 'Keuangan can reconcile bank');
assertPolicy(!RolePolicy::can(RolePolicy::ACTION_SHIPPING_FULFILL, RolePolicy::ROLE_KEUANGAN), 'Keuangan cannot fulfill shipping');
assertPolicy(!RolePolicy::can(RolePolicy::ACTION_STORE_DELETE, RolePolicy::ROLE_KEUANGAN), 'Keuangan cannot delete store');

// 5. Operasional permissions
assertPolicy(RolePolicy::can(RolePolicy::ACTION_ORDERS_MANAGE, RolePolicy::ROLE_OPERASIONAL), 'Operasional can manage orders');
assertPolicy(RolePolicy::can(RolePolicy::ACTION_SHIPPING_FULFILL, RolePolicy::ROLE_OPERASIONAL), 'Operasional can fulfill shipping');
assertPolicy(RolePolicy::can(RolePolicy::ACTION_INVENTORY_MANAGE, RolePolicy::ROLE_OPERASIONAL), 'Operasional can manage inventory');
assertPolicy(RolePolicy::can(RolePolicy::ACTION_PRICE_MANAGE, RolePolicy::ROLE_OPERASIONAL), 'Operasional can manage price');
assertPolicy(!RolePolicy::can(RolePolicy::ACTION_HPP_EDIT, RolePolicy::ROLE_OPERASIONAL), 'Operasional cannot edit HPP');
assertPolicy(!RolePolicy::can(RolePolicy::ACTION_WITHDRAWAL_MANAGE, RolePolicy::ROLE_OPERASIONAL), 'Operasional cannot manage withdrawals');

// 6. Pembaca permissions
assertPolicy(RolePolicy::can(RolePolicy::ACTION_REPORT_VIEW, RolePolicy::ROLE_PEMBACA), 'Pembaca can view reports');
assertPolicy(!RolePolicy::can(RolePolicy::ACTION_ORDERS_MANAGE, RolePolicy::ROLE_PEMBACA), 'Pembaca cannot manage orders');
assertPolicy(!RolePolicy::can(RolePolicy::ACTION_HPP_EDIT, RolePolicy::ROLE_PEMBACA), 'Pembaca cannot edit HPP');
assertPolicy(!RolePolicy::can(RolePolicy::ACTION_SHIPPING_FULFILL, RolePolicy::ROLE_PEMBACA), 'Pembaca cannot fulfill shipping');
assertPolicy(!RolePolicy::can(RolePolicy::ACTION_SETTINGS_MANAGE, RolePolicy::ROLE_PEMBACA), 'Pembaca cannot manage settings');

// 7. Strict require exception test
$_SESSION['user_id'] = 'TEST-USER';
$_SESSION['role'] = 'PEMBACA';
$caught = false;
try {
    RolePolicy::require(RolePolicy::ACTION_HPP_EDIT);
} catch (DomainException $e) {
    $caught = true;
}
assertPolicy($caught, 'Require throws DomainException when denied');

$_SESSION['role'] = 'KEUANGAN';
$caught = false;
try {
    RolePolicy::require(RolePolicy::ACTION_HPP_EDIT);
} catch (DomainException $e) {
    $caught = true;
}
assertPolicy(!$caught, 'Require succeeds when role has permission');

echo "PASS: RolePolicy RBAC tests for Owner, Administrator, Keuangan, Operasional, and Pembaca.\n";

