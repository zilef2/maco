<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import { CubeIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/solid';
import { computed } from 'vue';
import vSelect from 'vue-select';
import 'vue-select/dist/vue-select.css';
import { dineroANumero, formatdinero } from '@/global.ts';

/**
 * Líneas de material de una ficha técnica: material, consumo, unidad y costo
 * teórico. El costo real todavía no se captura, así que no se muestra.
 */
const filas = defineModel<any[]>({ required: true });

const props = withDefaults(
    defineProps<{
        opciones?: { materiales?: any[]; unidad_medida?: any[] };
        errors?: Record<string, string>;
    }>(),
    {
        opciones: () => ({}),
        errors: () => ({}),
    }
);

const filaVacia = () => ({
    material_componente_id: '' as string | number,
    consumo_estandar: 1 as string | number,
    unidad_medida: '',
    costo_asociado_teorico: '' as string | number,
});

const agregar = () => {
    filas.value = [...filas.value, filaVacia()];
};

const quitar = (index: number) => {
    filas.value = filas.value.filter((_, i) => i !== index);
};

const alElegirMaterial = (fila: any, material: any) => {
    fila.unidad_medida = material?.unidad_inventario ?? '';
};

const alEntrarDinero = (fila: any) => {
    fila.costo_asociado_teorico = dineroANumero(fila.costo_asociado_teorico);
};

const alSalirDinero = (fila: any) => {
    fila.costo_asociado_teorico = formatdinero(fila.costo_asociado_teorico);
};

const total = computed(() =>
    filas.value.reduce((suma, fila: any) => suma + dineroANumero(fila.costo_asociado_teorico), 0)
);

const errorDe = (index: number, campo: string) => props.errors?.[`materiales.${index}.${campo}`];
</script>

<template>
    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-sm">
                <thead>
                    <tr
                        class="border-b border-gray-200 bg-gray-50 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400"
                    >
                        <th scope="col" class="px-3 py-2.5 font-semibold">
                            {{ lang().t('app.label.material') }}
                        </th>
                        <th scope="col" class="w-28 px-3 py-2.5 font-semibold">
                            {{ lang().t('app.label.cantidad') }}
                        </th>
                        <th scope="col" class="w-32 px-3 py-2.5 font-semibold">
                            {{ lang().t('app.label.unidad') }}
                        </th>
                        <th scope="col" class="w-40 px-3 py-2.5 font-semibold">
                            {{ lang().t('app.label.costo_teorico', 'Costo teórico') }}
                        </th>
                        <th scope="col" class="w-11 px-2 py-2.5">
                            <span class="sr-only">{{ lang().t('app.tooltip.delete') }}</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                    <tr v-for="(fila, index) in filas" :key="index" class="align-top">
                        <td class="px-3 py-2">
                            <v-select
                                :options="props.opciones?.materiales || []"
                                label="name"
                                :reduce="(item: any) => item.id"
                                v-model="fila.material_componente_id"
                                :placeholder="lang().t('app.label.material')"
                                class="block w-full"
                                append-to-body
                                @option:selected="(opt: any) => alElegirMaterial(fila, opt)"
                            />
                            <InputError class="mt-1" :message="errorDe(index, 'material_componente_id')" />
                        </td>

                        <td class="px-3 py-2">
                            <TextInput
                                type="number"
                                inputmode="decimal"
                                min="0"
                                step="any"
                                class="block h-9 w-full px-2"
                                v-model="fila.consumo_estandar"
                                :aria-label="lang().t('app.label.cantidad')"
                                required
                            />
                            <InputError class="mt-1" :message="errorDe(index, 'consumo_estandar')" />
                        </td>

                        <td class="px-3 py-2">
                            <v-select
                                :options="props.opciones?.unidad_medida || []"
                                label="name"
                                :reduce="(item: any) => item.value"
                                v-model="fila.unidad_medida"
                                :placeholder="lang().t('app.label.unidad')"
                                class="block w-full"
                                :taggable="true"
                            />
                            <InputError class="mt-1" :message="errorDe(index, 'unidad_medida')" />
                        </td>

                        <td class="px-3 py-2">
                            <TextInput
                                type="text"
                                inputmode="decimal"
                                class="block h-9 w-full px-2 text-right tabular-nums"
                                v-model="fila.costo_asociado_teorico"
                                :aria-label="lang().t('app.label.costo_asociado_teorico')"
                                @focus="alEntrarDinero(fila)"
                                @blur="alSalirDinero(fila)"
                            />
                            <InputError class="mt-1" :message="errorDe(index, 'costo_asociado_teorico')" />
                        </td>

                        <td class="px-2 py-2">
                            <button
                                type="button"
                                @click="quitar(index)"
                                class="mt-0.5 rounded-md p-2 text-gray-400 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20 dark:hover:text-red-400"
                                :aria-label="lang().t('app.tooltip.delete')"
                                v-tooltip="lang().t('app.tooltip.delete')"
                            >
                                <TrashIcon class="h-4 w-4" />
                            </button>
                        </td>
                    </tr>

                    <tr v-if="!filas.length">
                        <td colspan="5" class="px-3 py-7 text-center">
                            <CubeIcon class="mx-auto h-5 w-5 text-gray-300 dark:text-gray-600" />
                            <p class="mt-1.5 text-sm font-medium text-gray-600 dark:text-gray-300">
                                {{ lang().t('app.label.sin_materiales', 'Sin materiales todavía') }}
                            </p>
                            <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">
                                {{ lang().t('app.label.sin_materiales_ayuda', 'Agrega el primer material para definir el consumo estándar de la referencia.') }}
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 bg-gray-50/60 px-3 py-2.5 dark:border-gray-700 dark:bg-gray-900/30"
        >
            <SecondaryButton type="button" class="text-xs" @click="agregar">
                <PlusIcon class="mr-1 inline h-3.5 w-3.5" />
                {{ lang().t('app.label.agregar_material') }}
            </SecondaryButton>

            <p v-if="filas.length" class="text-sm text-gray-500 dark:text-gray-400">
                {{ lang().t('app.label.costo_teorico_total', 'Costo teórico total') }}:
                <span class="ml-1 font-semibold tabular-nums text-gray-900 dark:text-gray-100">
                    {{ formatdinero(total) }}
                </span>
            </p>
        </div>
    </div>
</template>
