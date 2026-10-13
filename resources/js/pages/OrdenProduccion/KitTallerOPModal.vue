<script setup lang="ts">
import Modal from '@/components/Modal.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import { ref, watch } from 'vue';

const props = defineProps({
    show: Boolean,
    ordenProduccion: { type: Object, default: null },
});

const emit = defineEmits(['close']);

const filas = ref<any[]>([]);
const cargando = ref(false);
const errorCarga = ref<string | null>(null);

const formatear = (valor: any, decimales = 2) => {
    const num = parseFloat(String(valor ?? '').replace(/[^0-9.-]/g, ''));
    return isNaN(num) ? '0.00' : num.toFixed(decimales);
};

const cargar = async () => {
    filas.value = [];
    errorCarga.value = null;
    cargando.value = true;
    try {
        const respuesta = await fetch(
            route('KitTaller.por-orden', { id: props.ordenProduccion?.id }),
            {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
            },
        );
        if (!respuesta.ok) {
            throw new Error('HTTP ' + respuesta.status);
        }
        const json = await respuesta.json();
        filas.value = json.kits ?? [];
    } catch {
        errorCarga.value = 'No se pudo cargar el kit de esta orden de producción.';
    } finally {
        cargando.value = false;
    }
};

const closeModal = () => emit('close');

watch(() => props.show, (visible) => {
    if (visible) {
        cargar();
    } else {
        filas.value = [];
        errorCarga.value = null;
    }
});
</script>

<template>
    <Modal :show="props.show" @close="closeModal" :maxWidth="'xl4'">
        <div class="p-6">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                        KitTaller
                    </h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Orden de producción:
                        <b>{{ props.ordenProduccion?.numero_op ?? props.ordenProduccion?.id }}</b>
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <PrimaryButton type="button" @click="cargar" :disabled="cargando">
                        Actualizar
                    </PrimaryButton>
                    <button type="button" @click="closeModal"
                        class="rounded-md p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition"
                        aria-label="Cerrar">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <div v-if="cargando" class="text-center py-8 text-gray-500 dark:text-gray-400">
                Cargando…
            </div>

            <div v-else-if="errorCarga"
                class="rounded-lg bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700 px-4 py-3 text-sm text-red-700 dark:text-red-300">
                {{ errorCarga }}
            </div>

            <div v-else-if="filas.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
                Esta orden de producción no tiene kits cargados.
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="uppercase text-xs text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                        <tr class="text-left">
                            <th class="px-2 py-2">#</th>
                            <th class="px-2 py-2">Material</th>
                            <th class="px-2 py-2">Estado requerido</th>
                            <th class="px-2 py-2">Unidad</th>
                            <th class="px-2 py-2 text-right">Cant. requerida</th>
                            <th class="px-2 py-2 text-center">Listo</th>
                            <th class="px-2 py-2 text-right">Cant. disponible</th>
                            <th class="px-2 py-2 text-right">Cant. preparada</th>
                            <th class="px-2 py-2 text-right">Falta</th>
                            <th class="px-2 py-2">Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(fila, index) in filas" :key="fila.id"
                            class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-200/30 hover:dark:bg-gray-900/20">
                            <td class="px-2 py-2 text-center">{{ index + 1 }}</td>
                            <td class="px-2 py-2 whitespace-nowrap">
                                {{ [fila.material_codigo, fila.material_nombre].filter(Boolean).join(' — ') || '—' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap">{{ fila.estado_requerido ?? '—' }}</td>
                            <td class="px-2 py-2 whitespace-nowrap">{{ fila.unidad ?? '—' }}</td>
                            <td class="px-2 py-2 text-right">{{ formatear(fila.cantidad_requerida) }}</td>
                            <td class="px-2 py-2 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                    :class="fila.listo
                                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300'
                                        : 'bg-gray-100 text-gray-800 dark:bg-gray-700/50 dark:text-gray-300'">
                                    {{ fila.listo ? 'SI' : 'NO' }}
                                </span>
                            </td>
                            <td class="px-2 py-2 text-right">{{ formatear(fila.cantidad_disponible) }}</td>
                            <td class="px-2 py-2 text-right">{{ formatear(fila.cantidad_preparada) }}</td>
                            <td class="px-2 py-2 text-right">{{ formatear(fila.falta) }}</td>
                            <td class="px-2 py-2 max-w-[200px] truncate" :title="fila.observaciones">
                                {{ fila.observaciones || '—' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    {{ filas.length }} registro(s)
                </p>
            </div>

            <div class="mt-6 flex justify-end">
                <SecondaryButton type="button" @click="closeModal">
                    Cerrar
                </SecondaryButton>
            </div>
        </div>
    </Modal>
</template>
