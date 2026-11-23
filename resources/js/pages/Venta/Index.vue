<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import DataPagination from '@/components/DataPagination.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';

import { use } from 'echarts/core';
import { BarChart, LineChart } from 'echarts/charts';
import { GridComponent, LegendComponent, TooltipComponent } from 'echarts/components';
import { LegacyGridContainLabel } from 'echarts/features';
import { CanvasRenderer } from 'echarts/renderers';
import VChart from 'vue-echarts';

import {
    ArrowPathIcon,
    ArrowTrendingDownIcon,
    ArrowTrendingUpIcon,
    BanknotesIcon,
    ChartBarIcon,
    ChevronDownIcon,
    ChevronRightIcon,
    ChevronUpDownIcon,
    ChevronUpIcon,
    FunnelIcon,
    MagnifyingGlassIcon,
    RectangleStackIcon,
    ReceiptPercentIcon,
    ShoppingCartIcon,
    XMarkIcon,
} from '@heroicons/vue/24/solid';

import { useTablaClient, type ColumnaTabla } from '@/composables/useTablaClient';
import { formatDate, zilef_nuber_format } from '@/global.ts';

use([BarChart, LineChart, GridComponent, LegendComponent, TooltipComponent, LegacyGridContainLabel, CanvasRenderer]);

const props = defineProps({
    ventas: { type: Array as () => any[], default: () => [] },
    breadcrumbs: { type: Array as () => Array<{ label?: string; href?: string }>, default: () => [] },
    title: String,
    numberPermissions: Number,
});

type Periodo = 'mes' | '30' | '60' | 'todo';

type LineaVenta = {
    key: string;
    venta_id: number;
    fecha: string | null;
    forma_pago: string;
    forma_pago_label: string;
    subtotal: number;
    descuento_monto: number;
    impuesto_monto: number;
    total_venta: number;
    observaciones: string | null;
    registrado_por: string | null;
    producto: string;
    talla: string;
    cantidad: number;
    precio_unitario: number;
    total: number;
};

const periodos: { clave: Periodo; etiqueta: string }[] = [
    { clave: 'mes', etiqueta: 'Mes actual' },
    { clave: '30', etiqueta: 'Últimos 30 días' },
    { clave: '60', etiqueta: 'Últimos 60 días' },
    { clave: 'todo', etiqueta: 'Todo el histórico' },
];

const state = reactive({
    periodo: 'todo' as Periodo,
    formaPagoElegida: '',
    fechaInicio: '',
    fechaFin: '',
    cargando: false,
    metrica: 'ingreso' as 'ingreso' | 'unidades' | 'ventas',
    esOscuro: false,
});

let observadorTema: MutationObserver | null = null;

const nombreFormaPago = (venta: any): string => {
    const base: Record<string, string> = { contado: 'Contado', credito: 'Crédito', otro: 'Otro' };
    const detalle = String(venta?.forma_pago_detalle ?? '').trim();
    if (venta?.forma_pago === 'otro' && detalle) return `Otro · ${detalle}`;
    return base[String(venta?.forma_pago ?? '')] ?? '—';
};

/** Cada venta se abre en una fila por producto y talla vendidos. */
const lineas = computed<LineaVenta[]>(() =>
    (props.ventas ?? []).flatMap((venta: any) =>
        (venta.detalles ?? []).map((detalle: any) => ({
            key: `${venta.id}-${detalle.id}`,
            venta_id: Number(venta.id),
            fecha: venta.fecha ?? null,
            forma_pago: String(venta.forma_pago ?? ''),
            forma_pago_label: nombreFormaPago(venta),
            subtotal: Number(venta.subtotal) || 0,
            descuento_monto: Number(venta.descuento_monto) || 0,
            impuesto_monto: Number(venta.impuesto_monto) || 0,
            total_venta: Number(venta.total) || 0,
            observaciones: venta.observaciones ?? null,
            registrado_por: venta.registrado_por ?? null,
            producto:
                [detalle.codigo, detalle.titulo]
                    .map((valor) => String(valor ?? '').trim())
                    .filter(Boolean)
                    .join(' — ') || `Producto #${detalle.producto_id}`,
            talla: String(detalle.talla ?? ''),
            cantidad: Number(detalle.cantidad) || 0,
            precio_unitario: Number(detalle.precio_unitario) || 0,
            total: Number(detalle.total) || 0,
        })),
    ),
);

