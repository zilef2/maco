<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import DataPagination from '@/components/DataPagination.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

import {
    ArrowPathIcon,
    ChevronRightIcon,
    ChevronUpDownIcon,
    ChevronDownIcon,
    ChevronUpIcon,
    ClipboardDocumentListIcon,
    FunnelIcon,
    PencilIcon,
    PlusIcon,
    TrashIcon,
    XMarkIcon,
} from '@heroicons/vue/24/solid';

import Create from '@/pages/CierreOrdenProduccion/Create.vue';
import Edit from '@/pages/CierreOrdenProduccion/Edit.vue';
import Delete from '@/pages/CierreOrdenProduccion/Delete.vue';

import { formatDate } from '@/global.ts';

const props = defineProps({
    cierres: { type: Array as () => any[], default: () => [] },
    total: Number,
    breadcrumbs: { type: Array as () => Array<{ label?: string; href?: string }>, default: () => [] },
    perPage: Number,
    title: String,
    numberPermissions: Number,
    losSelect: Object,
    titulos: Array,
});

const breadcrumbCollapsed = ref(false);

/* --------------------------------------------------------------------------
 * Tabla 100% en cliente (igual que Producto): los cierres llegan una sola vez
 * y aquí se filtran, agrupan, ordenan y paginan. Al filtrar por OP las filas
 * se agrupan bajo una cabecera y el tamaño de página salta al valor más
 * pequeño que las contiene todas (11 filas → 25 por página).
 * ------------------------------------------------------------------------ */

const filas = computed<any[]>(() => props.cierres ?? []);

const opFiltro = ref('');
const responsableFiltro = ref('');
const fechaDesde = ref('');
const fechaHasta = ref('');

const sortKey = ref('fecha_cierre');
const sortDir = ref<'asc' | 'desc'>('desc');
const POR_PAGINA_OPCIONES = [10, 25, 50, 100];
const porPaginaDefecto = POR_PAGINA_OPCIONES.includes(props.perPage ?? 0) ? (props.perPage as number) : 10;
const porPagina = ref<number>(porPaginaDefecto);
const pagina = ref(1);
const cargando = ref(false);

const columnas = [
    { key: 'orden_codigo', label: 'OP', sortable: true, align: 'left', headerClass: 'min-w-[110px]', cellClass: 'min-w-[110px]' },
    { key: 'control_cierre', label: 'Control de cierre', sortable: true, align: 'left', headerClass: 'hidden min-w-[170px] md:table-cell', cellClass: 'hidden min-w-[170px] md:table-cell' },
    { key: 'dato_esperado', label: 'Esperada', sortable: true, align: 'right', headerClass: 'w-[100px]', cellClass: 'w-[100px]' },
    { key: 'dato_real', label: 'Aprobada', sortable: true, align: 'right', headerClass: 'w-[100px]', cellClass: 'w-[100px]' },
    { key: 'dato_no_recuperable', label: 'No recuperable', sortable: true, align: 'right', headerClass: 'w-[130px]', cellClass: 'w-[130px]' },
    { key: '_diferencia', label: 'Diferencia', sortable: true, align: 'right', headerClass: 'w-[110px]', cellClass: 'w-[110px]' },
    { key: 'estado_accion', label: 'Estado', sortable: true, align: 'left', headerClass: 'w-[160px]', cellClass: 'w-[160px]' },
    { key: 'responsable_nombre', label: 'Responsable', sortable: true, align: 'left', headerClass: 'hidden min-w-[150px] lg:table-cell', cellClass: 'hidden min-w-[150px] lg:table-cell' },
    { key: 'fecha_cierre', label: 'Fecha cierre', sortable: true, align: 'left', headerClass: 'w-[130px]', cellClass: 'w-[130px]' },
    { key: 'observaciones', label: 'Observaciones', sortable: false, align: 'left', headerClass: 'hidden min-w-[220px] xl:table-cell', cellClass: 'hidden min-w-[220px] xl:table-cell' },
] as const;

const titulos = [
    { order: 'orden_produccion_id', label: 'orden_produccion_id', type: 'foreign' },
    { order: 'control_cierre', label: 'control_cierre', type: 'string' },
    { order: 'dato_esperado', label: 'dato_esperado', type: 'number' },
    { order: 'dato_real', label: 'dato_real', type: 'number' },
    { order: 'dato_no_recuperable', label: 'dato_no_recuperable', type: 'number' },
    { order: 'diferencia', label: 'diferencia', type: 'number' },
    { order: 'estado_accion', label: 'estado_accion', type: 'string' },
    { order: 'responsable_id', label: 'responsable_id', type: 'foreign' },
    { order: 'fecha_cierre', label: 'fecha_cierre', type: 'datetime' },
    { order: 'observaciones', label: 'observaciones', type: 'text' },
];

