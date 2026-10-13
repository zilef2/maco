<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import Breadcrumb from '@/components/Breadcrumb.vue';
import TextInput from '@/components/TextInput.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SelectInput from '@/components/SelectInput.vue';
import { reactive, watch } from 'vue';

import DangerButton from '@/components/DangerButton.vue';
import pkg from 'lodash';

import Pagination from '@/components/Pagination.vue';
import { ChevronUpDownIcon, EyeIcon, PencilIcon, TrashIcon, ArrowUpTrayIcon, ClipboardIcon } from '@heroicons/vue/24/solid';
import Create from '@/pages/OrdenProduccion/Create.vue';
import Edit from '@/pages/OrdenProduccion/Edit.vue';
import Delete from '@/pages/OrdenProduccion/Delete.vue';
import DistribucionTallas from '@/pages/OrdenProduccion/DistribucionTallas.vue';
import VerObservacion from '@/pages/OrdenProduccion/VerObservacion.vue';
import ImportarKitModal from '@/pages/OrdenProduccion/ImportarKitModal.vue';
import KitTallerOPModal from '@/pages/OrdenProduccion/KitTallerOPModal.vue';

import Checkbox from '@/components/Checkbox.vue';
import InfoButton from '@/components/InfoButton.vue';

import { formatDate, zilef_nuber_format } from '@/global.ts';

const { _, debounce, pickBy } = pkg
const props = defineProps({
    fromController: Object,
    total: Number,
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
    OrdenProducciono: null as any,
    selectedId: [] as number[],
    multipleSelect: false,
    createOpen: false,
    editOpen: false,
    deleteOpen: false,
    observacionOpen: false,
    observacionTexto: '' as string,
    observacionOp: '' as string,
    distribucionOpen: false,
    distribucionOp: null as any,
    importKitOpen: false,
    kitTallerOpen: false,
    kitTallerOp: null as any,
    dataSet: (usePage().props as any)?.app?.perpage ?? defaultPerPage,
})

const order = (field: string) => {
    data.params.field = field
    data.params.order = data.params.order === "asc" ? "desc" : "asc"
}

watch(() => _.cloneDeep(data.params), debounce(() => {
    const params = pickBy(data.params)
    router.get(route("OrdenProduccion.index"), params, {
        replace: true,
        preserveState: true,
        preserveScroll: true,
    })
}, 150))

const selectAll = (event: any) => {
    if (event.target.checked === false) {
        data.selectedId = []
    } else {
        props.fromController?.data.forEach((OrdenProduccion: any) => {
            data.selectedId.push(OrdenProduccion.id)
        })
    }
}
const select = () => data.multipleSelect = props.fromController?.data.length === data.selectedId.length;

const abrirObservacion = (op: any) => {
    data.observacionTexto = op?.observaciones ?? ''
    data.observacionOp = op?.numero_op ?? ''
    data.observacionOpen = true
}

const abrirDistribucion = (op: any) => {
    data.distribucionOp = op ?? null
    data.distribucionOpen = true
}

const abrirKitTaller = (op: any) => {
    data.kitTallerOp = op ?? null
    data.kitTallerOpen = true
}

const titulos = [
    { order: 'numero_op', label: 'numero_op', type: 'string' },
    { order: 'producto_referencia_id', label: 'producto_referencia_id', type: 'foreign', nameid: 'producto_nombre' },
    { order: 'cantidad_planificada', label: 'cantidad_planificada', type: 'integer' },
    { order: 'fecha_creacion', label: 'fecha_creacion', type: 'date' },
    { order: 'fecha_prevista_envio_taller', label: 'fecha_prevista_envio_taller', type: 'date' },
    { order: 'taller_id', label: 'taller_id', type: 'foreign', nameid: 'taller_nombre' },
    { order: 'estado_op_id', label: 'estado_op_id', type: 'foreign', nameid: 'estado_nombre' },
    { order: 'user_id', label: 'user_id', type: 'foreign', nameid: 'responsable_nombre' },
    { order: 'id_shopify_producto', label: 'id_shopify_producto', type: 'string' },
    { order: 'observaciones', label: 'observaciones', type: 'text' },
];

</script>

