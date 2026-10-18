<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import InputLabel from '@/components/InputLabel.vue';
import Modal from '@/components/Modal.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import { TrashIcon } from '@heroicons/vue/24/solid';
import { useForm } from '@inertiajs/vue3';
import { computed, watchEffect } from 'vue';
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

const filaVacia = () => ({
    material_componente_id: '' as string | number,
    consumo_estandar: 1 as string | number,
    unidad_medida: '',
    costo_asociado_teorico: 0 as string | number,
    costo_asociado_real: '' as string | number,
});

const form = useForm({
    nombre: '',
    productos: [] as number[],
    materiales: [filaVacia()],
});

const valorTotal = computed(() =>
    form.materiales.reduce((total, fila) => total + (Number(fila.costo_asociado_teorico) || 0), 0)
);

const alElegirMaterial = (fila: any, material: any) => {
    fila.unidad_medida = material?.unidad_inventario ?? '';
};

const agregarFila = () => form.materiales.push(filaVacia());
const quitarFila = (index: number) => {
    if (form.materiales.length > 1) {
        form.materiales.splice(index, 1);
    }
};

const actualizar = () => {
    form
        .transform((datos) => ({
            ...datos,
            materiales: datos.materiales.filter((fila: any) => fila.material_componente_id),
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
        form.materiales = (props.FichaTecnicaMaterialesa.materiales ?? []).length
            ? props.FichaTecnicaMaterialesa.materiales.map((m: any) => ({
                material_componente_id: m.material_componente_id,
                consumo_estandar: m.consumo_estandar,
                unidad_medida: m.unidad_medida ?? '',
                costo_asociado_teorico: m.costo_asociado_teorico ?? 0,
                costo_asociado_real: m.costo_asociado_real ?? '',
            }))
            : [filaVacia()];
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
                    <div class="flex items-center justify-between">
                        <InputLabel :value="lang().t('app.label.materiales_ficha')" />
                        <span class="text-sm text-gray-600 dark:text-gray-400">
                            {{ lang().t('app.label.valor_total_ficha') }}:
                            <b class="text-gray-900 dark:text-gray-100">{{ valorTotal.toLocaleString() }}</b>
                        </span>
                    </div>

                    <div class="mt-2 space-y-3">
                        <div v-for="(fila, index) in form.materiales" :key="index"
                            class="grid grid-cols-12 gap-3 items-start rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/40 p-3">
                            <div class="col-span-12 lg:col-span-6">
                                <InputLabel :value="lang().t('app.label.material')" class="mb-1 text-sm" />
                                <v-select :options="props.losSelect?.materiales || []" label="name"
                                    :reduce="(item: any) => item.id" v-model="fila.material_componente_id"
                                    :placeholder="lang().t('app.label.material')" class="block w-full"
                                    @option:selected="(opt: any) => alElegirMaterial(fila, opt)" />
                                <InputError class="mt-1"
                                    :message="form.errors[`materiales.${index}.material_componente_id`]" />
                            </div>
                            <div class="col-span-6 lg:col-span-3">
                                <InputLabel :value="lang().t('app.label.cantidad')" class="mb-1 text-sm" />
                                <TextInput type="number" min="0" step="any" class="block w-full h-10 px-3" v-model="fila.consumo_estandar"
                                    :placeholder="lang().t('app.label.cantidad')" required />
                                <InputError class="mt-1" :message="form.errors[`materiales.${index}.consumo_estandar`]" />
                            </div>
                            <div class="col-span-6 lg:col-span-3">
                                <InputLabel :value="lang().t('app.label.unidad')" class="mb-1 text-sm" />
                                <v-select :options="props.losSelect?.unidad_medida || []" label="name"
                                    :reduce="(item: any) => item.value" v-model="fila.unidad_medida"
                                    :placeholder="lang().t('app.label.unidad')" class="block w-full"
                                    :taggable="true" />
                            </div>
                            <div class="col-span-6 lg:col-span-5">
                                <InputLabel :value="lang().t('app.label.costo_asociado_teorico')" class="mb-1 text-sm" />
                                <TextInput type="number" min="0" step="any" class="block w-full h-10 px-3" v-model="fila.costo_asociado_teorico"
                                    :placeholder="lang().t('app.label.costo_asociado_teorico')" />
                                <InputError class="mt-1" :message="form.errors[`materiales.${index}.costo_asociado_teorico`]" />
                            </div>
                            <div class="col-span-5 lg:col-span-5">
                                <InputLabel :value="lang().t('app.label.costo_asociado_real')" class="mb-1 text-sm" />
                                <TextInput type="number" min="0" step="any" class="block w-full h-10 px-3" v-model="fila.costo_asociado_real"
                                    :placeholder="lang().t('app.label.costo_asociado_real')" />
                            </div>
                            <div class="col-span-1 flex items-end justify-end self-stretch">
                                <button type="button" @click="quitarFila(index)"
                                    class="p-2 text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-md transition disabled:opacity-30"
                                    :disabled="form.materiales.length <= 1"
                                    v-tooltip="lang().t('app.tooltip.delete')">
                                    <TrashIcon class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <SecondaryButton type="button" class="mt-3 text-xs" @click="agregarFila">
                        + {{ lang().t('app.label.agregar_material') }}
                    </SecondaryButton>
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
