<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import {
    AlertCircle,
    ArrowDownToLine,
    Boxes,
    CalendarDays,
    Coins,
    PackageCheck,
    RefreshCw,
    Scale,
    TrendingDown,
    Wrench,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { use } from 'echarts/core';
import { BarChart, LineChart, PieChart } from 'echarts/charts';
import {
    GridComponent,
    LegendComponent,
    TooltipComponent,
} from 'echarts/components';
import { LegacyGridContainLabel } from 'echarts/features';
import { CanvasRenderer } from 'echarts/renderers';
import VChart from 'vue-echarts';

use([BarChart, LineChart, PieChart, GridComponent, LegendComponent, TooltipComponent, LegacyGridContainLabel, CanvasRenderer]);

interface NotificacionItem {
    id: number;
    mensaje: string;
    prioridad: string;
    created_at: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
];

const dias = ref(30);
const opcionesPeriodo = [
    { dias: 7, etiqueta: '7 días' },
    { dias: 30, etiqueta: '30 días' },
    { dias: 90, etiqueta: '90 días' },
    { dias: 365, etiqueta: '1 año' },
];

const datos = ref<any>(null);
const notificacionesRojas = ref<NotificacionItem[]>([]);
const cargando = ref(true);
const actualizado = ref('');

const esOscuro = ref(false);
let observadorTema: MutationObserver | null = null;
let timer: any = null;

const formatoPlata = (v: number) => '$' + Math.round(v || 0).toLocaleString('es-CO');
const formatoNumero = (v: number) => (v || 0).toLocaleString('es-CO');

const colorTexto = computed(() => (esOscuro.value ? '#cbd5e1' : '#475569'));
const colorLinea = computed(() => (esOscuro.value ? 'rgba(148,163,184,.14)' : 'rgba(100,116,139,.14)'));
const fondoTooltip = computed(() => (esOscuro.value ? '#1e293b' : '#ffffff'));
const bordeTooltip = computed(() => (esOscuro.value ? 'rgba(148,163,184,.25)' : '#e2e8f0'));

const baseEje = computed(() => ({
    axisLine: { lineStyle: { color: colorLinea.value } },
    axisTick: { show: false },
    axisLabel: { color: colorTexto.value, fontSize: 11 },
    splitLine: { lineStyle: { color: colorLinea.value } },
}));

const baseTooltip = computed(() => ({
    trigger: 'axis',
    backgroundColor: fondoTooltip.value,
    borderColor: bordeTooltip.value,
    textStyle: { color: esOscuro.value ? '#f1f5f9' : '#0f172a', fontSize: 12 },
    axisPointer: { type: 'shadow' as const, shadowStyle: { color: esOscuro.value ? 'rgba(148,163,184,.08)' : 'rgba(100,116,139,.08)' } },
}));

const baseLegenda = computed(() => ({
    textStyle: { color: colorTexto.value, fontSize: 12 },
    icon: 'roundRect',
    itemWidth: 14,
    itemHeight: 8,
}));

const degradado = (de: string, a: string, horizontal = false) => ({
    color: {
        type: 'linear',
        x: 0, y: 0, x2: horizontal ? 1 : 0, y2: horizontal ? 0 : 1,
        colorStops: [{ offset: 0, color: de }, { offset: 1, color: a }],
    },
});

const etiquetaFecha = (v: string) => (v.length === 10 ? `${v.slice(8, 10)}/${v.slice(5, 7)}` : v);

const opcionesMovimientos = computed(() => {
    const m = datos.value?.movimientos;
    if (!m) return {};
    return {
        tooltip: baseTooltip.value,
        legend: { ...baseLegenda.value, top: 0 },
        grid: { left: 8, right: 8, bottom: 0, top: 34, containLabel: true },
        xAxis: { type: 'category', data: m.labels, ...baseEje.value, splitLine: { show: false }, axisLabel: { ...baseEje.value.axisLabel, formatter: etiquetaFecha } },
        yAxis: { type: 'value', ...baseEje.value },
        series: [
            {
                name: 'Entradas', type: 'bar', data: m.entradas, barMaxWidth: 18,
                itemStyle: { borderRadius: [6, 6, 0, 0], ...degradado('#34d399', '#059669') },
            },
            {
                name: 'Salidas', type: 'bar', data: m.salidas, barMaxWidth: 18,
                itemStyle: { borderRadius: [6, 6, 0, 0], ...degradado('#f87171', '#dc2626') },
            },
        ],
    };
});

const opcionesProduccion = computed(() => {
    const p = datos.value?.produccion;
    if (!p) return {};
    const serie = (nombre: string, data: number[], de: string, a: string) => ({
        name: nombre, type: 'line', stack: 'total', smooth: true, showSymbol: false,
        lineStyle: { width: 2, color: de },
        areaStyle: {
            color: {
                type: 'linear', x: 0, y: 0, x2: 0, y2: 1,
                colorStops: [{ offset: 0, color: de }, { offset: 1, color: a }],
            },
            opacity: 0.55,
        },
        emphasis: { focus: 'series' },
        data,
    });
    return {
        tooltip: baseTooltip.value,
        legend: { ...baseLegenda.value, top: 0 },
        grid: { left: 8, right: 8, bottom: 0, top: 34, containLabel: true },
        xAxis: { type: 'category', boundaryGap: false, data: p.labels, ...baseEje.value, splitLine: { show: false }, axisLabel: { ...baseEje.value.axisLabel, formatter: etiquetaFecha } },
        yAxis: { type: 'value', ...baseEje.value },
        series: [
            serie('Aprobadas', p.aprobadas, '#22c55e', 'rgba(34,197,94,.05)'),
            serie('En reparación', p.reparacion, '#f59e0b', 'rgba(245,158,11,.05)'),
            serie('No recuperables', p.no_recuperables, '#ef4444', 'rgba(239,68,68,.05)'),
        ],
    };
});

const PALETA = ['#6366f1', '#22c55e', '#f59e0b', '#ec4899', '#06b6d4', '#8b5cf6', '#f97316', '#14b8a6', '#e11d48', '#84cc16'];

const opcionesExistencias = computed(() => {
    const e = datos.value?.existencias;
    if (!e || e.length === 0) return {};
    return {
        tooltip: {
            trigger: 'item',
            backgroundColor: fondoTooltip.value,
            borderColor: bordeTooltip.value,
            textStyle: { color: esOscuro.value ? '#f1f5f9' : '#0f172a', fontSize: 12 },
            formatter: (p: any) => `${p.name}<br/>${formatoNumero(p.value)} unidades (${p.percent}%)`,
        },
        legend: { ...baseLegenda.value, orient: 'vertical', right: 4, top: 'middle', itemGap: 10 },
        series: [{
            name: 'Existencias',
            type: 'pie',
            radius: ['38%', '68%'],
            center: ['38%', '52%'],
            roseType: 'radius',
            itemStyle: { borderRadius: 8, borderColor: esOscuro.value ? '#0f172a' : '#ffffff', borderWidth: 2 },
            label: { show: false },
            emphasis: { scaleSize: 8 },
            color: PALETA,
            data: e.map((f: any) => ({ name: f.categoria, value: f.unidades })),
        }],
    };
});

const opcionesCompras = computed(() => {
    const c = datos.value?.compras;
    if (!c || c.labels.length === 0) return {};
    return {
        tooltip: { ...baseTooltip.value, formatter: (params: any[]) => {
            let texto = params[0].axisValue;
            params.forEach((p) => {
                const valor = p.seriesName === 'Precio promedio' ? formatoPlata(p.value) : formatoPlata(p.value);
                texto += `<br/>${p.marker}${p.seriesName}: ${valor}`;
            });
            return texto;
        } },
        legend: { ...baseLegenda.value, top: 0 },
        grid: { left: 8, right: 8, bottom: 0, top: 34, containLabel: true },
        xAxis: { type: 'category', data: c.labels, ...baseEje.value, splitLine: { show: false } },
        yAxis: [
            { type: 'value', name: 'Costo', ...baseEje.value, axisLabel: { ...baseEje.value.axisLabel, formatter: (v: number) => formatoPlata(v) } },
            { type: 'value', name: 'Precio prom.', ...baseEje.value, splitLine: { show: false }, axisLabel: { ...baseEje.value.axisLabel, formatter: (v: number) => formatoPlata(v) } },
        ],
        series: [
            {
                name: 'Costo de materiales en OPs', type: 'bar', data: c.costos, barMaxWidth: 34,
                itemStyle: { borderRadius: [8, 8, 0, 0], ...degradado('#818cf8', '#4f46e5') },
            },
            {
                name: 'Precio promedio de compras', type: 'line', yAxisIndex: 1, data: c.precio_promedio,
                smooth: true, symbolSize: 8,
                lineStyle: { width: 3, color: '#f59e0b' },
                itemStyle: { color: '#f59e0b', borderColor: esOscuro.value ? '#0f172a' : '#fff', borderWidth: 2 },
            },
        ],
    };
});

const opcionesDesviaciones = computed(() => {
    const d = datos.value?.desviaciones;
    if (!d || d.ops.length === 0) return {};
    return {
        tooltip: { ...baseTooltip.value, formatter: (params: any[]) => {
            const planeado = params.find((p) => p.seriesName === 'Costo planeado')?.value ?? 0;
            const real = params.find((p) => p.seriesName === 'Costo real')?.value ?? 0;
            const delta = real - planeado;
            return `${params[0].axisValue}<br/>Planeado: ${formatoPlata(planeado)}<br/>Real: ${formatoPlata(real)}<br/><b>Desviación: ${delta >= 0 ? '+' : ''}${formatoPlata(delta)}</b>`;
        } },
        legend: { ...baseLegenda.value, top: 0 },
        grid: { left: 8, right: 8, bottom: 0, top: 34, containLabel: true },
        xAxis: { type: 'category', data: d.ops, ...baseEje.value, splitLine: { show: false } },
        yAxis: { type: 'value', ...baseEje.value, axisLabel: { ...baseEje.value.axisLabel, formatter: (v: number) => formatoPlata(v) } },
        series: [
            {
                name: 'Costo planeado', type: 'bar', data: d.planeado, barMaxWidth: 20,
                itemStyle: { borderRadius: [6, 6, 0, 0], color: esOscuro.value ? 'rgba(148,163,184,.5)' : '#cbd5e1' },
            },
            {
                name: 'Costo real', type: 'bar', data: d.real, barMaxWidth: 20,
                itemStyle: { borderRadius: [6, 6, 0, 0], ...degradado('#38bdf8', '#0284c7') },
            },
        ],
    };
});

const mes = computed(() => datos.value?.mes ?? {});

const kpis = computed(() => [
    { icon: PackageCheck, etiqueta: 'Pares aprobados', valor: formatoNumero(mes.value.pares_aprobados), color: 'text-emerald-500', fondo: 'bg-emerald-500/10' },
    { icon: Boxes, etiqueta: 'Pares recibidos', valor: formatoNumero(mes.value.pares_recibidos), color: 'text-sky-500', fondo: 'bg-sky-500/10' },
    { icon: Wrench, etiqueta: 'En reparación', valor: formatoNumero(mes.value.en_reparacion), color: 'text-amber-500', fondo: 'bg-amber-500/10' },
    { icon: TrendingDown, etiqueta: 'No recuperables', valor: formatoNumero(mes.value.no_recuperables), color: 'text-red-500', fondo: 'bg-red-500/10' },
    { icon: Coins, etiqueta: 'Costo de materiales', valor: formatoPlata(mes.value.costo_materiales), color: 'text-indigo-500', fondo: 'bg-indigo-500/10' },
    { icon: Scale, etiqueta: 'Desviación de costos', valor: `${(mes.value.desviacion_costos ?? 0) >= 0 ? '+' : ''}${formatoPlata(mes.value.desviacion_costos)}`, color: (mes.value.desviacion_costos ?? 0) > 0 ? 'text-red-500' : 'text-emerald-500', fondo: 'bg-slate-500/10' },
    { icon: ArrowDownToLine, etiqueta: 'Entradas / salidas', valor: `${formatoNumero(mes.value.movimientos_entradas)} / ${formatoNumero(mes.value.movimientos_salidas)}`, color: 'text-teal-500', fondo: 'bg-teal-500/10' },
    { icon: CalendarDays, etiqueta: 'OPs activas (en Recibiendo)', valor: `${formatoNumero(mes.value.ops_activas)} (${formatoNumero(mes.value.ops_recibiendo)})`, color: 'text-violet-500', fondo: 'bg-violet-500/10' },
]);

const cargar = async () => {
    cargando.value = true;
    try {
        const [respDatos, respNotif] = await Promise.all([
            fetch(`/dashboard/datos?dias=${dias.value}`, { headers: { Accept: 'application/json' } }),
            fetch('/notificaciones', { headers: { Accept: 'application/json' } }),
        ]);
        if (respDatos.ok) {
            datos.value = await respDatos.json();
            actualizado.value = datos.value.generado_en ?? '';
        }
        if (respNotif.ok) {
            const data = await respNotif.json();
            notificacionesRojas.value = (data.notificaciones || []).filter((n: NotificacionItem) => n.prioridad === 'alta');
        }
    } catch {
        // Silencioso: el siguiente sondeo reintenta
    } finally {
        cargando.value = false;
    }
};

const cambiarPeriodo = (nuevosDias: number) => {
    dias.value = nuevosDias;
    cargar();
};

onMounted(() => {
    esOscuro.value = document.documentElement.classList.contains('dark');
    observadorTema = new MutationObserver(() => {
        esOscuro.value = document.documentElement.classList.contains('dark');
    });
    observadorTema.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

    cargar();
    timer = setInterval(cargar, 20000);
});

onUnmounted(() => {
    observadorTema?.disconnect();
    if (timer) clearInterval(timer);
});
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">

            <!-- Zona 1: notificaciones rojas (solo si existen) -->
            <div v-if="notificacionesRojas.length > 0"
                class="rounded-xl border-2 border-red-500/60 bg-red-500/5 p-4 dark:bg-red-500/10">
                <div class="mb-2 flex items-center gap-2">
                    <AlertCircle class="h-5 w-5 text-red-500" />
                    <span class="text-sm font-bold text-red-600 dark:text-red-400">
                        Notificaciones rojas ({{ notificacionesRojas.length }})
                    </span>
                </div>
                <ul class="space-y-1.5">
                    <li v-for="notif in notificacionesRojas" :key="notif.id"
                        class="flex items-start gap-2 text-sm text-foreground">
                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-red-500" />
                        <span class="font-medium leading-snug">{{ notif.mensaje }}</span>
                        <span class="ml-auto shrink-0 text-[11px] text-muted-foreground">
                            {{ new Date(notif.created_at).toLocaleDateString([], { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) }}
                        </span>
                    </li>
                </ul>
            </div>

            <!-- Zona 2: gráficos -->
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-lg font-semibold text-foreground">Reportes por período</h2>
                <div class="flex items-center gap-2">
                    <span v-if="actualizado" class="text-xs text-muted-foreground">
                        Actualizado {{ actualizado }} · se refresca cada 20 s
                    </span>
                    <Button variant="ghost" size="sm" class="h-8 w-8 p-0 cursor-pointer" title="Actualizar ahora"
                        @click="cargar">
                        <RefreshCw class="h-4 w-4" :class="cargando ? 'animate-spin' : ''" />
                    </Button>
                    <div class="flex rounded-lg border border-sidebar-border/60 p-0.5">
                        <button v-for="opcion in opcionesPeriodo" :key="opcion.dias" @click="cambiarPeriodo(opcion.dias)"
                            class="rounded-md px-3 py-1 text-xs font-medium transition-colors cursor-pointer"
                            :class="dias === opcion.dias
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:text-foreground'">
                            {{ opcion.etiqueta }}
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="!datos && cargando" class="grid min-h-[40vh] place-items-center text-sm text-muted-foreground">
                Cargando reportes…
            </div>

            <div v-else class="grid gap-4 md:grid-cols-2">
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-semibold">Movimientos de materiales (entradas vs salidas)</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <VChart :option="opcionesMovimientos" autoresize style="width: 100%; height: 18rem" />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-semibold">Producción recibida en calidad</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <VChart :option="opcionesProduccion" autoresize style="width: 100%; height: 18rem" />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-semibold">Existencias por categoría de material</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <VChart v-if="datos?.existencias?.length" :option="opcionesExistencias" autoresize style="width: 100%; height: 18rem" />
                        <div v-else class="grid h-72 place-items-center text-sm text-muted-foreground">Sin existencias registradas</div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-semibold">Costos y desviaciones por OP cerrada</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <VChart v-if="datos?.desviaciones?.ops?.length" :option="opcionesDesviaciones" autoresize style="width: 100%; height: 18rem" />
                        <div v-else class="grid h-72 place-items-center text-sm text-muted-foreground">Sin cierres con costo real en el período</div>
                    </CardContent>
                </Card>

                <Card class="md:col-span-2">
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-semibold">Compras: costo de materiales asignado y precio promedio mensual</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <VChart :option="opcionesCompras" autoresize style="width: 100%; height: 18rem" />
                    </CardContent>
                </Card>
            </div>

            <!-- Zona 3: resultados del mes -->
            <h2 class="text-lg font-semibold text-foreground">Resultados del mes</h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Card v-for="kpi in kpis" :key="kpi.etiqueta">
                    <CardContent class="flex items-center gap-3 p-4">
                        <div class="grid h-10 w-10 shrink-0 place-items-center rounded-xl" :class="kpi.fondo">
                            <component :is="kpi.icon" class="h-5 w-5" :class="kpi.color" />
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-xs text-muted-foreground">{{ kpi.etiqueta }}</p>
                            <p class="text-lg font-bold text-foreground">{{ kpi.valor }}</p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
