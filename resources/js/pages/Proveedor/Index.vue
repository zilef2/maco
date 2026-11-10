<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';

import {
    ArrowUpTrayIcon,
    ChevronDownIcon,
    ChevronRightIcon,
    ChevronUpDownIcon,
    ChevronUpIcon,
    FunnelIcon,
    MagnifyingGlassIcon,
    PencilIcon,
    PlusIcon,
    TrashIcon,
    XMarkIcon,
} from '@heroicons/vue/24/solid';

import Create from '@/pages/Proveedor/Create.vue';
import Delete from '@/pages/Proveedor/Delete.vue';
import Edit from '@/pages/Proveedor/Edit.vue';
import ImportarProveedorModal from '@/pages/Proveedor/ImportarProveedorModal.vue';

const props = defineProps({
    proveedores: { type: Array as () => any[], default: () => [] },
    total: {
        type: Number,
        default: 0,
    },
    breadcrumbs: {
        type: Array as () => Array<{ label?: string; href?: string }>,
        default: () => [],
    },
    perPage: {
        type: Number,
        default: 10,
    },
    title: {
        type: String,
        default: 'Proveedores',
    },
    numberPermissions: {
        type: Number,
        default: 0,
    },
    opcionesSelect: {
        type: Object,
        default: () => ({
            tipo: ['Proveedor', 'Taller', 'Empresa externa'],
            tipo_proveedor: ['Materia prima', 'Servicio', 'Mixto', 'Otro'],
            estado: ['Activo', 'Inactivo'],
            forma_pago: ['Contado', 'Crédito 8 días', 'Crédito 15 días', 'Crédito 30 días', 'Anticipo', 'Otro'],
        }),
    },
});

const estadoPorDefecto = 'Activo';

const state = reactive({
    busqueda: '',
    tipoFichaFiltro: '',
    tipoFiltro: '',
    formaPagoFiltro: '',
    estadoFiltro: estadoPorDefecto,
    sortKey: 'updated_at',
    sortDir: 'desc' as 'asc' | 'desc',
    porPagina: props.perPage ?? 10,
    pagina: 1,
    proveedorSeleccionado: null as any,
    createOpen: false,
    editOpen: false,
    deleteOpen: false,
    importOpen: false,
});

const filas = computed<any[]>(() => props.proveedores ?? []);

const normalizar = (valor: any): string =>
    String(valor ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/\s+/g, ' ')
        .trim();

const preparadas = computed(() =>
    filas.value.map((proveedor: any) => ({
        ...proveedor,
        _tipo: proveedor.tipo || 'Proveedor',
        _busqueda: normalizar(
            [
                proveedor.codigo,
                proveedor.codigo_proveedor,
                proveedor.tipo || 'Proveedor',
                proveedor.nit,
                proveedor.nombre_comercial,
                proveedor.nombre,
                proveedor.razon_social,
                proveedor.correo_electronico,
                proveedor.ciudad,
                proveedor.direccion,
                proveedor.persona_contacto,
                proveedor.que_suministra,
            ].join(' '),
        ),
    })),
);

const baseSinEstado = computed(() =>
    preparadas.value.filter((proveedor: any) => {
        if (state.busqueda.trim() && !proveedor._busqueda.includes(normalizar(state.busqueda))) {
            return false;
        }
        if (state.tipoFichaFiltro && normalizar(proveedor._tipo) !== normalizar(state.tipoFichaFiltro)) {
            return false;
        }
        if (state.tipoFiltro && normalizar(proveedor.tipo_proveedor) !== normalizar(state.tipoFiltro)) {
            return false;
        }
        if (state.formaPagoFiltro && normalizar(proveedor.forma_pago) !== normalizar(state.formaPagoFiltro)) {
            return false;
        }
        return true;
    }),
);

const baseSinFicha = computed(() =>
    preparadas.value.filter((proveedor: any) => {
        if (state.busqueda.trim() && !proveedor._busqueda.includes(normalizar(state.busqueda))) {
            return false;
        }
        if (state.estadoFiltro && normalizar(proveedor.estado) !== normalizar(state.estadoFiltro)) {
            return false;
        }
        if (state.tipoFiltro && normalizar(proveedor.tipo_proveedor) !== normalizar(state.tipoFiltro)) {
            return false;
        }
        if (state.formaPagoFiltro && normalizar(proveedor.forma_pago) !== normalizar(state.formaPagoFiltro)) {
            return false;
        }
        return true;
    }),
);

