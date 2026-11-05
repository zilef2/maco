<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import DataPagination from '@/components/DataPagination.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

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
import Edit from '@/pages/Proveedor/Edit.vue';
import Delete from '@/pages/Proveedor/Delete.vue';
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
            tipo_proveedor: ['Materia prima', 'Servicio', 'Mixto', 'Otro'],
            estado: ['Activo', 'Inactivo'],
            forma_pago: ['Contado', 'Crédito 8 días', 'Crédito 15 días', 'Crédito 30 días', 'Anticipo', 'Otro'],
        }),
    },
});

/* --------------------------------------------------------------------------
 * Tabla 100% en cliente: los proveedores llegan una sola vez y aquí se
 * buscan, filtran, ordenan y paginan sin volver al backend.
 * ------------------------------------------------------------------------ */

const filas = computed<any[]>(() => props.proveedores ?? []);

const busqueda = ref('');
const tipoFiltro = ref('');
const formaPagoFiltro = ref('');
// Con la lista sin filtrar en la URL se arranca viendo solo los activos.
const estadoPorDefecto = 'Activo';
const estadoFiltro = ref(estadoPorDefecto);

const sortKey = ref('updated_at');
const sortDir = ref<'asc' | 'desc'>('desc');
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
    filas.value.map((proveedor: any) => ({
        ...proveedor,
        _busqueda: normalizar(
            [
                proveedor.codigo_proveedor,
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

// Base del segmento de estado: todo menos el estado, para que los conteos no
// cambien al elegir una de sus opciones.
const baseSinEstado = computed(() =>
    preparadas.value.filter((proveedor: any) => {
        if (busqueda.value.trim() && !proveedor._busqueda.includes(normalizar(busqueda.value))) {
            return false;
        }
        if (tipoFiltro.value && normalizar(proveedor.tipo_proveedor) !== normalizar(tipoFiltro.value)) {
            return false;
        }
        if (formaPagoFiltro.value && normalizar(proveedor.forma_pago) !== normalizar(formaPagoFiltro.value)) {
            return false;
        }
        return true;
    }),
);

const filtradas = computed(() =>
    baseSinEstado.value.filter((proveedor: any) =>
        estadoFiltro.value ? normalizar(proveedor.estado) === normalizar(estadoFiltro.value) : true,
    ),
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
const desde = computed(() => (totalFiltrado.value === 0 ? 0 : (paginaActual.value - 1) * porPagina.value + 1));
const hasta = computed(() => Math.min(paginaActual.value * porPagina.value, totalFiltrado.value));

const visibles = computed(() => {
    const inicio = (paginaActual.value - 1) * porPagina.value;
    return ordenadas.value.slice(inicio, inicio + porPagina.value);
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
    pagina.value = Math.min(Math.max(1, p), paginas.value);
};

const ordenarPor = (campo: string) => {
    if (sortKey.value === campo) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = campo;
        sortDir.value = 'asc';
    }
    pagina.value = 1;
};

const indicador = (campo: string) => (sortKey.value === campo ? sortDir.value : null);
const ariaSort = (campo: string): 'ascending' | 'descending' | 'none' =>
    sortKey.value !== campo ? 'none' : sortDir.value === 'asc' ? 'ascending' : 'descending';

const limpiarFiltros = () => {
    busqueda.value = '';
    tipoFiltro.value = '';
    estadoFiltro.value = estadoPorDefecto;
    formaPagoFiltro.value = '';
};

const hayFiltros = computed(
    () => busqueda.value.trim() !== ''
        || tipoFiltro.value !== ''
        || estadoFiltro.value !== estadoPorDefecto
        || formaPagoFiltro.value !== '',
);

watch([busqueda, tipoFiltro, formaPagoFiltro, estadoFiltro, porPagina], () => {
    pagina.value = 1;
});

watch(paginas, (n) => {
    if (pagina.value > n) pagina.value = n;
});

const selectClass = 'h-9 rounded-md border border-input bg-background px-2.5 text-xs text-foreground shadow-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30';

const segmentoClass = (activo: boolean) => [
    'whitespace-nowrap border-l border-border px-2.5 text-xs font-medium transition first:border-l-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring',
    activo
        ? 'bg-foreground text-background shadow-sm'
        : 'text-muted-foreground hover:bg-muted hover:text-foreground',
];

const data = reactive({
    proveedorSeleccionado: null as any,
    createOpen: false,
    editOpen: false,
    deleteOpen: false,
    importOpen: false,
});

const abrirEditar = (proveedor: any) => {
    data.proveedorSeleccionado = proveedor;
    data.editOpen = true;
};

const abrirEliminar = (proveedor: any) => {
    data.proveedorSeleccionado = proveedor;
    data.deleteOpen = true;
};
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
                        <h1 class="text-2xl font-semibold tracking-tight text-foreground">Proveedores</h1>
                        <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground" role="status" aria-live="polite">
                            <span class="tabular-nums">
                                <span class="font-medium text-foreground">{{ totalFiltrado }}</span>
                                de {{ filas.length }} {{ filas.length === 1 ? 'proveedor' : 'proveedores' }}
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

                    <div class="flex items-center gap-2">
                        <PrimaryButton
                            type="button"
                            class="h-9 bg-emerald-600 hover:bg-emerald-700 focus:ring-emerald-500"
                            @click="data.importOpen = true"
                        >
                            <ArrowUpTrayIcon class="mr-1.5 h-4 w-4" />
                            Cargar Excel
                        </PrimaryButton>

                        <PrimaryButton type="button" class="h-9" @click="data.createOpen = true">
                            <PlusIcon class="mr-1.5 h-4 w-4" />
                            Nuevo proveedor
                        </PrimaryButton>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <div class="flex flex-col gap-2.5 border-b border-border p-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="relative min-w-[200px] flex-1">
                            <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <input
                                v-model="busqueda"
                                type="search"
                                placeholder="Buscar por código, NIT, nombre, contacto, ciudad..."
                                class="h-9 w-full rounded-md border border-input bg-background pl-9 pr-9 text-xs text-foreground shadow-sm placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30"
                            />
                            <button
                                v-if="busqueda"
                                type="button"
                                @click="busqueda = ''"
                                class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                            >
                                <XMarkIcon class="h-3.5 w-3.5" />
                            </button>
                        </div>

                        <select v-model="tipoFiltro" :class="selectClass">
                            <option value="">Todos los tipos</option>
                            <option
                                v-for="opcion in (props.opcionesSelect?.tipo_proveedor || [])"
                                :key="opcion"
                                :value="opcion"
                            >
                                {{ opcion }}
                            </option>
                        </select>

                        <select v-model="formaPagoFiltro" :class="selectClass">
                            <option value="">Todas las formas de pago</option>
                            <option
                                v-for="opcion in (props.opcionesSelect?.forma_pago || [])"
                                :key="opcion"
                                :value="opcion"
                            >
                                {{ opcion }}
                            </option>
                        </select>

                        <div class="flex items-center gap-1.5">
                            <span
                                class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Estado</span>
                            <div
                                class="inline-flex h-9 items-stretch overflow-hidden rounded-md border border-border bg-card shadow-sm"
                                role="group" aria-label="Filtrar proveedores por estado">
                                <button
                                    v-for="opcion in opcionesEstado"
                                    :key="opcion.value || 'ambos'"
                                    type="button"
                                    :aria-pressed="estadoFiltro === opcion.value"
                                    :class="segmentoClass(estadoFiltro === opcion.value)"
                                    @click="estadoFiltro = opcion.value"
                                >
                                    {{ opcion.label }}
                                    <span class="ml-1 tabular-nums opacity-70">{{ opcion.count }}</span>
                                </button>
                            </div>
                        </div>

                        <select v-model.number="porPagina" :class="selectClass">
                            <option v-for="n in [10, 25, 50, 100]" :key="n" :value="n">{{ n }} / pág</option>
                        </select>

                        <button
                            v-if="hayFiltros"
                            type="button"
                            @click="limpiarFiltros"
                            class="inline-flex h-9 items-center gap-1.5 rounded-md border border-border bg-card px-2.5 text-xs font-medium text-foreground shadow-sm transition hover:bg-muted"
                        >
                            <XMarkIcon class="h-3.5 w-3.5" />
                            Limpiar
                        </button>
                    </div>
                </div>

                <div class="max-h-[68vh] overflow-auto overscroll-contain">
                    <table class="w-full min-w-[1200px] border-collapse text-sm">
                        <thead>
                            <tr class="bg-muted text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground">
                                <th scope="col" class="px-3 py-2.5 text-left" :aria-sort="ariaSort('codigo_proveedor')">
                                    <button
                                        type="button"
                                        @click="ordenarPor('codigo_proveedor')"
                                        class="inline-flex items-center gap-1 hover:text-foreground"
                                    >
                                        <span>Código</span>
                                        <ChevronUpIcon v-if="indicador('codigo_proveedor') === 'asc'" class="h-3 w-3" />
                                        <ChevronDownIcon v-else-if="indicador('codigo_proveedor') === 'desc'" class="h-3 w-3" />
                                        <ChevronUpDownIcon v-else class="h-3 w-3" />
                                    </button>
                                </th>
                                <th scope="col" class="px-3 py-2.5 text-left" :aria-sort="ariaSort('nombre_comercial')">
                                    <button
                                        type="button"
                                        @click="ordenarPor('nombre_comercial')"
                                        class="inline-flex items-center gap-1 hover:text-foreground"
                                    >
                                        <span>Proveedor / Razón Social</span>
                                        <ChevronUpIcon v-if="indicador('nombre_comercial') === 'asc'" class="h-3 w-3" />
                                        <ChevronDownIcon v-else-if="indicador('nombre_comercial') === 'desc'" class="h-3 w-3" />
                                        <ChevronUpDownIcon v-else class="h-3 w-3" />
                                    </button>
                                </th>
                                <th scope="col" class="px-3 py-2.5 text-left">NIT</th>
                                <th scope="col" class="px-3 py-2.5 text-left">Tipo</th>
                                <th scope="col" class="px-3 py-2.5 text-left">Qué suministra</th>
                                <th scope="col" class="px-3 py-2.5 text-left">Contacto / Celular</th>
                                <th scope="col" class="px-3 py-2.5 text-left">Ciudad / Dirección</th>
                                <th scope="col" class="px-3 py-2.5 text-left">Forma pago</th>
                                <th scope="col" class="px-3 py-2.5 text-left">Estado</th>
                                <th scope="col" class="sticky right-0 z-30 w-[90px] border-l border-border bg-muted px-2 py-2.5 text-right">
                                    Acciones
                                </th>
                            </tr>
                        </thead>

                        <tbody v-if="visibles.length">
                            <tr
                                v-for="proveedor in visibles"
                                :key="proveedor.id"
                                class="group border-b border-border/60 transition-colors last:border-0 hover:bg-muted/40"
                            >
                                <td class="px-3 py-3 align-top font-mono text-xs font-semibold text-foreground">
                                    {{ proveedor.codigo_proveedor || `PRV-${proveedor.id}` }}
                                </td>

                                <td class="px-3 py-3 align-top">
                                    <div class="flex max-w-[240px] flex-col gap-0.5">
                                        <span class="truncate font-semibold text-foreground" :title="proveedor.nombre_comercial || proveedor.nombre">
                                            {{ proveedor.nombre_comercial || proveedor.nombre || '—' }}
                                        </span>
                                        <span v-if="proveedor.razon_social" class="truncate text-[11px] text-muted-foreground" :title="proveedor.razon_social">
                                            {{ proveedor.razon_social }}
                                        </span>
                                    </div>
                                </td>

                                <td class="px-3 py-3 align-top text-xs tabular-nums text-muted-foreground">
                                    {{ proveedor.nit || '—' }}
                                </td>

                                <td class="px-3 py-3 align-top">
                                    <span
                                        v-if="proveedor.tipo_proveedor"
                                        class="inline-flex rounded-full border border-border bg-muted/60 px-2 py-0.5 text-[11px] font-medium text-foreground"
                                    >
                                        {{ proveedor.tipo_proveedor }}
                                    </span>
                                    <span v-else class="text-xs text-muted-foreground">—</span>
                                </td>

                                <td class="px-3 py-3 align-top">
                                    <span
                                        class="block max-w-[180px] truncate text-xs text-muted-foreground"
                                        :title="proveedor.que_suministra"
                                    >
                                        {{ proveedor.que_suministra || '—' }}
                                    </span>
                                </td>

                                <td class="px-3 py-3 align-top">
                                    <div class="flex max-w-[190px] flex-col gap-0.5 text-xs">
                                        <span class="font-medium text-foreground" :title="proveedor.persona_contacto">
                                            {{ proveedor.persona_contacto || '—' }}
                                        </span>
                                        <span class="text-muted-foreground">
                                            {{ proveedor.celular_contacto || proveedor.telefono_principal || (proveedor.celular1 ? String(proveedor.celular1) : '') || '—' }}
                                        </span>
                                    </div>
                                </td>

                                <td class="px-3 py-3 align-top">
                                    <div class="flex max-w-[190px] flex-col gap-0.5 text-xs">
                                        <span class="font-medium text-foreground">
                                            {{ proveedor.ciudad || '—' }}
                                        </span>
                                        <span class="truncate text-[11px] text-muted-foreground" :title="proveedor.direccion">
                                            {{ proveedor.direccion || '—' }}
                                        </span>
                                    </div>
                                </td>

                                <td class="px-3 py-3 align-top text-xs text-foreground">
                                    {{ proveedor.forma_pago || '—' }}
                                </td>

                                <td class="px-3 py-3 align-top">
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[11px] font-medium"
                                        :class="proveedor.estado === 'Activo'
                                            ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300'
                                            : 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-500/30 dark:bg-slate-500/10 dark:text-slate-300'"
                                    >
                                        <span
                                            class="h-1.5 w-1.5 rounded-full"
                                            :class="proveedor.estado === 'Activo' ? 'bg-emerald-500' : 'bg-slate-400'"
                                        />
                                        {{ proveedor.estado || 'Activo' }}
                                    </span>
                                </td>

                                <td class="sticky right-0 z-10 w-[90px] border-l border-border/60 bg-card px-2 py-2 text-right align-middle group-hover:bg-muted/40">
                                    <div class="flex items-center justify-end gap-1">
                                        <button
                                            type="button"
                                            @click="abrirEditar(proveedor)"
                                            title="Editar"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        >
                                            <PencilIcon class="h-3.5 w-3.5" />
                                        </button>
                                        <button
                                            type="button"
                                            @click="abrirEliminar(proveedor)"
                                            title="Eliminar"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-destructive shadow-sm transition hover:bg-destructive/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        >
                                            <TrashIcon class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>

                        <tbody v-else>
                            <tr>
                                <td colspan="10" class="px-6 py-16 text-center text-muted-foreground">
                                    <div class="mx-auto flex max-w-sm flex-col items-center">
                                        <FunnelIcon v-if="hayFiltros" class="h-8 w-8 text-muted-foreground/60" />
                                        <h3 class="mt-3 text-sm font-semibold text-foreground">
                                            {{ hayFiltros ? 'No hay proveedores que coincidan' : 'No hay proveedores registrados' }}
                                        </h3>
                                        <p class="mt-1 text-xs text-muted-foreground">
                                            {{
                                                hayFiltros
                                                    ? 'Ajusta la búsqueda o limpia los filtros para ver más resultados.'
                                                    : 'Crea un nuevo proveedor para poder asociarlo a materiales y kits.'
                                            }}
                                        </p>
                                        <button
                                            v-if="hayFiltros"
                                            type="button"
                                            @click="limpiarFiltros"
                                            class="mt-4 inline-flex h-9 items-center gap-1.5 rounded-md border border-border bg-card px-3 text-xs font-medium text-foreground shadow-sm transition hover:bg-muted"
                                        >
                                            <XMarkIcon class="h-3.5 w-3.5" />
                                            Limpiar filtros
                                        </button>
                                        <PrimaryButton v-else type="button" class="mt-4" @click="data.createOpen = true">
                                            <PlusIcon class="mr-1.5 h-4 w-4" />
                                            Crear proveedor
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
                    item-label="proveedores"
                    @page="cambiarPagina"
                />
            </div>
        </div>

        <Create
            :show="data.createOpen"
            :opcionesSelect="props.opcionesSelect"
            @close="data.createOpen = false"
        />

        <ImportarProveedorModal :show="data.importOpen" @close="data.importOpen = false" />

        <Edit
            :show="data.editOpen"
            :proveedora="data.proveedorSeleccionado"
            :opcionesSelect="props.opcionesSelect"
            @close="data.editOpen = false"
        />

        <Delete
            :show="data.deleteOpen"
            :Proveedora="data.proveedorSeleccionado"
            title="Proveedor"
            @close="data.deleteOpen = false"
        />
    </AppLayout>
</template>
