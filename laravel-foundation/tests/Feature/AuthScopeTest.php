<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class AuthScopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('auth_users_v2', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('username')->unique();
            $table->string('name')->nullable();
            $table->text('password_hash');
            $table->string('role_id')->nullable();
            $table->string('status')->default('aktif');
            $table->timestamps();
        });

        Schema::create('system_users', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('username')->unique();
            $table->string('name')->nullable();
            $table->text('password_hash');
            $table->string('role_id')->nullable();
            $table->string('status')->default('aktif');
            $table->timestamps();
        });

        Schema::create('marketplace_stores', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('company_key');
            $table->timestamp('archived_at')->nullable();
            $table->string('authorization_status')->nullable();
        });

        DB::table('auth_users_v2')->insert([
            'username' => 'owner',
            'name' => 'Owner Test',
            'password_hash' => password_hash('owner-secret', PASSWORD_BCRYPT),
            'role_id' => 'ROLE-OWNER_SUPER_ADMIN',
            'status' => 'aktif',
        ]);

        DB::table('auth_users_v2')->insert([
            'username' => 'reader',
            'name' => 'Reader Test',
            'password_hash' => password_hash('reader-secret', PASSWORD_BCRYPT),
            'role_id' => 'ROLE-PEMBACA',
            'status' => 'active',
        ]);

        DB::table('system_users')->insert([
            'username' => 'legacy-system',
            'name' => 'System Legacy',
            'password_hash' => password_hash('system-secret', PASSWORD_BCRYPT),
            'role_id' => 'ROLE-OPERASIONAL',
            'status' => 'aktif',
        ]);

        DB::table('marketplace_stores')->insert([
            ['id' => 'store-local', 'company_key' => 'LOCAL-COMPANY', 'archived_at' => null, 'authorization_status' => 'connected'],
            ['id' => 'store-foreign', 'company_key' => 'OTHER-COMPANY', 'archived_at' => null, 'authorization_status' => 'connected'],
        ]);

        Route::middleware(['web', 'auth', 'company.context'])->get('/_test/company-context', function (Request $request) {
            return response()->json(['company_key' => $request->attributes->get('company_key')]);
        });

        Route::middleware(['web', 'auth', 'company.context', 'store.context:store_id'])
            ->get('/_test/stores/{store_id}', function (Request $request) {
                return response()->json(['store_id' => $request->attributes->get('store_id')]);
            });

        Route::middleware(['web', 'auth', 'role.action:finance.manage'])
            ->get('/_test/finance', fn () => response()->json(['ok' => true]));

        Route::middleware(['web', 'auth', 'workspace.context:marketplace'])
            ->get('/_test/marketplace', fn () => response()->json(['ok' => true]));
    }

    public function test_valid_legacy_user_is_authenticated_through_old_rpc_contract(): void
    {
        $response = $this->postJson('/api/rpc.php', [
            'method' => 'prosesLoginBackend',
            'args' => ['OWNER', 'owner-secret'],
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('result.status', 'success')
            ->assertJsonPath('result.auth_source', 'auth_users_v2');

        $this->getJson('/auth/me')
            ->assertOk()
            ->assertJsonPath('user.username', 'owner')
            ->assertJsonPath('context.allowed_workspaces.0', 'konveksi')
            ->assertJsonPath('context.allowed_workspaces.1', 'marketplace');
    }

    public function test_system_users_password_hash_is_supported_as_fallback(): void
    {
        $this->postJson('/api/rpc.php', [
            'method' => 'prosesLoginBackend',
            'args' => ['legacy-system', 'system-secret'],
        ])
            ->assertOk()
            ->assertJsonPath('result.status', 'success')
            ->assertJsonPath('result.auth_source', 'system_users');
    }

    public function test_expired_session_is_rejected(): void
    {
        $this->getJson('/auth/me')->assertStatus(401);
    }

    public function test_wrong_role_is_rejected_by_action_policy(): void
    {
        $this->loginAs('reader', 'reader-secret');

        $this->getJson('/_test/finance')
            ->assertStatus(403)
            ->assertJsonPath('code', 'ROLE_FORBIDDEN');
    }

    public function test_foreign_company_context_is_rejected(): void
    {
        $this->loginAs('owner', 'owner-secret');

        $this->getJson('/_test/company-context?company_key=OTHER-COMPANY')
            ->assertStatus(403)
            ->assertJsonPath('code', 'COMPANY_FORBIDDEN');
    }

    public function test_foreign_store_is_rejected(): void
    {
        $this->loginAs('owner', 'owner-secret');

        $this->getJson('/_test/stores/store-foreign')
            ->assertStatus(403)
            ->assertJsonPath('code', 'STORE_FORBIDDEN');

        $this->getJson('/_test/stores/store-local')
            ->assertOk()
            ->assertJsonPath('store_id', 'store-local');
    }

    public function test_invalid_csrf_is_rejected_for_authenticated_legacy_rpc(): void
    {
        $this->loginAs('owner', 'owner-secret');

        $this->withHeader('X-CSRF-TOKEN', 'invalid-token')
            ->postJson('/api/rpc.php', ['method' => 'loadSemuaData', 'args' => []])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'CSRF_INVALID');
    }

    public function test_workspace_can_be_switched_and_is_required_by_scope_middleware(): void
    {
        $this->loginAs('owner', 'owner-secret');
        $token = csrf_token();

        $this->withHeader('X-CSRF-TOKEN', $token)
            ->postJson('/api/workspace.php', ['workspace' => 'marketplace'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('workspace', 'marketplace');

        $this->getJson('/_test/marketplace')->assertOk();

        $this->withHeader('X-CSRF-TOKEN', $token)
            ->postJson('/api/workspace.php', ['workspace' => 'konveksi'])
            ->assertOk()
            ->assertJsonPath('workspace', 'konveksi');

        $this->getJson('/_test/marketplace')
            ->assertStatus(403)
            ->assertJsonPath('code', 'WORKSPACE_NOT_ACTIVE');

        $this->getJson('/auth/me')
            ->assertOk()
            ->assertJsonPath('context.workspace', 'konveksi');
    }

    private function loginAs(string $username, string $password): void
    {
        $this->postJson('/api/rpc.php', [
            'method' => 'prosesLoginBackend',
            'args' => [$username, $password],
        ])->assertOk()->assertJsonPath('result.status', 'success');
    }
}
