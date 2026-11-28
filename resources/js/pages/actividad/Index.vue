<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import AccionesSuperadmin from '@/pages/actividad/AccionesSuperadmin.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { BarChart } from 'echarts/charts';
import { GridComponent, LegendComponent, TooltipComponent } from 'echarts/components';
import { use } from 'echarts/core';
import { CanvasRenderer } from 'echarts/renderers';
import {
    AlertTriangle,
    Ban,
    BookmarkCheck,
    ChevronLeft,
    ChevronRight,
    Grid3x3,
    LogIn,
    LogOut,
    MousePointerClick,
    SlidersHorizontal,
    TriangleAlert,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import VChart from 'vue-echarts';

use([BarChart, GridComponent, LegendComponent, TooltipComponent, CanvasRenderer]);

interface Paso {
    hora: string;
    etiqueta: string;
    ruta: string;
    metodo: string;
    tipo: 'negocio' | 'escritura' | 'navegacion' | 'marcador';
    entidad: string | null;
    detalle: string | null;
    otras: number;
    status: number | null;
    error: string | null;
    duracion_ms: number | null;
    ip: string | null;
    intervalo_seg: number | null;
    pausa: boolean;
    anormal: boolean;
}

interface UsuarioRitmo {
    id: number;
    nombre: string;
    acciones: number;
    pausas: number;
    negocio: number;
    fallidas: number;
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
    esAmbienteTest: Boolean,
    esAmbienteLocal: Boolean,
    estadosOp: Array as () => { id: number; nombre: string }[],
    materiales: Object as () => { total: number; sin_existencia: number; sin_precio: number; rango_existencia: string; rango_precio: string },
    filtro: Object as () => { dias: number; factor: number; umbral: number; foco: string; camino_usuario: number | null },
    bitacoraFiltro: Object as () => { desde: string; hasta: string; usuario: number | null; tipo: string; metodo: string },
    opciones: Object as () => {
        dias: number[];
        factor: number[];
        umbral: number[];
        focos: Record<string, string>;
        pausa_min: number;
        metodos: string[];
    },
    resumen: Object as () => {
        acciones: number;
        usuarios: number;
        conexiones: number;
        escrituras: number;
        negocios: number;
        fallidas: number;
        desde: string;
        hasta: string;
        picos: { hora: number; total: number }[];
        generado_en: string;
    },
    porHora: Object as () => { horas: string[]; acciones: number[]; conexiones: number[] },
    usuarios: Array as () => UsuarioRitmo[],
    usuarios_historial: Array as () => { id: number; nombre: string }[],
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

/** El punto del camino: verde es acción de negocio, azul escritura, gris navegación. */
const colorPunto = (paso: Paso): string => {
    if (paso.anormal) return 'bg-red-500 ring-2 ring-red-500/30';
    if (paso.tipo === 'negocio') return 'bg-emerald-500';
    if (paso.tipo === 'escritura') return 'bg-blue-500';

    return 'bg-gray-400';
};

const pasoFalla = (paso: Paso): boolean => !!paso.error || (paso.status ?? 0) >= 400;

const parametrosActuales = () => ({
    ...props.filtro,
    bitacora_desde: props.bitacoraFiltro.desde,
    bitacora_hasta: props.bitacoraFiltro.hasta,
    bitacora_usuario: props.bitacoraFiltro.usuario,
    bitacora_tipo: props.bitacoraFiltro.tipo,
    bitacora_metodo: props.bitacoraFiltro.metodo,
});

const filtrar = (cambios: Record<string, any>) => {
    const nuevos = { ...parametrosActuales(), ...cambios } as Record<string, any>;
    Object.keys(nuevos).forEach((clave) => {
        if (nuevos[clave] === null || nuevos[clave] === '') delete nuevos[clave];
    });

    router.get(route('Actividad.index'), nuevos, { preserveScroll: true, preserveState: true });
};

const seleccionUsuario = computed(() => (props.filtro.camino_usuario ? String(props.filtro.camino_usuario) : ''));

const cambiarUsuario = (evento: Event) => {
    const valor = (evento.target as HTMLSelectElement).value;
    filtrar({ camino_usuario: valor === '' ? null : Number(valor) });
};

const cambiarBitacoraUsuario = (evento: Event) => {
    const valor = (evento.target as HTMLSelectElement).value;
    filtrar({ bitacora_usuario: valor === '' ? null : Number(valor) });
};

const cambiarBitacoraTipo = (evento: Event) => {
    filtrar({ bitacora_tipo: (evento.target as HTMLSelectElement).value });
};

const cambiarBitacoraMetodo = (evento: Event) => {
    filtrar({ bitacora_metodo: (evento.target as HTMLSelectElement).value });
};

const cambiarBitacoraDesde = (evento: Event) => {
    filtrar({ bitacora_desde: (evento.target as HTMLInputElement).value });
};

const cambiarBitacoraHasta = (evento: Event) => {
    filtrar({ bitacora_hasta: (evento.target as HTMLInputElement).value });
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

const pausasCamino = computed(() => props.camino.pasos.filter((paso) => paso.pausa).length);

const pausasTotales = computed(() => props.usuarios.reduce((total, usuario) => total + usuario.pausas, 0));

const anomaliasTotales = computed(() => props.usuarios.reduce((total, usuario) => total + usuario.anomalias, 0));

const picosTexto = computed(() =>
    props.resumen.picos.length ? props.resumen.picos.map((pico) => `${String(pico.hora).padStart(2, '0')}h`).join(' · ') : 'sin datos',
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
            <div class="border-sidebar-border/60 bg-card flex flex-col gap-3 rounded-xl border p-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-foreground text-lg font-semibold">Actividad y tiempos entre acciones</h2>
                    <p class="text-muted-foreground mt-0.5 text-xs">
                        {{ props.resumen.desde }} → {{ props.resumen.hasta }} · un paso por petición, con sus acciones de negocio dentro · actualizado
                        {{ props.resumen.generado_en }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <div class="border-sidebar-border/60 flex rounded-lg border p-0.5">
                        <button
                            v-for="(texto, valor) in props.opciones.focos"
                            :key="valor"
                            @click="filtrar({ foco: valor })"
                            class="cursor-pointer rounded-md px-3 py-1 text-xs font-medium transition-colors"
                            :class="
                                props.filtro.foco === valor ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'
                            "
                        >
                            {{ texto }}
                        </button>
                    </div>
                    <div class="border-sidebar-border/60 flex rounded-lg border p-0.5">
                        <button
                            v-for="dia in props.opciones.dias"
                            :key="dia"
                            @click="filtrar({ dias: dia })"
                            class="cursor-pointer rounded-md px-3 py-1 text-xs font-medium transition-colors"
                            :class="props.filtro.dias === dia ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
                        >
                            {{ dia }} días
                        </button>
                    </div>
                    <label
                        class="text-muted-foreground flex items-center gap-1.5 text-[11px]"
                        title="Multiplicador aplicado a la mediana de los saltos del usuario. Un salto es anormal cuando supera la mediana por este factor."
                    >
                        Ritmo
                        <select
                            :value="props.filtro.factor"
                            @change="filtrar({ factor: Number(($event.target as HTMLSelectElement).value) })"
                            class="border-sidebar-border/60 bg-background text-foreground h-8 rounded-md border px-2 text-xs"
                        >
                            <option v-for="factor in props.opciones.factor" :key="factor" :value="factor">{{ factor }}×</option>
                        </select>
                    </label>
                    <label
                        class="text-muted-foreground flex items-center gap-1.5 text-[11px]"
                        title="Tiempo mínimo absoluto, en minutos, para considerar anormal un salto. El umbral real es el mayor valor entre este piso y el Ritmo aplicado a la mediana."
                    >
                        Piso
                        <select
                            :value="props.filtro.umbral"
                            @change="filtrar({ umbral: Number(($event.target as HTMLSelectElement).value) })"
                            class="border-sidebar-border/60 bg-background text-foreground h-8 rounded-md border px-2 text-xs"
                        >
                            <option v-for="umbral in props.opciones.umbral" :key="umbral" :value="umbral">{{ umbral }} min</option>
                        </select>
                    </label>
                </div>
            </div>

            <div class="text-muted-foreground bg-muted/40 rounded-lg border px-3 py-2 text-[11px] leading-relaxed">
                <strong class="text-foreground">Cómo se interpreta el umbral:</strong>
                <strong class="text-foreground">Ritmo</strong> multiplica la mediana de los saltos del usuario;
                <strong class="text-foreground">Piso</strong> es el mínimo absoluto en minutos. El umbral efectivo es el mayor de ambos valores. Por
                ejemplo, con Ritmo 3× y Piso 10 min, un salto se marca anormal cuando supera 10 minutos y la mediana multiplicada por 3.
            </div>

            <!-- KPIs -->
            <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
                <Card>
                    <CardContent class="flex items-center gap-3 p-4">
                        <MousePointerClick class="h-5 w-5 text-emerald-600" />
                        <div>
                            <p class="text-muted-foreground text-[10px] font-semibold tracking-wider uppercase">Pasos</p>
                            <p class="text-foreground text-xl font-bold">{{ numero(props.resumen.acciones) }}</p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="flex items-center gap-3 p-4">
                        <MousePointerClick class="h-5 w-5 text-teal-600" />
                        <div>
                            <p class="text-muted-foreground text-[10px] font-semibold tracking-wider uppercase">Acciones de negocio</p>
                            <p class="text-foreground text-xl font-bold">{{ numero(props.resumen.negocios) }}</p>
                        </div>
                    </CardContent>
                </Card>
                <Card :class="props.resumen.fallidas ? 'border-amber-500/50' : ''">
                    <CardContent class="flex items-center gap-3 p-4">
                        <Ban :class="props.resumen.fallidas ? 'text-amber-600' : 'text-muted-foreground'" class="h-5 w-5" />
                        <div>
                            <p class="text-muted-foreground text-[10px] font-semibold tracking-wider uppercase">Rechazadas</p>
                            <p class="text-xl font-bold" :class="props.resumen.fallidas ? 'text-amber-600 dark:text-amber-400' : 'text-foreground'">
                                {{ numero(props.resumen.fallidas) }}
                            </p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="flex items-center gap-3 p-4">
                        <LogIn class="h-5 w-5 text-blue-600" />
                        <div>
                            <p class="text-muted-foreground text-[10px] font-semibold tracking-wider uppercase">Conexiones</p>
                            <p class="text-foreground text-xl font-bold">{{ numero(props.resumen.conexiones) }}</p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="flex items-center gap-3 p-4">
                        <div
                            class="grid h-5 w-5 place-items-center rounded bg-gray-950 text-[10px] font-bold text-white dark:bg-white dark:text-gray-950"
                        >
                            U
                        </div>
                        <div>
                            <p class="text-muted-foreground text-[10px] font-semibold tracking-wider uppercase">Usuarios activos</p>
                            <p class="text-foreground text-xl font-bold">{{ numero(props.resumen.usuarios) }}</p>
                        </div>
                    </CardContent>
                </Card>
                <Card :class="anomaliasTotales ? 'border-red-500/50' : ''">
                    <CardContent class="flex items-center gap-3 p-4">
                        <AlertTriangle :class="anomaliasTotales ? 'text-red-500' : 'text-muted-foreground'" class="h-5 w-5" />
                        <div>
                            <p class="text-muted-foreground text-[10px] font-semibold tracking-wider uppercase">Saltos anormales</p>
                            <p class="text-xl font-bold" :class="anomaliasTotales ? 'text-red-600 dark:text-red-400' : 'text-foreground'">
                                {{ numero(anomaliasTotales) }}
                            </p>
                            <p class="text-muted-foreground text-[10px]">{{ numero(pausasTotales) }} pausa(s) sin el sistema, no cuentan</p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div v-if="props.resumen.acciones === 0" class="text-foreground rounded-xl border border-amber-500/40 bg-amber-500/5 p-4 text-sm">
                No hay pasos en el período con este foco. La bitácora registra una fila por petición y una por cada acción de negocio que hace el
                controlador dentro de ella (guardar, cambiar el estado de una OP, cargar un Excel, un intento rechazado), así que con «Solo acciones»
                basta que alguien guarde algo para que aparezcan.
            </div>

            <!-- Horas + camino -->
            <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
                <Card class="xl:col-span-1">
                    <CardHeader>
                        <CardTitle class="text-sm">Actividad por hora del día</CardTitle>
                        <p class="text-muted-foreground text-xs">
                            Barras verdes: todos los pasos. Azules: solo inicios de sesión. Más movimiento: {{ picosTexto }} ·
                            {{ numero(props.resumen.escrituras) }} pasos escribieron algo ({{ numero(props.resumen.negocios) }} con acción de
                            negocio).
                        </p>
                    </CardHeader>
                    <CardContent>
                        <VChart v-if="props.resumen.acciones > 0" :option="opcionesHoras" autoresize class="h-72 w-full" />
                        <p v-else class="text-muted-foreground py-10 text-center text-xs">Sin movimiento en el período</p>
                    </CardContent>
                </Card>

                <!-- El camino del usuario: los saltos anormales van en rojo -->
                <Card class="xl:col-span-2">
                    <CardHeader class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <CardTitle class="text-sm"> Camino de {{ props.camino.usuario ?? 'sin usuario' }} </CardTitle>
                            <p class="text-muted-foreground text-xs">
                                Salto anormal cuando supera {{ props.camino.umbral_min }} min ({{ props.filtro.factor }}× la mediana de este usuario,
                                con piso de {{ props.filtro.umbral }} min). Foco «{{ props.opciones.focos[props.filtro.foco] }}».
                                <span :class="anomaliasCamino ? 'font-semibold text-red-600' : ''">
                                    {{ anomaliasCamino }} salto(s) anormal(es).
                                </span>
                                <span v-if="pausasCamino">
                                    · {{ pausasCamino }} pausa(s): el usuario estuvo más de {{ props.opciones.pausa_min }} min fuera o el navegador
                                    avisó que dejó la pestaña; esas no se consideran anomalía.</span
                                >
                            </p>
                        </div>
                        <select
                            :value="seleccionUsuario"
                            @change="cambiarUsuario"
                            aria-label="Filtrar Camino de Superadmin por usuario con historial"
                            class="border-sidebar-border/60 bg-background text-foreground h-8 rounded-md border px-2 text-xs"
                        >
                            <option value="">El más activo</option>
                            <option v-for="usuario in props.usuarios_historial" :key="usuario.id" :value="usuario.id">
                                {{ usuario.nombre }}
                            </option>
                        </select>
                    </CardHeader>
                    <CardContent>
                        <p v-if="props.camino.ocultos > 0" class="text-muted-foreground mb-2 text-[11px]">
                            Se omiten {{ props.camino.ocultos }} pasos más antiguos de {{ props.camino.pasos.length }} mostrados.
                        </p>

                        <div class="max-h-[560px] space-y-3 overflow-y-auto pr-1">
                            <div v-for="grupo in pasosPorDia" :key="grupo.dia">
                                <p class="text-muted-foreground mb-1 font-mono text-[10px] tracking-widest uppercase">Día {{ grupo.dia }}</p>

                                <div v-for="(paso, indice) in grupo.pasos" :key="paso.hora + indice">
                                    <!-- El aviso del navegador no es una acción: separa y explica la pausa -->
                                    <div v-if="paso.tipo === 'marcador'" class="flex items-center gap-2 py-1 pl-3">
                                        <span class="border-sidebar-border flex-1 border-t border-dashed" />
                                        <span
                                            class="text-muted-foreground bg-muted/60 inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                        >
                                            <LogOut class="h-3 w-3" />
                                            {{ paso.etiqueta }} · {{ paso.hora.slice(6) }}
                                            <span v-if="paso.ip" class="font-mono opacity-70">· IP {{ paso.ip }}</span>
                                        </span>
                                        <span class="border-sidebar-border flex-1 border-t border-dashed" />
                                    </div>

                                    <template v-else>
                                        <!-- El intervalo vive entre pasos: así se ve el tiempo perdido -->
                                        <div v-if="paso.intervalo_seg !== null" class="flex items-center gap-2 py-0.5 pl-3">
                                            <span
                                                class="h-5 w-px"
                                                :class="
                                                    paso.anormal
                                                        ? 'bg-red-500'
                                                        : paso.pausa
                                                          ? 'border-sidebar-border border-l border-dashed'
                                                          : 'bg-sidebar-border'
                                                "
                                            />
                                            <span
                                                class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                                :class="
                                                    paso.anormal
                                                        ? 'bg-red-500/15 text-red-600 ring-1 ring-red-500/40 dark:text-red-400'
                                                        : 'bg-muted text-muted-foreground'
                                                "
                                            >
                                                <TriangleAlert v-if="paso.anormal" class="h-3 w-3" />
                                                {{ tiempoLegible(paso.intervalo_seg) }}
                                                <span v-if="paso.pausa" class="font-normal">sin el sistema</span>
                                            </span>
                                        </div>

                                        <div
                                            class="flex items-start gap-2 rounded-md border px-2.5 py-1.5"
                                            :class="paso.anormal ? 'border-red-500/50 bg-red-500/5' : 'border-sidebar-border/50 bg-background'"
                                        >
                                            <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full" :class="colorPunto(paso)" />

                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center gap-2">
                                                    <span class="text-muted-foreground w-[80px] shrink-0 font-mono text-[11px]">
                                                        {{ paso.hora.slice(6) }}
                                                    </span>
                                                    <span class="text-foreground min-w-0 truncate text-xs font-medium">
                                                        {{ paso.etiqueta }}
                                                    </span>
                                                    <span
                                                        v-if="paso.otras"
                                                        class="shrink-0 rounded bg-emerald-500/15 px-1.5 py-0.5 font-mono text-[9px] font-semibold text-emerald-700 dark:text-emerald-400"
                                                        :title="`${paso.otras} acción(es) de negocio más en la misma petición`"
                                                    >
                                                        +{{ paso.otras }}
                                                    </span>
                                                    <span
                                                        v-if="paso.entidad"
                                                        class="text-muted-foreground hidden shrink-0 font-mono text-[10px] sm:inline"
                                                    >
                                                        {{ paso.entidad }}
                                                    </span>
                                                    <span
                                                        class="text-muted-foreground hidden min-w-0 flex-1 truncate font-mono text-[10px] lg:inline"
                                                    >
                                                        /{{ paso.ruta }}
                                                    </span>
                                                    <span
                                                        class="border-sidebar-border/60 text-muted-foreground shrink-0 rounded border px-1.5 py-0.5 font-mono text-[9px] uppercase"
                                                    >
                                                        {{ paso.metodo }}
                                                    </span>
                                                    <span
                                                        class="text-muted-foreground shrink-0 font-mono text-[9px]"
                                                        :title="paso.ip ? `Dirección IP de origen: ${paso.ip}` : 'La IP no está disponible'"
                                                    >
                                                        IP: {{ paso.ip ?? 'no disponible' }}
                                                    </span>
                                                    <span
                                                        v-if="pasoFalla(paso)"
                                                        class="shrink-0 rounded bg-amber-500/15 px-1.5 py-0.5 font-mono text-[9px] text-amber-700 dark:text-amber-400"
                                                    >
                                                        {{ paso.status ?? 'rechazada' }}
                                                    </span>
                                                    <span class="text-muted-foreground w-[52px] shrink-0 text-right font-mono text-[10px]">
                                                        {{ duracionLegible(paso.duracion_ms) }}
                                                    </span>
                                                </div>

                                                <p
                                                    v-if="paso.error || paso.detalle"
                                                    class="mt-1 truncate pl-[88px] text-[11px]"
                                                    :class="paso.error ? 'text-amber-700 dark:text-amber-400' : 'text-muted-foreground'"
                                                >
                                                    {{ paso.error ?? paso.detalle }}
                                                </p>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <p v-if="!props.camino.pasos.length" class="text-muted-foreground py-10 text-center text-xs">
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
                    <p class="text-muted-foreground text-xs">
                        Promedio y mediana de minutos entre acción y acción. Las pausas ({{ props.opciones.pausa_min }} min fuera o aviso del
                        navegador) se muestran aparte y no entran en esos tiempos. Haz clic en una fila para ver su camino.
                    </p>
                </CardHeader>
                <CardContent class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="border-sidebar-border/60 text-muted-foreground border-b text-left text-[10px] tracking-wider uppercase">
                                <th class="py-2 pr-3 font-semibold">Usuario</th>
                                <th class="py-2 pr-3 text-right font-semibold">Pasos</th>
                                <th class="py-2 pr-3 text-right font-semibold">Negocio</th>
                                <th class="py-2 pr-3 text-right font-semibold">Rechazadas</th>
                                <th class="py-2 pr-3 text-right font-semibold">Conexiones</th>
                                <th class="py-2 pr-3 text-right font-semibold">Promedio</th>
                                <th class="py-2 pr-3 text-right font-semibold">Mediana</th>
                                <th class="py-2 pr-3 text-right font-semibold">Umbral</th>
                                <th class="py-2 pr-3 text-right font-semibold">Máximo</th>
                                <th class="py-2 pr-3 text-right font-semibold">Pausas</th>
                                <th class="py-2 pr-3 text-right font-semibold">Anomalías</th>
                                <th class="py-2 pr-3 font-semibold">Última</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="usuario in props.usuarios"
                                :key="usuario.id"
                                @click="filtrar({ camino_usuario: usuario.id })"
                                class="border-sidebar-border/30 hover:bg-muted/50 cursor-pointer border-b transition-colors"
                                :class="props.filtro.camino_usuario === usuario.id ? 'bg-muted/70' : ''"
                            >
                                <td class="text-foreground py-2 pr-3 font-medium">{{ usuario.nombre }}</td>
                                <td class="text-foreground py-2 pr-3 text-right font-mono">{{ numero(usuario.acciones) }}</td>
                                <td class="py-2 pr-3 text-right font-mono text-emerald-600 dark:text-emerald-400">{{ numero(usuario.negocio) }}</td>
                                <td
                                    class="py-2 pr-3 text-right font-mono"
                                    :class="usuario.fallidas ? 'text-amber-600 dark:text-amber-400' : 'text-muted-foreground'"
                                >
                                    {{ numero(usuario.fallidas) }}
                                </td>
                                <td class="text-muted-foreground py-2 pr-3 text-right font-mono">{{ numero(usuario.conexiones) }}</td>
                                <td class="text-foreground py-2 pr-3 text-right font-mono">{{ usuario.promedio_min }} min</td>
                                <td class="text-muted-foreground py-2 pr-3 text-right font-mono">{{ usuario.mediana_min }} min</td>
                                <td class="text-muted-foreground py-2 pr-3 text-right font-mono">{{ usuario.umbral_min }} min</td>
                                <td class="text-muted-foreground py-2 pr-3 text-right font-mono">{{ usuario.max_min }} min</td>
                                <td class="text-muted-foreground py-2 pr-3 text-right font-mono">{{ numero(usuario.pausas) }}</td>
                                <td class="py-2 pr-3 text-right">
                                    <span
                                        class="rounded-full px-2 py-0.5 font-mono text-[11px] font-semibold"
                                        :class="usuario.anomalias ? 'bg-red-500/15 text-red-600 dark:text-red-400' : 'bg-muted text-muted-foreground'"
                                    >
                                        {{ usuario.anomalias }}
                                    </span>
                                </td>
                                <td class="text-muted-foreground py-2 font-mono text-[11px]">{{ usuario.ultima }} · {{ usuario.ultima_accion }}</td>
                            </tr>
                            <tr v-if="!props.usuarios.length">
                                <td colspan="12" class="text-muted-foreground py-8 text-center">Sin usuarios en el período</td>
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
                        <p class="text-muted-foreground text-xs">De una acción a la siguiente, con su tiempo promedio.</p>
                    </CardHeader>
                    <CardContent class="space-y-1.5">
                        <div
                            v-for="par in props.transiciones"
                            :key="par.de + par.a"
                            class="border-sidebar-border/40 flex items-center justify-between gap-2 rounded-md border px-2.5 py-1.5 text-[11px]"
                        >
                            <span class="text-foreground min-w-0 truncate">
                                {{ par.de }} <span class="text-muted-foreground">→</span> {{ par.a }}
                            </span>
                            <span class="text-muted-foreground shrink-0 font-mono">
                                {{ par.veces }}× · {{ par.promedio_min }} min
                                <span v-if="par.anormales" class="ml-1 rounded bg-red-500/15 px-1 font-semibold text-red-600 dark:text-red-400">
                                    {{ par.anormales }}
                                </span>
                            </span>
                        </div>
                        <p v-if="!props.transiciones.length" class="text-muted-foreground py-8 text-center text-xs">
                            Falta secuencia para calcular saltos
                        </p>
                    </CardContent>
                </Card>

                <!-- Bitácora cruda -->
                <Card class="xl:col-span-2">
                    <CardHeader class="flex flex-row items-start justify-between gap-3">
                        <div>
                            <CardTitle class="text-sm">Bitácora</CardTitle>
                            <p class="text-muted-foreground text-xs">
                                {{ numero(props.bitacora.total) }} filas · página {{ props.bitacora.current_page }} de {{ props.bitacora.last_page }}
                            </p>
                        </div>
                        <div class="flex gap-1">
                            <Button
                                variant="ghost"
                                size="icon"
                                class="h-8 w-8"
                                :disabled="!props.bitacora.prev_page_url"
                                @click="irPagina(props.bitacora.prev_page_url)"
                            >
                                <ChevronLeft class="h-4 w-4" />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="h-8 w-8"
                                :disabled="!props.bitacora.next_page_url"
                                @click="irPagina(props.bitacora.next_page_url)"
                            >
                                <ChevronRight class="h-4 w-4" />
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="border-sidebar-border/60 mb-3 grid gap-2 rounded-lg border p-3 sm:grid-cols-2 xl:grid-cols-5">
                            <label class="text-muted-foreground flex flex-col gap-1 text-[10px] font-semibold uppercase">
                                Desde (fecha y hora)
                                <input
                                    type="datetime-local"
                                    :value="props.bitacoraFiltro.desde"
                                    @change="cambiarBitacoraDesde"
                                    class="border-sidebar-border/60 bg-background text-foreground h-8 rounded-md border px-2 text-xs font-normal normal-case"
                                />
                            </label>
                            <label class="text-muted-foreground flex flex-col gap-1 text-[10px] font-semibold uppercase">
                                Hasta (fecha y hora)
                                <input
                                    type="datetime-local"
                                    :value="props.bitacoraFiltro.hasta"
                                    @change="cambiarBitacoraHasta"
                                    class="border-sidebar-border/60 bg-background text-foreground h-8 rounded-md border px-2 text-xs font-normal normal-case"
                                />
                            </label>
                            <label class="text-muted-foreground flex flex-col gap-1 text-[10px] font-semibold uppercase">
                                Usuario con historial
                                <select
                                    :value="props.bitacoraFiltro.usuario ?? ''"
                                    @change="cambiarBitacoraUsuario"
                                    class="border-sidebar-border/60 bg-background text-foreground h-8 rounded-md border px-2 text-xs font-normal normal-case"
                                >
                                    <option value="">Todos</option>
                                    <option v-for="usuario in props.usuarios_historial" :key="usuario.id" :value="usuario.id">
                                        {{ usuario.nombre }}
                                    </option>
                                </select>
                            </label>
                            <label class="text-muted-foreground flex flex-col gap-1 text-[10px] font-semibold uppercase">
                                Tipo de registro
                                <select
                                    :value="props.bitacoraFiltro.tipo"
                                    @change="cambiarBitacoraTipo"
                                    class="border-sidebar-border/60 bg-background text-foreground h-8 rounded-md border px-2 text-xs font-normal normal-case"
                                >
                                    <option value="todos">Todos</option>
                                    <option value="acciones">Acciones</option>
                                    <option value="navegacion">Navegación</option>
                                </select>
                            </label>
                            <label class="text-muted-foreground flex flex-col gap-1 text-[10px] font-semibold uppercase">
                                Método HTTP
                                <select
                                    :value="props.bitacoraFiltro.metodo"
                                    @change="cambiarBitacoraMetodo"
                                    class="border-sidebar-border/60 bg-background text-foreground h-8 rounded-md border px-2 text-xs font-normal normal-case"
                                >
                                    <option value="">Todos</option>
                                    <option v-for="metodo in props.opciones.metodos" :key="metodo" :value="metodo">{{ metodo }}</option>
                                </select>
                            </label>
                        </div>

                        <div class="max-h-[420px] overflow-auto">
                            <table class="w-full text-[11px]">
                                <thead class="bg-card sticky top-0">
                                    <tr
                                        class="border-sidebar-border/60 text-muted-foreground border-b text-left text-[10px] tracking-wider uppercase"
                                    >
                                        <th class="py-2 pr-3 font-semibold">Cuándo</th>
                                        <th class="py-2 pr-3 font-semibold">Usuario</th>
                                        <th class="py-2 pr-3 font-semibold">Acción</th>
                                        <th class="py-2 pr-3 font-semibold">Ruta</th>
                                        <th class="py-2 pr-3 font-semibold">Método</th>
                                        <th class="py-2 pr-3 font-semibold">IP</th>
                                        <th class="py-2 pr-3 text-right font-semibold">ms</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="fila in props.bitacora.data" :key="fila.id" class="border-sidebar-border/30 border-b">
                                        <td class="text-muted-foreground py-1.5 pr-3 font-mono">{{ fila.cuando }}</td>
                                        <td class="text-foreground truncate py-1.5 pr-3">{{ fila.usuario }}</td>
                                        <td class="py-1.5 pr-3">
                                            <span
                                                class="mr-1.5 rounded px-1.5 py-0.5 font-mono text-[9px] uppercase"
                                                :class="
                                                    fila.tipo === 'accion'
                                                        ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400'
                                                        : 'bg-muted text-muted-foreground'
                                                "
                                            >
                                                {{ fila.tipo === 'accion' ? 'acción' : 'petición' }}
                                            </span>
                                            <span :class="fila.esEscritura ? 'font-semibold text-blue-600 dark:text-blue-400' : 'text-foreground'">
                                                {{ fila.etiqueta }}
                                            </span>
                                            <span v-if="fila.entidad" class="text-muted-foreground ml-1 font-mono text-[10px]">{{
                                                fila.entidad
                                            }}</span>
                                            <p
                                                v-if="fila.error || fila.detalle"
                                                class="truncate text-[10px]"
                                                :class="fila.error ? 'text-amber-700 dark:text-amber-400' : 'text-muted-foreground'"
                                            >
                                                {{ fila.error ?? fila.detalle }}
                                            </p>
                                        </td>
                                        <td class="text-muted-foreground py-1.5 pr-3 font-mono">/{{ fila.ruta }}</td>
                                        <td class="text-muted-foreground py-1.5 pr-3 font-mono">{{ fila.metodo }}</td>
                                        <td class="text-muted-foreground py-1.5 pr-3 font-mono" :title="fila.ip ?? 'La IP no está disponible'">
                                            {{ fila.ip ?? 'no disponible' }}
                                        </td>
                                        <td class="text-muted-foreground py-1.5 text-right font-mono">{{ fila.duracion_ms }}</td>
                                    </tr>
                                    <tr v-if="!props.bitacora.data?.length">
                                        <td colspan="7" class="text-muted-foreground py-8 text-center">Sin filas registradas</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div class="mt-8">
                <Card>
                    <CardHeader>
                        <div class="flex items-center gap-2">
                            <BookmarkCheck class="h-5 w-5 text-muted-foreground" />
                            <CardTitle class="text-base font-semibold">Solo Lectura</CardTitle>
                        </div>
                        <CardDescription>
                            Módulos de consulta y visualización de datos sin permisos de edición.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3">
                            <Link
                                href="/EstadoOperacion"
                                class="flex items-center gap-3 rounded-lg border p-4 transition-colors hover:bg-muted/50"
                            >
                                <div class="rounded-md bg-muted p-2 text-foreground">
                                    <SlidersHorizontal class="h-5 w-5" />
                                </div>
                                <div>
                                    <div class="font-medium">EstadoOperacion</div>
                                    <div class="text-xs text-muted-foreground">Consulta de estados de operación</div>
                                </div>
                            </Link>
                            <Link
                                href="/Distribucion_por_tallas"
                                class="flex items-center gap-3 rounded-lg border p-4 transition-colors hover:bg-muted/50"
                            >
                                <div class="rounded-md bg-muted p-2 text-foreground">
                                    <Grid3x3 class="h-5 w-5" />
                                </div>
                                <div>
                                    <div class="font-medium">Distribución por tallas</div>
                                    <div class="text-xs text-muted-foreground">Consulta de distribución por tallas</div>
                                </div>
                            </Link>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <AccionesSuperadmin
                :es-ambiente-test="props.esAmbienteTest"
                :es-ambiente-local="props.esAmbienteLocal"
                :materiales="props.materiales"
                :estados-op="props.estadosOp"
            />
        </div>
    </AppLayout>
</template>
