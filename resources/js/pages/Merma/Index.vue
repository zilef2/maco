<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import {
    ArrowPathIcon,
    BanknotesIcon,
    ChevronDownIcon,
    ChevronRightIcon,
    ChevronUpIcon,
    ClipboardDocumentListIcon,
    ShieldCheckIcon,
    XMarkIcon,
} from '@heroicons/vue/24/solid';
import DetalleMermaModal from '@/pages/Merma/DetalleMermaModal.vue';
import TablaMermas from '@/pages/Merma/TablaMermas.vue';
import GraficasMermas from '@/pages/Merma/GraficasMermas.vue';
import { formatDate, zilef_nuber_format } from '@/global.ts';

const props = defineProps({
    mermas: { type: Array as () => any[], default: () => [] },
    produccion: { type: Array as () => any[], default: () => [] },
    breadcrumbs: { type: Array as () => Array<{ label?: string; href?: string }>, default: () => [] },
    title: String,
    numberPermissions: Number,
});

type Periodo = 'mes' | '30' | '60' | 'todo';

const periodo = ref<Periodo>('60');
const mesElegido = ref<string | null>(null);
const tallerElegido = ref<number | null>(null);
const empresaElegida = ref<number | null>(null);
const fechaInicio = ref<string>('');
const fechaFin = ref<string>('');
const cargando = ref(false);
const detalleOpen = ref(false);
const mermaVisible = ref<any>(null);
const metrica = ref<'piezas' | 'valor'>('piezas');
const esOscuro = ref(false);
let observadorTema: MutationObserver | null = null;

const desde = (dias: number): Date => {
    const fecha = new Date();
    fecha.setHours(0, 0, 0, 0);
    fecha.setDate(fecha.getDate() - dias);
    return fecha;
};

const hayRangoFechas = computed(() => Boolean(fechaInicio.value || fechaFin.value));

const dentroDeRangoFechas = (valor: any): boolean => {
    const fecha = new Date(String(valor));
    if (Number.isNaN(fecha.getTime())) return false;
    if (fechaInicio.value) {
        const inicio = new Date(`${fechaInicio.value}T00:00:00`);
        if (fecha < inicio) return false;
    }
    if (fechaFin.value) {
        const fin = new Date(`${fechaFin.value}T23:59:59.999`);
        if (fecha > fin) return false;
    }
    return true;
};

const enPeriodo = (merma: any): boolean => {
    if (hayRangoFechas.value) return dentroDeRangoFechas(merma.fecha_registro);
    if (mesElegido.value) return String(merma.mes) === mesElegido.value;
    const fecha2 = new Date(String(merma.fecha_registro));
    if (Number.isNaN(fecha2.getTime())) return true;
    if (periodo.value === 'mes') {
        const ahora = new Date();
        return fecha2.getFullYear() === ahora.getFullYear() && fecha2.getMonth() === ahora.getMonth();
    }
    if (periodo.value === 'todo') return true;
    return fecha2 >= desde(Number(periodo.value));
};

const delPeriodo = computed(() => (props.mermas ?? []).filter(enPeriodo));

const opcionesTalleres = computed(() => {
    const mapa = new Map<number, string>();
    for (const merma of props.mermas ?? []) {
        if (merma.taller_id === null || merma.taller_id === undefined) continue;
        const id = Number(merma.taller_id);
        if (!mapa.has(id)) mapa.set(id, merma.taller_nombre || `Taller #${id}`);
    }
    return Array.from(mapa, ([id, nombre]) => ({ id, nombre })).sort((a, b) => a.nombre.localeCompare(b.nombre, 'es'));
});

const opcionesEmpresas = computed(() => {
    const mapa = new Map<number, string>();
    for (const merma of props.mermas ?? []) {
        if (merma.empresa_externa_id === null || merma.empresa_externa_id === undefined) continue;
        const id = Number(merma.empresa_externa_id);
        if (!mapa.has(id)) mapa.set(id, merma.empresa_externa_nombre || `Empresa externa #${id}`);
    }
    return Array.from(mapa, ([id, nombre]) => ({ id, nombre })).sort((a, b) => a.nombre.localeCompare(b.nombre, 'es'));
});

const cumpleTallerEmpresa = (merma: any): boolean => {
    if (tallerElegido.value !== null && Number(merma.taller_id) !== tallerElegido.value) return false;
    if (empresaElegida.value !== null && Number(merma.empresa_externa_id) !== empresaElegida.value) return false;
    return true;
};

const filasFiltradas = computed(() => delPeriodo.value.filter(cumpleTallerEmpresa));

