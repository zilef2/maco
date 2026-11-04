<script setup lang="ts">
import Modal from '@/components/Modal.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import DangerButton from '@/components/DangerButton.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    show: Boolean,
    /** Columnas del formato, tal como las define PlantillasExcelService. */
    columnas: {
        type: Array as () => Array<{ titulo: string; requerida: boolean; descripcion: string }>,
        default: () => [],
    },
});

const emit = defineEmits(['close']);

const requeridas = computed(() => props.columnas.filter((columna) => columna.requerida));
const opcionales = computed(() => props.columnas.filter((columna) => !columna.requerida));

const form = useForm({
    archivo: null as File | null,
});

const excelError = ref<string | null>(null);
const inputId = 'excel-input-material';

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

    form.post(route('Material.upload-excel'), {
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
                    Importar Materiales desde Excel
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
                    Ver columnas del archivo
                </summary>
                <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Requeridas
                </p>
                <ul class="mt-1 space-y-1 text-gray-600 dark:text-gray-300">
                    <li v-for="columna in requeridas" :key="columna.titulo" class="text-xs">
                        <span class="font-medium text-gray-800 dark:text-gray-200">{{ columna.titulo }}</span>
                        <span v-if="columna.descripcion" class="text-gray-500 dark:text-gray-400"> — {{ columna.descripcion }}</span>
                    </li>
                </ul>
                <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Opcionales
                </p>
                <ul class="mt-1 space-y-1 text-gray-600 dark:text-gray-300">
                    <li v-for="columna in opcionales" :key="columna.titulo" class="text-xs">
                        <span class="font-medium text-gray-800 dark:text-gray-200">{{ columna.titulo }}</span>
                        <span v-if="columna.descripcion" class="text-gray-500 dark:text-gray-400"> — {{ columna.descripcion }}</span>
                    </li>
                </ul>
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    Si el código de material ya existe, se actualiza el registro; si no, se crea con el siguiente número
                    libre del par. Las columnas "Maneja …" y "Procesado" admiten SI / NO en mayúsculas o minúsculas.
                    <b>Procesado</b> es obligatoria y debe estar en la columna inmediatamente siguiente a
                    <b>Costo</b>; si el archivo no trae ese orden no se importa nada.
                </p>
                <p class="mt-2 rounded-lg border border-violet-200 bg-violet-50 px-3 py-2 text-xs text-violet-800 dark:border-violet-500/30 dark:bg-violet-500/10 dark:text-violet-200">
                    <b>Código material</b> vale CAT-BASE-N (TEX-LFI-4) y se guarda sin ceros a la izquierda: un
                    TEX-LFL-005 del archivo pasa a TEX-LFL-5. <b>Unidad inventario</b> es el nombre de la unidad (Metros,
                    Unidades), no la cantidad en stock.
                </p>
                <p class="mt-2 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-xs text-sky-800 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-200">
                    <b>Color</b> y <b>Talla / tamaño</b> alimentan la tabla de variantes del material: sus valores van
                    separados por coma y el orden es la posición en la lista (0, 1, 2…). La lista reemplaza por completo
                    las variantes del material, así que si la dejas vacía se borran las que tuviera.
                </p>
                <p class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
                    Un material sí puede tener <b>Color</b> e <i>otras variantes</i> (tallas) a la vez: las dos columnas
                    se guardan como dos listas independientes de variantes.
                </p>
                <p class="mt-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-200">
                    <b>Proveedor habitual</b> tiene que existir en la app. La forma más segura de escribirlo es su
                    <b>NIT</b> tal como está registrado (900347285-7); también valen su nombre comercial, su razón
                    social o su código (PRV-8, PROV-8 o el id). La comparación ignora mayúsculas, tildes, espacios,
                    puntos y guiones, y acepta el nombre sin el artículo inicial («Casa de los cordones» sirve para
                    «LA CASA DE LOS CORDONES»). Si ningún proveedor coincide, no se importa nada y el aviso lista los
                    que faltaron.
                </p>
                <a
                    :href="route('Material.plantilla-excel')"
                    class="mt-3 inline-flex items-center gap-1.5 rounded-md border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300 dark:hover:bg-emerald-500/20"
                >
                    Descargar plantilla Excel
                </a>
            </details>
        </div>
    </Modal>
</template>
