<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PrimaryButton from '@/components/PrimaryButton.vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { computed, reactive, ref, watch } from 'vue';

import Create from '@/pages/EmpresaExterna/Create.vue';
import Edit from '@/pages/EmpresaExterna/Edit.vue';
import Delete from '@/pages/EmpresaExterna/Delete.vue';

import {
    ArrowPathIcon,
    BuildingOffice2Icon,
    ChevronDoubleLeftIcon,
    ChevronDoubleRightIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    ChevronUpDownIcon,
    ChevronUpIcon,
    ChevronDownIcon,
    EllipsisVerticalIcon,
    FunnelIcon,
    MagnifyingGlassIcon,
    PencilIcon,
    PlusIcon,
    TrashIcon,
    XMarkIcon,
} from '@heroicons/vue/24/solid';

const props = defineProps({
    empresa_externas: { type: Array as () => any[], default: () => [] },
    total: Number,
    breadcrumbs: Array as () => Array<{ label?: string; href?: string }>,
    perPage: Number,
    title: String,
    numberPermissions: Number,
    losSelect: Object,
    titulos: Array as () => any[],
});

/* --------------------------------------------------------------------------
 * Tabla 100% en cliente: los datos llegan una sola vez y aquí se buscan,
 * ordenan y paginan sin volver al backend.
 * ------------------------------------------------------------------------ */

const filas = computed<any[]>(() => (props.empresa_externas as any[]) ?? []);

const busqueda = ref('');
const actividadFiltro = ref<string | 'todos'>('todos');
const sortKey = ref('nombre');
const sortDir = ref<'asc' | 'desc'>('asc');
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
    filas.value.map((e: any) => ({
        ...e,
        _busqueda: normalizar([e.nombre, e.actividad, e.direccion, e.numero_contacto].join(' ')),
    })),
);

const actividadesDisponibles = computed(() => {
    const grupos = new Map<string, { value: string; count: number }>();
    preparadas.value.forEach((e: any) => {
        const raw = String(e.actividad ?? '').trim();
        if (!raw) return;
        const clave = normalizar(raw);
        const prev = grupos.get(clave);
        if (prev) {
            prev.count++;
        } else {
            grupos.set(clave, { value: raw, count: 1 });
        }
    });
    return Array.from(grupos.values()).sort((a, b) => a.value.localeCompare(b.value, 'es'));
});

const filtradas = computed(() =>
    preparadas.value.filter((e: any) => {
        if (busqueda.value.trim() && !e._busqueda.includes(normalizar(busqueda.value))) {
            return false;
        }
        if (actividadFiltro.value !== 'todos' && normalizar(e.actividad) !== normalizar(actividadFiltro.value)) {
            return false;
        }
        return true;
    }),
);

const collator = new Intl.Collator('es', { numeric: true, sensitivity: 'base' });

