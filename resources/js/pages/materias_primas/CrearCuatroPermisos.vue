<script setup lang="ts">
import CrearCuatroPermisos from '@/components/CrearCuatroPermisos.vue';

const props = defineProps({
    show: Boolean,
    modulo: {
        type: String,
        default: 'materias_primas',
    },
});

const emit = defineEmits(['close']);
</script>

<template>
    <CrearCuatroPermisos :show="props.show" :modulo="props.modulo" @close="emit('close')" />
</template>
