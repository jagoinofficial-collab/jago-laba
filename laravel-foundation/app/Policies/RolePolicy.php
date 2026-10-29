<?php

namespace App\Policies;

use App\Auth\LegacyUser;
use Illuminate\Contracts\Auth\Authenticatable;

final class RolePolicy
{
    public const OWNER = 'OWNER';

    public const ADMINISTRATOR = 'ADMINISTRATOR';

    /**
     * @var array<string, list<string>>
     */
    private const ACTIONS = [
        'KEUANGAN' => [
            'finance.manage',
            'hpp.edit',
            'expense.manage',
            'withdrawal.manage',
            'bank.manage',
            'sync.trigger',
            'report.view',
        ],
        'OPERASIONAL' => [
            'orders.manage',
            'shipping.fulfill',
            'inventory.manage',
            'price.manage',
            'sync.trigger',
            'report.view',
        ],
        'PEMBACA' => [
            'report.view',
        ],
    ];

    public function allows(?Authenticatable $user, string $action): bool
    {
        if (! $user instanceof LegacyUser) {
            return false;
        }

        $role = self::normalize((string) $user->getRole());
        $action = strtolower(trim($action));

        if ($role === self::OWNER) {
            return $action !== '';
        }

        if ($role === self::ADMINISTRATOR) {
            return $action !== 'store.delete' && $action !== '';
        }

        return in_array($action, self::ACTIONS[$role] ?? [], true);
    }

    public function can(?Authenticatable $user, string $action): bool
    {
        return $this->allows($user, $action);
    }

    public static function normalize(string $role): string
    {
        $value = strtoupper(trim($role));

        return match (true) {
            str_contains($value, 'OWNER') => self::OWNER,
            str_contains($value, 'ADMIN') => self::ADMINISTRATOR,
            str_contains($value, 'KEUANGAN'), str_contains($value, 'FINANCE') => 'KEUANGAN',
            str_contains($value, 'OPERASIONAL'), str_contains($value, 'OPERATIONAL') => 'OPERASIONAL',
            str_contains($value, 'PEMBACA'), str_contains($value, 'VIEWER'), str_contains($value, 'READER') => 'PEMBACA',
            default => $value,
        };
    }
}
