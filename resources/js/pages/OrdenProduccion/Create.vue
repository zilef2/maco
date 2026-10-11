<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import InputLabel from '@/components/InputLabel.vue';
import Modal from '@/components/Modal.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import { CalculatorIcon, TrashIcon } from '@heroicons/vue/24/solid';
import { useForm } from '@inertiajs/vue3';
import { computed, reactive, watchEffect } from 'vue';
import vSelect from "vue-select";
import "vue-select/dist/vue-select.css";

const props = defineProps({
    show: Boolean,
    title: String,
    roles: Object,
    titulos: Object,
    losSelect: Object,
    numberPermissions: Number,
});
const emit = defineEmits(["close"]);

const verificador = reactive({
    show: false,
    items: [] as Array<{ nombre: string; cantidad: string }>,
});

const form = useForm({
    numero_op: '',
    producto_referencia_id: '',
    cantidad_planificada: '',
    fecha_creacion: '',
    fecha_prevista_envio_taller: '',
    taller_id: '',
    estado_op_id: '',
    user_id: '',
    id_shopify_producto: '',
    observaciones: '',
});

const totalVerificador = computed(() => {
    return verificador.items.reduce((total, item) => {
        const val = Number(item.cantidad);
        return total + (isNaN(val) ? 0 : val);
    }, 0);
});

const abrirVerificador = () => {
    if (verificador.items.length === 0) {
        verificador.items = [
            { nombre: '', cantidad: form.cantidad_planificada ?? '' }
        ];
    }
    verificador.show = true;
};

const agregarItem = () => {
    verificador.items.push({ nombre: '', cantidad: '' });
};

const eliminarItem = (index: number) => {
    verificador.items.splice(index, 1);
};

const aplicarTotal = () => {
    form.cantidad_planificada = String(totalVerificador.value);
    verificador.show = false;
};

const closeModal = () => {
    emit("close");
    form.reset();
    verificador.items = [];
    verificador.show = false;
};

const create = () => {
    form.post(route('OrdenProduccion.store'), {
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
        if (!form.numero_op) {
            form.numero_op = String(props.losSelect?.siguiente_numero_op ?? 100);
        }
        if (!form.fecha_creacion) {
            form.fecha_creacion = new Date().toISOString().split('T')[0];
        }
        if (!form.estado_op_id && props.losSelect?.estado_op_id) {
            const estadoPrep = (props.losSelect.estado_op_id as any[]).find((item: any) =>
                (item.name || item.label || '').toLowerCase().includes('preparaci')
            );
            if (estadoPrep) {
                form.estado_op_id = estadoPrep.id;
            } else if (props.losSelect.estado_op_id[0]) {
                form.estado_op_id = props.losSelect.estado_op_id[0].id;
            }
        }
    }
});

