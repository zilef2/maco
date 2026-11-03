<script setup lang="ts">
import Modal from '@/components/Modal.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import { zilef_nuber_format } from '@/global.ts';
import { computed } from 'vue';

const props = defineProps({
    show: Boolean,
    op: Object,
});

const emit = defineEmits(['close']);

// Necesidad e inventario por material, sin importar el estado de la OP.
const materiales = computed<any[]>(() => (props.op as any)?.materiales_detalle ?? []);

const faltante = (fila: any): number => Math.max(0, (Number(fila?.requerida) || 0) - (Number(fila?.disponible) || 0));

const cuantosFaltan = computed(() => materiales.value.filter((fila) => faltante(fila) > 0).length);

const cantidad = (valor: any, decimales = 2): string => {
    if (valor === null || valor === undefined || valor === '') return '—';
    const numero = Number(valor);
    if (!Number.isFinite(numero)) return '—';
    return zilef_nuber_format(numero, decimales, false);
};

const productosTexto = (fila: any): string =>
    Array.isArray(fila?.productos) && fila.productos.length ? fila.productos.join(', ') : '—';
</script>

<template>
    <Modal :show="props.show" @close="emit('close')" maxWidth="xl6" closeable>
        <div class="p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Materiales de la OP
                        <span v-if="props.op?.numero_op" class="text-sm font-normal text-gray-500 dark:text-gray-400">
                            — {{ props.op.numero_op }}
                        </span>
                    </h2>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Cantidad necesaria según la ficha técnica y existencia actual del inventario.
                    </p>
                </div>

                <SecondaryButton type="button" @click="emit('close')">
                    Cerrar
                </SecondaryButton>
            </div>

            <div v-if="materiales.length" class="mt-4 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400">
                                <th scope="col" class="px-3 py-2 font-semibold">Material</th>
                                <th scope="col" class="w-24 px-3 py-2 font-semibold">Unidad</th>
                                <th scope="col" class="w-28 px-3 py-2 text-right font-semibold">Se necesita</th>
                                <th scope="col" class="w-28 px-3 py-2 text-right font-semibold">Hay</th>
                                <th scope="col" class="w-28 px-3 py-2 text-right font-semibold">Falta</th>
                                <th scope="col" class="px-3 py-2 font-semibold">Productos</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                            <tr v-for="fila in materiales" :key="fila.material_id">
                                <td class="px-3 py-2 text-sm text-gray-700 dark:text-gray-300">{{ fila.nombre }}</td>
                                <td class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">{{ fila.unidad || '—' }}</td>
                                <td class="px-3 py-2 text-right tabular-nums font-medium text-gray-900 dark:text-gray-100">
                                    {{ cantidad(fila.requerida) }}
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums text-gray-500 dark:text-gray-400">
                                    {{ cantidad(fila.disponible) }}
                                </td>
                                <td
                                    class="px-3 py-2 text-right tabular-nums font-semibold"
                                    :class="faltante(fila) > 0 ? 'text-rose-700 dark:text-rose-300' : 'text-emerald-700 dark:text-emerald-300'"
                                >
                                    {{ faltante(fila) > 0 ? cantidad(faltante(fila)) : '0' }}
                                </td>
                                <td class="max-w-[240px] truncate px-3 py-2 text-xs text-gray-500 dark:text-gray-400" :title="productosTexto(fila)">
                                    {{ productosTexto(fila) }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-900/40">
                                <td colspan="5" class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    {{ materiales.length }} {{ materiales.length === 1 ? 'material' : 'materiales' }}
                                    <span v-if="cuantosFaltan" class="normal-case font-medium text-rose-700 dark:text-rose-300">
                                        · faltan {{ cuantosFaltan }}
                                    </span>
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <p v-else class="mt-4 rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-4 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400">
                La OP no tiene materiales registrados.
            </p>
        </div>
    </Modal>
</template>
