<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import InputLabel from '@/components/InputLabel.vue';
import Modal from '@/components/Modal.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import { TrashIcon } from '@heroicons/vue/24/solid';
import { useForm } from '@inertiajs/vue3';
import { computed, watchEffect } from 'vue';

const props = defineProps({
    show: Boolean,
    title: String,
    ordenId: Number,
    numeroOp: [String, Number],
    items: Array as () => Array<{ id?: number; grupo?: number | null; talla?: string; cantidad_planificada?: number | string; cantidad_recibida_buena?: number | string | null; cantidad_pendiente_reparacion?: number | string | null }>,
    canEdit: { type: Boolean, default: true },
})

const emit = defineEmits(["close"]);

type FilaTalla = { id?: number; grupo?: number | null; talla: string; cantidad_planificada: number | string; cantidad_recibida_buena: number | string | null; cantidad_pendiente_reparacion: number | string | null };

const form = useForm({
    distribucion_tallas: [] as Array<FilaTalla>,
});

const total = computed(() => {
    return form.distribucion_tallas.reduce((acc: number, item: any) => {
        const val = Number(item.cantidad_planificada);
        return acc + (isNaN(val) ? 0 : val);
    }, 0);
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

const agregarTalla = () => {
    form.distribucion_tallas.push({ talla: '', cantidad_planificada: '', cantidad_recibida_buena: null, cantidad_pendiente_reparacion: null });
};

const eliminarTalla = (index: number) => {
    form.distribucion_tallas.splice(index, 1);
};

const closeModal = () => {
    emit("close");
    form.reset();
};

const guardar = () => {
    form.transform((data: any) => ({
        distribucion_tallas: (data.distribucion_tallas || []).map((item: any) => ({
            id: item.id,
            talla: (item.talla ?? '').trim(),
            cantidad_planificada: Number(item.cantidad_planificada) || 0,
            cantidad_recibida_buena: item.cantidad_recibida_buena === '' || item.cantidad_recibida_buena == null ? null : Number(item.cantidad_recibida_buena),
            cantidad_pendiente_reparacion: item.cantidad_pendiente_reparacion === '' || item.cantidad_pendiente_reparacion == null ? null : Number(item.cantidad_pendiente_reparacion),
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
                        <span v-if="props.numeroOp" class="text-sm font-normal text-gray-500 dark:text-gray-400">
                            — OP {{ props.numeroOp }}
                        </span>
                    </h3>
                    <button type="button" @click="closeModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        ✕
                    </button>
                </div>

                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Tallas asociadas a esta orden de producción. La cantidad planificada no es editable.
                    <span v-if="props.canEdit">Puede editar las cantidades recibidas y en reparación, agregar o quitar filas y guardar los cambios.</span>
                </p>

                <div v-if="form.distribucion_tallas.length === 0" class="mt-4 text-sm text-gray-500 dark:text-gray-400 text-center py-4">
                    Sin tallas registradas.
                </div>

                <div v-else class="mt-4 max-h-64 overflow-y-auto space-y-4">
                    <div
                        v-for="(item, index) in form.distribucion_tallas"
                        :key="item.id ?? `nueva-${index}`"
                        class="space-y-1 border-b pb-3 dark:border-gray-700"
                    >
                        <div class="flex items-center space-x-2">
                            <div class="flex-1">
                                <InputLabel :for="`talla-${index}`" value="Talla" class="sr-only" />
                                <TextInput
                                    :id="`talla-${index}`"
                                    type="text"
                                    v-model="item.talla"
                                    :readonly="!props.canEdit"
                                    placeholder="Talla (ej: S, M, L)"
                                    class="block w-full text-sm"
                                />
                            </div>
                            <div class="w-24">
                                <InputLabel :for="`planificada-${index}`" value="Planificada" class="sr-only" />
                                <TextInput
                                    :id="`planificada-${index}`"
                                    type="number"
                                    min="0"
                                    v-model="item.cantidad_planificada"
                                    :readonly="!(props.canEdit && !item.id)"
                                    title="Cantidad planificada"
                                    placeholder="Planif."
                                    class="block w-full text-sm bg-gray-100 dark:bg-gray-800"
                                />
                            </div>
                            <span v-if="item.grupo != null" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300" :title="`Grupo ${item.grupo}`">
                                G{{ item.grupo }}
                            </span>
                            <button
                                v-if="props.canEdit"
                                type="button"
                                @click="eliminarTalla(index)"
                                class="p-2 text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-md transition"
                                title="Eliminar talla"
                            >
                                <TrashIcon class="w-5 h-5" />
                            </button>
                        </div>
                        <div class="flex items-center space-x-2">
                            <div class="flex-1">
                                <InputLabel :for="`recibida-${index}`" :value="'Recibida buena'" class="sr-only" />
                                <TextInput
                                    :id="`recibida-${index}`"
                                    type="number"
                                    min="0"
                                    v-model="item.cantidad_recibida_buena"
                                    :readonly="!props.canEdit"
                                    placeholder="Recibida buena"
                                    class="block w-full text-sm"
                                />
                            </div>
                            <div class="flex-1">
                                <InputLabel :for="`reparacion-${index}`" :value="'Pendiente reparación'" class="sr-only" />
                                <TextInput
                                    :id="`reparacion-${index}`"
                                    type="number"
                                    min="0"
                                    v-model="item.cantidad_pendiente_reparacion"
                                    :readonly="!props.canEdit"
                                    placeholder="Pendiente reparación"
                                    class="block w-full text-sm"
                                />
                            </div>
                        </div>
                    </div>
                </div>
                <InputError class="mt-2" :message="(form.errors as any).distribucion_tallas" />

                <div class="mt-4 flex justify-between items-center">
                    <SecondaryButton v-if="props.canEdit" type="button" @click="agregarTalla" class="text-xs">
                        + Agregar talla
                    </SecondaryButton>
                    <span v-else></span>
                    <div class="text-right">
                        <span class="text-xs text-gray-500 dark:text-gray-400 block">Total planificado:</span>
                        <span class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ total }}</span>
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3 border-t pt-4 dark:border-gray-700">
                    <SecondaryButton type="button" @click="closeModal">
                        {{ lang().t('app.button.close') }}
                    </SecondaryButton>
                    <PrimaryButton v-if="props.canEdit" type="button" @click="guardar" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                        Guardar {{ form.processing ? '...' : '' }}
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </section>
</template>
