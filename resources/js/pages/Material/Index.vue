<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import PrimaryButton from '@/components/PrimaryButton.vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { computed, reactive, ref, watch } from 'vue';

import Create from '@/pages/Material/Create.vue';
import Edit from '@/pages/Material/Edit.vue';
import Delete from '@/pages/Material/Delete.vue';
import ImportarMaterialModal from '@/pages/Material/ImportarMaterialModal.vue';
import AlterarExistencia from '@/pages/Material/AlterarExistencia.vue';
import HistorialMovimientos from '@/pages/Material/HistorialMovimientos.vue';
import HistorialMultiples from '@/pages/Material/HistorialMultiples.vue';

import {
    ArrowPathIcon,
    ArrowUpTrayIcon,
    ChevronDoubleLeftIcon,
    ChevronDoubleRightIcon,
    ChevronDownIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    ChevronUpDownIcon,
    ChevronUpIcon,
    ClockIcon,
    CubeIcon,
    EllipsisVerticalIcon,
    ExclamationTriangleIcon,
    FunnelIcon,
    MagnifyingGlassIcon,
    PencilIcon,
    PlusIcon,
    TrashIcon,
    XMarkIcon,
} from '@heroicons/vue/24/solid';

import { formatDate, zilef_nuber_format } from '@/global.ts';

// Función para formatear precios
const formatPrice = (price: any): string => {
    if (price === null || price === undefined || price === '') {
        return '—';
    }
    return zilef_nuber_format(price, 2, false);
};

const props = defineProps({
    materiales: { type: Array as () => any[], default: () => [] },
    total: Number,
    breadcrumbs: Array as () => Array<{ label?: string; href?: string }>,
    perPage: Number,
    title: String,
    numberPermissions: Number,
    losSelect: Object,
    titulos: Array,
});

/* --------------------------------------------------------------------------
 * Tabla 100% en cliente: los datos llegan una sola vez y aquí se buscan,
 * filtran, ordenan y paginan sin volver al backend. Solo las acciones de
 * cada fila (editar, existencia, historial, eliminar) hablan con él.
 * ------------------------------------------------------------------------ */

const filas = computed<any[]>(() => (props.materiales as any[]) ?? []);

const busqueda = ref('');
const categoriaFiltro = ref<string | 'todos'>('todos');
const proveedorFiltro = ref<number | 'todos'>('todos');
const estadoFiltro = ref<string | 'todos'>('todos');
const unidadFiltro = ref<string | 'todos'>('todos');
const varianteFiltro = ref<string | 'todos'>('todos');
const precioFiltro = ref<string | 'sin_precio' | 'con_precio'>('todos');
const boolFiltros = reactive<Record<string, '' | '1' | '0'>>({
    maneja_color: '',
    maneja_talla: '',
    maneja_otra_variante: '',
    material_procesado: '',
});
const sortKey = ref('updated_at');
const sortDir = ref<'asc' | 'desc'>('desc');
const porPagina = ref<number>(props.perPage ?? 10);
const pagina = ref(1);

const normalizar = (valor: any): string =>
    String(valor ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/\s+/g, ' ')
        .trim();

const ESTADOS_MATERIAL = ['Activo', 'Inactivo'] as const;
type EstadoMaterial = (typeof ESTADOS_MATERIAL)[number];

/** Unifica cualquier valor histórico a los dos estados permitidos. */
const estadoNormalizado = (valor: any): EstadoMaterial => {
    const texto = normalizar(valor);
    return texto === 'activo' || texto === '1' || texto === 'true' || texto === 'si'
        ? 'Activo'
        : 'Inactivo';
};

const estaProcesado = (valor: any): boolean => Number(valor) === 1;

const preparadas = computed(() =>
    filas.value.map((m: any) => ({
        ...m,
        _estado: estadoNormalizado(m.estado),
        _busqueda: normalizar(
            [
                m.codigo_material,
                m.nombre_base,
                m.categoria,
                m.unidad_inventario,
                estadoNormalizado(m.estado),
                m.variante_atributo_sugerido,
                m.proveedor_nombre,
                m.observaciones,
            ].join(' '),
        ),
    })),
);

const baseFiltrada = computed(() =>
    preparadas.value.filter((m: any) => {
        if (busqueda.value.trim() && !m._busqueda.includes(normalizar(busqueda.value))) {
            return false;
        }
        if (categoriaFiltro.value !== 'todos' && normalizar(m.categoria) !== normalizar(categoriaFiltro.value)) {
            return false;
        }
        if (proveedorFiltro.value !== 'todos' && Number(m.proveedor_habitual) !== Number(proveedorFiltro.value)) {
            return false;
        }
        return true;
    }),
);

const opcionesDe = (campo: string): Array<{ value: string; count: number }> => {
    const grupos = new Map<string, { value: string; count: number }>();
    baseFiltrada.value.forEach((m: any) => {
        const raw = String(m[campo] ?? '').trim();
        if (!raw) return;
        const clave = normalizar(raw);
        const prev = grupos.get(clave);
        if (prev) {
            prev.count++;
        } else {
            grupos.set(clave, { value: raw, count: 1 });
        }
    });
    return Array.from(grupos.values()).sort((a, b) => a.value.localeCompare(b.value, 'es'));
};

