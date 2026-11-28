<script setup lang="ts">
import { computed } from 'vue';
import { use } from 'echarts/core';
import { BarChart, LineChart } from 'echarts/charts';
import { GridComponent, LegendComponent, TooltipComponent } from 'echarts/components';
import { LegacyGridContainLabel } from 'echarts/features';
import { CanvasRenderer } from 'echarts/renderers';
import VChart from 'vue-echarts';
import { ArrowTrendingDownIcon, ArrowTrendingUpIcon, ChartBarIcon } from '@heroicons/vue/24/solid';

const props = defineProps<{
    mermas: any[];
    produccion: any[];
    periodo: string;
    mesElegido: string | null;
    fechaInicio: string;
    fechaFin: string;
    metrica: 'piezas' | 'valor';
    esOscuro: boolean;
    hayRangoFechas: boolean;
    dentroDeRangoFechas: (valor: any) => boolean;
    nombreMes: (mes: string) => string;
    dinero: (valor: any) => string;
    piezas: (valor: any) => string;
    elegirMes: (mes: string) => void;
}>()

const emit = defineEmits(['cambiar-metrica']);

use([BarChart, LineChart, GridComponent, LegendComponent, TooltipComponent, LegacyGridContainLabel, CanvasRenderer]);

const MESES_CORTOS = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

const claveMes = (fecha: Date): string => `${fecha.getFullYear()}-${String(fecha.getMonth() + 1).padStart(2, '0')}`;

const desde = (dias: number): Date => {
    const f = new Date();
    f.setHours(0, 0, 0, 0);
    f.setDate(f.getDate() - dias);
    return f;
};

const recibidoEnPeriodo = (registro: any): boolean => {
    if (props.hayRangoFechas) return props.dentroDeRangoFechas(registro.fecha);
    if (props.mesElegido) return String(registro.mes) === props.mesElegido;
    const fecha = new Date(String(registro.fecha));
    if (Number.isNaN(fecha.getTime())) return true;
    if (props.periodo === 'mes') {
        const ahora = new Date();
        return fecha.getFullYear() === ahora.getFullYear() && fecha.getMonth() === ahora.getMonth();
    }
    if (props.periodo === 'todo') return true;
    return fecha >= desde(Number(props.periodo));
};

const recibidoDelPeriodo = computed(() => (props.produccion ?? []).filter(recibidoEnPeriodo));

const serieMensual = computed(() => {
    const acumulado = new Map<string, { piezas: number; valor: number }>();
    for (const merma of props.mermas ?? []) {
        const mes = String(merma.mes ?? '');
        if (!/^\d{4}-\d{2}$/.test(mes)) continue;
        const actual = acumulado.get(mes) ?? { piezas: 0, valor: 0 };
        actual.piezas += Number(merma.cantidad) || 0;
        actual.valor += Number(merma.costo_total_perdida) || 0;
        acumulado.set(mes, actual);
    }
    const hoy = new Date();
    return Array.from({ length: 12 }, (_, indice) => {
        const fecha2 = new Date(hoy.getFullYear(), hoy.getMonth() - (11 - indice), 1);
        const mes = claveMes(fecha2);
        const datos = acumulado.get(mes) ?? { piezas: 0, valor: 0 };
        return {
            mes,
            etiqueta: `${MESES_CORTOS[fecha2.getMonth()]} ${String(fecha2.getFullYear()).slice(2)}`,
            nombre: fecha2.toLocaleDateString('es-CO', { month: 'long', year: 'numeric' }),
            piezas: datos.piezas,
            valor: datos.valor,
            actual: indice === 11,
        };
    });
});

const haySerieMensual = computed(() => serieMensual.value.some((punto) => punto.piezas > 0 || punto.valor > 0));

const valoresSerie = computed(() =>
    serieMensual.value.map((punto) => (props.metrica === 'valor' ? punto.valor : punto.piezas)),
);

const formatear = (valor: number) => (props.metrica === 'valor' ? props.dinero(valor) : props.piezas(valor));

const abreviatura = (valor: number): string => {
    const absoluto = Math.abs(valor);
    if (absoluto >= 1_000_000) return `${(valor / 1_000_000).toFixed(1).replace('.', ',')} M`;
    if (absoluto >= 1_000) return `${Math.round(valor / 1_000)} k`;
    return String(Math.round(valor));
};

