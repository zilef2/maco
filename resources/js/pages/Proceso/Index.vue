<script setup lang="ts">
// import AppLayout from '@/layouts/AppLayout.vue';
import AuthBase from '@/layouts/AuthLayout.vue';

import Breadcrumb from '@/Components/Breadcrumb.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, router } from '@inertiajs/vue3';
import { reactive, watch,PropType } from 'vue';

import DangerButton from '@/Components/DangerButton.vue';
import _ from 'lodash'
import debounce from 'lodash/debounce'
import pickBy from 'lodash/pickBy'

import Pagination from '@/Components/Pagination.vue';
import { ChevronUpDownIcon, PencilIcon, TrashIcon } from '@heroicons/vue/24/solid';
// import { CursorArrowRippleIcon, ChevronUpDownIcon,QuestionMarkCircleIcon, EyeIcon, PencilIcon, TrashIcon, UserGroupIcon } from '@heroicons/vue/24/solid';
import Create from '@/pages/Proceso/Create.vue';
import Delete from '@/pages/Proceso/Delete.vue';
import Edit from '@/pages/Proceso/Edit.vue';

import { number_format } from '@/global.ts';


// const { _, debounce, pickBy } = pkg;

const props = defineProps({
    fromController: Object,
    total: Number,
    filters: {
        type: Object as PropType<{ 
            search?: string,
            field?: string,
            order?: string,
        }>,
        required: true,
    },
    breadcrumbs: Object,
    perPage: Number,

    title: String,

    numberPermissions: Number,
    losSelect: Object,
    titulos: Array,
});

const data = reactive({
    params: {
        search: props.filters.search ?? '',
        // search2: props.filters.search2,
        //   search3: props.filters.search3,
        field: props.filters.field,
        order: props.filters.order,
        perPage: props.perPage,
    },
    Procesoo: null,
    selectedId: [] as number[],
    multipleSelect: false,
    createOpen: false,
    editOpen: false,
    deleteOpen: false,
    // deleteBulkOpen: false,
    // dataSet: usePage().props.app.perpage,
});

// <!--<editor-fold desc="order, watchclone, select">-->
const order = (field: any) => {
    data.params.field = field;
    data.params.order = data.params.order === 'asc' ? 'desc' : 'asc';
};

watch(
    () => _.cloneDeep(data.params),
    debounce(() => {
        let params = pickBy(data.params);
        router.get(route('Proceso.index'), params, {
            replace: true,
            preserveState: true,
            preserveScroll: true,
        });
    }, 150),
);

interface Proceso {
    id: number;
    // agrega los demás campos si quieres
}

const selectAll = (event: any) => {
    if (event.target.checked === false) {
        data.selectedId = [];
    } else {
        props.fromController?.data.forEach((proceso: Proceso) => {
            data.selectedId.push(proceso.id);
        });
    }
};
const select = () => (data.multipleSelect = props.fromController?.data.length === data.selectedId.length);

// <!--</editor-fold>-->

// const form = useForm({ })
// watchEffect(() => { })

// text - string // number // dinero // date // datetime // foreign
const titulos = [
    { order: 'llave_proceso', label: 'llave_proceso', type: 'number' },
    { order: 'idProceso', label: 'idProceso', type: 'number' },
    { order: 'id_conexion', label: 'id_conexion', type: 'text' },
    { order: 'fecha_proceso', label: 'fecha_proceso', type: 'datetime' },
    { order: 'fecha_ultima_actuacion', label: 'fecha_ultima_actuacion', type: 'datetime' },
    { order: 'despacho', label: 'despacho', type: 'string' },
    { order: 'departamento', label: 'departamento', type: 'string' },
    { order: 'sujetos_procesales', label: 'sujetos_procesales', type: 'text' },
    { order: 'es_privado', label: 'es_privado', type: 'string' },
    { order: 'cant_filas', label: 'cant_filas', type: 'number' },
    { order: 'validacioncini', label: 'validacioncini', type: 'bool' },
    { order: 'pdf_name', label: 'pdf_name', type: 'string' },
    { order: 'pdf_size', label: 'pdf_size', type: 'string' },
    { order: 'pdf_sumarized', label: 'pdf_sumarized', type: 'string' },
    { order: 'pdf_path', label: 'pdf_path', type: 'string' },
];
</script>

