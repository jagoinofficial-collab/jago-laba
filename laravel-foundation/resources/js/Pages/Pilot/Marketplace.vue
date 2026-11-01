<script setup lang="ts">
import { computed } from 'vue';
import WorkspaceNavigation from '../../Components/WorkspaceNavigation.vue';
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

const periodOptions = [
    { value: 'today', label: 'Hari ini' },
    { value: 'week', label: '7 hari terakhir' },
    { value: 'month', label: 'Bulan ini' },
    { value: 'last_month', label: 'Bulan lalu' },
    { value: 'custom', label: 'Rentang tanggal' },
];

const finalGroup = computed(() => props.dashboard?.groups?.final ?? null);
const estimatedGroup = computed(() => props.dashboard?.groups?.estimated ?? null);
const unknownGroup = computed(() => props.dashboard?.groups?.unknown ?? null);
const attention = computed(() => props.dashboard?.attention ?? {});
const costs = computed(() => props.dashboard?.costs ?? {});
const daily = computed(() => (props.dashboard?.daily ?? []) as Array<Record<string, any>>);
const hasDashboard = computed(() => Boolean(props.dashboard && props.active_store));

const kpis = computed(() => [
    { label: 'Omzet tercatat', value: finalGroup.value?.sales, tone: 'blue', hint: 'Pesanan final' },
    { label: 'Laba setelah HPP', value: finalGroup.value?.profit, tone: 'green', hint: 'Hanya bila HPP lengkap' },
    { label: 'Pesanan final', value: finalGroup.value?.count, tone: 'indigo', kind: 'count', hint: 'Scope store aktif' },
    { label: 'Margin profit', value: margin(finalGroup.value?.profit, finalGroup.value?.sales), tone: 'violet', kind: 'percent', hint: 'Belum termasuk biaya di luar sumber' },
    { label: 'HPP produk', value: finalGroup.value?.hpp, tone: 'orange', hint: 'HPP tersedia' },
    { label: 'Fee marketplace', value: finalGroup.value?.fees, tone: 'pink', hint: 'Omzet dikurangi net' },
    { label: 'Biaya iklan', value: costs.value.ads, tone: 'amber', hint: 'Dari pengeluaran tercatat' },
    { label: 'Biaya operasional', value: costs.value.operational, tone: 'slate', hint: 'Dari pengeluaran tercatat' },
]);

const attentionCards = computed(() => [
    { label: 'Transaksi dibatalkan', value: attention.value.cancelled, tone: 'danger' },
    { label: 'Belum dibayar', value: attention.value.unpaid, tone: 'purple' },
    { label: 'Finance belum lengkap', value: attention.value.missing_finance, tone: 'amber' },
    { label: 'HPP belum lengkap', value: attention.value.missing_hpp, tone: 'blue' },
    { label: 'Perlu rekonsiliasi', value: attention.value.unreconciled, tone: 'slate' },
]);

const funnel = computed(() => [
    { label: 'Omzet', value: finalGroup.value?.sales, note: 'Pesanan final' },
    { label: 'Net marketplace', value: finalGroup.value?.net, note: 'Setelah fee' },
    { label: 'HPP produk', value: finalGroup.value?.hpp, note: 'Harga pokok tersedia' },
    { label: 'Biaya iklan', value: costs.value.ads, note: 'Pengeluaran tercatat' },
    { label: 'Operasional', value: costs.value.operational, note: 'Pengeluaran tercatat' },
    { label: 'Laba setelah HPP', value: finalGroup.value?.profit, note: 'Belum dikurangi biaya eksternal' },
]);

function loadDashboard(event: Event) {
    const form = event.target as HTMLFormElement;
    const values = new FormData(form);
    const params: Record<string, string> = {
        store: String(values.get('store') ?? ''),
        period: String(values.get('period') ?? 'month'),
        finance_mode: String(values.get('finance_mode') ?? 'final'),
    };

    if (params.period === 'custom') {
        params.date_start = String(values.get('date_start') ?? '');
        params.date_end = String(values.get('date_end') ?? '');
    }

    expenseForm.store_id = params.store;
    router.get('/pilot/marketplace', params, { preserveState: true, preserveScroll: true, replace: true });
}

