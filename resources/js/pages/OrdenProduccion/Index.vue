<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import PrimaryButton from '@/components/PrimaryButton.vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { computed, reactive, ref, watch } from 'vue';

import Create from '@/pages/OrdenProduccion/Create.vue';
import Edit from '@/pages/OrdenProduccion/Edit.vue';
import Delete from '@/pages/OrdenProduccion/Delete.vue';
import DistribucionTallas from '@/pages/OrdenProduccion/DistribucionTallas.vue';
import KitProcesoOPModal from '@/pages/OrdenProduccion/KitProcesoOPModal.vue';
import CambiarEstadoModal from '@/pages/OrdenProduccion/CambiarEstadoModal.vue';
import DetalleEstadoModal from '@/pages/OrdenProduccion/DetalleEstadoModal.vue';
import SacarMaterialesModal from '@/pages/ProcesoExterno/SacarMaterialesModal.vue';

import {
    ArrowDownTrayIcon,
    ArrowPathIcon,
    ArrowUpTrayIcon,
    ArrowUturnLeftIcon,
    ChevronDoubleLeftIcon,
    ChevronDoubleRightIcon,
    ChevronDownIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    ChevronUpDownIcon,
    ChevronUpIcon,
    ClipboardDocumentListIcon,
    EllipsisVerticalIcon,
    FunnelIcon,
    MagnifyingGlassIcon,
    PencilIcon,
    PlusIcon,
    SquaresPlusIcon,
    TrashIcon,
    XMarkIcon,
} from '@heroicons/vue/24/solid';

import { formatDate, zilef_nuber_format } from '@/global.ts';

const props = defineProps({
    ordenes: { type: Array as () => any[], default: () => [] },
    total: Number,
    breadcrumbs: Array as () => Array<{ label?: string; href?: string }>,
    perPage: Number,
    title: String,
    numberPermissions: Number,
    losSelect: Object,
    borradorUltimo: Object,
});

/* --------------------------------------------------------------------------
 * Tabla 100% en cliente: los datos llegan una sola vez y aquí se buscan,
 * filtran, ordenan y paginan sin volver al backend. Solo las acciones de
 * cada fila (editar, estado, tallas, kit, salida, eliminar) hablan con él.
 * ------------------------------------------------------------------------ */

const filas = computed<any[]>(() => (props.ordenes as any[]) ?? []);

const busqueda = ref('');
const estadoFiltro = ref<number | 'todos'>('todos');
const responsableFiltro = ref<number | 'todos'>('todos');
const tallerFiltro = ref<number | 'todos'>('todos');
const sortKey = ref('numero_op');
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

const preparadas = computed(() =>
    filas.value.map((op: any) => ({
        ...op,
        // La búsqueda libre solo cubre código de orden y nombres de producto;
        // estado y responsable tienen su propio filtro.
        _busqueda: normalizar(
            [
                op.numero_op,
                op.producto_nombre,
                op.productos_nombres,
            ].join(' '),
        ),
    })),
);

const baseFiltrada = computed(() =>
    preparadas.value.filter((op: any) => {
        if (busqueda.value.trim() && !op._busqueda.includes(normalizar(busqueda.value))) {
            return false;
        }
        if (responsableFiltro.value !== 'todos' && Number(op.user_id) !== Number(responsableFiltro.value)) {
            return false;
        }
        if (tallerFiltro.value !== 'todos' && Number(op.taller_id) !== Number(tallerFiltro.value)) {
            return false;
        }
        return true;
    }),
);