const hayFiltros = computed(() =>
    Boolean(mesElegido.value || tallerElegido.value !== null || empresaElegida.value !== null || hayRangoFechas.value),
);

const limpiarFiltros = () => {
    mesElegido.value = null;
    tallerElegido.value = null;
    empresaElegida.value = null;
    fechaInicio.value = '';
    fechaFin.value = '';
};

const sumarFilas = (filas: any[]) => {
    const piezas = filas.reduce((suma, m) => suma + (Number(m.cantidad) || 0), 0);
    const valor = filas.reduce((suma, m) => suma + (Number(m.costo_total_perdida) || 0), 0);
    const esperadas = filas.reduce((suma, m) => suma + (Number(m.esperada) || 0), 0);
    const costoUnitario = filas.reduce((suma, m) => suma + (Number(m.costo_unitario) || 0), 0);
    return {
        filas: filas.length,
        piezas,
        valor,
        costoUnitario,
        ops: new Set(filas.map((m) => m.numero_op)).size,
        porcentaje: esperadas > 0 ? (piezas / esperadas) * 100 : 0,
    };
};

const totales = computed(() => sumarFilas(filasFiltradas.value));

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
    const fecha2 = new Date(`${mes}-01T12:00:00`);
    if (Number.isNaN(fecha2.getTime())) return mes;
    return fecha2.toLocaleDateString('es-CO', { month: 'long', year: 'numeric' });
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

const fechaCorta = (valor: string): string => (valor ? formatDate(new Date(`${valor}T12:00:00`), 'fecha') : '');

const textoRangoFechas = computed(() => {
    if (fechaInicio.value && fechaFin.value) return `${fechaCorta(fechaInicio.value)} → ${fechaCorta(fechaFin.value)}`;
    if (fechaInicio.value) return `Desde ${fechaCorta(fechaInicio.value)}`;
    if (fechaFin.value) return `Hasta ${fechaCorta(fechaFin.value)}`;
    return '';
});

const selectClass =
    'h-9 rounded-md border border-input bg-background px-2 text-sm text-foreground shadow-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30';

