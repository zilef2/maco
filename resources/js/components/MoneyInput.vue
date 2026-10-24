<script setup lang="ts">
import { computed } from 'vue';
import { zilef_nuber_format } from '@/global.ts';

/**
 * Input de dinero en formato local ($x.xxx.xxx).
 * El v-model siempre guarda solo dígitos (enteros, sin separadores): lo
 * formateado existe únicamente en la caja de texto.
 */
const props = defineProps({
    modelValue: { type: [String, Number, null], default: '' },
    error: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['update:modelValue']);

const soloDigitos = (valor: unknown): string => String(valor ?? '').replace(/\D/g, '');

const digitos = computed(() => soloDigitos(props.modelValue));
const mostrado = computed(() => (digitos.value === '' ? '' : zilef_nuber_format(digitos.value, 0, true)));

const alEscribir = (evento: Event) => {
    emit('update:modelValue', soloDigitos((evento.target as HTMLInputElement).value));
};
</script>

<template>
    <input
        type="text"
        inputmode="numeric"
        autocomplete="off"
        :value="mostrado"
        @input="alEscribir"
        v-bind:class="error != null
            ? 'border-red-500 dark:border-red-400 dark:bg-black dark:text-gray-300 focus:border-red-500 dark:focus:border-red-400 focus:ring-red-500 dark:focus:ring-red-400'
            : 'border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-primary dark:focus:border-primary focus:ring-primary dark:focus:ring-primary'"
        class="rounded-md text-right tabular-nums shadow-sm placeholder:text-left placeholder:text-gray-400 placeholder:dark:text-gray-400/50"
    />
</template>