const estadosDisponibles = computed(() => {
    const orden = ((props.losSelect as any)?.estado_op_id ?? []) as any[];
    const ordenMap = new Map<number, number>();
    orden.forEach((e: any, i: number) => ordenMap.set(Number(e.id), i));

    // Conteos dentro del alcance actual (búsqueda, responsable y taller).
    const conteos = new Map<number, number>();
    baseFiltrada.value.forEach((op: any) => {
        // Un OP en coexistencia ("Esperando materiales" + "Procesos externos")
        // cuenta bajo ambos estados.
        [op.estado_op_id, op.estado_op_secundario_id].forEach((rawId: any) => {
            const id = Number(rawId);
            if (!id) return;
            conteos.set(id, (conteos.get(id) ?? 0) + 1);
        });
    });

    // Las opciones se arman con todas las órdenes para que la lista no cambie
    // de tamaño al mover los demás filtros.
    const grupos = new Map<number, { id: number; nombre: string; count: number }>();
    filas.value.forEach((op: any) => {
        [op.estado_op_id, op.estado_op_secundario_id].forEach((rawId: any) => {
            const id = Number(rawId);
            if (!id || grupos.has(id)) return;
            grupos.set(id, {
                id,
                nombre: (String(rawId) === String(op.estado_op_id) ? op.estado_nombre : op.estado_secundario_nombre) || `Estado #${id}`,
                count: 0,
            });
        });
    });
    grupos.forEach((grupo) => {
        grupo.count = conteos.get(grupo.id) ?? 0;
    });

    return Array.from(grupos.values()).sort((a, b) => {
        const oa = ordenMap.has(a.id) ? ordenMap.get(a.id)! : 999;
        const ob = ordenMap.has(b.id) ? ordenMap.get(b.id)! : 999;
        return oa - ob || a.nombre.localeCompare(b.nombre, 'es');
    });
});

const filtradas = computed(() =>
    baseFiltrada.value.filter((op: any) => {
        if (estadoFiltro.value === 'todos') return true;
        // Coincide con el estado principal o con el secundario (coexistencia).
        return Number(op.estado_op_id) === Number(estadoFiltro.value)
            || Number(op.estado_op_secundario_id) === Number(estadoFiltro.value);
    }),
);

const talleresDisponibles = computed(() => {
    const mapa = new Map<number, string>();
    filas.value.forEach((op: any) => {
        if (op.taller_id) {
            mapa.set(Number(op.taller_id), op.taller_nombre || `Taller #${op.taller_id}`);
        }
    });
    return Array.from(mapa.entries())
        .map(([id, nombre]) => ({ id, nombre }))
        .sort((a, b) => a.nombre.localeCompare(b.nombre, 'es'));
});

const responsablesDisponibles = computed(() => {
    const mapa = new Map<number, string>();
    filas.value.forEach((op: any) => {
        if (op.user_id) {
            mapa.set(Number(op.user_id), op.responsable_nombre || `Usuario #${op.user_id}`);
        }
    });
    return Array.from(mapa.entries())
        .map(([id, nombre]) => ({ id, nombre }))
        .sort((a, b) => a.nombre.localeCompare(b.nombre, 'es'));
});

const collator = new Intl.Collator('es', { numeric: true, sensitivity: 'base' });

