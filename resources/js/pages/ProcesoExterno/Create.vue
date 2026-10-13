<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import InputLabel from '@/components/InputLabel.vue';
import Modal from '@/components/Modal.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { watchEffect } from 'vue';
import vSelect from "vue-select";
import "vue-select/dist/vue-select.css";

const props = defineProps({
    show: Boolean,
    title: String,
    roles: Object,
    titulos: Object,
    losSelect: Object,
    numberPermissions: Number,
})
const emit = defineEmits(["close"]);

const form = useForm({
    orden_produccion_id: '',
    etapa_componente: '',
    material_origen_id: '',
    cantidad_salida: '',
    unidad_medida: '',
    bodega_origen: '',
    proveedor_destino_id: '',
    servicio_proceso: '',
    fecha_salida: '',
    fecha_prevista_regreso: '',
    resultado_observaciones: '',
    fecha_regreso_real: '',
});

const aFechaHora = (valor: any) => {
    if (!valor) {
        return '';
    }
    let v = String(valor).replace('T', ' ');
    if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/.test(v)) {
        v += ':00';
    }
    return v;
};

const closeModal = () => {
    emit("close");
    form.reset();
};

const create = () => {
    form.transform((data: any) => ({
        ...data,
        fecha_salida: aFechaHora(data.fecha_salida),
        fecha_regreso_real: aFechaHora(data.fecha_regreso_real),
    })).post(route('ProcesoExterno.store'), {
        preserveScroll: true,
        onSuccess: () => {
            closeModal();
        },
        onError: () => null,
        onFinish: () => null,
    });
};

watchEffect(() => {
    if (props.show) {
        form.errors = {};
    }
});
</script>

