<?php
declare(strict_types=1);

/**
 * Role-Based Access Control (RBAC) policy enforcing PRD Section 2:
 * - Owner: Seluruh data perusahaan, konfigurasi, laporan, dan tindakan penghapusan.
 * - Administrator: Mengelola integrasi, pengguna, dan operasional sesuai izin.
 * - Keuangan: HPP, rincian keuangan, pengeluaran, pencairan, rekonsiliasi.
 * - Operasional: Pesanan, pengiriman, resi, dan inventori.
 * - Pembaca: Melihat laporan tanpa mengubah data.
 *
 * Every server request verifies company, store, and specific action permission.
 */
final class RolePolicy
{
    public const ROLE_OWNER = 'OWNER';
    public const ROLE_ADMINISTRATOR = 'ADMINISTRATOR';
    public const ROLE_KEUANGAN = 'KEUANGAN';
    public const ROLE_OPERASIONAL = 'OPERASIONAL';
    public const ROLE_PEMBACA = 'PEMBACA';

    public const ACTION_STORE_DELETE = 'store.delete';
    public const ACTION_SETTINGS_MANAGE = 'settings.manage';
    public const ACTION_USER_MANAGE = 'user.manage';
    public const ACTION_INTEGRATION_MANAGE = 'integration.manage';
    public const ACTION_FINANCE_MANAGE = 'finance.manage';
    public const ACTION_HPP_EDIT = 'hpp.edit';
    public const ACTION_EXPENSE_MANAGE = 'expense.manage';
    public const ACTION_WITHDRAWAL_MANAGE = 'withdrawal.manage';
    public const ACTION_BANK_RECONCILE = 'bank.reconcile';
    public const ACTION_ORDERS_MANAGE = 'orders.manage';
    public const ACTION_SHIPPING_FULFILL = 'shipping.fulfill';
    public const ACTION_INVENTORY_MANAGE = 'inventory.manage';
    public const ACTION_PRICE_MANAGE = 'price.manage';
    public const ACTION_SYNC_TRIGGER = 'sync.trigger';
    public const ACTION_REPORT_VIEW = 'report.view';

    private const ROLE_CAPABILITIES = [
        self::ROLE_OWNER => [
            self::ACTION_STORE_DELETE,
            self::ACTION_SETTINGS_MANAGE,
            self::ACTION_USER_MANAGE,
            self::ACTION_INTEGRATION_MANAGE,
            self::ACTION_FINANCE_MANAGE,
            self::ACTION_HPP_EDIT,
            self::ACTION_EXPENSE_MANAGE,
            self::ACTION_WITHDRAWAL_MANAGE,
            self::ACTION_BANK_RECONCILE,
            self::ACTION_ORDERS_MANAGE,
            self::ACTION_SHIPPING_FULFILL,
            self::ACTION_INVENTORY_MANAGE,
            self::ACTION_PRICE_MANAGE,
            self::ACTION_SYNC_TRIGGER,
            self::ACTION_REPORT_VIEW,
        ],
        self::ROLE_ADMINISTRATOR => [
            self::ACTION_SETTINGS_MANAGE,
            self::ACTION_USER_MANAGE,
            self::ACTION_INTEGRATION_MANAGE,
            self::ACTION_FINANCE_MANAGE,
            self::ACTION_HPP_EDIT,
            self::ACTION_EXPENSE_MANAGE,
            self::ACTION_WITHDRAWAL_MANAGE,
            self::ACTION_BANK_RECONCILE,
            self::ACTION_ORDERS_MANAGE,
            self::ACTION_SHIPPING_FULFILL,
            self::ACTION_INVENTORY_MANAGE,
            self::ACTION_PRICE_MANAGE,
            self::ACTION_SYNC_TRIGGER,
            self::ACTION_REPORT_VIEW,
        ],
        self::ROLE_KEUANGAN => [
            self::ACTION_FINANCE_MANAGE,
            self::ACTION_HPP_EDIT,
            self::ACTION_EXPENSE_MANAGE,
            self::ACTION_WITHDRAWAL_MANAGE,
            self::ACTION_BANK_RECONCILE,
            self::ACTION_SYNC_TRIGGER,
            self::ACTION_REPORT_VIEW,
        ],
        self::ROLE_OPERASIONAL => [
            self::ACTION_ORDERS_MANAGE,
            self::ACTION_SHIPPING_FULFILL,
            self::ACTION_INVENTORY_MANAGE,
            self::ACTION_PRICE_MANAGE,
            self::ACTION_SYNC_TRIGGER,
            self::ACTION_REPORT_VIEW,
        ],
        self::ROLE_PEMBACA => [
            self::ACTION_REPORT_VIEW,
        ],
    ];

