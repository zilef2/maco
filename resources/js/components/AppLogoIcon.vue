<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <img 
    src="/macondas_741x.webp" 
    alt="Macondas"
    style="width: 300px !important; height: auto !important; max-width: none !important; min-width: 300px !important; display: block;"
    />
</template>