const columnas: ColumnaTabla[] = [
    { key: 'venta_id', label: 'Venta', sortable: true, align: 'left', headerClass: 'w-[110px]' },
    { key: 'fecha', label: 'Fecha', sortable: true, align: 'left', headerClass: 'w-[130px]' },
    { key: 'producto', label: 'Producto vendido', sortable: true, align: 'left', headerClass: 'min-w-[280px]' },
    { key: 'talla', label: 'Talla', sortable: true, align: 'center', headerClass: 'w-[90px]' },
    { key: 'cantidad', label: 'Cantidad', sortable: true, align: 'center', headerClass: 'w-[110px]' },
    { key: 'precio_unitario', label: 'Precio unitario', sortable: true, align: 'right', headerClass: 'w-[150px]' },
    { key: 'total', label: 'Total', sortable: true, align: 'right', headerClass: 'w-[140px]' },
    { key: 'forma_pago_label', label: 'Forma de pago', sortable: true, align: 'left', headerClass: 'min-w-[140px]' },
    { key: 'registrado_por', label: 'Registrado por', sortable: true, align: 'left', headerClass: 'min-w-[150px]' },
];

// La fecha de la venta se guarda como día sin hora: se interpreta a mediodía
// local para que no se corra un día por la zona horaria.
const fechaLocal = (valor: string | null): Date | null => {
    if (!valor) return null;
    const fecha = new Date(`${valor}T12:00:00`);
    return Number.isNaN(fecha.getTime()) ? null : fecha;
};

const hayRangoFechas = computed(() => Boolean(state.fechaInicio || state.fechaFin));

const dentroDeRangoFechas = (valor: string | null): boolean => {
    const fecha = fechaLocal(valor);
    if (!fecha) return false;

    if (state.fechaInicio) {
        const inicio = new Date(`${state.fechaInicio}T00:00:00`);
        if (fecha < inicio) return false;
    }
    if (state.fechaFin) {
        const fin = new Date(`${state.fechaFin}T23:59:59.999`);
        if (fecha > fin) return false;
    }

    return true;
};

const enPeriodo = (linea: LineaVenta): boolean => {
    if (hayRangoFechas.value) return dentroDeRangoFechas(linea.fecha);

    const fecha = fechaLocal(linea.fecha);
    if (!fecha) return true;
    if (state.periodo === 'mes') {
        const ahora = new Date();
        return fecha.getFullYear() === ahora.getFullYear() && fecha.getMonth() === ahora.getMonth();
    }
    if (state.periodo === 'todo') return true;

    const desde = new Date();
    desde.setHours(0, 0, 0, 0);
    desde.setDate(desde.getDate() - Number(state.periodo));
    return fecha >= desde;
};

const cumpleFormaPago = (linea: LineaVenta): boolean =>
    state.formaPagoElegida === '' || linea.forma_pago === state.formaPagoElegida;

const filasFiltradas = computed(() => lineas.value.filter((linea) => enPeriodo(linea) && cumpleFormaPago(linea)));

const hayFiltros = computed(
    () => Boolean(hayRangoFechas.value || state.formaPagoElegida || state.periodo !== 'todo'),
);

const limpiarFiltros = () => {
    state.periodo = 'todo';
    state.formaPagoElegida = '';
    state.fechaInicio = '';
    state.fechaFin = '';
};

const tabla = useTablaClient(() => filasFiltradas.value, {
    columnas,
    busquedaPor: ['venta_id', 'producto', 'talla', 'forma_pago_label', 'registrado_por', 'observaciones'],
    porPagina: 10,
    orden: { key: 'fecha', dir: 'desc' },
});

const {
    busqueda,
    porPagina,
    paginaActual,
    paginas,
    desde: desdeFila,
    hasta,
    visibles,
    filtradas: filasDeTabla,
    total,
    hayBusqueda,
    cambiarPagina,
    ordenarPor,
    indicador,
    ariaSort,
    limpiar,
} = tabla;

/** Ventas distintas del filtro: el descuento y el impuesto son por venta. */
const ventasFiltradas = computed(() => {
    const mapa = new Map<number, { total: number; descuento: number; impuesto: number }>();
    for (const linea of filasFiltradas.value) {
        if (!mapa.has(linea.venta_id)) {
            mapa.set(linea.venta_id, {
                total: linea.total_venta,
                descuento: linea.descuento_monto,
                impuesto: linea.impuesto_monto,
            });
        }
    }
    return Array.from(mapa.values());
});