const categoriasDisponibles = computed(() => opcionesDe('categoria'));
const unidadesDisponibles = computed(() => opcionesDe('unidad_inventario'));
const variantesDisponibles = computed(() => opcionesDe('variante_atributo_sugerido'));

const estadosDisponibles = computed(() => {
    const conteos: Record<EstadoMaterial, number> = { Activo: 0, Inactivo: 0 };
    baseFiltrada.value.forEach((m: any) => {
        conteos[m._estado as EstadoMaterial]++;
    });

    return ESTADOS_MATERIAL.map((estado) => ({
        value: estado,
        count: conteos[estado],
    }));
});

// Filtro de estado como botón de sí/no/ambos.
const opcionesEstado = computed(() => [
    { value: 'todos', label: 'Ambos', count: baseFiltrada.value.length },
    ...estadosDisponibles.value.map((estado) => ({
        value: estado.value,
        label: estado.value === 'Activo' ? 'Activos' : 'Inactivos',
        count: estado.count,
    })),
]);

const proveedoresDisponibles = computed(() => {
    const mapa = new Map<number, string>();
    filas.value.forEach((m: any) => {
        if (m.proveedor_habitual) {
            mapa.set(Number(m.proveedor_habitual), m.proveedor_nombre || `Proveedor #${m.proveedor_habitual}`);
        }
    });
    return Array.from(mapa.entries())
        .map(([id, nombre]) => ({ id, nombre }))
        .sort((a, b) => a.nombre.localeCompare(b.nombre, 'es'));
});

const filtradas = computed(() =>
    baseFiltrada.value.filter((m: any) => {
        if (estadoFiltro.value !== 'todos' && m._estado !== estadoFiltro.value) {
            return false;
        }
        if (unidadFiltro.value !== 'todos' && normalizar(m.unidad_inventario) !== normalizar(unidadFiltro.value)) {
            return false;
        }
        if (varianteFiltro.value !== 'todos' && normalizar(m.variante_atributo_sugerido) !== normalizar(varianteFiltro.value)) {
            return false;
        }
        // Filtro por precio
        if (precioFiltro.value === 'sin_precio' && m.last_purchase_price !== null && m.last_purchase_price !== undefined) {
            return false;
        }
        if (precioFiltro.value === 'con_precio' && (m.last_purchase_price === null || m.last_purchase_price === undefined)) {
            return false;
        }
        for (const campo of Object.keys(boolFiltros)) {
            const activo = boolFiltros[campo];
            if (activo !== '' && Number(!!m[campo]) !== Number(activo)) {
                return false;
            }
        }
        return true;
    }),
);

const collator = new Intl.Collator('es', { numeric: true, sensitivity: 'base' });

const valorOrden = (m: any, key: string): any => {
    switch (key) {
        case 'material':
            return m.nombre_base ?? '';
        case 'proveedor':
            return m.proveedor_nombre ?? '';
        case 'last_purchase_price':
            return m.last_purchase_price ?? -1; // Poner los sin precio al final
        default:
            return m[key];
    }
};

const ordenadas = computed(() => {
    const dir = sortDir.value === 'asc' ? 1 : -1;
    const key = sortKey.value;

    return [...filtradas.value].sort((a: any, b: any) => {
        const va = valorOrden(a, key);
        const vb = valorOrden(b, key);
        const nuloA = va === null || va === undefined || va === '';
        const nuloB = vb === null || vb === undefined || vb === '';

        if (nuloA && nuloB) return 0;
        if (nuloA) return 1;
        if (nuloB) return -1;
        if (typeof va === 'number' && typeof vb === 'number') return (va - vb) * dir;
        return collator.compare(String(va), String(vb)) * dir;
    });
});

const totalFiltrado = computed(() => ordenadas.value.length);
const paginas = computed(() => Math.max(1, Math.ceil(totalFiltrado.value / porPagina.value)));
const paginaActual = computed(() => Math.min(pagina.value, paginas.value));

const rango = computed(() => {
    if (!totalFiltrado.value) return { desde: 0, hasta: 0 };
    const desde = (paginaActual.value - 1) * porPagina.value + 1;
    const hasta = Math.min(paginaActual.value * porPagina.value, totalFiltrado.value);
    return { desde, hasta };
});

const visibles = computed(() => {
    const inicio = (paginaActual.value - 1) * porPagina.value;
    return ordenadas.value.slice(inicio, inicio + porPagina.value);
});

