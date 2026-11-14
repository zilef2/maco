<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { zilef_nuber_format } from '@/global.ts';
import PrimaryButton from '@/components/PrimaryButton.vue';
import { computed, reactive, ref, watch } from 'vue';
import {
    ChevronDownIcon,
    ChevronRightIcon,
    ChevronUpDownIcon,
    ChevronUpIcon,
    MagnifyingGlassIcon,
    PencilIcon,
    PlusIcon,
    TrashIcon,
    XMarkIcon,
} from '@heroicons/vue/24/solid';
import Create from '@/pages/Procesoop/Create.vue';
import Edit from '@/pages/Procesoop/Edit.vue';
import Delete from '@/pages/Procesoop/Delete.vue';

const props = defineProps({
    procesoops: { type: Array as () => any[], default: () => [] },
    vinculos: { type: Array as () => any[], default: () => [] },
    total: Number,
    breadcrumbs: { type: Array as () => Array<{ label?: string; href?: string }>, default: () => [] },
    title: String,
    numberPermissions: Number,
});

const busqueda = ref('');
const sortKey = ref('nombre');
const sortDir = ref<'asc' | 'desc'>('asc');
const porPagina = ref(10);
const pagina = ref(1);

const data = reactive({
    createOpen: false,
    editOpen: false,
    deleteOpen: false,
    item: null as any,
});

const normalizar = (s: any): string =>
    String(s ?? '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');

const columnas = [
    { key: 'nombre', label: 'Nombre', sortable: true },
    { key: 'productos_count', label: 'Productos', sortable: true },
] as const;

const filas = computed<any[]>(() => props.procesoops ?? []);

const filtradas = computed(() =>
    filas.value.filter((p: any) => {
        if (!busqueda.value.trim()) return true;
        return normalizar(p.nombre).includes(normalizar(busqueda.value));
    }),
);

const collator = new Intl.Collator('es', { numeric: true, sensitivity: 'base' });

const ordenadas = computed(() => {
    const dir = sortDir.value === 'asc' ? 1 : -1;
    const key = sortKey.value;
    return [...filtradas.value].sort((a: any, b: any) => {
        const va = a[key] ?? '';
        const vb = b[key] ?? '';
        if (typeof va === 'number' && typeof vb === 'number') return (va - vb) * dir;
        return collator.compare(String(va), String(vb)) * dir;
    });
});

const paginas = computed(() => Math.max(1, Math.ceil(ordenadas.value.length / porPagina.value)));
const paginaActual = computed(() => Math.min(pagina.value, paginas.value));
const desde = computed(() => ordenadas.value.length === 0 ? 0 : (paginaActual.value - 1) * porPagina.value + 1);
const hasta = computed(() => Math.min(paginaActual.value * porPagina.value, ordenadas.value.length));
const visibles = computed(() => {
    const inicio = (paginaActual.value - 1) * porPagina.value;
    return ordenadas.value.slice(inicio, inicio + porPagina.value);
});

const ordenarPor = (key: string) => {
    if (sortKey.value === key) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = key;
        sortDir.value = 'asc';
    }
    pagina.value = 1;
};

const indicador = (key: string) => (sortKey.value === key ? sortDir.value : null);

watch([busqueda, porPagina], () => { pagina.value = 1; });
watch(paginas, (n) => { if (pagina.value > n) pagina.value = n; });

const abrirEditar = (item: any) => {
    data.item = item;
    data.editOpen = true;
};

const abrirEliminar = (item: any) => {
    data.item = item;
    data.deleteOpen = true;
};

// ── Segunda tabla: procesos vinculados a cada producto ────────────────────
const formatearPrecio = (valor: any, decimales = 0): string =>
    valor === null || valor === undefined || valor === '' ? '—' : zilef_nuber_format(valor, decimales, true);

const decimalesDe = (valor: any): number =>
    Number.isFinite(Number(valor)) && Math.round(Number(valor) * 100) % 100 !== 0 ? 2 : 0;

const busquedaVinculos = ref('');
const sortKeyV = ref('proceso');
const sortDirV = ref<'asc' | 'desc'>('asc');
const porPaginaV = ref(10);
const paginaV = ref(1);

const columnasVinculos = [
    { key: 'proceso', label: 'Proceso', sortable: true },
    { key: 'producto', label: 'Producto', sortable: true },
    { key: 'coste_proceso', label: 'Coste', sortable: true },
] as const;

const filasVinculos = computed<any[]>(() => props.vinculos ?? []);

