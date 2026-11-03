<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import DataPagination from '@/components/DataPagination.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import {
    ArrowPathIcon,
    BanknotesIcon,
    ChevronDownIcon,
    ChevronRightIcon,
    ChevronUpDownIcon,
    ChevronUpIcon,
    ClipboardDocumentListIcon,
    EyeIcon,
    FunnelIcon,
    MagnifyingGlassIcon,
    RectangleStackIcon,
    ShieldCheckIcon,
    XMarkIcon,
} from '@heroicons/vue/24/solid';

import DetalleMermaModal from '@/pages/Merma/DetalleMermaModal.vue';

import { useTablaClient, type ColumnaTabla } from '@/composables/useTablaClient';
import { formatDate, zilef_nuber_format } from '@/global.ts';

const props = defineProps({
    mermas: { type: Array as () => any[], default: () => [] },
    breadcrumbs: { type: Array as () => Array<{ label?: string; href?: string }>, default: () => [] },
    title: String,
    numberPermissions: Number,
});

type Periodo = 'mes' | '30' | '60' | 'todo';

const periodos: { clave: Periodo; etiqueta: string }[] = [
    { clave: 'mes', etiqueta: 'Mes actual' },
    { clave: '30', etiqueta: 'Últimos 30 días' },
    { clave: '60', etiqueta: 'Últimos 60 días' },
    { clave: 'todo', etiqueta: 'Todo el histórico' },
];

const periodo = ref<Periodo>('60');
const mesElegido = ref<string | null>(null);
const cargando = ref(false);
const detalleOpen = ref(false);
const mermaVisible = ref<any>(null);

const columnas: ColumnaTabla[] = [
    { key: 'numero_op', label: 'OP', sortable: true, align: 'left', headerClass: 'min-w-[120px]' },
    { key: 'objetivo', label: 'Producto / material', sortable: true, align: 'left', headerClass: 'min-w-[260px]' },
    { key: 'talla', label: 'Talla', sortable: true, align: 'center', headerClass: 'w-[90px]' },
    { key: 'cantidad', label: 'Piezas perdidas', sortable: true, align: 'center', headerClass: 'w-[140px]' },
    { key: 'porcentaje', label: 'Merma / esperado', sortable: true, align: 'right',
        valor: (m: any) => (Number(m.esperada) > 0 ? (Number(m.cantidad) / Number(m.esperada)) * 100 : null),
        headerClass: 'w-[120px]' },
    { key: 'costo_unitario', label: 'Costo unitario', sortable: true, align: 'right', headerClass: 'w-[140px]' },
    { key: 'costo_total_perdida', label: 'Valor perdido', sortable: true, align: 'right', headerClass: 'w-[150px]' },
    { key: 'fecha_registro', label: 'Fecha de recepción', sortable: true, align: 'right', headerClass: 'w-[160px]' },
    { key: 'responsable', label: 'Registrado por', sortable: true, align: 'left', headerClass: 'min-w-[160px]' },
];

const desde = (dias: number): Date => {
    const fecha = new Date();
    fecha.setHours(0, 0, 0, 0);
    fecha.setDate(fecha.getDate() - dias);
    return fecha;
};

const enPeriodo = (merma: any): boolean => {
    if (mesElegido.value) return String(merma.mes) === mesElegido.value;

    const fecha = new Date(String(merma.fecha_registro));
    if (Number.isNaN(fecha.getTime())) return true;
    if (periodo.value === 'mes') {
        const ahora = new Date();
        return fecha.getFullYear() === ahora.getFullYear() && fecha.getMonth() === ahora.getMonth();
    }
    if (periodo.value === 'todo') return true;

    return fecha >= desde(Number(periodo.value));
};

const delPeriodo = computed(() => (props.mermas ?? []).filter(enPeriodo));

const tabla = useTablaClient(() => delPeriodo.value, {
    columnas,
    busquedaPor: ['numero_op', 'objetivo', 'talla', 'responsable', 'estado_op'],
    porPagina: 10,
    orden: { key: 'fecha_registro', dir: 'desc' },
});