<template>

    <Head :title="props.title" />

    <AppLayout>
        <!-- <Breadcrumb :title="title" :breadcrumbs="breadcrumbs" class="capitalize text-xl font-bold" /> -->
        <div class="space-y-4">
            <div class="px-4 sm:px-0">
                <div class="rounded-lg overflow-hidden w-fit">
                    <PrimaryButton class="m-1" @click="data.createOpen = true" v-if="can(['create OrdenProduccion'])">
                        {{ lang().t('app.button.add') }}
                    </PrimaryButton>

                    <Create v-if="can(['create OrdenProduccion'])" :numberPermissions="props.numberPermissions"
                        :titulos="titulos" :show="data.createOpen" @close="data.createOpen = false" :title="props.title"
                        :losSelect="props.losSelect" />

                    <Edit v-if="can(['update OrdenProduccion'])" :titulos="titulos"
                        :numberPermissions="props.numberPermissions" :show="data.editOpen"
                        @close="data.editOpen = false" :OrdenProducciona="data.OrdenProducciono" :title="props.title"
                        :losSelect="props.losSelect" />

                    <Delete v-if="can(['delete OrdenProduccion'])" :numberPermissions="props.numberPermissions"
                        :show="data.deleteOpen" @close="data.deleteOpen = false"
                        :OrdenProducciona="data.OrdenProducciono" :title="props.title" />

                    <VerObservacion :show="data.observacionOpen" @close="data.observacionOpen = false"
                        :title="props.title" :numeroOp="data.observacionOp" :observacion="data.observacionTexto" />

                    <DistribucionTallas :show="data.distribucionOpen" @close="data.distribucionOpen = false"
                        :title="props.title" :ordenId="data.distribucionOp?.id"
                        :numeroOp="data.distribucionOp?.numero_op"
                        :items="data.distribucionOp?.distribuciones_por_tallas || []"
                        :canEdit="can(['update OrdenProduccion'])" />

                    <ImportarKitModal :show="data.importKitOpen" @close="data.importKitOpen = false"
                        :ordenProduccion="data.OrdenProducciono" />

                    <KitTallerOPModal :show="data.kitTallerOpen" @close="data.kitTallerOpen = false"
                        :ordenProduccion="data.kitTallerOp" :title="props.title" />
                </div>
            </div>
            <div class="relative bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                <div class="flex justify-between p-2">
                    <div class="flex space-x-2">
                        <SelectInput v-model="data.params.perPage" :dataSet="data.dataSet" />
                    </div>
                    <TextInput v-if="props.numberPermissions && props.numberPermissions > 1"
                        v-model="data.params.search" type="text" class="block w-4/6 md:w-3/6 lg:w-2/6 rounded-lg"
                        :placeholder="lang().t('app.placeholder.search')" />
                </div>
                <div class="scrollbar-table">
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
                                <th class="px-2 py-4">Tallas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(claseFromController, indexu) in props.fromController?.data" :key="indexu"
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
                                            <InfoButton v-show="can(['update OrdenProduccion'])" type="button"
                                                @click="(data.editOpen = true), (data.OrdenProducciono = claseFromController)"
                                                class="px-2 py-1.5 rounded-none"
                                                v-tooltip="lang().t('app.tooltip.edit')">
                                                <PencilIcon class="w-4 h-4" />
                                            </InfoButton>
                                            <InfoButton v-show="can(['create KitTaller'])" type="button"
                                                :thecolor="'green'"
                                                @click="(data.importKitOpen = true), (data.OrdenProducciono = claseFromController)"
                                                class="px-2 py-1.5 rounded-none"
                                                v-tooltip="'Importar KitTaller desde Excel'">
                                                <ArrowUpTrayIcon class="w-4 h-4" />
                                            </InfoButton>
                                            <InfoButton v-show="can(['update OrdenProduccion'])" type="button"
                                                :thecolor="'blue'" @click="abrirKitTaller(claseFromController)"
                                                class="px-2 py-1.5 rounded-none" v-tooltip="'Ver Kitaller'">
                                                <ClipboardIcon class="w-4 h-4" />
                                            </InfoButton>
                                            <DangerButton v-show="can(['delete OrdenProduccion'])" type="button"
                                                @click="(data.deleteOpen = true), (data.OrdenProducciono = claseFromController)"
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
                                    <span v-if="titulo['order'] === 'observaciones'" class="flex items-center gap-2">
                                        <span class="block max-w-[240px] truncate">{{
                                            claseFromController[titulo['order']]
                                            || '—' }}</span>
                                        <InfoButton v-if="claseFromController[titulo['order']]" type="button"
                                            @click="abrirObservacion(claseFromController)"
                                            class="px-2 py-1.5 rounded-none" v-tooltip="lang().t('app.tooltip.ver')">
                                            <EyeIcon class="w-4 h-4" />
                                        </InfoButton>
                                    </span>
                                    <span v-else-if="titulo['type'] === 'text' || titulo['type'] === 'string'"> {{
                                        claseFromController[titulo['order']] }} </span>
                                    <span v-if="titulo['type'] === 'number' || titulo['type'] === 'integer'"> {{
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
                                <td class="whitespace-nowrap py-4 px-2 sm:py-3">
                                    <span class="flex items-center gap-2">
                                        <span
                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">
                                            {{ (claseFromController.distribuciones_por_tallas || []).length }} tallas
                                        </span>
                                        <InfoButton type="button" @click="abrirDistribucion(claseFromController)"
                                            class="px-2 py-1.5 rounded-none" v-tooltip="lang().t('app.tooltip.ver')">
                                            <EyeIcon class="w-4 h-4" />
                                        </InfoButton>
                                    </span>
                                </td>

                            </tr>
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
