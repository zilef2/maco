<script setup lang="ts">
import { computed } from 'vue';
import {
    ChevronDoubleLeftIcon,
    ChevronDoubleRightIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
} from '@heroicons/vue/24/solid';

const props = defineProps({
    currentPage: { type: Number, default: 1 },
    lastPage: { type: Number, default: 1 },
    from: { type: Number, default: 0 },
    to: { type: Number, default: 0 },
    total: { type: Number, default: 0 },
    itemLabel: { type: String, default: 'registros' },
    summary: { type: String, default: '' },
});

const emit = defineEmits<{ (event: 'page', page: number): void }>();

const paginaActual = computed(() => Math.min(Math.max(1, props.currentPage), Math.max(1, props.lastPage)));

const paginasVisibles = computed<Array<number | '...'>>(() => {
    const total = props.lastPage;
    const actual = paginaActual.value;
    const ventana = 5;
    let inicio = Math.max(1, actual - 2);
    let fin = Math.min(total, inicio + ventana - 1);
    inicio = Math.max(1, fin - ventana + 1);

    const salida: Array<number | '...'> = [];
    if (inicio > 1) {
        salida.push(1);
        if (inicio > 2) salida.push('...');
    }
    for (let i = inicio; i <= fin; i++) salida.push(i);
    if (fin < total) {
        if (fin < total - 1) salida.push('...');
        salida.push(total);
    }
    return salida;
});

const ir = (page: number) => {
    const destino = Math.min(Math.max(1, page), Math.max(1, props.lastPage));
    if (destino !== paginaActual.value) emit('page', destino);
};
</script>

<template>
    <footer
        v-if="props.total > 0"
        class="flex flex-wrap items-center justify-between gap-3 border-t border-border bg-card px-3 py-2.5 sm:px-4"
    >
        <p class="text-xs text-muted-foreground" role="status" aria-live="polite">
            Mostrando
            <span class="font-medium tabular-nums text-foreground">{{ props.from }}–{{ props.to }}</span>
            de
            <span class="font-medium tabular-nums text-foreground">{{ props.total }}</span>
            {{ props.itemLabel }}
            <span v-if="props.summary" class="ml-2 text-foreground">· {{ props.summary }}</span>
        </p>

        <nav v-if="props.lastPage > 1" class="flex items-center gap-1" aria-label="Paginación">
            <button
                type="button"
                @click="ir(1)"
                :disabled="paginaActual === 1"
                aria-label="Primera página"
                class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-40"
            >
                <ChevronDoubleLeftIcon class="h-3.5 w-3.5" />
            </button>
            <button
                type="button"
                @click="ir(paginaActual - 1)"
                :disabled="paginaActual === 1"
                aria-label="Página anterior"
                class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-40"
            >
                <ChevronLeftIcon class="h-4 w-4" />
            </button>

            <template v-for="(p, i) in paginasVisibles" :key="i">
                <span v-if="p === '...'" class="px-1 text-xs text-muted-foreground">…</span>
                <button
                    v-else
                    type="button"
                    @click="ir(Number(p))"
                    :aria-current="p === paginaActual ? 'page' : undefined"
                    :class="[
                        'inline-flex h-8 min-w-8 items-center justify-center rounded-md px-2 text-xs font-medium tabular-nums transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                        p === paginaActual
                            ? 'bg-foreground text-background shadow-sm'
                            : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                    ]"
                >
                    {{ p }}
                </button>
            </template>

            <button
                type="button"
                @click="ir(paginaActual + 1)"
                :disabled="paginaActual === props.lastPage"
                aria-label="Página siguiente"
                class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-40"
            >
                <ChevronRightIcon class="h-4 w-4" />
            </button>
            <button
                type="button"
                @click="ir(props.lastPage)"
                :disabled="paginaActual === props.lastPage"
                aria-label="Última página"
                class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-40"
            >
                <ChevronDoubleRightIcon class="h-3.5 w-3.5" />
            </button>
        </nav>
    </footer>
</template>