    /**
     * Normalizes string representation of user role to canonical PRD role.
     */
    public static function normalize(?string $rawRole): string
    {
        $role = strtoupper(trim((string)$rawRole));
        if ($role === '') {
            return '';
        }

        if (str_contains($role, 'OWNER')) {
            return self::ROLE_OWNER;
        }

        if (str_contains($role, 'ADMIN')) {
            return self::ROLE_ADMINISTRATOR;
        }

        if (str_contains($role, 'KEUANGAN') || str_contains($role, 'FINANCE')) {
            return self::ROLE_KEUANGAN;
        }

        if (str_contains($role, 'OPERASIONAL') || str_contains($role, 'OPERATIONAL')) {
            return self::ROLE_OPERASIONAL;
        }

        if (str_contains($role, 'PEMBACA') || str_contains($role, 'VIEWER') || str_contains($role, 'READER')) {
            return self::ROLE_PEMBACA;
        }

        return $role;
    }

    /**
     * Returns the canonical role of currently logged in user session.
     */
    public static function currentRole(): string
    {
        return self::normalize($_SESSION['role'] ?? '');
    }

    /**
     * Checks if current or supplied role is allowed to perform action.
     */
    public static function can(string $action, ?string $role = null): bool
    {
        $targetRole = $role !== null ? self::normalize($role) : self::currentRole();
        if ($targetRole === '') {
            return false;
        }

        $capabilities = self::ROLE_CAPABILITIES[$targetRole] ?? [];
        return in_array($action, $capabilities, true);
    }

    /**
     * Strict server-side check. Throws DomainException / 403 HTTP status if unauthorized.
     */
    public static function require(string $action, ?string $message = null): void
    {
        if (empty($_SESSION['user_id'])) {
            if (!headers_sent()) {
                http_response_code(401);
            }
            throw new DomainException('Sesi telah berakhir. Silakan login kembali.');
        }

        if (!self::can($action)) {
            if (!headers_sent()) {
                http_response_code(403);
            }
            $actionDescriptions = [
                self::ACTION_STORE_DELETE => 'menghapus toko (khusus Owner)',
                self::ACTION_SETTINGS_MANAGE => 'mengubah pengaturan sistem',
                self::ACTION_USER_MANAGE => 'mengelola pengguna dan hak akses',
                self::ACTION_INTEGRATION_MANAGE => 'mengelola integrasi platform API',
                self::ACTION_FINANCE_MANAGE => 'mengelola data keuangan dan pencairan',
                self::ACTION_HPP_EDIT => 'mengubah HPP produk',
                self::ACTION_EXPENSE_MANAGE => 'mencatat atau mengubah pengeluaran',
                self::ACTION_WITHDRAWAL_MANAGE => 'mengelola penarikan dana marketplace',
                self::ACTION_BANK_RECONCILE => 'melakukan rekonsiliasi mutasi bank',
                self::ACTION_ORDERS_MANAGE => 'memproses pesanan operasional',
                self::ACTION_SHIPPING_FULFILL => 'mengatur pengiriman dan resi kurir',
                self::ACTION_INVENTORY_MANAGE => 'mengubah inventori dan stok barang',
                self::ACTION_PRICE_MANAGE => 'mengubah harga dan diskon produk',
                self::ACTION_SYNC_TRIGGER => 'menjalankan sinkronisasi data',
                self::ACTION_REPORT_VIEW => 'melihat laporan',
            ];
            $desc = $actionDescriptions[$action] ?? $action;
            throw new DomainException($message ?? "Akses ditolak: Peran Anda tidak memiliki izin untuk {$desc}.");
        }
    }

    /**
     * Ensures role is not purely a read-only viewer (PEMBACA).
     */
    public static function assertCanModify(string $action = self::ACTION_REPORT_VIEW): void
    {
        $role = self::currentRole();
        if ($role === self::ROLE_PEMBACA) {
            if (!headers_sent()) {
                http_response_code(403);
            }
            throw new DomainException('Akses ditolak: Peran Pembaca hanya dapat melihat laporan dan tidak diizinkan mengubah data.');
        }
        if ($action !== self::ACTION_REPORT_VIEW) {
            self::require($action);
        }
    }

    /**
     * Returns true if current role is read-only.
     */
    public static function isReadOnly(): bool
    {
        return self::currentRole() === self::ROLE_PEMBACA;
    }
}

