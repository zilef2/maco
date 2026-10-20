<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import Breadcrumb from '@/components/Breadcrumb.vue';
import TextInput from '@/components/TextInput.vue';
import SelectInput from '@/components/SelectInput.vue';
import DangerButton from '@/components/DangerButton.vue';
import InfoButton from '@/components/InfoButton.vue';
import Pagination from '@/components/Pagination.vue';
import { computed, reactive, watch } from 'vue';
import {
    ChartBarIcon,
    ChevronDownIcon,
    ChevronUpDownIcon,
    ChevronUpIcon,
    ExclamationCircleIcon,
    InboxIcon,
    InformationCircleIcon,
    PencilIcon,
    PlusIcon,
    Squares2X2Icon,
    TrashIcon,
} from '@heroicons/vue/24/solid';
import Edit from '@/pages/KitProceso/Edit.vue';
import Delete from '@/pages/KitProceso/Delete.vue';
import ResumenEstados from '@/pages/KitProceso/ResumenEstados.vue';
import TotalesPorUnidad from '@/pages/KitProceso/TotalesPorUnidad.vue';
import { zilef_nuber_format } from '@/global.ts';
import pkg from 'lodash';

const { _, debounce, pickBy } = pkg;

const props = defineProps({
    fromController: Object,
    total: Number,
    filters: Object,
    breadcrumbs: Object,
    perPage: Number,
    title: String,
    numberPermissions: Number,
    titulos: Array,
    resumen: {
        type: Object,
        default: () => ({
            registros: 0,
            listos: 0,
            estados: [],
            porUnidad: [],
        }),
    },
    resumenPorOp: { type: Array, default: () => [] },
    ordenes: { type: Array, default: () => [] },
    proveedores: { type: Array, default: () => [] },
    estadosRequeridos: { type: Array, default: () => [] },
});

const defaultPerPage = [
    { label: '5', value: 5 },
    { label: '10', value: 10 },
    { label: '25', value: 25 },
    { label: '50', value: 50 },
    { label: '100', value: 100 },
];

const data = reactive({
    params: {
        search: props.filters?.search ?? '',
        field: props.filters?.field ?? '',
        order: props.filters?.order ?? '',
        orden_produccion_id: props.filters?.orden_produccion_id ?? '',
        perPage: props.perPage ?? 10,
    },
    KitProcesoo: null as any,
    editOpen: false,
    deleteOpen: false,
    dataSet: (usePage().props as any)?.app?.perpage ?? defaultPerPage,
});

const opSeleccionada = computed(() =>
    props.ordenes.find((op: any) => String(op.id) === String(data.params.orden_produccion_id)) ?? null,
);

const hayFiltros = computed(() => Boolean(data.params.search || data.params.orden_produccion_id));

const order = (field: string) => {
    data.params.order = data.params.field === field && data.params.order === 'asc' ? 'desc' : 'asc';
    data.params.field = field;
};

const iconoOrden = (field: string) => {
    if (data.params.field !== field) return ChevronUpDownIcon;
    return data.params.order === 'asc' ? ChevronUpIcon : ChevronDownIcon;
};

const cantidad = (valor: any) => {
    if (valor === null || valor === undefined || valor === '') return '—';
    return zilef_nuber_format(valor, 2, false);
};

const abrirEditar = (kit: any) => {
    data.KitProcesoo = kit;
    data.editOpen = true;
};

const abrirEliminar = (kit: any) => {
    data.KitProcesoo = kit;
    data.deleteOpen = true;
};

const limpiarFiltros = () => {
    data.params.search = '';
    data.params.orden_produccion_id = '';
    data.params.field = '';
    data.params.order = '';
};

watch(
    () => _.cloneDeep(data.params),
    debounce(() => {
        router.get(route('KitProceso.index'), pickBy(data.params), {
            replace: true,
            preserveState: true,
            preserveScroll: true,
        });
    }, 200),
);
</script>

