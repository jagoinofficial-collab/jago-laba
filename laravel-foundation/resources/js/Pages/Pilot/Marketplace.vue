<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';

type Store = Record<string, any>;
type Dashboard = Record<string, any>;

const props = defineProps<{
    read_only: boolean;
    workspace: string;
    scope: { company_key: string; store_id: string | null };
    stores: Store[];
    active_store?: Store;
    filters: Record<string, string>;
    dashboard: Dashboard | null;
    query: Record<string, any>;
    message?: string;
}>();

const expenseForm = useForm({
    store_id: props.scope.store_id ?? '',
    description: '',
    amount: '',
    category: 'Biaya Iklan/Advertising Marketplace',
    vendor_name: 'Vendor',
    expense_date: new Date().toISOString().slice(0, 10),
    payment_method: 'Transfer Bank',
    account_id: '',
    notes: '',
    affects_profit: true,
    is_cashflow_only: false,
});

let expenseIdempotencyKey: string | null = null;

function loadStore(event: Event) {
    const store = (event.target as HTMLSelectElement).value;
    expenseForm.store_id = store;
    router.get('/pilot/marketplace', { store, period: props.filters.period, finance_mode: props.filters.finance_mode }, { preserveState: true, replace: true });
}

function submitExpense() {
    expenseIdempotencyKey ??= crypto.randomUUID();
    expenseForm.post('/pilot/marketplace/expenses', {
        preserveScroll: true,
        headers: {
            'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
            'X-Idempotency-Key': expenseIdempotencyKey,
        },
        onSuccess: () => {
            expenseForm.reset('description', 'amount', 'notes');
            expenseIdempotencyKey = null;
        },
    });
}

function value(value: unknown): string {
    return value === null || value === undefined ? '—' : new Intl.NumberFormat('id-ID').format(Number(value));
}
</script>

