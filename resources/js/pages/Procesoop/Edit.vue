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
    productosSelect: { type: Array as () => any[], default: () => [] },
    numberPermissions: Number,
});

const emit = defineEmits(['close']);

const form = useForm({
    nombre: '',
    producto_id: '' as string | number,
    coste_proceso: '' as string | number,
});

watchEffect(() => {
    if (props.show && props.item) {
        form.nombre = props.item.nombre ?? '';
        form.producto_id = props.item.producto_id ?? '';
        form.coste_proceso = props.item.coste_proceso ?? '';
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
            <div class="mt-6 flex flex-col gap-4">
                <div>
                    <InputLabel for="edit_nombre" value="Nombre" />
                    <TextInput
                        id="edit_nombre"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.nombre"
                        placeholder="Nombre del proceso"
                        :error="form.errors.nombre"
                        required
                    />
                    <InputError class="mt-1" :message="form.errors.nombre" />
                </div>

                <div>
                    <InputLabel for="edit_producto_id" value="Producto" />
                    <select
                        id="edit_producto_id"
                        v-model="form.producto_id"
                        required
                        class="mt-1 block w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30"
                    >
                        <option value="">Seleccione un producto</option>
                        <option
                            v-for="p in props.productosSelect"
                            :key="p.value"
                            :value="p.value"
                        >
                            {{ p.label }}
                        </option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.producto_id" />
                </div>

                <div>
                    <InputLabel for="edit_coste_proceso" value="Costo proceso" />
                    <TextInput
                        id="edit_coste_proceso"
                        type="number"
                        step="0.01"
                        class="mt-1 block w-full"
                        v-model="form.coste_proceso"
                        placeholder="0.00"
                        :error="form.errors.coste_proceso"
                        required
                    />
                    <InputError class="mt-1" :message="form.errors.coste_proceso" />
                </div>
            </div>

            <div class="mt-8 flex justify-end gap-3">
                <SecondaryButton :disabled="form.processing" @click="emit('close')">
                    Cancelar
                </SecondaryButton>
                <PrimaryButton
                    type="submit"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Guardando…' : 'Guardar' }}
                </PrimaryButton>
            </div>
        </form>
    </Modal>
</template>
