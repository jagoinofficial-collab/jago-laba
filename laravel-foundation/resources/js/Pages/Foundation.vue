<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineProps<{ title: string; status: string }>();
</script>

<template>
    <Head :title="title" />
    <main>
        <h1>{{ title }}</h1>
        <p>Foundation Laravel tersedia. Status: {{ status }}.</p>
        <p>Proses bisnis dan URL lama belum dialihkan.</p>
    </main>
</template>
