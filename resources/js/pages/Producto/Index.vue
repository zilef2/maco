<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import DataPagination from '@/components/DataPagination.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

import {
    ArrowPathIcon,
    ArrowUpTrayIcon,
    ChevronDownIcon,
    ChevronRightIcon,
    ChevronUpDownIcon,
    ChevronUpIcon,
    CubeIcon,
    EllipsisVerticalIcon,
    FunnelIcon,
    MagnifyingGlassIcon,
    PencilIcon,
    PlusIcon,
    SquaresPlusIcon,
    TrashIcon,
    XMarkIcon,
} from '@heroicons/vue/24/solid';

import Create from '@/pages/Producto/Create.vue';
import Edit from '@/pages/Producto/Edit.vue';
import Delete from '@/pages/Producto/Delete.vue';
import ImportarProductoModal from '@/pages/Producto/ImportarProductoModal.vue';
import DistribucionTallas from '@/pages/Producto/DistribucionTallas.vue';

import { zilef_nuber_format } from '@/global.ts';

const props = defineProps({
    productos: { type: Array as () => any[], default: () => [] },
    total: Number,
    breadcrumbs: { type: Array as () => Array<{ label?: string; href?: string }>, default: () => [] },
    perPage: Number,
    title: String,
    numberPermissions: Number,
    losSelect: Object,
    titulos: Array,
});

/* --------------------------------------------------------------------------
 * Tabla 100% en cliente: los productos llegan una sola vez y aquí se buscan,
 * filtran, ordenan y paginan sin volver al backend. Solo las acciones de
 * cada fila (tallas, editar, eliminar, cargar Excel) hablan con él.
 * ------------------------------------------------------------------------ */

const filas = computed<any[]>(() => props.productos ?? []);

const busqueda = ref('');
const estadoFiltro = ref('todos');
const categoriaFiltro = ref('todos');
const familiaFiltro = ref('todos');
const controlVarianteFiltro = ref('todos');
const opcionFiltro = ref('');
const precioPreventaFiltro = ref('todos');
const precioEstimadoFiltro = ref('todos');
const tallasFiltro = ref('todos');
const cantidadFiltro = ref('todos');
const fichaFiltro = ref('todos');

const sortKey = ref('updated_at');
const sortDir = ref<'asc' | 'desc'>('desc');
const porPagina = ref<number>(props.perPage ?? 10);
const pagina = ref(1);
const cargando = ref(false);

const columnas = [
    { key: 'Codigo_interno', label: 'Código', sortable: true, align: 'left', headerClass: 'min-w-[120px]', cellClass: 'min-w-[120px]' },
    { key: 'Titulo_Shopify', label: 'Producto', sortable: true, align: 'left', headerClass: 'min-w-[260px]', cellClass: 'min-w-[260px]' },
    { key: 'cantidad', label: 'Cantidad', sortable: false, align: 'right', headerClass: 'w-[100px]', cellClass: 'w-[100px]' },
    { key: 'Categoria_original', label: 'Categoría', sortable: true, align: 'left', headerClass: 'hidden min-w-[190px] lg:table-cell', cellClass: 'hidden min-w-[190px] lg:table-cell' },
    { key: 'Control_de_variante', label: 'Variante', sortable: true, align: 'left', headerClass: 'hidden min-w-[170px] xl:table-cell', cellClass: 'hidden min-w-[170px] xl:table-cell' },
    { key: 'Estado', label: 'Estado', sortable: true, align: 'left', headerClass: 'w-[110px]', cellClass: 'w-[110px]' },
    { key: 'precio_preventa', label: 'Precio', sortable: true, align: 'right', headerClass: 'w-[140px]', cellClass: 'w-[140px]' },
    { key: 'precio_estimado', label: 'Precio estimado', sortable: true, align: 'right', headerClass: 'w-[150px]', cellClass: 'w-[150px]' },
    { key: 'tallas', label: 'Tallas', sortable: false, align: 'left', headerClass: 'hidden min-w-[150px] md:table-cell', cellClass: 'hidden min-w-[150px] md:table-cell' },
] as const;