const estadisticas = computed(() => {
    const valores = valoresSerie.value;
    const total = valores.length;
    const suma = valores.reduce((acumulado, valor) => acumulado + valor, 0);
    const media = total > 0 ? suma / total : 0;

    let sumaX = 0;
    let sumaY = 0;
    let sumaXY = 0;
    let sumaXX = 0;

    valores.forEach((valor, indice) => {
        sumaX += indice;
        sumaY += valor;
        sumaXY += indice * valor;
        sumaXX += indice * indice;
    });

    const denominador = total * sumaXX - sumaX * sumaX;
    const pendiente = denominador !== 0 ? (total * sumaXY - sumaX * sumaY) / denominador : 0;
    const intercepto = total > 0 ? (sumaY - pendiente * sumaX) / total : 0;
    const prediccion = valores.map((_, indice) => intercepto + pendiente * indice);
    const sumaResidual = valores.reduce((acumulado, valor, indice) => acumulado + (valor - prediccion[indice]) ** 2, 0);
    const sumaTotal = valores.reduce((acumulado, valor) => acumulado + (valor - media) ** 2, 0);
    const r2 = sumaTotal > 0 ? 1 - sumaResidual / sumaTotal : null;
    const maximo = total > 0 ? Math.max(...valores) : 0;
    const indiceMaximo = valores.indexOf(maximo);
    const actual = valores[valores.length - 1] ?? 0;
    const desviacion = media > 0 ? ((actual - media) / media) * 100 : 0;
    const proyeccion = Math.max(0, intercepto + pendiente * total);

    return {
        media,
        pendiente,
        intercepto,
        r2,
        maximo,
        maxMes: serieMensual.value[indiceMaximo]?.nombre ?? '—',
        actual,
        desviacion,
        proyeccion,
        total,
    };
});

const textoAjuste = computed(() => {
    const r2 = estadisticas.value.r2;
    if (r2 === null) return 'Sin variación entre meses';
    if (r2 >= 0.7) return 'La tendencia explica bien los datos';
    if (r2 >= 0.4) return 'Ajuste moderado';
    return 'Tendencia poco marcada';
});

const textoTendencia = computed(() => {
    const pendiente = estadisticas.value.pendiente;
    if (Math.abs(pendiente) < 0.005) return 'Sin cambios apreciables por mes';
    return pendiente > 0 ? 'Las pérdidas crecen por mes' : 'Las pérdidas bajan por mes';
});

const textoActual = computed(() => {
    const est = estadisticas.value;
    if (est.media <= 0) return 'sin base para comparar';
    const signo = est.desviacion > 0 ? '+' : '';
    return `${signo}${est.desviacion.toFixed(0)}% vs. media`;
});

const formatearMetrica = (valor: number): string =>
    props.metrica === 'valor' ? props.dinero(valor) : props.piezas(valor);

const formatearConSigno = (valor: number): string => {
    const signo = valor > 0 ? '+' : valor < 0 ? '−' : '';
    return `${signo}${formatearMetrica(Math.abs(valor))}`;
};

