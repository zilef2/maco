<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';
import { debounce, pickBy, cloneDeep } from 'lodash';
import { router } from '@inertiajs/vue3';
import { ChevronUpDownIcon, PencilIcon, TrashIcon } from '@heroicons/vue/24/solid';
import Create from '@/pages/Permission/Create.vue';
import Edit from '@/pages/Permission/Edit.vue';
import Delete from '@/pages/Permission/Delete.vue';
import DeleteBulk from '@/pages/Permission/DeleteBulk.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import DangerButton from '@/components/DangerButton.vue';
import InfoButton from '@/components/InfoButton.vue';
import TextInput from '@/components/TextInput.vue';
import Checkbox from '@/components/Checkbox.vue';

interface Permission {
    id: number;
    name: string;
    guard_name: string;
    created_at: string;
    updated_at: string;
}

interface Filters {
    search: string;
    field: string;
    order: string;
}

interface Permissions {
    data: Permission[];
    links: any;
}

interface BreadcrumbItem {
    title: string;
    url: string;
}

const props = defineProps<{
    title: string;
    filters: Filters;
    permissions: Permissions;
    breadcrumbs: BreadcrumbItem[];
    perPage: number;
    numberPermissions: number;
}>();

const page = usePage();

const data = reactive({
    params: {
        search: props.filters?.search,
        field: props.filters?.field,
        order: props.filters?.order,
        perPage: props.perPage,
    },
    selectedId: [] as number[],
    multipleSelect: false,
    createOpen: false,
    editOpen: false,
    deleteOpen: false,
    deleteBulkOpen: false,
    permission: null as Permission | null,
});

const order = (field: string) => {
    data.params.field = field;
    data.params.order = data.params.order === "asc" ? "desc" : "asc";
};

watch(() => cloneDeep(data.params), debounce(() => {
    const params = pickBy(data.params);
    router.get(route("permission.index"), params, {
        replace: true,
        preserveState: true,
        preserveScroll: true,
    });
}, 150));

const selectAll = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.checked === false) {
        data.selectedId = [];
    } else {
        props.permissions?.data.forEach((permission) => {
            data.selectedId.push(permission.id);
        });
    }
};

const select = () => {
    if (props.permissions?.data.length === data.selectedId.length) {
        data.multipleSelect = true;
    } else {
        data.multipleSelect = false;
    }
};

</script>

