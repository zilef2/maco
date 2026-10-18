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
    productoId: Number,
    productoLabel: [String, Number],
    items: Array as () => Array<{ id?: number; talla?: string; cantidad_planificada?: number | string }>,
})

const emit = defineEmits(["close"]);

type FilaTalla = { id?: number; talla: string; cantidad_planificada: number | string };

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
            talla: item.talla ?? '',
            cantidad_planificada: String(item.cantidad_planificada ?? ''),
        }));
    }
});

const agregarTalla = () => {
    form.distribucion_tallas.push({ talla: '', cantidad_planificada: '' });
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
        })),
    })).put(route('Producto.distribucion', props.productoId), {
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
                        <span v-if="props.productoLabel" class="text-sm font-normal text-gray-500 dark:text-gray-400">
                            — {{ props.productoLabel }}
                        </span>
                    </h3>
                    <button type="button" @click="closeModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        ✕
                    </button>
                </div>

                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Tallas y cantidades planificadas asociadas a este producto. Esta distribución sirve como plantilla para las órdenes de producción.
                </p>

                <div v-if="form.distribucion_tallas.length === 0" class="mt-4 text-sm text-gray-500 dark:text-gray-400 text-center py-4">
                    Sin tallas registradas.
                </div>

                <div v-else class="mt-4 max-h-64 overflow-y-auto space-y-2">
                    <div
                        v-for="(item, index) in form.distribucion_tallas"
                        :key="item.id ?? `nueva-${index}`"
                        class="flex items-center space-x-2 border-b pb-2 dark:border-gray-700"
                    >
                        <div class="flex-1">
                            <InputLabel :for="`talla-${index}`" value="Talla" class="sr-only" />
                            <TextInput
                                :id="`talla-${index}`"
                                type="text"
                                v-model="item.talla"
                                placeholder="Talla (ej: S, M, L)"
                                class="block w-full text-sm"
                            />
                        </div>
                        <div class="w-32">
                            <InputLabel :for="`planificada-${index}`" value="Cantidad" class="sr-only" />
                            <TextInput
                                :id="`planificada-${index}`"
                                type="number"
                                min="0"
                                v-model="item.cantidad_planificada"
                                placeholder="Cantidad"
                                class="block w-full text-sm"
                            />
                        </div>
                        <button
                            type="button"
                            @click="eliminarTalla(index)"
                            class="p-2 text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-md transition"
                            title="Eliminar talla"
                        >
                            <TrashIcon class="w-5 h-5" />
                        </button>
                    </div>
                </div>
                <InputError class="mt-2" :message="(form.errors as any).distribucion_tallas" />

                <div class="mt-4 flex justify-between items-center">
                    <SecondaryButton type="button" @click="agregarTalla" class="text-xs">
                        + Agregar talla
                    </SecondaryButton>
                    <div class="text-right">
                        <span class="text-xs text-gray-500 dark:text-gray-400 block">Total planificado:</span>
                        <span class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ total }}</span>
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3 border-t pt-4 dark:border-gray-700">
                    <SecondaryButton type="button" @click="closeModal">
                        {{ lang().t('app.button.close') }}
                    </SecondaryButton>
                    <PrimaryButton type="button" @click="guardar" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                        Guardar {{ form.processing ? '...' : '' }}
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </section>
</template>
