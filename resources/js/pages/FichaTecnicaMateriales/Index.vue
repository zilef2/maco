<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import DataPagination from '@/components/DataPagination.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

import {
    ArrowPathIcon,
    ChevronDownIcon,
    ChevronRightIcon,
    ChevronUpDownIcon,
    ChevronUpIcon,
    CubeIcon,
    FunnelIcon,
    RectangleStackIcon,
    MagnifyingGlassIcon,
    PencilIcon,
    PlusIcon,
    TrashIcon,
    XMarkIcon,
} from '@heroicons/vue/24/solid';

import Edit from '@/pages/FichaTecnicaMateriales/Edit.vue';
import Delete from '@/pages/FichaTecnicaMateriales/Delete.vue';
import MaterialesModal from '@/pages/FichaTecnicaMateriales/MaterialesModal.vue';
import ProductosSinFichaTabla from '@/pages/FichaTecnicaMateriales/ProductosSinFichaTabla.vue';
import MaterialesSinFichaTabla from '@/pages/FichaTecnicaMateriales/MaterialesSinFichaTabla.vue';

import { useTablaClient, type ColumnaTabla } from '@/composables/useTablaClient';
import { zilef_nuber_format } from '@/global.ts';

const props = defineProps({
    fichas: { type: Array as () => any[], default: () => [] },
    breadcrumbs: { type: Array as () => Array<{ label?: string; href?: string }>, default: () => [] },
    title: String,
    numberPermissions: Number,
    losSelect: Object,
    productosSinFicha: { type: Array as () => any[], default: () => [] },
    selectProducto: Object,
    titulosProducto: Array,
    materialesSinFicha: { type: Array as () => any[], default: () => [] },
    selectMaterial: Object,
    titulosMaterial: Array,
});

const breadcrumbCollapsed = ref(false);

type Pestana = 'fichas' | 'productos' | 'materiales';
const pestana = ref<Pestana>('fichas');
const cargando = ref(false);

const data = reactive({
    FichaTecnicaMaterialeso: null as any,
    editOpen: false,
    deleteOpen: false,
    materialesOpen: false,
    fichaVisible: null as any,
});

const columnas: ColumnaTabla[] = [
    {
        key: 'nombre',
        label: 'Ficha',
        sortable: true,
        align: 'left',
        headerClass: 'min-w-[220px]',
    },
    {
        key: 'objetivos',
        label: 'Objetivos',
        sortable: true,
        align: 'left',
        headerClass: 'min-w-[300px]',
        valor: (ficha: any) => [ficha.productos_nombres, ficha.materiales_procesados_nombres].join(' '),
    },
    {
        key: 'materiales_count',
        label: 'Materiales',
        sortable: true,
        align: 'center',
        headerClass: 'w-[120px]',
        valor: (ficha: any) => Number(ficha.materiales_count ?? 0),
    },
    {
        key: 'valor_total_ficha',
        label: 'Valor total',
        sortable: true,
        align: 'right',
        headerClass: 'w-[150px]',
        valor: (ficha: any) => Number(ficha.valor_total_ficha ?? 0),
    },
];

const tabla = useTablaClient(() => props.fichas ?? [], {
    columnas,
    busquedaPor: ['nombre', 'objetivos'],
    porPagina: 10,
    orden: { key: 'nombre', dir: 'asc' },
});

const {
    busqueda,
    porPagina,
    paginaActual,
    paginas,
    desde,
    hasta,
    visibles,
    total,
    hayBusqueda,
    cambiarPagina,
    ordenarPor,
    indicador,
    ariaSort,
    limpiar,
} = tabla;

const totales = computed(() => ({
    fichas: (props.fichas ?? []).length,
    productos: (props.productosSinFicha ?? []).length,
    materiales: (props.materialesSinFicha ?? []).length,
}));

const texto = (valor: any, fallback = '—'): string => {
    const resultado = String(valor ?? '').trim();
    return resultado !== '' ? resultado : fallback;
};

const nombreCorto = (valor: any, maximo = 60): string => {
    const nombre = String(valor ?? '').trim();
    return nombre.length > maximo ? `${nombre.slice(0, maximo - 1)}…` : nombre;
};

const formatearPrecio = (valor: any): string =>
    valor === null || valor === undefined || valor === '' ? '—' : zilef_nuber_format(valor, 0, true);