const {
    busqueda,
    porPagina,
    paginaActual,
    paginas,
    desde: desdeFila,
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

const totales = computed(() => {
    const filas = delPeriodo.value;
    const piezas = filas.reduce((suma, m) => suma + (Number(m.cantidad) || 0), 0);
    const valor = filas.reduce((suma, m) => suma + (Number(m.costo_total_perdida) || 0), 0);
    const esperadas = filas.reduce((suma, m) => suma + (Number(m.esperada) || 0), 0);

    return {
        filas: filas.length,
        piezas,
        valor,
        ops: new Set(filas.map((m) => m.numero_op)).size,
        porcentaje: esperadas > 0 ? (piezas / esperadas) * 100 : 0,
    };
});

/** Cuánto se pierde por mes: es la totalización que busca esta página. */
const porMes = computed(() => {
    const grupos = new Map<string, { mes: string; piezas: number; valor: number; ops: Set<string> }>();

    for (const merma of props.mermas ?? []) {
        const mes = String(merma.mes ?? '—');
        if (!grupos.has(mes)) {
            grupos.set(mes, { mes, piezas: 0, valor: 0, ops: new Set<string>() });
        }
        const grupo = grupos.get(mes)!;
        grupo.piezas += Number(merma.cantidad) || 0;
        grupo.valor += Number(merma.costo_total_perdida) || 0;
        if (merma.numero_op) grupo.ops.add(String(merma.numero_op));
    }

    return Array.from(grupos.values())
        .map((grupo) => ({ ...grupo, ops: grupo.ops.size }))
        .sort((a, b) => b.mes.localeCompare(a.mes));
});

const historico = computed(() => {
    const filas = props.mermas ?? [];
    return {
        filas: filas.length,
        piezas: filas.reduce((suma, m) => suma + (Number(m.cantidad) || 0), 0),
        valor: filas.reduce((suma, m) => suma + (Number(m.costo_total_perdida) || 0), 0),
    };
});

const nombreMes = (mes: string): string => {
    if (!mes || mes === '—') return 'Sin fecha';
    const fecha = new Date(`${mes}-01T12:00:00`);
    if (Number.isNaN(fecha.getTime())) return mes;
    return fecha.toLocaleDateString('es-CO', { month: 'long', year: 'numeric' });
};

const texto = (valor: any, fallback = '—'): string => {
    const resultado = String(valor ?? '').trim();
    return resultado !== '' ? resultado : fallback;
};

const dinero = (valor: any): string =>
    valor === null || valor === undefined || valor === ''
        ? '—'
        : zilef_nuber_format(valor, Math.round(Number(valor) * 100) % 100 === 0 ? 0 : 2, true);

const piezas = (valor: any): string => (valor === null || valor === undefined ? '—' : `${zilef_nuber_format(valor, 0, false)}`);

const porcentaje = (merma: any): string => {
    const esperada = Number(merma?.esperada) || 0;
    if (esperada <= 0) return '—';
    return `${((Number(merma.cantidad) || 0) / esperada) * 100}%`;
};

const fecha = (valor: any): string => (valor ? formatDate(valor, 'fecha') : '—');

const selectClass =
    'h-9 rounded-md border border-input bg-background px-2 text-sm text-foreground shadow-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30';

const segmentoClass = (activo: boolean) => [
    'whitespace-nowrap px-3 py-2 text-xs font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring',
    activo
        ? 'bg-foreground text-background shadow-sm'
        : 'text-muted-foreground hover:bg-muted hover:text-foreground',
];

const elegirPeriodo = (nuevo: Periodo) => {
    periodo.value = nuevo;
    mesElegido.value = null;
};

const elegirMes = (mes: string) => {
    mesElegido.value = mesElegido.value === mes ? null : mes;
};

const verDetalle = (merma: any) => {
    mermaVisible.value = merma;
    detalleOpen.value = true;
};

const abrirOrden = (merma: any) => {
    router.get(route('OrdenProduccion.index'), { search: merma.numero_op }, { preserveState: true });
};

const rutaIndex = new URL(String(route('Merma.index')), window.location.origin).pathname;

router.on('start', (event) => {
    const url = event.detail.visit.url;
    const pathname = url instanceof URL ? url.pathname : new URL(String(url), window.location.origin).pathname;
    if (pathname === rutaIndex) cargando.value = true;
});
router.on('finish', () => {
    cargando.value = false;
});

const recargar = () => router.reload({ only: ['mermas'], preserveScroll: true });
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
                        <h1 class="text-2xl font-semibold tracking-tight text-foreground">{{ props.title }}</h1>
                        <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground" role="status">
                            <span class="tabular-nums">
                                <span class="font-medium text-foreground">{{ totales.filas }}</span>
                                {{ totales.filas === 1 ? 'merma' : 'mermas' }} en el periodo
                            </span>
                            <span class="text-border">·</span>
                            <span class="tabular-nums">
                                <span class="font-medium text-foreground">{{ totales.piezas }}</span>
                                {{ totales.piezas === 1 ? 'par perdido' : 'pares perdidos' }}
                            </span>
                            <span class="text-border">·</span>
                            <span class="tabular-nums">
                                Histórico: {{ historico.piezas }} {{ historico.piezas === 1 ? 'par' : 'pares' }}
                                ({{ dinero(historico.valor) }})
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

                        <Link
                            :href="route('OrdenProduccion.index')"
                            class="inline-flex h-9 items-center gap-1.5 rounded-md border border-border bg-card px-3 text-xs font-medium text-foreground shadow-sm transition hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                        >
                            <ClipboardDocumentListIcon class="h-4 w-4" />
                            Órdenes de producción
                        </Link>
                    </div>
                </div>

                <p class="max-w-3xl text-xs text-muted-foreground">
                    La merma no se registra aquí: cada fila es un «No recuperable» anotado en el formulario
                    «Cambiar estado» de la OP al llegar a «Recibiendo». Corregir una merma vuelve a guardar esa
                    recepción, y lo perdido queda totalizado por mes y por periodo.
                </p>
            </div>

            <div
                class="inline-flex w-fit items-stretch divide-x divide-border overflow-hidden rounded-md border border-border bg-card shadow-sm"
                role="tablist"
                aria-label="Periodo de las mermas"
            >
                <button
                    v-for="opcion in periodos"
                    :key="opcion.clave"
                    type="button"
                    role="tab"
                    :aria-selected="periodo === opcion.clave && !mesElegido"
                    :class="segmentoClass(periodo === opcion.clave && !mesElegido)"
                    @click="elegirPeriodo(opcion.clave)"
                >
                    {{ opcion.etiqueta }}
                </button>
            </div>

            <div v-if="mesElegido" class="flex items-center gap-2 text-xs text-muted-foreground">
                <span
                    class="inline-flex items-center gap-1.5 rounded-full border border-border bg-muted/60 px-2.5 py-1 font-medium text-foreground"
                >
                    <ShieldCheckIcon class="h-3.5 w-3.5" />
                    Solo {{ nombreMes(mesElegido) }}
                    <button
                        type="button"
                        @click="mesElegido = null"
                        aria-label="Quitar el filtro del mes"
                        class="rounded p-0.5 transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        <XMarkIcon class="h-3 w-3" />
                    </button>
                </span>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Pares perdidos</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums text-foreground">{{ piezas(totales.piezas) }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        en {{ totales.filas }} {{ totales.filas === 1 ? 'registro' : 'registros' }}
                    </p>
                </div>

                <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Valor perdido</p>
                    <p class="mt-2 flex items-center gap-1.5 text-2xl font-semibold tabular-nums text-foreground">
                        <BanknotesIcon class="h-5 w-5 text-muted-foreground" />
                        {{ dinero(totales.valor) }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">costo de materiales de lo no recuperable</p>
                </div>

                <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Órdenes afectadas</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums text-foreground">{{ totales.ops }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">con al menos una talla con pérdida</p>
                </div>

                <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Pérdida sobre lo esperado</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums text-foreground">
                        {{ totales.porcentaje > 0 ? `${totales.porcentaje.toFixed(1)}%` : '—' }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">de las tallas que reportaron merma</p>
                </div>
            </div>

            <div v-if="porMes.length" class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <div class="border-b border-border px-3 py-2.5">
                    <h2 class="text-sm font-semibold text-foreground">Pérdidas por mes</h2>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        Presiona un mes para filtrar el detalle; vuelve a presionarlo para quitar el filtro.
                    </p>
                </div>
                <table class="w-full min-w-[620px] border-collapse text-sm">
                    <thead>
                        <tr class="bg-muted text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground">
                            <th scope="col" class="px-3 py-2.5 text-left">Mes</th>
                            <th scope="col" class="w-[140px] px-3 py-2.5 text-right">Pares perdidos</th>
                            <th scope="col" class="w-[160px] px-3 py-2.5 text-right">Valor perdido</th>
                            <th scope="col" class="w-[140px] px-3 py-2.5 text-center">Órdenes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="mes in porMes"
                            :key="mes.mes"
                            class="cursor-pointer border-b border-border/60 transition-colors last:border-0 hover:bg-muted/40"
                            :class="mesElegido === mes.mes ? 'bg-muted/60' : ''"
                            @click="elegirMes(mes.mes)"
                        >
                            <td class="px-3 py-2.5 font-medium text-foreground first-letter:uppercase">
                                {{ nombreMes(mes.mes) }}
                            </td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-foreground">{{ piezas(mes.piezas) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums font-semibold text-foreground">
                                {{ dinero(mes.valor) }}
                            </td>
                            <td class="px-3 py-2.5 text-center tabular-nums text-muted-foreground">{{ mes.ops }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <div class="flex flex-wrap items-center gap-2 border-b border-border p-3">
                    <div class="relative min-w-[220px] flex-1">
                        <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <input
                            v-model="busqueda"
                            type="text"
                            aria-label="Buscar merma"
                            placeholder="Buscar por OP, producto, material, talla o responsable…"
                            class="h-9 w-full rounded-md border border-input bg-background pl-9 pr-9 text-sm text-foreground shadow-sm placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30"
                        />
                        <button
                            v-if="busqueda"
                            type="button"
                            @click="limpiar"
                            aria-label="Limpiar búsqueda de mermas"
                            class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-muted-foreground transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <XMarkIcon class="h-3.5 w-3.5" />
                        </button>
                    </div>

                    <select v-model.number="porPagina" aria-label="Mermas por página" :class="selectClass">
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

                <div class="merma-scroll max-h-[68vh] overflow-auto overscroll-contain">
                    <table class="w-full min-w-[1280px] border-collapse text-sm">
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
                                    class="sticky right-0 z-30 w-[88px] border-l border-border bg-muted px-2 py-2.5 text-right text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground"
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
                                            <FunnelIcon v-if="hayBusqueda || mesElegido" class="h-5 w-5 text-muted-foreground" />
                                            <RectangleStackIcon v-else class="h-5 w-5 text-muted-foreground" />
                                        </div>
                                        <h3 class="mt-4 text-sm font-semibold text-foreground">
                                            {{ hayBusqueda || mesElegido ? 'Ninguna merma coincide' : 'Aún no hay mermas registradas' }}
                                        </h3>
                                        <p class="mt-1 text-sm text-muted-foreground">
                                            {{
                                                hayBusqueda || mesElegido
                                                    ? 'Ajusta el periodo o la búsqueda para ver otras mermas.'
                                                    : 'Las mermas aparecen cuando una OP en «Recibiendo» registra pares en «No recuperable».'
                                            }}
                                        </p>
                                        <button
                                            v-if="hayBusqueda || mesElegido"
                                            type="button"
                                            @click="(limpiar(), (mesElegido = null))"
                                            class="mt-4 inline-flex items-center gap-1.5 rounded-md border border-border bg-card px-3 py-1.5 text-xs font-medium text-foreground shadow-sm transition hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        >
                                            <XMarkIcon class="h-3.5 w-3.5" />
                                            Limpiar filtros
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>

                        <tbody v-else :key="paginaActual" class="merma-page">
                            <tr
                                v-for="merma in visibles"
                                :key="merma.id"
                                class="group border-b border-border/60 transition-colors last:border-0 hover:bg-muted/40"
                            >
                                <td class="px-3 py-3 align-top">
                                    <div class="flex flex-col gap-0.5">
                                        <span class="font-medium text-foreground">{{ texto(merma.numero_op) }}</span>
                                        <span class="text-[11px] text-muted-foreground">{{ texto(merma.estado_op, 'Sin estado') }}</span>
                                    </div>
                                </td>

                                <td class="px-3 py-3 align-top">
                                    <div class="flex max-w-[320px] flex-col gap-0.5">
                                        <span class="truncate font-medium text-foreground" :title="merma.objetivo">
                                            {{ texto(merma.objetivo) }}
                                        </span>
                                        <span class="text-[11px] text-muted-foreground">{{ texto(merma.objetivo_tipo) }}</span>
                                    </div>
                                </td>

                                <td class="px-3 py-3 text-center align-top">
                                    <span
                                        class="inline-flex min-w-[44px] justify-center rounded-md border border-border bg-muted/60 px-2 py-1 text-xs font-medium tabular-nums text-foreground"
                                    >
                                        {{ texto(merma.talla) }}
                                    </span>
                                </td>

                                <td class="px-3 py-3 text-center align-top">
                                    <button
                                        type="button"
                                        @click="verDetalle(merma)"
                                        title="Ver la recepción que originó esta merma"
                                        :aria-label="`Ver detalle de la merma ${merma.numero_op} talla ${merma.talla}`"
                                        class="inline-flex items-center gap-1.5 rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-semibold tabular-nums text-red-700 shadow-sm transition hover:bg-red-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300"
                                    >
                                        {{ piezas(merma.cantidad) }}
                                        <span class="text-[11px] font-normal">{{ Number(merma.cantidad) === 1 ? 'par' : 'pares' }}</span>
                                    </button>
                                </td>

                                <td class="px-3 py-3 text-right align-top tabular-nums text-muted-foreground">
                                    {{ porcentaje(merma) }}
                                </td>

                                <td class="px-3 py-3 text-right align-top tabular-nums text-muted-foreground">
                                    {{ dinero(merma.costo_unitario) }}
                                </td>

                                <td class="px-3 py-3 text-right align-top">
                                    <span class="font-semibold tabular-nums text-foreground">
                                        {{ dinero(merma.costo_total_perdida) }}
                                    </span>
                                </td>

                                <td class="px-3 py-3 text-right align-top text-xs tabular-nums text-muted-foreground">
                                    {{ fecha(merma.fecha_registro) }}
                                </td>

                                <td class="px-3 py-3 align-top text-sm text-foreground">{{ texto(merma.responsable) }}</td>

                                <td
                                    class="sticky right-0 z-10 w-[88px] border-l border-border/60 bg-card px-2 py-2 align-middle shadow-[-8px_0_16px_-16px_rgba(0,0,0,0.35)] transition-colors group-hover:bg-muted/40"
                                >
                                    <div class="flex items-center justify-end gap-1">
                                        <button
                                            type="button"
                                            @click="verDetalle(merma)"
                                            title="Ver detalle"
                                            :aria-label="`Ver detalle de la merma de ${merma.numero_op}`"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        >
                                            <EyeIcon class="h-4 w-4" />
                                        </button>
                                        <button
                                            type="button"
                                            @click="abrirOrden(merma)"
                                            title="Ir a la OP"
                                            :aria-label="`Ir a la orden ${merma.numero_op}`"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        >
                                            <ClipboardDocumentListIcon class="h-4 w-4" />
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
                    :from="desdeFila"
                    :to="hasta"
                    :total="total"
                    :item-label="total === 1 ? 'merma' : 'mermas'"
                    @page="cambiarPagina"
                />
            </div>
        </div>

        <DetalleMermaModal
            :show="detalleOpen"
            @close="detalleOpen = false"
            :merma="mermaVisible"
            @ver-orden="abrirOrden"
        />
    </AppLayout>
</template>

<style scoped>
@keyframes merma-page-in {
    from {
        opacity: 0;
        transform: translateY(3px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.merma-page {
    animation: merma-page-in 180ms cubic-bezier(0.22, 1, 0.36, 1);
}

.merma-scroll {
    scrollbar-width: thin;
    scrollbar-color: var(--border) transparent;
}

.merma-scroll::-webkit-scrollbar {
    width: 10px;
    height: 10px;
}

.merma-scroll::-webkit-scrollbar-track {
    background: transparent;
}

.merma-scroll::-webkit-scrollbar-thumb {
    background: var(--border);
    border: 3px solid transparent;
    border-radius: 9999px;
    background-clip: content-box;
}

@media (prefers-reduced-motion: reduce) {
    .merma-page {
        animation: none;
    }
}
</style>
