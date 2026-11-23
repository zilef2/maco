<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import DataPagination from '@/components/DataPagination.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import pkg from 'lodash';

import {
    ArrowPathIcon,
    ChevronDownIcon,
    ChevronRightIcon,
    ChevronUpIcon,
    CubeIcon,
    HashtagIcon,
    LanguageIcon,
    MagnifyingGlassIcon,
    Squares2X2Icon,
    SparklesIcon,
    XMarkIcon,
} from '@heroicons/vue/24/solid';

const { debounce, pickBy } = pkg;

const props = defineProps({
    fromController: { type: Object as () => any, default: () => ({}) },
    total: Number,
    filters: { type: Object as () => any, default: () => ({}) },
    breadcrumbs: { type: Array as () => Array<{ label?: string; href?: string }>, default: () => [] },
    perPage: Number,
    title: String,
    numberPermissions: Number,
    losSelect: Object,
    titulos: Array,
});

const breadcrumbCollapsed = ref(false);

type GrupoId = 'numericas' | 'alfabeticas' | 'otras';

type GrupoDef = {
    id: GrupoId;
    label: string;
    labelCorto: string;
    descripcion: string;
    icon: any;
    iconWrap: string;
    barra: string;
    badge: string;
    dot: string;
};

const GRUPOS: GrupoDef[] = [
    {
        id: 'numericas',
        label: 'Tallas numéricas',
        labelCorto: 'Numéricas',
        descripcion: 'Del 12 al 45, rangos y medidas',
        icon: HashtagIcon,
        iconWrap: 'border-indigo-200 bg-indigo-50 text-indigo-700 dark:border-indigo-500/30 dark:bg-indigo-500/10 dark:text-indigo-300',
        barra: 'bg-indigo-500',
        badge: 'border-indigo-200 bg-indigo-50 text-indigo-700 dark:border-indigo-500/30 dark:bg-indigo-500/10 dark:text-indigo-300',
        dot: 'bg-indigo-500',
    },
    {
        id: 'alfabeticas',
        label: 'Tallas alfabéticas',
        labelCorto: 'Alfabéticas',
        descripcion: 'XS, S, M, L, XL, XXL y similares',
        icon: LanguageIcon,
        iconWrap: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300',
        barra: 'bg-emerald-500',
        badge: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300',
        dot: 'bg-emerald-500',
    },
    {
        id: 'otras',
        label: 'Otras tallas',
        labelCorto: 'Otras',
        descripcion: 'Especiales, únicas o sin clasificar',
        icon: SparklesIcon,
        iconWrap: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300',
        barra: 'bg-amber-500',
        badge: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300',
        dot: 'bg-amber-500',
    },
];

const params = reactive({
    search: props.filters?.search ?? '',
    perPage: props.perPage ?? 200,
});

const grupoFiltro = ref<'todas' | GrupoId>('todas');
const cargando = ref(false);

const filas = computed<any[]>(() => props.fromController?.data ?? []);

const totalRegistros = computed(() => Number(props.fromController?.total ?? props.total ?? 0));
const paginaActual = computed(() => Number(props.fromController?.current_page ?? 1));
const ultimaPagina = computed(() => Number(props.fromController?.last_page ?? 1));
const desde = computed(() => Number(props.fromController?.from ?? 0));
const hasta = computed(() => Number(props.fromController?.to ?? 0));

const normalizar = (valor: any): string =>
    String(valor ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/\s+/g, ' ')
        .trim();

const tipoDeTalla = (talla: string): GrupoId => {
    const texto = talla.trim();
    if (!texto) return 'otras';
    if (/^\d+(?:[.,]\d+)?(?:\s*[-–/]\s*\d+(?:[.,]\d+)?)?$/.test(texto)) return 'numericas';
    if (/^[a-z]+$/i.test(texto) || /^\d*x*l$/i.test(texto)) return 'alfabeticas';
    return 'otras';
};

