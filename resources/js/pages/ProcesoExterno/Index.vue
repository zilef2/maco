<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import Breadcrumb from '@/components/Breadcrumb.vue';
import TextInput from '@/components/TextInput.vue';
import SelectInput from '@/components/SelectInput.vue';
import { computed, reactive, watch } from 'vue';

import DangerButton from '@/components/DangerButton.vue';
import pkg from 'lodash';

import Pagination from '@/components/Pagination.vue';
import { ChevronUpDownIcon, InformationCircleIcon, PencilIcon, TrashIcon } from '@heroicons/vue/24/solid';
import Edit from '@/pages/ProcesoExterno/Edit.vue';
import Delete from '@/pages/ProcesoExterno/Delete.vue';

import Checkbox from '@/components/Checkbox.vue';
import InfoButton from '@/components/InfoButton.vue';

import { formatDate, zilef_nuber_format } from '@/global.ts';

const { _, debounce, pickBy } = pkg
const props = defineProps({
    fromController: Object,
    total: Number,
    ordenesEnProcesoExterno: Number,
    filters: Object,
    breadcrumbs: Object,
    perPage: Number,

    title: String,

    numberPermissions: Number,
    losSelect: Object,
    titulos: Array,
})

const defaultPerPage = [
    { label: '5', value: 5 },
    { label: '10', value: 10 },
    { label: '25', value: 25 },
    { label: '50', value: 50 },
    { label: '100', value: 100 },
];

const data = reactive({
    params: {
        search: props.filters?.search,
        field: props.filters?.field,
        order: props.filters?.order,
        perPage: props.perPage,
    },
    ProcesoExternoo: null as any,
    selectedId: [] as number[],
    multipleSelect: false,
    editOpen: false,
    deleteOpen: false,
    dataSet: (usePage().props as any)?.app?.perpage ?? defaultPerPage,
})

const order = (field: string) => {
    data.params.field = field
    data.params.order = data.params.order === "asc" ? "desc" : "asc"
}

watch(() => _.cloneDeep(data.params), debounce(() => {
    const params = pickBy(data.params)
    router.get(route("ProcesoExterno.index"), params, {
        replace: true,
        preserveState: true,
        preserveScroll: true,
    })
}, 150))

const selectAll = (event: any) => {
    if (event.target.checked === false) {
        data.selectedId = []
    } else {
        props.fromController?.data.forEach((ProcesoExterno: any) => {
            data.selectedId.push(ProcesoExterno.id)
        })
    }
}
const select = () => data.multipleSelect = props.fromController?.data.length === data.selectedId.length;

const titulos = [
    { order: 'etapa_componente', label: 'etapa_componente', type: 'string' },
    { order: 'material_origen_id', label: 'material_origen_id', type: 'foreign', nameid: 'material_nombre' },
    { order: 'cantidad_salida', label: 'cantidad_salida', type: 'number' },
    { order: 'unidad_medida', label: 'unidad_medida', type: 'string' },
    { order: 'bodega_origen', label: 'bodega_origen', type: 'string' },
    { order: 'proveedor_destino_id', label: 'proveedor_destino_id', type: 'foreign', nameid: 'proveedor_nombre' },
    { order: 'servicio_proceso', label: 'servicio_proceso', type: 'string' },
    { order: 'fecha_salida', label: 'fecha_salida', type: 'datetime' },
    { order: 'fecha_prevista_regreso', label: 'fecha_prevista_regreso', type: 'date' },
    { order: 'id', label: 'id', type: 'string' },
    { order: 'resultado_observaciones', label: 'resultado_observaciones', type: 'text' },
    { order: 'fecha_regreso_real', label: 'fecha_regreso_real', type: 'datetime' },
];

// Agrupar los procesos externos por Orden de Producción (orden_numero)
const grupos = computed(() => {
    const map = new Map<string, any[]>();
    (props.fromController?.data ?? []).forEach((pe: any) => {
        const label = pe.orden_numero ?? 'Sin OP';
        if (!map.has(label)) map.set(label, []);
        map.get(label)!.push(pe);
    });
    return Array.from(map.entries()).map(([label, items]) => ({
        label,
        items,
        materiales: new Set(items.map((i: any) => i.material_nombre).filter(Boolean)).size,
    }));
});