const filtradas = computed(() =>
    baseSinEstado.value.filter((proveedor: any) => (state.estadoFiltro ? normalizar(proveedor.estado) === normalizar(state.estadoFiltro) : true)),
);

const collator = new Intl.Collator('es', { numeric: true, sensitivity: 'base' });

const ordenadas = computed(() => {
    const dir = state.sortDir === 'asc' ? 1 : -1;
    const key = state.sortKey;

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
const paginas = computed(() => Math.max(1, Math.ceil(totalFiltrado.value / state.porPagina)));
const paginaActual = computed(() => Math.min(state.pagina, paginas.value));
const desde = computed(() => (totalFiltrado.value === 0 ? 0 : (paginaActual.value - 1) * state.porPagina + 1));
const hasta = computed(() => Math.min(paginaActual.value * state.porPagina, totalFiltrado.value));

const visibles = computed(() => {
    const inicio = (paginaActual.value - 1) * state.porPagina;
    return ordenadas.value.slice(inicio, inicio + state.porPagina);
});

const etiquetasFicha: Record<string, string> = {
    Proveedor: 'Proveedores',
    Taller: 'Talleres',
    'Empresa externa': 'Empresas externas',
};

const opcionesFicha = computed(() => {
    const tipos = props.opcionesSelect?.tipo?.length ? props.opcionesSelect.tipo : ['Proveedor', 'Taller', 'Empresa externa'];

    return [
        { value: '', label: 'Todos', count: baseSinFicha.value.length },
        ...tipos.map((tipo: string) => ({
            value: tipo,
            label: etiquetasFicha[tipo] || tipo,
            count: baseSinFicha.value.filter((p: any) => normalizar(p._tipo) === normalizar(tipo)).length,
        })),
    ];
});

const opcionesEstado = computed(() => [
    { value: '', label: 'Ambos', count: baseSinEstado.value.length },
    {
        value: 'Activo',
        label: 'Activos',
        count: baseSinEstado.value.filter((p: any) => normalizar(p.estado) === 'activo').length,
    },
    {
        value: 'Inactivo',
        label: 'Inactivos',
        count: baseSinEstado.value.filter((p: any) => normalizar(p.estado) === 'inactivo').length,
    },
]);

const cambiarPagina = (p: number) => {
    state.pagina = Math.min(Math.max(1, p), paginas.value);
};

const ordenarPor = (campo: string) => {
    if (state.sortKey === campo) {
        state.sortDir = state.sortDir === 'asc' ? 'desc' : 'asc';
    } else {
        state.sortKey = campo;
        state.sortDir = 'asc';
    }
    state.pagina = 1;
};

const indicador = (campo: string) => (state.sortKey === campo ? state.sortDir : null);
const ariaSort = (campo: string): 'ascending' | 'descending' | 'none' =>
    state.sortKey !== campo ? 'none' : state.sortDir === 'asc' ? 'ascending' : 'descending';

const limpiarFiltros = () => {
    state.busqueda = '';
    state.tipoFichaFiltro = '';
    state.tipoFiltro = '';
    state.estadoFiltro = estadoPorDefecto;
    state.formaPagoFiltro = '';
};

const hayFiltros = computed(
    () =>
        state.busqueda.trim() !== '' ||
        state.tipoFichaFiltro !== '' ||
        state.tipoFiltro !== '' ||
        state.estadoFiltro !== estadoPorDefecto ||
        state.formaPagoFiltro !== '',
);

watch(
    () => [state.busqueda, state.tipoFichaFiltro, state.tipoFiltro, state.formaPagoFiltro, state.estadoFiltro, state.porPagina],
    () => {
        state.pagina = 1;
    },
);

watch(paginas, (n) => {
    if (state.pagina > n) state.pagina = n;
});

const selectClass =
    'h-9 rounded-md border border-input bg-background px-2.5 text-xs text-foreground shadow-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30';

const segmentoClass = (activo: boolean) => [
    'whitespace-nowrap border-l border-border px-2.5 text-xs font-medium transition first:border-l-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring',
    activo ? 'bg-foreground text-background shadow-sm' : 'text-muted-foreground hover:bg-muted hover:text-foreground',
];

const claseFicha = (tipo: string): string =>
    tipo === 'Taller'
        ? 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300'
        : tipo === 'Empresa externa'
          ? 'border-violet-200 bg-violet-50 text-violet-800 dark:border-violet-500/30 dark:bg-violet-500/10 dark:text-violet-300'
          : 'border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-300';

const abrirEditar = (proveedor: any) => {
    state.proveedorSeleccionado = proveedor;
    state.editOpen = true;
};

const abrirEliminar = (proveedor: any) => {
    state.proveedorSeleccionado = proveedor;
    state.deleteOpen = true;
};
</script>

<template>
    <Head :title="props.title" />

    <AppLayout>
        <div class="flex flex-col gap-4 p-4 sm:p-6">
            <div class="flex flex-col gap-3">
                <nav class="text-muted-foreground flex items-center gap-1.5 text-xs" aria-label="Ruta de navegación">
                    <Link
                        :href="route('dashboard')"
                        class="hover:text-foreground focus-visible:ring-ring rounded-sm transition-colors focus-visible:ring-2 focus-visible:outline-none"
                    >
                        Dashboard
                    </Link>
                    <template v-for="(miga, i) in props.breadcrumbs || []" :key="i">
                        <ChevronRightIcon class="h-3 w-3 opacity-60" />
                        <Link
                            v-if="miga.href"
                            :href="miga.href"
                            class="hover:text-foreground focus-visible:ring-ring rounded-sm transition-colors focus-visible:ring-2 focus-visible:outline-none"
                        >
                            {{ miga.label }}
                        </Link>
                        <span v-else class="text-foreground font-medium">{{ miga.label }}</span>
                    </template>
                </nav>

                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div class="min-w-0">
                        <h1 class="text-foreground text-2xl font-semibold tracking-tight">Proveedores y terceros</h1>
                        <p class="text-muted-foreground mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm" role="status" aria-live="polite">
                            <span class="tabular-nums">
                                <span class="text-foreground font-medium">{{ totalFiltrado }}</span>
                                de {{ filas.length }} {{ filas.length === 1 ? 'registro' : 'registros' }}
                            </span>
                            <span
                                v-if="hayFiltros"
                                class="border-border bg-muted/60 text-foreground inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[11px] font-medium"
                            >
                                <FunnelIcon class="h-3 w-3" />
                                Filtros activos
                            </span>
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <PrimaryButton
                            type="button"
                            class="h-9 bg-emerald-600 hover:bg-emerald-700 focus:ring-emerald-500"
                            @click="state.importOpen = true"
                        >
                            <ArrowUpTrayIcon class="mr-1.5 h-4 w-4" />
                            Cargar Excel
                        </PrimaryButton>

                        <PrimaryButton type="button" class="h-9" @click="state.createOpen = true">
                            <PlusIcon class="mr-1.5 h-4 w-4" />
                            Nuevo registro
                        </PrimaryButton>
                    </div>
                </div>
            </div>

            <div class="border-border bg-card overflow-hidden rounded-xl border shadow-sm">
                <div class="border-border flex flex-col gap-2.5 border-b p-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="relative min-w-[200px] flex-1">
                            <MagnifyingGlassIcon class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                            <input
                                v-model="state.busqueda"
                                type="search"
                                placeholder="Buscar por código, NIT, nombre, contacto, ciudad..."
                                class="border-input bg-background text-foreground placeholder:text-muted-foreground focus:border-ring focus:ring-ring/30 h-9 w-full rounded-md border pr-9 pl-9 text-xs shadow-sm focus:ring-2 focus:outline-none"
                            />
                            <button
                                v-if="state.busqueda"
                                type="button"
                                @click="state.busqueda = ''"
                                class="text-muted-foreground hover:bg-muted hover:text-foreground absolute top-1/2 right-2 -translate-y-1/2 rounded p-1"
                            >
                                <XMarkIcon class="h-3.5 w-3.5" />
                            </button>
                        </div>

                        <select v-model="state.tipoFiltro" :class="selectClass">
                            <option value="">Todos los tipos de proveedor</option>
                            <option v-for="opcion in props.opcionesSelect?.tipo_proveedor || []" :key="opcion" :value="opcion">
                                {{ opcion }}
                            </option>
                        </select>

                        <select v-model="state.formaPagoFiltro" :class="selectClass">
                            <option value="">Todas las formas de pago</option>
                            <option v-for="opcion in props.opcionesSelect?.forma_pago || []" :key="opcion" :value="opcion">
                                {{ opcion }}
                            </option>
                        </select>

                        <select v-model.number="state.porPagina" :class="selectClass">
                            <option v-for="n in [10, 25, 50, 100]" :key="n" :value="n">{{ n }} / pág</option>
                        </select>

                        <button
                            v-if="hayFiltros"
                            type="button"
                            @click="limpiarFiltros"
                            class="border-border bg-card text-foreground hover:bg-muted inline-flex h-9 items-center gap-1.5 rounded-md border px-2.5 text-xs font-medium shadow-sm transition"
                        >
                            <XMarkIcon class="h-3.5 w-3.5" />
                            Limpiar
                        </button>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <div class="flex items-center gap-1.5">
                            <span class="text-muted-foreground text-[11px] font-semibold tracking-wider uppercase">Ficha</span>
                            <div
                                class="border-border bg-card inline-flex h-9 items-stretch overflow-hidden rounded-md border shadow-sm"
                                role="group"
                                aria-label="Filtrar por ficha"
                            >
                                <button
                                    v-for="opcion in opcionesFicha"
                                    :key="opcion.value || 'todos'"
                                    type="button"
                                    :aria-pressed="state.tipoFichaFiltro === opcion.value"
                                    :class="segmentoClass(state.tipoFichaFiltro === opcion.value)"
                                    @click="state.tipoFichaFiltro = opcion.value"
                                >
                                    {{ opcion.label }}
                                    <span class="ml-1 tabular-nums opacity-70">{{ opcion.count }}</span>
                                </button>
                            </div>
                        </div>

                        <div class="flex items-center gap-1.5">
                            <span class="text-muted-foreground text-[11px] font-semibold tracking-wider uppercase">Estado</span>
                            <div
                                class="border-border bg-card inline-flex h-9 items-stretch overflow-hidden rounded-md border shadow-sm"
                                role="group"
                                aria-label="Filtrar proveedores por estado"
                            >
                                <button
                                    v-for="opcion in opcionesEstado"
                                    :key="opcion.value || 'ambos'"
                                    type="button"
                                    :aria-pressed="state.estadoFiltro === opcion.value"
                                    :class="segmentoClass(state.estadoFiltro === opcion.value)"
                                    @click="state.estadoFiltro = opcion.value"
                                >
                                    {{ opcion.label }}
                                    <span class="ml-1 tabular-nums opacity-70">{{ opcion.count }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="max-h-[68vh] overflow-auto overscroll-contain">
                    <table class="w-full min-w-[1320px] border-collapse text-sm">
                        <thead>
                            <tr class="bg-muted text-muted-foreground text-[11px] font-semibold tracking-[0.08em] uppercase">
                                <th scope="col" class="px-3 py-2.5 text-left" :aria-sort="ariaSort('codigo')">
                                    <button type="button" @click="ordenarPor('codigo')" class="hover:text-foreground inline-flex items-center gap-1">
                                        <span>Código</span>
                                        <ChevronUpIcon v-if="indicador('codigo') === 'asc'" class="h-3 w-3" />
                                        <ChevronDownIcon v-else-if="indicador('codigo') === 'desc'" class="h-3 w-3" />
                                        <ChevronUpDownIcon v-else class="h-3 w-3" />
                                    </button>
                                </th>
                                <th scope="col" class="px-3 py-2.5 text-left">Ficha</th>
                                <th scope="col" class="px-3 py-2.5 text-left" :aria-sort="ariaSort('nombre_comercial')">
                                    <button
                                        type="button"
                                        @click="ordenarPor('nombre_comercial')"
                                        class="hover:text-foreground inline-flex items-center gap-1"
                                    >
                                        <span>Proveedor / Razón Social</span>
                                        <ChevronUpIcon v-if="indicador('nombre_comercial') === 'asc'" class="h-3 w-3" />
                                        <ChevronDownIcon v-else-if="indicador('nombre_comercial') === 'desc'" class="h-3 w-3" />
                                        <ChevronUpDownIcon v-else class="h-3 w-3" />
                                    </button>
                                </th>
                                <th scope="col" class="px-3 py-2.5 text-left">NIT</th>
                                <th scope="col" class="px-3 py-2.5 text-left">Tipo proveedor</th>
                                <th scope="col" class="px-3 py-2.5 text-left">Qué suministra</th>
                                <th scope="col" class="px-3 py-2.5 text-left">Contacto / Celular</th>
                                <th scope="col" class="px-3 py-2.5 text-left">Ciudad / Dirección</th>
                                <th scope="col" class="px-3 py-2.5 text-left">Forma pago</th>
                                <th scope="col" class="px-3 py-2.5 text-left">Estado</th>
                                <th scope="col" class="border-border bg-muted sticky right-0 z-30 w-[90px] border-l px-2 py-2.5 text-right">
                                    Acciones
                                </th>
                            </tr>
                        </thead>

                        <tbody v-if="visibles.length">
                            <tr
                                v-for="proveedor in visibles"
                                :key="proveedor.id"
                                class="group border-border/60 hover:bg-muted/40 border-b transition-colors last:border-0"
                            >
                                <td class="text-foreground px-3 py-3 align-top font-mono text-xs font-semibold">
                                    {{ proveedor.codigo || '—' }}
                                </td>

                                <td class="px-3 py-3 align-top">
                                    <span
                                        class="inline-flex rounded-full border px-2 py-0.5 text-[11px] font-medium"
                                        :class="claseFicha(proveedor.tipo)"
                                    >
                                        {{ proveedor.tipo || 'Proveedor' }}
                                    </span>
                                </td>

                                <td class="px-3 py-3 align-top">
                                    <div class="flex max-w-[240px] flex-col gap-0.5">
                                        <span class="text-foreground truncate font-semibold" :title="proveedor.nombre_comercial || proveedor.nombre">
                                            {{ proveedor.nombre_comercial || proveedor.nombre || '—' }}
                                        </span>
                                        <span
                                            v-if="proveedor.razon_social"
                                            class="text-muted-foreground truncate text-[11px]"
                                            :title="proveedor.razon_social"
                                        >
                                            {{ proveedor.razon_social }}
                                        </span>
                                    </div>
                                </td>

                                <td class="text-muted-foreground px-3 py-3 align-top text-xs tabular-nums">
                                    {{ proveedor.nit || '—' }}
                                </td>

                                <td class="px-3 py-3 align-top">
                                    <span
                                        v-if="proveedor.tipo_proveedor"
                                        class="border-border bg-muted/60 text-foreground inline-flex rounded-full border px-2 py-0.5 text-[11px] font-medium"
                                    >
                                        {{ proveedor.tipo_proveedor }}
                                    </span>
                                    <span v-else class="text-muted-foreground text-xs">—</span>
                                </td>

                                <td class="px-3 py-3 align-top">
                                    <span class="text-muted-foreground block max-w-[180px] truncate text-xs" :title="proveedor.que_suministra">
                                        {{ proveedor.que_suministra || '—' }}
                                    </span>
                                </td>

                                <td class="px-3 py-3 align-top">
                                    <div class="flex max-w-[190px] flex-col gap-0.5 text-xs">
                                        <span class="text-foreground font-medium" :title="proveedor.persona_contacto">
                                            {{ proveedor.persona_contacto || '—' }}
                                        </span>
                                        <span class="text-muted-foreground">
                                            {{
                                                proveedor.celular_contacto ||
                                                proveedor.telefono_principal ||
                                                (proveedor.celular1 ? String(proveedor.celular1) : '') ||
                                                '—'
                                            }}
                                        </span>
                                    </div>
                                </td>

                                <td class="px-3 py-3 align-top">
                                    <div class="flex max-w-[190px] flex-col gap-0.5 text-xs">
                                        <span class="text-foreground font-medium">
                                            {{ proveedor.ciudad || '—' }}
                                        </span>
                                        <span class="text-muted-foreground truncate text-[11px]" :title="proveedor.direccion">
                                            {{ proveedor.direccion || '—' }}
                                        </span>
                                    </div>
                                </td>

                                <td class="text-foreground px-3 py-3 align-top text-xs">
                                    {{ proveedor.forma_pago || '—' }}
                                </td>

                                <td class="px-3 py-3 align-top">
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[11px] font-medium"
                                        :class="
                                            proveedor.estado === 'Activo'
                                                ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300'
                                                : 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-500/30 dark:bg-slate-500/10 dark:text-slate-300'
                                        "
                                    >
                                        <span
                                            class="h-1.5 w-1.5 rounded-full"
                                            :class="proveedor.estado === 'Activo' ? 'bg-emerald-500' : 'bg-slate-400'"
                                        />
                                        {{ proveedor.estado || 'Activo' }}
                                    </span>
                                </td>

                                <td
                                    class="border-border/60 bg-card group-hover:bg-muted/40 sticky right-0 z-10 w-[90px] border-l px-2 py-2 text-right align-middle"
                                >
                                    <div class="flex items-center justify-end gap-1">
                                        <button
                                            type="button"
                                            @click="abrirEditar(proveedor)"
                                            title="Editar"
                                            class="border-border bg-card text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:ring-ring inline-flex h-7 w-7 items-center justify-center rounded-md border shadow-sm transition focus-visible:ring-2 focus-visible:outline-none"
                                        >
                                            <PencilIcon class="h-3.5 w-3.5" />
                                        </button>
                                        <button
                                            type="button"
                                            @click="abrirEliminar(proveedor)"
                                            title="Eliminar"
                                            class="border-border bg-card text-destructive hover:bg-destructive/10 focus-visible:ring-ring inline-flex h-7 w-7 items-center justify-center rounded-md border shadow-sm transition focus-visible:ring-2 focus-visible:outline-none"
                                        >
                                            <TrashIcon class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>

                        <tbody v-else>
                            <tr>
                                <td colspan="11" class="text-muted-foreground px-6 py-16 text-center">
                                    <div class="mx-auto flex max-w-sm flex-col items-center">
                                        <FunnelIcon v-if="hayFiltros" class="text-muted-foreground/60 h-8 w-8" />
                                        <h3 class="text-foreground mt-3 text-sm font-semibold">
                                            {{ hayFiltros ? 'No hay registros que coincidan' : 'No hay terceros registrados' }}
                                        </h3>
                                        <p class="text-muted-foreground mt-1 text-xs">
                                            {{
                                                hayFiltros
                                                    ? 'Ajusta la búsqueda o limpia los filtros para ver más resultados.'
                                                    : 'Crea un proveedor, taller o empresa externa para poder asociarlo a la producción.'
                                            }}
                                        </p>
                                        <button
                                            v-if="hayFiltros"
                                            type="button"
                                            @click="limpiarFiltros"
                                            class="border-border bg-card text-foreground hover:bg-muted mt-4 inline-flex h-9 items-center gap-1.5 rounded-md border px-3 text-xs font-medium shadow-sm transition"
                                        >
                                            <XMarkIcon class="h-3.5 w-3.5" />
                                            Limpiar filtros
                                        </button>
                                        <PrimaryButton v-else type="button" class="mt-4" @click="state.createOpen = true">
                                            <PlusIcon class="mr-1.5 h-4 w-4" />
                                            Crear registro
                                        </PrimaryButton>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <DataPagination
                    :current-page="paginaActual"
                    :last-page="paginas"
                    :from="desde"
                    :to="hasta"
                    :total="totalFiltrado"
                    item-label="registros"
                    @page="cambiarPagina"
                />
            </div>
        </div>

        <Create :show="state.createOpen" :opcionesSelect="props.opcionesSelect" @close="state.createOpen = false" />

        <ImportarProveedorModal :show="state.importOpen" @close="state.importOpen = false" />

        <Edit
            :show="state.editOpen"
            :proveedora="state.proveedorSeleccionado"
            :opcionesSelect="props.opcionesSelect"
            @close="state.editOpen = false"
        />

        <Delete
            :show="state.deleteOpen"
            :Proveedora="state.proveedorSeleccionado"
            :title="state.proveedorSeleccionado?.tipo || 'Tercero'"
            @close="state.deleteOpen = false"
        />
    </AppLayout>
</template>
