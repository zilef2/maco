<script setup lang="ts">
import { ExclamationTriangleIcon } from '@heroicons/vue/24/solid';
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();

const pageProps = computed(() => page.props as any);
const faltantes = computed<any[]>(() => pageProps.value.faltantesOp ?? []);
const puedeComprar = computed(() => Boolean(pageProps.value.auth?.can?.['update Material']));
const visible = computed(() => puedeComprar.value && faltantes.value.length > 0);
const total = computed(() => faltantes.value.length);

const abrirCompra = () => {
    // El valor cambia en cada clic para que el modal se abra aunque ya se
    // esté en Materiales con el mismo parámetro de la visita anterior.
    router.visit(route('Material.index', { comprar_faltantes: Date.now() }));
};
</script>

<template>
    <button
        v-if="visible"
        type="button"
        class="inline-flex h-9 cursor-pointer items-center gap-2 rounded-full border border-neutral-900 bg-neutral-900 px-3.5 text-xs font-semibold text-white shadow-xs transition-colors hover:bg-neutral-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900 focus-visible:ring-offset-2 dark:border-white dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-300 dark:focus-visible:ring-white dark:focus-visible:ring-offset-neutral-950"
        title="Registrar la compra de los materiales que faltan en las órdenes abiertas"
        aria-label="Comprar faltantes"
        @click="abrirCompra"
    >
        <ExclamationTriangleIcon class="h-4 w-4" />
        <span class="hidden sm:inline">Comprar faltantes</span>
        <span
            class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-white px-1 text-[10px] font-bold tabular-nums text-neutral-900 dark:bg-neutral-900 dark:text-white"
        >
            {{ total > 99 ? '99+' : total }}
        </span>
    </button>
</template>
