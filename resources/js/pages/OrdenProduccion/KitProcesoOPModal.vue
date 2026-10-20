<script setup lang="ts">
import Modal from '@/components/Modal.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import { useForm } from '@inertiajs/vue3';
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

// ─── Carga de Excel (integrada al pie del modal) ──────────────
const COLUMNAS_REQUERIDAS = [
    'Material / componente',
    'Estado requerido',
    'Unidad',
    'Cantidad requerida',
    'Cantidad disponible',
    'Cantidad preparada',
    'Falta',
    '¿Listo? (SI/NO)',
    'Observaciones',
];

const form = useForm({
    archivo: null as File | null,
    orden_produccion_id: null as number | null,
});

const excelError = ref<string | null>(null);
const inputId = 'excel-input-op';

/** Valida el archivo en el cliente ANTES de enviarlo */
async function validarArchivoExcel(file: File): Promise<string | null> {
    // 1. Extensión
    const ext = file.name.split('.').pop()?.toLowerCase();
    if (ext !== 'xlsx') return 'Solo se permiten archivos .xlsx';

    // 2. MIME type declarado por el navegador
    const mimeValido = file.type === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    if (!mimeValido) return 'El tipo MIME del archivo no corresponde a un Excel (.xlsx)';

    // 3. Tamaño máximo (10 MB)
    if (file.size > 10 * 1024 * 1024) return 'El archivo supera el tamaño máximo de 10 MB';

    // 4. Magic bytes: los primeros 4 bytes de un ZIP/OOXML son PK\x03\x04
    const buffer = await file.slice(0, 4).arrayBuffer();
    const bytes = new Uint8Array(buffer);
    const esPK = bytes[0] === 0x50 && bytes[1] === 0x4B && bytes[2] === 0x03 && bytes[3] === 0x04;
    if (!esPK) return 'El archivo no tiene una firma XLSX válida (magic bytes incorrectos)';

    return null; // sin errores
}

function limpiarInput() {
    form.archivo = null;
    const input = document.getElementById(inputId) as HTMLInputElement | null;
    if (input) input.value = '';
}

function onFileChange(e: Event) {
    const input = e.target as HTMLInputElement;
    excelError.value = null;
    form.archivo = input.files?.[0] ?? null;
}

async function subirExcel() {
    excelError.value = null;

    if (!form.archivo) {
        excelError.value = 'Selecciona un archivo antes de continuar';
        return;
    }

    const error = await validarArchivoExcel(form.archivo);
    if (error) {
        excelError.value = error;
        limpiarInput();
        return;
    }

    form.orden_produccion_id = props.ordenProduccion?.id ?? null;

    form.post(route('KitTaller.upload-excel'), {
        preserveScroll: true,
        onSuccess: () => {
            limpiarInput();
            cargar(); // refresca la tabla con lo recién importado
        },
        onError: (errors) => {
            excelError.value = Object.values(errors).join(' | ');
        },
    });
}

const closeModal = () => {
    emit('close');
    excelError.value = null;
    limpiarInput();
};

watch(() => props.show, (visible) => {
    if (visible) {
        cargar();
        excelError.value = null;
        limpiarInput();
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

            <!-- ── Tabla del kit ─────────────────────────────── -->
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

            <!-- ── Carga de Excel (parte inferior) ───────────── -->
            <div v-if="can(['create KitTaller'])"
                class="mt-6 border-t border-gray-200 dark:border-gray-700 pt-5">
                <h3 class="font-semibold text-gray-800 dark:text-gray-100 mb-3">
                    Importar KitTaller desde Excel
                </h3>

                <div v-if="excelError"
                    class="mb-3 flex items-start gap-2 rounded-lg bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700 px-4 py-3 text-sm text-red-700 dark:text-red-300">
                    <svg class="mt-0.5 w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                            clip-rule="evenodd" />
                    </svg>
                    <span>{{ excelError }}</span>
                </div>

                <form @submit.prevent="subirExcel" class="flex flex-col gap-3">
                    <input :id="inputId" type="file"
                        accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                        @change="onFileChange"
                        class="block w-full text-sm text-gray-500 dark:text-gray-400
                               file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0
                               file:text-sm file:font-semibold
                               file:bg-emerald-50 file:text-emerald-700
                               hover:file:bg-emerald-100 cursor-pointer transition-colors" />

                    <!-- Barra de progreso -->
                    <div v-if="form.progress"
                        class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-2 overflow-hidden">
                        <div class="h-2 bg-emerald-500 transition-all duration-300"
                            :style="{ width: form.progress.percentage + '%' }" />
                    </div>

                    <div class="flex justify-end">
                        <PrimaryButton type="submit" :disabled="!form.archivo || form.processing"
                            class="bg-emerald-600 hover:bg-emerald-700 focus:ring-emerald-500 disabled:opacity-50 disabled:cursor-not-allowed">
                            <span v-if="form.processing">Subiendo…</span>
                            <span v-else>Subir</span>
                        </PrimaryButton>
                    </div>
                </form>

                <details class="mt-3 text-sm">
                    <summary
                        class="cursor-pointer text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 select-none">
                        Ver columnas requeridas
                    </summary>
                    <ul class="mt-2 list-decimal ml-5 space-y-0.5 text-gray-600 dark:text-gray-300">
                        <li v-for="col in COLUMNAS_REQUERIDAS" :key="col">{{ col }}</li>
                    </ul>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        El material se identifica por su código (codigo_material / código). La columna ¿Listo? admite
                        SI / NO en mayúsculas o minúsculas.
                    </p>
                </details>
            </div>

            <div class="mt-6 flex justify-end">
                <SecondaryButton type="button" @click="closeModal">
                    Cerrar
                </SecondaryButton>
            </div>
        </div>
    </Modal>
</template>
