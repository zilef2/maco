<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import InputLabel from '@/components/InputLabel.vue';
import Modal from '@/components/Modal.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { reactive, watchEffect } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: '',
    },
    Presupuestoa: {
        type: Object,
        default: null,
    },
    titulos: {
        type: Array as () => any[],
        default: () => [],
    },
    losSelect: Object,
});

const emit = defineEmits(["close"]);

const form = useForm({
    nombre: '',
    descripcion: '',
    fecha_inicio: '',
    fecha_fin: '',
    monto_total: 0,
    monto_ejecutado: 0,
    monto_disponible: 0,
    total_salarios: 0,
    total_viaticos: 0,
    total_materiales: 0,
    estado: '',
    observaciones: '',
    porcentaje_ejecutado: 0,
    umbral_alerta: 0,
    fecha_ultimo_reajuste: '',
    motivo_reajuste: '',
});

const state = reactive({
    validationError: false,
});

watchEffect(() => {
    if (props.show && props.Presupuestoa) {
        form.errors = {};
        state.validationError = false;

        form.nombre = props.Presupuestoa.nombre ?? '';
        form.descripcion = props.Presupuestoa.descripcion ?? '';
        form.fecha_inicio = props.Presupuestoa.fecha_inicio ? props.Presupuestoa.fecha_inicio.substring(0, 10) : '';
        form.fecha_fin = props.Presupuestoa.fecha_fin ? props.Presupuestoa.fecha_fin.substring(0, 10) : '';
        form.monto_total = props.Presupuestoa.monto_total ?? 0;
        form.monto_ejecutado = props.Presupuestoa.monto_ejecutado ?? 0;
        form.monto_disponible = props.Presupuestoa.monto_disponible ?? 0;
        form.total_salarios = props.Presupuestoa.total_salarios ?? 0;
        form.total_viaticos = props.Presupuestoa.total_viaticos ?? 0;
        form.total_materiales = props.Presupuestoa.total_materiales ?? 0;
        form.estado = props.Presupuestoa.estado ?? '';
        form.observaciones = props.Presupuestoa.observaciones ?? '';
        form.porcentaje_ejecutado = props.Presupuestoa.porcentaje_ejecutado ?? 0;
        form.umbral_alerta = props.Presupuestoa.umbral_alerta ?? 0;
        form.fecha_ultimo_reajuste = props.Presupuestoa.fecha_ultimo_reajuste ? props.Presupuestoa.fecha_ultimo_reajuste.substring(0, 10) : '';
        form.motivo_reajuste = props.Presupuestoa.motivo_reajuste ?? '';
    }
});

function validarVacios(): boolean {
    let isValid = true;
    if (props.titulos && Array.isArray(props.titulos)) {
        props.titulos.forEach((item) => {
            const val = (form as any)[item.order];
            if (!item.optional && (val === '' || val === null || val === undefined)) {
                console.log("Campo obligatorio vacío:", item.order);
                isValid = false;
            }
        });
    }
    return isValid;
}

const update = () => {
    if (validarVacios()) {
        state.validationError = false;
        form.put(route('Presupuesto.update', props.Presupuestoa?.id), {
            preserveScroll: true,
            onSuccess: () => {
                emit("close");
                form.reset();
            },
        });
    } else {
        state.validationError = true;
    }
};
</script>

