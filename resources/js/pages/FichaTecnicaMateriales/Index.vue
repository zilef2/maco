<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import Breadcrumb from '@/components/Breadcrumb.vue';
import TextInput from '@/components/TextInput.vue';
import SelectInput from '@/components/SelectInput.vue';
import { reactive, watch } from 'vue';

import DangerButton from '@/components/DangerButton.vue';
import InfoButton from '@/components/InfoButton.vue';
import pkg from 'lodash';

import Pagination from '@/components/Pagination.vue';
import { ChevronUpDownIcon, PencilIcon, TrashIcon, EyeIcon, PlusIcon } from '@heroicons/vue/24/solid';
import Edit from '@/pages/FichaTecnicaMateriales/Edit.vue';
import Delete from '@/pages/FichaTecnicaMateriales/Delete.vue';
import MaterialesModal from '@/pages/FichaTecnicaMateriales/MaterialesModal.vue';

import { zilef_nuber_format } from '@/global.ts';

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
    FichaTecnicaMaterialeso: null as any,
    editOpen: false,
    deleteOpen: false,
    materialesOpen: false,
    fichaVisible: null as any,
    dataSet: (usePage().props as any)?.app?.perpage ?? defaultPerPage,
})

const order = (field: string) => {
    data.params.field = field
    data.params.order = data.params.order === "asc" ? "desc" : "asc"
}

watch(() => _.cloneDeep(data.params), debounce(() => {
    const params = pickBy(data.params)
    router.get(route("FichaTecnicaMateriales.index"), params, {
        replace: true,
        preserveState: true,
        preserveScroll: true,
    })
}, 150))
</script>

<template>

    <Head :title="props.title" />

    <AppLayout>
        <Breadcrumb :title="title" :breadcrumbs="breadcrumbs" class="capitalize text-xl font-bold" />
        <div class="space-y-4">
            <div class="px-4 sm:px-0">
                <div class="rounded-lg overflow-hidden w-fit">
                    <Edit v-if="can(['update FichaTecnicaMateriales'])" :numberPermissions="props.numberPermissions"
                        :show="data.editOpen" @close="data.editOpen = false" :FichaTecnicaMaterialesa="data.FichaTecnicaMaterialeso"
                        :title="props.title" :losSelect="props.losSelect" />

                    <Delete v-if="can(['delete FichaTecnicaMateriales'])" :numberPermissions="props.numberPermissions"
                        :show="data.deleteOpen" @close="data.deleteOpen = false" :FichaTecnicaMaterialesa="data.FichaTecnicaMaterialeso"
                        :title="props.title" />

                    <MaterialesModal :show="data.materialesOpen" @close="data.materialesOpen = false"
                        :title="props.title" :ficha="data.fichaVisible" />
                </div>
            </div>
            <div class="px-4 sm:px-0 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Las fichas técnicas se registran al crear un producto.
                    <Link v-if="can(['create Producto'])" :href="route('Producto.index')"
                        class="inline-flex items-center gap-1 font-medium text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300">
                        <PlusIcon class="w-3.5 h-3.5" />
                        Ir a Nuevo producto
                    </Link>
                </p>
                <div class="inline-flex items-center gap-2 rounded-lg border px-3 py-1.5 text-sm"
                    :class="(props.losSelect?.productos_sin_ficha?.length || 0) > 0
                        ? 'border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/20 text-amber-800 dark:text-amber-300'
                        : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300'">
                    <span class="text-xs font-medium uppercase tracking-wide opacity-70">Sin ficha:</span>
                    <span class="text-base font-bold">{{ props.losSelect?.productos_sin_ficha?.length ?? 0 }}</span>
                    <span class="text-xs opacity-70">producto{{ (props.losSelect?.productos_sin_ficha?.length || 0) === 1 ? '' : 's' }}</span>
                </div>
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
                                <th v-if="props.numberPermissions && props.numberPermissions > 1" class="px-2 py-4">
                                    Accion</th>

                                <th class="px-2 py-4 text-center">#</th>
                                <th class="px-2 py-4 cursor-pointer" v-on:click="order('nombre')">
                                    <div class="flex justify-between items-center">
                                        <span>{{ lang().t('app.label.nombre') }}</span>
                                        <ChevronUpDownIcon class="w-4 h-4" />
                                    </div>
                                </th>
                                <th class="px-2 py-4">{{ lang().t('app.label.productos_nombres') }}</th>
                                <th class="px-2 py-4 text-center">{{ lang().t('app.label.materiales_count') }}</th>
                                <th class="px-2 py-4 text-right">{{ lang().t('app.label.valor_total_ficha') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(ficha, indexu) in props.fromController?.data" :key="ficha.id"
                                class="border-t border-gray-200 dark:border-gray-700 hover:bg-gray-200/30 hover:dark:bg-gray-900/20">

                                <td v-if="props.numberPermissions && props.numberPermissions > 1"
                                    class="whitespace-nowrap py-4 w-12 px-2 sm:py-3">
                                    <div class="flex justify-center items-center">
                                        <div class="rounded-md overflow-hidden">
                                            <InfoButton v-show="can(['update FichaTecnicaMateriales'])" type="button"
                                                @click="(data.editOpen = true), (data.FichaTecnicaMaterialeso = ficha)"
                                                class="px-2 py-1.5 rounded-none"
                                                v-tooltip="lang().t('app.tooltip.edit')">
                                                <PencilIcon class="w-4 h-4" />
                                            </InfoButton>
                                            <DangerButton v-show="can(['delete FichaTecnicaMateriales'])" type="button"
                                                @click="(data.deleteOpen = true), (data.FichaTecnicaMaterialeso = ficha)"
                                                class="px-2 py-1.5 rounded-none"
                                                v-tooltip="lang().t('app.tooltip.delete')">
                                                <TrashIcon class="w-4 h-4" />
                                            </DangerButton>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap py-4 px-2 sm:py-3 text-center">{{ ++indexu }}</td>
                                <td class="whitespace-nowrap py-4 px-2 sm:py-3">{{ ficha.nombre }}</td>
                                <td class="py-4 px-2 sm:py-3">
                                    <span v-if="ficha.productos_nombres">{{ ficha.productos_nombres }}</span>
                                    <span v-else class="text-gray-400">—</span>
                                </td>
                                <td class="whitespace-nowrap py-4 px-2 sm:py-3 text-center">
                                    <button type="button" @click="(data.fichaVisible = ficha), (data.materialesOpen = true)"
                                        class="inline-flex items-center gap-1.5 rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300 px-2.5 py-1 text-xs font-medium hover:bg-blue-200 dark:hover:bg-blue-900/50 transition"
                                        v-tooltip="'Ver materiales requeridos'">
                                        <span>{{ ficha.materiales_count }}</span>
                                        <EyeIcon class="w-4 h-4" />
                                    </button>
                                </td>
                                <td class="whitespace-nowrap py-4 px-2 sm:py-3 text-right">
                                    {{ zilef_nuber_format(ficha.valor_total_ficha, 0, true) }}
                                </td>
                            </tr>
                            <tr class="border-t border-gray-600">
                                <td v-if="props.numberPermissions && props.numberPermissions > 1"
                                    class="whitespace-nowrap py-4 w-12 px-2 sm:py-3 text-center"> -
                                </td>
                                <td class="whitespace-nowrap py-4 px-2 sm:py-3 text-center"> Total: </td>
                                <td class="whitespace-nowrap py-4 px-2 sm:py-3 text-center" colspan="4">
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
