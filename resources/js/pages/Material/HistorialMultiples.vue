<script setup lang="ts">
import Modal from '@/components/Modal.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import { formatDate } from '@/global.ts';
import axios from 'axios';
import { reactive, watch } from 'vue';

const props = defineProps({
    show: Boolean,
    materiales: {
        type: Array as () => any[],
        default: () => [],
    },
});

const emit = defineEmits(['close']);

type HistorialCarga = {
    material: any;
    loading: boolean;
    error: string;
    movimientos: any[];
};

const state = reactive({
    historiales: [] as HistorialCarga[],
});

const cargarHistoriales = async () => {
    state.historiales = props.materiales.map((material) => ({
        material,
        loading: true,
        error: '',
        movimientos: [],
    }));

    await Promise.all(
        state.historiales.map(async (h) => {
            try {
                const response = await axios.get(route('Material.movimientos', h.material.id));
                const res = response.data?.movimientos;
                h.movimientos = Array.isArray(res) ? res : (res?.data ?? []);
            } catch {
                h.error = 'No fue posible cargar el historial.';
            } finally {
                h.loading = false;
            }
        })
    );
};

watch(
    () => props.show,
    (isOpen) => {
        if (isOpen && props.materiales.length) {
            cargarHistoriales();
        } else {
            state.historiales = [];
        }
    }
);
</script>

<template>
    <section class="space-y-6">
        <Modal :show="props.show" @close="emit('close')" :maxWidth="'4xl'">
            <div class="p-6">
                <div class="border-b border-gray-200 dark:border-gray-700 pb-3 mb-4">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        Historial de Existencias ({{ props.materiales.length }} material{{ props.materiales.length === 1 ? '' : 'es' }})
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Movimientos de los materiales seleccionados.
                    </p>
                </div>

                <div class="space-y-8 max-h-[32rem] overflow-y-auto scrollbar-table pr-1">
                    <div v-for="h in state.historiales" :key="h.material.id">
                        <div class="flex justify-between items-start bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 px-4 py-3 mb-3">
                            <div>
                                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                    {{ h.material.nombre_base }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                    Código: {{ h.material.codigo_material || '-' }}
                                </p>
                            </div>
                            <div class="text-right">
                                <span class="text-xs text-gray-500 dark:text-gray-400">Existencia actual</span>
                                <p class="text-sm font-bold text-gray-900 dark:text-gray-100">
                                    {{ h.material.existencia_actual ?? 0 }} {{ h.material.unidad_inventario || '' }}
                                </p>
                            </div>
                        </div>

                        <div v-if="h.loading" class="py-8 text-center text-gray-500 dark:text-gray-400 text-sm">
                            Cargando histórico...
                        </div>

                        <div v-else-if="h.error" class="p-3 bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 rounded-lg text-sm">
                            {{ h.error }}
                        </div>

                        <div v-else-if="h.movimientos.length === 0" class="py-6 text-center text-gray-500 dark:text-gray-400 text-sm">
                            No se registran movimientos para este material.
                        </div>

                        <div v-else class="overflow-x-auto">
                            <table class="w-full text-sm text-left">
                                <thead class="text-xs uppercase bg-gray-50 dark:bg-gray-700/50 text-gray-600 dark:text-gray-300 border-b dark:border-gray-700">
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
                                        v-for="mov in h.movimientos"
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
                    </div>
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
