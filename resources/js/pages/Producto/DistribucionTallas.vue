<script setup lang="ts">
import InputLabel from '@/components/InputLabel.vue';
import Modal from '@/components/Modal.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import { ClockIcon, ChevronDownIcon, ChevronRightIcon } from '@heroicons/vue/24/solid';
import { computed, watchEffect, ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    show: Boolean,
    title: String,
    productoId: Number,
    productoLabel: [String, Number],
    items: Array as () => Array<{ id?: number; talla?: string; cantidad_planificada?: number | string }>,
    // OPs que ya usan estas tallas (numero_op).
    ordenesTallas: { type: Array as () => Array<string>, default: () => [] },
})

const emit = defineEmits(["close"]);

const filas = computed(() =>
    (props.items || []).map((item: any) => ({
        id: item.id,
        talla: item.talla ?? '',
        cantidad_planificada: Number(item.cantidad_planificada) || 0,
    }))
);

const total = computed(() =>
    filas.value.reduce((acumulado: number, fila) => acumulado + fila.cantidad_planificada, 0)
);

const ordenes = computed(() => (props.ordenesTallas || []).filter(Boolean));

// Aviso del modal: las tallas ya están comprometidas en órdenes de producción.
const avisoOrdenes = computed(() => {
    const lista = ordenes.value.map((numero) => numero.toUpperCase()).join(', ');
    if (!ordenes.value.length) {
        return 'Estas tallas todavía no están usadas en ninguna orden de producción.';
    }
    if (ordenes.value.length === 1) {
        return `Estas tallas pertenecen a la ${lista}. Si desea modificarlas, diríjase al apartado de Órdenes de Producción.`;
    }
    return `Estas tallas pertenecen a ${ordenes.value.length} órdenes de producción (${lista}). Si desea modificarlas, diríjase al apartado de Órdenes de Producción.`;
});

// Historial de cambios
const historial = ref<Record<string, any[]>>({});
const cargandoHistorial = ref(false);
const mostrarHistorial = ref(false);

const cargarHistorial = async () => {
    if (!props.productoId) return;
    cargandoHistorial.value = true;
    try {
        const response = await axios.get(route('Producto.tallas-history', props.productoId));
        historial.value = response.data.historial || {};
    } catch (e) {
        console.error('Error al cargar historial:', e);
    } finally {
        cargandoHistorial.value = false;
    }
};

const toggleHistorial = () => {
    mostrarHistorial.value = !mostrarHistorial.value;
    if (mostrarHistorial.value && Object.keys(historial.value).length === 0) {
        cargarHistorial();
    }
};