const totales = computed(() => {
    const ingreso = ventasFiltradas.value.reduce((suma, venta) => suma + venta.total, 0);

    return {
        ventas: ventasFiltradas.value.length,
        lineas: filasFiltradas.value.length,
        unidades: filasFiltradas.value.reduce((suma, linea) => suma + linea.cantidad, 0),
        ingreso,
        ticket: ventasFiltradas.value.length ? ingreso / ventasFiltradas.value.length : 0,
        descuento: ventasFiltradas.value.reduce((suma, venta) => suma + venta.descuento, 0),
        impuesto: ventasFiltradas.value.reduce((suma, venta) => suma + venta.impuesto, 0),
    };
});

/** Totales de lo que la tabla muestra (incluye la búsqueda de texto). */
const totalesTabla = computed(() => ({
    unidades: filasDeTabla.value.reduce((suma, linea) => suma + linea.cantidad, 0),
    total: filasDeTabla.value.reduce((suma, linea) => suma + linea.total, 0),
}));

const dinero = (valor: any): string =>
    valor === null || valor === undefined || valor === ''
        ? '—'
        : zilef_nuber_format(valor, Math.round(Number(valor) * 100) % 100 === 0 ? 0 : 2, true);

const piezas = (valor: any): string => zilef_nuber_format(valor, 0, false);

const fecha = (valor: string | null): string => {
    const fechaVenta = fechaLocal(valor);
    return fechaVenta ? formatDate(fechaVenta, 'fecha') : '—';
};

const texto = (valor: any, fallback = '—'): string => {
    const resultado = String(valor ?? '').trim();
    return resultado !== '' ? resultado : fallback;
};

const fechaCorta = (valor: string): string => (valor ? formatDate(new Date(`${valor}T12:00:00`), 'fecha') : '');

const textoRangoFechas = computed(() => {
    if (state.fechaInicio && state.fechaFin) return `${fechaCorta(state.fechaInicio)} → ${fechaCorta(state.fechaFin)}`;
    if (state.fechaInicio) return `Desde ${fechaCorta(state.fechaInicio)}`;
    if (state.fechaFin) return `Hasta ${fechaCorta(state.fechaFin)}`;
    return '';
});

const selectClass =
    'h-9 rounded-md border border-input bg-background px-2 text-sm text-foreground shadow-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30';

const segmentoClass = (activo: boolean) => [
    'whitespace-nowrap px-3 py-2 text-xs font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring',
    activo
        ? 'bg-foreground text-background shadow-sm'
        : 'text-muted-foreground hover:bg-muted hover:text-foreground',
];

// ── Gráfica de tendencia de ventas mensuales (últimos 12 meses) ─────────────────

const MESES_CORTOS = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

const claveMes = (f: Date): string =>
    `${f.getFullYear()}-${String(f.getMonth() + 1).padStart(2, '0')}`;

const serieMensual = computed(() => {
    const mapa = new Map<string, { total: number; unidades: number; ventas: Set<number> }>();

    for (const venta of props.ventas ?? []) {
        if (!venta.fecha) continue;
        const partes = String(venta.fecha).split('-');
        if (partes.length < 2) continue;
        const clave = `${partes[0]}-${partes[1]}`;

        if (!mapa.has(clave)) {
            mapa.set(clave, { total: 0, unidades: 0, ventas: new Set<number>() });
        }
        const grupo = mapa.get(clave)!;
        grupo.total += Number(venta.total) || 0;
        grupo.ventas.add(Number(venta.id));

        for (const detalle of venta.detalles ?? []) {
            grupo.unidades += Number(detalle.cantidad) || 0;
        }
    }

    const hoy = new Date();

    return Array.from({ length: 12 }, (_, indice) => {
        const fechaObj = new Date(hoy.getFullYear(), hoy.getMonth() - (11 - indice), 1);
        const mes = claveMes(fechaObj);
        const datos = mapa.get(mes) ?? { total: 0, unidades: 0, ventas: new Set<number>() };

        return {
            mes,
            etiqueta: `${MESES_CORTOS[fechaObj.getMonth()]} ${String(fechaObj.getFullYear()).slice(2)}`,
            nombre: fechaObj.toLocaleDateString('es-CO', { month: 'long', year: 'numeric' }),
            total: datos.total,
            unidades: datos.unidades,
            ventas: datos.ventas.size,
            actual: indice === 11,
        };
    });
});

const haySerieMensual = computed(() => serieMensual.value.some((p) => p.total > 0 || p.unidades > 0));

const valoresSerie = computed(() =>
    serieMensual.value.map((p) => {
        if (state.metrica === 'unidades') return p.unidades;
        if (state.metrica === 'ventas') return p.ventas;
        return p.total;
    }),
);