</script>

<template>

    <Head :title="props.title" />

    <AppLayout>
        <Breadcrumb :title="title" :breadcrumbs="breadcrumbs" class="capitalize text-xl font-bold" />
        <div class="space-y-4">
            <div class="px-4 sm:px-0">
                <div class="rounded-xl border border-blue-200 bg-blue-50/60 p-4 dark:border-blue-900/60 dark:bg-blue-950/20">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex items-start gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300">
                                <InformationCircleIcon class="h-5 w-5" />
                            </span>
                            <div>
                                <h2 class="text-sm font-semibold text-blue-900 dark:text-blue-200">
                                    El proceso externo se registra desde la OP
                                </h2>
                                <p class="mt-1 max-w-3xl text-sm text-blue-800 dark:text-blue-300">
                                    Este listado se llena automáticamente cuando una OP pasa al estado
                                    <span class="font-semibold">«Procesos externos»</span> y se registran el proveedor
                                    y los materiales que salen. No es necesario crear registros manualmente.
                                </p>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-3 self-start rounded-lg border border-blue-200 bg-white/70 px-4 py-3 dark:border-blue-900/60 dark:bg-blue-950/40 lg:self-auto">
                            <span class="text-2xl font-semibold tabular-nums text-blue-900 dark:text-blue-200">
                                {{ props.ordenesEnProcesoExterno }}
                            </span>
                            <span class="text-xs font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-300">
                                {{ props.ordenesEnProcesoExterno === 1 ? 'OP activa en proceso externo' : 'OP activas en proceso externo' }}
                            </span>
                        </div>
                    </div>
                </div>

                <Edit v-if="can(['update ProcesoExterno'])" :titulos="titulos" :numberPermissions="props.numberPermissions"
                    :show="data.editOpen" @close="data.editOpen = false" :ProcesoExternoa="data.ProcesoExternoo"
                    :title="props.title" :losSelect="props.losSelect" />

                <Delete v-if="can(['delete ProcesoExterno'])" :numberPermissions="props.numberPermissions"
                    :show="data.deleteOpen" @close="data.deleteOpen = false" :ProcesoExternoa="data.ProcesoExternoo"
                    :title="props.title" />
            </div>
            <div class="relative bg-white dark:bg-gray-800 shadow sm:rounded-lg w-max min-w-full">
                <div class="flex justify-between p-2">
                    <div class="flex space-x-2">
                        <SelectInput v-model="data.params.perPage" :dataSet="data.dataSet" />
                    </div>
                    <TextInput v-if="props.numberPermissions && props.numberPermissions > 1"
                        v-model="data.params.search" type="text" class="block w-4/6 md:w-3/6 lg:w-2/6 rounded-lg"
                        :placeholder="lang().t('app.placeholder.search')" />
                </div>
                <div>
                    <table v-if="props.total && props.total > 0" class="w-full">
                        <thead class="uppercase text-sm border-t border-gray-200 dark:border-gray-700">
                            <tr class="dark:bg-gray-900/50 text-left">
                                <th class="px-2 py-4 text-center">
                                    <Checkbox v-model:checked="data.multipleSelect" @change="selectAll" />
                                </th>
                                <th v-if="props.numberPermissions && props.numberPermissions > 1" class="px-2 py-4">
                                    Accion</th>

                                <th class="px-2 py-4 text-center">#</th>
                                <th v-for="titulo in titulos" :key="titulo.order" class="px-2 py-4 cursor-pointer"
                                    v-on:click="order(titulo['order'])">
                                    <div class="flex justify-between items-center">
                                        <span>{{ lang().t('app.label.' + titulo['label']) }}</span>
                                        <ChevronUpDownIcon class="w-4 h-4" />
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="(grupo, gi) in grupos" :key="grupo.label">
                                <tr class="border-t-2 border-gray-400 dark:border-gray-600 bg-gray-100/70 dark:bg-gray-900/60">
                                    <td :colspan="titulos.length + 3" class="px-3 py-2">
                                        <div class="flex items-center gap-3">
                                            <span class="uppercase text-xs font-bold tracking-wide text-gray-600 dark:text-gray-300">{{ grupo.label }}</span>
                                            <span
                                                class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                                                {{ grupo.items.length }} materiales
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-for="(claseFromController, indexu) in grupo.items" :key="`${gi}-${claseFromController.id}`"
                                    class="border-t border-gray-200 dark:border-gray-700 hover:bg-gray-200/30 hover:dark:bg-gray-900/20">

                                <td class="whitespace-nowrap py-4 px-2 sm:py-3 text-center">
                                    <input
                                        class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-primary dark:text-primary shadow-sm focus:ring-primary/80 dark:focus:ring-primary dark:focus:ring-offset-gray-800 dark:checked:bg-primary dark:checked:border-primary"
                                        type="checkbox" @change="select" :value="claseFromController.id"
                                        v-model="data.selectedId" />
                                </td>
                                <td v-if="props.numberPermissions && props.numberPermissions > 1"
                                    class="whitespace-nowrap py-4 w-12 px-2 sm:py-3">
                                    <div class="flex justify-center items-center">
                                        <div class="rounded-md overflow-hidden">
                                            <InfoButton v-show="can(['update ProcesoExterno'])" type="button"
                                                @click="(data.editOpen = true), (data.ProcesoExternoo = claseFromController)"
                                                class="px-2 py-1.5 rounded-none"
                                                v-tooltip="lang().t('app.tooltip.edit')">
                                                <PencilIcon class="w-4 h-4" />
                                            </InfoButton>
                                            <DangerButton v-show="can(['delete ProcesoExterno'])" type="button"
                                                @click="(data.deleteOpen = true), (data.ProcesoExternoo = claseFromController)"
                                                class="px-2 py-1.5 rounded-none"
                                                v-tooltip="lang().t('app.tooltip.delete')">
                                                <TrashIcon class="w-4 h-4" />
                                            </DangerButton>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap py-4 px-2 sm:py-3 text-center">{{ ++indexu }}</td>
                                <td v-for="titulo in titulos" :key="titulo.order"
                                    class="whitespace-nowrap py-4 px-2 sm:py-3">
                                    <span v-if="titulo['type'] === 'text' || titulo['type'] === 'string'"> {{
                                        claseFromController[titulo['order']] }} </span>
                                    <span v-if="titulo['type'] === 'number'"> {{
                                        zilef_nuber_format(claseFromController[titulo['order']], 0, false) }} </span>
                                    <span v-if="titulo['type'] === 'dinero'"> {{
                                        zilef_nuber_format(claseFromController[titulo['order']], 0, true) }} </span>
                                    <span v-if="titulo['type'] === 'date'"> {{
                                        formatDate(claseFromController[titulo['order']], false) }} </span>
                                    <span v-if="titulo['type'] === 'datetime'"> {{
                                        formatDate(claseFromController[titulo['order']], true) }} </span>
                                    <span v-if="titulo['type'] === 'foreign'">
                                        {{ claseFromController?.[titulo?.['nameid']] ?? '' }}
                                    </span>
                                </td>

                            </tr>
                            </template>
                            <tr class="border-t border-gray-600">
                                <td v-if="props.numberPermissions && props.numberPermissions > 1"
                                    class="whitespace-nowrap py-4 w-12 px-2 sm:py-3 text-center"> -
                                </td>
                                <td class="whitespace-nowrap py-4 px-2 sm:py-3 text-center"> Total: </td>
                                <td class="whitespace-nowrap py-4 px-2 sm:py-3 text-center">
                                    {{ props.total }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <h2 v-else class="text-center text-xl my-8">Sin Registros</h2>
                </div>
                <div v-if="props.total && props.total > 0"
                    class="flex justify-between items-center p-2 border-t border-gray-200 dark:border-gray-700">
                    <Pagination :links="props.fromController" :filters="data.params" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
