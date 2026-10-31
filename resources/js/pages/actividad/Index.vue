<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { use } from 'echarts/core';
import { BarChart } from 'echarts/charts';
import { GridComponent, LegendComponent, TooltipComponent } from 'echarts/components';
import { CanvasRenderer } from 'echarts/renderers';
import VChart from 'vue-echarts';
import { AlertTriangle, ChevronLeft, ChevronRight, LogIn, MousePointerClick, TriangleAlert } from 'lucide-vue-next';

use([BarChart, GridComponent, LegendComponent, TooltipComponent, CanvasRenderer]);

interface Paso {
    hora: string;
    etiqueta: string;
    ruta: string;
    metodo: string;
    status: number | null;
    duracion_ms: number | null;
    intervalo_seg: number | null;
    anormal: boolean;
}

interface UsuarioRitmo {
    id: number;
    nombre: string;
    acciones: number;
    conexiones: number;
    anomalias: number;
    promedio_min: number;
    mediana_min: number;
    umbral_min: number;
    max_min: number;
    ultima: string;
    ultima_accion: string;
}

const props = defineProps({
    breadcrumbs: Object as () => BreadcrumbItem[],
    title: String,
    filtro: Object as () => { dias: number; factor: number; umbral: number; usuario: number | null },
    opciones: Object as () => { dias: number[]; factor: number[]; umbral: number[] },
    resumen: Object as () => {
        acciones: number; usuarios: number; conexiones: number; escrituras: number;
        desde: string; hasta: string; picos: { hora: number; total: number }[]; generado_en: string;
    },
    porHora: Object as () => { horas: string[]; acciones: number[]; conexiones: number[] },
    usuarios: Array as () => UsuarioRitmo[],
    camino: Object as () => { usuario: string | null; umbral_min: number; pasos: Paso[]; ocultos: number },
    transiciones: Array as () => { de: string; a: string; veces: number; promedio_min: number; anormales: number }[],
    bitacora: Object as () => any,
});

const esOscuro = ref(false);
let observadorTema: MutationObserver | null = null;

const numero = (v: number | null | undefined) => (v ?? 0).toLocaleString('es-CO');

/** Tiempo entre acciones en lenguaje humano: es lo que se lee en el camino. */
const tiempoLegible = (seg: number | null): string => {
    if (seg === null) return '';
    if (seg < 60) return `${seg} s`;
    if (seg < 3600) return `${Math.floor(seg / 60)} min`;
    const horas = Math.floor(seg / 3600);
    const minutos = Math.round((seg % 3600) / 60);
    return minutos > 0 ? `${horas} h ${minutos} min` : `${horas} h`;
};

const duracionLegible = (ms: number | null): string => {
    if (ms === null) return '';
    return ms < 1000 ? `${ms} ms` : `${(ms / 1000).toFixed(2)} s`;
};

const filtrar = (cambios: Record<string, any>) => {
    const nuevos = { ...props.filtro, ...cambios } as Record<string, any>;
    Object.keys(nuevos).forEach((clave) => {
        if (nuevos[clave] === null || nuevos[clave] === '') delete nuevos[clave];
    });

    router.get(route('Actividad.index'), nuevos, { preserveScroll: true, preserveState: true });
};

const seleccionUsuario = computed(() => (props.filtro.usuario ? String(props.filtro.usuario) : ''));

const cambiarUsuario = (evento: Event) => {
    const valor = (evento.target as HTMLSelectElement).value;
    filtrar({ usuario: valor === '' ? null : Number(valor) });
};

const irPagina = (url: string | null) => {
    if (!url) return;
    router.visit(url, { preserveScroll: true, preserveState: true });
};

/** El camino se agrupa por día para que los saltos de un día a otro se vean. */
const pasosPorDia = computed(() => {
    const grupos: { dia: string; pasos: Paso[] }[] = [];

    for (const paso of props.camino.pasos) {
        const dia = paso.hora.slice(0, 5);
        const ultimo = grupos[grupos.length - 1];

        if (ultimo && ultimo.dia === dia) ultimo.pasos.push(paso);
        else grupos.push({ dia, pasos: [paso] });
    }

    return grupos;
});

const anomaliasCamino = computed(() => props.camino.pasos.filter((paso) => paso.anormal).length);

const anomaliasTotales = computed(() => props.usuarios.reduce((total, usuario) => total + usuario.anomalias, 0));

const picosTexto = computed(() =>
    props.resumen.picos.length
        ? props.resumen.picos.map((pico) => `${String(pico.hora).padStart(2, '0')}h`).join(' · ')
        : 'sin datos',
);