const titulos = [
    { order: 'Codigo_interno', label: 'Codigo_interno', type: 'string', default: 'COD-001' },
    { order: 'ID_producto_Shopify', label: 'ID_producto_Shopify', type: 'string' },
    { order: 'Categoria_original', label: 'Categoria_original', type: 'string' },
    { order: 'Familia_normalizada', label: 'Familia_normalizada', type: 'string' },
    { order: 'Titulo_Shopify', label: 'Titulo_Shopify', type: 'string' },
    { order: 'Control_de_variante', label: 'Control_de_variante', type: 'string' },
    { order: 'Opciones', label: 'Opciones', type: 'string' },
    { order: 'Estado', label: 'Estado', type: 'string' },
    { order: 'Observaciones', label: 'Observaciones', type: 'string' },
    { order: 'descripcion', label: 'descripcion', type: 'text' },
    { order: 'precio_predicho', label: 'precio_predicho', type: 'number' },
    { order: 'precio_preventa', label: 'precio_preventa', type: 'number' },
    { order: 'precio_estimado', label: 'precio_estimado', type: 'number' },
];

const data = reactive({
    Productoo: null as any,
    createOpen: false,
    editOpen: false,
    deleteOpen: false,
    importOpen: false,
    distribucionOpen: false,
    distribucionProducto: null as any,
});

const selectClass = 'h-9 rounded-md border border-input bg-background px-2 text-sm text-foreground shadow-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30';

const normalizar = (valor: any): string =>
    String(valor ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/\s+/g, ' ')
        .trim();

const estadoNormalizado = (valor: any): 'Activo' | 'Inactivo' => {
    const texto = normalizar(valor);
    return ['1', 'activo', 'activa', 'true', 'si', 'sí'].includes(texto) ? 'Activo' : 'Inactivo';
};

const ESTADO_ESTILOS = {
    activo: {
        dot: 'bg-emerald-500',
        badge: 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300',
    },
    inactivo: {
        dot: 'bg-slate-400',
        badge: 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-500/30 dark:bg-slate-500/10 dark:text-slate-300',
    },
};

const estadoEstilo = (valor: any) =>
    ESTADO_ESTILOS[estadoNormalizado(valor) === 'Activo' ? 'activo' : 'inactivo'];

const opcionesDeFiltro = (campo: string): Array<{ value: string; label: string }> => {
    const items = ((props.opcionesFiltro as any)?.[campo] ?? []) as any[];
    const vistos = new Set<string>();

    return items
        .map((item: any) => String(item ?? '').trim())
        .filter((valor: string) => {
            if (valor === '') return false;

            const clave = normalizar(valor);
            if (vistos.has(clave)) return false;

            vistos.add(clave);
            return true;
        })
        .sort((a: string, b: string) => a.localeCompare(b, 'es'))
        .map((valor: string) => ({ value: valor, label: valor }));
};

const categoriasDisponibles = computed(() => opcionesDeFiltro('categorias'));
const familiasDisponibles = computed(() => opcionesDeFiltro('familias'));
const controlesVarianteDisponibles = computed(() => opcionesDeFiltro('controles_variante'));

const opcionesEstado = [
    { value: '', label: 'Ambos' },
    { value: 'activo', label: 'Activos' },
    { value: 'inactivo', label: 'Inactivos' },
];

const opcionesPrecio = [
    { value: '', label: 'Todos' },
    { value: 'con_precio', label: 'Con precio' },
    { value: 'sin_precio', label: 'Sin precio' },
];

const opcionesTallas = [
    { value: '', label: 'Todas' },
    { value: 'con', label: 'Con tallas' },
    { value: 'sin', label: 'Sin tallas' },
];

const opcionesCantidad = [
    { value: '', label: 'Todas' },
    { value: 'con', label: 'Con cantidad' },
    { value: 'sin', label: 'Sin cantidad' },
];

