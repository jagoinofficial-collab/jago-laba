<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import WorkspaceNavigation from '../../Components/WorkspaceNavigation.vue';
const props = defineProps<{ catalog: Record<string, any>; filters: Record<string, any>; read_only: boolean }>();
const search = ref(props.filters.search ?? '');
const money = (v: unknown) => v === null || v === undefined ? 'Belum tersedia' : new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(Number(v));
const query = () => ({ ...props.filters, search: search.value });
const load = () => router.get('/marketplace/catalog', query());
const exportUrl = () => `/marketplace/catalog?${new URLSearchParams({ ...query(), format: 'csv' }).toString()}`;
</script>
<template>
    <WorkspaceNavigation workspace="marketplace" :store-id="filters.store_id" />
    <Head title="Katalog · Jago Laba" />
    <main class="pilot-shell">
        <header class="pilot-header"><div><Link :href="`/pilot/marketplace?store=${encodeURIComponent(filters.store_id)}`">← Dashboard</Link><h1>Katalog produk</h1><p>Produk dan varian dari toko terpilih.</p></div><a class="button" :href="exportUrl()">Unduh CSV</a></header>
        <form class="panel" @submit.prevent="load"><label>Cari produk atau SKU <input v-model="search" maxlength="200" /></label><button class="button" type="submit">Cari</button></form>
        <p>{{ catalog.products.length }} produk · {{ catalog.missing_hpp }} produk perlu pemeriksaan HPP</p>
        <section v-for="product in catalog.products" :key="`${product.store_id}:${product.name}`" class="panel" style="margin-bottom:1rem;overflow:auto">
            <h2>{{ product.name }}</h2><p>{{ product.store_name }} · {{ product.platform }}</p>
            <table><thead><tr><th>SKU / Varian</th><th>Harga</th><th>Stok</th><th>HPP</th><th>Terjual</th></tr></thead><tbody><tr v-for="variant in product.variants" :key="variant.external_variant_id"><td>{{ variant.sku }}<br />{{ variant.variant_name }}</td><td>{{ money(variant.price) }}</td><td>{{ variant.stock_qty ?? 'Belum tersedia' }}</td><td>{{ money(variant.hpp) }}</td><td>{{ variant.sold }}</td></tr></tbody></table>
        </section>
        <p v-if="catalog.products.length === 0" class="panel">Tidak ada produk yang cocok dengan filter.</p>
    </main>
</template>