const colorTexto = computed(() => (esOscuro.value ? '#cbd5e1' : '#475569'));
const colorLinea = computed(() => (esOscuro.value ? 'rgba(148,163,184,.14)' : 'rgba(100,116,139,.14)'));

const opcionesHoras = computed(() => {
    const datos = props.porHora;
    if (!datos?.horas?.length) return {};

    return {
        tooltip: {
            trigger: 'axis',
            backgroundColor: esOscuro.value ? '#1e293b' : '#ffffff',
            borderColor: esOscuro.value ? 'rgba(148,163,184,.25)' : '#e2e8f0',
            textStyle: { color: esOscuro.value ? '#f1f5f9' : '#0f172a', fontSize: 12 },
        },
        legend: { top: 0, textStyle: { color: colorTexto.value, fontSize: 12 }, icon: 'roundRect', itemWidth: 14, itemHeight: 8 },
        grid: { left: 8, right: 8, bottom: 0, top: 34, containLabel: true },
        xAxis: {
            type: 'category',
            data: datos.horas,
            axisLine: { lineStyle: { color: colorLinea.value } },
            axisTick: { show: false },
            splitLine: { show: false },
            axisLabel: { color: colorTexto.value, fontSize: 10 },
        },
        yAxis: {
            type: 'value',
            axisLine: { lineStyle: { color: colorLinea.value } },
            axisTick: { show: false },
            axisLabel: { color: colorTexto.value, fontSize: 11 },
            splitLine: { lineStyle: { color: colorLinea.value } },
        },
        series: [
            {
                name: 'Acciones',
                type: 'bar',
                data: datos.acciones,
                barMaxWidth: 16,
                itemStyle: { borderRadius: [5, 5, 0, 0], color: '#10b981' },
            },
            {
                name: 'Conexiones',
                type: 'bar',
                data: datos.conexiones,
                barMaxWidth: 16,
                itemStyle: { borderRadius: [5, 5, 0, 0], color: '#3b82f6' },
            },
        ],
    };
});

onMounted(() => {
    esOscuro.value = document.documentElement.classList.contains('dark');
    observadorTema = new MutationObserver(() => {
        esOscuro.value = document.documentElement.classList.contains('dark');
    });
    observadorTema.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
});

onUnmounted(() => observadorTema?.disconnect());
</script>