watchEffect(() => {
    if (props.show && props.numberPermissions > 99) {
        if (!form.numero_op) {
            form.numero_op = String(Math.floor(100000 + Math.random() * 900000));
        }
        if (!form.producto_referencia_id && props.losSelect?.producto_referencia_id?.length) {
            const arr = props.losSelect.producto_referencia_id;
            form.producto_referencia_id = arr[Math.floor(Math.random() * arr.length)].id;
        }
        if (!form.cantidad_planificada) {
            form.cantidad_planificada = String(Math.floor(Math.random() * 900 + 100));
        }
        if (!form.fecha_creacion) {
            form.fecha_creacion = new Date().toISOString().split('T')[0];
        }
        if (!form.fecha_prevista_envio_taller) {
            form.fecha_prevista_envio_taller = new Date(Date.now() + 15 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
        }
        if (!form.taller_id && props.losSelect?.taller_id?.length) {
            const arr = props.losSelect.taller_id;
            form.taller_id = arr[Math.floor(Math.random() * arr.length)].id;
        }
        if (!form.estado_op_id && props.losSelect?.estado_op_id?.length) {
            const arr = props.losSelect.estado_op_id;
            form.estado_op_id = arr[Math.floor(Math.random() * arr.length)].id;
        }
        if (!form.user_id && (props.losSelect?.user_id?.length || props.losSelect?.responsable_id?.length)) {
            const arr = props.losSelect.user_id ?? props.losSelect.responsable_id;
            form.user_id = arr[Math.floor(Math.random() * arr.length)].id;
        }
        if (!form.id_shopify_producto) {
            form.id_shopify_producto = Math.random().toString(36).substring(2, 10).toUpperCase();
        }
        if (!form.observaciones) {
            form.observaciones = 'Orden de producción generada automáticamente para superadmin';
        }
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
                        <InputLabel for="numero_op" :value="lang().t('app.label.numero_op') || 'Número OP'" />
                        <TextInput
                            id="numero_op"
                            type="text"
                            class="mt-1 block w-full bg-gray-100 dark:bg-gray-800 cursor-not-allowed"
                            v-model="form.numero_op"
                            readonly
                            required
                        />
                        <InputError class="mt-2" :message="form.errors.numero_op" />
                    </div>

                    <div>
                        <InputLabel for="producto_referencia_id" :value="lang().t('app.label.producto') || 'Producto'" />
                        <v-select
                            id="producto_referencia_id"
                            class="mt-1 block w-full"
                            :options="props.losSelect?.producto_referencia_id || []"
                            label="name"
                            :reduce="(item: any) => item.id"
                            v-model="form.producto_referencia_id"
                            placeholder="Buscar producto..."
                        />
                        <InputError class="mt-2" :message="form.errors.producto_referencia_id" />
                    </div>

                    <div>
                        <InputLabel for="cantidad_planificada" :value="lang().t('app.label.cantidad_planificada') || 'Cantidad Planificada'" />
                        <div class="mt-1 flex rounded-md shadow-sm">
                            <TextInput
                                id="cantidad_planificada"
                                type="number"
                                min="1"
                                class="block w-full rounded-none rounded-l-md"
                                v-model="form.cantidad_planificada"
                                required
                                placeholder="0"
                            />
                            <button
                                type="button"
                                @click="abrirVerificador"
                                class="inline-flex items-center px-4 py-2 border border-l-0 border-gray-300 dark:border-gray-700 rounded-r-md bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-200 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-600 transition"
                            >
                                <CalculatorIcon class="w-4 h-4 mr-1 text-indigo-600 dark:text-indigo-400" />
                                Verificar suma
                            </button>
                        </div>
                        <InputError class="mt-2" :message="form.errors.cantidad_planificada" />
                    </div>

                    <div>
                        <InputLabel for="fecha_creacion" :value="lang().t('app.label.fecha_creacion') || 'Fecha Creación'" />
                        <TextInput
                            id="fecha_creacion"
                            type="date"
                            class="mt-1 block w-full"
                            v-model="form.fecha_creacion"
                            required
                        />
                        <InputError class="mt-2" :message="form.errors.fecha_creacion" />
                    </div>

                    <div>
                        <InputLabel for="fecha_prevista_envio_taller" :value="lang().t('app.label.fecha_prevista_envio_taller') || 'Fecha Prevista Envío Taller'" />
                        <TextInput
                            id="fecha_prevista_envio_taller"
                            type="date"
                            class="mt-1 block w-full"
                            v-model="form.fecha_prevista_envio_taller"
                            required
                        />
                        <InputError class="mt-2" :message="form.errors.fecha_prevista_envio_taller" />
                    </div>

                    <div>
                        <InputLabel for="taller_id" :value="lang().t('app.label.taller') || 'Taller'" />
                        <v-select
                            id="taller_id"
                            class="mt-1 block w-full"
                            :options="props.losSelect?.taller_id || []"
                            label="name"
                            :reduce="(item: any) => item.id"
                            v-model="form.taller_id"
                            placeholder="Seleccione un taller..."
                        />
                        <InputError class="mt-2" :message="form.errors.taller_id" />
                    </div>

                    <div>
                        <InputLabel for="estado_op_id" :value="lang().t('app.label.estado_op_id') || 'Estado OP'" />
                        <v-select
                            id="estado_op_id"
                            class="mt-1 block w-full"
                            :options="props.losSelect?.estado_op_id || []"
                            label="name"
                            :reduce="(item: any) => item.id"
                            v-model="form.estado_op_id"
                            placeholder="Seleccione estado..."
                        />
                        <InputError class="mt-2" :message="form.errors.estado_op_id" />
                    </div>

                    <div>
                        <InputLabel for="user_id" :value="lang().t('app.label.responsable_id') || 'Responsable'" />
                        <v-select
                            id="user_id"
                            class="mt-1 block w-full"
                            :options="props.losSelect?.user_id || props.losSelect?.responsable_id || []"
                            label="name"
                            :reduce="(item: any) => item.id"
                            v-model="form.user_id"
                            placeholder="Buscar responsable..."
                        />
                        <InputError class="mt-2" :message="form.errors.user_id" />
                    </div>

                    <div>
                        <InputLabel for="id_shopify_producto" :value="lang().t('app.label.id_shopify_producto') || 'ID Shopify Producto'" />
                        <TextInput
                            id="id_shopify_producto"
                            type="text"
                            class="mt-1 block w-full"
                            v-model="form.id_shopify_producto"
                            placeholder="ID Shopify (opcional)"
                        />
                        <InputError class="mt-2" :message="form.errors.id_shopify_producto" />
                    </div>

                    <div>
                        <InputLabel for="observaciones" :value="lang().t('app.label.observaciones') || 'Observaciones'" />
                        <textarea
                            id="observaciones"
                            v-model="form.observaciones"
                            rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 shadow-sm"
                            placeholder="Observaciones de la orden..."
                        ></textarea>
                        <InputError class="mt-2" :message="form.errors.observaciones" />
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

        <Modal :show="verificador.show" @close="verificador.show = false" :maxWidth="'lg'">
            <div class="p-6">
                <div class="flex items-center justify-between border-b pb-3 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Verificador de Suma de Ítems
                    </h3>
                    <button type="button" @click="verificador.show = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        ✕
                    </button>
                </div>

                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Agregue los ítems con su nombre y cantidad. El total sumado será asignado como la cantidad planificada.
                </p>

                <div class="mt-4 max-h-64 overflow-y-auto space-y-3">
                    <div
                        v-for="(item, index) in verificador.items"
                        :key="index"
                        class="flex items-center space-x-2"
                    >
                        <TextInput
                            type="text"
                            v-model="item.nombre"
                            placeholder="Nombre del ítem (ej: Talla M)"
                            class="block w-2/3 text-sm"
                        />
                        <TextInput
                            type="number"
                            min="0"
                            v-model="item.cantidad"
                            placeholder="Cantidad"
                            class="block w-1/3 text-sm"
                        />
                        <button
                            type="button"
                            @click="eliminarItem(index)"
                            class="p-2 text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-md transition"
                            title="Eliminar ítem"
                        >
                            <TrashIcon class="w-5 h-5" />
                        </button>
                    </div>
                </div>

                <div class="mt-4 flex justify-between items-center">
                    <SecondaryButton type="button" @click="agregarItem" class="text-xs">
                        + Agregar ítem
                    </SecondaryButton>
                    <div class="text-right">
                        <span class="text-xs text-gray-500 dark:text-gray-400 block">Total calculado:</span>
                        <span class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ totalVerificador }}</span>
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3 border-t pt-4 dark:border-gray-700">
                    <SecondaryButton type="button" @click="verificador.show = false">
                        Cancelar
                    </SecondaryButton>
                    <PrimaryButton type="button" @click="aplicarTotal">
                        Aplicar a Cantidad ({{ totalVerificador }})
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </section>
</template>