const opcionesFicha = [
    { value: '', label: 'Todas' },
    { value: 'con', label: 'Con ficha' },
    { value: 'sin', label: 'Sin ficha' },
];

const formatearPrecio = (valor: any, decimales = 0): string =>
    valor === null || valor === undefined || valor === '' ? '—' : zilef_nuber_format(valor, decimales, true);

// El precio estimado es decimal: solo muestra centavos cuando realmente los trae.
const decimalesDe = (valor: any): number =>
    Number.isFinite(Number(valor)) && Math.round(Number(valor) * 100) % 100 !== 0 ? 2 : 0;

const texto = (valor: any, fallback = '—'): string => {
    const resultado = String(valor ?? '').trim();
    return resultado !== '' ? resultado : fallback;
};

const tallasDe = (producto: any): string[] => {
    const lista = producto?.distribuciones_por_tallas ?? producto?.distribucionesPorTallas ?? [];
    if (!Array.isArray(lista)) return [];
    return Array.from(
        new Set(
            lista
                .map((talla: any) => String(talla?.talla ?? '').trim())
                .filter((talla: string) => talla !== ''),
        ),
    );
};

// Desglose que valida la columna Cantidad: «S 10 · M 5».
const detalleTallasDe = (producto: any): string => {
    const lista = producto?.distribuciones_por_tallas ?? producto?.distribucionesPorTallas ?? [];
    if (!Array.isArray(lista)) return '';
    return lista
        .map((item: any) => {
            const talla = String(item?.talla ?? '').trim();
            const cantidad = Number(item?.cantidad_planificada ?? 0);
            return talla === '' || Number.isNaN(cantidad) ? '' : `${talla} ${cantidad}`;
        })
        .filter(Boolean)
        .join(' · ');
};

const indicador = (key: string) => (sortKey.value === key ? sortDir.value : null);
const ariaSort = (key: string): 'ascending' | 'descending' | 'none' =>
    sortKey.value !== key ? 'none' : sortDir.value === 'asc' ? 'ascending' : 'descending';

const ordenarPor = (key: string) => {
    if (sortKey.value === key) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = key;
        sortDir.value = 'asc';
    }
};

const accionFila = (accion: string, producto: any) => {
    data.Productoo = producto;

    if (accion === 'edit') data.editOpen = true;
    if (accion === 'tallas') {
        data.distribucionProducto = producto;
        data.distribucionOpen = true;
    }
    if (accion === 'delete') data.deleteOpen = true;
};

const limpiarFiltros = () => {
    params.search = '';
    params.estado = '';
    params.categoria = '';
    params.familia = '';
    params.control_variante = '';
    params.opcion = '';
    params.precio_preventa = '';
    params.precio_estimado = '';
    params.tallas = '';
    params.cantidad = '';
    params.ficha = '';
};

const rutaIndex = new URL(String(route('Producto.index')), window.location.origin).pathname;

router.on('start', (event) => {
    const url = event.detail.visit.url;
    const pathname = url instanceof URL ? url.pathname : new URL(String(url), window.location.origin).pathname;
    if (pathname === rutaIndex) cargando.value = true;
});
router.on('finish', () => {
    cargando.value = false;
});

const recargar = () => {
    router.reload({ only: ['fromController', 'total', 'filters', 'perPage', 'opcionesFiltro'], preserveScroll: true });
};

