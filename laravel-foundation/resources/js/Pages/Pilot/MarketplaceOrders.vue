<script setup lang="ts">
import { reactive, ref } from 'vue';
import WorkspaceNavigation from '../../Components/WorkspaceNavigation.vue';
import { Head, router } from '@inertiajs/vue3';

type Store = Record<string, any>;
type Order = Record<string, any>;
type OrdersPayload = { counts: Record<string, number>; rows: Order[]; total: number; page: number; pages: number; per_page: number; period: Record<string, any>; tab: string } | null;

const props = defineProps<{
    read_only: boolean;
    workspace: string;
    scope: { company_key: string; store_id: string | null };
    stores: Store[];
    active_store?: Store | null;
    filters: Record<string, any>;
    orders: OrdersPayload;
    query: Record<string, any>;
    message?: string;
}>();

const form = reactive({
    store: props.scope.store_id ?? '',
    period: props.filters.period ?? 'month',
    date_start: props.filters.date_start ?? '',
    date_end: props.filters.date_end ?? '',
    order_tab: props.filters.order_tab ?? 'all',
    search: props.filters.search ?? '',
    search_by: props.filters.search_by ?? 'order',
    courier: props.filters.courier ?? '',
    payment: props.filters.payment ?? '',
    condition: props.filters.condition ?? '',
    per_page: props.filters.per_page ?? 50,
    return_type: props.filters.return_type ?? '',
    return_date: props.filters.return_date ?? 'created',
});

const detail = ref<Record<string, any> | null>(null);
const detailBusy = ref(false);
const detailError = ref('');

function loadOrders(page = 1): void {
    const params: Record<string, any> = { ...form, page };
    if (params.period !== 'custom') {
        delete params.date_start;
        delete params.date_end;
    }
    Object.keys(params).forEach((key) => { if (params[key] === '') delete params[key]; });
    router.get('/pilot/marketplace/orders', params, { preserveState: true, preserveScroll: true, replace: true });
}

async function openDetail(order: Order): Promise<void> {
    detailBusy.value = true;
    detailError.value = '';
    detail.value = null;
    try {
        const response = await fetch(`/pilot/marketplace/orders/${encodeURIComponent(String(order.id))}/detail?store_id=${encodeURIComponent(String(props.scope.store_id ?? ''))}`, { credentials: 'same-origin', cache: 'no-store' });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || 'Rincian pesanan belum tersedia.');
        detail.value = result.data;
    } catch (error) {
        detailError.value = error instanceof Error ? error.message : 'Rincian pesanan belum tersedia.';
    } finally {
        detailBusy.value = false;
    }
}

function money(value: unknown): string {
    if (value === null || value === undefined || value === '') return 'Belum tersedia';
    return `Rp ${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(Number(value))}`;
}

function dateLabel(value: unknown): string {
    return value ? String(value).replace('T', ' ').slice(0, 19) : 'Belum tersedia';
}
</script>

