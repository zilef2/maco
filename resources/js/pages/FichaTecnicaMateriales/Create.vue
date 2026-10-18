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
import { formatdinero } from '@/global.ts';

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

const filaVacia = () => ({
    material_componente_id: '' as string | number,
    consumo_estandar: 1 as string | number,
    unidad_medida: '',
    costo_asociado_teorico: formatdinero(0) as string | number,
    costo_asociado_real: '' as string | number,
});

const form = useForm({
    nombre: '',
    productos: [] as number[],
    materiales: [filaVacia()],
});

const valorEnDinero = (valor: any): number => {
    const limpio = (valor ?? '').toString().replace(/[^0-9\.-]/g, '');
    const num = parseFloat(limpio);
    return isNaN(num) ? 0 : num;
};

const alEntrarDinero = (fila: any, campo: 'costo_asociado_teorico' | 'costo_asociado_real') => {
    if (fila[campo] === '' || fila[campo] === null) return;
    fila[campo] = valorEnDinero(fila[campo]).toString();
};

const alSalirDinero = (fila: any, campo: 'costo_asociado_teorico' | 'costo_asociado_real') => {
    if (fila[campo] === '' || fila[campo] === null) return;
    fila[campo] = formatdinero(fila[campo]);
};

const valorTotal = computed(() =>
    form.materiales.reduce((total, fila) => total + valorEnDinero(fila.costo_asociado_teorico), 0)
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

const limpiarDinero = (valor: any) => {
    if (valor === '' || valor === null || valor === undefined) return null;
    return valorEnDinero(valor);
};

const guardar = () => {
    form
        .transform((datos) => ({
            ...datos,
            materiales: datos.materiales
                .filter((fila: any) => fila.material_componente_id)
                .map((fila: any) => ({
                    ...fila,
                    costo_asociado_teorico: valorEnDinero(fila.costo_asociado_teorico),
                    costo_asociado_real: limpiarDinero(fila.costo_asociado_real),
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
        form.materiales = [filaVacia()]
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
                    <div class="flex items-center justify-between">
                        <InputLabel :value="lang().t('app.label.materiales_ficha')" />
                        <span class="text-sm text-gray-600 dark:text-gray-400">
                            {{ lang().t('app.label.valor_total_ficha') }}:
                            <b class="text-gray-900 dark:text-gray-100">{{ formatdinero(valorTotal) }}</b>
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
                                <TextInput type="text" inputmode="text" class="block w-full h-10 px-3" v-model="fila.costo_asociado_teorico"
                                    :placeholder="lang().t('app.label.costo_asociado_teorico')"
                                    @focus="alEntrarDinero(fila, 'costo_asociado_teorico')"
                                    @blur="alSalirDinero(fila, 'costo_asociado_teorico')" />
                                <InputError class="mt-1" :message="form.errors[`materiales.${index}.costo_asociado_teorico`]" />
                            </div>
                            <div class="col-span-5 lg:col-span-5">
                                <InputLabel :value="lang().t('app.label.costo_asociado_real')" class="mb-1 text-sm" />
                                <TextInput type="text" inputmode="text" class="block w-full h-10 px-3" v-model="fila.costo_asociado_real"
                                    :placeholder="lang().t('app.label.costo_asociado_real')"
                                    @focus="alEntrarDinero(fila, 'costo_asociado_real')"
                                    @blur="alSalirDinero(fila, 'costo_asociado_real')" />
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
                        {{ lang().t('app.button.add') }} {{ form.processing ? '...' : '' }}
                    </PrimaryButton>
                </div>
                </template>
            </form>
        </Modal>
    </section>
</template>
