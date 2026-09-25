<script setup lang="ts">
import Modal from '@/components/Modal.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import { formatDate } from '@/global.ts';
import axios from 'axios';
import { reactive, watch } from 'vue';

const props = defineProps({
    show: Boolean,
    materiaPrima: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close']);

const state = reactive({
    loading: false,
    movimientos: [] as any[],
    error: '',
});

const cargarMovimientos = async () => {
    if (!props.materiaPrima?.id) return;

    state.loading = true;
    state.error = '';
    try {
        const response = await axios.get(route('materias_primas.movimientos', props.materiaPrima.id));
        const res = response.data?.movimientos;
        state.movimientos = Array.isArray(res) ? res : (res?.data ?? []);
    } catch (e: any) {
        state.error = 'No fue posible cargar el historial.';
    } finally {
        state.loading = false;
    }
};

watch(
    () => props.show,
    (isOpen) => {
        if (isOpen && props.materiaPrima) {
            cargarMovimientos();
        } else {
            state.movimientos = [];
        }
    }
);
</script>

<template>
    <section class="space-y-6">
        <Modal :show="props.show" @close="emit('close')" :maxWidth="'2xl'">
            <div class="p-6">
                <div class="flex justify-between items-start border-b border-gray-200 dark:border-gray-700 pb-3 mb-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            Historial de Existencias
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            {{ props.materiaPrima?.nombre }} (Código: {{ props.materiaPrima?.codigo || '-' }})
                        </p>
                    </div>
                    <div class="text-right">
                        <span class="text-xs text-gray-500 dark:text-gray-400">Existencia actual</span>
                        <p class="text-base font-bold text-gray-900 dark:text-gray-100">
                            {{ props.materiaPrima?.existencia_actual ?? 0 }} {{ props.materiaPrima?.unidad_medida || '' }}
                        </p>
                    </div>
                </div>

                <div v-if="state.loading" class="py-12 text-center text-gray-500 dark:text-gray-400">
                    <svg class="animate-spin h-8 w-8 mx-auto text-primary mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Cargando histórico de movimientos...
                </div>

                <div v-else-if="state.error" class="p-4 bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 rounded-lg text-sm">
                    {{ state.error }}
                </div>

                <div v-else-if="state.movimientos.length === 0" class="py-8 text-center text-gray-500 dark:text-gray-400 text-sm">
                    No se registran movimientos históricos para esta materia prima.
                </div>

                <div v-else class="overflow-x-auto max-h-96 scrollbar-table">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs uppercase bg-gray-50 dark:bg-gray-700/50 text-gray-600 dark:text-gray-300 border-b dark:border-gray-700 sticky top-0">
                            <tr>
                                <th class="px-3 py-2.5">Fecha</th>
                                <th class="px-3 py-2.5">Tipo</th>
                                <th class="px-3 py-2.5 text-center">Cambio</th>
                                <th class="px-3 py-2.5 text-center">Existencia</th>
                                <th class="px-3 py-2.5">Motivo</th>
                                <th class="px-3 py-2.5">Usuario</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr
                                v-for="mov in state.movimientos"
                                :key="mov.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700/30"
                            >
                                <td class="px-3 py-2.5 whitespace-nowrap text-xs text-gray-600 dark:text-gray-400">
                                    {{ formatDate(mov.created_at, 'conLaHora') }}
                                </td>
                                <td class="px-3 py-2.5 whitespace-nowrap">
                                    <span
                                        class="px-2 py-0.5 text-xs font-semibold rounded-full capitalize"
                                        :class="{
                                            'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300': mov.tipo_movimiento === 'entrada' || (Number(mov.existencia_nueva) > Number(mov.existencia_anterior) && mov.tipo_movimiento !== 'salida'),
                                            'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300': mov.tipo_movimiento === 'salida' || (Number(mov.existencia_nueva) < Number(mov.existencia_anterior) && mov.tipo_movimiento !== 'entrada'),
                                            'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300': mov.tipo_movimiento === 'inicial',
                                            'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300': mov.tipo_movimiento === 'ajuste',
                                        }"
                                    >
                                        {{ mov.tipo_movimiento }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5 whitespace-nowrap text-center font-medium">
                                    <span
                                        v-if="Number(mov.existencia_nueva) > Number(mov.existencia_anterior)"
                                        class="text-green-600 dark:text-green-400 font-semibold"
                                    >
                                        +{{ Number(mov.cantidad) }} (Aumentó)
                                    </span>
                                    <span
                                        v-else-if="Number(mov.existencia_nueva) < Number(mov.existencia_anterior)"
                                        class="text-red-600 dark:text-red-400 font-semibold"
                                    >
                                        -{{ Number(mov.cantidad) }} (Disminuyó)
                                    </span>
                                    <span v-else class="text-gray-500 dark:text-gray-400">
                                        {{ Number(mov.cantidad) }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5 whitespace-nowrap text-center text-xs text-gray-600 dark:text-gray-300">
                                    <span class="text-gray-400 dark:text-gray-500">{{ Number(mov.existencia_anterior) }}</span>
                                    <span class="mx-1 font-bold">→</span>
                                    <span class="font-semibold text-gray-900 dark:text-gray-100">{{ Number(mov.existencia_nueva) }}</span>
                                </td>
                                <td class="px-3 py-2.5 text-xs text-gray-700 dark:text-gray-300 max-w-xs truncate" :title="mov.motivo || ''">
                                    {{ mov.motivo || '-' }}
                                </td>
                                <td class="px-3 py-2.5 whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">
                                    {{ mov.user?.name || '-' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end mt-6 pt-3 border-t border-gray-200 dark:border-gray-700">
                    <SecondaryButton @click="emit('close')">
                        Cerrar
                    </SecondaryButton>
                </div>
            </div>
        </Modal>
    </section>
</template>