const opcionesGrafica = computed(() => {
    const puntos = serieMensual.value;
    const valores = valoresSerie.value;
    const est = estadisticas.value;
    const regresion = valores.map((_, indice) => est.intercepto + est.pendiente * indice);
    return {
        tooltip: {
            trigger: 'axis',
            backgroundColor: props.esOscuro ? '#1e293b' : '#ffffff',
            borderColor: props.esOscuro ? 'rgba(148,163,184,.25)' : '#e2e8f0',
            textStyle: { color: props.esOscuro ? '#f1f5f9' : '#0f172a', fontSize: 12 },
            axisPointer: { type: 'shadow' },
            formatter: (params: any) => {
                const fila = Array.isArray(params) ? params[0] : params;
                const indice = fila?.dataIndex ?? 0;
                const punto = puntos[indice];
                if (!punto) return '';
                const valor = valores[indice] ?? 0;
                const diferencia = valor - est.media;
                const signo = diferencia > 0 ? '+' : diferencia < 0 ? '−' : '';
                const comparacion = diferencia === 0
                    ? 'Igual a la media'
                    : `${signo}${formatear(Math.abs(diferencia))} vs. media`;
                return [
                    `<div style="font-weight:600;margin-bottom:4px;text-transform:capitalize">${punto.nombre}</div>`,
                    `<div>${props.metrica === 'valor' ? 'Valor perdido' : 'Pares perdidos'}: <b>${formatear(valor)}</b></div>`,
                    `<div>Media: ${formatear(est.media)}</div>`,
                    `<div>Tendencia: ${formatearConSigno(regresion[indice] ?? 0)}</div>`,
                    `<div style="opacity:.8">${comparacion}</div>`,
                ].join('');
            },
        },
        legend: {
            top: 0,
            textStyle: { color: props.esOscuro ? '#cbd5e1' : '#475569', fontSize: 12 },
            icon: 'roundRect',
            itemWidth: 14,
            itemHeight: 8,
        },
        grid: { left: 8, right: 12, bottom: 0, top: 34, containLabel: true },
        xAxis: {
            type: 'category',
            data: puntos.map((punto) => punto.etiqueta),
            axisLine: { lineStyle: { color: props.esOscuro ? 'rgba(148,163,184,.14)' : 'rgba(100,116,139,.14)' } },
            axisTick: { show: false },
            axisLabel: { color: props.esOscuro ? '#cbd5e1' : '#475569', fontSize: 11 },
            splitLine: { show: false },
        },
        yAxis: {
            type: 'value',
            axisLine: { lineStyle: { color: props.esOscuro ? 'rgba(148,163,184,.14)' : 'rgba(100,116,139,.14)' } },
            axisTick: { show: false },
            axisLabel: {
                color: props.esOscuro ? '#cbd5e1' : '#475569',
                fontSize: 11,
                formatter: (valor: number) => (props.metrica === 'valor' ? `$${abreviatura(valor)}` : abreviatura(valor)),
            },
            splitLine: { lineStyle: { color: props.esOscuro ? 'rgba(148,163,184,.14)' : 'rgba(100,116,139,.14)' } },
        },
        series: [
            {
                name: props.metrica === 'valor' ? 'Valor perdido' : 'Pares perdidos',
                type: 'bar',
                barMaxWidth: 28,
                data: valores.map((valor, indice) => ({
                    value: valor,
                    itemStyle: {
                        color: indice === valores.length - 1 ? '#dc2626' : 'rgba(239,68,68,.45)',
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

const segmentoClass = (activo: boolean) => [
    'whitespace-nowrap px-3 py-2 text-xs font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring',
    activo ? 'bg-foreground text-background shadow-sm' : 'text-muted-foreground hover:bg-muted hover:text-foreground',
];

const recibidoPorMes = computed(() => {
    const grupos = new Map<string, { mes: string; aprobadas: number; perdidas: number }>();
    for (const registro of recibidoDelPeriodo.value) {
        const mes = String(registro.mes ?? '—');
        const grupo = grupos.get(mes) ?? { mes, aprobadas: 0, perdidas: 0 };
        grupo.aprobadas += Number(registro.aprobada) || 0;
        grupo.perdidas += Number(registro.no_recuperable) || 0;
        grupos.set(mes, grupo);
    }
    return Array.from(grupos.values()).sort((a, b) => a.mes.localeCompare(b.mes));
});

const totalRecibido = computed(() => {
    const aprobadas = recibidoPorMes.value.reduce((suma, punto) => suma + punto.aprobadas, 0);
    const perdidas = recibidoPorMes.value.reduce((suma, punto) => suma + punto.perdidas, 0);
    const recibidas = aprobadas + perdidas;
    return { aprobadas, perdidas, recibidas, porcentaje: recibidas > 0 ? (perdidas / recibidas) * 100 : 0 };
});

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

const etiquetaMesCorto = (mes: string): string => {
    if (!mes || mes === '—') return 'Sin fecha';
    const [anio, numero] = mes.split('-');
    const nombre = MESES_CORTOS[Number(numero) - 1];
    return nombre ? `${nombre} ${anio.slice(2)}` : mes;
};

const etiqueta = (valor: number): string => (Number(valor) > 0 ? props.piezas(valor) : '');

const opcionesProducido = computed(() => {
    const puntos = recibidoPorMes.value;
    return {
        tooltip: {
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
            backgroundColor: props.esOscuro ? '#1e293b' : '#ffffff',
            borderColor: props.esOscuro ? 'rgba(148,163,184,.25)' : '#e2e8f0',
            textStyle: { color: props.esOscuro ? '#f1f5f9' : '#0f172a', fontSize: 12 },
            formatter: (params: any) => {
                const fila = Array.isArray(params) ? params[0] : params;
                const punto = puntos[fila?.dataIndex ?? 0];
                if (!punto) return '';
                const recibidas = punto.aprobadas + punto.perdidas;
                const perdida = recibidas > 0 ? ((punto.perdidas / recibidas) * 100).toFixed(1).replace('.', ',') : '0,0';
                return [
                    `<div style="font-weight:600;margin-bottom:4px">${etiquetaMesCorto(punto.mes)}</div>`,
                    `<div>Aprobados: <b>${props.piezas(punto.aprobadas)}</b></div>`,
                    `<div>No recuperables: <b>${props.piezas(punto.perdidas)}</b></div>`,
                    `<div style="opacity:.8">Pérdida: ${perdida}% de lo recibido</div>`,
                ].join('');
            },
        },
        legend: {
            top: 0,
            textStyle: { color: props.esOscuro ? '#cbd5e1' : '#475569', fontSize: 12 },
            icon: 'roundRect',
            itemWidth: 14,
            itemHeight: 8,
        },
        grid: { left: 8, right: 12, bottom: 0, top: 34, containLabel: true },
        xAxis: {
            type: 'category',
            data: puntos.map((punto) => etiquetaMesCorto(punto.mes)),
            axisLine: { lineStyle: { color: props.esOscuro ? 'rgba(148,163,184,.14)' : 'rgba(100,116,139,.14)' } },
            axisTick: { show: false },
            axisLabel: { color: props.esOscuro ? '#cbd5e1' : '#475569', fontSize: 11 },
            splitLine: { show: false },
        },
        yAxis: {
            type: 'value',
            axisLine: { lineStyle: { color: props.esOscuro ? 'rgba(148,163,184,.14)' : 'rgba(100,116,139,.14)' } },
            axisTick: { show: false },
            axisLabel: { color: props.esOscuro ? '#cbd5e1' : '#475569', fontSize: 11, formatter: (valor: number) => abreviatura(valor) },
            splitLine: { lineStyle: { color: props.esOscuro ? 'rgba(148,163,184,.14)' : 'rgba(100,116,139,.14)' } },
        },
        series: [
            {
                name: 'Aprobados',
                type: 'bar',
                barMaxWidth: 26,
                data: puntos.map((punto) => punto.aprobadas),
                itemStyle: { color: '#10b981', borderRadius: [6, 6, 0, 0] },
                label: {
                    show: true,
                    position: 'top',
                    color: props.esOscuro ? '#cbd5e1' : '#475569',
                    fontSize: 10,
                    formatter: (params: any) => etiqueta(params.value),
                },
            },
            {
                name: 'No recuperables',
                type: 'bar',
                barMaxWidth: 26,
                data: puntos.map((punto) => punto.perdidas),
                itemStyle: { color: '#ef4444', borderRadius: [6, 6, 0, 0] },
                label: {
                    show: true,
                    position: 'top',
                    color: props.esOscuro ? '#fca5a5' : '#b91c1c',
                    fontSize: 10,
                    formatter: (params: any) => etiqueta(params.value),
                },
            },
        ],
    };
});
</script>

<template>
    <div class="grid gap-4 xl:grid-cols-2">
        <div
            v-if="recibidoPorMes.length"
            :class="['overflow-hidden rounded-xl border border-border bg-card shadow-sm', !haySerieMensual ? 'xl:col-span-2' : '']"
        >
            <div class="border-b border-border px-4 py-3">
                <h2 class="flex items-center gap-2 text-sm font-semibold text-foreground">
                    <ChartBarIcon class="h-4 w-4 text-emerald-500" />
                    Producido vs perdido
                </h2>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    Pares aprobados y no recuperables de las recepciones de calidad en el periodo. Los pares en
                    reparación no cuentan en ninguna barra.
                </p>
            </div>
            <div class="px-2 pt-3 sm:px-4">
                <div class="h-72 w-full">
                    <VChart :option="opcionesProducido" autoresize class="h-full w-full" />
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 border-t border-border bg-muted/30 px-4 py-2.5 text-[11px] text-muted-foreground">
                <span><span class="font-medium text-foreground">Aprobados:</span> {{ props.piezas(totalRecibido.aprobadas) }}</span>
                <span class="text-border">·</span>
                <span><span class="font-medium text-foreground">No recuperables:</span> {{ props.piezas(totalRecibido.perdidas) }}</span>
                <span class="text-border">·</span>
                <span>
                    <span class="font-medium text-foreground">Pérdida:</span>
                    {{ totalRecibido.porcentaje > 0 ? `${totalRecibido.porcentaje.toFixed(1).replace('.', ',')}%` : '—' }}
                    de lo recibido ({{ props.piezas(totalRecibido.recibidas) }} pares)
                </span>
            </div>
        </div>

        <div
            v-if="haySerieMensual"
            :class="['overflow-hidden rounded-xl border border-border bg-card shadow-sm', !recibidoPorMes.length ? 'xl:col-span-2' : '']"
        >
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-border px-4 py-3">
                <div class="min-w-0">
                    <h2 class="flex items-center gap-2 text-sm font-semibold text-foreground">
                        <ChartBarIcon class="h-4 w-4 text-red-500" />
                        Tendencia de pérdidas mensuales
                    </h2>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        Últimos 12 meses · el mes actual se resalta en rojo; la línea punteada es la media y la continua, la regresión lineal.
                    </p>
                </div>
                <div class="inline-flex items-stretch divide-x divide-border overflow-hidden rounded-md border border-border bg-card" role="tablist" aria-label="Métrica de la gráfica de pérdidas">
                    <button
                        type="button"
                        role="tab"
                        :aria-selected="props.metrica === 'piezas'"
                        :class="segmentoClass(props.metrica === 'piezas')"
                        @click="emit('cambiar-metrica', 'piezas')"
                    >
                        Pares
                    </button>
                    <button
                        type="button"
                        role="tab"
                        :aria-selected="props.metrica === 'valor'"
                        :class="segmentoClass(props.metrica === 'valor')"
                        @click="emit('cambiar-metrica', 'valor')"
                    >
                        Valor
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
                    <p class="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">Media mensual</p>
                    <p class="mt-1 text-lg font-semibold tabular-nums text-foreground">{{ formatearMetrica(estadisticas.media) }}</p>
                    <p class="mt-0.5 text-[11px] text-muted-foreground">{{ props.metrica === 'valor' ? 'valor perdido por mes' : 'pares perdidos por mes' }}</p>
                </div>
                <div>
                    <p class="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">Tendencia</p>
                    <p
                        class="mt-1 flex items-center gap-1 text-lg font-semibold tabular-nums"
                        :class="
                            estadisticas.pendiente > 0.005
                                ? 'text-red-600 dark:text-red-400'
                                : estadisticas.pendiente < -0.005
                                  ? 'text-emerald-600 dark:text-emerald-400'
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
                <div>
                    <p class="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">Calidad del ajuste (R²)</p>
                    <p class="mt-1 text-lg font-semibold tabular-nums text-foreground">
                        {{ estadisticas.r2 === null ? '—' : `${Math.round(estadisticas.r2 * 100)}%` }}
                    </p>
                    <p class="mt-0.5 text-[11px] text-muted-foreground">{{ textoAjuste }}</p>
                </div>
                <div>
                    <p class="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">Proyección próximo mes</p>
                    <p class="mt-1 text-lg font-semibold tabular-nums text-foreground">{{ formatearMetrica(estadisticas.proyeccion) }}</p>
                    <p class="mt-0.5 text-[11px] text-muted-foreground">según la regresión lineal</p>
                </div>
            </div>
            <div class="border-t border-border bg-muted/30 px-4 py-2.5 text-[11px] text-muted-foreground">
                <span class="font-medium text-foreground">Pico:</span> {{ formatearMetrica(estadisticas.maximo) }} en {{ estadisticas.maxMes }}
                <span class="mx-1.5 text-border">·</span>
                <span class="font-medium text-foreground">Mes actual:</span> {{ formatearMetrica(estadisticas.actual) }} ({{ textoActual }})
            </div>
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
                <tr v-if="porMes.length > 0" class="cursor-pointer border-b border-border/60 transition-colors hover:bg-muted/40" :class="props.mesElegido === porMes[0].mes ? 'bg-muted/60' : ''" @click="props.elegirMes(porMes[0].mes)">
                    <td class="px-3 py-2.5 font-medium text-foreground first-letter:uppercase">{{ props.nombreMes(porMes[0].mes) }}</td>
                    <td class="px-3 py-2.5 text-right tabular-nums text-foreground">{{ props.piezas(porMes[0].piezas) }}</td>
                    <td class="px-3 py-2.5 text-right tabular-nums font-semibold text-foreground">{{ props.dinero(porMes[0].valor) }}</td>
                    <td class="px-3 py-2.5 text-center tabular-nums text-muted-foreground">{{ porMes[0].ops }}</td>
                </tr>
                <tr v-if="porMes.length > 1" class="cursor-pointer border-b border-border/60 transition-colors hover:bg-muted/40" :class="props.mesElegido === porMes[1].mes ? 'bg-muted/60' : ''" @click="props.elegirMes(porMes[1].mes)">
                    <td class="px-3 py-2.5 font-medium text-foreground first-letter:uppercase">{{ props.nombreMes(porMes[1].mes) }}</td>
                    <td class="px-3 py-2.5 text-right tabular-nums text-foreground">{{ props.piezas(porMes[1].piezas) }}</td>
                    <td class="px-3 py-2.5 text-right tabular-nums font-semibold text-foreground">{{ props.dinero(porMes[1].valor) }}</td>
                    <td class="px-3 py-2.5 text-center tabular-nums text-muted-foreground">{{ porMes[1].ops }}</td>
                </tr>
                <tr v-if="porMes.length > 2" class="cursor-pointer border-b border-border/60 transition-colors hover:bg-muted/40" :class="props.mesElegido === porMes[2].mes ? 'bg-muted/60' : ''" @click="props.elegirMes(porMes[2].mes)">
                    <td class="px-3 py-2.5 font-medium text-foreground first-letter:uppercase">{{ props.nombreMes(porMes[2].mes) }}</td>
                    <td class="px-3 py-2.5 text-right tabular-nums text-foreground">{{ props.piezas(porMes[2].piezas) }}</td>
                    <td class="px-3 py-2.5 text-right tabular-nums font-semibold text-foreground">{{ props.dinero(porMes[2].valor) }}</td>
                    <td class="px-3 py-2.5 text-center tabular-nums text-muted-foreground">{{ porMes[2].ops }}</td>
                </tr>
                <tr v-if="porMes.length > 3" class="cursor-pointer border-b border-border/60 transition-colors hover:bg-muted/40" :class="props.mesElegido === porMes[3].mes ? 'bg-muted/60' : ''" @click="props.elegirMes(porMes[3].mes)">
                    <td class="px-3 py-2.5 font-medium text-foreground first-letter:uppercase">{{ props.nombreMes(porMes[3].mes) }}</td>
                    <td class="px-3 py-2.5 text-right tabular-nums text-foreground">{{ props.piezas(porMes[3].piezas) }}</td>
                    <td class="px-3 py-2.5 text-right tabular-nums font-semibold text-foreground">{{ props.dinero(porMes[3].valor) }}</td>
                    <td class="px-3 py-2.5 text-center tabular-nums text-muted-foreground">{{ porMes[3].ops }}</td>
                </tr>
                <tr v-if="porMes.length > 4" class="cursor-pointer border-b border-border/60 transition-colors hover:bg-muted/40" :class="props.mesElegido === porMes[4].mes ? 'bg-muted/60' : ''" @click="props.elegirMes(porMes[4].mes)">
                    <td class="px-3 py-2.5 font-medium text-foreground first-letter:uppercase">{{ props.nombreMes(porMes[4].mes) }}</td>
                    <td class="px-3 py-2.5 text-right tabular-nums text-foreground">{{ props.piezas(porMes[4].piezas) }}</td>
                    <td class="px-3 py-2.5 text-right tabular-nums font-semibold text-foreground">{{ props.dinero(porMes[4].valor) }}</td>
                    <td class="px-3 py-2.5 text-center tabular-nums text-muted-foreground">{{ porMes[4].ops }}</td>
                </tr>
                <tr v-if="porMes.length > 5" class="cursor-pointer border-b border-border/60 transition-colors hover:bg-muted/40" :class="props.mesElegido === porMes[5].mes ? 'bg-muted/60' : ''" @click="props.elegirMes(porMes[5].mes)">
                    <td class="px-3 py-2.5 font-medium text-foreground first-letter:uppercase">{{ props.nombreMes(porMes[5].mes) }}</td>
                    <td class="px-3 py-2.5 text-right tabular-nums text-foreground">{{ props.piezas(porMes[5].piezas) }}</td>
                    <td class="px-3 py-2.5 text-right tabular-nums font-semibold text-foreground">{{ props.dinero(porMes[5].valor) }}</td>
                    <td class="px-3 py-2.5 text-center tabular-nums text-muted-foreground">{{ porMes[5].ops }}</td>
                </tr>
                <tr v-if="porMes.length > 6" class="cursor-pointer border-b border-border/60 transition-colors hover:bg-muted/40" :class="props.mesElegido === porMes[6].mes ? 'bg-muted/60' : ''" @click="props.elegirMes(porMes[6].mes)">
                    <td class="px-3 py-2.5 font-medium text-foreground first-letter:uppercase">{{ props.nombreMes(porMes[6].mes) }}</td>
                    <td class="px-3 py-2.5 text-right tabular-nums text-foreground">{{ props.piezas(porMes[6].piezas) }}</td>
                    <td class="px-3 py-2.5 text-right tabular-nums font-semibold text-foreground">{{ props.dinero(porMes[6].valor) }}</td>
                    <td class="px-3 py-2.5 text-center tabular-nums text-muted-foreground">{{ porMes[6].ops }}</td>
                </tr>
                <tr v-if="porMes.length > 7" class="cursor-pointer border-b border-border/60 transition-colors hover:bg-muted/40" :class="props.mesElegido === porMes[7].mes ? 'bg-muted/60' : ''" @click="props.elegirMes(porMes[7].mes)">
                    <td class="px-3 py-2.5 font-medium text-foreground first-letter:uppercase">{{ props.nombreMes(porMes[7].mes) }}</td>
                    <td class="px-3 py-2.5 text-right tabular-nums text-foreground">{{ props.piezas(porMes[7].piezas) }}</td>
                    <td class="px-3 py-2.5 text-right tabular-nums font-semibold text-foreground">{{ props.dinero(porMes[7].valor) }}</td>
                    <td class="px-3 py-2.5 text-center tabular-nums text-muted-foreground">{{ porMes[7].ops }}</td>
                </tr>
                <tr v-if="porMes.length > 8" class="cursor-pointer border-b border-border/60 transition-colors hover:bg-muted/40" :class="props.mesElegido === porMes[8].mes ? 'bg-muted/60' : ''" @click="props.elegirMes(porMes[8].mes)">
                    <td class="px-3 py-2.5 font-medium text-foreground first-letter:uppercase">{{ props.nombreMes(porMes[8].mes) }}</td>
                    <td class="px-3 py-2.5 text-right tabular-nums text-foreground">{{ props.piezas(porMes[8].piezas) }}</td>
                    <td class="px-3 py-2.5 text-right tabular-nums font-semibold text-foreground">{{ props.dinero(porMes[8].valor) }}</td>
                    <td class="px-3 py-2.5 text-center tabular-nums text-muted-foreground">{{ porMes[8].ops }}</td>
                </tr>
                <tr v-if="porMes.length > 9" class="cursor-pointer border-b border-border/60 transition-colors hover:bg-muted/40" :class="props.mesElegido === porMes[9].mes ? 'bg-muted/60' : ''" @click="props.elegirMes(porMes[9].mes)">
                    <td class="px-3 py-2.5 font-medium text-foreground first-letter:uppercase">{{ props.nombreMes(porMes[9].mes) }}</td>
                    <td class="px-3 py-2.5 text-right tabular-nums text-foreground">{{ props.piezas(porMes[9].piezas) }}</td>
                    <td class="px-3 py-2.5 text-right tabular-nums font-semibold text-foreground">{{ props.dinero(porMes[9].valor) }}</td>
                    <td class="px-3 py-2.5 text-center tabular-nums text-muted-foreground">{{ porMes[9].ops }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<style scoped>
/* GraficasMermas no requiere estilos propios adicionales por ahora */
</style>