<template>
    <section class="space-y-6">
        <Modal :show="props.show" @close="closeModal" :maxWidth="'xl4'">
            <form class="p-6" @submit.prevent="create">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-6">
                    {{ lang().t('app.label.add') }} {{ props.title }}
                    --- {{ props.numberPermissions }}
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <InputLabel for="orden_produccion_id" :value="lang().t('app.label.orden_produccion_id') || 'Orden producción'" />
                        <v-select
                            id="orden_produccion_id"
                            class="mt-1 block w-full"
                            :options="props.losSelect?.orden_produccion_id || []"
                            label="name"
                            :reduce="(item: any) => item.id"
                            v-model="form.orden_produccion_id"
                            placeholder="Seleccione la orden..."
                        />
                        <InputError class="mt-2" :message="form.errors.orden_produccion_id" />
                    </div>

                    <div>
                        <InputLabel for="etapa_componente" :value="lang().t('app.label.etapa_componente') || 'Etapa componente'" />
                        <TextInput
                            id="etapa_componente"
                            type="text"
                            class="mt-1 block w-full"
                            v-model="form.etapa_componente"
                            required
                            placeholder="Etapa o componente..."
                        />
                        <InputError class="mt-2" :message="form.errors.etapa_componente" />
                    </div>

                    <div>
                        <InputLabel for="material_origen_id" :value="lang().t('app.label.material_origen_id') || 'Material origen'" />
                        <v-select
                            id="material_origen_id"
                            class="mt-1 block w-full"
                            :options="props.losSelect?.material_origen_id || []"
                            label="name"
                            :reduce="(item: any) => item.id"
                            v-model="form.material_origen_id"
                            placeholder="Seleccione el material..."
                        />
                        <InputError class="mt-2" :message="form.errors.material_origen_id" />
                    </div>

                    <div>
                        <InputLabel for="cantidad_salida" :value="lang().t('app.label.cantidad_salida') || 'Cantidad salida'" />
                        <TextInput
                            id="cantidad_salida"
                            type="number"
                            min="1"
                            class="mt-1 block w-full"
                            v-model="form.cantidad_salida"
                            required
                            placeholder="0"
                        />
                        <InputError class="mt-2" :message="form.errors.cantidad_salida" />
                    </div>

                    <div>
                        <InputLabel for="unidad_medida" :value="lang().t('app.label.unidad_medida') || 'Unidad medida'" />
                        <TextInput
                            id="unidad_medida"
                            type="text"
                            class="mt-1 block w-full"
                            v-model="form.unidad_medida"
                            required
                            placeholder="Ej: metros, kilos..."
                        />
                        <InputError class="mt-2" :message="form.errors.unidad_medida" />
                    </div>

                    <div>
                        <InputLabel for="bodega_origen" :value="lang().t('app.label.bodega_origen') || 'Bodega origen'" />
                        <TextInput
                            id="bodega_origen"
                            type="text"
                            class="mt-1 block w-full"
                            v-model="form.bodega_origen"
                            required
                            placeholder="Bodega de origen..."
                        />
                        <InputError class="mt-2" :message="form.errors.bodega_origen" />
                    </div>

                    <div>
                        <InputLabel for="proveedor_destino_id" :value="lang().t('app.label.proveedor_destino_id') || 'Proveedor destino'" />
                        <v-select
                            id="proveedor_destino_id"
                            class="mt-1 block w-full"
                            :options="props.losSelect?.proveedor_destino_id || []"
                            label="name"
                            :reduce="(item: any) => item.id"
                            v-model="form.proveedor_destino_id"
                            placeholder="Seleccione el proveedor..."
                        />
                        <InputError class="mt-2" :message="form.errors.proveedor_destino_id" />
                    </div>

                    <div>
                        <InputLabel for="servicio_proceso" :value="lang().t('app.label.servicio_proceso') || 'Servicio proceso'" />
                        <TextInput
                            id="servicio_proceso"
                            type="text"
                            class="mt-1 block w-full"
                            v-model="form.servicio_proceso"
                            required
                            placeholder="Servicio o proceso..."
                        />
                        <InputError class="mt-2" :message="form.errors.servicio_proceso" />
                    </div>

                    <div>
                        <InputLabel for="fecha_salida" :value="lang().t('app.label.fecha_salida') || 'Fecha salida'" />
                        <TextInput
                            id="fecha_salida"
                            type="datetime-local"
                            class="mt-1 block w-full"
                            v-model="form.fecha_salida"
                            required
                        />
                        <InputError class="mt-2" :message="form.errors.fecha_salida" />
                    </div>

                    <div>
                        <InputLabel for="fecha_prevista_regreso" :value="lang().t('app.label.fecha_prevista_regreso') || 'Fecha prevista regreso'" />
                        <TextInput
                            id="fecha_prevista_regreso"
                            type="date"
                            class="mt-1 block w-full"
                            v-model="form.fecha_prevista_regreso"
                            required
                        />
                        <InputError class="mt-2" :message="form.errors.fecha_prevista_regreso" />
                    </div>

                    <div>
                        <InputLabel for="fecha_regreso_real" :value="lang().t('app.label.fecha_regreso_real') || 'Fecha regreso real'" />
                        <TextInput
                            id="fecha_regreso_real"
                            type="datetime-local"
                            class="mt-1 block w-full"
                            v-model="form.fecha_regreso_real"
                            placeholder="Opcional"
                        />
                        <InputError class="mt-2" :message="form.errors.fecha_regreso_real" />
                    </div>

                    <div>
                        <InputLabel for="resultado_observaciones" :value="lang().t('app.label.resultado_observaciones') || 'Resultado observaciones'" />
                        <textarea
                            id="resultado_observaciones"
                            v-model="form.resultado_observaciones"
                            rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 shadow-sm"
                            placeholder="Observaciones del resultado (opcional)..."
                        ></textarea>
                        <InputError class="mt-2" :message="form.errors.resultado_observaciones" />
                    </div>
                </div>
                <div class="my-8 flex justify-end">
                    <SecondaryButton :disabled="form.processing" @click="closeModal">
                        {{ lang().t('app.button.close') }}
                    </SecondaryButton>
                    <PrimaryButton class="ml-3" :class="{ 'opacity-25': form.processing }" :disabled="form.processing" type="submit">
                        {{ lang().t('app.button.add') }} {{ form.processing ? '...' : '' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    </section>
</template>
