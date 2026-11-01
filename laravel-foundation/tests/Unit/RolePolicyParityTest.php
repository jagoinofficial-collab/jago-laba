<?php

namespace Tests\Unit;

use App\Auth\LegacyUser;
use App\Policies\RolePolicy;
use PHPUnit\Framework\TestCase;

final class RolePolicyParityTest extends TestCase
{
    public function test_every_role_and_action_matches_the_legacy_policy(): void
    {
        require_once dirname(__DIR__, 3).'/app/Shared/Authorization/RolePolicy.php';
        $actions = ['unknown.action', '', 'bank.manage'];
        foreach ((new \ReflectionClass(\RolePolicy::class))->getConstants() as $name => $value) {
            if (str_starts_with($name, 'ACTION_')) {
                $actions[] = $value;
            }
        }
        foreach (['ROLE-OWNER_SUPER_ADMIN', 'ROLE-ADMINISTRATOR', 'ROLE-KEUANGAN', 'ROLE-OPERASIONAL', 'ROLE-PEMBACA', 'ROLE-UNKNOWN'] as $role) {
            $user = new LegacyUser(['role_id' => $role]);
            foreach ($actions as $action) {
                $this->assertSame(\RolePolicy::can($action, $role), (new RolePolicy)->allows($user, $action), $role.':'.$action);
            }
        }
    }
}
