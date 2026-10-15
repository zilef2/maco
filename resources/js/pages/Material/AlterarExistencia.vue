<script setup lang="ts">
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
    material: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close']);

const form = useForm({
    existencia_actual: 0 as number,
    motivo: '',
});

watchEffect(() => {
    if (props.show && props.material) {
        form.existencia_actual = 0;
        form.motivo = '';
        form.errors = {};
        setTimeout(() => {
            (document.getElementById('existencia_actual_material') as HTMLInputElement | null)?.focus();
        }, 150);
    }
});

const diferencia = computed(() => {
    const anterior = Number(props.material?.existencia_actual ?? 0);
    const nueva = Number(form.existencia_actual ?? 0);
    return nueva - anterior;
});

const update = () => {
    if (!props.material?.id) return;

    if (Number(form.existencia_actual) < 0) {
        form.setError('existencia_actual', 'La existencia no puede ser negativa.');
        return;
    }

    form.put(route('Material.update', props.material.id), {
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
        <Modal :show="props.show" @close="emit('close')" :maxWidth="'md'">
            <form class="p-6 space-y-5" @submit.prevent="update">
                <div class="border-b border-gray-200 dark:border-gray-700 pb-3">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        Alterar Existencia Actual
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        {{ props.material?.nombre_base }} (Código: {{ props.material?.codigo_material || '-' }})
                    </p>
                </div>

                <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700">
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-600 dark:text-gray-400">Existencia actual registrada:</span>
                        <span class="font-bold text-gray-900 dark:text-gray-100 text-base">
                            {{ props.material?.existencia_actual ?? 0 }} {{ props.material?.unidad_inventario || '' }}
                        </span>
                    </div>
                </div>

                <div>
                    <InputLabel for="existencia_actual_material" value="Nueva Existencia Actual *" />
                    <TextInput
                        id="existencia_actual_material"
                        type="number"
                        step="any"
                        min="0"
                        class="mt-1 block w-full"
                        v-model="form.existencia_actual"
                        required
                        placeholder="Ingrese la nueva existencia"
                        :error="form.errors.existencia_actual"
                    />
                    <InputError class="mt-2" :message="form.errors.existencia_actual" />

                    <div class="mt-2 text-xs flex items-center space-x-1" v-if="diferencia !== 0">
                        <span class="text-gray-500 dark:text-gray-400">Variación:</span>
                        <span
                            :class="{
                                'text-green-600 dark:text-green-400 font-semibold': diferencia > 0,
                                'text-red-600 dark:text-red-400 font-semibold': diferencia < 0,
                            }"
                        >
                            {{ diferencia > 0 ? '+' + diferencia : diferencia }}
                            ({{ diferencia > 0 ? 'Aumento / Entrada' : 'Disminución / Salida' }})
                        </span>
                    </div>
                </div>

                <div>
                    <InputLabel for="motivo_material" value="Motivo del cambio" />
                    <TextInput
                        id="motivo_material"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.motivo"
                        placeholder="Ej: Ajuste por inventario físico, merma, compra..."
                        :error="form.errors.motivo"
                    />
                    <InputError class="mt-2" :message="form.errors.motivo" />
                </div>

                <div class="flex justify-end pt-3 border-t border-gray-200 dark:border-gray-700">
                    <SecondaryButton :disabled="form.processing" @click="emit('close')">
                        Cancelar
                    </SecondaryButton>
                    <PrimaryButton
                        class="ml-3"
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                        @click="update"
                    >
                        {{ form.processing ? 'Guardando...' : 'Guardar Existencia' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    </section>
</template>
