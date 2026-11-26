<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import DataPagination from '@/components/DataPagination.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

import {
    ArrowPathIcon,
    ChevronDownIcon,
    ChevronRightIcon,
    ChevronUpDownIcon,
    ChevronUpIcon,
    ClipboardDocumentCheckIcon,
    FunnelIcon,
    MagnifyingGlassIcon,
    PencilIcon,
    PlusIcon,
    TrashIcon,
    XMarkIcon,
} from '@heroicons/vue/24/solid';

import Create from '@/pages/RecepcionCalidad/Create.vue';
import Edit from '@/pages/RecepcionCalidad/Edit.vue';
import Delete from '@/pages/RecepcionCalidad/Delete.vue';

import { formatDate, zilef_nuber_format } from '@/global.ts';

const props = defineProps({
    recepciones: { type: Array as () => any[], default: () => [] },
    ordenes: { type: Array as () => Array<{ value: number; title: string; estado?: string; recepciones?: number }>, default: () => [] },
    total: Number,
    breadcrumbs: { type: Array as () => Array<{ label?: string; href?: string }>, default: () => [] },
    perPage: Number,
    title: String,
    numberPermissions: Number,
    losSelect: Object,
    titulos: Array,
});

/* --------------------------------------------------------------------------
 * La tabla trabaja 100% en cliente: las recepciones llegan una sola vez y aquí
 * se buscan, filtran y agrupan por OP. Cada grupo calcula sus totales de
 * cantidad y de costo usando el costo unitario de materiales que envía el
 * backend (ficha técnica del producto o del material procesado).
 * ------------------------------------------------------------------------ */

const data = reactive({
    RecepcionCalidado: null as any,
    createOpen: false,
    editOpen: false,
    deleteOpen: false,
});

const busqueda = ref('');
const ordenFiltro = ref<string | number>('');
const fechaFiltro = ref('');
const sortKey = ref('producto');
const sortDir = ref<'asc' | 'desc'>('asc');
const porPagina = ref<number>(props.perPage ?? 10);
const pagina = ref(1);
const cargando = ref(false);

const selectClass = 'h-9 rounded-md border border-input bg-background px-2 text-sm text-foreground shadow-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30';

const normalizar = (valor: any): string =>
    String(valor ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/\s+/g, ' ')
        .trim();

const numero = (valor: any): number => {
    if (valor === null || valor === undefined || valor === '') return 0;
    const resultado = Number(valor);
    return Number.isFinite(resultado) ? resultado : 0;
};

const cantidad = (valor: any): string =>
    valor === null || valor === undefined || valor === ''
        ? '—'
        : zilef_nuber_format(valor, 0, false);

const dinero = (valor: any): string => zilef_nuber_format(numero(valor), 0, true);

const porcentaje = (numerador: any, denominador: any): string => {
    const num = numero(numerador);
    const den = numero(denominador);
    if (den <= 0) return '0.0%';
    return (num / den * 100).toFixed(1) + '%';
};

const texto = (valor: any, fallback = '—'): string => {
    const resultado = String(valor ?? '').trim();
    return resultado !== '' ? resultado : fallback;
};

const fecha = (valor: any): string => {
    if (!valor) return '—';
    const fechaValida = new Date(String(valor).slice(0, 10) + 'T00:00:00');
    return Number.isNaN(fechaValida.getTime()) ? texto(valor) : formatDate(fechaValida, 'fecha');
};

const nombreObjetivo = (fila: any): string =>
    fila.producto_nombre || fila.material_nombre || 'Sin producto';

const esMaterial = (fila: any): boolean =>
    !fila.producto_referencia_id && !!fila.material_referencia_id;

const costoDe = (fila: any, campo: string): string => {
    const unitario = numero(fila.costo_unitario);
    const valor = fila[campo];

    if (unitario <= 0 || valor === null || valor === undefined || valor === '') return '—';
    const cantidadValor = numero(valor);

    return cantidadValor === 0 ? '—' : dinero(cantidadValor * unitario);
};