const valorOrden = (op: any, key: string): any => {
    switch (key) {
        case 'producto':
            return op.producto_nombre ?? '';
        case 'taller':
            return (op.taller_nombre || op.empresa_externa_nombre) ?? '';
        case 'estado':
            return op.estado_nombre ?? '';
        case 'responsable':
            return op.responsable_nombre ?? '';
        default:
            return op[key];
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
    const fin = Math.min(total, inicio + ventana - 1);
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

const hayFiltros = computed(
    () => busqueda.value.trim() !== ''
        || estadoFiltro.value !== 'todos'
        || responsableFiltro.value !== 'todos'
        || tallerFiltro.value !== 'todos',
);

const limpiarFiltros = () => {
    busqueda.value = '';
    estadoFiltro.value = 'todos';
    responsableFiltro.value = 'todos';
    tallerFiltro.value = 'todos';
};

watch([busqueda, estadoFiltro, responsableFiltro, tallerFiltro, porPagina], () => {
    pagina.value = 1;
});

watch(paginas, (n) => {
    if (pagina.value > n) pagina.value = n;
});

/* ------------------------------- Estilos -------------------------------- */

const ESTADO_ESTILOS: Record<string, { dot: string; badge: string }> = {
    borrador: {
        dot: 'bg-amber-500',
        badge: 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300',
    },
    preparacion: {
        dot: 'bg-blue-500',
        badge: 'border-blue-200 bg-blue-50 text-blue-800 dark:border-blue-500/30 dark:bg-blue-500/10 dark:text-blue-300',
    },
    esperando_materiales: {
        dot: 'bg-orange-500',
        badge: 'border-orange-200 bg-orange-50 text-orange-800 dark:border-orange-500/30 dark:bg-orange-500/10 dark:text-orange-300',
    },
    procesos_externos: {
        dot: 'bg-violet-500',
        badge: 'border-violet-200 bg-violet-50 text-violet-800 dark:border-violet-500/30 dark:bg-violet-500/10 dark:text-violet-300',
    },
    lista_para_taller: {
        dot: 'bg-emerald-500',
        badge: 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300',
    },
    en_taller: {
        dot: 'bg-cyan-500',
        badge: 'border-cyan-200 bg-cyan-50 text-cyan-800 dark:border-cyan-500/30 dark:bg-cyan-500/10 dark:text-cyan-300',
    },
    recibiendo: {
        dot: 'bg-yellow-400',
        badge: 'border-yellow-300 bg-yellow-50 text-yellow-800 dark:border-yellow-500/30 dark:bg-yellow-400/10 dark:text-yellow-300',
    },
    pendiente: {
        dot: 'bg-rose-500',
        badge: 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300',
    },
    terminada: {
        dot: 'bg-green-500',
        badge: 'border-green-200 bg-green-50 text-green-800 dark:border-green-500/30 dark:bg-green-500/10 dark:text-green-300',
    },
    cerrada: {
        dot: 'bg-slate-400',
        badge: 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-500/30 dark:bg-slate-500/10 dark:text-slate-300',
    },
};

const estiloEstado = (nombre: string) =>
    ESTADO_ESTILOS[normalizar(nombre).replace(/\s+/g, '_')] ?? {
        dot: 'bg-muted-foreground/50',
        badge: 'border-border bg-muted text-muted-foreground',
    };

// Estados que muestran información adicional al hacer clic. El resto
// (Borrador, Procesos externos, Lista para taller, En taller, Pendiente,
// Cerrada) no tiene detalle y su vínculo queda deshabilitado.
const ESTADOS_CON_DETALLE = ['Preparación', 'Esperando materiales', 'Recibiendo'];
const estadoConDetalle = (nombre: string | null | undefined) =>
    ESTADOS_CON_DETALLE.includes(String(nombre ?? ''));

const columnas = [
    { key: 'numero_op', label: 'Orden', sortable: true, align: 'left', headerClass: 'min-w-[130px]', cellClass: 'min-w-[130px]' },
    { key: 'producto', label: 'Producto', sortable: true, align: 'left', headerClass: 'min-w-[200px]', cellClass: 'min-w-[200px]' },
    { key: 'cantidad_planificada', label: 'Cantidad', sortable: true, align: 'right', headerClass: 'w-[100px]', cellClass: 'w-[100px]' },
    { key: 'taller', label: 'Envío taller', sortable: true, align: 'left', headerClass: 'hidden min-w-[150px] md:table-cell', cellClass: 'hidden min-w-[150px] md:table-cell' },
    { key: 'estado', label: 'Estado', sortable: true, align: 'left', headerClass: 'min-w-[150px]', cellClass: 'min-w-[150px]' },
    { key: 'responsable', label: 'Responsable', sortable: true, align: 'left', headerClass: 'hidden min-w-[120px] lg:table-cell', cellClass: 'hidden min-w-[120px] lg:table-cell' },
    { key: 'observaciones', label: 'Observaciones', sortable: false, align: 'left', headerClass: 'hidden min-w-[200px] 2xl:table-cell', cellClass: 'hidden min-w-[200px] 2xl:table-cell' },
] as const;

/* ------------------------------- Acciones ------------------------------- */

const authCan = ((usePage().props as any)?.auth?.can ?? {}) as Record<string, boolean>;
const puede = (perms: string[]) => perms.some((p) => authCan[p]);

const data = reactive({
    createOpen: false,
    borradorCrear: null as any,
    editOpen: false,
    deleteOpen: false,
    ordenEditando: null as any,
    distribucionOpen: false,
    distribucionOp: null as any,
    kitProcesoOpen: false,
    kitProcesoOp: null as any,
    cambioEstadoOpen: false,
    cambioEstadoOp: null as any,
    detalleEstadoOpen: false,
    detalleEstadoOp: null as any,
    salidaOpen: false,
    salidaOp: null as any,
});

const abrirNuevo = () => {
    data.borradorCrear = null;
    data.createOpen = true;
};

const abrirContinuarUltima = () => {
    data.borradorCrear = props.borradorUltimo ?? null;
    data.createOpen = true;
};

const abrirDistribucion = (op: any) => {
    data.distribucionOp = op ?? null;
    data.distribucionOpen = true;
};

const abrirKitProceso = (op: any) => {
    data.kitProcesoOp = op ?? null;
    data.kitProcesoOpen = true;
};

const abrirCambioEstado = (op: any) => {
    data.cambioEstadoOp = op ?? null;
    data.cambioEstadoOpen = true;
};

const abrirDetalleEstado = (op: any) => {
    if (!estadoConDetalle(op?.estado_nombre)) return;
    data.detalleEstadoOp = op ?? null;
    data.detalleEstadoOpen = true;
};

const accionesFila = computed(() => {
    const arr: Array<{ id: string; label: string; icon: any; visible?: (op: any) => boolean }> = [];
    if (puede(['update OrdenProduccion'])) {
        arr.push({ id: 'edit', label: 'Editar', icon: PencilIcon });
    }
    if (puede(['create KitProceso'])) {
        arr.push({ id: 'kit', label: 'Kit taller / Importar Excel', icon: ArrowUpTrayIcon });
    }
    arr.push({ id: 'tallas', label: 'Editar tallas', icon: SquaresPlusIcon });
    if (puede(['create ProcesoExterno'])) {
        arr.push({
            id: 'salida',
            label: 'Sacar materiales al taller',
            icon: ArrowDownTrayIcon,
            visible: (op: any) => op?.estado_nombre === 'Lista para taller',
        });
    }
    if (puede(['delete OrdenProduccion'])) {
        arr.push({ id: 'delete', label: 'Eliminar', icon: TrashIcon });
    }
    return arr;
});

const accionesVisibles = (op: any) => accionesFila.value.filter((a) => !a.visible || a.visible(op));

const onAccionFila = (id: string, op: any) => {
    if (id === 'edit') {
        data.editOpen = true;
        data.deleteOpen = false;
        data.distribucionOpen = false;
        data.kitProcesoOpen = false;
        data.cambioEstadoOpen = false;
        data.salidaOpen = false;
        data.ordenEditando = op;
    }
    if (id === 'kit') abrirKitProceso(op);
    if (id === 'tallas') abrirDistribucion(op);
    if (id === 'salida') {
        data.salidaOp = op;
        data.salidaOpen = true;
    }
    if (id === 'delete') {
        data.ordenEditando = op;
        data.deleteOpen = true;
    }
};

const tooltipProductos = (op: any) => {
    const escapar = (s: string) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    const lineas = (op?.productos || []).map((p: any) =>
        escapar(String(p.Titulo_Shopify || p.Codigo_interno || 'Producto #' + p.id)),
    );
    if (!lineas.length) return undefined;
    return { content: lineas.join('<br><br>'), html: true, delay: { show: 200, hide: 100 } };
};

/* -------------------------- Carga / recarga ----------------------------- */

const cargando = ref(false);
const rutaIndex = new URL(String(route('OrdenProduccion.index')), window.location.origin).pathname;

router.on('start', (event) => {
    const url = event.detail.visit.url;
    const pathname = url instanceof URL ? url.pathname : new URL(String(url), window.location.origin).pathname;
    if (pathname === rutaIndex) cargando.value = true;
});
router.on('finish', () => {
    cargando.value = false;
});

const recargar = () => {
    router.reload({ only: ['ordenes', 'total', 'borradorUltimo', 'losSelect'], preserveScroll: true });
};

/* Títulos para los modales de crear/editar (mismo contrato de antes). */
const titulos = [
    { order: 'numero_op', label: 'numero_op', type: 'string' },
    { order: 'producto_referencia_id', label: 'producto_referencia_id', type: 'foreign', nameid: 'producto_nombre' },
    { order: 'cantidad_planificada', label: 'cantidad_planificada', type: 'integer' },
    { order: 'fecha_prevista_envio_taller', label: 'fecha_prevista_envio_taller', type: 'date' },
    { order: 'taller_id', label: 'taller_id', type: 'foreign', nameid: 'taller_nombre' },
    { order: 'estado_op_id', label: 'estado_op_id', type: 'foreign', nameid: 'estado_nombre' },
    { order: 'user_id', label: 'user_id', type: 'foreign', nameid: 'responsable_nombre' },
    { order: 'id_shopify_producto', label: 'id_shopify_producto', type: 'string' },
    { order: 'observaciones', label: 'observaciones', type: 'text' },
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
                        <h1 class="text-2xl font-semibold tracking-tight text-foreground">Órdenes de producción</h1>
                        <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground" role="status" aria-live="polite">
                            <span class="tabular-nums">
                                <span class="font-medium text-foreground">{{ totalFiltrado }}</span>
                                de {{ filas.length }} órdenes
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

                        <button
                            v-if="can(['create OrdenProduccion']) && props.borradorUltimo"
                            type="button"
                            @click="abrirContinuarUltima"
                            class="inline-flex h-9 items-center gap-1.5 rounded-md border border-amber-300 bg-amber-50 px-3 text-xs font-semibold text-amber-800 shadow-sm transition hover:bg-amber-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 focus-visible:ring-offset-2 focus-visible:ring-offset-background dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-300 dark:hover:bg-amber-500/20"
                        >
                            <ArrowUturnLeftIcon class="h-4 w-4" />
                            Continuar OP {{ (props.borradorUltimo as any)?.numero_op }}
                        </button>

                        <PrimaryButton v-if="can(['create OrdenProduccion'])" type="button" class="h-9" @click="abrirNuevo">
                            <PlusIcon class="mr-1.5 h-4 w-4" />
                            Nueva orden
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
                                aria-label="Buscar por código de orden o producto"
                                placeholder="Buscar por código de OP o producto…"
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
                            v-model="estadoFiltro"
                            aria-label="Filtrar por estado"
                            class="h-9 max-w-[190px] rounded-md border border-input bg-background px-2 text-sm text-foreground shadow-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30"
                        >
                            <option value="todos">Todos los estados</option>
                            <option v-for="estado in estadosDisponibles" :key="estado.id" :value="estado.id">
                                {{ estado.nombre }} ({{ estado.count }})
                            </option>
                        </select>

                        <select
                            v-model="responsableFiltro"
                            aria-label="Filtrar por responsable"
                            class="h-9 max-w-[190px] rounded-md border border-input bg-background px-2 text-sm text-foreground shadow-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30"
                        >
                            <option value="todos">Todos los responsables</option>
                            <option v-for="responsable in responsablesDisponibles" :key="responsable.id" :value="responsable.id">
                                {{ responsable.nombre }}
                            </option>
                        </select>

                        <select
                            v-model="tallerFiltro"
                            aria-label="Filtrar por taller"
                            class="h-9 max-w-[190px] rounded-md border border-input bg-background px-2 text-sm text-foreground shadow-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30"
                        >
                            <option value="todos">Todos los talleres</option>
                            <option v-for="taller in talleresDisponibles" :key="taller.id" :value="taller.id">
                                {{ taller.nombre }}
                            </option>
                        </select>

                        <select
                            v-model.number="porPagina"
                            aria-label="Filas por página"
                            class="h-9 rounded-md border border-input bg-background px-2 text-sm text-foreground shadow-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30"
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
                <div class="op-scroll max-h-[68vh] overflow-auto overscroll-contain">
                    <table class="w-full min-w-[960px] border-collapse text-sm">
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

                        <!-- Cargando -->
                        <tbody v-if="cargando">
                            <tr v-for="n in 8" :key="'skeleton-' + n" class="border-b border-border/60">
                                <td :colspan="columnas.length + 1" class="px-4 py-3">
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
                        <tbody v-else-if="visibles.length" :key="paginaActual" class="op-page">
                            <tr
                                v-for="op in visibles"
                                :key="op.id"
                                class="group border-b border-border/60 transition-colors last:border-0 hover:bg-muted/40"
                            >
                                <!-- Orden -->
                                <td class="px-3 py-3 align-top">
                                    <div class="flex flex-col gap-0.5">
                                        <span class="font-semibold tabular-nums tracking-tight text-foreground">
                                            {{ op.numero_op || '—' }}
                                        </span>
                                        <span class="text-[11px] text-muted-foreground">
                                            {{ op.created_at ? 'Creada ' + formatDate(op.created_at) : 'Sin fecha' }}
                                        </span>
                                    </div>
                                </td>

                                <!-- Producto -->
                                <td class="px-3 py-3 align-top">
                                    <div class="flex max-w-[220px] flex-col gap-0.5">
                                        <span class="truncate font-medium text-foreground" :title="op.producto_nombre">
                                            {{ op.producto_nombre || 'Sin producto de referencia' }}
                                        </span>
                                        <span class="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                                            <span class="inline-flex min-w-4 items-center justify-center rounded-full border border-border bg-muted/60 px-1.5 py-0.5 font-medium tabular-nums">
                                                {{ (op.productos || []).length }}
                                            </span>
                                            <span class="truncate" v-tooltip="tooltipProductos(op)">
                                                {{ op.productos_nombres || 'Sin productos asociados' }}
                                            </span>
                                        </span>
                                    </div>
                                </td>

                                <!-- Cantidad -->
                                <td class="px-3 py-3 text-right align-top">
                                    <div class="flex flex-col items-end gap-0.5">
                                        <span class="font-semibold tabular-nums text-foreground">
                                            {{ zilef_nuber_format(op.cantidad_planificada, 0, false) }}
                                        </span>
                                        <span class="text-[11px] tabular-nums text-muted-foreground">
                                            {{ (op.distribuciones_por_tallas || []).length }} tallas
                                        </span>
                                    </div>
                                </td>

                                <!-- Envío taller: taller o proceso externo seleccionado -->
                                <td class="hidden px-3 py-3 align-top md:table-cell">
                                    <div class="flex flex-col gap-0.5">
                                        <span class="text-sm text-foreground">{{ op.taller_nombre || op.empresa_externa_nombre || '—' }}</span>
                                        <span v-if="op.tipo === 'externa'" class="text-[10px] font-medium uppercase tracking-wide text-violet-600 dark:text-violet-300">Proceso externo</span>
                                        <span v-if="op.fecha_prevista_envio_taller" class="text-[11px] tabular-nums text-muted-foreground">
                                            {{ formatDate(op.fecha_prevista_envio_taller) }}
                                        </span>
                                    </div>
                                </td>

                                <!-- Estado -->
                                <td class="px-3 py-3 align-top">
                                    <button
                                        v-if="estadoConDetalle(op.estado_nombre)"
                                        type="button"
                                        @click="abrirDetalleEstado(op)"
                                        title="Ver detalle del estado"
                                        aria-haspopup="dialog"
                                        class="inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[11px] font-medium transition hover:brightness-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background dark:hover:brightness-110"
                                        :class="estiloEstado(op.estado_nombre).badge"
                                    >
                                        <span class="h-1.5 w-1.5 rounded-full" :class="estiloEstado(op.estado_nombre).dot"></span>
                                        {{ op.estado_nombre || 'Sin estado' }}
                                        <ChevronRightIcon class="h-3 w-3 opacity-60" />
                                    </button>
                                    <span
                                        v-else
                                        class="inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[11px] font-medium"
                                        :class="estiloEstado(op.estado_nombre).badge"
                                    >
                                        <span class="h-1.5 w-1.5 rounded-full" :class="estiloEstado(op.estado_nombre).dot"></span>
                                        {{ op.estado_nombre || 'Sin estado' }}
                                    </span>
                                    <span
                                        v-if="op.estado_secundario_nombre"
                                        class="mt-1 block w-fit rounded-full border border-violet-200 bg-violet-50 px-2 py-0.5 text-[11px] font-medium text-violet-700 dark:border-violet-500/30 dark:bg-violet-500/10 dark:text-violet-300"
                                        title="Estado simultáneo (única coexistencia permitida)"
                                    >
                                        + {{ op.estado_secundario_nombre }}
                                    </span>
                                </td>

                                <!-- Responsable -->
                                <td class="hidden px-3 py-3 align-top lg:table-cell">
                                    <span class="text-sm text-foreground">{{ op.responsable_nombre || '—' }}</span>
                                </td>

                                <!-- Observaciones -->
                                <td class="hidden px-3 py-3 align-top 2xl:table-cell">
                                    <span
                                        v-if="op.observaciones"
                                        v-tooltip="{ content: op.observaciones, delay: { show: 200, hide: 100 } }"
                                        class="block max-w-[220px] truncate text-xs text-muted-foreground"
                                    >
                                        {{ op.observaciones }}
                                    </span>
                                    <span v-else class="text-xs text-muted-foreground">—</span>
                                </td>

                                <!-- Acciones -->
                                <td
                                    class="sticky right-0 z-10 w-[104px] border-l border-border/60 bg-card px-2 py-2 align-middle shadow-[-8px_0_16px_-16px_rgba(0,0,0,0.35)] transition-colors group-hover:bg-muted/40"
                                >
                                    <div class="flex items-center justify-end gap-1">
                                        <button
                                            v-if="puede(['update OrdenProduccion'])"
                                            type="button"
                                            @click="abrirCambioEstado(op)"
                                            title="Cambiar estado"
                                            aria-label="Cambiar estado"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                                        >
                                            <ArrowPathIcon class="h-3.5 w-3.5" />
                                        </button>

                                        <DropdownMenu>
                                            <DropdownMenuTrigger
                                                type="button"
                                                title="Más acciones"
                                                aria-label="Más acciones"
                                                class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                                            >
                                                <EllipsisVerticalIcon class="h-4 w-4" />
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" class="w-60 p-1">
                                                <DropdownMenuItem
                                                    v-for="accion in accionesVisibles(op)"
                                                    :key="accion.id"
                                                    @click="onAccionFila(accion.id, op)"
                                                    :variant="accion.id === 'delete' ? 'destructive' : 'default'"
                                                    class="flex cursor-pointer items-center gap-2 px-2.5 py-1.5 text-xs font-medium"
                                                >
                                                    <component :is="accion.icon" class="h-4 w-4" />
                                                    <span>{{ accion.label }}</span>
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
                                <td :colspan="columnas.length + 1" class="px-6 py-16">
                                    <div class="mx-auto flex max-w-sm flex-col items-center text-center">
                                        <div class="flex h-12 w-12 items-center justify-center rounded-full border border-border bg-muted/60">
                                            <FunnelIcon v-if="hayFiltros" class="h-5 w-5 text-muted-foreground" />
                                            <ClipboardDocumentListIcon v-else class="h-5 w-5 text-muted-foreground" />
                                        </div>
                                        <h3 class="mt-4 text-sm font-semibold text-foreground">
                                            {{ hayFiltros ? 'No hay órdenes que coincidan' : 'Aún no hay órdenes de producción' }}
                                        </h3>
                                        <p class="mt-1 text-sm text-muted-foreground">
                                            {{
                                                hayFiltros
                                                    ? 'Ajusta la búsqueda o limpia los filtros para ver más resultados.'
                                                    : 'Crea la primera orden para empezar a planificar la producción.'
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
                                        <PrimaryButton v-else-if="can(['create OrdenProduccion'])" type="button" class="mt-4" @click="abrirNuevo">
                                            <PlusIcon class="mr-1.5 h-4 w-4" />
                                            Crear la primera orden
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
        <Create
            v-if="can(['create OrdenProduccion'])"
            :numberPermissions="props.numberPermissions"
            :titulos="titulos"
            :show="data.createOpen"
            @close="data.createOpen = false; data.borradorCrear = null"
            :title="props.title"
            :losSelect="props.losSelect"
            :borrador="data.borradorCrear"
        />

        <Edit
            v-if="can(['update OrdenProduccion'])"
            :titulos="titulos"
            :numberPermissions="props.numberPermissions"
            :show="data.editOpen"
            @close="data.editOpen = false"
            :OrdenProducciona="(data as any).ordenEditando"
            :title="props.title"
            :losSelect="props.losSelect"
        />

        <Delete
            v-if="can(['delete OrdenProduccion'])"
            :numberPermissions="props.numberPermissions"
            :show="data.deleteOpen"
            @close="data.deleteOpen = false"
            :OrdenProducciona="(data as any).ordenEditando"
            :title="props.title"
        />

        <DistribucionTallas
            :show="data.distribucionOpen"
            @close="data.distribucionOpen = false"
            :title="props.title"
            :ordenId="data.distribucionOp?.id"
            :numeroOp="data.distribucionOp?.numero_op"
            :items="data.distribucionOp?.distribuciones_por_tallas || []"
            :canEdit="can(['update OrdenProduccion'])"
        />

        <KitProcesoOPModal
            :show="data.kitProcesoOpen"
            @close="data.kitProcesoOpen = false"
            :ordenProduccion="data.kitProcesoOp"
        />

        <CambiarEstadoModal
            :show="data.cambioEstadoOpen"
            @close="data.cambioEstadoOpen = false"
            :title="props.title"
            :op="data.cambioEstadoOp"
            :estados="(props.losSelect as any)?.estado_op_id || []"
        />

        <DetalleEstadoModal
            :show="data.detalleEstadoOpen"
            @close="data.detalleEstadoOpen = false"
            :op="data.detalleEstadoOp"
        />

        <SacarMaterialesModal :show="data.salidaOpen" @close="data.salidaOpen = false" :op="data.salidaOp" />
    </AppLayout>
</template>

<style scoped>
@keyframes op-page-in {
    from {
        opacity: 0;
        transform: translateY(3px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.op-page {
    animation: op-page-in 180ms cubic-bezier(0.22, 1, 0.36, 1);
}

.op-scroll {
    scrollbar-width: thin;
    scrollbar-color: var(--border) transparent;
}

.op-scroll::-webkit-scrollbar {
    width: 10px;
    height: 10px;
}

.op-scroll::-webkit-scrollbar-track {
    background: transparent;
}

.op-scroll::-webkit-scrollbar-thumb {
    background: var(--border);
    border: 3px solid transparent;
    border-radius: 9999px;
    background-clip: content-box;
}

@media (prefers-reduced-motion: reduce) {
    .op-page {
        animation: none;
    }
}
</style>
