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
import { watchEffect } from 'vue';
import vSelect from "vue-select";
import "vue-select/dist/vue-select.css";

const props = defineProps({
    show: Boolean,
    title: String,
    losSelect: Object,
    numberPermissions: Number,
    FichaTecnicaMaterialesa: Object,
})
const emit = defineEmits(["close"]);

const form = useForm({
    nombre: '',
    productos: [] as number[],
    materiales: [] as any[],
});

const actualizar = () => {
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
        .put(route('FichaTecnicaMateriales.update', props.FichaTecnicaMaterialesa?.id), {
            preserveScroll: true,
            onSuccess: () => emit("close"),
            onError: () => null,
            onFinish: () => null,
        })
}

watchEffect(() => {
    if (props.show && props.FichaTecnicaMaterialesa) {
        form.errors = {}
        form.nombre = props.FichaTecnicaMaterialesa.nombre ?? '';
        form.productos = (props.FichaTecnicaMaterialesa.productos ?? []).map((p: any) => p.id);
        form.materiales = (props.FichaTecnicaMaterialesa.materiales ?? []).map((m: any) => ({
            material_componente_id: m.material_componente_id,
            consumo_estandar: m.consumo_estandar,
            unidad_medida: m.unidad_medida ?? '',
            costo_asociado_teorico: m.costo_asociado_teorico ?? '',
        }));
    }
})
</script>

<template>
    <section class="space-y-6">
        <Modal :show="props.show" @close="emit('close')" :maxWidth="'xl8'">
            <form class="p-6" @submit.prevent="actualizar">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    {{ lang().t('app.label.edit') }} {{ props.title }}
                </h2>

                <div class="mt-4">
                    <InputLabel for="nombre" :value="lang().t('app.label.nombre')" />
                    <TextInput id="nombre" type="text" class="mt-1 block w-full" v-model="form.nombre" required
                        :placeholder="lang().t('app.label.nombre_ficha')" :error="form.errors.nombre" />
                    <InputError class="mt-2" :message="form.errors.nombre" />
                </div>

                <div class="mt-4">
                    <InputLabel :value="lang().t('app.label.productos')" />
                    <v-select multiple class="mt-1 block w-full" :options="props.losSelect?.productos || []"
                        label="name" :reduce="(item: any) => item.id" v-model="form.productos"
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
                        {{ lang().t('app.button.save') }} {{ form.processing ? '...' : '' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    </section>
</template>