const data = reactive({
    CierreOrdenProducciono: null as any,
    createOpen: false,
    editOpen: false,
    deleteOpen: false,
});

const selectClass = 'h-9 rounded-md border border-input bg-background px-2 text-sm text-foreground shadow-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30';

const normalizar = (valor: any): string =>
    String(valor ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();

const texto = (valor: any, fallback = '—'): string => {
    const resultado = String(valor ?? '').trim();
    return resultado !== '' ? resultado : fallback;
};

const cantidad = (valor: any): string =>
    valor === null || valor === undefined || valor === '' ? '—' : String(Number(valor));

// La diferencia es calculada, igual que en el formulario de «Cambiar estado»:
// esperada − aprobada. Si el cierre aún no tiene ninguno de los dos datos,
// se usa la diferencia guardada en la BD.
const diferenciaDe = (cierre: any): number | null => {
    const esperado = cierre.dato_esperado;
    const real = cierre.dato_real;
    if (esperado !== null && esperado !== undefined && real !== null && real !== undefined) {
        return Number(esperado) - Number(real);
    }
    if (cierre.diferencia !== null && cierre.diferencia !== undefined) {
        return Number(cierre.diferencia);
    }
    return null;
};

const diferenciaClase = (valor: number | null): string => {
    if (valor === null || Number.isNaN(valor)) return 'text-muted-foreground';
    if (valor === 0) return 'text-emerald-600 dark:text-emerald-400';
    return valor > 0
        ? 'text-rose-600 dark:text-rose-400'
        : 'text-amber-600 dark:text-amber-400';
};

const ESTADOS_EMERALD = ['cerrado', 'completado', 'revisado'];
const ESTADOS_AMBAR = ['con observaciones', 'pendiente'];

const estadoEstilo = (valor: any) => {
    const estado = normalizar(valor);
    if (ESTADOS_EMERALD.includes(estado)) {
        return 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300';
    }
    if (ESTADOS_AMBAR.includes(estado)) {
        return 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300';
    }
    return 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-500/30 dark:bg-slate-500/10 dark:text-slate-300';
};

const preparadas = computed(() =>
    filas.value.map((cierre: any) => ({
        ...cierre,
        _diferencia: diferenciaDe(cierre),
        _fecha: String(cierre.fecha_cierre ?? '').slice(0, 10),
    })),
);

const ordenesDisponibles = computed(() =>
    Array.from(new Set(filas.value.map((c: any) => String(c.orden_codigo ?? '').trim()).filter((v) => v && v !== '—')))
        .sort((a, b) => a.localeCompare(b, 'es', { numeric: true })),
);

const responsablesDisponibles = computed(() =>
    Array.from(new Set(filas.value.map((c: any) => String(c.responsable_nombre ?? '').trim()).filter((v) => v && v !== '—')))
        .sort((a, b) => a.localeCompare(b, 'es')),
);

const filtradas = computed(() =>
    preparadas.value.filter((cierre: any) => {
        if (opFiltro.value && String(cierre.orden_codigo ?? '') !== opFiltro.value) return false;
        if (responsableFiltro.value && String(cierre.responsable_nombre ?? '') !== responsableFiltro.value) return false;
        if (fechaDesde.value && (!cierre._fecha || cierre._fecha < fechaDesde.value)) return false;
        if (fechaHasta.value && (!cierre._fecha || cierre._fecha > fechaHasta.value)) return false;
        return true;
    }),
);

const collator = new Intl.Collator('es', { numeric: true, sensitivity: 'base' });

const ordenadas = computed(() => {
    const dir = sortDir.value === 'asc' ? 1 : -1;
    const key = sortKey.value;

    return [...filtradas.value].sort((a: any, b: any) => {
        const va = a[key];
        const vb = b[key];
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
const totalRegistros = computed(() => ordenadas.value.length);
const totalCierres = computed(() => filas.value.length);
const paginas = computed(() => Math.max(1, Math.ceil(totalFiltrado.value / porPagina.value)));
const paginaActual = computed(() => Math.min(pagina.value, paginas.value));
const ultimaPagina = computed(() => paginas.value);
const desde = computed(() => (totalFiltrado.value === 0 ? 0 : (paginaActual.value - 1) * porPagina.value + 1));
const hasta = computed(() => Math.min(paginaActual.value * porPagina.value, totalFiltrado.value));

const visibles = computed(() => {
    const inicio = (paginaActual.value - 1) * porPagina.value;
    return ordenadas.value.slice(inicio, inicio + porPagina.value);
});

// Solo con el filtro de OP activo las filas se agrupan bajo una cabecera.
const grupos = computed(() => {
    if (!opFiltro.value) return null;
    const mapa = new Map<string, any[]>();
    visibles.value.forEach((cierre: any) => {
        const clave = String(cierre.orden_codigo ?? '—');
        if (!mapa.has(clave)) mapa.set(clave, []);
        mapa.get(clave)!.push(cierre);
    });
    return Array.from(mapa, ([op, cierreGrupados]) => ({ op, filas: cierreGrupados }));
});

const hayFiltros = computed(
    () => [opFiltro.value, responsableFiltro.value, fechaDesde.value, fechaHasta.value].some((v) => String(v ?? '').trim() !== ''),
);

// Con el filtro de OP, la página debe crecer al tamaño más pequeño que
// contenga todas las filas resultantes (ej. 11 filas → 25 por página).
watch([opFiltro, () => filtradas.value.length], ([op]) => {
    if (op) {
        const requerido = POR_PAGINA_OPCIONES.find((n) => n >= filtradas.value.length) ?? POR_PAGINA_OPCIONES.at(-1)!;
        if (requerido > porPagina.value) porPagina.value = requerido;
    }
    pagina.value = 1;
});

watch(opFiltro, (op) => {
    if (!op) porPagina.value = porPaginaDefecto;
});

watch([opFiltro, responsableFiltro, fechaDesde, fechaHasta, porPagina], () => {
    pagina.value = 1;
});

watch(paginas, (n) => {
    if (pagina.value > n) pagina.value = n;
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

const accionFila = (accion: string, cierre: any) => {
    data.CierreOrdenProducciono = cierre;
    if (accion === 'edit') data.editOpen = true;
    if (accion === 'delete') data.deleteOpen = true;
};

const limpiarFiltros = () => {
    opFiltro.value = '';
    responsableFiltro.value = '';
    fechaDesde.value = '';
    fechaHasta.value = '';
};

const rutaIndex = new URL(String(route('CierreOrdenProduccion.index')), window.location.origin).pathname;

router.on('start', (event) => {
    const url = event.detail.visit.url;
    const pathname = url instanceof URL ? url.pathname : new URL(String(url), window.location.origin).pathname;
    if (pathname === rutaIndex) cargando.value = true;
});
router.on('finish', () => {
    cargando.value = false;
});

const recargar = () => {
    router.reload({ only: ['cierres', 'total'], preserveScroll: true });
};
</script>

<template>
    <Head :title="props.title" />

    <AppLayout>
        <div class="flex flex-col gap-4 p-4 sm:p-6">
        <div class="flex justify-end mb-2">
            <button
                @click="breadcrumbCollapsed = !breadcrumbCollapsed"
                class="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                :aria-label="breadcrumbCollapsed ? 'Mostrar título y breadcrumb' : 'Ocultar título y breadcrumb'"
            >
                <ChevronDownIcon v-if="breadcrumbCollapsed" class="h-4 w-4" />
                <ChevronUpIcon v-else class="h-4 w-4" />
            </button>
        </div>
        <div v-if="!breadcrumbCollapsed" class="flex flex-col gap-3">
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
                        <h1 class="text-2xl font-semibold tracking-tight text-foreground">Cierres de OP</h1>
                        <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground" role="status" aria-live="polite">
                            <span class="tabular-nums">
                                <span class="font-medium text-foreground">{{ totalRegistros }}</span>
                                {{ totalRegistros === 1 ? 'cierre' : 'cierres' }}
                            </span>
                            <span v-if="hayFiltros" class="text-border">·</span>
                            <span v-if="hayFiltros" class="tabular-nums">
                                de <span class="font-medium text-foreground">{{ totalCierres }}</span>
                            </span>
                            <span v-if="opFiltro" class="text-border">·</span>
                            <span v-if="opFiltro" class="tabular-nums">
                                agrupados por <span class="font-medium text-foreground">{{ opFiltro }}</span>
                            </span>
                            <span
                                v-if="hayFiltros"
                                class="inline-flex items-center gap-1 rounded-full border border-border bg-muted/60 px-2 py-0.5 text-[11px] font-medium text-foreground"
                            >
                                <FunnelIcon class="h-3 w-3" />
                                {{ [opFiltro, responsableFiltro, fechaDesde, fechaHasta].filter(v => String(v ?? '').trim() !== '').length }}
                                {{ [opFiltro, responsableFiltro, fechaDesde, fechaHasta].filter(v => String(v ?? '').trim() !== '').length === 1 ? 'filtro activo' : 'filtros activos' }}
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
                            v-if="can(['create CierreOrdenProduccion'])"
                            type="button"
                            class="h-9"
                            @click="data.createOpen = true"
                        >
                            <PlusIcon class="mr-1.5 h-4 w-4" />
                            Nuevo cierre
                        </PrimaryButton>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <div class="flex flex-col gap-2.5 border-b border-border p-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="flex items-center gap-1.5">
                            <span class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">OP</span>
                            <select
                                v-model="opFiltro"
                                aria-label="Filtrar por orden de producción"
                                :class="[selectClass, 'max-w-[160px]']"
                            >
                                <option value="">Todas las OP</option>
                                <option v-for="op in ordenesDisponibles" :key="op" :value="op">{{ op }}</option>
                            </select>
                        </div>

                        <div class="flex items-center gap-1.5">
                            <span class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Responsable</span>
                            <select
                                v-model="responsableFiltro"
                                aria-label="Filtrar por responsable"
                                :class="[selectClass, 'max-w-[200px]']"
                            >
                                <option value="">Todos</option>
                                <option v-for="resp in responsablesDisponibles" :key="resp" :value="resp">{{ resp }}</option>
                            </select>
                        </div>

                        <div class="flex items-center gap-1.5">
                            <span class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Fecha cierre</span>
                            <input
                                v-model="fechaDesde"
                                type="date"
                                aria-label="Fecha de cierre desde"
                                :class="selectClass"
                            />
                            <span class="text-xs text-muted-foreground">hasta</span>
                            <input
                                v-model="fechaHasta"
                                type="date"
                                aria-label="Fecha de cierre hasta"
                                :class="selectClass"
                            />
                        </div>

                        <select v-model.number="porPagina" aria-label="Registros por página" :class="selectClass">
                            <option v-for="n in POR_PAGINA_OPCIONES" :key="n" :value="n">{{ n }} / página</option>
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
                    <table class="w-full min-w-[1180px] border-collapse text-sm">
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
                                    class="sticky right-0 z-30 w-[88px] border-l border-border bg-muted px-2 py-2.5 text-right text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground"
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

                        <tbody v-else-if="visibles.length" :key="paginaActual + '|' + opFiltro" class="mat-page">
                            <template v-for="grupo in (grupos ?? [{ op: '', filas: visibles }])" :key="'grupo-' + grupo.op">
                                <tr v-if="grupo.op" class="border-b border-border/60 bg-muted/60">
                                    <td :colspan="columnas.length + 1" class="px-3 py-2">
                                        <span class="text-sm font-semibold tracking-tight text-foreground">{{ grupo.op }}</span>
                                        <span class="ml-2 text-xs tabular-nums text-muted-foreground">
                                            {{ grupo.filas.length }} {{ grupo.filas.length === 1 ? 'registro' : 'registros' }}
                                        </span>
                                    </td>
                                </tr>

                                <tr
                                    v-for="cierre in grupo.filas"
                                    :key="cierre.id"
                                    class="group border-b border-border/60 transition-colors last:border-0 hover:bg-muted/40"
                                >
                                    <td class="px-3 py-3 align-top">
                                        <span class="font-semibold tabular-nums tracking-tight text-foreground">
                                            {{ texto(cierre.orden_codigo) }}
                                        </span>
                                    </td>

                                    <td class="hidden px-3 py-3 align-top md:table-cell">
                                        <span class="text-sm text-foreground">{{ texto(cierre.control_cierre) }}</span>
                                    </td>

                                    <td class="px-3 py-3 text-right align-top">
                                        <span class="font-semibold tabular-nums text-foreground">{{ cantidad(cierre.dato_esperado) }}</span>
                                    </td>

                                    <td class="px-3 py-3 text-right align-top">
                                        <span class="font-medium tabular-nums text-emerald-700 dark:text-emerald-300">{{ cantidad(cierre.dato_real) }}</span>
                                    </td>

                                    <td class="px-3 py-3 text-right align-top">
                                        <span class="font-medium tabular-nums text-rose-700 dark:text-rose-300">{{ cantidad(cierre.dato_no_recuperable) }}</span>
                                    </td>

                                    <td class="px-3 py-3 text-right align-top">
                                        <span
                                            class="font-semibold tabular-nums"
                                            :class="diferenciaClase(cierre._diferencia)"
                                            :title="cierre._diferencia === null ? 'Sin esperada/aprobada registradas' : 'Esperada − Aprobada'"
                                        >
                                            {{ cierre._diferencia === null ? '—' : cierre._diferencia }}
                                        </span>
                                    </td>

                                    <td class="px-3 py-3 align-top">
                                        <span
                                            class="inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-medium capitalize"
                                            :class="estadoEstilo(cierre.estado_accion)"
                                        >
                                            {{ texto(cierre.estado_accion, 'sin estado') }}
                                        </span>
                                    </td>

                                    <td class="hidden px-3 py-3 align-top lg:table-cell">
                                        <span class="text-sm text-foreground">{{ texto(cierre.responsable_nombre) }}</span>
                                    </td>

                                    <td class="px-3 py-3 align-top">
                                        <span class="text-sm tabular-nums text-foreground">
                                            {{ cierre.fecha_cierre ? formatDate(cierre.fecha_cierre, 'fecha') : '—' }}
                                        </span>
                                    </td>

                                    <td class="hidden px-3 py-3 align-top xl:table-cell">
                                        <span
                                            class="block max-w-[280px] truncate text-sm text-muted-foreground"
                                            :title="cierre.observaciones"
                                        >
                                            {{ texto(cierre.observaciones, 'Sin observaciones') }}
                                        </span>
                                    </td>

                                    <td
                                        class="sticky right-0 z-10 w-[88px] border-l border-border/60 bg-card px-2 py-2 align-middle shadow-[-8px_0_16px_-16px_rgba(0,0,0,0.35)] transition-colors group-hover:bg-muted/40"
                                    >
                                        <div class="flex items-center justify-end gap-1">
                                            <button
                                                v-if="can(['update CierreOrdenProduccion'])"
                                                type="button"
                                                @click="accionFila('edit', cierre)"
                                                title="Editar"
                                                :aria-label="`Editar cierre ${cierre.orden_codigo} · ${cierre.control_cierre}`"
                                                class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                                            >
                                                <PencilIcon class="h-4 w-4" />
                                            </button>
                                            <button
                                                v-if="can(['delete CierreOrdenProduccion'])"
                                                type="button"
                                                @click="accionFila('delete', cierre)"
                                                title="Eliminar"
                                                :aria-label="`Eliminar cierre ${cierre.orden_codigo} · ${cierre.control_cierre}`"
                                                class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-rose-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                                            >
                                                <TrashIcon class="h-4 w-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>

                        <tbody v-else>
                            <tr>
                                <td :colspan="columnas.length + 1" class="px-6 py-16">
                                    <div class="mx-auto flex max-w-sm flex-col items-center text-center">
                                        <div class="flex h-12 w-12 items-center justify-center rounded-full border border-border bg-muted/60">
                                            <FunnelIcon v-if="hayFiltros" class="h-5 w-5 text-muted-foreground" />
                                            <ClipboardDocumentListIcon v-else class="h-5 w-5 text-muted-foreground" />
                                        </div>
                                        <h3 class="mt-4 text-sm font-semibold text-foreground">
                                            {{ hayFiltros ? 'No hay cierres que coincidan' : 'Aún no hay cierres' }}
                                        </h3>
                                        <p class="mt-1 text-sm text-muted-foreground">
                                            {{
                                                hayFiltros
                                                    ? 'Ajusta el filtro de OP, responsable o rango de fechas, o límpialos para ver todos los cierres.'
                                                    : 'Los cierres se generan al cerrar una orden de producción desde «Cambiar estado».'
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
                    :item-label="totalRegistros === 1 ? 'cierre' : 'cierres'"
                    @page="cambiarPagina"
                />
            </div>
        </div>

        <Create
            v-if="can(['create CierreOrdenProduccion'])"
            :numberPermissions="props.numberPermissions"
            :titulos="titulos"
            :show="data.createOpen"
            @close="data.createOpen = false"
            :title="props.title"
            :losSelect="props.losSelect"
        />

        <Edit
            v-if="can(['update CierreOrdenProduccion'])"
            :titulos="titulos"
            :numberPermissions="props.numberPermissions"
            :show="data.editOpen"
            @close="data.editOpen = false"
            :CierreOrdenProducciona="data.CierreOrdenProducciono"
            :title="props.title"
            :losSelect="props.losSelect"
        />

        <Delete
            v-if="can(['delete CierreOrdenProduccion'])"
            :numberPermissions="props.numberPermissions"
            :show="data.deleteOpen"
            @close="data.deleteOpen = false"
            :CierreOrdenProducciona="data.CierreOrdenProducciono"
            :title="props.title"
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