const formatearMetrica = (valor: number): string => {
    if (state.metrica === 'ingreso') return dinero(valor);
    if (state.metrica === 'unidades') return `${piezas(valor)} unidades`;
    return `${piezas(valor)} ventas`;
};

const formatearConSigno = (valor: number): string => {
    const signo = valor > 0 ? '+' : valor < 0 ? '−' : '';
    return `${signo}${formatearMetrica(Math.abs(valor))}`;
};

const estadisticas = computed(() => {
    const valores = valoresSerie.value;
    const count = valores.length;
    const suma = valores.reduce((acc, v) => acc + v, 0);
    const media = count > 0 ? suma / count : 0;

    let sumaX = 0;
    let sumaY = 0;
    let sumaXY = 0;
    let sumaXX = 0;

    valores.forEach((v, i) => {
        sumaX += i;
        sumaY += v;
        sumaXY += i * v;
        sumaXX += i * i;
    });

    const denominador = count * sumaXX - sumaX * sumaX;
    const pendiente = denominador !== 0 ? (count * sumaXY - sumaX * sumaY) / denominador : 0;
    const intercepto = count > 0 ? (sumaY - pendiente * sumaX) / count : 0;
    const prediccion = valores.map((_, i) => intercepto + pendiente * i);
    const sumaResidual = valores.reduce((acc, v, i) => acc + (v - prediccion[i]) ** 2, 0);
    const sumaTotal = valores.reduce((acc, v) => acc + (v - media) ** 2, 0);
    const r2 = sumaTotal > 0 ? 1 - sumaResidual / sumaTotal : null;

    const actual = valores[valores.length - 1] ?? 0;
    const desviacion = media > 0 ? ((actual - media) / media) * 100 : 0;

    return {
        media,
        pendiente,
        intercepto,
        r2,
        actual,
        desviacion,
        total: suma,
    };
});

const textoTendencia = computed(() => {
    const pendiente = estadisticas.value.pendiente;
    if (Math.abs(pendiente) < 0.005) return 'Sin cambios apreciables por mes';
    return pendiente > 0 ? 'Las ventas suben mes a mes' : 'Las ventas bajan mes a mes';
});

const colorTexto = computed(() => (state.esOscuro ? '#cbd5e1' : '#475569'));
const colorLinea = computed(() => (state.esOscuro ? 'rgba(148,163,184,.14)' : 'rgba(100,116,139,.14)'));
const fondoTooltip = computed(() => (state.esOscuro ? '#1e293b' : '#ffffff'));
const bordeTooltip = computed(() => (state.esOscuro ? 'rgba(148,163,184,.25)' : '#e2e8f0'));

const baseEje = computed(() => ({
    axisLine: { lineStyle: { color: colorLinea.value } },
    axisTick: { show: false },
    axisLabel: { color: colorTexto.value, fontSize: 11 },
    splitLine: { lineStyle: { color: colorLinea.value } },
}));

const abreviatura = (valor: number): string => {
    const abs = Math.abs(valor);
    if (abs >= 1_000_000) return `${(valor / 1_000_000).toFixed(1).replace('.', ',')} M`;
    if (abs >= 1_000) return `${Math.round(valor / 1_000)} k`;
    return String(Math.round(valor));
};