<template>
    <Head :title="props.title" />

    <AppLayout>
        <Breadcrumb :title="title" :breadcrumbs="breadcrumbs" class="capitalize text-xl font-bold" />

        <div class="space-y-4">
            <Edit v-if="can(['update KitProceso'])" :show="data.editOpen" @close="data.editOpen = false"
                :KitProcesoa="data.KitProcesoo" :proveedores="props.proveedores"
                :estadosRequeridos="props.estadosRequeridos" :title="props.title" />

            <Delete v-if="can(['delete KitProceso'])" :show="data.deleteOpen" @close="data.deleteOpen = false"
                :KitProcesoa="data.KitProcesoo" :title="props.title" />

            <div class="space-y-4 px-4 sm:px-0">
                <!-- Aviso: los kits nacen con la OP, no se crean a mano. -->
                <div
                    class="rounded-xl border border-indigo-100 bg-indigo-50/70 px-4 py-3 text-sm text-indigo-950 dark:border-indigo-500/20 dark:bg-indigo-500/10 dark:text-indigo-100">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="flex items-start gap-2">
                            <InformationCircleIcon
                                class="mt-0.5 h-4 w-4 shrink-0 text-indigo-500 dark:text-indigo-300" />
                            <span>
                                Los kits de proceso se generan automáticamente al crear una orden de producción.
                                Aquí completas el <b>estado requerido</b>, el <b>proveedor</b> y la
                                <b>cantidad preparada</b> de cada material.
                            </span>
                        </p>
                        <Link v-if="can(['create OrdenProduccion'])" :href="route('OrdenProduccion.index')"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
                            <PlusIcon class="h-4 w-4" />
                            Ir a Nueva OP
                        </Link>
                    </div>
                </div>

                <!-- Resumen por OP: se muestra al ver todas las órdenes. -->
                <section v-if="!data.params.orden_produccion_id && props.resumenPorOp.length > 1"
                    class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 dark:border-gray-700/70">
                        <div>
                            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                Resumen por orden de producción
                            </h2>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                Cantidades y estados requeridos de cada OP. Haz clic en una OP para filtrar la tabla.
                            </p>
                        </div>
                        <span
                            class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                            {{ props.resumenPorOp.length }} OP
                        </span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[920px] text-sm">
                            <thead
                                class="text-[11px] font-semibold uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">
                                <tr class="border-b border-gray-100 text-left dark:border-gray-700/70">
                                    <th class="px-4 py-2.5">OP</th>
                                    <th class="px-3 py-2.5 text-center">Kits</th>
                                    <th class="px-3 py-2.5 text-center">Listos</th>
                                    <th class="px-3 py-2.5 text-right">Requerida</th>
                                    <th class="px-3 py-2.5 text-right">Disponible</th>
                                    <th class="px-3 py-2.5 text-right">Preparada</th>
                                    <th class="px-3 py-2.5 text-right">Falta</th>
                                    <th class="px-3 py-2.5">Estados requeridos</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="op in props.resumenPorOp" :key="op.orden_produccion_id"
                                    class="border-b border-gray-100 last:border-0 hover:bg-gray-50/70 dark:border-gray-700/50 dark:hover:bg-gray-900/30">
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <button type="button" @click="data.params.orden_produccion_id = op.orden_produccion_id"
                                            class="inline-flex items-center rounded-md bg-indigo-50 px-2 py-1 text-xs font-bold text-indigo-700 transition-colors hover:bg-indigo-100 dark:bg-indigo-500/15 dark:text-indigo-300 dark:hover:bg-indigo-500/25"
                                            title="Filtrar por esta OP">
                                            {{ op.numero_op }}
                                        </button>
                                    </td>
                                    <td class="px-3 py-3 text-center tabular-nums text-gray-700 dark:text-gray-200">
                                        {{ op.registros }}
                                    </td>
                                    <td class="px-3 py-3 text-center">
                                        <span
                                            class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-bold tabular-nums"
                                            :class="op.registros > 0 && op.listos === op.registros
                                                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300'
                                                : 'bg-gray-100 text-gray-500 dark:bg-gray-700/50 dark:text-gray-400'">
                                            {{ op.listos }} / {{ op.registros }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-3 text-right">
                                        <TotalesPorUnidad :unidades="op.porUnidad" campo="requerida" tono="suave" />
                                    </td>
                                    <td class="px-3 py-3 text-right">
                                        <TotalesPorUnidad :unidades="op.porUnidad" campo="disponible" tono="suave" />
                                    </td>
                                    <td class="px-3 py-3 text-right">
                                        <TotalesPorUnidad :unidades="op.porUnidad" campo="preparada" tono="suave" />
                                    </td>
                                    <td class="px-3 py-3 text-right">
                                        <TotalesPorUnidad :unidades="op.porUnidad" campo="falta" tono="suave" />
                                    </td>
                                    <td class="px-3 py-3">
                                        <ResumenEstados :estados="op.estados" />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Filtros -->
                <div
                    class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <div class="flex items-center gap-2">
                            <label for="kit-op-filter"
                                class="text-[11px] font-semibold uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">
                                OP
                            </label>
                            <select id="kit-op-filter" v-model="data.params.orden_produccion_id"
                                class="rounded-md border border-gray-300 bg-white py-2 pl-3 pr-8 text-sm text-gray-700 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
                                <option value="">Todas las OP</option>
                                <option v-for="op in props.ordenes" :key="op.id" :value="op.id">
                                    {{ op.numero_op || 'OP #' + op.id }}
                                </option>
                            </select>
                        </div>

                        <div class="flex items-center gap-2">
                            <label for="kit-per-page"
                                class="text-[11px] font-semibold uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">
                                Filas
                            </label>
                            <SelectInput id="kit-per-page" v-model="data.params.perPage" :dataSet="data.dataSet" />
                        </div>
                    </div>

                    <TextInput v-model="data.params.search" type="text"
                        class="block w-full rounded-lg lg:w-96"
                        placeholder="Buscar OP, material, estado o proveedor…" />
                </div>

                <!-- Tabla principal -->
                <div
                    class="relative overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div v-if="props.total && props.total > 0" class="overflow-x-auto">
                        <table class="w-full min-w-[1120px] text-sm">
                            <thead
                                class="border-b border-gray-200 bg-gray-50/80 text-[11px] font-semibold uppercase tracking-[0.08em] text-gray-500 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400">
                                <tr class="text-left">
                                    <th class="cursor-pointer select-none px-3 py-3 hover:text-gray-700 dark:hover:text-gray-200"
                                        @click="order('orden_produccion_id')">
                                        <span class="inline-flex items-center gap-1">
                                            OP
                                            <component :is="iconoOrden('orden_produccion_id')" class="h-3.5 w-3.5"
                                                :class="{ 'opacity-40': data.params.field !== 'orden_produccion_id' }" />
                                        </span>
                                    </th>
                                    <th class="cursor-pointer select-none px-3 py-3 hover:text-gray-700 dark:hover:text-gray-200"
                                        @click="order('material_id')">
                                        <span class="inline-flex items-center gap-1">
                                            Material
                                            <component :is="iconoOrden('material_id')" class="h-3.5 w-3.5"
                                                :class="{ 'opacity-40': data.params.field !== 'material_id' }" />
                                        </span>
                                    </th>
                                    <th class="cursor-pointer select-none px-3 py-3 hover:text-gray-700 dark:hover:text-gray-200"
                                        @click="order('estado_requerido')">
                                        <span class="inline-flex items-center gap-1">
                                            Estado requerido
                                            <component :is="iconoOrden('estado_requerido')" class="h-3.5 w-3.5"
                                                :class="{ 'opacity-40': data.params.field !== 'estado_requerido' }" />
                                        </span>
                                    </th>
                                    <th class="cursor-pointer select-none px-3 py-3 hover:text-gray-700 dark:hover:text-gray-200"
                                        @click="order('proveedor_id')">
                                        <span class="inline-flex items-center gap-1">
                                            Proveedor
                                            <component :is="iconoOrden('proveedor_id')" class="h-3.5 w-3.5"
                                                :class="{ 'opacity-40': data.params.field !== 'proveedor_id' }" />
                                        </span>
                                    </th>
                                    <th class="cursor-pointer select-none px-3 py-3 hover:text-gray-700 dark:hover:text-gray-200"
                                        @click="order('unidad')">
                                        <span class="inline-flex items-center gap-1">
                                            Unidad
                                            <component :is="iconoOrden('unidad')" class="h-3.5 w-3.5"
                                                :class="{ 'opacity-40': data.params.field !== 'unidad' }" />
                                        </span>
                                    </th>
                                    <th class="cursor-pointer select-none px-3 py-3 text-right hover:text-gray-700 dark:hover:text-gray-200"
                                        @click="order('cantidad_requerida')">
                                        <span class="inline-flex items-center gap-1">
                                            Requerida
                                            <component :is="iconoOrden('cantidad_requerida')" class="h-3.5 w-3.5"
                                                :class="{ 'opacity-40': data.params.field !== 'cantidad_requerida' }" />
                                        </span>
                                    </th>
                                    <th class="px-3 py-3 text-center">Listo</th>
                                    <th class="cursor-pointer select-none px-3 py-3 text-right hover:text-gray-700 dark:hover:text-gray-200"
                                        @click="order('cantidad_disponible')">
                                        <span class="inline-flex items-center gap-1">
                                            Disponible
                                            <component :is="iconoOrden('cantidad_disponible')" class="h-3.5 w-3.5"
                                                :class="{ 'opacity-40': data.params.field !== 'cantidad_disponible' }" />
                                        </span>
                                    </th>
                                    <th class="cursor-pointer select-none px-3 py-3 text-right hover:text-gray-700 dark:hover:text-gray-200"
                                        @click="order('cantidad_preparada')">
                                        <span class="inline-flex items-center gap-1">
                                            Preparada
                                            <component :is="iconoOrden('cantidad_preparada')" class="h-3.5 w-3.5"
                                                :class="{ 'opacity-40': data.params.field !== 'cantidad_preparada' }" />
                                        </span>
                                    </th>
                                    <th class="cursor-pointer select-none px-3 py-3 text-right hover:text-gray-700 dark:hover:text-gray-200"
                                        @click="order('falta')">
                                        <span class="inline-flex items-center gap-1">
                                            Falta
                                            <component :is="iconoOrden('falta')" class="h-3.5 w-3.5"
                                                :class="{ 'opacity-40': data.params.field !== 'falta' }" />
                                        </span>
                                    </th>
                                    <th class="px-3 py-3">Observaciones</th>
                                    <th class="px-3 py-3 text-right">Acciones</th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr v-for="kit in props.fromController?.data" :key="kit.id"
                                    class="border-t border-gray-100 transition-colors hover:bg-amber-50/40 dark:border-gray-700/60 dark:hover:bg-gray-900/30">
                                    <td class="px-3 py-3 whitespace-nowrap">
                                        <span
                                            class="inline-flex items-center rounded-md bg-indigo-50 px-2 py-1 text-xs font-bold text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300">
                                            {{ kit.orden_produccion_numero || 'OP #' + kit.orden_produccion_id }}
                                        </span>
                                    </td>

                                    <td class="px-3 py-3">
                                        <div class="min-w-[11rem] max-w-[16rem]">
                                            <p class="font-medium text-gray-900 dark:text-gray-100">
                                                {{ kit.material_nombre || '—' }}
                                            </p>
                                            <p v-if="kit.material_codigo"
                                                class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                                {{ kit.material_codigo }}
                                            </p>
                                        </div>
                                    </td>

                                    <td class="px-3 py-3">
                                        <span v-if="kit.estado_requerido"
                                            class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700 dark:bg-gray-700/60 dark:text-gray-200">
                                            {{ kit.estado_requerido }}
                                        </span>
                                        <span v-else
                                            class="inline-flex items-center gap-1 rounded-full border border-dashed border-amber-300 bg-amber-50/70 px-2.5 py-1 text-xs font-semibold text-amber-800 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200">
                                            <ExclamationCircleIcon class="h-3.5 w-3.5" />
                                            Sin estado
                                        </span>
                                    </td>

                                    <td class="px-3 py-3">
                                        <span v-if="kit.proveedor_nombre"
                                            class="text-sm text-gray-700 dark:text-gray-200">
                                            {{ kit.proveedor_nombre }}
                                        </span>
                                        <span v-else
                                            class="inline-flex items-center gap-1 rounded-full border border-dashed border-amber-300 bg-amber-50/70 px-2.5 py-1 text-xs font-semibold text-amber-800 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200">
                                            <ExclamationCircleIcon class="h-3.5 w-3.5" />
                                            Sin proveedor
                                        </span>
                                    </td>

                                    <td class="px-3 py-3 whitespace-nowrap text-gray-700 dark:text-gray-200">
                                        {{ kit.unidad || '—' }}
                                    </td>

                                    <td
                                        class="px-3 py-3 text-right tabular-nums whitespace-nowrap text-gray-700 dark:text-gray-200">
                                        {{ cantidad(kit.cantidad_requerida) }}
                                    </td>

                                    <td class="px-3 py-3 text-center">
                                        <span
                                            class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide"
                                            :class="kit.listo
                                                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300'
                                                : 'bg-gray-100 text-gray-500 dark:bg-gray-700/50 dark:text-gray-400'">
                                            {{ kit.listo ? 'Sí' : 'No' }}
                                        </span>
                                    </td>

                                    <td
                                        class="px-3 py-3 text-right tabular-nums whitespace-nowrap text-gray-700 dark:text-gray-200">
                                        {{ cantidad(kit.cantidad_disponible) }}
                                    </td>

                                    <td
                                        class="px-3 py-3 text-right tabular-nums whitespace-nowrap text-gray-700 dark:text-gray-200">
                                        {{ cantidad(kit.cantidad_preparada) }}
                                    </td>

                                    <td
                                        class="px-3 py-3 text-right tabular-nums whitespace-nowrap text-gray-700 dark:text-gray-200">
                                        {{ cantidad(kit.falta) }}
                                    </td>

                                    <td class="px-3 py-3">
                                        <span class="line-clamp-2 block max-w-[15rem] text-xs text-gray-500 dark:text-gray-400"
                                            :title="kit.observaciones || ''">
                                            {{ kit.observaciones || '—' }}
                                        </span>
                                    </td>

                                    <td class="px-3 py-3 text-right">
                                        <div class="inline-flex items-center gap-1">
                                            <InfoButton v-show="can(['update KitProceso'])" type="button"
                                                class="rounded-md px-2 py-1.5" @click="abrirEditar(kit)"
                                                v-tooltip="lang().t('app.tooltip.edit')">
                                                <PencilIcon class="h-4 w-4" />
                                            </InfoButton>

                                            <DangerButton v-show="can(['delete KitProceso'])" type="button"
                                                class="rounded-md px-2 py-1.5" @click="abrirEliminar(kit)"
                                                v-tooltip="lang().t('app.tooltip.delete')">
                                                <TrashIcon class="h-4 w-4" />
                                            </DangerButton>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>

                            <!--
                                Fila de totales. Se calcula sobre todos los registros
                                filtrados, no solo sobre la página visible.
                            -->
                            <tfoot>
                                <tr
                                    class="border-t-2 border-amber-300/80 bg-amber-50/80 dark:border-amber-500/40 dark:bg-amber-400/10">
                                    <td colspan="5" class="px-3 py-3">
                                        <div class="flex items-center gap-3">
                                            <span
                                                class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700 dark:bg-amber-400/15 dark:text-amber-300">
                                                <ChartBarIcon class="h-4 w-4" />
                                            </span>
                                            <div>
                                                <p
                                                    class="text-[11px] font-bold uppercase tracking-[0.16em] text-amber-700 dark:text-amber-300">
                                                    Totales de la selección
                                                </p>
                                                <p class="text-xs text-amber-800/80 dark:text-amber-200/80">
                                                    {{ props.resumen.registros }} kit{{ props.resumen.registros === 1 ? '' : 's' }}
                                                    <span v-if="opSeleccionada"> · {{ opSeleccionada.numero_op }}</span>
                                                    <span v-else> · {{ props.resumenPorOp.length }} OP</span>
                                                    · todos los registros filtrados
                                                </p>
                                                <p class="mt-0.5 text-[11px] text-amber-700/70 dark:text-amber-300/70">
                                                    Las cantidades se totalizan por unidad de medida.
                                                </p>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="px-3 py-3 text-right">
                                        <TotalesPorUnidad :unidades="props.resumen.porUnidad" campo="requerida" />
                                    </td>

                                    <td class="px-3 py-3 text-center text-xs font-bold text-amber-800 dark:text-amber-200">
                                        {{ props.resumen.listos }} / {{ props.resumen.registros }}
                                    </td>

                                    <td class="px-3 py-3 text-right">
                                        <TotalesPorUnidad :unidades="props.resumen.porUnidad" campo="disponible" />
                                    </td>

                                    <td class="px-3 py-3 text-right">
                                        <TotalesPorUnidad :unidades="props.resumen.porUnidad" campo="preparada" />
                                    </td>

                                    <td class="px-3 py-3 text-right">
                                        <TotalesPorUnidad :unidades="props.resumen.porUnidad" campo="falta" />
                                    </td>

                                    <td colspan="2" class="px-3 py-3 text-right">
                                        <span
                                            class="text-[11px] font-semibold uppercase tracking-wide text-amber-700/80 dark:text-amber-300/80">
                                            {{ props.resumen.registros }} registro(s)
                                        </span>
                                    </td>
                                </tr>

                                <tr
                                    class="border-t border-amber-200/70 bg-amber-50/60 dark:border-amber-500/20 dark:bg-amber-400/[0.06]">
                                    <td colspan="12" class="px-3 py-3">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span
                                                class="inline-flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.14em] text-amber-700 dark:text-amber-300">
                                                <Squares2X2Icon class="h-3.5 w-3.5" />
                                                Estados requeridos
                                            </span>
                                            <ResumenEstados :estados="props.resumen.estados" />
                                        </div>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div v-else class="flex flex-col items-center justify-center px-6 py-16 text-center">
                        <InboxIcon class="h-10 w-10 text-gray-300 dark:text-gray-600" />
                        <h3 class="mt-3 text-sm font-semibold text-gray-900 dark:text-gray-100">
                            {{ hayFiltros ? 'No hay kits con esos filtros' : 'Aún no hay kits de proceso' }}
                        </h3>
                        <p class="mt-1 max-w-md text-sm text-gray-500 dark:text-gray-400">
                            {{
                                hayFiltros
                                    ? 'Prueba con otra búsqueda o limpia el filtro de OP.'
                                    : 'Los kits se generan automáticamente al crear una orden de producción.'
                            }}
                        </p>
                        <button v-if="hayFiltros" type="button" @click="limpiarFiltros"
                            class="mt-4 inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                            Limpiar filtros
                        </button>
                        <Link v-else-if="can(['create OrdenProduccion'])" :href="route('OrdenProduccion.index')"
                            class="mt-4 inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-indigo-700">
                            <PlusIcon class="h-4 w-4" />
                            Ir a Nueva OP
                        </Link>
                    </div>

                    <div v-if="props.total && props.total > 0"
                        class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 px-3 py-2 dark:border-gray-700">
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Mostrando {{ props.fromController?.from ?? 0 }}–{{ props.fromController?.to ?? 0 }} de
                            {{ props.total }}
                        </p>
                        <Pagination :links="props.fromController" :filters="data.params" />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
