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

const COLUMNAS_REQUERIDAS = [
    'Nombre',
    'Email',
    'Identificación',
    'Rol',
];

const COLUMNAS_OPCIONALES = [
    'Cargo',
    'Celular',
    'Sexo',
    'Fecha nacimiento',
    'Salario',
    'Fecha de ingreso',
];

const form = useForm({
    archivo: null as File | null,
});

const excelError = ref<string | null>(null);
const inputId = 'excel-input-usuario';

/** Valida el archivo en el cliente ANTES de enviarlo */
async function validarArchivoExcel(file: File): Promise<string | null> {
    const ext = file.name.split('.').pop()?.toLowerCase();
    if (ext !== 'xlsx') return 'Solo se permiten archivos .xlsx';

    const mimeValido = file.type === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    if (!mimeValido) return 'El tipo MIME del archivo no corresponde a un Excel (.xlsx)';

    if (file.size > 10 * 1024 * 1024) return 'El archivo supera el tamaño máximo de 10 MB';

    const buffer = await file.slice(0, 4).arrayBuffer();
    const bytes = new Uint8Array(buffer);
    const esPK = bytes[0] === 0x50 && bytes[1] === 0x4B && bytes[2] === 0x03 && bytes[3] === 0x04;
    if (!esPK) return 'El archivo no tiene una firma XLSX válida (magic bytes incorrectos)';

    return null;
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

    form.post(route('user.upload-excel'), {
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
            <div class="mb-4 flex items-center gap-2">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Importar Usuarios desde Excel
                </h2>
            </div>

            <div v-if="excelError"
                class="mb-4 flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-700 dark:bg-red-900/30 dark:text-red-300">
                <svg class="mt-0.5 h-4 w-4 shrink-0" fill="currentColor" viewBox="0 0 20 20">
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
                    class="block w-full cursor-pointer text-sm text-gray-500 transition-colors file:mr-3 file:rounded-lg file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-blue-700 hover:file:bg-blue-100 dark:text-gray-400" />

                <div v-if="form.progress" class="h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                    <div class="h-2 bg-blue-500 transition-all duration-300"
                        :style="{ width: form.progress.percentage + '%' }" />
                </div>

                <div class="flex justify-end gap-2">
                    <DangerButton type="button" :disabled="form.processing" @click="closeModal">
                        Cancelar
                    </DangerButton>
                    <PrimaryButton type="submit" :disabled="!form.archivo || form.processing"
                        class="bg-blue-600 hover:bg-blue-700 focus:ring-blue-500 disabled:cursor-not-allowed disabled:opacity-50">
                        <span v-if="form.processing">Subiendo…</span>
                        <span v-else>Subir</span>
                    </PrimaryButton>
                </div>
            </form>

            <details class="mt-4 text-sm" open>
                <summary
                    class="cursor-pointer select-none text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                    Ver columnas requeridas
                </summary>
                <ul class="ml-5 mt-2 list-decimal space-y-0.5 text-gray-600 dark:text-gray-300">
                    <li v-for="col in COLUMNAS_REQUERIDAS" :key="col">{{ col }}</li>
                </ul>
                <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Opcionales
                </p>
                <ul class="ml-5 mt-1 list-decimal space-y-0.5 text-gray-600 dark:text-gray-300">
                    <li v-for="col in COLUMNAS_OPCIONALES" :key="col">{{ col }}</li>
                </ul>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    Si el email o la identificación ya existe, se actualiza el registro; si no, se crea
                    con la contraseña identificación + "*". El rol debe existir previamente en la aplicación.
                    La importación se procesa en segundo plano y recibirás una notificación al finalizar.
                </p>
            </details>
        </div>
    </Modal>
</template>