const cambiarPagina = (page: number) => {
    router.get(
        route('Producto.index'),
        pickBy({ ...params, field: sortKey.value, order: sortDir.value, page }),
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

watch(
    () => ({ ...params, field: sortKey.value, order: sortDir.value }),
    debounce(() => {
        router.get(
            route('Producto.index'),
            pickBy({ ...params, field: sortKey.value, order: sortDir.value }),
            { replace: true, preserveState: true, preserveScroll: true },
        );
    }, 200),
    { deep: true },
);
</script>

<template>
    <Head :title="props.title" />

    <AppLayout>
        <div class="flex flex-col gap-4 p-4 sm:p-6">
            <div class="flex flex-col gap-3">
                <nav class="flex items-center gap-1.5 text-xs text-muted-foreground" aria-label="Ruta de navegación">
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
                    <div class="min-w-0">
                        <h1 class="text-2xl font-semibold tracking-tight text-foreground">Productos</h1>
                        <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground" role="status" aria-live="polite">
                            <span class="tabular-nums">
                                <span class="font-medium text-foreground">{{ totalRegistros }}</span>
                                {{ totalRegistros === 1 ? 'producto' : 'productos' }}
                            </span>
                            <span v-if="totalRegistros !== filas.length" class="text-border">·</span>
                            <span v-if="totalRegistros !== filas.length" class="tabular-nums">
                                <span class="font-medium text-foreground">{{ filas.length }}</span>
                                en esta página
                            </span>
                            <span
                                v-if="hayFiltros"
                                class="inline-flex items-center gap-1 rounded-full border border-border bg-muted/60 px-2 py-0.5 text-[11px] font-medium text-foreground"
                            >
                                <FunnelIcon class="h-3 w-3" />
                                {{ filtrosActivos }}
                                {{ filtrosActivos === 1 ? 'filtro activo' : 'filtros activos' }}
                            </span>
                        </p>
                    </div>

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

                        <PrimaryButton
                            v-if="can(['create Producto'])"
                            type="button"
                            class="h-9 bg-emerald-600 hover:bg-emerald-700 focus:ring-emerald-500"
                            @click="data.importOpen = true"
                        >
                            <ArrowUpTrayIcon class="mr-1.5 h-4 w-4" />
                            Cargar Excel
                        </PrimaryButton>

                        <PrimaryButton
                            v-if="can(['create Producto'])"
                            type="button"
                            class="h-9"
                            @click="data.createOpen = true"
                        >
                            <PlusIcon class="mr-1.5 h-4 w-4" />
                            Nuevo producto
                        </PrimaryButton>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <div class="flex flex-col gap-2.5 border-b border-border p-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="relative min-w-[220px] flex-1">
                            <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <input
                                v-model="params.search"
                                type="search"
                                aria-label="Buscar productos"
                                placeholder="Buscar por código o título…"
                                class="h-9 w-full rounded-md border border-input bg-background pl-9 pr-9 text-sm text-foreground shadow-sm placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30"
                            />
                            <button
                                v-if="params.search"
                                type="button"
                                @click="params.search = ''"
                                aria-label="Limpiar búsqueda"
                                class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-muted-foreground transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            >
                                <XMarkIcon class="h-3.5 w-3.5" />
                            </button>
                        </div>

                        <select
                            v-model="params.categoria"
                            aria-label="Filtrar por categoría"
                            :class="[selectClass, 'max-w-[220px]']"
                        >
                            <option value="">Todas las categorías</option>
                            <option v-for="opcion in categoriasDisponibles" :key="opcion.value" :value="opcion.value">
                                {{ opcion.label }}
                            </option>
                        </select>

                        <div class="flex items-center gap-1.5">
                            <span class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Estado</span>
                            <div
                                class="inline-flex h-9 items-stretch overflow-hidden rounded-md border border-border bg-card shadow-sm"
                                role="group"
                                aria-label="Filtrar productos por estado"
                            >
                                <button
                                    v-for="opcion in opcionesEstado"
                                    :key="opcion.value || 'todos'"
                                    type="button"
                                    @click="params.estado = opcion.value"
                                    :aria-pressed="params.estado === opcion.value"
                                    :class="segmentoClass(params.estado === opcion.value)"
                                >
                                    {{ opcion.label }}
                                </button>
                            </div>
                        </div>

                        <DropdownMenu>
                            <DropdownMenuTrigger
                                type="button"
                                :class="[
                                    'inline-flex h-9 items-center gap-1.5 rounded-md border border-border bg-card px-3 text-xs font-medium text-foreground shadow-sm transition hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background',
                                    filtrosAvanzados > 0 ? 'border-foreground/20 bg-muted' : '',
                                ]"
                            >
                                <FunnelIcon class="h-3.5 w-3.5" />
                                Más filtros
                                <span v-if="filtrosAvanzados > 0" class="tabular-nums">{{ filtrosAvanzados }}</span>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" class="max-h-[70vh] w-72 overflow-y-auto p-3">
                                <div class="flex flex-col gap-2.5" @click.stop>
                                    <label class="flex flex-col gap-1 text-xs font-medium text-muted-foreground">
                                        Familia
                                        <select v-model="params.familia" :class="selectClass">
                                            <option value="">Todas</option>
                                            <option v-for="opcion in familiasDisponibles" :key="opcion.value" :value="opcion.value">
                                                {{ opcion.label }}
                                            </option>
                                        </select>
                                    </label>

                                    <label class="flex flex-col gap-1 text-xs font-medium text-muted-foreground">
                                        Control de variante
                                        <select v-model="params.control_variante" :class="selectClass">
                                            <option value="">Todos</option>
                                            <option v-for="opcion in controlesVarianteDisponibles" :key="opcion.value" :value="opcion.value">
                                                {{ opcion.label }}
                                            </option>
                                        </select>
                                    </label>

                                    <label class="flex flex-col gap-1 text-xs font-medium text-muted-foreground">
                                        Opciones contiene
                                        <input
                                            v-model="params.opcion"
                                            type="search"
                                            placeholder="Ej. S, M, L o 21-30"
                                            :class="selectClass"
                                        />
                                    </label>

                                    <label class="flex flex-col gap-1 text-xs font-medium text-muted-foreground">
                                        Precio preventa
                                        <select v-model="params.precio_preventa" :class="selectClass">
                                            <option v-for="opcion in opcionesPrecio" :key="opcion.value" :value="opcion.value">
                                                {{ opcion.label }}
                                            </option>
                                        </select>
                                    </label>

                                    <label class="flex flex-col gap-1 text-xs font-medium text-muted-foreground">
                                        Precio estimado
                                        <select v-model="params.precio_estimado" :class="selectClass">
                                            <option v-for="opcion in opcionesPrecio" :key="opcion.value" :value="opcion.value">
                                                {{ opcion.label }}
                                            </option>
                                        </select>
                                    </label>

                                    <label class="flex flex-col gap-1 text-xs font-medium text-muted-foreground">
                                        Tallas
                                        <select v-model="params.tallas" :class="selectClass">
                                            <option v-for="opcion in opcionesTallas" :key="opcion.value" :value="opcion.value">
                                                {{ opcion.label }}
                                            </option>
                                        </select>
                                    </label>

                                    <label class="flex flex-col gap-1 text-xs font-medium text-muted-foreground">
                                        Cantidad planificada
                                        <select v-model="params.cantidad" :class="selectClass">
                                            <option v-for="opcion in opcionesCantidad" :key="opcion.value" :value="opcion.value">
                                                {{ opcion.label }}
                                            </option>
                                        </select>
                                    </label>

                                    <label class="flex flex-col gap-1 text-xs font-medium text-muted-foreground">
                                        Ficha técnica
                                        <select v-model="params.ficha" :class="selectClass">
                                            <option v-for="opcion in opcionesFicha" :key="opcion.value" :value="opcion.value">
                                                {{ opcion.label }}
                                            </option>
                                        </select>
                                    </label>
                                </div>
                            </DropdownMenuContent>
                        </DropdownMenu>

                        <select v-model.number="params.perPage" aria-label="Registros por página" :class="selectClass">
                            <option v-for="n in [10, 25, 50, 100]" :key="n" :value="n">{{ n }} / página</option>
                        </select>

                        <button
                            v-if="hayFiltros"
                            type="button"
                            @click="limpiarFiltros"
                            class="inline-flex h-9 items-center gap-1.5 rounded-md border border-border bg-card px-3 text-xs font-medium text-foreground shadow-sm transition hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                        >
                            <XMarkIcon class="h-3.5 w-3.5" />
                            Limpiar
                        </button>
                    </div>
                </div>

                <div class="mat-scroll max-h-[68vh] overflow-auto overscroll-contain">
                    <table class="w-full min-w-[1330px] border-collapse text-sm">
                        <thead>
                            <tr>
                                <th
                                    v-for="col in columnas"
                                    :key="col.key"
                                    scope="col"
                                    :aria-sort="col.sortable ? ariaSort(col.key) : undefined"
                                    :class="[
                                        'bg-muted px-3 py-2.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground',
                                        col.align === 'right' ? 'text-right' : 'text-left',
                                        col.headerClass,
                                    ]"
                                >
                                    <button
                                        v-if="col.sortable"
                                        type="button"
                                        @click="ordenarPor(col.key)"
                                        :class="[
                                            'group/sort inline-flex items-center gap-1 rounded-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background',
                                            col.align === 'right' ? 'w-full justify-end' : '',
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

                        <tbody v-if="cargando">
                            <tr v-for="n in 8" :key="'skeleton-' + n" class="border-b border-border/60">
                                <td :colspan="columnas.length + 1" class="px-4 py-3">
                                    <div class="flex items-center gap-4">
                                        <div class="h-3.5 w-20 animate-pulse rounded bg-muted"></div>
                                        <div class="h-3.5 w-56 animate-pulse rounded bg-muted"></div>
                                        <div class="h-3.5 flex-1 animate-pulse rounded bg-muted"></div>
                                        <div class="h-3.5 w-24 animate-pulse rounded bg-muted"></div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>

                        <tbody v-else-if="filas.length" :key="paginaActual" class="mat-page">
                            <tr
                                v-for="producto in filas"
                                :key="producto.id"
                                class="group border-b border-border/60 transition-colors last:border-0 hover:bg-muted/40"
                            >
                                <td class="px-3 py-3 align-top">
                                    <div class="flex flex-col gap-0.5">
                                        <span class="font-semibold tabular-nums tracking-tight text-foreground">
                                            {{ texto(producto.Codigo_interno) }}
                                        </span>
                                        <span
                                            v-if="producto.ID_producto_Shopify"
                                            class="block max-w-[140px] truncate text-[10px] text-muted-foreground"
                                            :title="producto.ID_producto_Shopify"
                                        >
                                            {{ producto.ID_producto_Shopify }}
                                        </span>
                                    </div>
                                </td>

                                <td class="px-3 py-3 align-top">
                                    <div class="flex max-w-[300px] flex-col gap-0.5">
                                        <span class="truncate font-medium text-foreground" :title="producto.Titulo_Shopify">
                                            {{ texto(producto.Titulo_Shopify) }}
                                        </span>
                                        <span class="truncate text-[11px] text-muted-foreground" :title="producto.descripcion">
                                            {{ texto(producto.descripcion, 'Sin descripción') }}
                                        </span>
                                    </div>
                                </td>

                                <td class="px-3 py-3 text-right align-top">
                                    <div class="flex flex-col items-end gap-0.5">
                                        <span class="font-semibold tabular-nums text-foreground">
                                            {{ Number(producto.cantidad ?? 0) }}
                                        </span>
                                        <span
                                            v-if="detalleTallasDe(producto)"
                                            class="max-w-[130px] truncate text-[11px] tabular-nums text-muted-foreground"
                                            :title="detalleTallasDe(producto)"
                                        >
                                            {{ detalleTallasDe(producto) }}
                                        </span>
                                    </div>
                                </td>

                                <td class="hidden px-3 py-3 align-top lg:table-cell">
                                    <div class="flex max-w-[210px] flex-col gap-0.5">
                                        <span class="truncate text-sm text-foreground" :title="producto.Categoria_original">
                                            {{ texto(producto.Categoria_original) }}
                                        </span>
                                        <span class="truncate text-[11px] text-muted-foreground" :title="producto.Familia_normalizada">
                                            {{ texto(producto.Familia_normalizada, 'Sin familia') }}
                                        </span>
                                    </div>
                                </td>

                                <td class="hidden px-3 py-3 align-top xl:table-cell">
                                    <div class="flex max-w-[190px] flex-col gap-0.5">
                                        <span class="truncate text-sm text-foreground" :title="producto.Control_de_variante">
                                            {{ texto(producto.Control_de_variante) }}
                                        </span>
                                        <span class="truncate text-[11px] text-muted-foreground" :title="producto.Opciones">
                                            {{ texto(producto.Opciones, 'Sin opciones') }}
                                        </span>
                                    </div>
                                </td>

                                <td class="px-3 py-3 align-top">
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[11px] font-medium"
                                        :class="estadoEstilo(producto.Estado).badge"
                                    >
                                        <span class="h-1.5 w-1.5 rounded-full" :class="estadoEstilo(producto.Estado).dot"></span>
                                        {{ estadoNormalizado(producto.Estado) }}
                                    </span>
                                </td>

                                <td class="px-3 py-3 text-right align-top">
                                    <div class="flex flex-col items-end gap-0.5">
                                        <span class="font-semibold tabular-nums text-foreground">
                                            {{ formatearPrecio(producto.precio_preventa) }}
                                        </span>
                                        <span class="text-[11px] tabular-nums text-muted-foreground">
                                            Predicho {{ formatearPrecio(producto.precio_predicho) }}
                                        </span>
                                    </div>
                                </td>

                                <td class="px-3 py-3 text-right align-top">
                                    <span class="font-medium tabular-nums text-foreground">
                                        {{ formatearPrecio(producto.precio_estimado, decimalesDe(producto.precio_estimado)) }}
                                    </span>
                                </td>

                                <td class="hidden px-3 py-3 align-top md:table-cell">
                                    <div class="flex max-w-[190px] flex-wrap items-center gap-1">
                                        <span
                                            v-for="talla in tallasDe(producto).slice(0, 3)"
                                            :key="talla"
                                            class="inline-flex min-w-6 items-center justify-center rounded border border-border bg-muted/60 px-1.5 py-0.5 text-[10px] font-semibold text-muted-foreground"
                                        >
                                            {{ talla }}
                                        </span>
                                        <span
                                            v-if="tallasDe(producto).length > 3"
                                            class="inline-flex min-w-6 items-center justify-center rounded border border-border bg-muted/60 px-1.5 py-0.5 text-[10px] font-semibold text-muted-foreground"
                                            :title="tallasDe(producto).slice(3).join(', ')"
                                        >
                                            +{{ tallasDe(producto).length - 3 }}
                                        </span>
                                        <span v-if="!tallasDe(producto).length" class="text-xs text-muted-foreground">—</span>
                                    </div>
                                </td>

                                <td
                                    class="sticky right-0 z-10 w-[104px] border-l border-border/60 bg-card px-2 py-2 align-middle shadow-[-8px_0_16px_-16px_rgba(0,0,0,0.35)] transition-colors group-hover:bg-muted/40"
                                >
                                    <div class="flex items-center justify-end gap-1">
                                        <button
                                            v-if="can(['update Producto'])"
                                            type="button"
                                            @click="accionFila('tallas', producto)"
                                            title="Tallas"
                                            :aria-label="`Editar tallas de ${producto.Codigo_interno || producto.Titulo_Shopify}`"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                                        >
                                            <SquaresPlusIcon class="h-4 w-4" />
                                        </button>
                                        <button
                                            v-if="can(['update Producto'])"
                                            type="button"
                                            @click="accionFila('edit', producto)"
                                            title="Editar"
                                            :aria-label="`Editar ${producto.Codigo_interno || producto.Titulo_Shopify}`"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                                        >
                                            <PencilIcon class="h-4 w-4" />
                                        </button>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger
                                                type="button"
                                                title="Acciones"
                                                :aria-label="`Acciones de ${producto.Codigo_interno || producto.Titulo_Shopify}`"
                                                class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                                            >
                                                <EllipsisVerticalIcon class="h-4 w-4" />
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" class="w-56 p-1">
                                                <DropdownMenuItem
                                                    v-if="can(['delete Producto'])"
                                                    @click="accionFila('delete', producto)"
                                                    variant="destructive"
                                                    class="flex cursor-pointer items-center gap-2 px-2.5 py-1.5 text-xs font-medium"
                                                >
                                                    <TrashIcon class="h-4 w-4" />
                                                    <span>Eliminar</span>
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </td>
                            </tr>
                        </tbody>

                        <tbody v-else>
                            <tr>
                                <td :colspan="columnas.length + 1" class="px-6 py-16">
                                    <div class="mx-auto flex max-w-sm flex-col items-center text-center">
                                        <div class="flex h-12 w-12 items-center justify-center rounded-full border border-border bg-muted/60">
                                            <FunnelIcon v-if="hayFiltros" class="h-5 w-5 text-muted-foreground" />
                                            <CubeIcon v-else class="h-5 w-5 text-muted-foreground" />
                                        </div>
                                        <h3 class="mt-4 text-sm font-semibold text-foreground">
                                            {{ hayFiltros ? 'No hay productos que coincidan' : 'Aún no hay productos' }}
                                        </h3>
                                        <p class="mt-1 text-sm text-muted-foreground">
                                            {{
                                                hayFiltros
                                                    ? 'Ajusta los filtros o límpialos para ver más resultados.'
                                                    : 'Crea el primer producto o carga un Excel para empezar.'
                                            }}
                                        </p>
                                        <button
                                            v-if="hayFiltros"
                                            type="button"
                                            @click="limpiarFiltros"
                                            class="mt-4 inline-flex items-center gap-1.5 rounded-md border border-border bg-card px-3 py-1.5 text-xs font-medium text-foreground shadow-sm transition hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                                        >
                                            <XMarkIcon class="h-3.5 w-3.5" />
                                            Limpiar filtros
                                        </button>
                                        <PrimaryButton
                                            v-else-if="can(['create Producto'])"
                                            type="button"
                                            class="mt-4"
                                            @click="data.createOpen = true"
                                        >
                                            <PlusIcon class="mr-1.5 h-4 w-4" />
                                            Crear el primer producto
                                        </PrimaryButton>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <DataPagination
                    v-if="!cargando"
                    :current-page="paginaActual"
                    :last-page="ultimaPagina"
                    :from="desde"
                    :to="hasta"
                    :total="totalRegistros"
                    :item-label="totalRegistros === 1 ? 'producto' : 'productos'"
                    @page="cambiarPagina"
                />
            </div>
        </div>

        <ImportarProductoModal :show="data.importOpen" @close="data.importOpen = false" />

        <Create
            v-if="can(['create Producto'])"
            :numberPermissions="props.numberPermissions"
            :titulos="titulos"
            :show="data.createOpen"
            @close="data.createOpen = false"
            :title="props.title"
            :losSelect="props.losSelect"
        />

        <Edit
            v-if="can(['update Producto'])"
            :titulos="titulos"
            :numberPermissions="props.numberPermissions"
            :show="data.editOpen"
            @close="data.editOpen = false"
            :Productoa="data.Productoo"
            :title="props.title"
            :losSelect="props.losSelect"
        />

        <Delete
            v-if="can(['delete Producto'])"
            :numberPermissions="props.numberPermissions"
            :show="data.deleteOpen"
            @close="data.deleteOpen = false"
            :Productoa="data.Productoo"
            :title="props.title"
        />

        <DistribucionTallas
            v-if="can(['update Producto'])"
            :show="data.distribucionOpen"
            @close="data.distribucionOpen = false"
            :title="props.title"
            :productoId="data.distribucionProducto?.id"
            :productoLabel="data.distribucionProducto?.Codigo_interno"
            :items="data.distribucionProducto?.distribuciones_por_tallas || []"
            :ordenesTallas="data.distribucionProducto?.ordenes_con_tallas || []"
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