<template>
    <Head :title="props.title ?? 'Actividad'" />

    <AppLayout :breadcrumbs="props.breadcrumbs ?? []">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">

            <!-- Encabezado: qué se está midiendo y con qué umbral -->
            <div class="flex flex-col gap-3 rounded-xl border border-sidebar-border/60 bg-card p-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-foreground">Actividad y tiempos entre acciones</h2>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        {{ props.resumen.desde }} → {{ props.resumen.hasta }} · una fila por petición autenticada ·
                        actualizado {{ props.resumen.generado_en }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <div class="flex rounded-lg border border-sidebar-border/60 p-0.5">
                        <button v-for="dia in props.opciones.dias" :key="dia" @click="filtrar({ dias: dia })"
                            class="cursor-pointer rounded-md px-3 py-1 text-xs font-medium transition-colors"
                            :class="props.filtro.dias === dia ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'">
                            {{ dia }} días
                        </button>
                    </div>
                    <label class="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                        Ritmo
                        <select :value="props.filtro.factor" @change="filtrar({ factor: Number(($event.target as HTMLSelectElement).value) })"
                            class="h-8 rounded-md border border-sidebar-border/60 bg-background px-2 text-xs text-foreground">
                            <option v-for="factor in props.opciones.factor" :key="factor" :value="factor">{{ factor }}×</option>
                        </select>
                    </label>
                    <label class="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                        Piso
                        <select :value="props.filtro.umbral" @change="filtrar({ umbral: Number(($event.target as HTMLSelectElement).value) })"
                            class="h-8 rounded-md border border-sidebar-border/60 bg-background px-2 text-xs text-foreground">
                            <option v-for="umbral in props.opciones.umbral" :key="umbral" :value="umbral">{{ umbral }} min</option>
                        </select>
                    </label>
                </div>
            </div>

            <!-- KPIs -->
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <Card>
                    <CardContent class="flex items-center gap-3 p-4">
                        <MousePointerClick class="h-5 w-5 text-emerald-600" />
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Acciones</p>
                            <p class="text-xl font-bold text-foreground">{{ numero(props.resumen.acciones) }}</p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="flex items-center gap-3 p-4">
                        <LogIn class="h-5 w-5 text-blue-600" />
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Conexiones</p>
                            <p class="text-xl font-bold text-foreground">{{ numero(props.resumen.conexiones) }}</p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="flex items-center gap-3 p-4">
                        <div class="grid h-5 w-5 place-items-center rounded bg-gray-950 text-[10px] font-bold text-white dark:bg-white dark:text-gray-950">
                            U
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Usuarios activos</p>
                            <p class="text-xl font-bold text-foreground">{{ numero(props.resumen.usuarios) }}</p>
                        </div>
                    </CardContent>
                </Card>
                <Card :class="anomaliasTotales ? 'border-red-500/50' : ''">
                    <CardContent class="flex items-center gap-3 p-4">
                        <AlertTriangle :class="anomaliasTotales ? 'text-red-500' : 'text-muted-foreground'" class="h-5 w-5" />
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Saltos anormales</p>
                            <p class="text-xl font-bold" :class="anomaliasTotales ? 'text-red-600 dark:text-red-400' : 'text-foreground'">
                                {{ numero(anomaliasTotales) }}
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div v-if="props.resumen.acciones === 0"
                class="rounded-xl border border-amber-500/40 bg-amber-500/5 p-4 text-sm text-foreground">
                Todavía no hay acciones registradas en el período. La bitácora empieza a llenarse con la navegación
                posterior a la instalación del registro: el archivo laravel.log no servía como fuente porque las líneas
                de admin y superadmin no guardaban la acción del controlador, ni el usuario, ni la ruta.
            </div>

            <!-- Horas + camino -->
            <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
                <Card class="xl:col-span-1">
                    <CardHeader>
                        <CardTitle class="text-sm">Actividad por hora del día</CardTitle>
                        <p class="text-xs text-muted-foreground">
                            Barras verdes: todas las acciones. Azules: solo inicios de sesión.
                            Más movimiento: {{ picosTexto }} · {{ numero(props.resumen.escrituras) }} acciones fueron escrituras.
                        </p>
                    </CardHeader>
                    <CardContent>
                        <VChart v-if="props.resumen.acciones > 0" :option="opcionesHoras" autoresize class="h-72 w-full" />
                        <p v-else class="py-10 text-center text-xs text-muted-foreground">Sin movimiento en el período</p>
                    </CardContent>
                </Card>

                <!-- El camino del usuario: los saltos anormales van en rojo -->
                <Card class="xl:col-span-2">
                    <CardHeader class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <CardTitle class="text-sm">
                                Camino de {{ props.camino.usuario ?? 'sin usuario' }}
                            </CardTitle>
                            <p class="text-xs text-muted-foreground">
                                Salto anormal cuando supera {{ props.camino.umbral_min }} min
                                ({{ props.filtro.factor }}× la mediana de este usuario, con piso de {{ props.filtro.umbral }} min).
                                <span :class="anomaliasCamino ? 'font-semibold text-red-600' : ''">
                                    {{ anomaliasCamino }} salto(s) anormal(es).
                                </span>
                            </p>
                        </div>
                        <select :value="seleccionUsuario" @change="cambiarUsuario"
                            class="h-8 rounded-md border border-sidebar-border/60 bg-background px-2 text-xs text-foreground">
                            <option value="">El más activo</option>
                            <option v-for="usuario in props.usuarios" :key="usuario.id" :value="usuario.id">
                                {{ usuario.nombre }}
                            </option>
                        </select>
                    </CardHeader>
                    <CardContent>
                        <p v-if="props.camino.ocultos > 0" class="mb-2 text-[11px] text-muted-foreground">
                            Se omiten {{ props.camino.ocultos }} pasos más antiguos de {{ props.camino.pasos.length }} mostrados.
                        </p>

                        <div class="max-h-[560px] space-y-3 overflow-y-auto pr-1">
                            <div v-for="grupo in pasosPorDia" :key="grupo.dia">
                                <p class="mb-1 font-mono text-[10px] uppercase tracking-widest text-muted-foreground">
                                    Día {{ grupo.dia }}
                                </p>

                                <div v-for="(paso, indice) in grupo.pasos" :key="paso.hora + indice">
                                    <!-- El intervalo vive entre pasos: así se ve el tiempo perdido -->
                                    <div v-if="paso.intervalo_seg !== null" class="flex items-center gap-2 py-0.5 pl-3">
                                        <span class="h-5 w-px" :class="paso.anormal ? 'bg-red-500' : 'bg-sidebar-border'" />
                                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                            :class="paso.anormal
                                                ? 'bg-red-500/15 text-red-600 ring-1 ring-red-500/40 dark:text-red-400'
                                                : 'bg-muted text-muted-foreground'">
                                            <TriangleAlert v-if="paso.anormal" class="h-3 w-3" />
                                            {{ tiempoLegible(paso.intervalo_seg) }}
                                        </span>
                                    </div>

                                    <div class="flex items-center gap-2 rounded-md border px-2.5 py-1.5"
                                        :class="paso.anormal
                                            ? 'border-red-500/50 bg-red-500/5'
                                            : 'border-sidebar-border/50 bg-background'">
                                        <span class="h-2.5 w-2.5 shrink-0 rounded-full"
                                            :class="[
                                                paso.anormal ? 'bg-red-500 ring-2 ring-red-500/30' : 'bg-emerald-500',
                                                ['POST', 'PUT', 'PATCH', 'DELETE'].includes(paso.metodo) && !paso.anormal ? 'bg-blue-500' : '',
                                            ]" />
                                        <span class="w-[92px] shrink-0 font-mono text-[11px] text-muted-foreground">
                                            {{ paso.hora.slice(6) }}
                                        </span>
                                        <span class="min-w-0 flex-1 truncate text-xs font-medium text-foreground">
                                            {{ paso.etiqueta }}
                                        </span>
                                        <span class="hidden shrink-0 font-mono text-[10px] text-muted-foreground lg:inline">
                                            /{{ paso.ruta }}
                                        </span>
                                        <span class="shrink-0 rounded border border-sidebar-border/60 px-1.5 py-0.5 font-mono text-[9px] uppercase text-muted-foreground">
                                            {{ paso.metodo }}
                                        </span>
                                        <span v-if="paso.status && paso.status >= 400"
                                            class="shrink-0 rounded bg-amber-500/15 px-1.5 py-0.5 font-mono text-[9px] text-amber-700">
                                            {{ paso.status }}
                                        </span>
                                        <span class="w-[52px] shrink-0 text-right font-mono text-[10px] text-muted-foreground">
                                            {{ duracionLegible(paso.duracion_ms) }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <p v-if="!props.camino.pasos.length" class="py-10 text-center text-xs text-muted-foreground">
                                Este usuario no tiene acciones en el período
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Ritmo por usuario -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-sm">Ritmo por usuario</CardTitle>
                    <p class="text-xs text-muted-foreground">
                        Promedio y mediana de minutos entre acción y acción. Haz clic en una fila para ver su camino.
                    </p>
                </CardHeader>
                <CardContent class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="border-b border-sidebar-border/60 text-left text-[10px] uppercase tracking-wider text-muted-foreground">
                                <th class="py-2 pr-3 font-semibold">Usuario</th>
                                <th class="py-2 pr-3 text-right font-semibold">Acciones</th>
                                <th class="py-2 pr-3 text-right font-semibold">Conexiones</th>
                                <th class="py-2 pr-3 text-right font-semibold">Promedio</th>
                                <th class="py-2 pr-3 text-right font-semibold">Mediana</th>
                                <th class="py-2 pr-3 text-right font-semibold">Umbral</th>
                                <th class="py-2 pr-3 text-right font-semibold">Máximo</th>
                                <th class="py-2 pr-3 text-right font-semibold">Anomalías</th>
                                <th class="py-2 pr-3 font-semibold">Última</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="usuario in props.usuarios" :key="usuario.id"
                                @click="filtrar({ usuario: usuario.id })"
                                class="cursor-pointer border-b border-sidebar-border/30 transition-colors hover:bg-muted/50"
                                :class="props.filtro.usuario === usuario.id ? 'bg-muted/70' : ''">
                                <td class="py-2 pr-3 font-medium text-foreground">{{ usuario.nombre }}</td>
                                <td class="py-2 pr-3 text-right font-mono text-foreground">{{ numero(usuario.acciones) }}</td>
                                <td class="py-2 pr-3 text-right font-mono text-muted-foreground">{{ numero(usuario.conexiones) }}</td>
                                <td class="py-2 pr-3 text-right font-mono text-foreground">{{ usuario.promedio_min }} min</td>
                                <td class="py-2 pr-3 text-right font-mono text-muted-foreground">{{ usuario.mediana_min }} min</td>
                                <td class="py-2 pr-3 text-right font-mono text-muted-foreground">{{ usuario.umbral_min }} min</td>
                                <td class="py-2 pr-3 text-right font-mono text-muted-foreground">{{ usuario.max_min }} min</td>
                                <td class="py-2 pr-3 text-right">
                                    <span class="rounded-full px-2 py-0.5 font-mono text-[11px] font-semibold"
                                        :class="usuario.anomalias ? 'bg-red-500/15 text-red-600 dark:text-red-400' : 'bg-muted text-muted-foreground'">
                                        {{ usuario.anomalias }}
                                    </span>
                                </td>
                                <td class="py-2 font-mono text-[11px] text-muted-foreground">
                                    {{ usuario.ultima }} · {{ usuario.ultima_accion }}
                                </td>
                            </tr>
                            <tr v-if="!props.usuarios.length">
                                <td colspan="9" class="py-8 text-center text-muted-foreground">Sin usuarios en el período</td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>

            <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
                <!-- Transiciones típicas -->
                <Card class="xl:col-span-1">
                    <CardHeader>
                        <CardTitle class="text-sm">Saltos más frecuentes</CardTitle>
                        <p class="text-xs text-muted-foreground">De una acción a la siguiente, con su tiempo promedio.</p>
                    </CardHeader>
                    <CardContent class="space-y-1.5">
                        <div v-for="par in props.transiciones" :key="par.de + par.a"
                            class="flex items-center justify-between gap-2 rounded-md border border-sidebar-border/40 px-2.5 py-1.5 text-[11px]">
                            <span class="min-w-0 truncate text-foreground">
                                {{ par.de }} <span class="text-muted-foreground">→</span> {{ par.a }}
                            </span>
                            <span class="shrink-0 font-mono text-muted-foreground">
                                {{ par.veces }}× · {{ par.promedio_min }} min
                                <span v-if="par.anormales" class="ml-1 rounded bg-red-500/15 px-1 font-semibold text-red-600 dark:text-red-400">
                                    {{ par.anormales }}
                                </span>
                            </span>
                        </div>
                        <p v-if="!props.transiciones.length" class="py-8 text-center text-xs text-muted-foreground">
                            Falta secuencia para calcular saltos
                        </p>
                    </CardContent>
                </Card>

                <!-- Bitácora cruda -->
                <Card class="xl:col-span-2">
                    <CardHeader class="flex flex-row items-start justify-between gap-3">
                        <div>
                            <CardTitle class="text-sm">Bitácora</CardTitle>
                            <p class="text-xs text-muted-foreground">
                                {{ numero(props.bitacora.total) }} filas · página {{ props.bitacora.current_page }} de {{ props.bitacora.last_page }}
                            </p>
                        </div>
                        <div class="flex gap-1">
                            <Button variant="ghost" size="icon" class="h-8 w-8" :disabled="!props.bitacora.prev_page_url"
                                @click="irPagina(props.bitacora.prev_page_url)">
                                <ChevronLeft class="h-4 w-4" />
                            </Button>
                            <Button variant="ghost" size="icon" class="h-8 w-8" :disabled="!props.bitacora.next_page_url"
                                @click="irPagina(props.bitacora.next_page_url)">
                                <ChevronRight class="h-4 w-4" />
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent class="max-h-[420px] overflow-auto">
                        <table class="w-full text-[11px]">
                            <thead class="sticky top-0 bg-card">
                                <tr class="border-b border-sidebar-border/60 text-left text-[10px] uppercase tracking-wider text-muted-foreground">
                                    <th class="py-2 pr-3 font-semibold">Cuándo</th>
                                    <th class="py-2 pr-3 font-semibold">Usuario</th>
                                    <th class="py-2 pr-3 font-semibold">Acción</th>
                                    <th class="py-2 pr-3 font-semibold">Ruta</th>
                                    <th class="py-2 pr-3 font-semibold">Método</th>
                                    <th class="py-2 pr-3 text-right font-semibold">ms</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="fila in props.bitacora.data" :key="fila.id" class="border-b border-sidebar-border/30">
                                    <td class="py-1.5 pr-3 font-mono text-muted-foreground">{{ fila.cuando }}</td>
                                    <td class="py-1.5 pr-3 truncate text-foreground">{{ fila.usuario }}</td>
                                    <td class="py-1.5 pr-3">
                                        <span :class="fila.esEscritura ? 'font-semibold text-blue-600 dark:text-blue-400' : 'text-foreground'">
                                            {{ fila.etiqueta }}
                                        </span>
                                    </td>
                                    <td class="py-1.5 pr-3 font-mono text-muted-foreground">/{{ fila.ruta }}</td>
                                    <td class="py-1.5 pr-3 font-mono text-muted-foreground">{{ fila.metodo }}</td>
                                    <td class="py-1.5 text-right font-mono text-muted-foreground">{{ fila.duracion_ms }}</td>
                                </tr>
                                <tr v-if="!props.bitacora.data?.length">
                                    <td colspan="6" class="py-8 text-center text-muted-foreground">Sin filas registradas</td>
                                </tr>
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
