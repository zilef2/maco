<script setup lang="ts">
import Modal from '@/components/Modal.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: true,
    },
})
const emit = defineEmits(['close'])
</script>

<template>
    <section class="space-y-6">
        <Modal :show="props.show" @close="emit('close')" :closeable="true">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    achu
                </h2>
            </div>
        </Modal>
    </section>
</template>

<style>
[name="labelSelectVue"],
.muted {
    color: #1b416699;
}

[name="labelSelectVue"] {
    /* font-size: 22px; */
    font-weight: 600;
}
</style>