<template>
    <section class="space-y-6">
        <Modal :show="props.show" @close="emit('close')" :maxWidth="'xl4'">
            <form class="p-6 space-y-6" @submit.prevent="update">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 border-b pb-3 dark:border-gray-700">
                    Editar {{ props.title || 'Presupuesto' }}
                </h2>

                <div v-if="state.validationError" class="p-3 bg-red-100 border border-red-400 text-red-700 rounded-lg text-sm">
                    Por favor complete todos los campos obligatorios antes de guardar.
                </div>

                <!-- Sección 1: Información General -->
                <div>
                    <h3 class="text-md font-medium text-gray-700 dark:text-gray-300 mb-3">Información General</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="edit_nombre" value="Nombre *" />
                            <TextInput id="edit_nombre" type="text" class="mt-1 block w-full" v-model="form.nombre" required />
                            <InputError class="mt-2" :message="form.errors.nombre" />
                        </div>
                        <div>
                            <InputLabel for="edit_estado" value="Estado *" />
                            <select
                                id="edit_estado"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 shadow-sm"
                                v-model="form.estado"
                                required
                            >
                                <option value="" disabled>Seleccione un estado</option>
                                <option v-for="item in ((props.losSelect as any)?.estado || [])" :key="item.id" :value="item.nombre">
                                    {{ item.nombre }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.estado" />
                        </div>
                        <div class="md:col-span-2">
                            <InputLabel for="edit_descripcion" value="Descripción" />
                            <textarea
                                id="edit_descripcion"
                                rows="2"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 shadow-sm"
                                v-model="form.descripcion"
                            ></textarea>
                            <InputError class="mt-2" :message="form.errors.descripcion" />
                        </div>
                    </div>
                </div>

                <!-- Sección 2: Montos y Saldos -->
                <div>
                    <h3 class="text-md font-medium text-gray-700 dark:text-gray-300 mb-3">Montos y Saldos</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <InputLabel for="edit_monto_total" value="Monto Total *" />
                            <TextInput id="edit_monto_total" type="number" step="0.01" class="mt-1 block w-full" v-model="form.monto_total" required />
                            <InputError class="mt-2" :message="form.errors.monto_total" />
                        </div>
                        <div>
                            <InputLabel for="edit_monto_ejecutado" value="Monto Ejecutado" />
                            <TextInput id="edit_monto_ejecutado" type="number" step="0.01" class="mt-1 block w-full" v-model="form.monto_ejecutado" />
                            <InputError class="mt-2" :message="form.errors.monto_ejecutado" />
                        </div>
                        <div>
                            <InputLabel for="edit_monto_disponible" value="Monto Disponible" />
                            <TextInput id="edit_monto_disponible" type="number" step="0.01" class="mt-1 block w-full" v-model="form.monto_disponible" />
                            <InputError class="mt-2" :message="form.errors.monto_disponible" />
                        </div>
                    </div>
                </div>

                <!-- Sección 3: Desglose de Costos -->
                <div>
                    <h3 class="text-md font-medium text-gray-700 dark:text-gray-300 mb-3">Desglose de Costos</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <InputLabel for="edit_total_salarios" value="Total Salarios" />
                            <TextInput id="edit_total_salarios" type="number" step="0.01" class="mt-1 block w-full" v-model="form.total_salarios" />
                            <InputError class="mt-2" :message="form.errors.total_salarios" />
                        </div>
                        <div>
                            <InputLabel for="edit_total_viaticos" value="Total Viáticos" />
                            <TextInput id="edit_total_viaticos" type="number" step="0.01" class="mt-1 block w-full" v-model="form.total_viaticos" />
                            <InputError class="mt-2" :message="form.errors.total_viaticos" />
                        </div>
                        <div>
                            <InputLabel for="edit_total_materiales" value="Total Materiales" />
                            <TextInput id="edit_total_materiales" type="number" step="0.01" class="mt-1 block w-full" v-model="form.total_materiales" />
                            <InputError class="mt-2" :message="form.errors.total_materiales" />
                        </div>
                    </div>
                </div>

                <!-- Sección 4: Métricas y Alertas -->
                <div>
                    <h3 class="text-md font-medium text-gray-700 dark:text-gray-300 mb-3">Métricas y Alertas</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="edit_porcentaje_ejecutado" value="Porcentaje Ejecutado (%)" />
                            <TextInput id="edit_porcentaje_ejecutado" type="number" step="0.01" class="mt-1 block w-full" v-model="form.porcentaje_ejecutado" />
                            <InputError class="mt-2" :message="form.errors.porcentaje_ejecutado" />
                        </div>
                        <div>
                            <InputLabel for="edit_umbral_alerta" value="Umbral Alerta (%)" />
                            <TextInput id="edit_umbral_alerta" type="number" step="0.01" class="mt-1 block w-full" v-model="form.umbral_alerta" />
                            <InputError class="mt-2" :message="form.errors.umbral_alerta" />
                        </div>
                    </div>
                </div>

                <!-- Sección 5: Fechas y Reajustes -->
                <div>
                    <h3 class="text-md font-medium text-gray-700 dark:text-gray-300 mb-3">Fechas y Reajustes</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <InputLabel for="edit_fecha_inicio" value="Fecha Inicio" />
                            <TextInput id="edit_fecha_inicio" type="date" class="mt-1 block w-full" v-model="form.fecha_inicio" />
                            <InputError class="mt-2" :message="form.errors.fecha_inicio" />
                        </div>
                        <div>
                            <InputLabel for="edit_fecha_fin" value="Fecha Fin" />
                            <TextInput id="edit_fecha_fin" type="date" class="mt-1 block w-full" v-model="form.fecha_fin" />
                            <InputError class="mt-2" :message="form.errors.fecha_fin" />
                        </div>
                        <div>
                            <InputLabel for="edit_fecha_ultimo_reajuste" value="Fecha Último Reajuste" />
                            <TextInput id="edit_fecha_ultimo_reajuste" type="date" class="mt-1 block w-full" v-model="form.fecha_ultimo_reajuste" />
                            <InputError class="mt-2" :message="form.errors.fecha_ultimo_reajuste" />
                        </div>
                        <div class="md:col-span-3">
                            <InputLabel for="edit_motivo_reajuste" value="Motivo Reajuste" />
                            <select
                                id="edit_motivo_reajuste"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 shadow-sm"
                                v-model="form.motivo_reajuste"
                            >
                                <option value="" disabled>Seleccione un motivo</option>
                                <option v-for="item in ((props.losSelect as any)?.motivo_reajuste || [])" :key="item.id" :value="item.nombre">
                                    {{ item.nombre }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.motivo_reajuste" />
                        </div>
                        <div class="md:col-span-3">
                            <InputLabel for="edit_observaciones" value="Observaciones" />
                            <textarea
                                id="edit_observaciones"
                                rows="2"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 shadow-sm"
                                v-model="form.observaciones"
                            ></textarea>
                            <InputError class="mt-2" :message="form.errors.observaciones" />
                        </div>
                    </div>
                </div>

                <div class="pt-4 flex justify-end border-t dark:border-gray-700">
                    <SecondaryButton :disabled="form.processing" @click="emit('close')">
                        Cancelar
                    </SecondaryButton>
                    <PrimaryButton class="ml-3" :class="{ 'opacity-25': form.processing }" :disabled="form.processing" @click="update">
                        Guardar Cambios {{ form.processing ? '...' : '' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    </section>
</template>