const columnasCantidad = [
    { key: 'cantidad_esperada', totalKey: 'esperada', label: 'Esperada', clase: 'text-foreground' },
    { key: 'cantidad_recibida', totalKey: 'recibida', label: 'Recibida', clase: 'text-foreground' },
    { key: 'cantidad_aprobada', totalKey: 'aprobada', label: 'Aprobada', clase: 'text-emerald-700 dark:text-emerald-300' },
    { key: 'cantidad_en_reparacion', totalKey: 'enReparacion', label: 'En reparación', clase: 'text-amber-700 dark:text-amber-300' },
    { key: 'cantidad_no_recuperable', totalKey: 'noRecuperable', label: 'No recuperable', clase: 'text-rose-700 dark:text-rose-300' },
] as const;

// Mismo modelo de columnas que Productos: los <th> se generan de aquí y las
// celdas se dibujan en el mismo orden.
type Columna = { key: string; label: string; sortable: boolean; align: 'left' | 'right'; headerClass: string };

const columnas: Columna[] = [
    { key: 'producto', label: 'Producto', sortable: true, align: 'left', headerClass: 'min-w-[260px]' },
    { key: 'talla', label: 'Talla', sortable: true, align: 'left', headerClass: 'w-[90px]' },
    ...columnasCantidad.map((col) => ({
        key: col.key,
        label: col.label,
        sortable: true,
        align: 'right' as const,
        headerClass: 'w-[124px]',
    })),
    { key: 'fecha_recepcion', label: 'Fecha recepción', sortable: true, align: 'left', headerClass: 'w-[130px]' },
    { key: 'observaciones', label: 'Observaciones', sortable: false, align: 'left', headerClass: 'min-w-[200px]' },
];

type TotalCantidad = { cantidad: number; costo: number };
type TotalesRecepcion = Record<
    'esperada' | 'recibida' | 'aprobada' | 'enReparacion' | 'noRecuperable',
    TotalCantidad
>;

const totalesDe = (filas: any[]): TotalesRecepcion => {
    const total: TotalesRecepcion = {
        esperada: { cantidad: 0, costo: 0 },
        recibida: { cantidad: 0, costo: 0 },
        aprobada: { cantidad: 0, costo: 0 },
        enReparacion: { cantidad: 0, costo: 0 },
        noRecuperable: { cantidad: 0, costo: 0 },
    };

    for (const fila of filas) {
        const unitario = numero(fila.costo_unitario);

        for (const columna of columnasCantidad) {
            const valor = numero(fila[columna.key]);
            total[columna.totalKey].cantidad += valor;
            total[columna.totalKey].costo += valor * unitario;
        }
    }

    return total;
};

// Un total sin unidades no tiene costo que mostrar.
const costoTotal = (total: TotalCantidad): string =>
    total.cantidad === 0 ? '—' : dinero(total.costo);

const ESTADO_ESTILOS = {
    cerrada: {
        badge: 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-500/30 dark:bg-slate-500/10 dark:text-slate-300',
        dot: 'bg-slate-400',
    },
    recibiendo: {
        badge: 'border-indigo-200 bg-indigo-50 text-indigo-800 dark:border-indigo-500/30 dark:bg-indigo-500/10 dark:text-indigo-300',
        dot: 'bg-indigo-500',
    },
    taller: {
        badge: 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300',
        dot: 'bg-amber-500',
    },
    proceso: {
        badge: 'border-violet-200 bg-violet-50 text-violet-800 dark:border-violet-500/30 dark:bg-violet-500/10 dark:text-violet-300',
        dot: 'bg-violet-500',
    },
    activa: {
        badge: 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300',
        dot: 'bg-emerald-500',
    },
};

const estadoEstilo = (valor: any) => {
    const estado = normalizar(valor);

    if (estado.includes('cerrada')) return ESTADO_ESTILOS.cerrada;
    if (estado.includes('recibiendo')) return ESTADO_ESTILOS.recibiendo;
    if (estado.includes('taller')) return ESTADO_ESTILOS.taller;
    if (estado.includes('proceso')) return ESTADO_ESTILOS.proceso;
    return ESTADO_ESTILOS.activa;
};

