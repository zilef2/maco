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
import { ChevronUpDownIcon, PencilIcon, TrashIcon, ArrowUpTrayIcon } from '@heroicons/vue/24/solid';
import Create from '@/pages/Producto/Create.vue';
import Edit from '@/pages/Producto/Edit.vue';
import Delete from '@/pages/Producto/Delete.vue';
import ImportarProductoModal from '@/pages/Producto/ImportarProductoModal.vue';

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
    Productoo: null as any,
    selectedId: [] as number[],
    multipleSelect: false,
    createOpen: false,
    editOpen: false,
    deleteOpen: false,
    importOpen: false,
    dataSet: (usePage().props as any)?.app?.perpage ?? defaultPerPage,
})

const order = (field: string) => {
    data.params.field = field
    data.params.order = data.params.order === "asc" ? "desc" : "asc"
}

watch(() => _.cloneDeep(data.params), debounce(() => {
    const params = pickBy(data.params)
    router.get(route("Producto.index"), params, {
        replace: true,
        preserveState: true,
        preserveScroll: true,
    })
}, 150))

const selectAll = (event: any) => {
    if (event.target.checked === false) {
        data.selectedId = []
    } else {
        props.fromController?.data.forEach((Producto: any) => {
            data.selectedId.push(Producto.id)
        })
    }
}
const select = () => data.multipleSelect = props.fromController?.data.length === data.selectedId.length;

const titulos = [
    { order: 'Codigo_interno', label: 'Codigo_interno', type: 'string' ,default:'COD-001'},
    { order: 'ID_producto_Shopify', label: 'ID_producto_Shopify', type: 'string' },
    { order: 'Categoria_original', label: 'Categoria_original', type: 'string' },
    { order: 'Familia_normalizada', label: 'Familia_normalizada', type: 'string' },
    { order: 'Titulo_Shopify', label: 'Titulo_Shopify', type: 'string' },
    { order: 'Control_de_variante', label: 'Control_de_variante', type: 'string' },
    { order: 'Opciones', label: 'Opciones', type: 'string' },
    { order: 'Estado', label: 'Estado', type: 'string' },
    { order: 'Observaciones', label: 'Observaciones', type: 'string' },
    { order: 'descripcion', label: 'descripcion', type: 'text' },
    { order: 'precio_predicho', label: 'precio_predicho', type: 'number' },
    { order: 'precio_preventa', label: 'precio_preventa', type: 'number' },
];

</script>

<template>

    <Head :title="props.title" />

    <AppLayout>
        <Breadcrumb :title="title" :breadcrumbs="breadcrumbs" class="capitalize text-xl font-bold" />
        <div class="space-y-4">
            <div class="px-4 sm:px-0">
                <div class="rounded-lg overflow-hidden w-fit">
                    <PrimaryButton class="m-1 align-top" @click="data.createOpen = true" v-if="can(['create Producto'])">
                        {{ lang().t('app.button.add') }}
                    </PrimaryButton>

                    <PrimaryButton v-if="can(['create Producto'])"
                        class="m-1 align-top bg-emerald-600 hover:bg-emerald-700 focus:ring-emerald-500"
                        @click="data.importOpen = true">
                        <ArrowUpTrayIcon class="w-4 h-4 mr-1 inline" />
                        Cargar Excel
                    </PrimaryButton>

                    <ImportarProductoModal :show="data.importOpen" @close="data.importOpen = false" />

                    <Create v-if="can(['create Producto'])" :numberPermissions="props.numberPermissions"
                        :titulos="titulos" :show="data.createOpen" @close="data.createOpen = false" :title="props.title"
                        :losSelect="props.losSelect" />

                    <Edit v-if="can(['update Producto'])" :titulos="titulos" :numberPermissions="props.numberPermissions"
                        :show="data.editOpen" @close="data.editOpen = false" :Productoa="data.Productoo"
                        :title="props.title" :losSelect="props.losSelect" />

                    <Delete v-if="can(['delete Producto'])" :numberPermissions="props.numberPermissions"
                        :show="data.deleteOpen" @close="data.deleteOpen = false" :Productoa="data.Productoo"
                        :title="props.title" />
                </div>
            </div>
            <div class="relative bg-white dark:bg-gray-800 shadow sm:rounded-lg w-max min-w-full">
                <div class="flex justify-between p-2">
                    <div class="flex space-x-2">
                        <SelectInput v-model="data.params.perPage" :dataSet="data.dataSet" />
                    </div>
                    <TextInput v-if="props.numberPermissions && props.numberPermissions > 1"
                        v-model="data.params.search" type="text" class="block w-4/6 md:w-3/6 lg:w-2/6 rounded-lg"
                        :placeholder="lang().t('app.placeholder.searchfor_producto')" />
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
                                            <InfoButton v-show="can(['update Producto'])" type="button"
                                                @click="(data.editOpen = true), (data.Productoo = claseFromController)"
                                                class="px-2 py-1.5 rounded-none"
                                                v-tooltip="lang().t('app.tooltip.edit')">
                                                <PencilIcon class="w-4 h-4" />
                                            </InfoButton>
                                            <DangerButton v-show="can(['delete Producto'])" type="button"
                                                @click="(data.deleteOpen = true), (data.Productoo = claseFromController)"
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