const opcionesGrafica = computed(() => {
    const puntos = serieMensual.value;
    const valores = valoresSerie.value;
    const est = estadisticas.value;
    const regresion = valores.map((_, indice) => est.intercepto + est.pendiente * indice);

    const tituloMetrica =
        state.metrica === 'ingreso' ? 'Ingresos' : state.metrica === 'unidades' ? 'Unidades' : 'Ventas';

    return {
        tooltip: {
            trigger: 'axis',
            backgroundColor: fondoTooltip.value,
            borderColor: bordeTooltip.value,
            textStyle: { color: state.esOscuro ? '#f1f5f9' : '#0f172a', fontSize: 12 },
            axisPointer: { type: 'shadow' },
            formatter: (params: any) => {
                const fila = Array.isArray(params) ? params[0] : params;
                const indice = fila?.dataIndex ?? 0;
                const punto = puntos[indice];
                if (!punto) return '';

                const valor = valores[indice] ?? 0;
                const diferencia = valor - est.media;
                const signo = diferencia > 0 ? '+' : diferencia < 0 ? '−' : '';
                const comparacion =
                    diferencia === 0
                        ? 'Igual a la media'
                        : `${signo}${formatearMetrica(Math.abs(diferencia))} vs. media`;

                return [
                    `<div style="font-weight:600;margin-bottom:4px;text-transform:capitalize">${punto.nombre}</div>`,
                    `<div>${tituloMetrica}: <b>${formatearMetrica(valor)}</b></div>`,
                    `<div>Media mensual: ${formatearMetrica(est.media)}</div>`,
                    `<div>Tendencia calculada: ${formatearConSigno(regresion[indice] ?? 0)}</div>`,
                    `<div style="opacity:.8;margin-top:2px">${comparacion}</div>`,
                ].join('');
            },
        },
        legend: {
            top: 0,
            textStyle: { color: colorTexto.value, fontSize: 12 },
            icon: 'roundRect',
            itemWidth: 14,
            itemHeight: 8,
        },
        grid: { left: 8, right: 12, bottom: 0, top: 34, containLabel: true },
        xAxis: {
            type: 'category',
            data: puntos.map((p) => p.etiqueta),
            ...baseEje.value,
            splitLine: { show: false },
        },
        yAxis: {
            type: 'value',
            ...baseEje.value,
            axisLabel: {
                ...baseEje.value.axisLabel,
                formatter: (valor: number) => (state.metrica === 'ingreso' ? `$${abreviatura(valor)}` : abreviatura(valor)),
            },
        },
        series: [
            {
                name: tituloMetrica,
                type: 'bar',
                barMaxWidth: 28,
                data: valores.map((valor, indice) => ({
                    value: valor,
                    itemStyle: {
                        color: indice === valores.length - 1 ? '#0ea5e9' : 'rgba(14,165,233,.45)',
                        borderRadius: [6, 6, 0, 0],
                    },
                })),
            },
            {
                name: 'Media',
                type: 'line',
                data: valores.map(() => Number(est.media.toFixed(2))),
                symbol: 'none',
                lineStyle: { color: '#f59e0b', width: 1.5, type: 'dashed' },
                itemStyle: { color: '#f59e0b' },
            },
            {
                name: 'Tendencia',
                type: 'line',
                data: regresion.map((valor) => Number(valor.toFixed(2))),
                symbol: 'none',
                lineStyle: { color: '#6366f1', width: 2.5 },
                itemStyle: { color: '#6366f1' },
            },
        ],
    };
});

onMounted(() => {
    state.esOscuro = document.documentElement.classList.contains('dark');
    observadorTema = new MutationObserver(() => {
        state.esOscuro = document.documentElement.classList.contains('dark');
    });
    observadorTema.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
});

onUnmounted(() => {
    observadorTema?.disconnect();
});

const recargar = () => router.reload({ only: ['ventas'], preserveScroll: true });

