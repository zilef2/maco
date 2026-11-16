<script setup lang="ts">
import Checkbox from '@/components/Checkbox.vue';
import InputError from '@/components/InputError.vue';
import InputLabel from '@/components/InputLabel.vue';
import Modal from '@/components/Modal.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, watchEffect } from 'vue';

const props = defineProps({
    show: Boolean,
    title: String,
    role: Object,
    permissions: Object,
});

const emit = defineEmits(['close']);

const form = useForm({
    name: '',
    permissions: [],
});

const ACTIONS = ['read', 'create', 'update', 'delete'];
const ACTION_ORDER = ['read', 'create', 'update', 'delete'];

const update = () => {
    form.put(route('role.update', props.role?.id), {
        preserveScroll: true,
        onSuccess: () => {
            emit('close');
            form.reset();
        },
        onError: () => null,
        onFinish: () => null,
    });
};

watchEffect(() => {
    if (props.show) {
        form.errors = {};
        form.name = props.role?.name;
        form.permissions = props.role.permissions?.map((d) => d.id) ?? [];
    }
});

const groups = computed(() => {
    const result: Record<string, any[]> = {};

    (props.permissions ?? []).forEach((permission) => {
        const name = permission.name ?? '';
        const match = name.match(/^(\S+)\s+(.+)$/);
        const action = match ? match[1].toLowerCase() : null;
        const entity = match ? match[2] : 'Otros';

        if (!result[entity]) {
            result[entity] = [];
        }

        result[entity].push({
            ...permission,
            action,
            isKnown: action !== null && ACTIONS.includes(action),
        });
    });

    return Object.entries(result)
        .sort(([a], [b]) => {
            if (a === 'Otros') return 1;
            if (b === 'Otros') return -1;
            return a.localeCompare(b);
        })
        .map(([name, permissions]) => ({
            name,
            permissions: permissions.sort((a, b) => ACTION_ORDER.indexOf(a.action) - ACTION_ORDER.indexOf(b.action)),
        }));
});

const actionGroups = computed(() => {
    const result: Record<string, any[]> = {};

    groups.value.forEach((group) => {
        group.permissions.forEach((permission) => {
            if (!permission.isKnown) return;
            if (!result[permission.action]) {
                result[permission.action] = [];
            }
            result[permission.action].push(permission);
        });
    });

    return result;
});

const actionChecked = (action: string) => {
    const permissions = actionGroups.value[action] ?? [];

    return permissions.length > 0 && permissions.every((permission) => form.permissions.includes(permission.id));
};

const toggleAction = (action: string, event: Event) => {
    const ids = (actionGroups.value[action] ?? []).map((permission) => permission.id);

    if ((event.target as HTMLInputElement).checked) {
        form.permissions = Array.from(new Set([...form.permissions, ...ids]));
    } else {
        form.permissions = form.permissions.filter((id) => !ids.includes(id));
    }
};

const groupChecked = (group: any) => {
    return group.permissions.length > 0 && group.permissions.every((permission: any) => form.permissions.includes(permission.id));
};

const toggleGroup = (group: any, event: Event) => {
    const ids = group.permissions.map((permission: any) => permission.id);

    if ((event.target as HTMLInputElement).checked) {
        form.permissions = Array.from(new Set([...form.permissions, ...ids]));
    } else {
        form.permissions = form.permissions.filter((id) => !ids.includes(id));
    }
};
</script>

<template>
    <section class="space-y-6">
        <Modal :show="props.show" @close="emit('close')" :maxWidth="'xl4'">
            <form class="p-6" @submit.prevent="update">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ lang().t('app.label.edit') }} {{ props.title }}</h2>
                <div class="my-6 space-y-4">
                    <div>
                        <InputLabel for="name" :value="lang().t('app.label.role')" />
                        <TextInput
                            id="name"
                            type="text"
                            class="mt-1 block w-full"
                            v-model="form.name"
                            required
                            :placeholder="lang().t('app.placeholder.name')"
                            :error="form.errors.name"
                        />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>
                    <div>
                        <InputLabel :value="lang().t('app.label.permission')" />
                        <InputError class="mt-2" :message="form.errors.permissions" />

                        <div class="mt-2 flex flex-wrap gap-2">
                            <label
                                v-for="action in ACTIONS"
                                :key="action"
                                class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-1.5 transition-colors select-none"
                                :class="
                                    actionChecked(action)
                                        ? 'border-red-500 bg-red-50 dark:bg-red-900/20'
                                        : 'border-gray-300 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800'
                                "
                            >
                                <Checkbox :checked="actionChecked(action)" @change="toggleAction(action, $event)" />
                                <span
                                    class="text-sm font-medium"
                                    :class="actionChecked(action) ? 'text-red-600 dark:text-red-400' : 'text-gray-700 dark:text-gray-300'"
                                >
                                    {{ lang().t('app.label.action_' + action + '_all') }}
                                </span>
                            </label>
                        </div>

                        <div class="mt-4 max-h-96 space-y-3 overflow-y-auto pr-1">
                            <div v-for="group in groups" :key="group.name" class="rounded-lg border border-gray-200 dark:border-gray-700">
                                <div class="flex items-center justify-between gap-2 rounded-t-lg bg-gray-50 px-3 py-2 dark:bg-gray-800/60">
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                        {{ group.name }}
                                    </h3>
                                    <label class="flex cursor-pointer items-center gap-2 select-none">
                                        <span class="text-primary text-xs font-medium">
                                            {{ lang().t('app.label.select_all_entity') }}
                                        </span>
                                        <Checkbox :checked="groupChecked(group)" @change="toggleGroup(group, $event)" />
                                    </label>
                                </div>
                                <div class="grid grid-cols-2 gap-2 p-3 md:grid-cols-4">
                                    <div
                                        class="flex items-center justify-start space-x-2"
                                        v-for="permission in group.permissions"
                                        :key="permission.id"
                                    >
                                        <input
                                            class="text-primary dark:text-primary focus:ring-primary/80 dark:focus:ring-primary dark:checked:bg-primary dark:checked:border-primary rounded border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:focus:ring-offset-gray-800"
                                            type="checkbox"
                                            :id="'permission_' + permission.id"
                                            :value="permission.id"
                                            v-model="form.permissions"
                                        />
                                        <InputLabel
                                            :for="'permission_' + permission.id"
                                            :value="permission.isKnown ? lang().t('app.label.action_' + permission.action) : permission.name"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end">
                    <SecondaryButton :disabled="form.processing" @click="emit('close')"> {{ lang().t('app.button.close') }} </SecondaryButton>
                    <PrimaryButton class="ml-3" :class="{ 'opacity-25': form.processing }" :disabled="form.processing" @click="update">
                        {{ form.processing ? lang().t('app.button.save') + '...' : lang().t('app.button.save') }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    </section>
</template>
