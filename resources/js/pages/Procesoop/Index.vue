<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
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
    total: Number,
    breadcrumbs: { type: Array as () => Array<{ label?: string; href?: string }>, default: () => [] },
    title: String,
    numberPermissions: Number,
    productosSelect: { type: Array as () => any[], default: () => [] },
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

const formatearPrecio = (v: any): string => {
    const n = Number(v ?? 0);
    return n.toLocaleString('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 });
};

const normalizar = (s: any): string =>
    String(s ?? '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');

const columnas = [
    { key: 'nombre', label: 'Nombre', sortable: true },
    { key: 'producto_nombre', label: 'Producto', sortable: true },
    { key: 'coste_proceso', label: 'Costo proceso', sortable: true },
] as const;

const filas = computed<any[]>(() => props.procesoops ?? []);

const filtradas = computed(() =>
    filas.value.filter((p: any) => {
        if (!busqueda.value.trim()) return true;
        const q = normalizar(busqueda.value);
        return normalizar(p.nombre).includes(q) || normalizar(p.producto_nombre).includes(q);
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
                        <input v-model="busqueda" type="search" aria-label="Buscar procesoops"
                            placeholder="Buscar por nombre o producto…"
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
                                        'bg-muted px-3 py-2.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground text-left',
                                        col.key === 'coste_proceso' ? 'text-right' : 'text-left',
                                    ]">
                                    <button v-if="col.sortable" type="button" @click="ordenarPor(col.key)"
                                        class="group/sort inline-flex items-center gap-1 rounded-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        :class="col.key === 'coste_proceso' ? 'w-full justify-end' : ''">
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
                                <td class="px-3 py-3 text-sm text-foreground">
                                    <span class="font-medium">{{ item.producto_nombre }}</span>
                                    <span v-if="item.producto_codigo"
                                        class="ml-1.5 text-xs text-muted-foreground">({{ item.producto_codigo }})</span>
                                </td>
                                <td class="px-3 py-3 text-sm text-right tabular-nums text-foreground">
                                    {{ formatearPrecio(item.coste_proceso) }}
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
                                <td :colspan="4" class="py-16 text-center text-sm text-muted-foreground">
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
        </div>

        <Create
            v-if="can(['create Procesoop'])"
            :show="data.createOpen"
            :title="props.title"
            :productosSelect="props.productosSelect"
            :numberPermissions="props.numberPermissions"
            @close="data.createOpen = false"
        />

        <Edit
            v-if="can(['update Procesoop'])"
            :show="data.editOpen"
            :title="props.title"
            :item="data.item"
            :productosSelect="props.productosSelect"
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
