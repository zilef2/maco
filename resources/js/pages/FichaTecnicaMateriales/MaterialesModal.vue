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
</script>

<template>
    <section>
        <Modal :show="props.show" @close="emit('close')" :maxWidth="'md'">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Materiales requeridos
                    <span v-if="props.ficha?.nombre" class="text-sm font-normal text-gray-500 dark:text-gray-400">
                        — {{ props.ficha.nombre }}
                    </span>
                </h2>

                <div class="mt-4 overflow-x-auto">
                    <table v-if="materiales.length" class="w-full text-sm">
                        <thead class="uppercase text-xs border-b border-gray-200 dark:border-gray-700 text-left text-gray-500 dark:text-gray-400">
                            <tr>
                                <th class="py-2 pr-2">{{ lang().t('app.label.material') }}</th>
                                <th class="py-2 pr-2 text-right">Cantidad</th>
                                <th class="py-2 text-left">Unidad</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(fila, i) in materiales" :key="fila.id ?? i"
                                class="border-b border-gray-100 dark:border-gray-700/60 text-gray-800 dark:text-gray-200">
                                <td class="py-2 pr-2">{{ nombreMaterial(fila) }}</td>
                                <td class="py-2 pr-2 text-right whitespace-nowrap">
                                    {{ zilef_nuber_format(fila.consumo_estandar, 2, false) }}
                                </td>
                                <td class="py-2">{{ unidad(fila) || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="text-sm text-gray-500 dark:text-gray-400">Esta ficha no tiene materiales.</p>
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
