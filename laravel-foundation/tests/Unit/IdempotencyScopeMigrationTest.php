<?php

namespace Tests\Unit;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class IdempotencyScopeMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('idempotency_keys', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('idempotency_key')->unique();
            $table->string('module')->nullable();
            $table->string('reference_id')->nullable();
            $table->text('response_json')->nullable();
            $table->dateTime('created_at')->nullable();
        });
        DB::table('idempotency_keys')->insert([
            'idempotency_key' => 'legacy-key',
            'module' => null,
            'reference_id' => 'legacy-1',
            'response_json' => '{}',
        ]);
    }

    public function test_upgrade_is_a_noop_when_legacy_table_is_absent(): void
    {
        Schema::drop('idempotency_keys');
        $migration = require base_path('database/migrations/2026_10_30_000003_scope_idempotency_keys.php');

        $migration->up();

        $this->assertFalse(Schema::hasTable('idempotency_keys'));
    }

    public function test_upgrade_backfills_legacy_rows_and_uses_composite_unique_scope(): void
    {
        $migration = require base_path('database/migrations/2026_10_30_000003_scope_idempotency_keys.php');
        $migration->up();

        $legacy = DB::table('idempotency_keys')->where('idempotency_key', 'legacy-key')->first();
        $this->assertSame('LEGACY-GLOBAL', $legacy->company_key);
        $this->assertSame('legacy', $legacy->module);
        $this->assertTrue(Schema::hasColumn('idempotency_keys', 'company_key'));
        $this->assertTrue(Schema::hasIndex('idempotency_keys', ['company_key', 'module', 'idempotency_key'], 'unique'));
        $this->assertFalse(Schema::hasIndex('idempotency_keys', ['idempotency_key'], 'unique'));

        DB::table('idempotency_keys')->insert([
            'company_key' => 'LOCAL-COMPANY',
            'module' => 'marketplace.expense',
            'idempotency_key' => 'same-key',
            'response_json' => '{}',
        ]);
        DB::table('idempotency_keys')->insert([
            'company_key' => 'OTHER-COMPANY',
            'module' => 'marketplace.expense',
            'idempotency_key' => 'same-key',
            'response_json' => '{}',
        ]);

        $this->expectException(QueryException::class);
        DB::table('idempotency_keys')->insert([
            'company_key' => 'LOCAL-COMPANY',
            'module' => 'marketplace.expense',
            'idempotency_key' => 'same-key',
            'response_json' => '{}',
        ]);
    }

    public function test_rollback_refuses_to_restore_global_uniqueness_when_scopes_collide(): void
    {
        $migration = require base_path('database/migrations/2026_10_30_000003_scope_idempotency_keys.php');
        $migration->up();
        DB::table('idempotency_keys')->insert([
            'company_key' => 'LOCAL-COMPANY',
            'module' => 'marketplace.expense',
            'idempotency_key' => 'rollback-collision',
            'response_json' => '{}',
        ]);
        DB::table('idempotency_keys')->insert([
            'company_key' => 'OTHER-COMPANY',
            'module' => 'marketplace.expense',
            'idempotency_key' => 'rollback-collision',
            'response_json' => '{}',
        ]);

        $this->expectException(\RuntimeException::class);
        $migration->down();
    }

    public function test_rollback_restores_legacy_unique_key_when_no_scoped_collision_exists(): void
    {
        $migration = require base_path('database/migrations/2026_10_30_000003_scope_idempotency_keys.php');
        $migration->up();

        $migration->down();

        $this->assertFalse(Schema::hasColumn('idempotency_keys', 'company_key'));
        $this->assertTrue(Schema::hasIndex('idempotency_keys', ['idempotency_key'], 'unique'));
        $this->assertFalse(Schema::hasIndex('idempotency_keys', ['company_key', 'module', 'idempotency_key'], 'unique'));
    }
}
