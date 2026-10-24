<script setup lang="ts">
import Modal from '@/components/Modal.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';

const props = defineProps({
    show: Boolean,
    title: String,
    numeroOp: [String, Number],
    observacion: String,
})

const emit = defineEmits(["close"]);

</script>

<template>
    <section class="space-y-6">
        <Modal :show="props.show" @close="emit('close')" :maxWidth="'lg'">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    {{ lang().t('app.label.observaciones') || 'Observaciones' }}
                    <span v-if="props.numeroOp" class="text-sm font-normal text-gray-500 dark:text-gray-400">
                        — {{ props.numeroOp }}
                    </span>
                </h2>
                <p class="mt-4 text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap break-words max-h-96 overflow-y-auto">
                    {{ props.observacion || '—' }}
                </p>
                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="emit('close')">
                        {{ lang().t('app.button.close') }}
                    </SecondaryButton>
                </div>
            </div>
        </Modal>
    </section>
</template>