const ordenadas = computed(() => {
    const dir = sortDir.value === 'asc' ? 1 : -1;
    const key = sortKey.value;

    return [...filtradas.value].sort((a: any, b: any) => {
        const va = a[key];
        const vb = b[key];
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
    let fin = Math.min(total, inicio + ventana - 1);
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

const hayFiltros = computed(() => busqueda.value.trim() !== '' || actividadFiltro.value !== 'todos');

const limpiarFiltros = () => {
    busqueda.value = '';
    actividadFiltro.value = 'todos';
};

watch([busqueda, actividadFiltro, porPagina], () => {
    pagina.value = 1;
});

watch(paginas, (n) => {
    if (pagina.value > n) pagina.value = n;
});

const chipClass = (activo: boolean) => [
    'inline-flex shrink-0 items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background',
    activo
        ? 'border-foreground/15 bg-foreground text-background shadow-sm'
        : 'border-border bg-card text-muted-foreground hover:bg-muted hover:text-foreground',
];

const columnas = [
    { key: 'nombre', label: 'Nombre', sortable: true, headerClass: 'min-w-[200px]', cellClass: 'min-w-[200px]' },
    { key: 'actividad', label: 'Actividad', sortable: true, headerClass: 'min-w-[160px]', cellClass: 'min-w-[160px]' },
    { key: 'direccion', label: 'Dirección', sortable: true, headerClass: 'hidden min-w-[180px] lg:table-cell', cellClass: 'hidden min-w-[180px] lg:table-cell' },
    { key: 'numero_contacto', label: 'Contacto', sortable: true, headerClass: 'min-w-[130px]', cellClass: 'min-w-[130px]' },
] as const;

const selectClass = 'h-9 rounded-md border border-input bg-background px-2 text-sm text-foreground shadow-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30';

/* ------------------------------- Acciones --------------------------------- */

const data = reactive({
    EmpresaExternao: null as any,
    createOpen: false,
    editOpen: false,
    deleteOpen: false,
});

const abrirCrear = () => (data.createOpen = true);

const accionFila = (id: string, empresa: any) => {
    data.EmpresaExternao = empresa;
    if (id === 'edit') data.editOpen = true;
    if (id === 'delete') data.deleteOpen = true;
};

/* -------------------------- Carga / recarga ------------------------------- */

const cargando = ref(false);
const rutaIndex = new URL(String(route('EmpresaExterna.index')), window.location.origin).pathname;

router.on('start', (event) => {
    const url = event.detail.visit.url;
    const pathname = url instanceof URL ? url.pathname : new URL(String(url), window.location.origin).pathname;
    if (pathname === rutaIndex) cargando.value = true;
});
router.on('finish', () => {
    cargando.value = false;
});

const recargar = () => {
    router.reload({ only: ['empresa_externas', 'total', 'losSelect'], preserveScroll: true });
};
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
                        <h1 class="text-2xl font-semibold tracking-tight text-foreground">{{ props.title || 'Empresas externas' }}</h1>
                        <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground" role="status" aria-live="polite">
                            <span class="tabular-nums">
                                <span class="font-medium text-foreground">{{ totalFiltrado }}</span>
                                de {{ filas.length }} empresas externas
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

                        <PrimaryButton v-if="can(['create EmpresaExterna'])" type="button" class="h-9" @click="abrirCrear">
                            <PlusIcon class="mr-1.5 h-4 w-4" />
                            Nueva empresa
                        </PrimaryButton>
                    </div>
                </div>
            </div>

            <!-- Superficie principal: barra de filtros + tabla + paginación -->
            <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <div class="flex flex-wrap items-center gap-2 border-b border-border p-3">
                    <div class="relative min-w-[220px] flex-1">
                        <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <input
                            v-model="busqueda"
                            type="search"
                            aria-label="Buscar empresas externas"
                            placeholder="Buscar nombre, actividad, dirección, contacto…"
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

                    <select v-model="actividadFiltro" aria-label="Filtrar por actividad" :class="[selectClass, 'max-w-[210px]']">
                        <option value="todos">Todas las actividades</option>
                        <option v-for="act in actividadesDisponibles" :key="act.value" :value="act.value">
                            {{ act.value }} ({{ act.count }})
                        </option>
                    </select>

                    <select v-model.number="porPagina" aria-label="Filas por página" :class="selectClass">
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

                <!-- Tabla -->
                <div class="ee-scroll max-h-[68vh] overflow-auto overscroll-contain">
                    <table class="w-full min-w-[720px] border-collapse text-sm">
                        <thead>
                            <tr>
                                <th
                                    v-for="col in columnas"
                                    :key="col.key"
                                    scope="col"
                                    :aria-sort="ariaSort(col.key)"
                                    :class="[
                                        'bg-muted px-3 py-2.5 text-left text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground',
                                        col.headerClass,
                                    ]"
                                >
                                    <button
                                        type="button"
                                        @click="ordenarPor(col.key)"
                                        class="group/sort inline-flex items-center gap-1 rounded-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                                    >
                                        <span>{{ col.label }}</span>
                                        <span class="text-muted-foreground/60 transition-colors group-hover/sort:text-foreground">
                                            <ChevronUpIcon v-if="indicador(col.key) === 'asc'" class="h-3 w-3" />
                                            <ChevronDownIcon v-else-if="indicador(col.key) === 'desc'" class="h-3 w-3" />
                                            <ChevronUpDownIcon v-else class="h-3 w-3 opacity-0 transition-opacity group-hover/sort:opacity-100" />
                                        </span>
                                    </button>
                                </th>

                                <th
                                    scope="col"
                                    class="sticky right-0 z-30 w-[72px] border-l border-border bg-muted px-2 py-2.5 text-right text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground"
                                >
                                    Acciones
                                </th>
                            </tr>
                        </thead>

                        <!-- Cargando -->
                        <tbody v-if="cargando">
                            <tr v-for="n in 6" :key="'skeleton-' + n" class="border-b border-border/60">
                                <td :colspan="columnas.length + 1" class="px-4 py-3">
                                    <div class="flex items-center gap-4">
                                        <div class="h-3.5 w-44 animate-pulse rounded bg-muted"></div>
                                        <div class="h-3.5 w-28 animate-pulse rounded bg-muted"></div>
                                        <div class="h-3.5 flex-1 animate-pulse rounded bg-muted"></div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>

                        <!-- Filas -->
                        <tbody v-else-if="visibles.length" :key="paginaActual" class="ee-page">
                            <tr
                                v-for="empresa in visibles"
                                :key="empresa.id"
                                class="group border-b border-border/60 transition-colors last:border-0 hover:bg-muted/40"
                            >
                                <td class="px-3 py-3 align-top">
                                    <div class="flex items-center gap-2">
                                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-300">
                                            <BuildingOffice2Icon class="h-4 w-4" />
                                        </span>
                                        <span class="truncate font-medium text-foreground" :title="empresa.nombre">{{ empresa.nombre || '—' }}</span>
                                    </div>
                                </td>

                                <td class="px-3 py-3 align-top">
                                    <span
                                        v-if="empresa.actividad"
                                        class="inline-flex items-center rounded-full border border-indigo-200 bg-indigo-50 px-2 py-0.5 text-[11px] font-medium text-indigo-700 dark:border-indigo-500/30 dark:bg-indigo-500/10 dark:text-indigo-300"
                                    >
                                        {{ empresa.actividad }}
                                    </span>
                                    <span v-else class="text-xs text-muted-foreground">—</span>
                                </td>

                                <td class="hidden px-3 py-3 align-top lg:table-cell">
                                    <span class="text-sm text-foreground">{{ empresa.direccion || '—' }}</span>
                                </td>

                                <td class="px-3 py-3 align-top">
                                    <span v-if="empresa.numero_contacto" class="tabular-nums text-sm text-foreground">{{ empresa.numero_contacto }}</span>
                                    <span v-else class="text-xs text-muted-foreground">—</span>
                                </td>

                                <td
                                    class="sticky right-0 z-10 w-[72px] border-l border-border/60 bg-card px-2 py-2 align-middle shadow-[-8px_0_16px_-16px_rgba(0,0,0,0.35)] transition-colors group-hover:bg-muted/40"
                                >
                                    <div class="flex items-center justify-end gap-1">
                                        <DropdownMenu>
                                            <DropdownMenuTrigger
                                                type="button"
                                                title="Acciones"
                                                :aria-label="`Acciones de ${empresa.nombre}`"
                                                class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                                            >
                                                <EllipsisVerticalIcon class="h-4 w-4" />
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" class="w-48 p-1">
                                                <DropdownMenuItem
                                                    v-if="can(['update EmpresaExterna'])"
                                                    @click="accionFila('edit', empresa)"
                                                    class="flex cursor-pointer items-center gap-2 px-2.5 py-1.5 text-xs font-medium"
                                                >
                                                    <PencilIcon class="h-4 w-4" />
                                                    <span>Editar</span>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    v-if="can(['delete EmpresaExterna'])"
                                                    @click="accionFila('delete', empresa)"
                                                    variant="destructive"
                                                    class="flex cursor-pointer items-center gap-2 px-2.5 py-1.5 text-xs font-medium"
                                                >
                                                    <TrashIcon class="h-4 w-4" />
                                                    <span>Eliminar</span>
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
                                            <BuildingOffice2Icon v-else class="h-5 w-5 text-muted-foreground" />
                                        </div>
                                        <h3 class="mt-4 text-sm font-semibold text-foreground">
                                            {{ hayFiltros ? 'No hay empresas que coincidan' : 'Aún no hay empresas externas' }}
                                        </h3>
                                        <p class="mt-1 text-sm text-muted-foreground">
                                            {{
                                                hayFiltros
                                                    ? 'Ajusta la búsqueda o limpia los filtros para ver más resultados.'
                                                    : 'Crea la primera empresa externa para poder asociarla a órdenes de producción.'
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
                                        <PrimaryButton v-else-if="can(['create EmpresaExterna'])" type="button" class="mt-4" @click="abrirCrear">
                                            <PlusIcon class="mr-1.5 h-4 w-4" />
                                            Crear la primera empresa
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

        <!-- Modales -->
        <Create
            v-if="can(['create EmpresaExterna'])"
            :numberPermissions="props.numberPermissions"
            :show="data.createOpen"
            @close="data.createOpen = false"
            :title="props.title"
        />

        <Edit
            v-if="can(['update EmpresaExterna'])"
            :titulos="props.titulos"
            :numberPermissions="props.numberPermissions"
            :show="data.editOpen"
            @close="data.editOpen = false"
            :EmpresaExternaa="data.EmpresaExternao"
            :title="props.title"
            :losSelect="props.losSelect"
        />

        <Delete
            v-if="can(['delete EmpresaExterna'])"
            :show="data.deleteOpen"
            @close="data.deleteOpen = false"
            :EmpresaExternaa="data.EmpresaExternao"
            :title="props.title"
        />
    </AppLayout>
</template>

<style scoped>
@keyframes ee-page-in {
    from {
        opacity: 0;
        transform: translateY(3px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.ee-page {
    animation: ee-page-in 180ms cubic-bezier(0.22, 1, 0.36, 1);
}

.ee-scroll {
    scrollbar-width: thin;
    scrollbar-color: var(--border) transparent;
}

.ee-scroll::-webkit-scrollbar {
    width: 10px;
    height: 10px;
}

.ee-scroll::-webkit-scrollbar-track {
    background: transparent;
}

.ee-scroll::-webkit-scrollbar-thumb {
    background: var(--border);
    border: 3px solid transparent;
    border-radius: 9999px;
    background-clip: content-box;
}

@media (prefers-reduced-motion: reduce) {
    .ee-page {
        animation: none;
    }
}
</style>