// Cada fila trae lo necesario para buscar y agrupar sin volver a la BD.
const preparadas = computed(() =>
    (props.recepciones ?? []).map((fila: any) => ({
        ...fila,
        _busqueda: normalizar([
            fila.producto_nombre,
            fila.producto_codigo,
            fila.material_nombre,
        ].filter(Boolean).join(' ')),
        _fecha: String(fila.fecha_recepcion ?? '').slice(0, 10),
        _opLabel: fila.orden_numero || `OP #${fila.orden_produccion_id}`,
        _producto: nombreObjetivo(fila),
    })),
);

const terminosBusqueda = computed(() =>
    normalizar(busqueda.value).split(' ').filter(Boolean),
);

/* Buscamos también por nombre «parecido»: el usuario escribe de oído y el
 * catálogo trae tildes, abreviaturas y errores de tipeo. Un término coincide si
 * es subcadena, si alguna palabra empieza igual, o si se parece lo suficiente
 * (coeficiente de bigramas de Dice). */
const bigramas = (valor: string): string[] => {
    const salida: string[] = [];
    for (let i = 0; i < valor.length - 1; i++) salida.push(valor.slice(i, i + 2));
    return salida;
};

const similitud = (a: string, b: string): number => {
    if (a === b) return 1;

    const izquierda = bigramas(a);
    const derecha = bigramas(b);
    if (!izquierda.length || !derecha.length) return 0;

    const disponibles = [...derecha];
    let comunes = 0;
    for (const bigrama of izquierda) {
        const i = disponibles.indexOf(bigrama);
        if (i === -1) continue;
        disponibles.splice(i, 1);
        comunes++;
    }

    return (2 * comunes) / (izquierda.length + derecha.length);
};

// Los términos cortos (iniciales, tallas) exigen coincidencia exacta para no
// llenar la tabla de falsos positivos.
const umbralDe = (token: string): number => (token.length >= 6 ? 0.6 : 0.5);

const terminoCoincide = (token: string, texto: string): boolean => {
    if (texto.includes(token)) return true;
    if (token.length < 4) return false;

    const umbral = umbralDe(token);

    return texto.split(' ').some(
        (palabra) => palabra.startsWith(token) || token.startsWith(palabra) || similitud(token, palabra) >= umbral,
    );
};

const filtradas = computed(() =>
    preparadas.value.filter((fila: any) => {
        if (ordenFiltro.value !== '' && String(fila.orden_produccion_id) !== String(ordenFiltro.value)) {
            return false;
        }
        if (fechaFiltro.value && fila._fecha !== fechaFiltro.value) {
            return false;
        }
        if (
            terminosBusqueda.value.length
            && !terminosBusqueda.value.every((termino) => terminoCoincide(termino, fila._busqueda))
        ) {
            return false;
        }
        return true;
    }),
);

const collator = new Intl.Collator('es', { numeric: true, sensitivity: 'base' });

