<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import Modal from '@/components/Modal.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import { useForm } from '@inertiajs/vue3';
import { watchEffect } from 'vue';
import DistribucionTallasEditor from './DistribucionTallasEditor.vue';

const props = defineProps({
    show: Boolean,
    title: String,
    ordenId: Number,
    numeroOp: [String, Number],
    items: Array as () => Array<{
        id?: number;
        grupo?: number | null;
        talla?: string;
        cantidad_planificada?: number | string;
        cantidad_recibida_buena?: number | string | null;
        cantidad_pendiente_reparacion?: number | string | null;
    }>,
    canEdit: { type: Boolean, default: true },
});

const emit = defineEmits(['close']);

const form = useForm({
    distribucion_tallas: [] as Array<any>,
});

watchEffect(() => {
    if (props.show) {
        form.errors = {};
        form.distribucion_tallas = (props.items || []).map((item: any) => ({
            id: item.id,
            grupo: item.grupo ?? null,
            talla: item.talla ?? '',
            cantidad_planificada: String(item.cantidad_planificada ?? ''),
            cantidad_recibida_buena: item.cantidad_recibida_buena ?? null,
            cantidad_pendiente_reparacion: item.cantidad_pendiente_reparacion ?? null,
        }));
    }
});

const closeModal = () => {
    emit('close');
    form.reset();
};

const guardar = () => {
    form.transform((data: any) => ({
        distribucion_tallas: (data.distribucion_tallas || []).map((item: any) => ({
            id: item.id,
            talla: (item.talla ?? '').trim(),
            cantidad_planificada: Number(item.cantidad_planificada) || 0,
            cantidad_recibida_buena:
                item.cantidad_recibida_buena === '' || item.cantidad_recibida_buena == null ? null : Number(item.cantidad_recibida_buena),
            cantidad_pendiente_reparacion:
                item.cantidad_pendiente_reparacion === '' || item.cantidad_pendiente_reparacion == null
                    ? null
                    : Number(item.cantidad_pendiente_reparacion),
        })),
    })).put(route('OrdenProduccion.distribucion', props.ordenId), {
        preserveScroll: true,
        onSuccess: () => {
            closeModal();
        },
        onError: () => null,
        onFinish: () => null,
    });
};
</script>

<template>
    <section class="space-y-6">
        <Modal :show="props.show" @close="closeModal" :maxWidth="'lg'">
            <div class="p-6">
                <div class="flex items-center justify-between border-b pb-3 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Distribución por Tallas
                        <span v-if="props.numeroOp" class="text-sm font-normal text-gray-500 dark:text-gray-400"> — OP {{ props.numeroOp }} </span>
                    </h3>
                    <button type="button" @click="closeModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">✕</button>
                </div>

                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Tallas asociadas a esta orden de producción. La cantidad planificada no es editable.
                    <span v-if="props.canEdit"
                        >Puede editar las cantidades recibidas y en reparación, agregar o quitar filas y guardar los cambios.</span
                    >
                </p>

                <div class="mt-4">
                    <DistribucionTallasEditor
                        v-model:items="form.distribucion_tallas"
                        :can-edit="props.canEdit"
                        :planificada-editable="false"
                        :mostrar-recepcion="true"
                    />
                </div>
                <InputError class="mt-2" :message="(form.errors as any).distribucion_tallas" />

                <div class="mt-6 flex justify-end space-x-3 border-t pt-4 dark:border-gray-700">
                    <SecondaryButton type="button" @click="closeModal">
                        {{ lang().t('app.button.close') }}
                    </SecondaryButton>
                    <PrimaryButton
                        v-if="props.canEdit"
                        type="button"
                        @click="guardar"
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                    >
                        Guardar {{ form.processing ? '...' : '' }}
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </section>
</template>
