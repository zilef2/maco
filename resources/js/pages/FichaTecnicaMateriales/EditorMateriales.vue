<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import { dineroANumero, formatdinero } from '@/global.ts';
import { CubeIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/solid';
import { computed, nextTick, ref } from 'vue';
import vSelect from 'vue-select';
import 'vue-select/dist/vue-select.css';

/**
 * Líneas de material de una ficha técnica. La unidad y el costo unitario se
 * toman del material seleccionado; solo se capturan material y consumo.
 */
const filas = defineModel<any[]>({ required: true });

const props = withDefaults(
    defineProps<{
        opciones?: { materiales?: any[]; unidad_medida?: any[] };
        errors?: Record<string, string>;
        // Bloquea la edición (p. ej. cuando la ficha está en una OP activa).
        disabled?: boolean;
    }>(),
    {
        opciones: () => ({}),
        errors: () => ({}),
        disabled: false,
    },
);

const filaVacia = () => ({
    material_componente_id: '' as string | number,
    consumo_estandar: 1 as string | number,
    unidad_medida: '',
    costo_asociado_teorico: 0 as string | number,
});

// Referencias a los v-select de cada fila, para devolver el foco al buscar.
const selectsDeFila = ref<any[]>([]);

const enfocarSelect = (index: number) => {
    nextTick(() => {
        const select = selectsDeFila.value[index];
        if (!select) {
            return;
        }
        // open es un dato del componente, no un método: se abre y se enfoca su
        // campo de búsqueda directamente para poder teclear de una.
        select.open = true;
        select.searchEl?.focus();
    });
};

const agregar = () => {
    filas.value = [...filas.value, filaVacia()];
};

const quitar = (index: number) => {
    filas.value = filas.value.filter((_, i) => i !== index);
};

/** Enter con el foco en el select de material: agrega otra fila y la enfoca. */
const alEnterEnMaterial = (index: number) => {
    if (props.disabled) {
        return;
    }
    filas.value = [...filas.value, filaVacia()];
    enfocarSelect(filas.value.length - 1);
};

const materialDe = (fila: any) =>
    (props.opciones?.materiales || []).find((material: any) => String(material.id) === String(fila.material_componente_id));

// La ficha se anota en las bases y en los huérfanos: el select oculta solo a
// los hijos de una base. Una fila antigua que apunte a un hijo conserva su
// opción para que el nombre siga visible al editar.
const esAnotable = (material: any) => !material.base_id;

const opcionesVisibles = computed<any[]>(() =>
    (props.opciones?.materiales || [])
        .filter(esAnotable)
        .map((material: any) => ({
            ...material,
            nombre_con_variants: material.variants?.length
                ? `${material.name} · ${material.variants.join(', ')}`
                : material.name,
        })),
);

const opcionesDeFila = (fila: any): any[] => {
    const seleccion = (props.opciones?.materiales || []).find(
        (material: any) => String(material.id) === String(fila.material_componente_id),
    );
    if (!seleccion || esAnotable(seleccion)) {
        return opcionesVisibles.value;
    }
    return [
        {
            ...seleccion,
            nombre_con_variants: seleccion.variants?.length
                ? `${seleccion.name} · ${seleccion.variants.join(', ')}`
                : seleccion.name,
        },
        ...opcionesVisibles.value,
    ];
};

/** Quita tildes y pasa a minúsculas, para buscar "lfl" o "Florentina" igual. */
const normalizarBusqueda = (texto: string): string =>
    String(texto ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase();

// Búsqueda por código o por nombre base, sin importar tildes ni mayúsculas.
const filtrarOpciones = (opcion: any, busqueda: string): boolean => {
    const texto = normalizarBusqueda(busqueda);
    return (
        normalizarBusqueda(opcion?.codigo_material ?? '').includes(texto) ||
        normalizarBusqueda(opcion?.nombre_base ?? '').includes(texto) ||
        normalizarBusqueda(opcion?.nombre_material ?? '').includes(texto) ||
        normalizarBusqueda(opcion?.nombre_con_variants ?? '').includes(texto)
    );
};

const alElegirMaterial = (fila: any, material: any) => {
    fila.unidad_medida = material?.unidad_inventario ?? '';
    fila.costo_asociado_teorico = Number(material?.costo_unitario) || 0;
};

const unidad = (fila: any) => {
    const material = materialDe(fila);
    return material?.unidad_inventario || material?.unidad_medida || fila.unidad_medida || '';
};

const costoUnitario = (fila: any) => {
    const material = materialDe(fila);
    return material ? Number(material.costo_unitario) || 0 : dineroANumero(fila.costo_asociado_teorico);
};

const tienePrecio = (fila: any) => {
    const material = materialDe(fila);
    if (material) {
        return Boolean(material.precio_registrado) || Number(material.costo_unitario) > 0;
    }
    return Number(fila.costo_asociado_teorico) > 0;
};

// Costo de la línea: costo unitario del material × consumo estándar.
const subtotal = (fila: any) => (Number(fila.consumo_estandar) || 0) * costoUnitario(fila);

const total = computed(() => filas.value.reduce((suma, fila: any) => suma + subtotal(fila), 0));

const errorDe = (index: number, campo: string) => props.errors?.[`materiales.${index}.${campo}`];
</script>

<template>
    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[780px] text-sm">
                <thead>
                    <tr
                        class="border-b border-gray-200 bg-gray-50 text-left text-[11px] font-semibold tracking-wider text-gray-500 uppercase dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400"
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
                            {{ lang().t('app.label.costo_teorico', 'Costo unitario') }}
                        </th>
                        <th scope="col" class="w-36 px-3 py-2.5 text-right font-semibold">
                            {{ lang().t('app.label.subtotal', 'Subtotal') }}
                        </th>
                        <th scope="col" class="w-11 px-2 py-2.5">
                            <span class="sr-only">{{ lang().t('app.tooltip.delete') }}</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                    <tr v-for="(fila, index) in filas" :key="index" class="align-top">
                        <td class="px-3 py-2 col-span-2">
                            <v-select
                                :ref="(el: any) => (selectsDeFila[index] = el)"
                                :options="opcionesDeFila(fila)"
                                label="nombre_con_variants"
                                :reduce="(item: any) => item.id"
                                v-model="fila.material_componente_id"
                                :placeholder="lang().t('app.label.material_buscar_codigo_nombre', 'Buscar por código o nombre…')"
                                class="block w-full"
                                append-to-body
                                :disabled="props.disabled"
                                :filter-by="filtrarOpciones"
                                @option:selected="(opt: any) => alElegirMaterial(fila, opt)"
                                @keyup.enter="alEnterEnMaterial(index)"
                            >
                                <template v-slot:option="opt">
                                    <div class="flex flex-col">
                                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ opt.name }}</span>
                                        <span v-if="opt.variants?.length" class="text-[11px] text-gray-500 dark:text-gray-400">
                                            {{ opt.variants.join(', ') }}
                                        </span>
                                    </div>
                                </template>
                            </v-select>
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
                                :disabled="props.disabled"
                                required
                            />
                            <InputError class="mt-1" :message="errorDe(index, 'consumo_estandar')" />
                        </td>

                        <td class="px-3 py-2">
                            <span
                                class="inline-flex h-9 w-full items-center rounded-md border border-transparent px-1 text-sm text-gray-600 dark:text-gray-300"
                                :aria-label="lang().t('app.label.unidad')"
                            >
                                {{ unidad(fila) || '—' }}
                            </span>
                        </td>

                        <td class="px-3 py-2 text-right">
                            <span
                                v-if="tienePrecio(fila)"
                                class="inline-flex h-9 items-center justify-end text-sm font-medium text-gray-700 tabular-nums dark:text-gray-200"
                            >
                                {{ formatdinero(costoUnitario(fila)) }}
                            </span>
                            <span
                                v-else
                                class="inline-flex h-9 items-center justify-end text-xs font-medium text-amber-600 dark:text-amber-400"
                                :title="lang().t('app.label.material_sin_precio', 'El material no tiene precio registrado')"
                            >
                                {{ lang().t('app.label.sin_precio', 'Sin precio') }}
                            </span>
                        </td>

                        <td class="px-3 py-2 text-right">
                            <span class="inline-flex h-9 items-center justify-end text-sm font-medium text-gray-700 tabular-nums dark:text-gray-200">
                                {{ formatdinero(subtotal(fila)) }}
                            </span>
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
                        <td colspan="6" class="px-3 py-7 text-center">
                            <CubeIcon class="mx-auto h-5 w-5 text-gray-300 dark:text-gray-600" />
                            <p class="mt-1.5 text-sm font-medium text-gray-600 dark:text-gray-300">
                                {{ lang().t('app.label.sin_materiales', 'Sin materiales todavía') }}
                            </p>
                            <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">
                                {{
                                    lang().t(
                                        'app.label.sin_materiales_ayuda',
                                        'Agrega el primer material para definir el consumo estándar de la referencia.',
                                    )
                                }}
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
                <SecondaryButton v-if="!props.disabled" type="button" class="text-xs" @click="agregar(); enfocarSelect(filas.length - 1)">
                    <PlusIcon class="mr-1 inline h-3.5 w-3.5" />
                    {{ lang().t('app.label.agregar_material') }}
                </SecondaryButton>
                <span v-else class="text-xs font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">
                    Ficha técnica en solo lectura
                </span>
                <span class="text-[11px] text-gray-400 dark:text-gray-500">
                    {{ lang().t('app.label.costo_unidad_desde_material', 'La unidad y el costo unitario se toman del material.') }}
                    · {{ lang().t('app.label.ficha_enter_agrega_fila', 'Enter agrega otra fila y vuelve al buscador.') }}
                </span>
            </div>

            <p v-if="filas.length" class="text-sm text-gray-500 dark:text-gray-400">
                {{ lang().t('app.label.costo_teorico_total', 'Costo total de materiales') }}:
                <span class="ml-1 font-semibold text-gray-900 tabular-nums dark:text-gray-100">
                    {{ formatdinero(total) }}
                </span>
            </p>
        </div>
    </div>
</template>
