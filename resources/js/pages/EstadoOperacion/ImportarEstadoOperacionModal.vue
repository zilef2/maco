<script setup lang="ts">
import Modal from '@/components/Modal.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import DangerButton from '@/components/DangerButton.vue';
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    show: Boolean,
});

const emit = defineEmits(['close']);

const COLUMNAS_REQUERIDAS = ['Nombre'];

const COLUMNAS_OPCIONALES = ['Cuando aplica', 'Regla clave', 'Puede pasar a'];

const form = useForm({
    archivo: null as File | null,
});

const excelError = ref<string | null>(null);
const inputId = 'excel-input-estado-operacion';

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

function closeModal() {
    emit('close');
    excelError.value = null;
    limpiarInput();
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

    form.post(route('EstadoOperacion.upload-excel'), {
        preserveScroll: true,
        onSuccess: () => {
            closeModal();
        },
        onError: (errors) => {
            excelError.value = Object.values(errors).join(' | ');
        },
    });
}

watch(() => props.show, (visible) => {
    if (visible) {
        excelError.value = null;
        limpiarInput();
    }
});
</script>

<template>
    <Modal :show="props.show" @close="closeModal" :maxWidth="'lg'">
        <div class="p-6">
            <div class="flex items-center gap-2 mb-4">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Importar Estados de operación desde Excel
                </h2>
            </div>

            <!-- Error de validación frontend o backend -->
            <div v-if="excelError"
                class="mb-4 flex items-start gap-2 rounded-lg bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700 px-4 py-3 text-sm text-red-700 dark:text-red-300">
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
                <div v-if="form.progress" class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-2 overflow-hidden">
                    <div class="h-2 bg-emerald-500 transition-all duration-300"
                        :style="{ width: form.progress.percentage + '%' }" />
                </div>

                <div class="flex gap-2 justify-end">
                    <DangerButton type="button" :disabled="form.processing" @click="closeModal">
                        Cancelar
                    </DangerButton>
                    <PrimaryButton type="submit" :disabled="!form.archivo || form.processing"
                        class="bg-emerald-600 hover:bg-emerald-700 focus:ring-emerald-500 disabled:opacity-50 disabled:cursor-not-allowed">
                        <span v-if="form.processing">Subiendo…</span>
                        <span v-else>Subir</span>
                    </PrimaryButton>
                </div>
            </form>

            <!-- Columnas -->
            <details class="mt-4 text-sm" open>
                <summary
                    class="cursor-pointer text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 select-none">
                    Ver columnas requeridas
                </summary>
                <ul class="mt-2 list-decimal ml-5 space-y-0.5 text-gray-600 dark:text-gray-300">
                    <li v-for="col in COLUMNAS_REQUERIDAS" :key="col">{{ col }}</li>
                </ul>
                <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Opcionales
                </p>
                <ul class="mt-1 list-decimal ml-5 space-y-0.5 text-gray-600 dark:text-gray-300">
                    <li v-for="col in COLUMNAS_OPCIONALES" :key="col">{{ col }}</li>
                </ul>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    Si el nombre ya existe, se actualiza el registro; si no, se crea.
                </p>
            </details>
        </div>
    </Modal>
</template>
