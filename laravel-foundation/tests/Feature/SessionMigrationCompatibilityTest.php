<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class SessionMigrationCompatibilityTest extends TestCase
{
    public function test_sessions_can_be_added_and_rolled_back_without_touching_legacy_users(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->string('legacy_id')->primary();
            $table->string('legacy_marker');
        });
        DB::table('users')->insert(['legacy_id' => 'legacy-user', 'legacy_marker' => 'preserve']);
        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
        });
        DB::table('sessions')->insert(['id' => 'already-owned-session']);

        $migration = require database_path('migrations/2026_11_01_000004_create_laravel_sessions_table.php');
        $migration->up();
        DB::table('laravel_sessions')->insert([
            'id' => 'staging-session',
            'user_id' => 123,
            'payload' => base64_encode('session-round-trip'),
            'last_activity' => 1,
        ]);

        $this->assertSame(base64_encode('session-round-trip'), DB::table('laravel_sessions')->value('payload'));
        $this->assertSame(1, DB::table('users')->count());
        $this->assertSame('preserve', DB::table('users')->value('legacy_marker'));

        $migration->down();

        $this->assertFalse(Schema::hasTable('laravel_sessions'));
        $this->assertSame('already-owned-session', DB::table('sessions')->value('id'));
        $this->assertSame(['legacy_id', 'legacy_marker'], Schema::getColumnListing('users'));
        $this->assertSame('preserve', DB::table('users')->value('legacy_marker'));
    }
}