const fechaCorta = (valor: any): string => {
    if (!valor) return '—';
    const fecha = new Date(String(valor));
    return Number.isNaN(fecha.getTime())
        ? String(valor)
        : fecha.toLocaleDateString('es-CO', { day: '2-digit', month: 'short', year: 'numeric' });
};

const selectClass =
    'h-9 rounded-md border border-input bg-background px-2 text-sm text-foreground shadow-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30';

const segmentoClass = (activo: boolean) => [
    'whitespace-nowrap px-3 py-2 text-xs font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring',
    activo ? 'bg-foreground text-background shadow-sm' : 'text-muted-foreground hover:bg-muted hover:text-foreground',
];

const accionFila = (accion: string, ficha: any) => {
    data.FichaTecnicaMaterialeso = ficha;
    if (accion === 'edit') data.editOpen = true;
    if (accion === 'delete') data.deleteOpen = true;
};

const rutaIndex = new URL(String(route('FichaTecnicaMateriales.index')), window.location.origin).pathname;

router.on('start', (event) => {
    const url = event.detail.visit.url;
    const pathname = url instanceof URL ? url.pathname : new URL(String(url), window.location.origin).pathname;
    if (pathname === rutaIndex) cargando.value = true;
});
router.on('finish', () => {
    cargando.value = false;
});