const paginasVisibles = computed<Array<number | '...'>>(() => {
    const total = paginas.value;
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

const cambiarPagina = (p: number) => {
    pagina.value = Math.min(Math.max(1, p), paginas.value);
};

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
const ariaSort = (key: string): 'ascending' | 'descending' | 'none' =>
    sortKey.value !== key ? 'none' : sortDir.value === 'asc' ? 'ascending' : 'descending';

const filtrosBarra = computed(
    () =>
        (busqueda.value.trim() !== '' ? 1 : 0) +
        (categoriaFiltro.value !== 'todos' ? 1 : 0) +
        (proveedorFiltro.value !== 'todos' ? 1 : 0) +
        (precioFiltro.value !== 'todos' ? 1 : 0) +
        (estadoFiltro.value !== 'todos' ? 1 : 0),
);

const filtrosDropdown = computed(
    () =>
        (unidadFiltro.value !== 'todos' ? 1 : 0) +
        (varianteFiltro.value !== 'todos' ? 1 : 0) +
        Object.values(boolFiltros).filter((v) => v !== '').length,
);

const hayFiltros = computed(() => filtrosBarra.value + filtrosDropdown.value > 0);

// Contar materiales con y sin precio
const materialesConPrecio = computed(() => {
    return filas.value.filter((m: any) => m.last_purchase_price !== null && m.last_purchase_price !== undefined).length;
});
const materialesSinPrecio = computed(() => {
    return filas.value.filter((m: any) => m.last_purchase_price === null || m.last_purchase_price === undefined).length;
});

const limpiarFiltros = () => {
    busqueda.value = '';
    categoriaFiltro.value = 'todos';
    proveedorFiltro.value = 'todos';
    estadoFiltro.value = 'todos';
    unidadFiltro.value = 'todos';
    varianteFiltro.value = 'todos';
    precioFiltro.value = 'todos';
    Object.keys(boolFiltros).forEach((campo) => (boolFiltros[campo] = ''));
};

watch([busqueda, categoriaFiltro, proveedorFiltro, estadoFiltro, unidadFiltro, varianteFiltro, precioFiltro, porPagina], () => {
    pagina.value = 1;
});
watch(boolFiltros, () => {
    pagina.value = 1;
});

watch(paginas, (n) => {
    if (pagina.value > n) pagina.value = n;
});

/* ------------------------------- Estilos -------------------------------- */

const ESTADO_ESTILOS: Record<string, { dot: string; badge: string }> = {
    activo: {
        dot: 'bg-emerald-500',
        badge: 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300',
    },
    inactivo: {
        dot: 'bg-slate-400',
        badge: 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-500/30 dark:bg-slate-500/10 dark:text-slate-300',
    },
    obsoleto: {
        dot: 'bg-rose-500',
        badge: 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300',
    },
    agotado: {
        dot: 'bg-orange-500',
        badge: 'border-orange-200 bg-orange-50 text-orange-800 dark:border-orange-500/30 dark:bg-orange-500/10 dark:text-orange-300',
    },
};

const estiloEstado = (nombre: string) =>
    ESTADO_ESTILOS[normalizar(nombre)] ?? {
        dot: 'bg-muted-foreground/50',
        badge: 'border-border bg-muted text-muted-foreground',
    };

const chipClass = (activo: boolean) => [
    'inline-flex shrink-0 items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background',
    activo
        ? 'border-foreground/15 bg-foreground text-background shadow-sm'
        : 'border-border bg-card text-muted-foreground hover:bg-muted hover:text-foreground',
];

const segmentoClass = (activo: boolean) => [
    'whitespace-nowrap border-l border-border px-2.5 text-[11px] font-medium transition first:border-l-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring',
    activo
        ? 'bg-foreground text-background shadow-sm'
        : 'text-muted-foreground hover:bg-muted hover:text-foreground',
];

const columnas = [
    { key: 'codigo_material', label: 'Código', sortable: true, align: 'left', headerClass: 'min-w-[120px]', cellClass: 'min-w-[120px]' },
    { key: 'material', label: 'Material', sortable: true, align: 'left', headerClass: 'min-w-[200px]', cellClass: 'min-w-[200px]' },
    { key: 'last_purchase_price', label: 'Último precio', sortable: true, align: 'right', headerClass: 'min-w-[140px]', cellClass: 'min-w-[140px]' },
    { key: 'existencia_actual', label: 'Existencia', sortable: true, align: 'right', headerClass: 'w-[120px]', cellClass: 'w-[120px]' },
    { key: 'estado', label: 'Estado', sortable: true, align: 'left', headerClass: 'min-w-[130px]', cellClass: 'min-w-[130px]' },
    { key: 'material_procesado', label: 'Procesado', sortable: true, align: 'left', headerClass: 'min-w-[110px]', cellClass: 'min-w-[110px]' },
    { key: 'proveedor', label: 'Proveedor', sortable: true, align: 'left', headerClass: 'hidden min-w-[140px] md:table-cell', cellClass: 'hidden min-w-[140px] md:table-cell' },
    { key: 'variante_atributo_sugerido', label: 'Variante', sortable: true, align: 'left', headerClass: 'hidden min-w-[120px] xl:table-cell', cellClass: 'hidden min-w-[120px] xl:table-cell' },
    { key: 'variantes', label: 'Maneja', sortable: false, align: 'left', headerClass: 'hidden min-w-[120px] lg:table-cell', cellClass: 'hidden min-w-[120px] lg:table-cell' },
    { key: 'observaciones', label: 'Observaciones', sortable: false, align: 'left', headerClass: 'hidden min-w-[200px] 2xl:table-cell', cellClass: 'hidden min-w-[200px] 2xl:table-cell' },
] as const;

const selectClass = 'h-9 rounded-md border border-input bg-background px-2 text-sm text-foreground shadow-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30';

const opcionesBooleano = [
    { value: '', label: 'Todos' },
    { value: '1', label: 'Sí' },
    { value: '0', label: 'No' },
];

/* ------------------------------- Acciones ------------------------------- */

const data = reactive({
    Materialo: null as any,
    selectedId: [] as number[],
    multipleSelect: false,
    createOpen: false,
    editOpen: false,
    deleteOpen: false,
    importOpen: false,
    alterarExistenciaOpen: false,
    historialOpen: false,
    historialMultipleOpen: false,
    historialMateriales: [] as any[],
});

// Historial de varios materiales: activo solo con 1 a 3 seleccionados
const MAX_HISTORIAL_MULTIPLE = 3;
const puedeHistorialMultiple = computed(
    () => data.selectedId.length >= 1 && data.selectedId.length <= MAX_HISTORIAL_MULTIPLE,
);

const seleccionValidos = computed(() => filas.value.filter((m: any) => data.selectedId.includes(m.id)));

const abrirHistorialMultiple = () => {
    if (!puedeHistorialMultiple.value) return;
    data.historialMateriales = seleccionValidos.value;
    data.historialMultipleOpen = true;
};

const pageIds = computed(() => visibles.value.map((m: any) => m.id));
const todosEnPagina = computed(() => pageIds.value.length > 0 && pageIds.value.every((id) => data.selectedId.includes(id)));

const toggleFila = (id: number) => {
    data.selectedId = data.selectedId.includes(id)
        ? data.selectedId.filter((s) => s !== id)
        : [...data.selectedId, id];
};

const toggleTodos = () => {
    if (todosEnPagina.value) {
        data.selectedId = data.selectedId.filter((id) => !pageIds.value.includes(id));
    } else {
        data.selectedId = Array.from(new Set([...data.selectedId, ...pageIds.value]));
    }
};

const abrirCrear = () => (data.createOpen = true);

const accionFila = (id: string, material: any) => {
    data.Materialo = material;
    if (id === 'edit') data.editOpen = true;
    if (id === 'alterar') data.alterarExistenciaOpen = true;
    if (id === 'historial') data.historialOpen = true;
    if (id === 'delete') data.deleteOpen = true;
};

/* -------------------------- Carga / recarga ----------------------------- */

const cargando = ref(false);
const rutaIndex = new URL(String(route('Material.index')), window.location.origin).pathname;

router.on('start', (event) => {
    const url = event.detail.visit.url;
    const pathname = url instanceof URL ? url.pathname : new URL(String(url), window.location.origin).pathname;
    if (pathname === rutaIndex) cargando.value = true;
});
router.on('finish', () => {
    cargando.value = false;
});

const recargar = () => {
    router.reload({ only: ['materiales', 'total', 'losSelect'], preserveScroll: true });
};

/* Títulos para los modales de crear/editar (mismo contrato de antes). */
const titulos = [
    { order: 'codigo_material', label: 'codigo_material', type: 'string' },
    { order: 'categoria', label: 'categoria', type: 'string' },
    { order: 'nombre_base', label: 'nombre_base', type: 'string' },
    { order: 'unidad_inventario', label: 'unidad_inventario', type: 'string' },
    { order: 'existencia_actual', label: 'existencia_actual', type: 'dinero' },
    { order: 'maneja_color', label: 'maneja_color', type: 'boolean' },
    { order: 'maneja_talla', label: 'maneja_talla', type: 'boolean' },
    { order: 'maneja_otra_variante', label: 'maneja_otra_variante', type: 'boolean' },
    { order: 'estado', label: 'estado', type: 'string' },
    { order: 'variante_atributo_sugerido', label: 'variante_atributo_sugerido', type: 'string' },
    { order: 'proveedor_habitual', label: 'proveedor_habitual', type: 'foreign', nameid: 'proveedor_nombre' },
    { order: 'material_procesado', label: 'material_procesado', type: 'boolean' },
    { order: 'observaciones', label: 'observaciones', type: 'string' },
];
</script>

<template>
    <Head :title="props.title" />

    <AppLayout>
        <div class="flex flex-col gap-4 p-4 sm:p-6">
            <!-- Encabezado -->
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
                        <h1 class="text-2xl font-semibold tracking-tight text-foreground">Materiales</h1>
                        <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground" role="status" aria-live="polite">
                            <span class="tabular-nums">
                                <span class="font-medium text-foreground">{{ totalFiltrado }}</span>
                                de {{ filas.length }} materiales
                            </span>
                            <span
                                v-if="hayFiltros"
                                class="inline-flex items-center gap-1 rounded-full border border-border bg-muted/60 px-2 py-0.5 text-[11px] font-medium text-foreground"
                            >
                                <FunnelIcon class="h-3 w-3" />
                                Filtros activos
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
                            v-if="can(['read Material'])"
                            type="button"
                            class="h-9 bg-indigo-600 hover:bg-indigo-700 focus:ring-indigo-500 disabled:opacity-40"
                            :disabled="!puedeHistorialMultiple"
                            v-tooltip="data.selectedId.length > MAX_HISTORIAL_MULTIPLE
                                ? `Máximo ${MAX_HISTORIAL_MULTIPLE} materiales`
                                : 'Ver historial de los materiales seleccionados'"
                            @click="abrirHistorialMultiple"
                        >
                            <ClockIcon class="mr-1.5 h-4 w-4" />
                            Historial{{ data.selectedId.length ? ` (${data.selectedId.length})` : '' }}
                        </PrimaryButton>

                        <PrimaryButton
                            v-if="can(['create Material'])"
                            type="button"
                            class="h-9 bg-emerald-600 hover:bg-emerald-700 focus:ring-emerald-500"
                            @click="data.importOpen = true"
                        >
                            <ArrowUpTrayIcon class="mr-1.5 h-4 w-4" />
                            Cargar Excel
                        </PrimaryButton>

                        <PrimaryButton v-if="can(['create Material'])" type="button" class="h-9" @click="abrirCrear">
                            <PlusIcon class="mr-1.5 h-4 w-4" />
                            Nuevo material
                        </PrimaryButton>
                    </div>
                </div>
            </div>

            <!-- Superficie principal: barra de filtros + tabla + paginación -->
            <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <!-- Barra de filtros -->
                <div class="flex flex-col gap-2.5 border-b border-border p-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="relative min-w-[220px] flex-1">
                            <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <input
                                v-model="busqueda"
                                type="search"
                                aria-label="Buscar materiales"
                                placeholder="Buscar código, material, categoría, proveedor…"
                                class="h-9 w-full rounded-md border border-input bg-background pl-9 pr-9 text-sm text-foreground shadow-sm placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30"
                            />
                            <button
                                v-if="busqueda"
                                type="button"
                                @click="busqueda = ''"
                                aria-label="Limpiar búsqueda"
                                class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-muted-foreground transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            >
                                <XMarkIcon class="h-3.5 w-3.5" />
                            </button>
                        </div>

                        <select
                            v-model="categoriaFiltro"
                            aria-label="Filtrar por categoría"
                            :class="[selectClass, 'max-w-[190px]']"
                        >
                            <option value="todos">Todas las categorías</option>
                            <option v-for="cat in categoriasDisponibles" :key="cat.value" :value="cat.value">
                                {{ cat.value }} ({{ cat.count }})
                            </option>
                        </select>

                        <select
                            v-model="proveedorFiltro"
                            aria-label="Filtrar por proveedor"
                            :class="[selectClass, 'max-w-[190px]']"
                        >
                            <option value="todos">Todos los proveedores</option>
                            <option v-for="prov in proveedoresDisponibles" :key="prov.id" :value="prov.id">
                                {{ prov.nombre }}
                            </option>
                        </select>

                        <select
                            v-model="precioFiltro"
                            aria-label="Filtrar por precio"
                            :class="[selectClass, 'max-w-[190px]']"
                        >
                            <option value="todos">Todos los precios</option>
                            <option value="con_precio">Con precio</option>
                            <option value="sin_precio">Sin precio</option>
                        </select>

                        <div class="flex items-center gap-1.5">
                            <span class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Estado</span>
                            <div
                                class="inline-flex h-9 items-stretch overflow-hidden rounded-md border border-border bg-card shadow-sm"
                                role="group"
                                aria-label="Filtrar materiales por estado"
                            >
                                <button
                                    v-for="opcion in opcionesEstado"
                                    :key="opcion.value"
                                    type="button"
                                    @click="estadoFiltro = opcion.value"
                                    :aria-pressed="estadoFiltro === opcion.value"
                                    :class="segmentoClass(estadoFiltro === opcion.value)"
                                >
                                    {{ opcion.label }}
                                    <span class="tabular-nums opacity-70">{{ opcion.count }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- Banner rojo para materiales sin precio -->
                        <div v-if="materialesSinPrecio > 0" class="inline-flex items-center gap-2 rounded-full border border-red-500/30 bg-red-50/80 px-3 py-1.5 text-xs font-medium text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300">
                            <ExclamationTriangleIcon class="h-4 w-4" />
                            {{ materialesSinPrecio }} materiales sin precio
                            <button
                                type="button"
                                @click="precioFiltro = 'sin_precio'"
                                class="ml-1 rounded px-1.5 py-0.5 hover:bg-red-200 dark:hover:bg-red-500/20"
                                title="Filtrar materiales sin precio"
                            >
                                Filtrar
                            </button>
                        </div>

                        <DropdownMenu>
                            <DropdownMenuTrigger
                                type="button"
                                :class="chipClass(filtrosDropdown > 0)"
                                class="h-9 rounded-md"
                            >
                                <FunnelIcon class="h-3.5 w-3.5" />
                                Más filtros
                                <span v-if="filtrosDropdown > 0" class="tabular-nums">{{ filtrosDropdown }}</span>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" class="w-64 p-3">
                                <div class="flex flex-col gap-2.5" @click.stop>
                                    <label class="flex flex-col gap-1 text-xs font-medium text-muted-foreground">
                                        Unidad
                                        <select v-model="unidadFiltro" :class="selectClass">
                                            <option value="todos">Todas</option>
                                            <option v-for="u in unidadesDisponibles" :key="u.value" :value="u.value">{{ u.value }}</option>
                                        </select>
                                    </label>
                                    <label class="flex flex-col gap-1 text-xs font-medium text-muted-foreground">
                                        Variante sugerida
                                        <select v-model="varianteFiltro" :class="selectClass">
                                            <option value="todos">Todas</option>
                                            <option v-for="v in variantesDisponibles" :key="v.value" :value="v.value">{{ v.value }}</option>
                                        </select>
                                    </label>
                                    <label class="flex flex-col gap-1 text-xs font-medium text-muted-foreground">
                                        Maneja color
                                        <select v-model="boolFiltros.maneja_color" :class="selectClass">
                                            <option v-for="o in opcionesBooleano" :key="o.value" :value="o.value">{{ o.label }}</option>
                                        </select>
                                    </label>
                                    <label class="flex flex-col gap-1 text-xs font-medium text-muted-foreground">
                                        Maneja talla
                                        <select v-model="boolFiltros.maneja_talla" :class="selectClass">
                                            <option v-for="o in opcionesBooleano" :key="o.value" :value="o.value">{{ o.label }}</option>
                                        </select>
                                    </label>
                                    <label class="flex flex-col gap-1 text-xs font-medium text-muted-foreground">
                                        Otra variante
                                        <select v-model="boolFiltros.maneja_otra_variante" :class="selectClass">
                                            <option v-for="o in opcionesBooleano" :key="o.value" :value="o.value">{{ o.label }}</option>
                                        </select>
                                    </label>
                                    <label class="flex flex-col gap-1 text-xs font-medium text-muted-foreground">
                                        Procesado
                                        <select v-model="boolFiltros.material_procesado" :class="selectClass">
                                            <option v-for="o in opcionesBooleano" :key="o.value" :value="o.value">{{ o.label }}</option>
                                        </select>
                                    </label>
                                </div>
                            </DropdownMenuContent>
                        </DropdownMenu>

                        <select
                            v-model.number="porPagina"
                            aria-label="Filas por página"
                            :class="selectClass"
                        >
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

                <!-- Tabla -->
                <div class="mat-scroll max-h-[68vh] overflow-auto">
                    <table class="w-full min-w-[1080px] border-collapse text-sm">
                        <thead>
                            <tr>
                                <th scope="col" class="w-10 bg-muted px-2 py-2.5 text-center">
                                    <input
                                        type="checkbox"
                                        :checked="todosEnPagina"
                                        @change="toggleTodos"
                                        aria-label="Seleccionar materiales de la página"
                                        class="rounded border-input bg-background text-primary focus:ring-2 focus:ring-ring"
                                    />
                                </th>

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
                                    class="sticky right-0 z-30 w-[136px] border-l border-border bg-muted px-2 py-2.5 text-right text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground"
                                >
                                    Acciones
                                </th>
                            </tr>
                        </thead>

                        <!-- Cargando -->
                        <tbody v-if="cargando">
                            <tr v-for="n in 8" :key="'skeleton-' + n" class="border-b border-border/60">
                                <td :colspan="columnas.length + 2" class="px-4 py-3">
                                    <div class="flex items-center gap-4">
                                        <div class="h-3.5 w-16 animate-pulse rounded bg-muted"></div>
                                        <div class="h-3.5 w-44 animate-pulse rounded bg-muted"></div>
                                        <div class="h-3.5 flex-1 animate-pulse rounded bg-muted"></div>
                                        <div class="h-3.5 w-24 animate-pulse rounded bg-muted"></div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>

                        <!-- Filas -->
                        <tbody v-else-if="visibles.length" :key="paginaActual" class="mat-page">
                            <tr
                                v-for="material in visibles"
                                :key="material.id"
                                class="group border-b border-border/60 transition-colors last:border-0 hover:bg-muted/40"
                                :class="{ 'bg-primary/5': data.selectedId.includes(material.id) }"
                            >
                                <td class="px-2 py-3 text-center align-top">
                                    <input
                                        type="checkbox"
                                        :checked="data.selectedId.includes(material.id)"
                                        @change="toggleFila(material.id)"
                                        :aria-label="`Seleccionar ${material.codigo_material || material.nombre_base}`"
                                        class="rounded border-input bg-background text-primary focus:ring-2 focus:ring-ring"
                                    />
                                </td>

                                <!-- Código -->
                                <td class="px-3 py-3 align-top">
                                    <span class="font-semibold tabular-nums tracking-tight text-foreground">
                                        {{ material.codigo_material || '—' }}
                                    </span>
                                </td>

                                <!-- Material -->
                                <td class="px-3 py-3 align-top">
                                    <div class="flex max-w-[220px] flex-col gap-0.5">
                                        <span class="truncate font-medium text-foreground" :title="material.nombre_base">
                                            {{ material.nombre_base || '—' }}
                                        </span>
                                        <span class="truncate text-[11px] text-muted-foreground">
                                            {{ material.categoria || 'Sin categoría' }}
                                        </span>
                                    </div>
                                </td>

                                <!-- Último precio -->
                                <td class="px-3 py-3 text-right align-top">
                                    <div class="flex flex-col items-end gap-0.5">
                                        <span class="font-semibold tabular-nums text-foreground">
                                            {{ formatPrice(material.last_purchase_price) }}
                                        </span>
                                        <!-- Mostrar los últimos 3 precios si existen -->
                                        <span v-if="material.recent_prices && material.recent_prices.length > 1" class="text-[10px] text-muted-foreground">
                                            Últimos {{ material.recent_prices.length }}: 
                                            {{ material.recent_prices.map((p: any) => formatPrice(p.precio)).join(' | ') }}
                                        </span>
                                        <span v-else class="text-[10px] text-muted-foreground">
                                            {{ material.recent_prices && material.recent_prices.length === 1 ? 'Último registro' : 'Sin histórico' }}
                                        </span>
                                    </div>
                                </td>

                                <!-- Existencia -->
                                <td class="px-3 py-3 text-right align-top">
                                    <div class="flex flex-col items-end gap-0.5">
                                        <span class="font-semibold tabular-nums text-foreground">
                                            {{ zilef_nuber_format(material.existencia_actual, 0, false) }}
                                        </span>
                                        <span class="text-[11px] text-muted-foreground">
                                            {{ material.unidad_inventario || '—' }}
                                        </span>
                                    </div>
                                </td>

                                <!-- Estado -->
                                <td class="px-3 py-3 align-top">
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[11px] font-medium"
                                        :class="estiloEstado(material._estado).badge"
                                    >
                                        <span class="h-1.5 w-1.5 rounded-full" :class="estiloEstado(material._estado).dot"></span>
                                        {{ material._estado }}
                                    </span>
                                </td>

                                <!-- Procesado -->
                                <td class="px-3 py-3 align-top">
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[11px] font-medium"
                                        :class="estaProcesado(material.material_procesado)
                                            ? 'border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-500/30 dark:bg-violet-500/10 dark:text-violet-300'
                                            : 'border-border bg-muted text-muted-foreground'"
                                    >
                                        <!-- SVG de máquina de manufactura -->
                                        <svg v-if="estaProcesado(material.material_procesado)" class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect>
                                            <rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect>
                                            <line x1="6" y1="6" x2="6.01" y2="6"></line>
                                            <line x1="6" y1="18" x2="6.01" y2="18"></line>
                                            <line x1="10" y1="2" x2="10" y2="4"></line>
                                            <line x1="10" y1="20" x2="10" y2="22"></line>
                                            <line x1="14" y1="2" x2="14" y2="4"></line>
                                            <line x1="14" y1="20" x2="14" y2="22"></line>
                                            <line x1="18" y1="6" x2="18" y2="8"></line>
                                            <line x1="18" y1="18" x2="18" y2="20"></line>
                                        </svg>
                                        <span
                                            class="h-1.5 w-1.5 rounded-full"
                                            :class="estaProcesado(material.material_procesado) ? 'bg-violet-500' : 'bg-muted-foreground/50'"
                                        ></span>
                                        {{ estaProcesado(material.material_procesado) ? 'Sí' : 'No' }}
                                    </span>
                                </td>

                                <!-- Proveedor -->
                                <td class="hidden px-3 py-3 align-top md:table-cell">
                                    <span class="text-sm text-foreground">{{ material.proveedor_nombre || '—' }}</span>
                                </td>

                                <!-- Variante -->
                                <td class="hidden px-3 py-3 align-top xl:table-cell">
                                    <span class="text-sm text-foreground">{{ material.variante_atributo_sugerido || '—' }}</span>
                                </td>

                                <!-- Maneja -->
                                <td class="hidden px-3 py-3 align-top lg:table-cell">
                                    <div class="flex items-center gap-1">
                                        <span v-if="material.maneja_color" class="inline-flex min-w-5 items-center justify-center rounded border border-border bg-muted/60 px-1 py-0.5 text-[10px] font-semibold text-muted-foreground" title="Maneja color">C</span>
                                        <span v-if="material.maneja_talla" class="inline-flex min-w-5 items-center justify-center rounded border border-border bg-muted/60 px-1 py-0.5 text-[10px] font-semibold text-muted-foreground" title="Maneja talla">T</span>
                                        <span v-if="material.maneja_otra_variante" class="inline-flex min-w-5 items-center justify-center rounded border border-border bg-muted/60 px-1 py-0.5 text-[10px] font-semibold text-muted-foreground" title="Maneja otra variante">V</span>
                                        <span v-if="!(material.maneja_color || material.maneja_talla || material.maneja_otra_variante)" class="text-xs text-muted-foreground">—</span>
                                    </div>
                                </td>

                                <!-- Observaciones -->
                                <td class="hidden px-3 py-3 align-top 2xl:table-cell">
                                    <span
                                        v-if="material.observaciones"
                                        v-tooltip="{ content: material.observaciones, delay: { show: 200, hide: 100 } }"
                                        class="block max-w-[220px] truncate text-xs text-muted-foreground"
                                    >
                                        {{ material.observaciones }}
                                    </span>
                                    <span v-else class="text-xs text-muted-foreground">—</span>
                                </td>

                                <!-- Acciones -->
                                <td
                                    class="sticky right-0 z-10 w-[136px] border-l border-border/60 bg-card px-2 py-2 align-middle shadow-[-8px_0_16px_-16px_rgba(0,0,0,0.35)] transition-colors group-hover:bg-muted/40"
                                >
                                    <div class="flex items-center justify-end gap-1">
                                        <button
                                            v-if="can(['update Material'])"
                                            type="button"
                                            @click="accionFila('edit', material)"
                                            title="Editar"
                                            :aria-label="`Editar ${material.codigo_material || material.nombre_base}`"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                                        >
                                            <PencilIcon class="h-4 w-4" />
                                        </button>
                                        <button
                                            v-if="can(['update Material'])"
                                            type="button"
                                            @click="accionFila('alterar', material)"
                                            title="Alterar existencia"
                                            :aria-label="`Alterar existencia de ${material.codigo_material || material.nombre_base}`"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                                        >
                                            <ArrowUpTrayIcon class="h-4 w-4 rotate-180" />
                                        </button>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger
                                                type="button"
                                                title="Acciones"
                                                :aria-label="`Acciones de ${material.codigo_material || material.nombre_base}`"
                                                class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                                            >
                                                <EllipsisVerticalIcon class="h-4 w-4" />
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" class="w-60 p-1">
                                                <DropdownMenuItem
                                                    v-if="can(['read Material'])"
                                                    @click="accionFila('historial', material)"
                                                    class="flex cursor-pointer items-center gap-2 px-2.5 py-1.5 text-xs font-medium"
                                                >
                                                    <ClockIcon class="h-4 w-4" />
                                                    <span>Ver historial</span>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    v-if="can(['delete Material'])"
                                                    @click="accionFila('delete', material)"
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

                        <!-- Sin resultados -->
                        <tbody v-else>
                            <tr>
                                <td :colspan="columnas.length + 2" class="px-6 py-16">
                                    <div class="mx-auto flex max-w-sm flex-col items-center text-center">
                                        <div class="flex h-12 w-12 items-center justify-center rounded-full border border-border bg-muted/60">
                                            <FunnelIcon v-if="hayFiltros" class="h-5 w-5 text-muted-foreground" />
                                            <CubeIcon v-else class="h-5 w-5 text-muted-foreground" />
                                        </div>
                                        <h3 class="mt-4 text-sm font-semibold text-foreground">
                                            {{ hayFiltros ? 'No hay materiales que coincidan' : 'Aún no hay materiales' }}
                                        </h3>
                                        <p class="mt-1 text-sm text-muted-foreground">
                                            {{
                                                hayFiltros
                                                    ? 'Ajusta la búsqueda o limpia los filtros para ver más resultados.'
                                                    : 'Crea el primer material o carga un Excel para empezar.'
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
                                        <PrimaryButton v-else-if="can(['create Material'])" type="button" class="mt-4" @click="abrirCrear">
                                            <PlusIcon class="mr-1.5 h-4 w-4" />
                                            Crear el primer material
                                        </PrimaryButton>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <footer
                    v-if="!cargando && totalFiltrado > 0"
                    class="flex flex-wrap items-center justify-between gap-3 border-t border-border bg-card px-3 py-2.5 sm:px-4"
                >
                    <p class="text-xs text-muted-foreground" role="status" aria-live="polite">
                        Mostrando
                        <span class="font-medium tabular-nums text-foreground">{{ rango.desde }}–{{ rango.hasta }}</span>
                        de
                        <span class="font-medium tabular-nums text-foreground">{{ totalFiltrado }}</span>
                        <span v-if="data.selectedId.length" class="ml-2 text-foreground">· {{ data.selectedId.length }} seleccionados</span>
                    </p>

                    <nav class="flex items-center gap-1" aria-label="Paginación">
                        <button
                            type="button"
                            @click="cambiarPagina(1)"
                            :disabled="paginaActual === 1"
                            aria-label="Primera página"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-40"
                        >
                            <ChevronDoubleLeftIcon class="h-3.5 w-3.5" />
                        </button>
                        <button
                            type="button"
                            @click="cambiarPagina(paginaActual - 1)"
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
                                @click="cambiarPagina(Number(p))"
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
                            @click="cambiarPagina(paginaActual + 1)"
                            :disabled="paginaActual === paginas"
                            aria-label="Página siguiente"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-40"
                        >
                            <ChevronRightIcon class="h-4 w-4" />
                        </button>
                        <button
                            type="button"
                            @click="cambiarPagina(paginas)"
                            :disabled="paginaActual === paginas"
                            aria-label="Última página"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-40"
                        >
                            <ChevronDoubleRightIcon class="h-3.5 w-3.5" />
                        </button>
                    </nav>
                </footer>
            </div>
        </div>

        <!-- Modales (acciones que sí hablan con el backend) -->
        <HistorialMultiples
            :show="data.historialMultipleOpen"
            :materiales="data.historialMateriales"
            @close="data.historialMultipleOpen = false"
        />

        <ImportarMaterialModal :show="data.importOpen" @close="data.importOpen = false" />

        <Create
            v-if="can(['create Material'])"
            :numberPermissions="props.numberPermissions"
            :titulos="titulos"
            :show="data.createOpen"
            @close="data.createOpen = false"
            :title="props.title"
            :losSelect="props.losSelect"
        />

        <Edit
            v-if="can(['update Material'])"
            :titulos="titulos"
            :numberPermissions="props.numberPermissions"
            :show="data.editOpen"
            @close="data.editOpen = false"
            :Materiala="data.Materialo"
            :title="props.title"
            :losSelect="props.losSelect"
        />

        <Delete
            v-if="can(['delete Material'])"
            :numberPermissions="props.numberPermissions"
            :show="data.deleteOpen"
            @close="data.deleteOpen = false"
            :Materiala="data.Materialo"
            :title="props.title"
        />

        <AlterarExistencia
            v-if="can(['update Material'])"
            :show="data.alterarExistenciaOpen"
            :material="data.Materialo"
            @close="data.alterarExistenciaOpen = false"
        />

        <HistorialMovimientos
            v-if="can(['read Material'])"
            :show="data.historialOpen"
            :material="data.Materialo"
            @close="data.historialOpen = false"
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
