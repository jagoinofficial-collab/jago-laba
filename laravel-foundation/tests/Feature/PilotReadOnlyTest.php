<?php

namespace Tests\Feature;

use App\Pilots\Marketplace\MarketplacePilotReadService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class PilotReadOnlyTest extends TestCase
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
        Schema::create('marketplace_stores', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('company_key');
            $table->string('platform')->nullable();
            $table->string('store_name')->nullable();
            $table->string('store_identifier')->nullable();
            $table->string('authorization_status')->nullable();
            $table->timestamp('archived_at')->nullable();
        });
        Schema::create('marketplace_oauth_connections', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('store_id');
            $table->string('company_key');
            $table->string('status')->nullable();
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_successful_sync_at')->nullable();
        });
        Schema::create('marketplace_orders', function (Blueprint $table): void {
            $table->string('id');
            $table->string('store_id');
            $table->string('source_kind');
            $table->dateTime('order_date');
            $table->string('order_status')->nullable();
            $table->decimal('gross_sales', 18, 2)->nullable();
            $table->text('transaction_detail_json')->nullable();
            $table->string('internal_state')->nullable();
        });
        Schema::create('marketplace_expenses', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('store_id');
            $table->date('expense_date');
            $table->string('category')->nullable();
            $table->string('description')->nullable();
            $table->string('vendor_name')->nullable();
            $table->decimal('amount', 18, 2)->nullable();
            $table->string('payment_method')->nullable();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('affects_profit')->default(true);
            $table->boolean('is_cashflow_only')->default(false);
            $table->dateTime('created_at')->nullable();
        });
        Schema::create('idempotency_keys', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('company_key')->nullable();
            $table->string('idempotency_key');
            $table->string('module')->nullable();
            $table->string('reference_id')->nullable();
            $table->text('response_json')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->unique(['company_key', 'module', 'idempotency_key'], 'uq_idempotency_scope');
        });
        Schema::create('system_audit_logs', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('event_id');
            $table->dateTime('event_time');
            $table->string('user_id')->nullable();
            $table->string('username')->nullable();
            $table->string('module')->nullable();
            $table->string('action');
            $table->string('record_id')->nullable();
            $table->string('reference_id')->nullable();
            $table->text('before_json')->nullable();
            $table->text('after_json')->nullable();
            $table->string('status')->default('SUCCESS');
            $table->text('notes')->nullable();
        });
        Schema::create('workspace_user_access', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('user_key');
            $table->string('workspace');
        });
        Schema::create('system_table_map', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('api_key')->unique();
            $table->string('sheet_name')->nullable();
            $table->string('table_name');
        });
        Schema::create('system_settings', function (Blueprint $table): void {
            $table->string('setting_key')->primary();
            $table->text('setting_value')->nullable();
        });

        foreach (['bahan', 'produk', 'master_stok'] as $key) {
            $table = 'jl_data_'.$key;
            Schema::create($table, function (Blueprint $blueprint): void {
                $blueprint->bigIncrements('_row_id');
                $blueprint->unsignedInteger('_row_order')->default(0);
                $blueprint->text('_data_json');
            });
            DB::table('system_table_map')->insert([
                'api_key' => $key,
                'sheet_name' => $key,
                'table_name' => $table,
            ]);
        }

        DB::table('auth_users_v2')->insert([
            'username' => 'owner',
            'name' => 'Owner Pilot',
            'password_hash' => password_hash('owner-secret', PASSWORD_BCRYPT),
            'role_id' => 'ROLE-OWNER_SUPER_ADMIN',
            'status' => 'aktif',
        ]);
        DB::table('auth_users_v2')->insert([
            'username' => 'reader',
            'name' => 'Reader Pilot',
            'password_hash' => password_hash('reader-secret', PASSWORD_BCRYPT),
            'role_id' => 'ROLE-PEMBACA',
            'status' => 'aktif',
        ]);
        $readerId = (string) DB::table('auth_users_v2')->where('username', 'reader')->value('id');
        DB::table('workspace_user_access')->insert(['user_key' => $readerId, 'workspace' => 'marketplace']);
        DB::table('marketplace_stores')->insert([
            ['id' => 'store-a', 'company_key' => 'LOCAL-COMPANY', 'platform' => 'Shopee', 'store_name' => 'Toko A', 'store_identifier' => 'shop-a', 'authorization_status' => 'verified'],
            ['id' => 'store-foreign', 'company_key' => 'OTHER-COMPANY', 'platform' => 'Shopee', 'store_name' => 'Asing', 'store_identifier' => 'shop-x', 'authorization_status' => 'verified'],
        ]);
        DB::table('marketplace_oauth_connections')->insert([
            'store_id' => 'store-a',
            'company_key' => 'LOCAL-COMPANY',
            'status' => 'authorized',
            'authorized_at' => '2026-10-01 00:00:00',
            'verified_at' => '2026-10-01 00:00:00',
            'last_successful_sync_at' => '2026-10-30 08:00:00',
        ]);
        $this->insertMarketplaceFixture();
        DB::table('system_settings')->insert(['setting_key' => 'KONVEKSI_RESET_EPOCH', 'setting_value' => 'epoch-1']);
        DB::table('jl_data_bahan')->insert(['_row_order' => 1, '_data_json' => json_encode(['id' => 'B-1', 'nama' => 'Kain'])]);
        DB::table('jl_data_produk')->insert([
            ['_row_order' => 1, '_data_json' => json_encode(['id' => 'P-1', 'id_stok_item' => 'STOK-1'])],
            ['_row_order' => 2, '_data_json' => json_encode(['id' => 'P-2', 'id_stok_item' => 'STOK-1'])],
        ]);
        DB::table('jl_data_master_stok')->insert(['_row_order' => 1, '_data_json' => json_encode(['id' => 'STOK-1', 'stok_akhir' => -2])]);
    }

    public function test_marketplace_fixture_matches_legacy_dashboard_contract(): void
    {
        $orders = $this->fixtureOrders();
        $dashboard = MarketplacePilotReadService::aggregate($orders, []);

        $this->assertSame(128340.0, $dashboard['groups']['final']['sales']);
        $this->assertSame(24338.0, $dashboard['groups']['final']['fees']);
        $this->assertSame(45002.0, $dashboard['groups']['final']['profit']);
        $this->assertSame(80000.0, $dashboard['groups']['estimated']['net']);
        $this->assertNull($dashboard['groups']['unknown']['net']);
        $this->assertNull($dashboard['costs']['ads']);
        $this->assertNull($dashboard['net_profit']);
        $this->assertSame(1, $dashboard['attention']['cancelled']);
        $this->assertSame(1, $dashboard['attention']['unpaid']);
        $this->assertSame(2, $dashboard['subtotal_orders']);
    }

    public function test_marketplace_legacy_and_laravel_runtime_match_on_the_same_fixture(): void
    {
        $this->loginAndSelect('marketplace');
        $_SESSION['company_key'] = 'LOCAL-COMPANY';
        $filters = [
            'period' => 'custom',
            'date_start' => '2026-10-01',
            'date_end' => '2026-10-31',
            'finance_mode' => 'final',
        ];

        $legacyStarted = hrtime(true);
        $legacy = $this->legacyMarketplaceReport('store-a', $filters);
        $legacyElapsed = (hrtime(true) - $legacyStarted) / 1_000_000_000;

        $laravelStarted = hrtime(true);
        $response = $this->inertiaGet('/pilot/marketplace?store=store-a&period=custom&date_start=2026-10-01&date_end=2026-10-31&finance_mode=final');
        $laravelElapsed = (hrtime(true) - $laravelStarted) / 1_000_000_000;
        $response->assertStatus(200);

        $props = $response->json('props');
        $legacyComparable = $legacy;
        unset($legacyComparable['generated_at']);
        $laravelComparable = $props['dashboard'];
        unset($laravelComparable['generated_at'], $laravelComparable['finance_mode']);

        $this->assertSame(
            $this->normalizeParityValue($legacyComparable),
            $this->normalizeParityValue($laravelComparable),
            'Legacy and Laravel dashboard payloads diverged on the same fixture.'
        );
        $this->assertTrue($props['read_only']);
        $this->assertSame('marketplace', $props['workspace']);
        $this->assertSame(['company_key' => 'LOCAL-COMPANY', 'store_id' => 'store-a'], $props['scope']);
        $this->assertSame($filters, array_intersect_key($props['filters'], $filters));
        $this->assertSame($legacy['period'], $props['query']['period']);
        $this->assertSame('api', $props['query']['orders']['where']['source_kind']);
        $this->assertSame(['null', 'active'], $props['query']['orders']['where']['internal_state']);
        $this->assertSame($legacy['period']['start'], $props['query']['orders']['where']['order_date_gte']);
        $this->assertSame($legacy['period']['exclusive_end'], $props['query']['orders']['where']['order_date_lt']);
        $this->assertSame(['order_date', 'id'], $props['query']['orders']['order_by']);
        $this->assertSame(['expense_date', 'id'], $props['query']['expenses']['order_by']);
        $this->assertLessThan(2.0, $legacyElapsed, 'Legacy fixture read took unexpectedly long.');
        $this->assertLessThan(2.0, $laravelElapsed, 'Laravel/Inertia fixture read took unexpectedly long.');

        $legacyForeignDenied = false;
        try {
            $this->legacyMarketplaceReport('store-foreign', $filters);
        } catch (\DomainException) {
            $legacyForeignDenied = true;
        }
        $this->assertTrue($legacyForeignDenied, 'Legacy runtime did not reject a foreign-company store.');
        $this->inertiaGet('/pilot/marketplace?store=store-foreign')->assertForbidden();
    }

    public function test_marketplace_pilot_is_scoped_read_only_and_returns_inertia_contract(): void
    {
        $this->loginAndSelect('marketplace');
        $beforeOrders = DB::table('marketplace_orders')->count();
        $beforeExpenses = DB::table('marketplace_expenses')->count();
        $started = microtime(true);

        $response = $this->inertiaGet('/pilot/marketplace?store=store-a&period=custom&date_start=2026-10-01&date_end=2026-10-31&finance_mode=final');

        $elapsed = microtime(true) - $started;
        $response->assertOk()->assertJsonPath('props.read_only', true)
            ->assertJsonPath('props.scope.company_key', 'LOCAL-COMPANY')
            ->assertJsonPath('props.scope.store_id', 'store-a')
            ->assertJsonPath('props.dashboard.groups.final.sales', 128340)
            ->assertJsonPath('props.dashboard.groups.final.profit', 45002)
            ->assertJsonPath('props.query.orders.where.source_kind', 'api');
        $this->assertLessThan(2.0, $elapsed, 'Pilot read took unexpectedly long.');
        $this->assertSame($beforeOrders, DB::table('marketplace_orders')->count());
        $this->assertSame($beforeExpenses, DB::table('marketplace_expenses')->count());
    }

    public function test_marketplace_pilot_rejects_foreign_store(): void
    {
        $this->loginAndSelect('marketplace');

        $this->inertiaGet('/pilot/marketplace?store=store-foreign')->assertForbidden();
    }

    public function test_marketplace_expense_write_success_is_scoped_audited_and_idempotent(): void
    {
        $this->loginAndSelect('marketplace');

        $response = $this->postExpense(['description' => 'Biaya iklan pilot', 'amount' => 'Rp 12.500'], 'expense-success-key');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('expense_id', 1)
            ->assertJsonPath('duplicate', false)
            ->assertHeader('X-Request-Id');
        $this->assertDatabaseHas('marketplace_expenses', [
            'id' => 1,
            'store_id' => 'store-a',
            'description' => 'Biaya iklan pilot',
            'amount' => 12500,
            'affects_profit' => 0,
            'is_cashflow_only' => 0,
        ]);
        $this->assertDatabaseHas('system_audit_logs', ['action' => 'CREATE_EXPENSE', 'record_id' => '1']);
        $this->assertDatabaseHas('idempotency_keys', [
            'company_key' => 'LOCAL-COMPANY',
            'module' => 'marketplace.expense',
            'idempotency_key' => 'expense-success-key',
            'reference_id' => '1',
        ]);

        $duplicate = $this->postExpense(['description' => 'Biaya iklan pilot', 'amount' => 'Rp 12.500'], 'expense-success-key');
        $duplicate->assertOk()->assertJsonPath('duplicate', true)->assertJsonPath('expense_id', 1);

        $this->postExpense(['description' => 'Payload berbeda', 'amount' => 'Rp 12.500'], 'expense-success-key')
            ->assertStatus(409)
            ->assertJsonPath('code', 'IDEMPOTENCY_CONFLICT');
        $this->assertSame(1, DB::table('marketplace_expenses')->count());
        $this->assertSame(1, DB::table('system_audit_logs')->count());
    }

    public function test_marketplace_expense_idempotency_is_scoped_by_company_and_module(): void
    {
        $this->loginAndSelect('marketplace');

        DB::table('idempotency_keys')->insert([
            'company_key' => 'LOCAL-COMPANY',
            'module' => 'marketplace.sync',
            'idempotency_key' => 'shared-module-key',
            'reference_id' => 'sync-1',
            'response_json' => json_encode(['request_fingerprint' => 'sync-fingerprint']),
            'created_at' => now(),
        ]);
        DB::table('idempotency_keys')->insert([
            'company_key' => 'OTHER-COMPANY',
            'module' => 'marketplace.expense',
            'idempotency_key' => 'shared-company-key',
            'reference_id' => 'other-expense-1',
            'response_json' => json_encode(['request_fingerprint' => 'other-fingerprint']),
            'created_at' => now(),
        ]);

        $sameModuleKey = $this->postExpense([
            'description' => 'Module scope tetap terpisah',
            'amount' => '1000',
        ], 'shared-module-key');
        $sameModuleKey->assertOk()
            ->assertJsonPath('duplicate', false)
            ->assertJsonPath('expense_id', 1);

        $sameCompanyKey = $this->postExpense([
            'description' => 'Company scope tetap terpisah',
            'amount' => '2000',
        ], 'shared-company-key');
        $sameCompanyKey->assertOk()
            ->assertJsonPath('duplicate', false)
            ->assertJsonPath('expense_id', 2);

        $this->assertSame(2, DB::table('marketplace_expenses')->count());
        $this->assertSame(4, DB::table('idempotency_keys')->count());
        $this->assertSame(2, DB::table('idempotency_keys')
            ->where('company_key', 'LOCAL-COMPANY')
            ->where('module', 'marketplace.expense')
            ->count());
    }

    public function test_marketplace_expense_write_validation_and_role_are_rejected(): void
    {
        $this->loginAndSelect('marketplace');

        $this->from('/pilot/marketplace')
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Requested-With' => 'XMLHttpRequest',
                'X-CSRF-TOKEN' => csrf_token(),
                'X-Idempotency-Key' => 'expense-inertia-invalid-key',
            ])
            ->post('/pilot/marketplace/expenses', [
                'store_id' => 'store-a',
                'description' => '',
                'amount' => '0',
            ])
            ->assertStatus(303)
            ->assertRedirect('/pilot/marketplace')
            ->assertSessionHasErrors(['description', 'amount']);

        $this->postExpense(['description' => '', 'amount' => '0'], 'expense-invalid-key')
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->loginAndSelect('marketplace', 'reader', 'reader-secret');
        $this->postExpense(['description' => 'Reader cannot write', 'amount' => '1000'], 'expense-reader-key')
            ->assertForbidden()
            ->assertJsonPath('code', 'ROLE_FORBIDDEN');
        $this->assertSame(0, DB::table('marketplace_expenses')->count());
    }

    public function test_marketplace_expense_write_enforces_csrf_store_scope_and_legacy_action_contract(): void
    {
        $this->loginAndSelect('marketplace');

        $this->withHeader('X-CSRF-TOKEN', 'wrong-token')
            ->postJson('/api/marketplace-ajax.php?action=add_expense', [
                'store_id' => 'store-a',
                'description' => 'CSRF failure',
                'amount' => '1000',
            ])
            ->assertStatus(419);

        $this->postExpense(['store_id' => 'store-foreign', 'description' => 'Foreign store', 'amount' => '1000'], 'expense-foreign-key')
            ->assertForbidden()
            ->assertJsonPath('code', 'STORE_FORBIDDEN');

        $this->withHeader('X-CSRF-TOKEN', csrf_token())
            ->postJson('/api/marketplace-ajax.php?action=delete_expense', ['store_id' => 'store-a'])
            ->assertStatus(503);

        $this->assertSame(0, DB::table('marketplace_expenses')->count());
    }

    public function test_marketplace_expense_write_rolls_back_on_database_failure(): void
    {
        $this->loginAndSelect('marketplace');
        Schema::drop('marketplace_expenses');

        $this->postExpense(['description' => 'Database failure', 'amount' => '1000'], 'expense-failure-key')
            ->assertStatus(500)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'WRITE_FAILED');
        $this->assertSame(0, DB::table('idempotency_keys')->count());
        $this->assertSame(0, DB::table('system_audit_logs')->count());
    }

    public function test_marketplace_expense_inertia_redirect_refreshes_the_dashboard(): void
    {
        $this->loginAndSelect('marketplace');

        $this->withHeaders(['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'])
            ->withHeader('X-CSRF-TOKEN', csrf_token())
            ->withHeader('X-Idempotency-Key', 'expense-inertia-key')
            ->post('/pilot/marketplace/expenses', [
                'store_id' => 'store-a',
                'description' => 'Refresh dashboard',
                'amount' => '2500',
                'affects_profit' => true,
            ])
            ->assertStatus(303)
            ->assertRedirect('/pilot/marketplace?store=store-a');

        $this->inertiaGet('/pilot/marketplace?store=store-a&period=custom&date_start=2026-10-01&date_end=2026-10-31')
            ->assertOk()
            ->assertJsonCount(1, 'props.dashboard.expenses');
    }

    public function test_legacy_marketplace_dashboard_url_forwards_without_writing(): void
    {
        $this->loginAndSelect('marketplace');

        $this->get('/marketplace.php?tab=dashboard&store=store-a&period=month')
            ->assertStatus(303)
            ->assertRedirect('/pilot/marketplace?store=store-a&tab=dashboard&period=month');
    }

    public function test_konveksi_pilot_preserves_load_semua_data_shape_without_writes(): void
    {
        $this->loginAndSelect('konveksi');
        $beforeMap = DB::table('system_table_map')->count();
        $beforeBahan = DB::table('jl_data_bahan')->count();

        $response = $this->inertiaGet('/pilot/konveksi?tab=dashboard');

        $response->assertOk()->assertJsonPath('props.read_only', true)
            ->assertJsonPath('props.workspace', 'konveksi')
            ->assertJsonPath('props.scope.company_key', 'LOCAL-COMPANY')
            ->assertJsonPath('props.data.bahan.0.nama', 'Kain')
            ->assertJsonPath('props.data.__meta.domain', 'konveksi')
            ->assertJsonPath('props.data.__meta.health.missing_stock_ids', 0)
            ->assertJsonPath('props.data.__meta.health.duplicate_stock_ids', 1)
            ->assertJsonPath('props.data.__meta.health.negative_stock', 1)
            ->assertJsonPath('props.query.method', 'loadSemuaData');
        $this->assertSame($beforeMap, DB::table('system_table_map')->count());
        $this->assertSame($beforeBahan, DB::table('jl_data_bahan')->count());
    }

    public function test_konveksi_legacy_and_laravel_runtime_match_on_the_same_fixture(): void
    {
        $this->loginAndSelect('konveksi');
        $_SESSION['company_key'] = 'LOCAL-COMPANY';

        $legacyStarted = hrtime(true);
        $legacy = $this->legacyKonveksiData();
        $legacyElapsed = (hrtime(true) - $legacyStarted) / 1_000_000_000;

        $laravelStarted = hrtime(true);
        $response = $this->inertiaGet('/pilot/konveksi?tab=dashboard');
        $laravelElapsed = (hrtime(true) - $laravelStarted) / 1_000_000_000;
        $response->assertStatus(200);

        $props = $response->json('props');
        $legacyComparable = $this->withoutVolatileKonveksiMeta($legacy);
        $laravelComparable = $this->withoutVolatileKonveksiMeta($props['data']);

        $this->assertSame($legacyComparable, $laravelComparable, 'Legacy and Laravel Konveksi payloads diverged on the same fixture.');
        $this->assertSame($legacyComparable, $this->withoutVolatileKonveksiMeta($props['collections']));
        $this->assertTrue($props['read_only']);
        $this->assertSame('konveksi', $props['workspace']);
        $this->assertSame('LOCAL-COMPANY', $props['scope']['company_key']);
        $this->assertSame('dashboard', $props['tab']);
        $this->assertSame(\KonveksiCollectionStore::COLLECTIONS, $props['query']['tables']);
        $this->assertSame(['_row_order', '_row_id'], $props['query']['order_by']);
        $this->assertSame('loadSemuaData', $props['query']['method']);
        $this->assertLessThan(2.0, $legacyElapsed, 'Legacy Konveksi fixture read took unexpectedly long.');
        $this->assertLessThan(2.0, $laravelElapsed, 'Laravel/Inertia Konveksi fixture read took unexpectedly long.');
    }

    public function test_legacy_konveksi_dashboard_url_forwards_without_writing(): void
    {
        $this->loginAndSelect('konveksi');

        $this->get('/index.php?tab=dashboard')
            ->assertStatus(303)
            ->assertRedirect('/pilot/konveksi?tab=dashboard');
    }

    public function test_legacy_konveksi_read_rpc_uses_the_new_adapter_shape(): void
    {
        $this->loginAndSelect('konveksi');

        $this->withHeader('X-CSRF-TOKEN', csrf_token())
            ->postJson('/api/rpc.php', ['method' => 'loadSemuaData', 'args' => []])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('result.bahan.0.nama', 'Kain')
            ->assertJsonPath('result.__meta.domain', 'konveksi');
    }

    public function test_pilot_routes_require_the_selected_workspace(): void
    {
        $this->postJson('/api/rpc.php', ['method' => 'prosesLoginBackend', 'args' => ['owner', 'owner-secret']])->assertOk();

        $this->inertiaGet('/pilot/marketplace?store=store-a')->assertForbidden();
        $this->inertiaGet('/pilot/konveksi')->assertForbidden();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fixtureOrders(): array
    {
        return [
            $this->order('FINAL', ['gross_amount' => 225000, 'finance_revenue' => 128340, 'net_income' => 104002, 'total_fees' => 120998, 'total_cogs' => 59000, 'is_estimated' => false]),
            $this->order('EST', ['gross_amount' => 100000, 'net_income' => 80000, 'total_cogs' => 45000, 'is_estimated' => true], 'IN_TRANSIT'),
            $this->order('UNKNOWN', ['gross_amount' => 50000, 'net_income' => null, 'total_cogs' => null], 'AWAITING_SHIPMENT'),
            $this->order('CANCEL', ['net_income' => 0, 'total_cogs' => 59000], 'CANCELLED'),
            $this->order('UNPAID', [], 'UNPAID'),
        ];
    }

    /**
     * Execute the actual legacy Marketplace read service against this test's
     * PDO. This intentionally avoids booting the legacy provider or touching
     * the live MySQL connection.
     *
     * @return array<string, mixed>
     */
    private function legacyMarketplaceReport(string $storeId, array $filters): array
    {
        $root = dirname(__DIR__, 3);
        require_once $root.'/app/Modules/Marketplace/Services/MarketplaceDashboardService.php';

        return (new \MarketplaceDashboardService(DB::connection()->getPdo()))->report($storeId, $filters);
    }

    /**
     * Execute the actual legacy Konveksi collection read against this test's
     * PDO. Only allowlisted collection reads are exercised here.
     *
     * @return array<string, mixed>
     */
    private function legacyKonveksiData(): array
    {
        $root = dirname(__DIR__, 3);
        require_once $root.'/app/Core/Database/Database.php';
        require_once $root.'/app/Shared/Support/SnapshotImporter.php';
        require_once $root.'/app/Shared/Support/CollectionStore.php';
        require_once $root.'/app/Modules/Konveksi/Services/KonveksiDatabase.php';
        require_once $root.'/app/Modules/Konveksi/Repositories/KonveksiCollectionStore.php';

        // The isolated-database flag is a scope fact. Mirror the legacy
        // deployment flag in this isolated fixture before reading Laravel.
        $legacyConfig = \KonveksiDatabase::config();
        config()->set('jago.konveksi_database_isolated', (bool) ($legacyConfig['isolated'] ?? false));
        \KonveksiDatabase::assertCompany();

        return (new \KonveksiCollectionStore(DB::connection()->getPdo()))->allKnown();
    }

    /**
     * `loaded_at` is intentionally generated by each runtime and is not a
     * parity field. All data, checksums and health values remain comparable.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withoutVolatileKonveksiMeta(array $data): array
    {
        unset($data['__meta']['loaded_at']);

        return $data;
    }

    private function normalizeParityValue(mixed $value): mixed
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->normalizeParityValue($item);
            }

            return $value;
        }

        return is_int($value) || is_float($value) ? (float) $value : $value;
    }

    private function insertMarketplaceFixture(): void
    {
        foreach ($this->fixtureOrders() as $order) {
            DB::table('marketplace_orders')->insert([
                'id' => $order['id'],
                'store_id' => 'store-a',
                'source_kind' => 'api',
                'order_date' => $order['order_date'],
                'order_status' => $order['order_status'],
                'gross_sales' => $order['gross_sales'],
                'transaction_detail_json' => $order['transaction_detail_json'],
                'internal_state' => 'active',
            ]);
        }
        DB::table('marketplace_orders')->insert([
            'id' => 'MANUAL', 'store_id' => 'store-a', 'source_kind' => 'manual', 'order_date' => '2026-10-15 12:00:00',
            'order_status' => 'COMPLETED', 'gross_sales' => 999999, 'transaction_detail_json' => '{}', 'internal_state' => 'active',
        ]);
    }

    private function order(string $id, array $snapshot, string $status = 'COMPLETED'): array
    {
        return [
            'id' => $id,
            'source_kind' => 'api',
            'order_date' => '2026-10-15 12:00:00',
            'order_status' => $status,
            'gross_sales' => 999999,
            'transaction_detail_json' => json_encode($snapshot + ['source_status' => $status]),
        ];
    }

    private function loginAndSelect(string $workspace, string $username = 'owner', string $password = 'owner-secret'): void
    {
        $this->postJson('/api/rpc.php', ['method' => 'prosesLoginBackend', 'args' => [$username, $password]])
            ->assertOk()->assertJsonPath('result.status', 'success');
        $this->withHeader('X-CSRF-TOKEN', csrf_token())
            ->postJson('/api/workspace.php', ['workspace' => $workspace])
            ->assertOk()->assertJsonPath('workspace', $workspace);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postExpense(array $payload, string $idempotencyKey): TestResponse
    {
        return $this->withHeader('X-CSRF-TOKEN', csrf_token())
            ->withHeader('X-Idempotency-Key', $idempotencyKey)
            ->postJson('/api/marketplace-ajax.php?action=add_expense', array_merge([
                'store_id' => 'store-a',
                'category' => 'Iklan',
            ], $payload));
    }

    private function inertiaGet(string $uri): TestResponse
    {
        $headers = [
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'text/html, application/xhtml+xml',
        ];
        $manifest = public_path('build/manifest.json');
        if (is_file($manifest)) {
            $headers['X-Inertia-Version'] = hash_file('xxh128', $manifest);
        }

        return $this->withHeaders($headers)->get($uri);
    }
}
