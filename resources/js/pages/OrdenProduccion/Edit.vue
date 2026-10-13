<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import InputLabel from '@/components/InputLabel.vue';
import Modal from '@/components/Modal.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import { CalculatorIcon, ListBulletIcon, SquaresPlusIcon, TrashIcon, XMarkIcon } from '@heroicons/vue/24/solid';
import { useForm } from '@inertiajs/vue3';
import { computed, reactive, ref, watchEffect } from 'vue';
import vSelect from "vue-select";
import "vue-select/dist/vue-select.css";

const props = defineProps({
    show: Boolean,
    title: String,
    OrdenProducciona: Object,
    titulos: Object,
    losSelect: Object,
    numberPermissions: Number,
});
const emit = defineEmits(["close"]);

const verificador = reactive({
    show: false,
    items: [] as Array<{ nombre: string; cantidad: string }>,
});

const distribucion = reactive({
    show: false,
    items: [] as Array<{ id?: number; talla: string; cantidad: string }>,
});

const productos = reactive({
    show: false,
    temporal: [] as number[],
    aplicados: [] as Array<{ id: number; nombre: string }>,
});

const opcionesProducto = computed(() => props.losSelect?.producto_referencia_id || []);

const grupoTallas = ref<number | null>(null);

const form = useForm({
    numero_op: '',
    producto_referencia_id: '',
    cantidad_planificada: '',
    fecha_creacion: '',
    fecha_prevista_envio_taller: '',
    taller_id: '',
    user_id: '',
    id_shopify_producto: '',
    observaciones: '',
    distribucion_tallas: [] as Array<{ id?: number; talla: string; cantidad_planificada: number }>,
    grupo_tallas: null as number | null,
    productos: [] as number[],
});

const totalVerificador = computed(() => {
    return verificador.items.reduce((total, item) => {
        const val = Number(item.cantidad);
        return total + (isNaN(val) ? 0 : val);
    }, 0);
});

