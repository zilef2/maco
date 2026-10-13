<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import InputLabel from '@/components/InputLabel.vue';
import Modal from '@/components/Modal.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, watchEffect } from 'vue';
import vSelect from "vue-select";
import "vue-select/dist/vue-select.css";

const props = defineProps({
    show: Boolean,
    title: String,
    op: Object,
    estados: Array,
});

const emit = defineEmits(["close"]);

const form = useForm({
    estado_op_id: '',
});

const estadoActual = computed(() =>
    (props.estados || []).find((e: any) => e.id === props.op?.estado_op_id)
);

const opcionesPermitidas = computed(() => {
    const permitidos: number[] = estadoActual.value?.permitidos || [];
    return (props.estados || []).filter((e: any) => permitidos.includes(e.id));
});

const abrirModal = () => {
    form.estado_op_id = '';
    form.errors = {} as any;
    form.reset();
};

const guardar = () => {
    form.put(route('OrdenProduccion.cambiarEstado', props.op?.id), {
        preserveScroll: true,
        onSuccess: () => emit("close"),
        onError: () => null,
    });
};

watchEffect(() => {
    if (props.show) {
        abrirModal();
    }
});
</script>

<template>
    <section class="space-y-6">
        <Modal :show="props.show" @close="emit('close')" :maxWidth="'md'">
            <form class="p-6" @submit.prevent="guardar">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Cambiar estado
                    <span v-if="props.op?.numero_op" class="text-sm font-normal text-gray-500 dark:text-gray-400">
                        — OP {{ props.op.numero_op }}
                    </span>
                </h2>

                <div class="mt-4 rounded-md border border-gray-200 dark:border-gray-700 p-3">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Estado actual</p>
                    <p class="text-base font-semibold text-gray-900 dark:text-gray-100">
                        {{ estadoActual?.name || 'Sin estado' }}
                    </p>
                    <p v-if="estadoActual?.regla_clave" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ estadoActual.regla_clave }}
                    </p>
                </div>

                <div class="mt-4">
                    <InputLabel for="estado_nuevo" value="Nuevo estado" />
                    <v-select
                        v-if="opcionesPermitidas.length"
                        id="estado_nuevo"
                        class="mt-1 block w-full"
                        :options="opcionesPermitidas"
                        label="name"
                        :reduce="(item: any) => item.id"
                        v-model="form.estado_op_id"
                        placeholder="Seleccione el nuevo estado..."
                    />
                    <p v-else class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Este estado es final: no admite transiciones.
                    </p>
                    <InputError class="mt-2" :message="form.errors.estado_op_id" />
                </div>

                <div class="my-8 flex justify-end">
                    <SecondaryButton :disabled="form.processing" type="button" @click="emit('close')">
                        {{ lang().t('app.button.close') }}
                    </SecondaryButton>
                    <PrimaryButton
                        v-if="opcionesPermitidas.length"
                        class="ml-3"
                        :class="{ 'opacity-25': form.processing || !form.estado_op_id }"
                        :disabled="form.processing || !form.estado_op_id"
                        type="submit"
                    >
                        {{ form.processing ? lang().t('app.button.save') + '...' : lang().t('app.button.save') }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    </section>
</template>