router.on('start', () => {
    state.cargando = true;
});
router.on('finish', () => {
    state.cargando = false;
});
const breadcrumbCollapsed = ref(false);

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
                        <h1 class="text-2xl font-semibold tracking-tight text-foreground">{{ props.title }}</h1>
                        <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground" role="status">
                            <span class="tabular-nums">
                                <span class="font-medium text-foreground">{{ totales.ventas }}</span>
                                {{ totales.ventas === 1 ? 'venta' : 'ventas' }} en el periodo
                            </span>
                            <span class="text-border">·</span>
                            <span class="tabular-nums">
                                <span class="font-medium text-foreground">{{ totales.unidades }}</span>
                                {{ totales.unidades === 1 ? 'unidad vendida' : 'unidades vendidas' }}
                            </span>
                            <span class="text-border">·</span>
                            <span class="tabular-nums">
                                Descuentos {{ dinero(totales.descuento) }} · Impuestos {{ dinero(totales.impuesto) }}
                            </span>
                        </p>
                    </div>

                    <button
                        type="button"
                        @click="recargar"
                        :disabled="state.cargando"
                        title="Actualizar datos"
                        aria-label="Actualizar datos"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:pointer-events-none disabled:opacity-50"
                    >
                        <ArrowPathIcon class="h-4 w-4" :class="{ 'animate-spin': state.cargando }" />
                    </button>
                </div>

                <p class="max-w-3xl text-xs text-muted-foreground">
                    Cada fila es una talla vendida. Las ventas se registran desde el botón «Vender productos» de
                    Productos y descuentan la existencia recibida; aquí solo se consultan.
                </p>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div
                    class="inline-flex w-fit items-stretch divide-x divide-border overflow-hidden rounded-md border border-border bg-card shadow-sm"
                    role="tablist"
                    aria-label="Periodo de las ventas"
                >
                    <button
                        v-for="opcion in periodos"
                        :key="opcion.clave"
                        type="button"
                        role="tab"
                        :aria-selected="state.periodo === opcion.clave && !hayRangoFechas"
                        :class="segmentoClass(state.periodo === opcion.clave && !hayRangoFechas)"
                        @click="state.periodo = opcion.clave"
                    >
                        {{ opcion.etiqueta }}
                    </button>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <div class="flex items-center gap-1.5">
                        <label for="filtro-fecha-inicio" class="text-xs font-medium text-muted-foreground">Desde</label>
                        <input
                            id="filtro-fecha-inicio"
                            v-model="state.fechaInicio"
                            type="date"
                            aria-label="Fecha de inicio"
                            :class="selectClass"
                        />
                    </div>

                    <div class="flex items-center gap-1.5">
                        <label for="filtro-fecha-fin" class="text-xs font-medium text-muted-foreground">Hasta</label>
                        <input
                            id="filtro-fecha-fin"
                            v-model="state.fechaFin"
                            type="date"
                            aria-label="Fecha de fin"
                            :class="selectClass"
                        />
                    </div>

                    <label class="sr-only" for="filtro-forma-pago">Filtrar por forma de pago</label>
                    <select id="filtro-forma-pago" v-model="state.formaPagoElegida" :class="selectClass">
                        <option value="">Todas las formas de pago</option>
                        <option value="contado">Contado</option>
                        <option value="credito">Crédito</option>
                        <option value="otro">Otro</option>
                    </select>
                </div>
            </div>

            <div v-if="hayFiltros" class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                <span
                    v-if="hayRangoFechas"
                    class="inline-flex items-center gap-1.5 rounded-full border border-border bg-muted/60 px-2.5 py-1 font-medium text-foreground"
                >
                    <FunnelIcon class="h-3.5 w-3.5" />
                    {{ textoRangoFechas }}
                    <button
                        type="button"
                        @click="(state.fechaInicio = ''), (state.fechaFin = '')"
                        aria-label="Quitar el filtro de fechas"
                        class="rounded p-0.5 transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        <XMarkIcon class="h-3 w-3" />
                    </button>
                </span>

                <span
                    v-if="state.formaPagoElegida"
                    class="inline-flex items-center gap-1.5 rounded-full border border-border bg-muted/60 px-2.5 py-1 font-medium text-foreground"
                >
                    <FunnelIcon class="h-3.5 w-3.5" />
                    Forma de pago:
                    {{ state.formaPagoElegida === 'contado' ? 'Contado' : state.formaPagoElegida === 'credito' ? 'Crédito' : 'Otro' }}
                    <button
                        type="button"
                        @click="state.formaPagoElegida = ''"
                        aria-label="Quitar el filtro de la forma de pago"
                        class="rounded p-0.5 transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        <XMarkIcon class="h-3 w-3" />
                    </button>
                </span>

                <button
                    type="button"
                    @click="limpiarFiltros"
                    class="rounded-sm font-medium text-foreground underline-offset-2 transition hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                >
                    Limpiar filtros
                </button>
            </div>

            <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <div class="flex flex-wrap items-center gap-2 border-b border-border p-3">
                    <div class="relative min-w-[220px] flex-1">
                        <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <input
                            v-model="busqueda"
                            type="text"
                            aria-label="Buscar venta"
                            placeholder="Buscar por venta, producto, talla, forma de pago o responsable…"
                            class="h-9 w-full rounded-md border border-input bg-background pl-9 pr-9 text-sm text-foreground shadow-sm placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30"
                        />
                        <button
                            v-if="busqueda"
                            type="button"
                            @click="limpiar"
                            aria-label="Limpiar búsqueda de ventas"
                            class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-muted-foreground transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <XMarkIcon class="h-3.5 w-3.5" />
                        </button>
                    </div>

                    <select v-model.number="porPagina" aria-label="Ventas por página" :class="selectClass">
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

                <div class="max-h-[68vh] overflow-auto overscroll-contain">
                    <table class="w-full min-w-[1300px] border-collapse text-sm">
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
                            </tr>
                        </thead>

                        <tbody v-if="total === 0">
                            <tr>
                                <td :colspan="columnas.length" class="px-6 py-16">
                                    <div class="mx-auto flex max-w-sm flex-col items-center text-center">
                                        <div class="flex h-12 w-12 items-center justify-center rounded-full border border-border bg-muted/60">
                                            <FunnelIcon v-if="hayBusqueda || hayFiltros" class="h-5 w-5 text-muted-foreground" />
                                            <RectangleStackIcon v-else class="h-5 w-5 text-muted-foreground" />
                                        </div>
                                        <h3 class="mt-4 text-sm font-semibold text-foreground">
                                            {{ hayBusqueda || hayFiltros ? 'Ninguna venta coincide' : 'Aún no hay ventas registradas' }}
                                        </h3>
                                        <p class="mt-1 text-sm text-muted-foreground">
                                            {{
                                                hayBusqueda || hayFiltros
                                                    ? 'Ajusta el periodo, la forma de pago o la búsqueda para ver otras ventas.'
                                                    : 'Las ventas aparecen cuando registras una desde «Vender productos» en Productos.'
                                            }}
                                        </p>
                                        <button
                                            v-if="hayBusqueda || hayFiltros"
                                            type="button"
                                            @click="(limpiar(), limpiarFiltros())"
                                            class="mt-4 inline-flex items-center gap-1.5 rounded-md border border-border bg-card px-3 py-1.5 text-xs font-medium text-foreground shadow-sm transition hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        >
                                            <XMarkIcon class="h-3.5 w-3.5" />
                                            Limpiar filtros
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>

                        <tbody v-else>
                            <tr
                                v-for="linea in visibles"
                                :key="linea.key"
                                class="border-b border-border/60 transition-colors last:border-0 hover:bg-muted/40"
                            >
                                <td class="px-3 py-3 align-top">
                                    <span class="inline-flex items-center gap-1.5 font-medium tabular-nums text-foreground">
                                        <ShoppingCartIcon class="h-3.5 w-3.5 text-muted-foreground" />
                                        #{{ linea.venta_id }}
                                    </span>
                                    <div v-if="linea.observaciones" class="mt-0.5 max-w-[150px] truncate text-[11px] text-muted-foreground"
                                        :title="linea.observaciones">
                                        {{ linea.observaciones }}
                                    </div>
                                </td>

                                <td class="px-3 py-3 align-top text-xs tabular-nums text-muted-foreground">
                                    {{ fecha(linea.fecha) }}
                                </td>

                                <td class="px-3 py-3 align-top">
                                    <span class="block max-w-[360px] truncate font-medium text-foreground"
                                        :title="linea.producto">
                                        {{ linea.producto }}
                                    </span>
                                </td>

                                <td class="px-3 py-3 text-center align-top">
                                    <span
                                        class="inline-flex min-w-[44px] justify-center rounded-md border border-border bg-muted/60 px-2 py-1 text-xs font-medium tabular-nums text-foreground"
                                    >
                                        {{ texto(linea.talla) }}
                                    </span>
                                </td>

                                <td class="px-3 py-3 text-center align-top">
                                    <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold tabular-nums text-emerald-700 shadow-sm dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300">
                                        {{ piezas(linea.cantidad) }}
                                    </span>
                                </td>

                                <td class="px-3 py-3 text-right align-top tabular-nums text-muted-foreground">
                                    {{ dinero(linea.precio_unitario) }}
                                </td>

                                <td class="px-3 py-3 text-right align-top">
                                    <span class="font-semibold tabular-nums text-foreground">{{ dinero(linea.total) }}</span>
                                </td>

                                <td class="px-3 py-3 align-top text-sm text-foreground">{{ texto(linea.forma_pago_label) }}</td>

                                <td class="px-3 py-3 align-top text-sm text-foreground">{{ texto(linea.registrado_por) }}</td>
                            </tr>
                        </tbody>

                        <tfoot v-if="total > 0">
                            <tr
                                class="sticky bottom-0 z-20 bg-muted text-foreground shadow-[0_-8px_16px_-16px_rgba(0,0,0,0.35)]"
                            >
                                <td
                                    colspan="4"
                                    class="border-t border-border px-3 py-2.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground"
                                >
                                    Totales
                                    <span class="ml-1 font-normal normal-case tracking-normal">
                                        ({{ totalesTabla.unidades }} {{ totalesTabla.unidades === 1 ? 'unidad' : 'unidades' }})
                                    </span>
                                </td>
                                <td class="border-t border-border px-3 py-2.5 text-center font-semibold tabular-nums text-foreground">
                                    {{ piezas(totalesTabla.unidades) }}
                                </td>
                                <td class="border-t border-border px-3 py-2.5"></td>
                                <td class="border-t border-border px-3 py-2.5 text-right font-semibold tabular-nums text-foreground">
                                    {{ dinero(totalesTabla.total) }}
                                </td>
                                <td class="border-t border-border px-3 py-2.5"></td>
                                <td class="border-t border-border px-3 py-2.5"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <DataPagination
                    :current-page="paginaActual"
                    :last-page="paginas"
                    :from="desdeFila"
                    :to="hasta"
                    :total="total"
                    :item-label="total === 1 ? 'línea vendida' : 'líneas vendidas'"
                    @page="cambiarPagina"
                />
            </div>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Ingreso registrado</p>
                    <p class="mt-2 flex items-center gap-1.5 text-2xl font-semibold tabular-nums text-foreground">
                        <BanknotesIcon class="h-5 w-5 text-muted-foreground" />
                        {{ dinero(totales.ingreso) }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">total de las ventas del periodo</p>
                </div>

                <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Unidades vendidas</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums text-foreground">{{ piezas(totales.unidades) }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        en {{ totales.lineas }} {{ totales.lineas === 1 ? 'línea' : 'líneas' }} de producto y talla
                    </p>
                </div>

                <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Ventas</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums text-foreground">{{ totales.ventas }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">registros de venta del periodo</p>
                </div>

                <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Ticket promedio</p>
                    <p class="mt-2 flex items-center gap-1.5 text-2xl font-semibold tabular-nums text-foreground">
                        <ReceiptPercentIcon class="h-5 w-5 text-muted-foreground" />
                        {{ dinero(totales.ticket) }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">ingreso promedio por venta</p>
                </div>
            </div>

            <div
                v-if="haySerieMensual"
                class="overflow-hidden rounded-xl border border-border bg-card shadow-sm"
            >
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-border px-4 py-3">
                    <div class="min-w-0">
                        <h2 class="flex items-center gap-2 text-sm font-semibold text-foreground">
                            <ChartBarIcon class="h-4 w-4 text-sky-500" />
                            Tendencia de ventas mensuales
                        </h2>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            Últimos 12 meses · el mes actual se destaca; la línea punteada es la media y la continua representa la tendencia lineal.
                        </p>
                    </div>

                    <div
                        class="inline-flex items-stretch divide-x divide-border overflow-hidden rounded-md border border-border bg-card"
                        role="tablist"
                        aria-label="Métrica de la gráfica de ventas"
                    >
                        <button
                            type="button"
                            role="tab"
                            :aria-selected="state.metrica === 'ingreso'"
                            :class="segmentoClass(state.metrica === 'ingreso')"
                            @click="state.metrica = 'ingreso'"
                        >
                            Ingresos
                        </button>
                        <button
                            type="button"
                            role="tab"
                            :aria-selected="state.metrica === 'unidades'"
                            :class="segmentoClass(state.metrica === 'unidades')"
                            @click="state.metrica = 'unidades'"
                        >
                            Unidades
                        </button>
                        <button
                            type="button"
                            role="tab"
                            :aria-selected="state.metrica === 'ventas'"
                            :class="segmentoClass(state.metrica === 'ventas')"
                            @click="state.metrica = 'ventas'"
                        >
                            Ventas
                        </button>
                    </div>
                </div>

                <div class="px-2 pt-3 sm:px-4">
                    <div class="h-72 w-full">
                        <VChart :option="opcionesGrafica" autoresize class="h-full w-full" />
                    </div>
                </div>

                <div class="grid gap-4 border-t border-border p-4 sm:grid-cols-2">
                    <div>
                        <p class="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">Media mensual (12 meses)</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums text-foreground">
                            {{ formatearMetrica(estadisticas.media) }}
                        </p>
                        <p class="mt-0.5 text-[11px] text-muted-foreground">
                            {{
                                state.metrica === 'ingreso'
                                    ? 'promedio de ingresos por mes'
                                    : state.metrica === 'unidades'
                                      ? 'promedio de unidades vendidas por mes'
                                      : 'promedio de ventas por mes'
                            }}
                        </p>
                    </div>

                    <div>
                        <p class="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">Tendencia</p>
                        <p
                            class="mt-1 flex items-center gap-1 text-lg font-semibold tabular-nums"
                            :class="
                                estadisticas.pendiente > 0.005
                                    ? 'text-emerald-600 dark:text-emerald-400'
                                    : estadisticas.pendiente < -0.005
                                      ? 'text-red-600 dark:text-red-400'
                                      : 'text-foreground'
                            "
                        >
                            <ArrowTrendingUpIcon v-if="estadisticas.pendiente > 0.005" class="h-4 w-4" />
                            <ArrowTrendingDownIcon v-else-if="estadisticas.pendiente < -0.005" class="h-4 w-4" />
                            {{ formatearConSigno(estadisticas.pendiente) }}
                            <span class="text-xs font-normal text-muted-foreground">/mes</span>
                        </p>
                        <p class="mt-0.5 text-[11px] text-muted-foreground">{{ textoTendencia }}</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