const filtradasVinculos = computed(() =>
    filasVinculos.value.filter((v: any) => {
        const q = busquedaVinculos.value.trim();
        if (!q) return true;
        return normalizar(v.proceso).includes(normalizar(q)) || normalizar(v.producto).includes(normalizar(q));
    }),
);

const ordenadasVinculos = computed(() => {
    const dir = sortDirV.value === 'asc' ? 1 : -1;
    const key = sortKeyV.value;
    return [...filtradasVinculos.value].sort((a: any, b: any) => {
        const va = a[key] ?? '';
        const vb = b[key] ?? '';
        if (typeof va === 'number' && typeof vb === 'number') return (va - vb) * dir;
        return collator.compare(String(va), String(vb)) * dir;
    });
});

const paginasV = computed(() => Math.max(1, Math.ceil(ordenadasVinculos.value.length / porPaginaV.value)));
const paginaActualV = computed(() => Math.min(paginaV.value, paginasV.value));
const desdeV = computed(() => ordenadasVinculos.value.length === 0 ? 0 : (paginaActualV.value - 1) * porPaginaV.value + 1);
const hastaV = computed(() => Math.min(paginaActualV.value * porPaginaV.value, ordenadasVinculos.value.length));
const visiblesVinculos = computed(() => {
    const inicio = (paginaActualV.value - 1) * porPaginaV.value;
    return ordenadasVinculos.value.slice(inicio, inicio + porPaginaV.value);
});

const ordenarPorV = (key: string) => {
    if (sortKeyV.value === key) {
        sortDirV.value = sortDirV.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKeyV.value = key;
        sortDirV.value = 'asc';
    }
    paginaV.value = 1;
};

const indicadorV = (key: string) => (sortKeyV.value === key ? sortDirV.value : null);

watch([busquedaVinculos, porPaginaV], () => { paginaV.value = 1; });
watch(paginasV, (n) => { if (paginaV.value > n) paginaV.value = n; });
</script>

