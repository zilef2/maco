<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import InputLabel from '@/components/InputLabel.vue';
import Modal from '@/components/Modal.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import axios from 'axios';
import { reactive, ref, watchEffect } from 'vue';
import { router } from '@inertiajs/vue3';
import vSelect from 'vue-select';
import 'vue-select/dist/vue-select.css';

const props = defineProps({
    show: Boolean,
    op: {
        type: Object,
        default: null,
    },
});
const emit = defineEmits(['close']);

const cargando = ref(false);
const errorGeneral = ref('');
const datos = ref<any>(null);

const hoy = () => new Date().toISOString().split('T')[0];

const form = reactive({
    proveedor_destino_id: '' as number | string,
    etapa_componente: '',
    bodega_origen: '',
    servicio_proceso: '',
    fecha_salida: hoy(),
    fecha_prevista_regreso: '',
    resultado_observaciones: '',
});

const items = () => datos.value?.items ?? [];
const hayFaltantes = () => items().some((i: any) => Number(i.faltante) > 0);

const cargar = async (opId: number) => {
    cargando.value = true;
    errorGeneral.value = '';
    datos.value = null;
    try {
        const res = await axios.get(route('ProcesoExterno.materialesOp', opId));
        datos.value = res.data;
        form.proveedor_destino_id = res.data.proveedor_sugerido_id ?? '';
        form.fecha_salida = hoy();
    } catch (e: any) {
        errorGeneral.value = 'No se pudieron cargar los materiales de la OP.';
    } finally {
        cargando.value = false;
    }
};

watchEffect(() => {
    if (props.show && props.op?.id) {
        Object.assign(form, {
            etapa_componente: '',
            bodega_origen: '',
            servicio_proceso: '',
            fecha_prevista_regreso: '',
            resultado_observaciones: '',
        });
        cargar(props.op.id);
    }
});

const enviando = ref(false);

const enviar = async () => {
    if (!props.op?.id || enviando.value) return;
    enviando.value = true;
    errorGeneral.value = '';
    try {
        await axios.post(route('ProcesoExterno.salidaOp'), {
            orden_produccion_id: props.op.id,
            ...form,
            items: items().map((i: any) => ({
                material_origen_id: i.material_origen_id,
                cantidad_salida: i.cantidad_salida,
                unidad_medida: i.unidad_medida,
            })),
        });
        emit('close');
        router.reload();
    } catch (e: any) {
        const errs = e.response?.data?.errors;
        errorGeneral.value = errs?.items?.[0] || errs?.['items.0.cantidad_salida']?.[0]
            || e.response?.data?.message
            || 'No se pudo registrar la salida. Revise los datos.';
    } finally {
        enviando.value = false;
    }
};
</script>

