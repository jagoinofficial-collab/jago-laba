<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('idempotency_keys')) {
            return;
        }

        if (! Schema::hasColumn('idempotency_keys', 'company_key')) {
            Schema::table('idempotency_keys', function (Blueprint $table): void {
                $table->string('company_key', 120)->nullable()->after('id');
            });
        }

        if (! Schema::hasColumn('idempotency_keys', 'module')) {
            Schema::table('idempotency_keys', function (Blueprint $table): void {
                $table->string('module', 100)->nullable();
            });
        }

        // Legacy receipts have no reliable tenant context. Isolate them from
        // new scoped receipts rather than assigning them to a company.
        DB::table('idempotency_keys')
            ->where(function ($query): void {
                $query->whereNull('company_key')->orWhere('company_key', '');
            })
            ->update(['company_key' => 'LEGACY-GLOBAL']);
        DB::table('idempotency_keys')
            ->where(function ($query): void {
                $query->whereNull('module')->orWhere('module', '');
            })
            ->update(['module' => 'legacy']);

        foreach (Schema::getIndexes('idempotency_keys') as $index) {
            if ($index['unique'] && ! $index['primary'] && $index['columns'] === ['idempotency_key']) {
                Schema::table('idempotency_keys', function (Blueprint $table) use ($index): void {
                    $table->dropUnique($index['name']);
                });
            }
        }

        if (! Schema::hasIndex('idempotency_keys', ['company_key', 'module', 'idempotency_key'], 'unique')) {
            Schema::table('idempotency_keys', function (Blueprint $table): void {
                $table->unique(['company_key', 'module', 'idempotency_key'], 'uq_idempotency_scope');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('idempotency_keys')) {
            return;
        }

        $duplicates = DB::table('idempotency_keys')
            ->select('idempotency_key')
            ->groupBy('idempotency_key')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicates) {
            throw new RuntimeException('Idempotency scope tidak dapat di-rollback karena ada key yang dipakai lintas company/module.');
        }

        if (Schema::hasIndex('idempotency_keys', ['company_key', 'module', 'idempotency_key'], 'unique')) {
            Schema::table('idempotency_keys', function (Blueprint $table): void {
                $table->dropUnique('uq_idempotency_scope');
            });
        }

        if (! Schema::hasIndex('idempotency_keys', ['idempotency_key'], 'unique')) {
            Schema::table('idempotency_keys', function (Blueprint $table): void {
                $table->unique('idempotency_key');
            });
        }

        if (Schema::hasColumn('idempotency_keys', 'company_key')) {
            Schema::table('idempotency_keys', function (Blueprint $table): void {
                $table->dropColumn('company_key');
            });
        }
    }
};
