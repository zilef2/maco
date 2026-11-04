<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import DataPagination from '@/components/DataPagination.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';
import pkg from 'lodash';

import {
    ChevronRightIcon,
    ChevronUpDownIcon,
    FunnelIcon,
    MagnifyingGlassIcon,
    PencilIcon,
    PlusIcon,
    TrashIcon,
    XMarkIcon,
} from '@heroicons/vue/24/solid';

import Create from '@/pages/Taller/Create.vue';
import Edit from '@/pages/Taller/Edit.vue';
import Delete from '@/pages/Taller/Delete.vue';

const { debounce, pickBy } = pkg;

const props = defineProps({
    fromController: { type: Object, default: () => ({ data: [] }) },
    total: { type: Number, default: 0 },
    filters: { type: Object, default: () => ({}) },
    breadcrumbs: { type: Array as () => Array<{ label?: string; href?: string }>, default: () => [] },
    perPage: { type: Number, default: 10 },
    title: { type: String, default: 'Taller' },
    numberPermissions: { type: Number, default: 0 },
    losSelect: { type: Object, default: () => ({}) },
    titulos: { type: Array as () => any[], default: () => [] },
    opcionesSelect: {
        type: Object,
        default: () => ({
            tipo_proveedor: ['Materia prima', 'Servicio', 'Mixto', 'Otro'],
            estado: ['Activo', 'Inactivo'],
        }),
    },
});

// Con la lista sin filtrar en la URL se arranca viendo solo los activos.
const estadoPorDefecto = 'Activo';

const opcionesEstado = [
    { value: '', label: 'Ambos' },
    { value: 'Activo', label: 'Activos' },
    { value: 'Inactivo', label: 'Inactivos' },
];

const data = reactive({
    params: {
        search: (props.filters?.search as string) ?? '',
        field: (props.filters?.field as string) ?? 'updated_at',
        order: (props.filters?.order as string) ?? 'desc',
        perPage: props.perPage ?? 10,
        tipo_proveedor: (props.filters?.tipo_proveedor as string) ?? '',
        estado: (props.filters?.estado as string) ?? estadoPorDefecto,
    },
    tallerSeleccionado: null as any,
    createOpen: false,
    editOpen: false,
    deleteOpen: false,
});

const selectClass =
    'h-9 rounded-md border border-input bg-background px-2.5 text-xs text-foreground shadow-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30';

const segmentoClass = (activo: boolean) => [
    'whitespace-nowrap border-l border-border px-2.5 text-xs font-medium transition first:border-l-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring',
    activo
        ? 'bg-foreground text-background shadow-sm'
        : 'text-muted-foreground hover:bg-muted hover:text-foreground',
];

const query = () => pickBy(data.params, (v) => v !== '' && v !== null && v !== undefined);