<template>
    <WorkspaceNavigation workspace="marketplace" :store-id="scope.store_id" />
    <Head title="Pesanan Marketplace · Read-only" />
    <main class="min-h-screen bg-slate-50 p-4 text-slate-900 md:p-8">
        <header class="mx-auto flex max-w-7xl flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-widest text-orange-600">Marketplace · Pesanan</p>
                <h1 class="mt-2 text-3xl font-black">Transaksi dan retur</h1>
                <p class="mt-2 text-sm text-slate-600">Daftar dan detail pesanan dari data legacy yang dibaca tanpa provider atau mutasi.</p>
            </div>
            <div class="rounded-full border border-emerald-200 bg-emerald-50 px-4 py-2 text-xs font-bold text-emerald-700">Read-only pilot · {{ scope.company_key }}</div>
        </header>

        <section class="mx-auto mt-6 max-w-7xl rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <form class="grid gap-3 md:grid-cols-4" @submit.prevent="loadOrders(1)">
                <label class="text-xs font-bold text-slate-600">Toko<select v-model="form.store" class="mt-1 w-full rounded-lg border p-2 text-sm"><option v-for="store in stores" :key="store.id" :value="store.id">{{ store.store_name || store.id }}</option></select></label>
                <label class="text-xs font-bold text-slate-600">Periode<select v-model="form.period" class="mt-1 w-full rounded-lg border p-2 text-sm"><option value="today">Hari ini</option><option value="week">7 hari terakhir</option><option value="month">Bulan ini</option><option value="last_month">Bulan lalu</option><option value="custom">Rentang tanggal</option></select></label>
                <label class="text-xs font-bold text-slate-600">Tab<select v-model="form.order_tab" class="mt-1 w-full rounded-lg border p-2 text-sm"><option value="all">Semua</option><option value="new">Pesanan Baru</option><option value="pickup">Menunggu Pickup</option><option value="shipped">Dikirim</option><option value="cancelled">Dibatalkan</option><option value="returns">Pengembalian</option></select></label>
                <label class="text-xs font-bold text-slate-600">Cari berdasarkan<select v-model="form.search_by" class="mt-1 w-full rounded-lg border p-2 text-sm"><option value="order">Kode pesanan</option><option value="sku">SKU</option><option value="product">Produk</option><option value="tracking">Resi</option><option value="recipient">Penerima</option></select></label>
                <label v-if="form.period === 'custom'" class="text-xs font-bold text-slate-600">Mulai<input v-model="form.date_start" type="date" class="mt-1 w-full rounded-lg border p-2 text-sm" required /></label>
                <label v-if="form.period === 'custom'" class="text-xs font-bold text-slate-600">Sampai<input v-model="form.date_end" type="date" class="mt-1 w-full rounded-lg border p-2 text-sm" required /></label>
                <label class="text-xs font-bold text-slate-600 md:col-span-2">Pencarian<input v-model="form.search" type="search" maxlength="200" placeholder="Kode, SKU, produk, resi..." class="mt-1 w-full rounded-lg border p-2 text-sm" /></label>
                <label class="text-xs font-bold text-slate-600">Pembayaran<select v-model="form.payment" class="mt-1 w-full rounded-lg border p-2 text-sm"><option value="">Semua</option><option value="cod">COD</option><option value="non_cod">Non-COD</option></select></label>
                <label class="text-xs font-bold text-slate-600">Baris<select v-model="form.per_page" class="mt-1 w-full rounded-lg border p-2 text-sm"><option :value="25">25</option><option :value="50">50</option><option :value="100">100</option></select></label>
                <div class="flex items-end"><button class="w-full rounded-lg bg-orange-600 px-4 py-2 text-sm font-bold text-white" type="submit">Terapkan filter</button></div>
            </form>
        </section>

        <p v-if="message" class="mx-auto mt-4 max-w-7xl rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">{{ message }}</p>

        <section v-if="orders" class="mx-auto mt-6 max-w-7xl">
            <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
                <div v-for="(value, key) in orders.counts" :key="key" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs uppercase text-slate-500">{{ key }}</p><p class="mt-2 text-2xl font-black">{{ value }}</p></div>
            </div>
            <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-4"><div><h2 class="font-black">{{ orders.total }} pesanan</h2><p class="text-xs text-slate-500">{{ orders.period.start }} sampai sebelum {{ orders.period.exclusive_end }}</p></div><span class="text-xs text-slate-500">Halaman {{ orders.page }} / {{ orders.pages }}</span></div>
                <div class="overflow-x-auto"><table class="w-full min-w-[900px] text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="p-3">Tanggal</th><th class="p-3">Kode & produk</th><th class="p-3">Status</th><th class="p-3">Nilai</th><th class="p-3">Pembayaran</th><th class="p-3">Kurir / resi</th><th class="p-3">Aksi</th></tr></thead><tbody><tr v-for="order in orders.rows" :key="order.id" class="border-t border-slate-100 align-top"><td class="p-3 whitespace-nowrap">{{ dateLabel(order.date) }}</td><td class="p-3"><button class="font-bold text-orange-700 hover:underline" type="button" @click="openDetail(order)">{{ order.id }}</button><div v-for="item in order.items" :key="`${order.id}-${item.sku}-${item.name}`" class="mt-2 text-xs text-slate-600">{{ item.name || 'Produk belum tersedia' }} · {{ item.sku || 'SKU belum tersedia' }}</div></td><td class="p-3"><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-bold">{{ order.status }}</span><p v-if="order.cancel_requested" class="mt-2 text-xs text-amber-700">Pengajuan pembatalan</p></td><td class="p-3 font-bold">{{ money(order.amount) }}</td><td class="p-3">{{ order.is_cod === true ? 'COD' : order.is_cod === false ? (order.payment_method || 'Non-COD') : 'Belum tersedia' }}</td><td class="p-3">{{ order.courier || 'Belum tersedia' }}<br /><span class="text-xs text-slate-500">{{ order.tracking || 'Resi belum tersedia' }}</span></td><td class="p-3"><button class="rounded-lg border border-orange-200 px-3 py-2 text-xs font-bold text-orange-700" type="button" @click="openDetail(order)">Lihat detail</button></td></tr><tr v-if="orders.rows.length === 0"><td colspan="7" class="p-8 text-center text-slate-500">Tidak ada pesanan pada filter ini.</td></tr></tbody></table></div>
                <div class="flex justify-between border-t border-slate-100 p-4"><button class="rounded-lg border px-3 py-2 text-xs font-bold disabled:opacity-40" :disabled="orders.page <= 1" type="button" @click="loadOrders(orders.page - 1)">Sebelumnya</button><button class="rounded-lg border px-3 py-2 text-xs font-bold disabled:opacity-40" :disabled="orders.page >= orders.pages" type="button" @click="loadOrders(orders.page + 1)">Berikutnya</button></div>
            </div>
        </section>

        <div v-if="detailBusy || detailError || detail" class="fixed inset-0 z-20 flex items-start justify-center overflow-y-auto bg-slate-950/40 p-4 md:p-10" role="dialog" aria-modal="true">
            <section class="w-full max-w-3xl rounded-2xl bg-white p-6 shadow-2xl"><div class="flex justify-between gap-4"><h2 class="text-xl font-black">Rincian pesanan</h2><button class="rounded-lg border px-3 py-1 text-sm" type="button" @click="detail = null; detailError = ''">Tutup</button></div><p v-if="detailBusy" class="mt-6 text-slate-500">Memuat rincian read-only...</p><p v-else-if="detailError" class="mt-6 text-red-600">{{ detailError }}</p><div v-else-if="detail" class="mt-6 space-y-5"><div class="grid gap-3 sm:grid-cols-3"><div><p class="text-xs text-slate-500">Kode</p><p class="font-bold">{{ detail.presentation.order_id }}</p></div><div><p class="text-xs text-slate-500">Status</p><p class="font-bold">{{ detail.presentation.status }}</p></div><div><p class="text-xs text-slate-500">Nilai</p><p class="font-bold">{{ money(detail.presentation.gross_amount) }}</p></div></div><div class="grid gap-3 sm:grid-cols-3"><div><p class="text-xs text-slate-500">Net income</p><p class="font-bold">{{ money(detail.presentation.net_income) }}</p></div><div><p class="text-xs text-slate-500">HPP</p><p class="font-bold">{{ money(detail.presentation.total_cogs) }}</p></div><div><p class="text-xs text-slate-500">Laba bersih</p><p class="font-bold">{{ money(detail.presentation.net_profit) }}</p></div></div><div><h3 class="font-bold">Produk</h3><div v-for="item in detail.presentation.items" :key="`${item.sku}-${item.name}`" class="mt-2 rounded-lg bg-slate-50 p-3 text-sm">{{ item.name || 'Produk belum tersedia' }} · {{ item.variant || 'Varian belum tersedia' }} · {{ item.quantity }} unit · {{ money(item.total_price) }}</div></div><div><h3 class="font-bold">Paket dan retur</h3><p v-for="pack in (detail.presentation.packages || [])" :key="pack.id" class="mt-2 text-sm">Paket {{ pack.id }} · {{ pack.courier || 'Kurir belum tersedia' }} · {{ pack.tracking || 'Resi belum tersedia' }}</p><p v-for="item in (detail.presentation.return_cases || [])" :key="item.id" class="mt-2 text-sm text-amber-700">{{ item.type_label }} · {{ item.status }} · {{ item.refund_status }}</p><p v-if="!(detail.presentation.return_cases || []).length" class="mt-2 text-sm text-slate-500">Tidak ada kasus retur yang tersedia.</p></div></div></section>
        </div>
    </main>
</template>
