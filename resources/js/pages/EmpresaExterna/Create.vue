<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import InputLabel from '@/components/InputLabel.vue';
import Modal from '@/components/Modal.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import { BuildingOffice2Icon, ClipboardDocumentListIcon, PhoneIcon } from '@heroicons/vue/24/solid';
import { useForm } from '@inertiajs/vue3';
import { watchEffect } from 'vue';

const props = withDefaults(
    defineProps<{
        show: boolean;
        title?: string;
        titulos?: any[];
        losSelect?: any;
        numberPermissions?: number;
    }>(),
    {
        show: false,
        title: '',
        titulos: () => [],
        losSelect: () => ({}),
        numberPermissions: 0,
    }
);

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const form = useForm({
    nombre: '',
    actividad: '',
    direccion: '',
    numero_contacto: '',
});

const create = () => {
    form.post(route('EmpresaExterna.store'), {
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
        form.reset();
        if ((props.numberPermissions ?? 0) > 99) {
            const valueRAn = Math.floor(Math.random() * 9 + 1);
            form.nombre = 'Empresa Genérica ' + valueRAn;
            form.actividad = 'Confección';
            form.direccion = 'Av. Siempreviva ' + valueRAn;
            form.numero_contacto = '70' + String(valueRAn).repeat(6);
        }
    }
});
</script>

<template>
    <section class="space-y-6">
        <Modal :show="props.show" @close="emit('close')" :maxWidth="'xl4'">
            <form class="p-0" @submit.prevent="create">
                <!-- Encabezado -->
                <div class="px-6 pt-6 pb-4 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-indigo-50 via-white to-violet-50 dark:from-gray-800 dark:via-gray-800 dark:to-gray-800 rounded-t-lg">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-600 text-white">
                            <BuildingOffice2Icon class="w-5 h-5" />
                        </span>
                        {{ lang().t('app.label.add') }} {{ props.title }}
                    </h2>
                    <p class="mt-1.5 text-sm text-gray-500 dark:text-gray-400 pl-10">
                        Complete los datos de la nueva empresa externa.
                    </p>
                </div>

                <div class="px-6 py-5 space-y-6 max-h-[70vh] overflow-y-auto scrollbar-table">
                    <!-- Información principal -->
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 flex items-center gap-1.5 mb-4">
                            <ClipboardDocumentListIcon class="w-4 h-4 text-gray-400 dark:text-gray-500" />
                            Información principal
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                            <div class="md:col-span-2">
                                <InputLabel for="nombre" :value="lang().t('app.label.nombre') || 'Nombre'" />
                                <TextInput id="nombre" type="text" class="mt-1 block w-full"
                                    v-model="form.nombre" required placeholder="Nombre de la empresa"
                                    :error="form.errors.nombre" />
                                <InputError class="mt-2" :message="form.errors.nombre" />
                            </div>

                            <div>
                                <InputLabel for="actividad" :value="lang().t('app.label.actividad') || 'Actividad'" />
                                <TextInput id="actividad" type="text" class="mt-1 block w-full"
                                    v-model="form.actividad" required placeholder="Ej. Confección, Bordado, Grabado"
                                    :error="form.errors.actividad" />
                                <InputError class="mt-2" :message="form.errors.actividad" />
                            </div>

                            <div>
                                <InputLabel for="numero_contacto" :value="lang().t('app.label.numero_contacto') || 'Número de contacto'" />
                                <TextInput id="numero_contacto" type="text" class="mt-1 block w-full"
                                    v-model="form.numero_contacto" placeholder="Teléfono o celular"
                                    :error="form.errors.numero_contacto" />
                                <InputError class="mt-2" :message="form.errors.numero_contacto" />
                            </div>
                        </div>
                    </div>

                    <!-- Datos opcionales -->
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 flex items-center gap-1.5 mb-1">
                            <PhoneIcon class="w-4 h-4 text-gray-400 dark:text-gray-500" />
                            Datos opcionales
                        </h3>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mb-3 pl-6">
                            Puede dejar estos campos vacíos y completarlos después.
                        </p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 rounded-xl border border-dashed border-gray-300 dark:border-gray-600 p-4">
                            <div class="md:col-span-2">
                                <InputLabel for="direccion" :value="lang().t('app.label.direccion') || 'Dirección'" />
                                <TextInput id="direccion" type="text" class="mt-1 block w-full"
                                    v-model="form.direccion" placeholder="Dirección de la empresa (opcional)"
                                    :error="form.errors.direccion" />
                                <InputError class="mt-2" :message="form.errors.direccion" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pie -->
                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-3 bg-gray-50 dark:bg-gray-800/50 rounded-b-lg">
                    <SecondaryButton :disabled="form.processing" @click="emit('close')">
                        {{ lang().t('app.button.close') }}
                    </SecondaryButton>
                    <PrimaryButton class="ml-3" :class="{ 'opacity-25': form.processing }" :disabled="form.processing"
                        @click="create">
                        {{ lang().t('app.button.add') }} {{ form.processing ? '...' : '' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    </section>
</template>
