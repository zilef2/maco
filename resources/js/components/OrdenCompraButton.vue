<script setup lang="ts">
import { ClipboardDocumentListIcon } from '@heroicons/vue/24/solid';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();

const faltantes = computed<any[]>(() => (page.props as any).faltantesOp ?? []);
const puedeVer = computed(() => Boolean((page.props as any).auth?.can?.['update Material']));
const visible = computed(() => puedeVer.value && faltantes.value.length > 0);
const total = computed(() => faltantes.value.length);
</script>

<template>
    <Link v-if="visible" :href="route('Material.orden-compra')"
        class="inline-flex h-9 items-center gap-2 rounded-full border border-neutral-300 bg-card px-3.5 text-xs font-semibold text-neutral-800 shadow-xs transition-colors hover:border-neutral-400 hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-400 focus-visible:ring-offset-2 focus-visible:ring-offset-background dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100 dark:hover:border-neutral-600 dark:hover:bg-neutral-800"
        title="Ver cuánto hay que comprar y actualizar precios, sin registrar la compra"
        aria-label="Mostrar orden de compra">
        <ClipboardDocumentListIcon class="h-4 w-4" />
        <span class="hidden sm:inline">Orden de compra</span>
        <span
            class="inline-flex h-5 min-w-5 items-center justify-center rounded-full border border-neutral-300 bg-background px-1 text-[10px] font-bold tabular-nums text-neutral-800 dark:border-neutral-600 dark:bg-neutral-950 dark:text-neutral-100">
            {{ total > 99 ? '99+' : total }}
        </span>
    </Link>
</template>