<template>
    <Head title="Pilot Marketplace · Read-only" />
    <main class="pilot-shell">
        <header class="pilot-header">
            <div>
                <div class="eyebrow">Jago Laba · Pilot read-only</div>
                <h1>Marketplace</h1>
                <p class="muted">Dashboard Laravel/Inertia dengan kontrak query dan agregasi legacy.</p>
            </div>
            <div>
                <span class="badge badge-read">● Read-only</span>
                <span class="badge badge-scope">Workspace: {{ workspace }}</span>
            </div>
        </header>

        <section class="panel toolbar">
            <div class="field">
                <label for="store">Store</label>
                <select id="store" :value="scope.store_id ?? ''" @change="loadStore">
                    <option value="" disabled>Pilih store</option>
                    <option v-for="store in stores" :key="store.id" :value="store.id">{{ store.store_name || store.id }}</option>
                </select>
            </div>
            <div class="field"><label>Period</label><input :value="filters.period || 'month'" readonly /></div>
            <div class="field"><label>Company</label><input :value="scope.company_key" readonly /></div>
        </section>

        <section v-if="message" class="panel">
            <h2>Store belum siap</h2>
            <p class="muted">{{ message }} Pilih store terhubung melalui alur pengaturan lama: {{ query.redirect }}.</p>
        </section>

        <template v-if="dashboard && active_store">
            <section class="panel" style="margin-bottom: 1rem">
                <div class="eyebrow">{{ active_store.platform }}</div>
                <h2>{{ active_store.store_name || active_store.id }}</h2>
                <p class="muted">{{ active_store.authorization_label }} · periode {{ dashboard.period.start }} sampai {{ dashboard.period.end }}</p>
            </section>

            <section class="grid grid-4" style="margin-bottom: 1rem">
                <div class="kpi"><small>Final sales</small><strong>{{ value(dashboard.groups.final.sales) }}</strong></div>
                <div class="kpi"><small>Final profit</small><strong>{{ value(dashboard.groups.final.profit) }}</strong></div>
                <div class="kpi"><small>Known subtotal</small><strong>{{ value(dashboard.known_subtotal) }}</strong></div>
                <div class="kpi"><small>Eligible orders</small><strong>{{ value(dashboard.eligible_orders) }}</strong></div>
            </section>

            <section class="panel expense-form" style="margin-bottom: 1rem">
                <div class="eyebrow">Write pilot</div>
                <h2>Catat pengeluaran Marketplace lokal</h2>
                <p class="muted">Scope aktif: {{ scope.company_key }} / {{ scope.store_id }}. Tidak memanggil provider Marketplace.</p>
                <form class="form-grid" @submit.prevent="submitExpense">
                    <div class="field field-wide">
                        <label for="expense-description">Deskripsi</label>
                        <input id="expense-description" v-model="expenseForm.description" required maxlength="255" placeholder="Contoh: biaya iklan marketplace" />
                        <small v-if="expenseForm.errors.description" class="error">{{ expenseForm.errors.description }}</small>
                    </div>
                    <div class="field">
                        <label for="expense-amount">Jumlah</label>
                        <input id="expense-amount" v-model="expenseForm.amount" required inputmode="decimal" placeholder="Rp 12.500" />
                        <small v-if="expenseForm.errors.amount" class="error">{{ expenseForm.errors.amount }}</small>
                    </div>
                    <div class="field">
                        <label for="expense-date">Tanggal</label>
                        <input id="expense-date" v-model="expenseForm.expense_date" required type="date" />
                    </div>
                    <div class="field">
                        <label for="expense-category">Kategori</label>
                        <input id="expense-category" v-model="expenseForm.category" required maxlength="100" />
                    </div>
                    <div class="field">
                        <label for="expense-vendor">Vendor</label>
                        <input id="expense-vendor" v-model="expenseForm.vendor_name" maxlength="150" />
                    </div>
                    <div class="field">
                        <label for="expense-method">Metode pembayaran</label>
                        <input id="expense-method" v-model="expenseForm.payment_method" required maxlength="50" />
                    </div>
                    <div class="field field-wide">
                        <label for="expense-notes">Catatan</label>
                        <input id="expense-notes" v-model="expenseForm.notes" placeholder="Opsional" />
                    </div>
                    <div class="checkbox-row field-wide">
                        <label><input v-model="expenseForm.affects_profit" type="checkbox" /> Mempengaruhi profit</label>
                        <label><input v-model="expenseForm.is_cashflow_only" type="checkbox" /> Hanya arus kas</label>
                    </div>
                    <div class="form-actions field-wide">
                        <small v-if="expenseForm.errors.store_id" class="error">{{ expenseForm.errors.store_id }}</small>
                        <small v-if="expenseForm.recentlySuccessful" class="success">Pengeluaran tersimpan.</small>
                        <button class="button" :disabled="expenseForm.processing" type="submit">{{ expenseForm.processing ? 'Menyimpan…' : 'Simpan pengeluaran' }}</button>
                    </div>
                </form>
            </section>

            <section class="grid grid-3">
                <div class="panel"><h2>Status perhatian</h2><dl class="data-list"><div><dt>Dibatalkan</dt><dd>{{ dashboard.attention.cancelled }}</dd></div><div><dt>Belum dibayar</dt><dd>{{ dashboard.attention.unpaid }}</dd></div><div><dt>Finance belum lengkap</dt><dd>{{ dashboard.attention.missing_finance }}</dd></div><div><dt>HPP belum lengkap</dt><dd>{{ dashboard.attention.missing_hpp }}</dd></div></dl></div>
                <div class="panel"><h2>Kelompok finance</h2><dl class="data-list"><div><dt>Final</dt><dd>{{ dashboard.groups.final.count }}</dd></div><div><dt>Estimated</dt><dd>{{ dashboard.groups.estimated.count }}</dd></div><div><dt>Unknown</dt><dd>{{ dashboard.groups.unknown.count }}</dd></div><div><dt>Cost coverage</dt><dd>{{ dashboard.cost_coverage_complete ? 'Lengkap' : 'Belum' }}</dd></div></dl></div>
                <div class="panel"><h2>Scope/query evidence</h2><dl class="data-list"><div><dt>Store</dt><dd>{{ scope.store_id }}</dd></div><div><dt>Order source</dt><dd>api</dd></div><div><dt>Order rows</dt><dd>{{ dashboard.rows.length }}</dd></div><div><dt>Expense rows</dt><dd>{{ dashboard.expenses.length }}</dd></div></dl></div>
            </section>
        </template>
    </main>
</template>