<template>
    <Head :title="props.title" />

    <AppLayout>
        <Breadcrumb :title="title" :breadcrumbs="breadcrumbs" />
        <div class="space-y-4">
            <div class="px-4 sm:px-0 flex items-center justify-between">
                <div>
                    <PrimaryButton v-show="can(['create permission'])" @click="data.createOpen = true">
                        {{ lang().t('app.button.add') }}
                    </PrimaryButton>
                </div>
                <Create :show="data.createOpen" @close="data.createOpen = false" :title="props.title" />
                <Edit :show="data.editOpen" @close="data.editOpen = false" :permission="data.permission"
                    :title="props.title" />
                <Delete :show="data.deleteOpen" @close="data.deleteOpen = false" :permission="data.permission"
                    :title="props.title" />
                <DeleteBulk :show="data.deleteBulkOpen"
                    @close="data.deleteBulkOpen = false, data.multipleSelect = false, data.selectedId = []"
                    :selectedId="data.selectedId" :title="props.title" />
            </div>

            <div class="bg-white dark:bg-gray-900 border border-gray-200/80 dark:border-gray-800 rounded-xl shadow-xs overflow-hidden">
                <div class="flex justify-between items-center p-3 border-b border-gray-200/80 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-900/40">
                    <div class="flex items-center space-x-2">
                        <DangerButton @click="data.deleteBulkOpen = true"
                            v-show="data.selectedId.length != 0 && can(['delete permission'])" class="px-2.5 py-1 text-xs">
                            <TrashIcon class="w-3.5 h-3.5 mr-1 inline" /> {{ lang().t('app.tooltip.delete_selected') }} ({{ data.selectedId.length }})
                        </DangerButton>
                    </div>
                    <TextInput v-if="props.numberPermissions > 1" v-model="data.params.search" type="text"
                        class="block w-48 md:w-64 rounded-lg text-xs"
                        :placeholder="lang().t('app.placeholder.search')" />
                </div>
                <div class="overflow-x-auto scrollbar-table">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead class="bg-gray-50/80 dark:bg-gray-800/60 border-b border-gray-200/80 dark:border-gray-700/80 text-gray-500 dark:text-gray-400 font-semibold uppercase tracking-wider">
                            <tr>
                                <th class="py-2.5 px-3 text-center w-10">
                                    <Checkbox v-model:checked="data.multipleSelect" @change="selectAll" />
                                </th>
                                <th class="py-2.5 px-3 text-center w-12">#</th>
                                <th class="py-2.5 px-3 cursor-pointer hover:text-gray-700 dark:hover:text-gray-200 transition-colors" v-on:click="order('name')">
                                    <div class="flex items-center justify-between">
                                        <span>{{ lang().t('app.label.name') }}</span>
                                        <ChevronUpDownIcon class="w-3.5 h-3.5 opacity-60" />
                                    </div>
                                </th>
                                <th class="py-2.5 px-3 cursor-pointer hover:text-gray-700 dark:hover:text-gray-200 transition-colors" v-on:click="order('guard')">
                                    <div class="flex items-center justify-between">
                                        <span>Guard</span>
                                        <ChevronUpDownIcon class="w-3.5 h-3.5 opacity-60" />
                                    </div>
                                </th>
                                <th class="py-2.5 px-3 cursor-pointer hover:text-gray-700 dark:hover:text-gray-200 transition-colors" v-on:click="order('created_at')">
                                    <div class="flex items-center justify-between">
                                        <span>{{ lang().t('app.label.date') }}</span>
                                        <ChevronUpDownIcon class="w-3.5 h-3.5 opacity-60" />
                                    </div>
                                </th>
                                <th class="py-2.5 px-3 cursor-pointer hover:text-gray-700 dark:hover:text-gray-200 transition-colors" v-on:click="order('updated_at')">
                                    <div class="flex items-center justify-between">
                                        <span>{{ lang().t('app.label.updated') }}</span>
                                        <ChevronUpDownIcon class="w-3.5 h-3.5 opacity-60" />
                                    </div>
                                </th>
                                <th class="py-2.5 px-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800/60">
                            <tr v-for="(permission, index) in permissions.data" :key="permission.id"
                                class="hover:bg-indigo-50/40 dark:hover:bg-indigo-950/20 transition-colors duration-150">
                                <td class="py-2 px-3 text-center">
                                    <input
                                        class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-primary dark:text-primary shadow-xs focus:ring-primary/80 dark:focus:ring-primary"
                                        type="checkbox" @change="select" :value="permission.id"
                                        v-model="data.selectedId" />
                                </td>
                                <td class="py-2 px-3 text-center font-mono text-xs text-gray-500 dark:text-gray-400">{{ index + 1 }}</td>
                                <td class="py-2 px-3 font-medium text-gray-900 dark:text-gray-100">{{ permission.name }}</td>
                                <td class="py-2 px-3">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200/60 dark:border-emerald-800/40">
                                        {{ permission.guard_name }}
                                    </span>
                                </td>
                                <td class="py-2 px-3 text-gray-600 dark:text-gray-400 text-xs">{{ permission.created_at }}</td>
                                <td class="py-2 px-3 text-gray-600 dark:text-gray-400 text-xs">{{ permission.updated_at }}</td>
                                <td class="py-2 px-3 text-right">
                                    <div class="inline-flex rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden shadow-xs">
                                        <InfoButton v-show="can(['update permission'])" type="button"
                                            @click="(data.editOpen = true), (data.permission = permission)"
                                            class="px-2 py-1 rounded-none hover:bg-blue-50 dark:hover:bg-blue-900/30"
                                            v-tooltip="lang().t('app.tooltip.edit')">
                                            <PencilIcon class="w-3.5 h-3.5" />
                                        </InfoButton>
                                        <DangerButton v-show="can(['delete permission'])" type="button"
                                            @click="(data.deleteOpen = true), (data.permission = permission)"
                                            class="px-2 py-1 rounded-none hover:bg-red-50 dark:hover:bg-red-900/30"
                                            v-tooltip="lang().t('app.tooltip.delete')">
                                            <TrashIcon class="w-3.5 h-3.5" />
                                        </DangerButton>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