<template>
    <Head :title="props.title" />

    <AuthBase>
        <Breadcrumb :title="title" :breadcrumbs="breadcrumbs" class="text-xl font-bold capitalize" />
        <div class="space-y-4">
            <!-- {{ props.fromController.data[2] }} -->
            <div class="px-4 sm:px-0">
                <div class="w-fit overflow-hidden rounded-lg">
                    <PrimaryButton class="rounded-none" @click="data.createOpen = true" v-if="can(['create Proceso'])">
                        {{ lang().button.new }} 
                    </PrimaryButton>

                    <Create
                        v-if="can(['create Proceso'])"
                        :numberPermissions="props.numberPermissions"
                        :titulos="titulos"
                        :show="data.createOpen"
                        @close="data.createOpen = false"
                        :title="props.title"
                        :losSelect="props.losSelect"
                    />

                    <Edit
                        v-if="can(['update Proceso'])"
                        :titulos="titulos"
                        :numberPermissions="props.numberPermissions"
                        :show="data.editOpen"
                        @close="data.editOpen = false"
                        :Procesoa="data.Procesoo"
                        :title="props.title"
                        :losSelect="props.losSelect"
                    />

                    <Delete
                        v-if="can(['delete Proceso'])"
                        :numberPermissions="props.numberPermissions"
                        :show="data.deleteOpen"
                        @close="data.deleteOpen = false"
                        :Procesoa="data.Procesoo"
                        :title="props.title"
                    />
                </div>
            </div>
            <div class="relative bg-white shadow sm:rounded-lg dark:bg-gray-800">
                <div class="flex justify-between p-2">
                    <div class="flex space-x-2">
                        <!--                        <SelectInput v-model="data.params.perPage" :dataSet="data.dataSet" />-->
                        <!-- <DangerButton @click="data.deleteBulkOpen = true"
                            v-show="data.selectedId.length != 0 && can(['delete Proceso'])" class="px-3 py-1.5"
                            v-tooltip="lang().tooltip.delete_selected">
                            <TrashIcon class="w-5 h-5" />
                        </DangerButton> -->
                    </div>
                    <TextInput
                        v-if="props.numberPermissions > 1"
                        v-model="data.params.search"
                        type="text"
                        class="block w-4/6 rounded-lg md:w-3/6 lg:w-2/6"
                        placeholder="Nombre, codigo"
                    />
                </div>
                <div class="scrollbar-table overflow-x-auto">
                    <table v-if="props.total > 0" class="w-full">
                        <thead class="border-t border-gray-200 text-sm uppercase dark:border-gray-700">
                            <tr class="text-left dark:bg-gray-900/50">
                                <th class="px-2 py-4 text-center">
                                    <Checkbox v-model:checked="data.multipleSelect" @change="selectAll" />
                                </th>
                                <th v-if="numberPermissions > 1" class="px-2 py-4">Accion</th>

                                <th class="px-2 py-4 text-center">#</th>
                                <th v-for="titulo in titulos" class="cursor-pointer px-2 py-4" v-on:click="order(titulo['order'])">
                                    <div class="flex items-center justify-between">
                                        <span>{{ lang().label[titulo['label']] }}</span>
                                        <ChevronUpDownIcon class="h-4 w-4" />
                                    </div>
                                </th>
                                <!-- <th class="px-2 py-4 cursor-pointer" v-on:click="order('fecha_nacimiento')">
                                    <div class="flex justify-between items-center"> <span>{{ lang().label.edad }}</span>
                                        <ChevronUpDownIcon class="w-4 h-4" />
                                    </div>
                                </th> -->
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(claseFromController, indexu) in props.fromController.data"
                                :key="indexu"
                                class="border-t border-gray-200 hover:bg-gray-200/30 dark:border-gray-700 hover:dark:bg-gray-900/20"
                            >
                                <td class="px-2 py-4 text-center whitespace-nowrap sm:py-3">
                                    <input
                                        class="text-primary dark:text-primary focus:ring-primary/80 dark:focus:ring-primary dark:checked:bg-primary dark:checked:border-primary rounded border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:focus:ring-offset-gray-800"
                                        type="checkbox"
                                        @change="select"
                                        :value="claseFromController.id"
                                        v-model="data.selectedId"
                                    />
                                </td>
                                <td v-if="numberPermissions > 1" class="w-12 px-2 py-4 whitespace-nowrap sm:py-3">
                                    <div class="flex items-center justify-center">
                                        <div class="overflow-hidden rounded-md">
                                            <InfoButton
                                                v-show="can(['update Proceso'])"
                                                type="button"
                                                @click="((data.editOpen = true), (data.Procesoo = claseFromController))"
                                                class="rounded-none px-2 py-1.5"
                                                v-tooltip="lang().tooltip.edit"
                                            >
                                                <PencilIcon class="h-4 w-4" />
                                            </InfoButton>
                                            <DangerButton
                                                v-show="can(['delete Proceso'])"
                                                type="button"
                                                @click="((data.deleteOpen = true), (data.Procesoo = claseFromController))"
                                                class="rounded-none px-2 py-1.5"
                                                v-tooltip="lang().tooltip.delete"
                                            >
                                                <TrashIcon class="h-4 w-4" />
                                            </DangerButton>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-2 py-4 text-center whitespace-nowrap sm:py-3">{{ ++indexu }}</td>
                                <td v-for="titulo in titulos" class="px-2 py-4 whitespace-nowrap sm:py-3">
                                    <span v-if="titulo['type'] === 'text' || titulo['type'] === 'string'">
                                        {{ claseFromController[titulo['order']] }}
                                    </span>
                                    <span v-if="titulo['type'] === 'number'">
                                        {{ number_format(claseFromController[titulo['order']], 0, false) }}
                                    </span>
                                    <span v-if="titulo['type'] === 'dinero'">
                                        {{ number_format(claseFromController[titulo['order']], 0, true) }}
                                    </span>
                                    <span v-if="titulo['type'] === 'date'"> {{ formatDate(claseFromController[titulo['order']], false) }} </span>
                                    <span v-if="titulo['type'] === 'datetime'"> {{ formatDate(claseFromController[titulo['order']], true) }} </span>
                                    <span v-if="titulo['type'] === 'foreign'">
                                        {{ claseFromController?.[titulo?.['nameid']] ?? '' }}
                                    </span>
                                </td>
                            </tr>
                            <tr class="border-t border-gray-600">
                                <td v-if="numberPermissions > 1" class="w-12 px-2 py-4 text-center whitespace-nowrap sm:py-3">-</td>
                                <td class="px-2 py-4 text-center whitespace-nowrap sm:py-3">Total:</td>
                                <td class="px-2 py-4 text-center whitespace-nowrap sm:py-3">
                                    {{ props.total }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <h2 v-else class="my-8 text-center text-xl">Sin Registros</h2>
                </div>
                <div v-if="props.total > 0" class="flex items-center justify-between border-t border-gray-200 p-2 dark:border-gray-700">
                    <Pagination :links="props.fromController" :filters="data.params" />
                </div>
            </div>
        </div>
    </AuthBase>
</template>