const ORDEN_ALFA = ['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL', '2XL', '3XL', '4XL', '5XL'];
const ORDEN_GRUPO: Record<GrupoId, number> = { numericas: 0, alfabeticas: 1, otras: 2 };

const numeroDeTalla = (talla: string): number => {
    const encontrado = talla.match(/\d+(?:[.,]\d+)?/);
    return encontrado ? Number.parseFloat(encontrado[0].replace(',', '.')) : Number.POSITIVE_INFINITY;
};

const posicionAlfa = (talla: string): number => {
    const posicion = ORDEN_ALFA.indexOf(talla.toUpperCase());
    return posicion === -1 ? 999 : posicion;
};

type ItemTalla = {
    talla: string;
    count: number;
    productos: Set<number>;
    planificada: number;
    tipo: GrupoId;
};

const itemsAgrupados = computed<ItemTalla[]>(() => {
    const mapa = new Map<string, ItemTalla>();

    for (const fila of filas.value) {
        const talla = String(fila.talla ?? '').trim() || 'Sin talla';
        const clave = normalizar(talla) || 'sin talla';
        const tipo = tipoDeTalla(talla);
        const actual = mapa.get(clave);

        if (actual) {
            actual.count++;
            actual.planificada += Number(fila.cantidad_planificada ?? 0);
            if (fila.producto_id !== null && fila.producto_id !== undefined) {
                actual.productos.add(Number(fila.producto_id));
            }
        } else {
            const productos = new Set<number>();
            if (fila.producto_id !== null && fila.producto_id !== undefined) {
                productos.add(Number(fila.producto_id));
            }
            mapa.set(clave, {
                talla,
                count: 1,
                productos,
                planificada: Number(fila.cantidad_planificada ?? 0),
                tipo,
            });
        }
    }

    return Array.from(mapa.values()).sort((a, b) => {
        if (ORDEN_GRUPO[a.tipo] !== ORDEN_GRUPO[b.tipo]) {
            return ORDEN_GRUPO[a.tipo] - ORDEN_GRUPO[b.tipo];
        }
        if (a.tipo === 'numericas') {
            const diferencia = numeroDeTalla(a.talla) - numeroDeTalla(b.talla);
            if (diferencia !== 0) return diferencia;
        }
        if (a.tipo === 'alfabeticas') {
            const diferencia = posicionAlfa(a.talla) - posicionAlfa(b.talla);
            if (diferencia !== 0) return diferencia;
        }
        return a.talla.localeCompare(b.talla, 'es', { numeric: true, sensitivity: 'base' });
    });
});

const conteos = computed<Record<GrupoId, number>>(() => {
    const base: Record<GrupoId, number> = { numericas: 0, alfabeticas: 0, otras: 0 };
    for (const item of itemsAgrupados.value) base[item.tipo]++;
    return base;
});

const totalTallas = computed(() => itemsAgrupados.value.length);

const gruposVisibles = computed(() => {
    const base = grupoFiltro.value === 'todas'
        ? itemsAgrupados.value
        : itemsAgrupados.value.filter((item) => item.tipo === grupoFiltro.value);

    return GRUPOS
        .map((grupo) => ({ ...grupo, items: base.filter((item) => item.tipo === grupo.id) }))
        .filter((grupo) => grupo.items.length > 0);
});

const hayFiltros = computed(() => params.search.trim() !== '' || grupoFiltro.value !== 'todas');

const limpiarFiltros = () => {
    params.search = '';
    grupoFiltro.value = 'todas';
};

const selectClass = 'h-9 rounded-md border border-input bg-background px-2 text-sm text-foreground shadow-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30';

const chipClass = (activo: boolean) => [
    'inline-flex shrink-0 items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background',
    activo
        ? 'border-foreground/15 bg-foreground text-background shadow-sm'
        : 'border-border bg-card text-muted-foreground hover:bg-muted hover:text-foreground',
];

const rutaIndex = new URL(String(route('Distribucion_por_tallas.index')), window.location.origin).pathname;