const recargar = () => {
    router.reload({
        only: ['fichas', 'productosSinFicha', 'materialesSinFicha'],
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="props.title" />

    <AppLayout>
        <div class="flex flex-col gap-4 p-4 sm:p-6">
        <!-- boton colapsar se mueve a la fila del titulo -->
        <div class="flex flex-col gap-2">
            <nav v-if="!breadcrumbCollapsed" class="flex items-center gap-1.5 text-xs text-muted-foreground" aria-label="Ruta de navegación">
                    <Link
                        :href="route('dashboard')"
                        class="rounded-sm transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        Dashboard
                    </Link>
                    <template v-for="(miga, i) in (props.breadcrumbs || [])" :key="i">
                        <ChevronRightIcon class="h-3 w-3 opacity-60" />
                        <Link
                            v-if="miga.href"
                            :href="miga.href"
                            class="rounded-sm transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            {{ miga.label }}
                        </Link>
                        <span v-else class="font-medium text-foreground">{{ miga.label }}</span>
                    </template>
                </nav>

                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div class="min-w-0" v-if="!breadcrumbCollapsed">
                        <h1 class="text-2xl font-semibold tracking-tight text-foreground">
                            {{ props.title }}
                        </h1>
                        <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground" role="status">
                            <span class="tabular-nums">
                                <span class="font-medium text-foreground">{{ totales.fichas }}</span>
                                {{ totales.fichas === 1 ? 'ficha' : 'fichas' }}
                            </span>
                            <span class="text-border">·</span>
                            <span class="tabular-nums">
                                <span class="font-medium text-amber-600 dark:text-amber-400">{{ totales.productos }}</span>
                                {{ totales.productos === 1 ? 'producto' : 'productos' }} sin ficha
                            </span>
                            <span class="text-border">·</span>
                            <span class="tabular-nums">
                                <span class="font-medium text-amber-600 dark:text-amber-400">{{ totales.materiales }}</span>
                                {{ totales.materiales === 1 ? 'material' : 'materiales' }} con proceso sin ficha
                            </span>
                        </p>
                    </div>

                    <button
                        @click="breadcrumbCollapsed = !breadcrumbCollapsed"
                        class="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                        :aria-label="breadcrumbCollapsed ? 'Mostrar título y breadcrumb' : 'Ocultar título y breadcrumb'"
                    >
                        <ChevronDownIcon v-if="breadcrumbCollapsed" class="h-4 w-4" />
                        <ChevronUpIcon v-else class="h-4 w-4" />
                    </button>

                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            @click="recargar"
                            :disabled="cargando"
                            title="Actualizar datos"
                            aria-label="Actualizar datos"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:pointer-events-none disabled:opacity-50"
                        >
                            <ArrowPathIcon class="h-4 w-4" :class="{ 'animate-spin': cargando }" />
                        </button>

                        <Link
                            v-if="can(['create Producto'])"
                            :href="route('Producto.index')"
                            class="inline-flex h-9 items-center gap-1.5 rounded-md border border-border bg-card px-3 text-xs font-medium text-foreground shadow-sm transition hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                        >
                            <PlusIcon class="h-4 w-4" />
                            Nuevo producto
                        </Link>

                        <Link
                            v-if="can(['create Material'])"
                            :href="route('Material.index')"
                            class="inline-flex h-9 items-center gap-1.5 rounded-md border border-border bg-card px-3 text-xs font-medium text-foreground shadow-sm transition hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                        >
                            <PlusIcon class="h-4 w-4" />
                            Nuevo material
                        </Link>
                    </div>
                </div>

                <p class="text-xs text-muted-foreground">
                    La ficha se registra al crear el producto o el material con proceso: cada objetivo pertenece a una
                    sola ficha y queda bloqueado mientras esté en una OP activa.
                </p>
            </div>

            <div
                class="inline-flex w-fit items-stretch divide-x divide-border overflow-hidden rounded-md border border-border bg-card shadow-sm"
                role="tablist"
                aria-label="Listados de fichas técnicas"
            >
                <button type="button" role="tab" :aria-selected="pestana === 'fichas'" :class="segmentoClass(pestana === 'fichas')" @click="pestana = 'fichas'">
                    Fichas técnicas
                    <span class="ml-1.5 tabular-nums opacity-70">{{ totales.fichas }}</span>
                </button>
                <button type="button" role="tab" :aria-selected="pestana === 'productos'" :class="segmentoClass(pestana === 'productos')" @click="pestana = 'productos'">
                    Productos sin ficha
                    <span class="ml-1.5 tabular-nums opacity-70">{{ totales.productos }}</span>
                </button>
                <button type="button" role="tab" :aria-selected="pestana === 'materiales'" :class="segmentoClass(pestana === 'materiales')" @click="pestana = 'materiales'">
                    Materiales con proceso sin ficha
                    <span class="ml-1.5 tabular-nums opacity-70">{{ totales.materiales }}</span>
                </button>
            </div>

            <div v-if="pestana === 'fichas'" class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <div class="flex flex-wrap items-center gap-2 border-b border-border p-3">
                    <div class="relative min-w-[220px] flex-1">
                        <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <input
                            v-model="busqueda"
                            type="text"
                            aria-label="Buscar ficha técnica"
                            placeholder="Buscar por nombre, producto o material…"
                            class="h-9 w-full rounded-md border border-input bg-background pl-9 pr-9 text-sm text-foreground shadow-sm placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30"
                        />
                        <button
                            v-if="busqueda"
                            type="button"
                            @click="limpiar"
                            aria-label="Limpiar búsqueda de fichas"
                            class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-muted-foreground transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <XMarkIcon class="h-3.5 w-3.5" />
                        </button>
                    </div>

                    <select v-model.number="porPagina" aria-label="Fichas por página" :class="selectClass">
                        <option v-for="n in [10, 25, 50, 100]" :key="n" :value="n">{{ n }} / página</option>
                    </select>

                    <button
                        v-if="hayBusqueda"
                        type="button"
                        @click="limpiar"
                        class="inline-flex h-9 items-center gap-1.5 rounded-md border border-border bg-card px-3 text-xs font-medium text-foreground shadow-sm transition hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                    >
                        <XMarkIcon class="h-3.5 w-3.5" />
                        Limpiar
                    </button>
                </div>

                <div class="mat-scroll max-h-[68vh] overflow-auto overscroll-contain">
                    <table class="w-full min-w-[1000px] border-collapse text-sm">
                        <thead>
                            <tr>
                                <th
                                    v-for="col in columnas"
                                    :key="col.key"
                                    scope="col"
                                    :aria-sort="col.sortable ? ariaSort(col.key) : undefined"
                                    :class="[
                                        'bg-muted px-3 py-2.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground',
                                        col.align === 'right' ? 'text-right' : col.align === 'center' ? 'text-center' : 'text-left',
                                        col.headerClass,
                                    ]"
                                >
                                    <button
                                        v-if="col.sortable"
                                        type="button"
                                        @click="ordenarPor(col.key)"
                                        :class="[
                                            'group/sort inline-flex items-center gap-1 rounded-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                                            col.align === 'right' ? 'w-full justify-end' : col.align === 'center' ? 'w-full justify-center' : '',
                                        ]"
                                    >
                                        <span>{{ col.label }}</span>
                                        <span class="text-muted-foreground/60 transition-colors group-hover/sort:text-foreground">
                                            <ChevronUpIcon v-if="indicador(col.key) === 'asc'" class="h-3 w-3" />
                                            <ChevronDownIcon v-else-if="indicador(col.key) === 'desc'" class="h-3 w-3" />
                                            <ChevronUpDownIcon v-else class="h-3 w-3 opacity-0 transition-opacity group-hover/sort:opacity-100" />
                                        </span>
                                    </button>
                                    <span v-else>{{ col.label }}</span>
                                </th>

                                <th
                                    scope="col"
                                    class="sticky right-0 z-30 w-[104px] border-l border-border bg-muted px-2 py-2.5 text-right text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground"
                                >
                                    Acciones
                                </th>
                            </tr>
                        </thead>

                        <tbody v-if="total === 0">
                            <tr>
                                <td :colspan="columnas.length + 1" class="px-6 py-16">
                                    <div class="mx-auto flex max-w-sm flex-col items-center text-center">
                                        <div class="flex h-12 w-12 items-center justify-center rounded-full border border-border bg-muted/60">
                                            <FunnelIcon v-if="hayBusqueda" class="h-5 w-5 text-muted-foreground" />
                                            <RectangleStackIcon v-else class="h-5 w-5 text-muted-foreground" />
                                        </div>
                                        <h3 class="mt-4 text-sm font-semibold text-foreground">
                                            {{ hayBusqueda ? 'Ninguna ficha coincide' : 'Aún no hay fichas técnicas' }}
                                        </h3>
                                        <p class="mt-1 text-sm text-muted-foreground">
                                            {{
                                                hayBusqueda
                                                    ? 'Ajusta la búsqueda para encontrar la ficha.'
                                                    : 'Las fichas se crean desde Producto o desde un material con proceso.'
                                            }}
                                        </p>
                                        <button
                                            v-if="hayBusqueda"
                                            type="button"
                                            @click="limpiar"
                                            class="mt-4 inline-flex items-center gap-1.5 rounded-md border border-border bg-card px-3 py-1.5 text-xs font-medium text-foreground shadow-sm transition hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        >
                                            <XMarkIcon class="h-3.5 w-3.5" />
                                            Limpiar búsqueda
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>

                        <tbody v-else :key="paginaActual" class="mat-page">
                            <tr
                                v-for="ficha in visibles"
                                :key="ficha.id"
                                class="group border-b border-border/60 transition-colors last:border-0 hover:bg-muted/40"
                            >
                                <td class="px-3 py-3 align-top">
                                    <div class="flex max-w-[260px] flex-col gap-0.5">
                                        <span class="truncate font-medium text-foreground" :title="ficha.nombre">
                                            {{ texto(ficha.nombre) }}
                                        </span>
                                        <span class="text-[11px] text-muted-foreground">
                                            Actualizada {{ fechaCorta(ficha.updated_at) }}
                                        </span>
                                    </div>
                                </td>

                                <td class="px-3 py-3 align-top">
                                    <div class="flex max-w-[360px] flex-col gap-0.5">
                                        <span
                                            v-if="ficha.productos_nombres"
                                            class="truncate text-sm text-foreground"
                                            :title="ficha.productos_nombres"
                                        >
                                            {{ nombreCorto(ficha.productos_nombres) }}
                                        </span>
                                        <span
                                            v-if="ficha.materiales_procesados_nombres"
                                            class="truncate text-[11px] font-medium text-amber-700 dark:text-amber-300"
                                            :title="ficha.materiales_procesados_nombres"
                                        >
                                            {{ nombreCorto(ficha.materiales_procesados_nombres) }}
                                        </span>
                                        <span
                                            v-if="!ficha.productos_nombres && !ficha.materiales_procesados_nombres"
                                            class="text-xs text-muted-foreground"
                                        >
                                            Sin objetivos asignados
                                        </span>
                                        <span class="text-[11px] tabular-nums text-muted-foreground">
                                            {{ ficha.productos_count }} producto{{ Number(ficha.productos_count) === 1 ? '' : 's' }}
                                            · {{ ficha.materiales_procesados_count }} material{{ Number(ficha.materiales_procesados_count) === 1 ? '' : 'es' }} con proceso
                                        </span>
                                    </div>
                                </td>

                                <td class="px-3 py-3 text-center align-top">
                                    <button
                                        type="button"
                                        @click="(data.fichaVisible = ficha), (data.materialesOpen = true)"
                                        title="Ver materiales requeridos"
                                        class="inline-flex items-center gap-1.5 rounded-full border border-border bg-muted/60 px-2.5 py-1 text-xs font-medium text-foreground shadow-sm transition hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                    >
                                        <span class="tabular-nums">{{ ficha.materiales_count }}</span>
                                        <CubeIcon class="h-3.5 w-3.5" />
                                    </button>
                                </td>

                                <td class="px-3 py-3 text-right align-top">
                                    <span class="font-semibold tabular-nums text-foreground">
                                        {{ formatearPrecio(ficha.valor_total_ficha) }}
                                    </span>
                                </td>

                                <td
                                    class="sticky right-0 z-10 w-[104px] border-l border-border/60 bg-card px-2 py-2 align-middle shadow-[-8px_0_16px_-16px_rgba(0,0,0,0.35)] transition-colors group-hover:bg-muted/40"
                                >
                                    <div class="flex items-center justify-end gap-1">
                                        <button
                                            v-if="can(['update FichaTecnicaMateriales'])"
                                            type="button"
                                            @click="accionFila('edit', ficha)"
                                            title="Editar"
                                            :aria-label="`Editar ${ficha.nombre}`"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        >
                                            <PencilIcon class="h-4 w-4" />
                                        </button>
                                        <button
                                            v-if="can(['delete FichaTecnicaMateriales'])"
                                            type="button"
                                            @click="accionFila('delete', ficha)"
                                            title="Eliminar"
                                            :aria-label="`Eliminar ${ficha.nombre}`"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-red-50 hover:text-red-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring dark:hover:bg-red-500/10"
                                        >
                                            <TrashIcon class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <DataPagination
                    :current-page="paginaActual"
                    :last-page="paginas"
                    :from="desde"
                    :to="hasta"
                    :total="total"
                    :item-label="total === 1 ? 'ficha' : 'fichas'"
                    @page="cambiarPagina"
                />
            </div>

            <ProductosSinFichaTabla
                v-else-if="pestana === 'productos'"
                :productos="props.productosSinFicha"
                :los-select="props.selectProducto"
                :titulos="props.titulosProducto"
                :number-permissions="props.numberPermissions"
                :title="props.title"
            />

            <MaterialesSinFichaTabla
                v-else
                :materiales="props.materialesSinFicha"
                :los-select="props.selectMaterial"
                :titulos="props.titulosMaterial"
                :number-permissions="props.numberPermissions"
                :title="props.title"
            />
        </div>

        <Edit
            v-if="can(['update FichaTecnicaMateriales'])"
            :numberPermissions="props.numberPermissions"
            :show="data.editOpen"
            @close="data.editOpen = false"
            :FichaTecnicaMaterialesa="data.FichaTecnicaMaterialeso"
            :title="props.title"
            :losSelect="props.losSelect"
        />

        <Delete
            v-if="can(['delete FichaTecnicaMateriales'])"
            :numberPermissions="props.numberPermissions"
            :show="data.deleteOpen"
            @close="data.deleteOpen = false"
            :FichaTecnicaMaterialesa="data.FichaTecnicaMaterialeso"
            :title="props.title"
        />

        <MaterialesModal
            :show="data.materialesOpen"
            @close="data.materialesOpen = false"
            :title="props.title"
            :ficha="data.fichaVisible"
        />
    </AppLayout>
</template>

<style scoped>
@keyframes mat-page-in {
    from {
        opacity: 0;
        transform: translateY(3px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.mat-page {
    animation: mat-page-in 180ms cubic-bezier(0.22, 1, 0.36, 1);
}

.mat-scroll {
    scrollbar-width: thin;
    scrollbar-color: var(--border) transparent;
}

.mat-scroll::-webkit-scrollbar {
    width: 10px;
    height: 10px;
}

.mat-scroll::-webkit-scrollbar-track {
    background: transparent;
}

.mat-scroll::-webkit-scrollbar-thumb {
    background: var(--border);
    border: 3px solid transparent;
    border-radius: 9999px;
    background-clip: content-box;
}

@media (prefers-reduced-motion: reduce) {
    .mat-page {
        animation: none;
    }
}
</style>
