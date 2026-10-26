<script setup lang="ts">
import Modal from '@/components/Modal.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import { computed } from 'vue';
import { zilef_nuber_format } from '@/global.ts';

const props = defineProps({
    show: Boolean,
    title: String,
    ficha: Object,
});

const emit = defineEmits(["close"]);

const nombreMaterial = (fila: any) => {
    const m = fila?.material;
    if (!m) return 'Material #' + (fila?.material_componente_id ?? '');
    const codigo = m.codigo_material ? m.codigo_material + ' — ' : '';
    return (codigo + (m.nombre_base ?? '')).trim() || m.nombre_base || ('Material #' + m.id);
};

const unidad = (fila: any) => fila?.unidad_medida || fila?.material?.unidad_inventario || '';

const materiales = computed(() => (props.ficha as any)?.materiales ?? []);

const costoLinea = (fila: any) =>
    Number(
        fila?.costo_total
        ?? ((Number(fila?.consumo_estandar) || 0) * (Number(fila?.costo_asociado_teorico) || 0)),
    );

const costoTotal = computed(() =>
    materiales.value.reduce((total: number, fila: any) => total + costoLinea(fila), 0),
);
</script>

<template>
    <section>
        <Modal :show="props.show" @close="emit('close')" :maxWidth="'xl'">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Materiales requeridos
                    <span v-if="props.ficha?.nombre" class="text-sm font-normal text-gray-500 dark:text-gray-400">
                        — {{ props.ficha.nombre }}
                    </span>
                </h2>

                <div class="mt-4 overflow-x-auto">
                    <table v-if="materiales.length" class="w-full min-w-[560px] text-sm">
                        <thead class="uppercase text-xs border-b border-gray-200 dark:border-gray-700 text-left text-gray-500 dark:text-gray-400">
                            <tr>
                                <th class="py-2 pr-2">{{ lang().t('app.label.material') }}</th>
                                <th class="py-2 pr-2 text-right">Cantidad</th>
                                <th class="py-2 px-2 text-left">Unidad</th>
                                <th class="py-2 px-2 text-right">Costo unitario</th>
                                <th class="py-2 pl-2 text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(fila, i) in materiales" :key="fila.id ?? i"
                                class="border-b border-gray-100 dark:border-gray-700/60 text-gray-800 dark:text-gray-200">
                                <td class="py-2 pr-2">{{ nombreMaterial(fila) }}</td>
                                <td class="py-2 pr-2 text-right whitespace-nowrap">
                                    {{ zilef_nuber_format(fila.consumo_estandar, 2, false) }}
                                </td>
                                <td class="py-2 px-2">{{ unidad(fila) || '—' }}</td>
                                <td class="py-2 px-2 text-right whitespace-nowrap tabular-nums">
                                    {{ zilef_nuber_format(fila.costo_asociado_teorico, 0, true) }}
                                </td>
                                <td class="py-2 pl-2 text-right whitespace-nowrap tabular-nums font-medium">
                                    {{ zilef_nuber_format(costoLinea(fila), 0, true) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="text-sm text-gray-500 dark:text-gray-400">Esta ficha no tiene materiales.</p>
                </div>

                <div
                    v-if="materiales.length"
                    class="mt-3 flex items-center justify-end gap-2 border-t border-gray-200 pt-3 text-sm dark:border-gray-700"
                >
                    <span class="text-gray-500 dark:text-gray-400">Costo total de materiales:</span>
                    <span class="font-semibold tabular-nums text-gray-900 dark:text-gray-100">
                        {{ zilef_nuber_format(costoTotal, 0, true) }}
                    </span>
                </div>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="emit('close')">
                        {{ lang().t('app.button.close') }}
                    </SecondaryButton>
                </div>
            </div>
        </Modal>
    </section>
</template>
