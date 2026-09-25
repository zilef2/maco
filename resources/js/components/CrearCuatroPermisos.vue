<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import InputLabel from '@/components/InputLabel.vue';
import Modal from '@/components/Modal.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import Checkbox from '@/components/Checkbox.vue';
import { useForm } from '@inertiajs/vue3';
import { reactive, watchEffect } from 'vue';

const props = defineProps({
    show: Boolean,
    modulo: {
        type: String,
        default: 'materias_primas',
    },
});

const emit = defineEmits(['close']);

const data = reactive({
    multipleSelect: true,
});

const form = useForm({
    modulo: props.modulo || 'materias_primas',
    permissions: [] as string[],
});

const getPermissionsList = (mod: string) => {
    const clean = mod.trim();
    if (!clean) return [];
    return [
        `create ${clean}`,
        `read ${clean}`,
        `update ${clean}`,
        `delete ${clean}`,
    ];
};

watchEffect(() => {
    if (props.show) {
        form.modulo = props.modulo || 'materias_primas';
        form.permissions = getPermissionsList(form.modulo);
        data.multipleSelect = true;
        form.errors = {};
    }
});

const onModuloInput = () => {
    form.permissions = getPermissionsList(form.modulo);
    data.multipleSelect = true;
};

const selectAll = (event: any) => {
    if (event.target.checked === false) {
        form.permissions = [];
    } else {
        form.permissions = getPermissionsList(form.modulo);
    }
};

const select = () => {
    const all = getPermissionsList(form.modulo);
    data.multipleSelect = all.length > 0 && form.permissions.length === all.length;
};

const create = () => {
    form.post(route('permission.store-modulo'), {
        preserveScroll: true,
        onSuccess: () => {
            emit('close');
            form.reset();
        },
        onError: () => null,
        onFinish: () => null,
    });
};
</script>

<template>
    <section class="space-y-6">
        <Modal :show="props.show" @close="emit('close')">
            <form class="p-6" @submit.prevent="create">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Crear 4 permisos
                </h2>
                <div class="my-6 space-y-4">
                    <div>
                        <InputLabel for="modulo" value="Nombre del módulo / string" />
                        <TextInput
                            id="modulo"
                            type="text"
                            class="mt-1 block w-full"
                            v-model="form.modulo"
                            @input="onModuloInput"
                            required
                            placeholder="Ej: materias_primas"
                            :error="form.errors.modulo"
                        />
                        <InputError class="mt-2" :message="form.errors.modulo" />
                    </div>

                    <div>
                        <InputLabel value="Permisos a generar" />
                        <div class="flex justify-start items-center space-x-2 mt-2">
                            <Checkbox id="permission-all" v-model:checked="data.multipleSelect" @change="selectAll" />
                            <InputLabel for="permission-all" value="Seleccionar todos" />
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-2">
                            <div
                                class="flex items-center justify-start space-x-2 p-2 rounded bg-gray-50 dark:bg-gray-700/50"
                                v-for="(perm, index) in getPermissionsList(form.modulo)"
                                :key="index"
                            >
                                <input
                                    @change="select"
                                    class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-primary dark:text-primary shadow-sm focus:ring-primary/80 dark:focus:ring-primary dark:focus:ring-offset-gray-800 dark:checked:bg-primary dark:checked:border-primary"
                                    type="checkbox"
                                    :id="'perm_' + index"
                                    :value="perm"
                                    v-model="form.permissions"
                                />
                                <InputLabel :for="'perm_' + index" :value="perm" class="font-mono text-sm" />
                            </div>
                        </div>
                        <InputError class="mt-2" :message="form.errors.permissions" />
                    </div>
                </div>

                <div class="flex justify-end">
                    <SecondaryButton :disabled="form.processing" @click="emit('close')">
                        Cancelar
                    </SecondaryButton>
                    <PrimaryButton
                        class="ml-3"
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing || form.permissions.length === 0"
                        @click="create"
                    >
                        {{ form.processing ? 'Creando...' : 'Crear 4 permisos' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    </section>
</template>
