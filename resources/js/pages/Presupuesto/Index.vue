<script setup lang="ts">

import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';
import pkg from 'lodash';
import { ChevronUpDownIcon, PencilIcon, TrashIcon } from '@heroicons/vue/24/solid';
import PrimaryButton from '@/components/PrimaryButton.vue';
import Create from '@/pages/Presupuesto/Create.vue';
import Edit from '@/pages/Presupuesto/Edit.vue';
import Delete from '@/pages/Presupuesto/Delete.vue';

const page = usePage();

const presupuestoPermissions = computed(() => {
    const userCan = (page.props.auth as any)?.can || {};
    return Object.keys(userCan).filter((permKey: string) =>
        permKey.toLowerCase().includes('presupuesto')
    );
});


import { formatDate, zilef_nuber_format } from '@/global.ts';

const { _, debounce, pickBy } = pkg


interface Props {
    fromController?: object
    total?: number
    filters?: { search?: string; field?: string; order?: string }
    breadcrumbs?: object
    perPage?: number
    title?: string
    numberPermissions?: number
    losSelect?: object
    titulos?: any[]
}

// const props = withDefaults(defineProps<Props>(), {
//     filters: () => ({}),
//     perPage: 10,
//     // ...
// })


const props = withDefaults(defineProps<Props>(), {
    // const props = defineProps({
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

const data = reactive({
    params: {
        search: props.filters.search,
        // search2: props.filters.search2,
        //   search3: props.filters.search3,
        field: props.filters.field,
        order: props.filters.order,
        perPage: props.perPage,
    },
    Presupuestoo: null,
    selectedId: [],
    multipleSelect: false,
    createOpen: false,
    editOpen: false,
    deleteOpen: false,
    // deleteBulkOpen: false,
    dataSet: 5
})


// <!--<editor-fold desc="order, watchclone, select">-->
const order = (field) => {
    data.params.field = field
    data.params.order = data.params.order === "asc" ? "desc" : "asc"
}

watch(() => _.cloneDeep(data.params), debounce(() => {
    let params = pickBy(data.params)
    router.get(route("Presupuesto.index"), params, {
        replace: true,
        preserveState: true,
        preserveScroll: true,
    })
}, 150))

const selectAll = (event) => {
    if (event.target.checked === false) {
        data.selectedId = []
    } else {
        props.fromController?.data.forEach((Presupuesto) => {
            data.selectedId.push(Presupuesto.id)
        })
    }
}
const select = () => data.multipleSelect = props.fromController?.data.length === data.selectedId.length;

// <!--</editor-fold>-->


// const form = useForm({ })
// watchEffect(() => { })


// text - string // number // dinero // date // datetime // foreign
const titulos = [
    { order: 'nombre', label: 'nombre', type: 'string', optional: false, default: 'Presupuesto' },
    { order: 'descripcion', label: 'descripcion', type: 'text', optional: false, default: 'Presupuesto para el semestre 2 del 2026' },
    { order: 'fecha_inicio', label: 'fecha_inicio', type: 'datetime', optional: false, default: '2026-09-12' },
    { order: 'fecha_fin', label: 'fecha_fin', type: 'datetime', optional: false, default: '2026-12-31' },
    { order: 'monto_total', label: 'monto_total', type: 'dinero', optional: false, default: 5600000 },
    { order: 'monto_ejecutado', label: 'monto_ejecutado', type: 'dinero', optional: false, default: 0 },
    { order: 'monto_disponible', label: 'monto_disponible', type: 'dinero', optional: false, default: 5000000 },
    { order: 'total_salarios', label: 'total_salarios', type: 'dinero', optional: false, default: 200000 },
    { order: 'total_viaticos', label: 'total_viaticos', type: 'dinero', optional: false, default: 200000 },
    { order: 'total_materiales', label: 'total_materiales', type: 'dinero', optional: false, default: 100000 },
    { order: 'estado', label: 'estado', type: 'string', optional: false, default: 'activo' },
    { order: 'observaciones', label: 'observaciones', type: 'text', optional: false, default: 'No hay observaciones' },
    { order: 'porcentaje_ejecutado', label: 'porcentaje_ejecutado', type: 'float', optional: false, default: 0 },
    { order: 'umbral_alerta', label: 'umbral_alerta', type: 'float', optional: false, default: 0 },
    { order: 'fecha_ultimo_reajuste', label: 'fecha_ultimo_reajuste', type: 'datetime', optional: false, default: '2026-09-12' },
    { order: 'motivo_reajuste', label: 'motivo_reajuste', type: 'text', optional: false, default: 'No hay reajustes' },
    // { order: 'user_id_aprobado', label: 'user_id_aprobado', type: 'foreign' },
    // { order: 'aprobado_por', label: 'aprobado_por', type: 'string' },
    // { order: 'fecha_aprobacion', label: 'fecha_aprobacion', type: 'datetime' },
    // { order: 'codigo_interno', label: 'codigo_interno', type: 'string' },
];

</script>

<template>

    <Head :title="props.title" />

    <AppLayout>
        <Breadcrumb :title="title" :breadcrumbs="breadcrumbs" class="capitalize text-xl font-bold" />
        <div class="space-y-4">
            <div v-if="presupuestoPermissions.length > 0" class="flex flex-wrap items-center gap-2 p-3 bg-white dark:bg-gray-800 shadow rounded-lg text-xs border dark:border-gray-700">
                <span class="font-semibold text-gray-700 dark:text-gray-300">Permisos (Presupuesto):</span>
                <span v-for="perm in presupuestoPermissions" :key="perm" class="px-2.5 py-1 bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-300 rounded-full font-medium">
                    {{ perm }}
                </span>
            </div>
              
            <div class="px-4 sm:px-0">
                <div class="rounded-lg overflow-hidden w-fit">
                    <PrimaryButton class="rounded-none" @click="data.createOpen = true">
                        <!-- v-if="can(['create Presupuesto'])" -->
                        {{ lang().t('app.button.new') }}
                    </PrimaryButton>

                    <!-- create Presupuesto = {{can(['create Presupuesto'])}} -->
                    <!-- v-if="can(['create Presupuesto'])" -->
                    <Create :numberPermissions="props.numberPermissions" :titulos="titulos" :show="data.createOpen"
                        @close="data.createOpen = false" :title="props.title" :losSelect=props.losSelect />

                    <Edit v-if="can(['update Presupuesto'])" :titulos="titulos"
                        :numberPermissions="props.numberPermissions" :show="data.editOpen"
                        @close="data.editOpen = false" :Presupuestoa="data.Presupuestoo" :title="props.title"
                        :losSelect=props.losSelect />

                    <Delete v-if="can(['delete Presupuesto'])" :numberPermissions="props.numberPermissions"
                        :show="data.deleteOpen" @close="data.deleteOpen = false" :Presupuestoa="data.Presupuestoo"
                        :title="props.title" />
                </div>
            </div>
            <div class="bg-white dark:bg-gray-900 border border-gray-200/80 dark:border-gray-800 rounded-xl shadow-xs overflow-hidden">
                <div class="flex justify-between items-center p-3 border-b border-gray-200/80 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-900/40">
                    <div class="flex items-center space-x-2">
                        <SelectInput v-model="data.params.perPage" :dataSet="data.dataSet" />
                    </div>
                    <TextInput v-if="props.numberPermissions > 1" v-model="data.params.search" type="text"
                        class="block w-48 md:w-64 rounded-lg text-xs" :placeholder="lang().t('app.placeholder.search')" />
                </div>
                <div class="overflow-x-auto scrollbar-table">
                    <table v-if="props.total > 0" class="w-full text-left border-collapse text-xs">
                        <thead class="bg-gray-50/80 dark:bg-gray-800/60 border-b border-gray-200/80 dark:border-gray-700/80 text-gray-500 dark:text-gray-400 font-semibold uppercase tracking-wider">
                            <tr>
                                <th class="py-2.5 px-3 text-center w-10">
                                    <Checkbox v-model:checked="data.multipleSelect" @change="selectAll" />
                                </th>
                                <th v-if="numberPermissions > 1" class="py-2.5 px-3 text-center">Acciones</th>
                                <th class="py-2.5 px-3 text-center w-12">#</th>
                                <th v-for="titulo in titulos" :key="titulo.order" class="py-2.5 px-3 cursor-pointer hover:text-gray-700 dark:hover:text-gray-200 transition-colors"
                                    v-on:click="order(titulo['order'])">
                                    <div class="flex items-center justify-between space-x-1">
                                        <span>{{ lang().t("app.label." + titulo['label']) }}</span>
                                        <ChevronUpDownIcon class="w-3.5 h-3.5 opacity-60" />
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800/60">
                            <tr v-for="(claseFromController, indexu) in props.fromController.data" :key="claseFromController.id"
                                class="hover:bg-indigo-50/40 dark:hover:bg-indigo-950/20 transition-colors duration-150">
                                <td class="py-2 px-3 text-center">
                                    <input
                                        class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-primary dark:text-primary shadow-xs focus:ring-primary/80 dark:focus:ring-primary"
                                        type="checkbox" @change="select" :value="claseFromController.id"
                                        v-model="data.selectedId" />
                                </td>
                                <td v-if="numberPermissions > 1" class="py-2 px-3 text-center">
                                    <div class="inline-flex rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden shadow-xs">
                                        <InfoButton v-show="can(['update Presupuesto'])" type="button"
                                            @click="(data.editOpen = true), (data.Presupuestoo = claseFromController)"
                                            class="px-2 py-1 rounded-none hover:bg-blue-50 dark:hover:bg-blue-900/30"
                                            v-tooltip="lang().t('app.tooltip.edit')">
                                            <PencilIcon class="w-3.5 h-3.5" />
                                        </InfoButton>
                                        <DangerButton v-show="can(['delete Presupuesto'])" type="button"
                                            @click="(data.deleteOpen = true), (data.Presupuestoo = claseFromController)"
                                            class="px-2 py-1 rounded-none hover:bg-red-50 dark:hover:bg-red-900/30"
                                            v-tooltip="lang().t('app.tooltip.delete')">
                                            <TrashIcon class="w-3.5 h-3.5" />
                                        </DangerButton>
                                    </div>
                                </td>
                                <td class="py-2 px-3 text-center font-mono text-xs text-gray-500 dark:text-gray-400">{{ indexu + 1 }}</td>
                                <td v-for="titulo in titulos" :key="titulo.order" class="py-2 px-3 whitespace-nowrap text-gray-700 dark:text-gray-300">
                                    <span v-if="titulo['type'] === 'text' || titulo['type'] === 'string'" class="font-medium text-gray-900 dark:text-gray-100"> {{
                                        claseFromController[titulo['order']] }} </span>
                                    <span v-if="titulo['type'] === 'number'"> {{
                                        zilef_nuber_format(claseFromController[titulo['order']], 0, false) }} </span>
                                    <span v-if="titulo['type'] === 'dinero'" class="font-mono text-emerald-600 dark:text-emerald-400"> {{
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
                                <td v-if="numberPermissions > 1"
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
                <div v-if="props.total > 0"
                    class="flex justify-between items-center p-2 border-t border-gray-200 dark:border-gray-700">
                    <Pagination :links="props.fromController" :filters="data.params" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