const formatearFecha = (fecha: string) => {
    return new Date(fecha).toLocaleDateString('es-CO', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
};

const formatearHora = (fecha: string) => {
    return new Date(fecha).toLocaleTimeString('es-CO', {
        hour: '2-digit',
        minute: '2-digit',
    });
};

const accionEtiqueta = (accion: string) => {
    const etiquetas: Record<string, string> = {
        create: 'Creado',
        update: 'Actualizado',
        delete: 'Eliminado',
    };
    return etiquetas[accion] || accion;
};

const accionColor = (accion: string) => {
    const colores: Record<string, string> = {
        create: 'text-emerald-600 dark:text-emerald-400',
        update: 'text-blue-600 dark:text-blue-400',
        delete: 'text-red-600 dark:text-red-400',
    };
    return colores[accion] || 'text-gray-600';
};

watchEffect(() => {
    if (props.show) {
        // Reset historial al abrir el modal
        historial.value = {};
        mostrarHistorial.value = false;
    }
});

const closeModal = () => {
    emit("close");
};

</script>

<template>
    <section class="space-y-6">
        <Modal :show="props.show" @close="closeModal" :maxWidth="'lg'">
            <div class="p-6">
                <div class="flex items-center justify-between border-b pb-3 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Distribución por Tallas
                        <span v-if="props.productoLabel" class="text-sm font-normal text-gray-500 dark:text-gray-400">
                            — {{ props.productoLabel }}
                        </span>
                    </h3>
                    <button type="button" @click="closeModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        ✕
                    </button>
                </div>

                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Tallas y cantidades planificadas de este producto, solo lectura. Esta distribución sirve como
                    plantilla para las órdenes de producción.
                </p>

                <div v-if="filas.length === 0" class="mt-4 text-sm text-gray-500 dark:text-gray-400 text-center py-4">
                    Sin tallas registradas.
                </div>

                <div v-else class="mt-4 max-h-64 overflow-y-auto space-y-2">
                    <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        <span class="flex-1">Talla</span>
                        <span class="w-32 text-right">Cantidad</span>
                    </div>
                    <div
                        v-for="(fila, index) in filas"
                        :key="fila.id ?? `talla-${index}`"
                        class="flex items-center gap-2 border-b pb-2 dark:border-gray-700"
                    >
                        <div class="flex-1">
                            <InputLabel :for="`talla-${index}`" value="Talla" class="sr-only" />
                            <p :id="`talla-${index}`" class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                {{ fila.talla || '—' }}
                            </p>
                        </div>
                        <div class="w-32">
                            <InputLabel :for="`planificada-${index}`" value="Cantidad" class="sr-only" />
                            <p :id="`planificada-${index}`" class="text-right text-sm tabular-nums text-gray-900 dark:text-gray-100">
                                {{ fila.cantidad_planificada }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex justify-end items-center">
                    <div class="text-right">
                        <span class="text-xs text-gray-500 dark:text-gray-400 block">Total planificado:</span>
                        <span class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ total }}</span>
                    </div>
                </div>

                <!-- Historial de cambios -->
                <div class="mt-6 border-t pt-4 dark:border-gray-700">
                    <button
                        type="button"
                        @click="toggleHistorial"
                        class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100 transition"
                    >
                        <ClockIcon class="w-4 h-4" />
                        <span>Historial de cambios</span>
                        <ChevronDownIcon v-if="mostrarHistorial" class="w-4 h-4" />
                        <ChevronRightIcon v-else class="w-4 h-4" />
                    </button>

                    <div v-if="mostrarHistorial" class="mt-3">
                        <div v-if="cargandoHistorial" class="text-sm text-gray-500 dark:text-gray-400 py-4 text-center">
                            Cargando historial...
                        </div>
                        <div v-else-if="Object.keys(historial).length === 0" class="text-sm text-gray-500 dark:text-gray-400 py-4 text-center">
                            Sin cambios registrados.
                        </div>
                        <div v-else class="max-h-64 overflow-y-auto space-y-4">
                            <div v-for="(cambios, fecha) in historial" :key="fecha" class="space-y-2">
                                <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide sticky top-0 bg-white dark:bg-gray-800 py-1">
                                    {{ formatearFecha(fecha) }}
                                </h4>
                                <div
                                    v-for="cambio in cambios"
                                    :key="cambio.id"
                                    class="flex items-start gap-2 p-2 rounded-lg bg-gray-50 dark:bg-gray-700/50"
                                >
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="font-semibold text-sm text-gray-900 dark:text-gray-100">
                                                Talla {{ cambio.talla }}
                                            </span>
                                            <span class="text-xs px-2 py-0.5 rounded-full bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-300">
                                                {{ cambio.cantidad_planificada }} uds
                                            </span>
                                            <span :class="['text-xs font-medium', accionColor(cambio.accion)]">
                                                {{ accionEtiqueta(cambio.accion) }}
                                            </span>
                                        </div>
                                        <div v-if="cambio.datos_anteriores || cambio.datos_nuevos" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            <span v-if="cambio.datos_anteriores">
                                                Antes: {{ cambio.datos_anteriores.talla }} {{ cambio.datos_anteriores.cantidad_planificada }} uds
                                            </span>
                                            <span v-if="cambio.datos_anteriores && cambio.datos_nuevos" class="mx-1">→</span>
                                            <span v-if="cambio.datos_nuevos">
                                                Ahora: {{ cambio.datos_nuevos.talla }} {{ cambio.datos_nuevos.cantidad_planificada }} uds
                                            </span>
                                        </div>
                                        <div class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                            {{ formatearHora(cambio.created_at) }}
                                            <span v-if="cambio.user">· {{ cambio.user.name }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Resumen de las OPs que usan estas tallas -->
                <p
                    class="mt-4 rounded-md border px-3 py-2 text-xs"
                    :class="ordenes.length
                        ? 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-300'
                        : 'border-gray-200 bg-gray-50 text-gray-600 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400'"
                >
                    {{ avisoOrdenes }}
                </p>

                <div class="mt-6 flex justify-end space-x-3 border-t pt-4 dark:border-gray-700">
                    <SecondaryButton type="button" @click="closeModal">
                        {{ lang().t('app.button.close') }}
                    </SecondaryButton>
                </div>
            </div>
        </Modal>
    </section>
</template>