function loadStore(event: Event) {
    loadDashboard(event);
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
function displayValue(value: unknown, kind: string = 'currency'): string {
    if (value === null || value === undefined || value === '' || Number.isNaN(Number(value))) {
        return 'Belum tersedia';
    }

    const number = Number(value);
    if (kind === 'count') {
        return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(number);
    }
    if (kind === 'percent') {
        return `${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 }).format(number)}%`;
    }

    return `Rp ${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(number)}`;
}

function margin(profit: unknown, sales: unknown): number | null {
    const profitNumber = Number(profit);
    const salesNumber = Number(sales);
    return Number.isFinite(profitNumber) && Number.isFinite(salesNumber) && salesNumber !== 0
        ? (profitNumber / salesNumber) * 100
        : null;
}

function dateLabel(date: unknown): string {
    if (!date) return '—';
    const parsed = new Date(`${String(date).slice(0, 10)}T00:00:00+07:00`);
    return Number.isNaN(parsed.valueOf())
        ? String(date)
        : new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short' }).format(parsed);
}

function barWidth(valueToMeasure: unknown, values: Array<Record<string, any>>, key: string): string {
    const number = Number(valueToMeasure);
    const max = Math.max(...values.map((item) => Number(item[key] ?? 0)), 1);
    return `${Math.max(4, Math.round((number / max) * 100))}%`;
}
</script>

<template>
    <WorkspaceNavigation workspace="marketplace" :store-id="scope.store_id" />
    <Head title="Pilot Marketplace · Read-only" />
    <main class="marketplace-app">
        <aside class="marketplace-sidebar" aria-label="Navigasi Marketplace">
            <div class="brand-lockup"><span class="brand-mark">JL</span><span><strong>JagoLaba</strong><small>MARKETPLACE</small></span></div>
            <div class="workspace-label">Ruang kerja aktif</div>
            <div class="workspace-switcher"><span class="workspace-dot"></span><strong>Marketplace</strong><span class="muted">{{ workspace }}</span></div>
            <nav class="sidebar-nav">
                <a class="active" href="#dashboard">Dashboard proyek/toko</a>
                <a href="#orders">Transaksi</a><a href="#expenses">Pengeluaran</a><a href="#finance">Pemasukan</a><a href="#reports">Analitik &amp; laporan</a><a href="#settings">Pengaturan</a>
            </nav>
            <div class="sidebar-footer"><span class="sidebar-status"><span class="status-dot"></span> Data operasional toko aktif</span><span class="muted">Company: {{ scope.company_key }}</span></div>
        </aside>

        <section class="marketplace-content" id="dashboard">
            <header class="marketplace-topbar"><div><div class="eyebrow">Marketplace / Ikhtisar</div><h1>Dashboard proyek/toko</h1><p class="muted">Ringkasan keuangan dan operasional berdasarkan data yang tersedia.</p></div><div class="topbar-actions"><span class="badge badge-read"><span class="status-dot"></span> Read-only pilot</span><span class="badge badge-scope">{{ active_store?.platform || 'Marketplace' }}</span></div></header>

            <form class="panel filter-bar" @submit.prevent="loadDashboard">
                <div class="field filter-store"><label for="dashboard-store">Store</label><select id="dashboard-store" name="store" :value="scope.store_id ?? ''"><option value="" disabled>Pilih store</option><option v-for="store in stores" :key="store.id" :value="store.id">{{ store.store_name || store.id }}</option></select></div>
                <div class="field"><label for="dashboard-period">Periode</label><select id="dashboard-period" name="period" :value="filters.period || 'month'"><option v-for="period in periodOptions" :key="period.value" :value="period.value">{{ period.label }}</option></select></div>
                <div class="field"><label for="dashboard-finance">Mode finance</label><select id="dashboard-finance" name="finance_mode" :value="filters.finance_mode || 'final'"><option value="final">Final</option><option value="estimated">Final + estimasi</option></select></div>
                <div v-if="filters.period === 'custom'" class="field"><label for="date-start">Mulai</label><input id="date-start" name="date_start" type="date" :value="filters.date_start || ''" required /></div>
                <div v-if="filters.period === 'custom'" class="field"><label for="date-end">Sampai</label><input id="date-end" name="date_end" type="date" :value="filters.date_end || ''" required /></div>
                <button class="button button-primary" type="submit">Terapkan</button>
            </form>

            <section v-if="message" class="panel empty-state"><span class="empty-state-kicker">Store belum siap</span><h2>Hubungkan store Marketplace terlebih dahulu</h2><p class="muted">{{ message }} Pilih store terhubung melalui alur pengaturan lama: {{ query.redirect }}.</p></section>

            <template v-if="hasDashboard">
                <section class="store-hero panel"><div><span class="eyebrow">{{ active_store?.platform }} · {{ active_store?.authorization_label }}</span><h2>{{ active_store?.store_name || active_store?.id }}</h2><p class="muted">Periode {{ dashboard?.period.start }} sampai {{ dashboard?.period.end }} · sumber order API</p></div><div class="store-hero-meta"><span class="status-dot"></span><span>Data terukur</span></div></section>

                <section class="kpi-grid" aria-label="Indikator utama"><article v-for="kpi in kpis" :key="kpi.label" class="metric-card" :class="`metric-${kpi.tone}`"><span class="metric-label">{{ kpi.label }}</span><strong>{{ displayValue(kpi.value, kpi.kind || 'currency') }}</strong><small>{{ kpi.hint }}</small></article></section>

                <section class="panel finance-panel" id="finance"><div class="section-heading"><div><span class="eyebrow">Rekonsiliasi nilai</span><h2>Profit funnel</h2><p class="muted">Nilai dihitung dari snapshot finance legacy yang tersedia.</p></div><span class="coverage-badge" :class="dashboard?.cost_coverage_complete ? 'is-complete' : 'is-partial'">{{ dashboard?.cost_coverage_complete ? 'Lengkap' : 'Sebagian tersedia' }}</span></div><div class="funnel-grid"><article v-for="item in funnel" :key="item.label" class="funnel-step"><span class="funnel-label">{{ item.label }}</span><strong>{{ displayValue(item.value) }}</strong><small>{{ item.note }}</small></article></div></section>

                <section class="dashboard-grid dashboard-grid-wide"><article class="panel attention-panel"><div class="section-heading compact"><div><span class="eyebrow">Data quality</span><h2>Perlu perhatian</h2></div><span class="muted">{{ dashboard?.eligible_orders ?? 0 }} eligible order</span></div><div class="attention-list"><div v-for="item in attentionCards" :key="item.label" class="attention-item" :class="`attention-${item.tone}`"><span>{{ item.label }}</span><strong>{{ displayValue(item.value, 'count') }}</strong></div></div></article><article class="panel finance-status-panel"><div class="section-heading compact"><div><span class="eyebrow">Coverage</span><h2>Status finance</h2></div></div><dl class="data-list"><div><dt>Final</dt><dd>{{ displayValue(finalGroup?.count, 'count') }} order</dd></div><div><dt>Estimasi</dt><dd>{{ displayValue(estimatedGroup?.count, 'count') }} order</dd></div><div><dt>Belum diketahui</dt><dd>{{ displayValue(unknownGroup?.count, 'count') }} order</dd></div><div><dt>Subtotal laba terukur</dt><dd>{{ displayValue(dashboard?.known_subtotal) }}</dd></div></dl></article></section>

                <section class="panel performance-panel" id="orders"><div class="section-heading"><div><span class="eyebrow">Performa harian</span><h2>Penjualan dan profit terukur</h2><p class="muted">Grafik hanya menampilkan nilai yang berasal dari fixture runtime yang sama.</p></div><span class="muted">{{ daily.length }} hari</span></div><div v-if="daily.length" class="chart-area"><div v-for="item in daily" :key="item.date" class="chart-column"><div class="chart-bars"><span class="bar bar-sales" :style="{ height: barWidth(item.sales, daily, 'sales') }"></span><span class="bar bar-profit" :style="{ height: barWidth(item.profit, daily, 'profit') }"></span></div><strong>{{ dateLabel(item.date) }}</strong><small>{{ displayValue(item.orders, 'count') }} order</small></div></div><div v-else class="empty-inline">Belum ada order pada periode ini.</div><div v-if="daily.length" class="table-wrap compact-table"><table><thead><tr><th>Tanggal</th><th>Omzet</th><th>Net</th><th>Laba</th><th>Order</th></tr></thead><tbody><tr v-for="item in daily" :key="`row-${item.date}`"><td>{{ dateLabel(item.date) }}</td><td>{{ displayValue(item.sales) }}</td><td>{{ displayValue(item.net) }}</td><td>{{ displayValue(item.profit) }}</td><td>{{ displayValue(item.orders, 'count') }}</td></tr></tbody></table></div></section>

                <section class="dashboard-grid dashboard-grid-wide"><article class="panel" id="expenses"><div class="section-heading compact"><div><span class="eyebrow">Marketplace cost</span><h2>Rincian biaya</h2></div><span class="muted">{{ displayValue(costs.count, 'count') }} baris</span></div><dl class="data-list"><div><dt>Biaya iklan</dt><dd>{{ displayValue(costs.ads) }}</dd></div><div><dt>Operasional</dt><dd>{{ displayValue(costs.operational) }}</dd></div><div><dt>Total tercatat</dt><dd>{{ displayValue((costs.ads ?? 0) + (costs.operational ?? 0)) }}</dd></div><div><dt>Data expense</dt><dd>{{ dashboard?.expenses?.length ? 'Tersedia' : 'Belum tersedia' }}</dd></div></dl></article><article class="panel"><div class="section-heading compact"><div><span class="eyebrow">Operasional order</span><h2>Status pesanan</h2></div></div><dl class="data-list"><div><dt>Order eligible</dt><dd>{{ displayValue(dashboard?.eligible_orders, 'count') }}</dd></div><div><dt>Order profit terukur</dt><dd>{{ displayValue(dashboard?.subtotal_orders, 'count') }}</dd></div><div><dt>Retur</dt><dd>{{ displayValue(attention.returns, 'count') }}</dd></div><div><dt>Finance belum lengkap</dt><dd>{{ displayValue(attention.missing_finance, 'count') }}</dd></div></dl></article></section>

                <section class="panel unavailable-panel" id="reports"><div><span class="eyebrow">Kesiapan modul</span><h2>Analitik lanjutan, iklan, settlement, dan laporan</h2><p class="muted">Modul ini belum diaktifkan pada pilot read-only. Tidak ada angka yang direka; integrasi provider dan kontrak endpoint tetap berada di luar slice ini.</p></div><div class="unavailable-tags"><span>Belum dimigrasikan</span><span>Tanpa provider Live</span><span>Read-only</span></div></section>

                <section class="panel expense-form" id="settings"><div class="section-heading"><div><span class="eyebrow">Mutasi lokal terkontrol</span><h2>Catat pengeluaran Marketplace</h2><p class="muted">Scope aktif: {{ scope.company_key }} / {{ scope.store_id }}. Tidak memanggil provider Marketplace.</p></div><span class="coverage-badge is-partial">Write pilot</span></div><form class="form-grid" @submit.prevent="submitExpense"><div class="field field-wide"><label for="expense-description">Deskripsi</label><input id="expense-description" v-model="expenseForm.description" required maxlength="255" placeholder="Contoh: biaya iklan marketplace" /><small v-if="expenseForm.errors.description" class="error">{{ expenseForm.errors.description }}</small></div><div class="field"><label for="expense-amount">Jumlah</label><input id="expense-amount" v-model="expenseForm.amount" required inputmode="decimal" placeholder="Rp 12.500" /><small v-if="expenseForm.errors.amount" class="error">{{ expenseForm.errors.amount }}</small></div><div class="field"><label for="expense-date">Tanggal</label><input id="expense-date" v-model="expenseForm.expense_date" required type="date" /></div><div class="field"><label for="expense-category">Kategori</label><input id="expense-category" v-model="expenseForm.category" required maxlength="100" /></div><div class="field"><label for="expense-vendor">Vendor</label><input id="expense-vendor" v-model="expenseForm.vendor_name" maxlength="150" /></div><div class="field"><label for="expense-method">Metode pembayaran</label><input id="expense-method" v-model="expenseForm.payment_method" required maxlength="50" /></div><div class="field field-wide"><label for="expense-notes">Catatan</label><input id="expense-notes" v-model="expenseForm.notes" placeholder="Opsional" /></div><div class="checkbox-row field-wide"><label><input v-model="expenseForm.affects_profit" type="checkbox" /> Mempengaruhi profit</label><label><input v-model="expenseForm.is_cashflow_only" type="checkbox" /> Hanya arus kas</label></div><div class="form-actions field-wide"><small v-if="expenseForm.errors.store_id" class="error">{{ expenseForm.errors.store_id }}</small><small v-if="expenseForm.recentlySuccessful" class="success">Pengeluaran tersimpan.</small><button class="button button-primary" :disabled="expenseForm.processing" type="submit">{{ expenseForm.processing ? 'Menyimpan…' : 'Simpan pengeluaran' }}</button></div></form></section>
            </template>
        </section>
    </main>

    <!-- <div v-if="false" class="pilot-shell">
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
    </div> -->
</template>