const segmentoClass = (activo: boolean) => [
    'whitespace-nowrap px-3 py-2 text-xs font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring',
    activo ? 'bg-foreground text-background shadow-sm' : 'text-muted-foreground hover:bg-muted hover:text-foreground',
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

const recargar = () => router.reload({ only: ['mermas', 'produccion'], preserveScroll: true });

onMounted(() => {
    esOscuro.value = document.documentElement.classList.contains('dark');
    observadorTema = new MutationObserver(() => {
        esOscuro.value = document.documentElement.classList.contains('dark');
    });
    observadorTema.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
});

onUnmounted(() => {
    observadorTema?.disconnect();
});

const breadcrumbCollapsed = ref(false);
</script>

<template>
    <Head :title="props.title" />

    <AppLayout>
        <div class="flex flex-col gap-4 p-4 sm:p-6">
            <div class="flex flex-col gap-2">
                <nav v-if="!breadcrumbCollapsed" class="flex items-center gap-1.5 text-xs text-muted-foreground" aria-label="Ruta de navegación">
                    <Link
                        :href="route('dashboard')"
                        class="rounded-sm transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        Dashboard
                    </Link>
                    <template v-if="(props.breadcrumbs || []).length > 0">
                        <ChevronRightIcon class="h-3 w-3 opacity-60" />
                        <Link
                            v-if="props.breadcrumbs[0]?.href"
                            :href="props.breadcrumbs[0].href"
                            class="rounded-sm transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            {{ props.breadcrumbs[0]?.label }}
                        </Link>
                        <span v-else class="font-medium text-foreground">{{ props.breadcrumbs[0]?.label }}</span>
                    </template>
                    <template v-if="(props.breadcrumbs || []).length > 1">
                        <ChevronRightIcon class="h-3 w-3 opacity-60" />
                        <Link
                            v-if="props.breadcrumbs[1]?.href"
                            :href="props.breadcrumbs[1].href"
                            class="rounded-sm transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            {{ props.breadcrumbs[1]?.label }}
                        </Link>
                        <span v-else class="font-medium text-foreground">{{ props.breadcrumbs[1]?.label }}</span>
                    </template>
                    <template v-if="(props.breadcrumbs || []).length > 2">
                        <ChevronRightIcon class="h-3 w-3 opacity-60" />
                        <Link
                            v-if="props.breadcrumbs[2]?.href"
                            :href="props.breadcrumbs[2].href"
                            class="rounded-sm transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            {{ props.breadcrumbs[2]?.label }}
                        </Link>
                        <span v-else class="font-medium text-foreground">{{ props.breadcrumbs[2]?.label }}</span>
                    </template>
                    <template v-if="(props.breadcrumbs || []).length > 3">
                        <ChevronRightIcon class="h-3 w-3 opacity-60" />
                        <Link
                            v-if="props.breadcrumbs[3]?.href"
                            :href="props.breadcrumbs[3].href"
                            class="rounded-sm transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            {{ props.breadcrumbs[3]?.label }}
                        </Link>
                        <span v-else class="font-medium text-foreground">{{ props.breadcrumbs[3]?.label }}</span>
                    </template>
                    <template v-if="(props.breadcrumbs || []).length > 4">
                        <ChevronRightIcon class="h-3 w-3 opacity-60" />
                        <Link
                            v-if="props.breadcrumbs[4]?.href"
                            :href="props.breadcrumbs[4].href"
                            class="rounded-sm transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            {{ props.breadcrumbs[4]?.label }}
                        </Link>
                        <span v-else class="font-medium text-foreground">{{ props.breadcrumbs[4]?.label }}</span>
                    </template>
                </nav>

                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div class="min-w-0" v-if="!breadcrumbCollapsed">
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

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div
                    class="inline-flex w-fit items-stretch divide-x divide-border overflow-hidden rounded-md border border-border bg-card shadow-sm"
                    role="tablist"
                    aria-label="Periodo de las mermas"
                >
                    <button
                        type="button"
                        role="tab"
                        :aria-selected="periodo === 'mes' && !mesElegido && !hayRangoFechas"
                        :class="segmentoClass(periodo === 'mes' && !mesElegido && !hayRangoFechas)"
                        @click="elegirPeriodo('mes')"
                    >
                        Mes actual
                    </button>
                    <button
                        type="button"
                        role="tab"
                        :aria-selected="periodo === '30' && !mesElegido && !hayRangoFechas"
                        :class="segmentoClass(periodo === '30' && !mesElegido && !hayRangoFechas)"
                        @click="elegirPeriodo('30')"
                    >
                        Últimos 30 días
                    </button>
                    <button
                        type="button"
                        role="tab"
                        :aria-selected="periodo === '60' && !mesElegido && !hayRangoFechas"
                        :class="segmentoClass(periodo === '60' && !mesElegido && !hayRangoFechas)"
                        @click="elegirPeriodo('60')"
                    >
                        Últimos 60 días
                    </button>
                    <button
                        type="button"
                        role="tab"
                        :aria-selected="periodo === 'todo' && !mesElegido && !hayRangoFechas"
                        :class="segmentoClass(periodo === 'todo' && !mesElegido && !hayRangoFechas)"
                        @click="elegirPeriodo('todo')"
                    >
                        Todo el histórico
                    </button>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <div class="flex items-center gap-1.5">
                        <label for="filtro-fecha-inicio" class="text-xs font-medium text-muted-foreground">Desde</label>
                        <input
                            id="filtro-fecha-inicio"
                            v-model="fechaInicio"
                            type="date"
                            aria-label="Fecha de inicio"
                            :class="selectClass"
                        />
                    </div>

                    <div class="flex items-center gap-1.5">
                        <label for="filtro-fecha-fin" class="text-xs font-medium text-muted-foreground">Hasta</label>
                        <input
                            id="filtro-fecha-fin"
                            v-model="fechaFin"
                            type="date"
                            aria-label="Fecha de fin"
                            :class="selectClass"
                        />
                    </div>

                    <label class="sr-only" for="filtro-taller">Filtrar por taller</label>
                    <select
                        id="filtro-taller"
                        v-model.number="tallerElegido"
                        aria-label="Filtrar por taller"
                        :class="selectClass"
                    >
                        <option :value="null">Todos los talleres</option>
                        <option v-if="opcionesTalleres.length > 0" :value="opcionesTalleres[0].id">
                            {{ opcionesTalleres[0].nombre }}
                        </option>
                        <option v-if="opcionesTalleres.length > 1" :value="opcionesTalleres[1].id">
                            {{ opcionesTalleres[1].nombre }}
                        </option>
                        <option v-if="opcionesTalleres.length > 2" :value="opcionesTalleres[2].id">
                            {{ opcionesTalleres[2].nombre }}
                        </option>
                        <option v-if="opcionesTalleres.length > 3" :value="opcionesTalleres[3].id">
                            {{ opcionesTalleres[3].nombre }}
                        </option>
                        <option v-if="opcionesTalleres.length > 4" :value="opcionesTalleres[4].id">
                            {{ opcionesTalleres[4].nombre }}
                        </option>
                    </select>

                    <label class="sr-only" for="filtro-empresa">Filtrar por empresa externa</label>
                    <select
                        id="filtro-empresa"
                        v-model.number="empresaElegida"
                        aria-label="Filtrar por empresa externa"
                        :class="selectClass"
                    >
                        <option :value="null">Todas las empresas externas</option>
                        <option v-if="opcionesEmpresas.length > 0" :value="opcionesEmpresas[0].id">
                            {{ opcionesEmpresas[0].nombre }}
                        </option>
                        <option v-if="opcionesEmpresas.length > 1" :value="opcionesEmpresas[1].id">
                            {{ opcionesEmpresas[1].nombre }}
                        </option>
                        <option v-if="opcionesEmpresas.length > 2" :value="opcionesEmpresas[2].id">
                            {{ opcionesEmpresas[2].nombre }}
                        </option>
                        <option v-if="opcionesEmpresas.length > 3" :value="opcionesEmpresas[3].id">
                            {{ opcionesEmpresas[3].nombre }}
                        </option>
                        <option v-if="opcionesEmpresas.length > 4" :value="opcionesEmpresas[4].id">
                            {{ opcionesEmpresas[4].nombre }}
                        </option>
                    </select>
                </div>
            </div>

            <div v-if="hayFiltros" class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                <span
                    v-if="mesElegido"
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

                <span
                    v-if="hayRangoFechas"
                    class="inline-flex items-center gap-1.5 rounded-full border border-border bg-muted/60 px-2.5 py-1 font-medium text-foreground"
                >
                    <ShieldCheckIcon class="h-3.5 w-3.5" />
                    {{ textoRangoFechas }}
                    <button
                        type="button"
                        @click="(fechaInicio = ''), (fechaFin = '')"
                        aria-label="Quitar el filtro de fechas"
                        class="rounded p-0.5 transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        <XMarkIcon class="h-3 w-3" />
                    </button>
                </span>

                <span
                    v-if="tallerElegido !== null"
                    class="inline-flex items-center gap-1.5 rounded-full border border-border bg-muted/60 px-2.5 py-1 font-medium text-foreground"
                >
                    <ShieldCheckIcon class="h-3.5 w-3.5" />
                    Taller:
                    {{ opcionesTalleres.find((o) => o.id === tallerElegido)?.nombre ?? `#${tallerElegido}` }}
                    <button
                        type="button"
                        @click="tallerElegido = null"
                        aria-label="Quitar el filtro del taller"
                        class="rounded p-0.5 transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        <XMarkIcon class="h-3 w-3" />
                    </button>
                </span>

                <span
                    v-if="empresaElegida !== null"
                    class="inline-flex items-center gap-1.5 rounded-full border border-border bg-muted/60 px-2.5 py-1 font-medium text-foreground"
                >
                    <ShieldCheckIcon class="h-3.5 w-3.5" />
                    Empresa externa:
                    {{ opcionesEmpresas.find((o) => o.id === empresaElegida)?.nombre ?? `#${empresaElegida}` }}
                    <button
                        type="button"
                        @click="empresaElegida = null"
                        aria-label="Quitar el filtro de la empresa externa"
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

            <TablaMermas
                :filas="filasFiltradas"
                :totales="totales"
                :hay-filtros="hayFiltros"
                :texto="texto"
                :dinero="dinero"
                :piezas="piezas"
                :porcentaje="porcentaje"
                :fecha="fecha"
                @ver-detalle="verDetalle"
                @abrir-orden="abrirOrden"
                @limpiar-filtros="limpiarFiltros"
            />

            <GraficasMermas
                :mermas="props.mermas"
                :produccion="props.produccion"
                :periodo="periodo"
                :mes-elegido="mesElegido"
                :fecha-inicio="fechaInicio"
                :fecha-fin="fechaFin"
                :metrica="metrica"
                :es-oscuro="esOscuro"
                :hay-rango-fechas="hayRangoFechas"
                :dentro-de-rango-fechas="dentroDeRangoFechas"
                :nombre-mes="nombreMes"
                :dinero="dinero"
                :piezas="piezas"
                :elegir-mes="elegirMes"
                @cambiar-metrica="metrica = $event"
            />

            <DetalleMermaModal
                :show="detalleOpen"
                @close="detalleOpen = false"
                :merma="mermaVisible"
                @ver-orden="abrirOrden"
            />
        </div>
    </AppLayout>
</template>

<style src="./Index.css" scoped></style>
