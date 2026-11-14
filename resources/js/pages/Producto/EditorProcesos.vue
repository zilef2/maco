<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import { dineroANumero, formatdinero } from '@/global.ts';
import { Cog6ToothIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/solid';
import { computed } from 'vue';
import vSelect from 'vue-select';
import 'vue-select/dist/vue-select.css';

/**
 * Filas de procesos del producto. La suma de los costes de estas filas, por
 * cada unidad planificada, es el costo de proceso que lleva la OP.
 */
const filas = defineModel<any[]>({ required: true });

const props = withDefaults(
    defineProps<{
        // Nombres de proceso disponibles: se eligen, no se digitan.
        opciones?: string[];
        errors?: Record<string, string>;
        disabled?: boolean;
    }>(),
    {
        opciones: () => [],
        errors: () => ({}),
        disabled: false,
    },
);

const filaVacia = () => ({
    nombre: '',
    coste_proceso: '' as string | number,
});

const agregar = () => {
    filas.value = [...filas.value, filaVacia()];
};

const quitar = (index: number) => {
    filas.value = filas.value.filter((_, i) => i !== index);
};

const coste = (fila: any) => dineroANumero(fila.coste_proceso);

const total = computed(() => filas.value.reduce((suma, fila: any) => suma + coste(fila), 0));

const errorDe = (index: number, campo: string) => props.errors?.[`procesos.${index}.${campo}`];
</script>

<template>
    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[560px] text-sm">
                <thead>
                    <tr
                        class="border-b border-gray-200 bg-gray-50 text-left text-[11px] font-semibold tracking-wider text-gray-500 uppercase dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400"
                    >
                        <th scope="col" class="px-3 py-2.5 font-semibold">Proceso</th>
                        <th scope="col" class="w-44 px-3 py-2.5 text-right font-semibold">Coste del proceso</th>
                        <th scope="col" class="w-11 px-2 py-2.5">
                            <span class="sr-only">{{ lang().t('app.tooltip.delete') }}</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                    <tr v-for="(fila, index) in filas" :key="index" class="align-top">
                        <td class="px-3 py-2">
                            <v-select
                                :options="props.opciones"
                                v-model="fila.nombre"
                                placeholder="Buscar proceso…"
                                :aria-label="'Proceso'"
                                :clearable="true"
                                :taggable="false"
                                class="block w-full"
                                append-to-body
                                :disabled="props.disabled"
                            />
                            <InputError class="mt-1" :message="errorDe(index, 'nombre')" />
                        </td>

                        <td class="px-3 py-2">
                            <TextInput
                                type="number"
                                inputmode="decimal"
                                min="0"
                                step="any"
                                class="block h-9 w-full px-2 text-right"
                                v-model="fila.coste_proceso"
                                :aria-label="'Coste del proceso'"
                                :disabled="props.disabled"
                            />
                            <InputError class="mt-1" :message="errorDe(index, 'coste_proceso')" />
                        </td>

                        <td v-if="!props.disabled" class="px-2 py-2">
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
                        <td colspan="3" class="px-3 py-7 text-center">
                            <Cog6ToothIcon class="mx-auto h-5 w-5 text-gray-300 dark:text-gray-600" />
                            <p class="mt-1.5 text-sm font-medium text-gray-600 dark:text-gray-300">Sin procesos todavía</p>
                            <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">
                                Agrega el primer proceso con su coste por unidad.
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 bg-gray-50/60 px-3 py-2.5 dark:border-gray-700 dark:bg-gray-900/30"
        >
            <div class="flex flex-col items-start gap-1">
                <SecondaryButton v-if="!props.disabled" type="button" class="text-xs" @click="agregar">
                    <PlusIcon class="mr-1 inline h-3.5 w-3.5" />
                    Agregar proceso
                </SecondaryButton>
                <span class="text-[11px] text-gray-400 dark:text-gray-500">
                    La OP multiplica este total por la cantidad planificada del producto.
                </span>
            </div>

            <p v-if="filas.length" class="text-sm text-gray-500 dark:text-gray-400">
                Costo de proceso por unidad:
                <span class="ml-1 font-semibold text-gray-900 tabular-nums dark:text-gray-100">
                    {{ formatdinero(total) }}
                </span>
            </p>
        </div>
    </div>
</template>