const valorOrden = (fila: any, key: string): any => {
    if (key === 'producto') return fila._producto;
    if (key === 'fecha_recepcion') return fila._fecha;
    if (key === 'talla') return fila.talla;
    return numero(fila[key]);
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

const grupos = computed(() => {
    const mapa = new Map<string, any>();

    for (const fila of ordenadas.value) {
        const clave = String(fila.orden_produccion_id ?? 'sin-op');

        if (!mapa.has(clave)) {
            mapa.set(clave, {
                id: clave,
                ordenProduccionId: fila.orden_produccion_id,
                label: fila._opLabel,
                numero: fila.orden_numero,
                estado: fila.orden_estado,
                objetivo: fila.orden_objetivo,
                filas: [] as any[],
                totales: null as TotalesRecepcion | null,
            });
        }

        mapa.get(clave).filas.push(fila);
    }

    const lista = Array.from(mapa.values());

    for (const grupo of lista) {
        grupo.totales = totalesDe(grupo.filas);
    }

    // Las OPs más recientes primero (por número, no por texto).
    return lista.sort((a, b) => {
        const na = numero(String(a.numero ?? '').replace(/\D+/g, ''));
        const nb = numero(String(b.numero ?? '').replace(/\D+/g, ''));

        if (na !== nb) return nb - na;
        return collator.compare(String(a.label), String(b.label));
    });
});

const totalFiltrado = computed(() => filtradas.value.length);
const totalRecepciones = computed(() => (props.recepciones ?? []).length);
const totalOps = computed(() => grupos.value.length);
const paginas = computed(() => Math.max(1, Math.ceil(totalOps.value / porPagina.value)));
const paginaActual = computed(() => Math.min(pagina.value, paginas.value));
const gruposVisibles = computed(() => {
    const inicio = (paginaActual.value - 1) * porPagina.value;
    return grupos.value.slice(inicio, inicio + porPagina.value);
});
const desde = computed(() => (totalOps.value === 0 ? 0 : (paginaActual.value - 1) * porPagina.value + 1));
const hasta = computed(() => Math.min(paginaActual.value * porPagina.value, totalOps.value));

const filtrosActivos = computed(
    () =>
        (busqueda.value.trim() !== '' ? 1 : 0)
        + (ordenFiltro.value !== '' ? 1 : 0)
        + (fechaFiltro.value !== '' ? 1 : 0),
);

const hayFiltros = computed(() => filtrosActivos.value > 0);

// Una OP abierta todavía sin recepciones deja la tabla vacía por diseño; el
// aviso lo dice en vez de hacer parecer que el filtro falló.
const opSeleccionada = computed(() =>
    props.ordenes.find((op) => String(op.value) === String(ordenFiltro.value)),
);
const totalesGenerales = computed(() => {
    let valorProceso = 0;
    let costoTotalOp = 0;
    let esperada = 0;
    let recibida = 0;
    let noRecuperable = 0;
    let valorRecibidoMat = 0;
    let valorNoRecuperableMat = 0;
    let recepcionesCount = 0;

    for (const grupo of grupos.value) {
        const filaBase = grupo.filas?.[0] || {};
        valorProceso += numero(filaBase.op_costo_proceso);
        costoTotalOp += numero(filaBase.op_costo_total);

        const unitario = numero(filaBase.costo_unitario);
        const cantEsperada = numero(grupo.totales?.esperada?.cantidad);
        const cantRecibida = numero(grupo.totales?.recibida?.cantidad);
        const cantNoRecuperable = numero(grupo.totales?.noRecuperable?.cantidad);

        esperada += cantEsperada;
        recibida += cantRecibida;
        noRecuperable += cantNoRecuperable;
        valorRecibidoMat += unitario * cantRecibida;
        valorNoRecuperableMat += unitario * cantNoRecuperable;
        recepcionesCount += grupo.filas?.length || 0;
    }

    return {
        valorProceso,
        costoTotalOp,
        esperada,
        recibida,
        noRecuperable,
        valorRecibidoMat,
        valorNoRecuperableMat,
        ops: grupos.value.length,
        recepciones: recepcionesCount,
    };
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

const accionFila = (accion: string, fila: any) => {
    data.RecepcionCalidado = fila;

    if (accion === 'edit') data.editOpen = true;
    if (accion === 'delete') data.deleteOpen = true;
};

const limpiarFiltros = () => {
    busqueda.value = '';
    ordenFiltro.value = '';
    fechaFiltro.value = '';
};

watch([busqueda, ordenFiltro, fechaFiltro, porPagina], () => {
    pagina.value = 1;
});

watch(paginas, (n) => {
    if (pagina.value > n) pagina.value = n;
});

const rutaIndex = new URL(String(route('RecepcionCalidad.index')), window.location.origin).pathname;

router.on('start', (event) => {
    const url = event.detail.visit.url;
    const pathname = url instanceof URL ? url.pathname : new URL(String(url), window.location.origin).pathname;
    if (pathname === rutaIndex) cargando.value = true;
});
router.on('finish', () => {
    cargando.value = false;
});

const recargar = () => {
    router.reload({ only: ['recepciones', 'ordenes', 'total', 'perPage'] });
};
const breadcrumbCollapsed = ref(false);

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
                        <h1 class="text-2xl font-semibold tracking-tight text-foreground">Recepción de calidad</h1>
                        <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground" role="status" aria-live="polite">
                            <span class="tabular-nums">
                                <span class="font-medium text-foreground">{{ totalFiltrado }}</span>
                                {{ totalFiltrado === 1 ? 'recepción' : 'recepciones' }}
                            </span>
                            <span v-if="hayFiltros" class="text-border">·</span>
                            <span v-if="hayFiltros" class="tabular-nums">
                                de <span class="font-medium text-foreground">{{ totalRecepciones }}</span>
                            </span>
                            <span class="text-border">·</span>
                            <span class="tabular-nums">
                                <span class="font-medium text-foreground">{{ totalOps }}</span>
                                {{ totalOps === 1 ? 'OP' : 'OPs' }}
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

                        <!-- <PrimaryButton
                            v-if="can(['create RecepcionCalidad'])"
                            type="button"
                            class="h-9"
                            @click="data.createOpen = true"
                        >
                            <PlusIcon class="mr-1.5 h-4 w-4" />
                            Nuevo registro
                        </PrimaryButton> -->
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <div class="flex flex-col gap-2.5 border-b border-border p-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="relative min-w-[220px] flex-1">
                            <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <input
                                v-model="busqueda"
                                type="search"
                                aria-label="Buscar por nombre de producto"
                                placeholder="Buscar por nombre de producto, admite errores…"
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
                            v-model="ordenFiltro"
                            aria-label="Filtrar por orden de producción no cerrada"
                            :class="[selectClass, 'max-w-[320px]']"
                        >
                            <option value="">Todas las OPs abiertas</option>
                            <option v-for="op in props.ordenes" :key="op.value" :value="op.value">
                                {{ op.title }} · {{ op.recepciones ?? 0 }}
                                {{ (op.recepciones ?? 0) === 1 ? 'recepción' : 'recepciones' }}
                            </option>
                        </select>

                        <div class="flex items-center gap-1.5">
                            <span class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                                Recepción
                            </span>
                            <input
                                v-model="fechaFiltro"
                                type="date"
                                aria-label="Fecha de recepción"
                                :class="[selectClass, 'w-[150px]']"
                            />
                        </div>

                        <select v-model.number="porPagina" aria-label="OPs por página" :class="selectClass">
                            <option v-for="n in [5, 10, 25, 50]" :key="n" :value="n">{{ n }} OP / página</option>
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
                    <table class="w-full min-w-[1440px] border-collapse text-sm">
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

                        <tbody v-else-if="gruposVisibles.length" :key="paginaActual" class="mat-page">
                            <template v-for="grupo in gruposVisibles" :key="grupo.id">
                                <tr class="border-y border-border bg-muted/60">
                                    <td :colspan="columnas.length + 1" class="px-3 py-2">
                                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                            <span class="inline-flex items-center gap-1.5 rounded-md border border-border bg-card px-2 py-0.5 text-xs font-semibold text-foreground shadow-sm">
                                                <ClipboardDocumentCheckIcon class="h-3.5 w-3.5 text-muted-foreground" />
                                                {{ grupo.label }}
                                            </span>

                                            <span
                                                class="inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[11px] font-medium"
                                                :class="estadoEstilo(grupo.estado).badge"
                                            >
                                                <span class="h-1.5 w-1.5 rounded-full" :class="estadoEstilo(grupo.estado).dot"></span>
                                                {{ texto(grupo.estado, 'Sin estado') }}
                                            </span>

                                            <span
                                                v-if="grupo.objetivo"
                                                class="max-w-[380px] truncate text-xs text-muted-foreground"
                                                :title="grupo.objetivo"
                                            >
                                                {{ grupo.objetivo }}
                                            </span>

                                            <div class="ml-auto flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-muted-foreground">
                                                <div class="flex items-center gap-1.5">
                                                    <span>Valor proceso:</span>
                                                    <span class="tabular-nums font-medium text-foreground">
                                                        {{ dinero(grupo.filas[0]?.op_costo_proceso ?? 0) }}
                                                    </span>
                                                </div>
                                                <div class="flex items-center gap-1.5">
                                                    <span>Costo total OP (mat. + proceso):</span>
                                                    <span class="tabular-nums font-medium text-foreground">
                                                        {{ dinero(grupo.filas[0]?.op_costo_total ?? 0) }}
                                                    </span>
                                                </div>
                                                <div class="flex items-center gap-1.5">
                                                    <span>Valor materiales recibido:</span>
                                                    <span class="tabular-nums font-medium text-foreground">
                                                        {{ dinero(numero(grupo.filas[0]?.costo_unitario ?? 0) * numero(grupo.totales?.recibida?.cantidad ?? 0)) }}
                                                    </span>
                                                </div>
                                                <div class="flex items-center gap-1.5">
                                                    <span>Valor no recuperable:</span>
                                                    <span class="tabular-nums font-medium text-rose-700 dark:text-rose-300">
                                                        {{ dinero(numero(grupo.filas[0]?.costo_unitario ?? 0) * numero(grupo.totales?.noRecuperable?.cantidad ?? 0)) }}
                                                    </span>
                                                </div>
                                                <div class="flex items-center gap-1.5">
                                                    <span>% recibido:</span>
                                                    <span class="tabular-nums font-medium text-foreground">
                                                        {{ porcentaje(grupo.totales?.recibida?.cantidad ?? 0, grupo.totales?.esperada?.cantidad ?? 0) }}
                                                    </span>
                                                </div>
                                                <div class="flex items-center gap-1.5">
                                                    <span>% perdido:</span>
                                                    <span class="tabular-nums font-medium text-rose-700 dark:text-rose-300">
                                                        {{ porcentaje(grupo.totales?.noRecuperable?.cantidad ?? 0, grupo.totales?.esperada?.cantidad ?? 0) }}
                                                    </span>
                                                </div>
                                                <div class="flex items-center gap-1.5">
                                                    <span>{{ grupo.filas.length }}</span>
                                                    <span>{{ grupo.filas.length === 1 ? 'recepción' : 'recepciones' }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>

                                <tr
                                    v-for="fila in grupo.filas"
                                    :key="fila.id"
                                    class="group border-b border-border/60 transition-colors hover:bg-muted/40"
                                >
                                    <td class="px-3 py-3 align-top">
                                        <div class="flex max-w-[300px] flex-col gap-0.5">
                                            <span class="truncate font-medium text-foreground" :title="nombreObjetivo(fila)">
                                                {{ nombreObjetivo(fila) }}
                                            </span>
                                            <span class="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                                                <span v-if="fila.producto_codigo" class="tabular-nums">
                                                    {{ fila.producto_codigo }}
                                                </span>
                                                <span
                                                    v-if="esMaterial(fila)"
                                                    class="inline-flex items-center rounded border border-violet-200 bg-violet-50 px-1 py-0.5 text-[10px] font-semibold text-violet-700 dark:border-violet-500/30 dark:bg-violet-500/10 dark:text-violet-300"
                                                >
                                                    Material
                                                </span>
                                            </span>
                                        </div>
                                    </td>

                                    <td class="px-3 py-3 align-top">
                                        <span class="inline-flex min-w-6 items-center justify-center rounded border border-border bg-muted/60 px-1.5 py-0.5 text-[10px] font-semibold text-muted-foreground">
                                            {{ texto(fila.talla) }}
                                        </span>
                                    </td>

                                    <td v-for="col in columnasCantidad" :key="col.key" class="px-3 py-3 text-right align-top">
                                        <div class="flex flex-col items-end gap-0.5">
                                            <span class="font-semibold tabular-nums" :class="col.clase">
                                                {{ cantidad(fila[col.key]) }}
                                            </span>
                                            <span class="text-[11px] tabular-nums text-muted-foreground">
                                                {{ costoDe(fila, col.key) }}
                                            </span>
                                        </div>
                                    </td>

                                    <td class="px-3 py-3 align-top">
                                        <span class="whitespace-nowrap text-sm tabular-nums text-foreground">
                                            {{ fecha(fila.fecha_recepcion) }}
                                        </span>
                                    </td>

                                    <td class="px-3 py-3 align-top">
                                        <span
                                            class="line-clamp-2 max-w-[220px] text-xs text-muted-foreground"
                                            :title="texto(fila.observaciones, '')"
                                        >
                                            {{ texto(fila.observaciones) }}
                                        </span>
                                    </td>

                                    <td
                                        class="sticky right-0 z-10 w-[104px] border-l border-border/60 bg-card px-2 py-2 align-middle shadow-[-8px_0_16px_-16px_rgba(0,0,0,0.35)] transition-colors group-hover:bg-muted/40"
                                    >
                                        <div class="flex items-center justify-end gap-1">
                                            <button
                                                v-if="can(['update RecepcionCalidad'])"
                                                type="button"
                                                @click="accionFila('edit', fila)"
                                                title="Editar"
                                                :aria-label="`Editar ${nombreObjetivo(fila)}`"
                                                class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                                            >
                                                <PencilIcon class="h-4 w-4" />
                                            </button>
                                            <button
                                                v-if="can(['delete RecepcionCalidad'])"
                                                type="button"
                                                @click="accionFila('delete', fila)"
                                                title="Eliminar"
                                                :aria-label="`Eliminar ${nombreObjetivo(fila)}`"
                                                class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-rose-600 shadow-sm transition hover:bg-rose-50 hover:text-rose-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500 focus-visible:ring-offset-2 focus-visible:ring-offset-background dark:text-rose-400 dark:hover:bg-rose-500/10"
                                            >
                                                <TrashIcon class="h-4 w-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <tr class="border-b border-border bg-muted/30">
                                    <td colspan="2" class="px-3 py-2.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                        Total {{ grupo.label }}
                                    </td>

                                    <td v-for="col in columnasCantidad" :key="'total-' + col.key" class="px-3 py-2.5 text-right align-top">
                                        <div class="flex flex-col items-end gap-0.5">
                                            <span class="font-semibold tabular-nums" :class="col.clase">
                                                {{ zilef_nuber_format(grupo.totales[col.totalKey].cantidad, 0, false) }}
                                            </span>
                                            <span class="text-[11px] tabular-nums text-muted-foreground">
                                                {{ costoTotal(grupo.totales[col.totalKey]) }}
                                            </span>
                                        </div>
                                    </td>

                                    <td colspan="2" class="px-3 py-2.5"></td>

                                    <td class="sticky right-0 z-10 border-l border-border/60 bg-muted/30 px-2 py-2.5"></td>
                                </tr>
                            </template>
                        </tbody>

                        <tbody v-else>
                            <tr>
                                <td :colspan="columnas.length + 1" class="px-6 py-16">
                                    <div class="mx-auto flex max-w-sm flex-col items-center text-center">
                                        <div class="flex h-12 w-12 items-center justify-center rounded-full border border-border bg-muted/60">
                                            <FunnelIcon v-if="hayFiltros" class="h-5 w-5 text-muted-foreground" />
                                            <ClipboardDocumentCheckIcon v-else class="h-5 w-5 text-muted-foreground" />
                                        </div>
                                        <h3 class="mt-4 text-sm font-semibold text-foreground">
                                            {{ hayFiltros ? 'No hay recepciones que coincidan' : 'Aún no hay recepciones' }}
                                        </h3>
                                        <p class="mt-1 text-sm text-muted-foreground">
                                            {{
                                                opSinRecepciones
                                                    ? `${opSeleccionada?.title} aún no tiene recepciones registradas.`
                                                    : hayFiltros
                                                        ? 'Ajusta los filtros o límpialos para ver más resultados.'
                                                        : 'Registra una recepción desde el cambio de estado de una OP.'
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
                                            v-else-if="can(['create RecepcionCalidad'])"
                                            type="button"
                                            class="mt-4"
                                            @click="data.createOpen = true"
                                        >
                                            <PlusIcon class="mr-1.5 h-4 w-4" />
                                            Nuevo registro
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
                    :last-page="paginas"
                    :from="desde"
                    :to="hasta"
                    :total="totalOps"
                    :item-label="totalOps === 1 ? 'OP' : 'OPs'"
                    :summary="`${totalFiltrado} ${totalFiltrado === 1 ? 'recepción' : 'recepciones'}`"
                    @page="cambiarPagina"
                />
            </div>
        </div>

        <div
            v-if="!cargando && grupos.length"
            class="mx-auto mt-4 max-w-7xl px-4 sm:px-6 lg:px-8"
        >
            <div class="overflow-x-auto rounded-lg border border-border bg-card shadow-sm">
                <table class="min-w-full divide-y divide-border text-sm">
                    <thead>
                        <tr class="bg-muted/60">
                            <th
                                scope="col"
                                colspan="2"
                                class="px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground"
                            >
                                Total general (todas las OP)
                            </th>
                            <th
                                scope="col"
                                class="px-3 py-2 text-right text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground"
                            >
                                Valor proceso
                            </th>
                            <th
                                scope="col"
                                class="px-3 py-2 text-right text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground"
                            >
                                Costo total OP (mat. + proceso)
                            </th>
                            <th
                                scope="col"
                                class="px-3 py-2 text-right text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground"
                            >
                                Valor materiales recibido
                            </th>
                            <th
                                scope="col"
                                class="px-3 py-2 text-right text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground"
                            >
                                Valor no recuperable
                            </th>
                            <th
                                scope="col"
                                class="px-3 py-2 text-right text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground"
                            >
                                % recibido
                            </th>
                            <th
                                scope="col"
                                class="px-3 py-2 text-right text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground"
                            >
                                % perdido
                            </th>
                            <th
                                scope="col"
                                class="px-3 py-2 text-right text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground"
                            >
                                OPs / Recepciones
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="2" class="px-3 py-2.5 text-sm font-medium text-foreground">
                                Consolidado
                            </td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-foreground">
                                {{ dinero(totalesGenerales.valorProceso) }}
                            </td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-foreground">
                                {{ dinero(totalesGenerales.costoTotalOp) }}
                            </td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-foreground">
                                {{ dinero(totalesGenerales.valorRecibidoMat) }}
                            </td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-rose-700 dark:text-rose-300">
                                {{ dinero(totalesGenerales.valorNoRecuperableMat) }}
                            </td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-foreground">
                                {{ porcentaje(totalesGenerales.recibida, totalesGenerales.esperada) }}
                            </td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-rose-700 dark:text-rose-300">
                                {{ porcentaje(totalesGenerales.noRecuperable, totalesGenerales.esperada) }}
                            </td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-muted-foreground">
                                {{ totalesGenerales.ops }} / {{ totalesGenerales.recepciones }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Create
            v-if="can(['create RecepcionCalidad'])"
            :numberPermissions="props.numberPermissions"
            :titulos="props.titulos"
            :show="data.createOpen"
            @close="data.createOpen = false"
            :title="props.title"
            :losSelect="props.losSelect"
        />

        <Edit
            v-if="can(['update RecepcionCalidad'])"
            :titulos="props.titulos"
            :numberPermissions="props.numberPermissions"
            :show="data.editOpen"
            @close="data.editOpen = false"
            :RecepcionCalidada="data.RecepcionCalidado"
            :title="props.title"
            :losSelect="props.losSelect"
        />

        <Delete
            v-if="can(['delete RecepcionCalidad'])"
            :numberPermissions="props.numberPermissions"
            :show="data.deleteOpen"
            @close="data.deleteOpen = false"
            :RecepcionCalidada="data.RecepcionCalidado"
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
