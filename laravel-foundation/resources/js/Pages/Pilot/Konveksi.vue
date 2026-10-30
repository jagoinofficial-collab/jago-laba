<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    read_only: boolean;
    workspace: string;
    scope: { company_key: string; database_isolated: boolean };
    tab: string;
    collections: Record<string, any>;
    data: Record<string, any>;
}>();

const visible = ['bahan', 'produk', 'pegawai', 'supplier', 'rekening', 'keuangan', 'master_stok'];
</script>

<template>
    <Head title="Pilot Konveksi · Read-only" />
    <main class="pilot-shell">
        <header class="pilot-header">
            <div><div class="eyebrow">Jago Laba · Pilot read-only</div><h1>Konveksi</h1><p class="muted">Snapshot Laravel/Inertia dari kontrak <code>loadSemuaData</code>.</p></div>
            <div><span class="badge badge-read">● Read-only</span><span class="badge badge-scope">Workspace: {{ workspace }}</span></div>
        </header>
        <section class="panel" style="margin-bottom: 1rem">
            <h2>Scope snapshot</h2>
            <dl class="data-list"><div><dt>Company</dt><dd>{{ scope.company_key }}</dd></div><div><dt>Database isolated</dt><dd>{{ scope.database_isolated ? 'Ya' : 'Konfigurasi belum ditandai' }}</dd></div><div><dt>Tab compatibility</dt><dd>{{ tab }}</dd></div><div><dt>Collection</dt><dd>{{ Object.keys(collections).length - 1 }} + __meta</dd></div></dl>
        </section>
        <section class="grid grid-3" style="margin-bottom: 1rem">
            <div v-for="name in visible" :key="name" class="kpi"><small>{{ name }}</small><strong>{{ collections[name]?.length ?? 0 }}</strong><span class="muted">baris terbaca</span></div>
        </section>
        <section class="panel">
            <h2>Data quality</h2>
            <dl class="data-list"><div><dt>Missing stock IDs</dt><dd>{{ data.__meta?.health?.missing_stock_ids ?? 0 }}</dd></div><div><dt>Duplicate stock IDs</dt><dd>{{ data.__meta?.health?.duplicate_stock_ids ?? 0 }}</dd></div><div><dt>Negative stock</dt><dd>{{ data.__meta?.health?.negative_stock ?? 0 }}</dd></div><div><dt>Company bound</dt><dd>{{ data.__meta?.company_bound ? 'Ya' : 'Tidak' }}</dd></div></dl>
        </section>
    </main>
</template>
