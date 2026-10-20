<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import InputLabel from '@/components/InputLabel.vue';
import Modal from '@/components/Modal.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import EditorMateriales from '@/pages/FichaTecnicaMateriales/EditorMateriales.vue';
import { dineroANumero } from '@/global.ts';
import { useForm } from '@inertiajs/vue3';
import { computed, watchEffect } from 'vue';
import vSelect from "vue-select";
import "vue-select/dist/vue-select.css";

const props = defineProps({
    show: Boolean,
    title: String,
    losSelect: Object,
    numberPermissions: Number,
})
const emit = defineEmits(["close"]);

const productosSinFicha = computed(() => props.losSelect?.productos_sin_ficha ?? []);

const productoUnico = computed({
    get: () => form.productos[0] ?? null,
    set: (valor: number | null) => {
        form.productos = valor ? [valor] : [];
    },
});

const form = useForm({
    nombre: '',
    productos: [] as number[],
    materiales: [] as any[],
});

const guardar = () => {
    form
        .transform((datos) => ({
            ...datos,
            materiales: datos.materiales
                .filter((fila: any) => fila.material_componente_id)
                .map((fila: any) => ({
                    ...fila,
                    consumo_estandar: dineroANumero(fila.consumo_estandar),
                    costo_asociado_teorico: dineroANumero(fila.costo_asociado_teorico),
                })),
        }))
        .post(route('FichaTecnicaMateriales.store'), {
            preserveScroll: true,
            onSuccess: () => emit("close"),
            onError: () => null,
            onFinish: () => null,
        })
}

watchEffect(() => {
    if (props.show) {
        form.errors = {}
        form.reset()
        form.materiales = []
        if (props.numberPermissions > 9) {
            form.nombre = 'Ficha generica ' + Math.floor(Math.random() * 90 + 10);
        }
    }
})
</script>

<template>
    <section class="space-y-6">
        <Modal :show="props.show" @close="emit('close')" :maxWidth="'xl8'">
            <form class="p-6" @submit.prevent="guardar">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    {{ lang().t('app.label.add') }} {{ props.title }}
                </h2>

                <template v-if="!productosSinFicha.length">
                    <p class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm font-medium text-amber-800 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-200">
                        {{ lang().t('app.label.todos_productos_con_ficha') }}
                    </p>
                    <div class="my-8 flex justify-end">
                        <SecondaryButton @click="emit('close')">
                            {{ lang().t('app.button.close') }}
                        </SecondaryButton>
                    </div>
                </template>

                <template v-else>
                <div class="mt-4">
                    <InputLabel for="nombre" :value="lang().t('app.label.nombre')" />
                    <TextInput id="nombre" type="text" class="mt-1 block w-full" v-model="form.nombre" required
                        :placeholder="lang().t('app.label.nombre_ficha')" :error="form.errors.nombre" />
                    <InputError class="mt-2" :message="form.errors.nombre" />
                </div>

                <div class="mt-4">
                    <InputLabel :value="lang().t('app.label.productos')" />
                    <v-select class="mt-1 block w-full" :options="productosSinFicha"
                        label="name" :reduce="(item: any) => item.id" v-model="productoUnico"
                        :placeholder="lang().t('app.label.ficha_unica_producto')" />
                    <InputError class="mt-2" :message="form.errors.productos" />
                </div>

                <div class="mt-6">
                    <InputLabel :value="lang().t('app.label.materiales_ficha')" class="mb-2" />
                    <EditorMateriales v-model="form.materiales" :opciones="props.losSelect" :errors="form.errors" />
                    <InputError class="mt-2" :message="form.errors.materiales" />
                </div>

                <div class="my-8 flex justify-end">
                    <SecondaryButton :disabled="form.processing" @click="emit('close')">
                        {{ lang().t('app.button.close') }}
                    </SecondaryButton>
                    <PrimaryButton class="ml-3" :class="{ 'opacity-25': form.processing }" :disabled="form.processing"
                        type="submit">
                        {{ lang().t('app.button.add') }} {{ form.processing ? '...' : '' }}
                    </PrimaryButton>
                </div>
                </template>
            </form>
        </Modal>
    </section>
</template>
