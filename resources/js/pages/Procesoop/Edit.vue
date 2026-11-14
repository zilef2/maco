<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import InputLabel from '@/components/InputLabel.vue';
import Modal from '@/components/Modal.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { watchEffect } from 'vue';

const props = defineProps({
    show: Boolean,
    title: String,
    item: { type: Object as () => any, default: null },
    numberPermissions: Number,
});

const emit = defineEmits(['close']);

const form = useForm({
    nombre: '',
});

watchEffect(() => {
    if (props.show && props.item) {
        form.nombre = props.item.nombre ?? '';
        form.clearErrors();
    }
});

const actualizar = () => {
    form.put(route('Procesoop.update', props.item?.id), {
        preserveScroll: true,
        onSuccess: () => {
            emit('close');
            form.reset();
        },
        onError: () => null,
        onFinish: () => null,
    });
};
</script>

<template>
    <Modal :show="props.show" @close="emit('close')" maxWidth="lg">
        <form class="p-6" @submit.prevent="actualizar">
            <h2 class="text-lg font-semibold text-foreground">
                Editar {{ props.title }}
            </h2>
            <p class="mt-1 text-xs text-muted-foreground">
                El proceso es un nombre del catálogo. Su coste se define en cada producto.
            </p>
            <div class="mt-6 flex flex-col gap-4">
                <div>
                    <InputLabel for="edit_nombre" value="Nombre del proceso" />
                    <TextInput
                        id="edit_nombre"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.nombre"
                        placeholder="Ej. Capellada"
                        :error="form.errors.nombre"
                        autofocus
                    />
                    <InputError class="mt-1" :message="form.errors.nombre" />
                </div>
            </div>

            <div class="mt-8 flex justify-end gap-3">
                <SecondaryButton :disabled="form.processing" @click="emit('close')">
                    Cancelar
                </SecondaryButton>
                <PrimaryButton
                    type="submit"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing || !form.nombre.trim()"
                >
                    {{ form.processing ? 'Guardando…' : 'Guardar' }}
                </PrimaryButton>
            </div>
        </form>
    </Modal>
</template>