router.on('start', (event) => {
    const url = event.detail.visit.url;
    const pathname = url instanceof URL ? url.pathname : new URL(String(url), window.location.origin).pathname;
    if (pathname === rutaIndex) cargando.value = true;
});
router.on('finish', () => {
    cargando.value = false;
});

const recargar = () => {
    router.reload({ only: ['fromController', 'total', 'filters', 'perPage'], preserveScroll: true });
};

const cambiarPagina = (page: number) => {
    router.get(
        route('Distribucion_por_tallas.index'),
        pickBy({ ...params, page }),
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

watch(
    () => ({ ...params }),
    debounce(() => {
        router.get(
            route('Distribucion_por_tallas.index'),
            pickBy({ ...params }),
            { replace: true, preserveState: true, preserveScroll: true },
        );
    }, 200),
    { deep: true },
);
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
                        <h1 class="text-2xl font-semibold tracking-tight text-foreground">Tallas</h1>
                        <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground" role="status" aria-live="polite">
                            <span class="tabular-nums">
                                <span class="font-medium text-foreground">{{ totalRegistros }}</span>
                                {{ totalRegistros === 1 ? 'registro' : 'registros' }}
                            </span>
                            <span class="text-border">·</span>
                            <span class="tabular-nums">
                                <span class="font-medium text-foreground">{{ totalTallas }}</span>
                                {{ totalTallas === 1 ? 'talla única' : 'tallas únicas' }} en esta página
                            </span>
                            <span
                                v-if="hayFiltros"
                                class="inline-flex items-center gap-1 rounded-full border border-border bg-muted/60 px-2 py-0.5 text-[11px] font-medium text-foreground"
                            >
                                <Squares2X2Icon class="h-3 w-3" />
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
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <div class="flex flex-col gap-2.5 border-b border-border p-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="relative min-w-[220px] flex-1">
                            <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <input
                                v-model="params.search"
                                type="search"
                                aria-label="Buscar tallas"
                                placeholder="Buscar una talla…"
                                class="h-9 w-full rounded-md border border-input bg-background pl-9 pr-9 text-sm text-foreground shadow-sm placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30"
                            />
                            <button
                                v-if="params.search"
                                type="button"
                                @click="params.search = ''"
                                aria-label="Limpiar búsqueda"
                                class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-muted-foreground transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            >
                                <XMarkIcon class="h-3.5 w-3.5" />
                            </button>
                        </div>

                        <select v-model.number="params.perPage" aria-label="Registros por página" :class="selectClass">
                            <option v-for="n in [10, 25, 50, 100, 200]" :key="n" :value="n">{{ n }} / página</option>
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

                    <div class="mat-scroll flex items-center gap-1.5 overflow-x-auto pb-0.5" role="group" aria-label="Filtrar por tipo de talla">
                        <button
                            type="button"
                            @click="grupoFiltro = 'todas'"
                            :aria-pressed="grupoFiltro === 'todas'"
                            :class="chipClass(grupoFiltro === 'todas')"
                        >
                            Todas
                            <span class="tabular-nums opacity-70">{{ totalTallas }}</span>
                        </button>
                        <button
                            v-for="grupo in GRUPOS"
                            :key="grupo.id"
                            type="button"
                            @click="grupoFiltro = grupo.id"
                            :aria-pressed="grupoFiltro === grupo.id"
                            :class="chipClass(grupoFiltro === grupo.id)"
                        >
                            <span class="h-1.5 w-1.5 rounded-full" :class="grupo.dot"></span>
                            {{ grupo.labelCorto }}
                            <span class="tabular-nums opacity-70">{{ conteos[grupo.id] }}</span>
                        </button>
                    </div>
                </div>

                <div v-if="cargando" class="grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-6">
                    <div v-for="n in 12" :key="'skeleton-' + n" class="flex min-h-[104px] flex-col justify-between rounded-xl border border-border bg-card p-3.5">
                        <div class="h-7 w-16 animate-pulse rounded bg-muted"></div>
                        <div class="h-3 w-24 animate-pulse rounded bg-muted"></div>
                    </div>
                </div>

                <div v-else-if="gruposVisibles.length" class="mat-page flex flex-col gap-6 p-4">
                    <section v-for="grupo in gruposVisibles" :key="grupo.id">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <div class="flex min-w-0 items-center gap-2">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg border" :class="grupo.iconWrap">
                                    <component :is="grupo.icon" class="h-3.5 w-3.5" aria-hidden="true" />
                                </span>
                                <div class="min-w-0">
                                    <h2 class="truncate text-sm font-semibold text-foreground">{{ grupo.label }}</h2>
                                    <p class="truncate text-[11px] text-muted-foreground">{{ grupo.descripcion }}</p>
                                </div>
                            </div>
                            <span class="shrink-0 text-xs tabular-nums text-muted-foreground">{{ grupo.items.length }}</span>
                        </div>

                        <div class="grid gap-2.5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-6">
                            <article
                                v-for="item in grupo.items"
                                :key="item.talla"
                                class="group relative flex min-h-[104px] flex-col justify-between overflow-hidden rounded-xl border border-border bg-card p-3.5 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-foreground/20 hover:shadow-md"
                            >
                                <span class="absolute inset-x-0 top-0 h-1" :class="grupo.barra"></span>

                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="text-[10px] font-medium uppercase tracking-[0.12em] text-muted-foreground">Talla</p>
                                        <p class="mt-0.5 truncate text-2xl font-semibold tracking-tight text-foreground" :title="item.talla">
                                            {{ item.talla }}
                                        </p>
                                    </div>
                                    <span
                                        class="inline-flex h-6 min-w-6 shrink-0 items-center justify-center rounded-full border px-1.5 text-[10px] font-semibold tabular-nums"
                                        :class="grupo.badge"
                                        :title="`${item.count} ${item.count === 1 ? 'registro' : 'registros'}`"
                                    >
                                        {{ item.count }}
                                    </span>
                                </div>

                                <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-muted-foreground">
                                    <span>{{ item.count }} {{ item.count === 1 ? 'registro' : 'registros' }}</span>
                                    <span v-if="item.productos.size" class="inline-flex items-center gap-1">
                                        <CubeIcon class="h-3 w-3" />
                                        {{ item.productos.size }} {{ item.productos.size === 1 ? 'producto' : 'productos' }}
                                    </span>
                                    <span v-if="item.planificada > 0" class="inline-flex items-center gap-1">
                                        Plan: {{ item.planificada }}
                                    </span>
                                </div>
                            </article>
                        </div>
                    </section>
                </div>

                <div v-else class="px-6 py-16">
                    <div class="mx-auto flex max-w-sm flex-col items-center text-center">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full border border-border bg-muted/60">
                            <Squares2X2Icon v-if="hayFiltros" class="h-5 w-5 text-muted-foreground" />
                            <HashtagIcon v-else class="h-5 w-5 text-muted-foreground" />
                        </div>
                        <h3 class="mt-4 text-sm font-semibold text-foreground">
                            {{ hayFiltros ? 'No hay tallas que coincidan' : 'Aún no hay tallas registradas' }}
                        </h3>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{
                                hayFiltros
                                    ? 'Ajusta la búsqueda o limpia los filtros para ver más resultados.'
                                    : 'Las tallas aparecerán aquí cuando se registren productos con distribución por tallas.'
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
                </div>

                <DataPagination
                    v-if="!cargando"
                    :current-page="paginaActual"
                    :last-page="ultimaPagina"
                    :from="desde"
                    :to="hasta"
                    :total="totalRegistros"
                    :item-label="totalRegistros === 1 ? 'registro' : 'registros'"
                    :summary="`${totalTallas} ${totalTallas === 1 ? 'talla única' : 'tallas únicas'} en esta página`"
                    @page="cambiarPagina"
                />
            </div>
        </div>
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