<template>
    <Head :title="props.title" />
    <AppLayout>
        <div class="flex flex-col gap-4 p-4 sm:p-6">
            <div class="flex flex-col gap-3">
                <nav class="flex items-center gap-1.5 text-xs text-muted-foreground" aria-label="Ruta de navegación">
                    <Link :href="route('dashboard')"
                        class="rounded-sm transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                        Dashboard
                    </Link>
                    <template v-for="(miga, i) in (props.breadcrumbs || [])" :key="i">
                        <ChevronRightIcon class="h-3 w-3 opacity-60" />
                        <Link v-if="miga.href" :href="miga.href"
                            class="rounded-sm transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                            {{ miga.label }}
                        </Link>
                        <span v-else class="font-medium text-foreground">{{ miga.label }}</span>
                    </template>
                </nav>

                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div class="min-w-0">
                        <h1 class="text-2xl font-semibold tracking-tight text-foreground">{{ props.title }}</h1>
                        <p class="mt-1 text-sm text-muted-foreground" role="status" aria-live="polite">
                            <span class="font-medium text-foreground">{{ ordenadas.length }}</span>
                            {{ ordenadas.length === 1 ? 'proceso' : 'procesos' }}
                            <template v-if="ordenadas.length !== visibles.length">
                                &middot;
                                <span class="font-medium text-foreground">{{ visibles.length }}</span> en esta página
                            </template>
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <PrimaryButton v-if="can(['create Procesoop'])" type="button" class="h-9"
                            @click="data.createOpen = true">
                            <PlusIcon class="mr-1.5 h-4 w-4" />
                            Agregar
                        </PrimaryButton>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <div class="flex flex-wrap items-center gap-2 border-b border-border p-3">
                    <div class="relative min-w-[220px] flex-1">
                        <MagnifyingGlassIcon
                            class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <input v-model="busqueda" type="search" aria-label="Buscar procesos"
                            placeholder="Buscar por nombre…"
                            class="h-9 w-full rounded-md border border-input bg-background pl-9 pr-9 text-sm text-foreground shadow-sm placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30" />
                        <button v-if="busqueda" type="button" @click="busqueda = ''" aria-label="Limpiar búsqueda"
                            class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-muted-foreground transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                            <XMarkIcon class="h-3.5 w-3.5" />
                        </button>
                    </div>
                    <select v-model.number="porPagina" aria-label="Registros por página"
                        class="h-9 rounded-md border border-input bg-background px-2 text-sm text-foreground shadow-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30">
                        <option v-for="n in [10, 25, 50, 100]" :key="n" :value="n">{{ n }} / página</option>
                    </select>
                </div>

                <div class="overflow-auto overscroll-contain max-h-[68vh]">
                    <table class="w-full border-collapse text-sm">
                        <thead>
                            <tr>
                                <th v-for="col in columnas" :key="col.key" scope="col"
                                    :class="[
                                        'bg-muted px-3 py-2.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground',
                                        col.key === 'productos_count' ? 'text-right' : 'text-left',
                                    ]">
                                    <button v-if="col.sortable" type="button" @click="ordenarPor(col.key)"
                                        class="group/sort inline-flex items-center gap-1 rounded-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        :class="col.key === 'productos_count' ? 'w-full justify-end' : ''">
                                        <span>{{ col.label }}</span>
                                        <span class="text-muted-foreground/60 transition-colors group-hover/sort:text-foreground">
                                            <ChevronUpIcon v-if="indicador(col.key) === 'asc'" class="h-3 w-3" />
                                            <ChevronDownIcon v-else-if="indicador(col.key) === 'desc'" class="h-3 w-3" />
                                            <ChevronUpDownIcon v-else class="h-3 w-3 opacity-0 transition-opacity group-hover/sort:opacity-100" />
                                        </span>
                                    </button>
                                    <span v-else>{{ col.label }}</span>
                                </th>
                                <th scope="col"
                                    class="sticky right-0 z-30 w-[90px] border-l border-border bg-muted px-2 py-2.5 text-right text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground">
                                    Acciones
                                </th>
                            </tr>
                        </thead>

                        <tbody v-if="visibles.length">
                            <tr v-for="item in visibles" :key="item.id"
                                class="group border-b border-border/60 transition-colors last:border-0 hover:bg-muted/40">
                                <td class="px-3 py-3 text-sm text-foreground">{{ item.nombre }}</td>
                                <td class="px-3 py-3 text-sm text-right tabular-nums text-foreground">
                                    {{ item.productos_count ?? 0 }}
                                </td>
                                <td class="sticky right-0 z-20 border-l border-border bg-card px-2 py-3 group-hover:bg-muted/40">
                                    <div class="flex items-center justify-end gap-1">
                                        <button v-if="can(['update Procesoop'])" type="button"
                                            @click="abrirEditar(item)"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-md text-muted-foreground transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                            title="Editar">
                                            <PencilIcon class="h-3.5 w-3.5" />
                                        </button>
                                        <button v-if="can(['delete Procesoop'])" type="button"
                                            @click="abrirEliminar(item)"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-md text-muted-foreground transition hover:bg-destructive/10 hover:text-destructive focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                            title="Eliminar">
                                            <TrashIcon class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>

                        <tbody v-else>
                            <tr>
                                <td :colspan="3" class="py-16 text-center text-sm text-muted-foreground">
                                    {{ busqueda ? 'Sin resultados para la búsqueda.' : 'Sin registros.' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center justify-between border-t border-border px-4 py-3 text-sm text-muted-foreground">
                    <span class="tabular-nums">
                        <template v-if="ordenadas.length > 0">
                            {{ desde }}–{{ hasta }} de {{ ordenadas.length }}
                        </template>
                        <template v-else>Sin resultados</template>
                    </span>
                    <div class="flex items-center gap-1">
                        <button type="button" :disabled="paginaActual <= 1" @click="pagina = paginaActual - 1"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border bg-card text-xs transition hover:bg-muted disabled:pointer-events-none disabled:opacity-40">
                            ‹
                        </button>
                        <span class="px-2 tabular-nums">{{ paginaActual }} / {{ paginas }}</span>
                        <button type="button" :disabled="paginaActual >= paginas" @click="pagina = paginaActual + 1"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border bg-card text-xs transition hover:bg-muted disabled:pointer-events-none disabled:opacity-40">
                            ›
                        </button>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border p-3">
                    <div class="min-w-0">
                        <h2 class="text-sm font-semibold text-foreground">Procesos vinculados a productos</h2>
                        <p class="text-xs text-muted-foreground" role="status" aria-live="polite">
                            <span class="font-medium text-foreground">{{ ordenadasVinculos.length }}</span>
                            {{ ordenadasVinculos.length === 1 ? 'vínculo' : 'vínculos' }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="relative min-w-[220px]">
                            <MagnifyingGlassIcon
                                class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <input v-model="busquedaVinculos" type="search" aria-label="Buscar vínculos"
                                placeholder="Buscar por proceso o producto…"
                                class="h-9 w-full rounded-md border border-input bg-background pl-9 pr-9 text-sm text-foreground shadow-sm placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30" />
                            <button v-if="busquedaVinculos" type="button" @click="busquedaVinculos = ''" aria-label="Limpiar búsqueda"
                                class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-muted-foreground transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                                <XMarkIcon class="h-3.5 w-3.5" />
                            </button>
                        </div>
                        <select v-model.number="porPaginaV" aria-label="Registros por página"
                            class="h-9 rounded-md border border-input bg-background px-2 text-sm text-foreground shadow-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30">
                            <option v-for="n in [10, 25, 50, 100]" :key="n" :value="n">{{ n }} / página</option>
                        </select>
                    </div>
                </div>

                <div class="overflow-auto overscroll-contain max-h-[60vh]">
                    <table class="w-full border-collapse text-sm">
                        <thead>
                            <tr>
                                <th v-for="col in columnasVinculos" :key="col.key" scope="col"
                                    :class="[
                                        'bg-muted px-3 py-2.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground',
                                        col.key === 'coste_proceso' ? 'text-right' : 'text-left',
                                    ]">
                                    <button type="button" @click="ordenarPorV(col.key)"
                                        class="group/sort inline-flex items-center gap-1 rounded-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        :class="col.key === 'coste_proceso' ? 'w-full justify-end' : ''">
                                        <span>{{ col.label }}</span>
                                        <span class="text-muted-foreground/60 transition-colors group-hover/sort:text-foreground">
                                            <ChevronUpIcon v-if="indicadorV(col.key) === 'asc'" class="h-3 w-3" />
                                            <ChevronDownIcon v-else-if="indicadorV(col.key) === 'desc'" class="h-3 w-3" />
                                            <ChevronUpDownIcon v-else class="h-3 w-3 opacity-0 transition-opacity group-hover/sort:opacity-100" />
                                        </span>
                                    </button>
                                </th>
                            </tr>
                        </thead>

                        <tbody v-if="visiblesVinculos.length">
                            <tr v-for="(v, i) in visiblesVinculos" :key="`${v.procesoop_id}-${v.producto_id}-${i}`"
                                class="border-b border-border/60 transition-colors last:border-0 hover:bg-muted/40">
                                <td class="px-3 py-3 text-sm text-foreground">{{ v.proceso }}</td>
                                <td class="px-3 py-3 text-sm text-foreground">{{ v.producto }}</td>
                                <td class="px-3 py-3 text-sm text-right tabular-nums text-foreground">
                                    {{ formatearPrecio(v.coste_proceso, decimalesDe(v.coste_proceso)) }}
                                </td>
                            </tr>
                        </tbody>

                        <tbody v-else>
                            <tr>
                                <td :colspan="3" class="py-16 text-center text-sm text-muted-foreground">
                                    {{ busquedaVinculos ? 'Sin resultados para la búsqueda.' : 'Ningún proceso está vinculado a un producto todavía.' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center justify-between border-t border-border px-4 py-3 text-sm text-muted-foreground">
                    <span class="tabular-nums">
                        <template v-if="ordenadasVinculos.length > 0">
                            {{ desdeV }}–{{ hastaV }} de {{ ordenadasVinculos.length }}
                        </template>
                        <template v-else>Sin resultados</template>
                    </span>
                    <div class="flex items-center gap-1">
                        <button type="button" :disabled="paginaActualV <= 1" @click="paginaV = paginaActualV - 1"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border bg-card text-xs transition hover:bg-muted disabled:pointer-events-none disabled:opacity-40">
                            ‹
                        </button>
                        <span class="px-2 tabular-nums">{{ paginaActualV }} / {{ paginasV }}</span>
                        <button type="button" :disabled="paginaActualV >= paginasV" @click="paginaV = paginaActualV + 1"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border bg-card text-xs transition hover:bg-muted disabled:pointer-events-none disabled:opacity-40">
                            ›
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <Create
            v-if="can(['create Procesoop'])"
            :show="data.createOpen"
            :title="props.title"
            :numberPermissions="props.numberPermissions"
            @close="data.createOpen = false"
        />

        <Edit
            v-if="can(['update Procesoop'])"
            :show="data.editOpen"
            :title="props.title"
            :item="data.item"
            :numberPermissions="props.numberPermissions"
            @close="data.editOpen = false"
        />

        <Delete
            v-if="can(['delete Procesoop'])"
            :show="data.deleteOpen"
            :title="props.title"
            :Procesoopa="data.item"
            @close="data.deleteOpen = false"
        />
    </AppLayout>
</template>