<template>
    <section class="space-y-6">
        <Modal :show="props.show" @close="emit('close')" :maxWidth="'xl4'">
            <div v-if="cargando" class="p-6 space-y-4">
                <div class="h-6 w-1/3 rounded bg-gray-200 dark:bg-gray-700 animate-pulse"></div>
                <div class="h-32 rounded bg-gray-200 dark:bg-gray-700 animate-pulse"></div>
            </div>

            <div v-else-if="!datos" class="p-6">
                <p class="text-sm text-red-600 dark:text-red-400">{{ errorGeneral }}</p>
                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="emit('close')">Cerrar</SecondaryButton>
                </div>
            </div>

            <form v-else class="p-6 space-y-5" @submit.prevent="enviar">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        Sacar materiales al taller — OP {{ datos.numero_op }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Taller: {{ datos.taller_nombre || '—' }} · Cantidad planificada: {{ datos.cantidad_planificada }}.
                        Se creará un Proceso Externo por cada material y se descontará del inventario.
                    </p>
                </div>

                <p v-if="!items().length" class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm font-medium text-amber-800 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-200">
                    Esta OP no requiere materiales: sus productos no tienen ficha técnica de materiales con consumo.
                </p>

                <div v-else class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800 text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <tr>
                                <th class="px-3 py-2">Material</th>
                                <th class="px-3 py-2 text-right">Cantidad a sacar</th>
                                <th class="px-3 py-2">Unidad</th>
                                <th class="px-3 py-2 text-right">En inventario</th>
                                <th class="px-3 py-2">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in items()" :key="item.material_origen_id"
                                class="border-t border-gray-100 dark:border-gray-700/60">
                                <td class="px-3 py-2 font-medium text-gray-800 dark:text-gray-200">{{ item.material_nombre }}</td>
                                <td class="px-3 py-2 text-right">{{ item.cantidad_salida }}</td>
                                <td class="px-3 py-2">{{ item.unidad_medida || '—' }}</td>
                                <td class="px-3 py-2 text-right">{{ item.existencia_actual }}</td>
                                <td class="px-3 py-2">
                                    <span v-if="Number(item.faltante) > 0"
                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300">
                                        Faltan {{ item.faltante }}
                                    </span>
                                    <span v-else
                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                                        OK
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <template v-if="items().length">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                        <div>
                            <InputLabel value="Proveedor / taller destino *" />
                            <v-select :options="datos.proveedores" label="nombre"
                                :reduce="(item: any) => item.id" v-model="form.proveedor_destino_id"
                                placeholder="Seleccione proveedor destino" class="mt-1 block w-full" />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Sugerido automáticamente según el taller de la OP.
                            </p>
                        </div>

                        <div>
                            <InputLabel for="etapa_componente_pe" value="Etapa / componente" />
                            <TextInput id="etapa_componente_pe" type="text" class="mt-1 block w-full h-10 px-3"
                                v-model="form.etapa_componente" placeholder="Ej: embarretado, corte..." />
                        </div>

                        <div>
                            <InputLabel for="bodega_origen_pe" value="Bodega origen" />
                            <TextInput id="bodega_origen_pe" type="text" class="mt-1 block w-full h-10 px-3"
                                v-model="form.bodega_origen" placeholder="Ej: Bodega principal" />
                        </div>

                        <div>
                            <InputLabel for="servicio_proceso_pe" value="Servicio / proceso" />
                            <TextInput id="servicio_proceso_pe" type="text" class="mt-1 block w-full h-10 px-3"
                                v-model="form.servicio_proceso" placeholder="Ej: sublimación" />
                        </div>

                        <div>
                            <InputLabel for="fecha_salida_pe" value="Fecha de salida *" />
                            <TextInput id="fecha_salida_pe" type="date" class="mt-1 block w-full h-10 px-3"
                                v-model="form.fecha_salida" required />
                        </div>

                        <div>
                            <InputLabel for="fecha_regreso_pe" value="Fecha prevista de regreso" />
                            <TextInput id="fecha_regreso_pe" type="date" class="mt-1 block w-full h-10 px-3"
                                v-model="form.fecha_prevista_regreso" />
                        </div>

                        <div class="md:col-span-2">
                            <InputLabel for="obs_pe" value="Observaciones" />
                            <textarea id="obs_pe" v-model="form.resultado_observaciones" rows="2"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-primary dark:focus:border-primary focus:ring-primary dark:focus:ring-primary shadow-sm text-sm"
                                placeholder="Observaciones de la salida (opcional)"></textarea>
                        </div>
                    </div>

                    <p v-if="hayFaltantes()"
                        class="rounded-lg border border-red-300 bg-red-50 p-3 text-sm font-medium text-red-700 dark:border-red-700 dark:bg-red-900/30 dark:text-red-300">
                        Hay materiales con stock insuficiente para la cantidad que pide la OP. Reabastezca o ajuste la OP antes de continuar: la salida no se registrará.
                    </p>

                    <p v-if="errorGeneral"
                        class="rounded-lg border border-red-300 bg-red-50 p-3 text-sm font-medium text-red-700 dark:border-red-700 dark:bg-red-900/30 dark:text-red-300">
                        {{ errorGeneral }}
                    </p>

                    <div class="flex justify-end pt-3 border-t border-gray-200 dark:border-gray-700">
                        <SecondaryButton :disabled="enviando" @click="emit('close')">Cancelar</SecondaryButton>
                        <PrimaryButton class="ml-3" type="submit"
                            :disabled="enviando || hayFaltantes() || !form.proveedor_destino_id || !form.fecha_salida">
                            {{ enviando ? 'Registrando...' : 'Registrar salida' }}
                        </PrimaryButton>
                    </div>
                </template>

                <div v-else class="flex justify-end">
                    <SecondaryButton @click="emit('close')">Cerrar</SecondaryButton>
                </div>
            </form>
        </Modal>
    </section>
</template>