const aplicarFiltros = debounce(() => {
    router.get(route('Taller.index'), query(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}, 250);

watch(
    () => [data.params.search, data.params.perPage, data.params.tipo_proveedor, data.params.estado],
    () => aplicarFiltros(),
);

const ordenarPor = (campo: string) => {
    if (data.params.field === campo) {
        data.params.order = data.params.order === 'asc' ? 'desc' : 'asc';
    } else {
        data.params.field = campo;
        data.params.order = 'asc';
    }
    router.get(route('Taller.index'), query(), { preserveState: true, preserveScroll: true });
};

const cambiarPagina = (pagina: number) => {
    router.get(route('Taller.index'), { ...query(), page: pagina }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const limpiarFiltros = () => {
    data.params.search = '';
    data.params.tipo_proveedor = '';
    data.params.estado = estadoPorDefecto;
    aplicarFiltros();
};

const hayFiltros = computed(
    () => !!data.params.search
        || !!data.params.tipo_proveedor
        || data.params.estado !== estadoPorDefecto,
);

const abrirEditar = (taller: any) => {
    data.tallerSeleccionado = taller;
    data.editOpen = true;
};

const abrirEliminar = (taller: any) => {
    data.tallerSeleccionado = taller;
    data.deleteOpen = true;
};

const columnas = [
    { campo: 'codigo_taller', etiqueta: 'Código' },
    { campo: 'nombre_comercial', etiqueta: 'Taller / Razón social' },
    { campo: 'nit', etiqueta: 'NIT' },
    { campo: 'tipo_proveedor', etiqueta: 'Tipo' },
    { campo: 'que_suministra', etiqueta: 'Qué suministra' },
    { campo: 'persona_contacto', etiqueta: 'Contacto / Celular' },
    { campo: 'ciudad', etiqueta: 'Ciudad / Dirección' },
    { campo: 'forma_pago', etiqueta: 'Plazo de pago' },
    { campo: 'tiempo_entrega', etiqueta: 'Entrega' },
    { campo: 'estado', etiqueta: 'Estado' },
];
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
                        <h1 class="text-2xl font-semibold tracking-tight text-foreground">
                            {{ props.title || 'Talleres' }}
                        </h1>
                        <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground">
                            <span class="tabular-nums">
                                <span class="font-medium text-foreground">{{ props.total }}</span>
                                {{ props.total === 1 ? 'taller' : 'talleres' }}
                            </span>
                        </p>
                    </div>

                    <PrimaryButton
                        v-if="can(['create Taller'])"
                        type="button"
                        class="h-9"
                        @click="data.createOpen = true"
                    >
                        <PlusIcon class="mr-1.5 h-4 w-4" />
                        Nuevo taller
                    </PrimaryButton>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <div class="flex flex-wrap items-center gap-2 border-b border-border p-3">
                    <div class="relative min-w-[220px] flex-1">
                        <MagnifyingGlassIcon
                            class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <input
                            v-model="data.params.search"
                            type="search"
                            aria-label="Buscar talleres"
                            placeholder="Buscar por código, NIT, nombre, razón social, ciudad, contacto…"
                            class="h-9 w-full rounded-md border border-input bg-background pl-9 pr-9 text-xs text-foreground shadow-sm placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30"
                        />
                        <button
                            v-if="data.params.search"
                            type="button"
                            aria-label="Limpiar búsqueda"
                            class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                            @click="data.params.search = ''"
                        >
                            <XMarkIcon class="h-3.5 w-3.5" />
                        </button>
                    </div>

                    <select v-model="data.params.tipo_proveedor" aria-label="Filtrar por tipo" :class="selectClass">
                        <option value="">Todos los tipos</option>
                        <option v-for="opcion in (props.opcionesSelect?.tipo_proveedor || [])" :key="opcion" :value="opcion">
                            {{ opcion }}
                        </option>
                    </select>

                    <div class="flex items-center gap-1.5">
                        <span
                            class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Estado</span>
                        <div
                            class="inline-flex h-9 items-stretch overflow-hidden rounded-md border border-border bg-card shadow-sm"
                            role="group" aria-label="Filtrar talleres por estado">
                            <button
                                v-for="opcion in opcionesEstado"
                                :key="opcion.value || 'ambos'"
                                type="button"
                                :aria-pressed="data.params.estado === opcion.value"
                                :class="segmentoClass(data.params.estado === opcion.value)"
                                @click="data.params.estado = opcion.value"
                            >
                                {{ opcion.label }}
                            </button>
                        </div>
                    </div>

                    <select v-model.number="data.params.perPage" aria-label="Filas por página" :class="selectClass">
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

                <div class="max-h-[68vh] overflow-auto overscroll-contain">
                    <table class="w-full min-w-[1200px] border-collapse text-sm">
                        <thead>
                            <tr class="bg-muted text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground">
                                <th v-for="columna in columnas" :key="columna.campo" scope="col" class="px-3 py-2.5 text-left">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 hover:text-foreground"
                                        @click="ordenarPor(columna.campo)"
                                    >
                                        <span>{{ columna.etiqueta }}</span>
                                        <ChevronUpDownIcon class="h-3 w-3" />
                                    </button>
                                </th>
                                <th
                                    scope="col"
                                    class="sticky right-0 z-30 w-[90px] border-l border-border bg-muted px-2 py-2.5 text-right"
                                >
                                    Acciones
                                </th>
                            </tr>
                        </thead>

                        <tbody v-if="props.fromController?.data?.length">
                            <tr
                                v-for="taller in props.fromController.data"
                                :key="taller.id"
                                class="group border-b border-border/60 transition-colors last:border-0 hover:bg-muted/40"
                            >
                                <td class="px-3 py-3 align-top font-mono text-xs font-semibold text-foreground">
                                    {{ taller.codigo_taller || `TAL-${taller.id}` }}
                                </td>

                                <td class="px-3 py-3 align-top">
                                    <div class="flex max-w-[240px] flex-col gap-0.5">
                                        <span
                                            class="truncate font-semibold text-foreground"
                                            :title="taller.nombre_comercial || taller.nombre"
                                        >
                                            {{ taller.nombre_comercial || taller.nombre || '—' }}
                                        </span>
                                        <span
                                            v-if="taller.razon_social"
                                            class="truncate text-[11px] text-muted-foreground"
                                            :title="taller.razon_social"
                                        >
                                            {{ taller.razon_social }}
                                        </span>
                                    </div>
                                </td>

                                <td class="px-3 py-3 align-top text-xs tabular-nums text-muted-foreground">
                                    {{ taller.nit || '—' }}
                                </td>

                                <td class="px-3 py-3 align-top">
                                    <span
                                        v-if="taller.tipo_proveedor"
                                        class="inline-flex rounded-full border border-border bg-muted/60 px-2 py-0.5 text-[11px] font-medium text-foreground"
                                    >
                                        {{ taller.tipo_proveedor }}
                                    </span>
                                    <span v-else class="text-xs text-muted-foreground">—</span>
                                </td>

                                <td class="px-3 py-3 align-top">
                                    <span class="block max-w-[180px] truncate text-xs text-muted-foreground" :title="taller.que_suministra">
                                        {{ taller.que_suministra || '—' }}
                                    </span>
                                </td>

                                <td class="px-3 py-3 align-top">
                                    <div class="flex max-w-[190px] flex-col gap-0.5 text-xs">
                                        <span class="font-medium text-foreground" :title="taller.persona_contacto">
                                            {{ taller.persona_contacto || '—' }}
                                        </span>
                                        <span class="text-muted-foreground">
                                            {{ taller.celular_contacto || taller.telefono_principal || '—' }}
                                        </span>
                                    </div>
                                </td>

                                <td class="px-3 py-3 align-top">
                                    <div class="flex max-w-[190px] flex-col gap-0.5 text-xs">
                                        <span class="font-medium text-foreground">{{ taller.ciudad || '—' }}</span>
                                        <span class="truncate text-[11px] text-muted-foreground" :title="taller.direccion">
                                            {{ taller.direccion || '—' }}
                                        </span>
                                    </div>
                                </td>

                                <td class="px-3 py-3 align-top text-xs text-foreground">{{ taller.forma_pago || '—' }}</td>

                                <td class="px-3 py-3 align-top text-xs text-muted-foreground">
                                    {{ taller.tiempo_entrega || '—' }}
                                </td>

                                <td class="px-3 py-3 align-top">
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[11px] font-medium"
                                        :class="taller.estado === 'Inactivo'
                                            ? 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-500/30 dark:bg-slate-500/10 dark:text-slate-300'
                                            : 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300'"
                                    >
                                        <span
                                            class="h-1.5 w-1.5 rounded-full"
                                            :class="taller.estado === 'Inactivo' ? 'bg-slate-400' : 'bg-emerald-500'"
                                        />
                                        {{ taller.estado || 'Activo' }}
                                    </span>
                                </td>

                                <td class="sticky right-0 z-10 w-[90px] border-l border-border/60 bg-card px-2 py-2 text-right align-middle group-hover:bg-muted/40">
                                    <div class="flex items-center justify-end gap-1">
                                        <button
                                            v-if="can(['update Taller'])"
                                            type="button"
                                            title="Editar"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                            @click="abrirEditar(taller)"
                                        >
                                            <PencilIcon class="h-3.5 w-3.5" />
                                        </button>
                                        <button
                                            v-if="can(['delete Taller'])"
                                            type="button"
                                            title="Eliminar"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-destructive shadow-sm transition hover:bg-destructive/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                            @click="abrirEliminar(taller)"
                                        >
                                            <TrashIcon class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>

                        <tbody v-else>
                            <tr>
                                <td :colspan="columnas.length + 1" class="px-6 py-16 text-center text-muted-foreground">
                                    <div class="mx-auto flex max-w-sm flex-col items-center">
                                        <FunnelIcon class="h-8 w-8 text-muted-foreground/60" />
                                        <h3 class="mt-3 text-sm font-semibold text-foreground">
                                            {{ hayFiltros
                                                ? 'Ningún taller coincide con los filtros'
                                                : 'No hay talleres registrados' }}
                                        </h3>
                                        <p class="mt-1 text-xs text-muted-foreground">
                                            Ajuste la búsqueda o cree un nuevo taller.
                                        </p>
                                        <PrimaryButton
                                            v-if="can(['create Taller'])"
                                            type="button"
                                            class="mt-4"
                                            @click="data.createOpen = true"
                                        >
                                            <PlusIcon class="mr-1.5 h-4 w-4" />
                                            Crear taller
                                        </PrimaryButton>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <DataPagination
                    :current-page="props.fromController?.current_page || 1"
                    :last-page="props.fromController?.last_page || 1"
                    :from="props.fromController?.from || 0"
                    :to="props.fromController?.to || 0"
                    :total="props.fromController?.total || 0"
                    item-label="talleres"
                    @page="cambiarPagina"
                />
            </div>
        </div>

        <Create
            v-if="can(['create Taller'])"
            :show="data.createOpen"
            :title="props.title"
            :opcionesSelect="props.opcionesSelect"
            @close="data.createOpen = false"
        />

        <Edit
            v-if="can(['update Taller'])"
            :show="data.editOpen"
            :title="props.title"
            :tallera="data.tallerSeleccionado"
            :opcionesSelect="props.opcionesSelect"
            @close="data.editOpen = false"
        />

        <Delete
            v-if="can(['delete Taller'])"
            :show="data.deleteOpen"
            :title="props.title"
            :Tallera="data.tallerSeleccionado"
            @close="data.deleteOpen = false"
        />
    </AppLayout>
</template>
