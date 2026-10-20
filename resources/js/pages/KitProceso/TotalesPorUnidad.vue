<script setup lang="ts">
import { zilef_nuber_format } from '@/global.ts';

/**
 * Totales por unidad de medida. Sumar cantidades de unidades distintas
 * (und, m, kg…) daría una cifra sin significado, así que se muestran
 * separadas.
 */
const props = withDefaults(defineProps<{
    unidades?: any[];
    campo: string;
    tono?: 'fuerte' | 'suave';
}>(), {
    unidades: () => [],
    tono: 'fuerte',
});

const esFuerte = () => props.tono === 'fuerte';
</script>

<template>
    <div v-if="props.unidades.length" class="flex flex-wrap items-baseline justify-end gap-x-2 gap-y-0.5">
        <span v-for="unidad in props.unidades" :key="unidad.unidad" class="whitespace-nowrap">
            <span class="tabular-nums"
                :class="esFuerte()
                    ? 'text-base font-bold text-amber-950 dark:text-amber-50'
                    : 'text-sm text-gray-700 dark:text-gray-200'">
                {{ zilef_nuber_format(unidad[props.campo], 2, false) }}
            </span>
            <span class="ml-1 text-[10px] font-semibold uppercase tracking-wide"
                :class="esFuerte()
                    ? 'text-amber-700/80 dark:text-amber-300/80'
                    : 'text-gray-400 dark:text-gray-500'">
                {{ unidad.unidad }}
            </span>
        </span>
    </div>
    <span v-else class="text-sm" :class="esFuerte() ? 'text-amber-700/70 dark:text-amber-300/70' : 'text-gray-400 dark:text-gray-500'">
        —
    </span>
</template>