const totalDistribucion = computed(() => {
    return distribucion.items.reduce((total, item) => {
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

const abrirDistribucion = () => {
    if (distribucion.items.length === 0 && form.cantidad_planificada) {
        distribucion.items = [
            { talla: '', cantidad: form.cantidad_planificada ?? '' }
        ];
    }
    distribucion.show = true;
};

const agregarTalla = () => {
    distribucion.items.push({ talla: '', cantidad: '' });
};

const eliminarTalla = (index: number) => {
    distribucion.items.splice(index, 1);
};

const aplicarDistribucion = () => {
    form.cantidad_planificada = String(totalDistribucion.value);
    distribucion.show = false;
};

const elegirGrupo = (grupo: any) => {
    const g = grupo ?? (props.losSelect?.grupos_tallas || []).find((x: any) => x.id === grupoTallas.value);
    if (!g || !g.tallas) {
        return;
    }
    grupoTallas.value = g.id;
    const existentes = new Set(distribucion.items.map((i) => (i.talla ?? '').trim().toLowerCase()));
    (g.tallas || []).forEach((t: any) => {
        if (!existentes.has(String(t.talla ?? '').trim().toLowerCase())) {
            distribucion.items.push({ id: t.id, talla: t.talla ?? '', cantidad: String(t.cantidad_planificada ?? '') });
        }
    });
};

const abrirProductos = () => {
    productos.temporal = productos.aplicados.map((p) => p.id);
    productos.show = true;
};

const aplicarProductos = () => {
    productos.aplicados = opcionesProducto.value
        .filter((p: any) => productos.temporal.includes(p.id))
        .map((p: any) => ({ id: p.id, nombre: p.name }));
    productos.show = false;
};

const quitarProducto = (id: number) => {
    productos.aplicados = productos.aplicados.filter((p) => p.id !== id);
};

const closeModal = () => {
    emit("close");
    form.reset();
    verificador.items = [];
    verificador.show = false;
    distribucion.items = [];
    distribucion.show = false;
    productos.show = false;
    productos.aplicados = [];
    productos.temporal = [];
    grupoTallas.value = null;
};

const update = () => {
    form.grupo_tallas = grupoTallas.value;
    form.productos = productos.aplicados.map((p) => p.id);
    form.distribucion_tallas = distribucion.items
        .filter((item) => (item.talla && item.talla.trim() !== '') || Number(item.cantidad) > 0)
        .map((item) => ({
            ...(item.id ? { id: item.id } : {}),
            talla: (item.talla ?? '').trim(),
            cantidad_planificada: Number(item.cantidad) || 0,
        }));
    form.put(route('OrdenProduccion.update', (props.OrdenProducciona as any)?.id), {
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
        const op: any = props.OrdenProducciona ?? {};
        form.numero_op = op.numero_op ?? '';
        form.producto_referencia_id = op.producto_referencia_id ?? '';
        form.cantidad_planificada = op.cantidad_planificada ?? '';
        form.fecha_creacion = String(op.fecha_creacion ?? '').substring(0, 10);
        form.fecha_prevista_envio_taller = String(op.fecha_prevista_envio_taller ?? '').substring(0, 10);
        form.taller_id = op.taller_id ?? '';
        form.user_id = op.user_id ?? '';
        form.id_shopify_producto = op.id_shopify_producto ?? '';
        form.observaciones = op.observaciones ?? '';
        form.distribucion_tallas = [];
        grupoTallas.value = null;
        distribucion.items = ((op.distribuciones_por_tallas || []) as any[]).map((t: any) => ({
            id: t.id,
            talla: t.talla ?? '',
            cantidad: String(t.cantidad_planificada ?? ''),
        }));
        productos.aplicados = ((op.productos || []) as any[]).map((p: any) => ({
            id: p.id,
            nombre: p.Titulo_Shopify || p.Codigo_interno || `Producto #${p.id}`,
        }));
    }
});
</script>

<template>
    <section class="space-y-6">
        <Modal :show="props.show" @close="closeModal" :maxWidth="'xl4'">
            <form class="p-6" @submit.prevent="update">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-6">
                    {{ lang().t('app.label.edit') }} {{ props.title }}
                    --- {{ props.numberPermissions }}
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <InputLabel for="numero_op" :value="lang().t('app.label.numero_op') || 'Número OP'" />
                        <TextInput
                            id="numero_op"
                            type="text"
                            class="mt-1 block w-full"
                            v-model="form.numero_op"
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
                        <button
                            type="button"
                            @click="abrirProductos"
                            class="mt-2 inline-flex items-center px-3 py-1.5 rounded-md border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-200 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-600 transition"
                        >
                            <ListBulletIcon class="w-4 h-4 mr-1 text-blue-600 dark:text-blue-400" />
                            Lista de productos{{ productos.aplicados.length ? ` (${productos.aplicados.length})` : '' }}
                        </button>
                        <div v-if="productos.aplicados.length" class="mt-2 flex flex-wrap gap-1">
                            <span
                                v-for="p in productos.aplicados"
                                :key="p.id"
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300"
                            >
                                {{ p.nombre }}
                                <button type="button" @click="quitarProducto(p.id)" class="hover:text-red-600">
                                    <XMarkIcon class="w-3.5 h-3.5" />
                                </button>
                            </span>
                        </div>
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
                                class="inline-flex items-center px-4 py-2 border border-l-0 border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-200 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-600 transition"
                            >
                                <CalculatorIcon class="w-4 h-4 mr-1 text-indigo-600 dark:text-indigo-400" />
                                Verificar suma
                            </button>
                            <button
                                type="button"
                                @click="abrirDistribucion"
                                class="inline-flex items-center px-4 py-2 border border-l-0 border-gray-300 dark:border-gray-700 rounded-r-md bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-200 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-600 transition"
                            >
                                <SquaresPlusIcon class="w-4 h-4 mr-1 text-emerald-600 dark:text-emerald-400" />
                                Tallas{{ distribucion.items.length ? ` (${distribucion.items.length})` : '' }}
                            </button>
                        </div>
                        <p v-if="distribucion.items.length" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Distribución por tallas: {{ distribucion.items.length }} talla(s) · total {{ totalDistribucion }} — quedará asociada a esta orden.
                        </p>
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
                        {{ lang().t('app.button.save') }} {{ form.processing ? '...' : '' }}
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

        <Modal :show="distribucion.show" @close="distribucion.show = false" :maxWidth="'lg'">
            <div class="p-6">
                <div class="flex items-center justify-between border-b pb-3 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Distribución por Tallas
                    </h3>
                    <button type="button" @click="distribucion.show = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        ✕
                    </button>
                </div>

                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Defina las tallas y sus cantidades. El total se aplicará como cantidad planificada y las filas quedarán asociadas a esta orden de producción.
                </p>

                <div class="mt-3">
                    <InputLabel for="grupo_tallas_edit" :value="'Grupo de tallas (sugerencia)'" />
                    <v-select
                        id="grupo_tallas_edit"
                        class="mt-1 block w-full"
                        :options="props.losSelect?.grupos_tallas || []"
                        label="name"
                        :reduce="(item: any) => item.id"
                        v-model="grupoTallas"
                        @option:selected="(opt: any) => elegirGrupo(opt)"
                        placeholder="Seleccione un grupo para cargar sus tallas..."
                    />
                </div>

                <div class="mt-4 max-h-64 overflow-y-auto space-y-3">
                    <div
                        v-for="(item, index) in distribucion.items"
                        :key="item.id ?? `nueva-${index}`"
                        class="flex items-center space-x-2"
                    >
                        <TextInput
                            type="text"
                            v-model="item.talla"
                            placeholder="Talla (ej: S, M, L)"
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
                            @click="eliminarTalla(index)"
                            class="p-2 text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-md transition"
                            title="Eliminar talla"
                        >
                            <TrashIcon class="w-5 h-5" />
                        </button>
                    </div>
                </div>

                <div class="mt-4 flex justify-between items-center">
                    <SecondaryButton type="button" @click="agregarTalla" class="text-xs">
                        + Agregar talla
                    </SecondaryButton>
                    <div class="text-right">
                        <span class="text-xs text-gray-500 dark:text-gray-400 block">Total calculado:</span>
                        <span class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ totalDistribucion }}</span>
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3 border-t pt-4 dark:border-gray-700">
                    <SecondaryButton type="button" @click="distribucion.show = false">
                        Cancelar
                    </SecondaryButton>
                    <PrimaryButton type="button" @click="aplicarDistribucion">
                        Aplicar a Cantidad ({{ totalDistribucion }})
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

        <Modal :show="productos.show" @close="productos.show = false" :maxWidth="'lg'">
            <div class="p-6">
                <div class="flex items-center justify-between border-b pb-3 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Lista de Productos de la Orden
                    </h3>
                    <button type="button" @click="productos.show = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        ✕
                    </button>
                </div>

                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Busque y seleccione uno o varios productos. Quedarán asociados a esta orden de producción.
                </p>

                <div class="mt-4">
                    <v-select
                        multiple
                        class="block w-full"
                        :options="opcionesProducto"
                        label="name"
                        :reduce="(item: any) => item.id"
                        v-model="productos.temporal"
                        placeholder="Buscar producto..."
                    />
                </div>

                <div v-if="productos.temporal.length" class="mt-4 max-h-64 overflow-y-auto space-y-2">
                    <div
                        v-for="pid in productos.temporal"
                        :key="pid"
                        class="flex items-center justify-between px-3 py-2 rounded-md border border-gray-200 dark:border-gray-700"
                    >
                        <span class="text-sm text-gray-800 dark:text-gray-200">
                            {{ opcionesProducto.find((p: any) => p.id === pid)?.name ?? ('Producto #' + pid) }}
                        </span>
                        <button
                            type="button"
                            @click="productos.temporal = productos.temporal.filter((x) => x !== pid)"
                            class="p-1.5 text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-md transition"
                            title="Quitar producto"
                        >
                            <TrashIcon class="w-4 h-4" />
                        </button>
                    </div>
                </div>
                <p v-else class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                    Todavía no ha seleccionado productos.
                </p>

                <div class="mt-6 flex justify-end space-x-3 border-t pt-4 dark:border-gray-700">
                    <SecondaryButton type="button" @click="productos.show = false">
                        Cancelar
                    </SecondaryButton>
                    <PrimaryButton type="button" @click="aplicarProductos">
                        Aplicar{{ productos.temporal.length ? ` (${productos.temporal.length})` : '' }}
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </section>
</template>
