<script setup lang="ts">
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    ArchiveBoxIcon,
    ArrowDownTrayIcon,
    ArrowPathIcon,
    ArrowUpTrayIcon,
    BuildingStorefrontIcon,
    SquaresPlusIcon,
    TruckIcon,
    UsersIcon,
} from '@heroicons/vue/24/solid';

const props = defineProps<{
    entidad: {
        id: string;
        nombre: string;
        grupo: string;
        descripcion: string;
        ruta: string;
        columnas_requeridas: string[];
        columnas_opcionales: string[];
        nota: string;
        plantilla: string;
        metrica: {
            muestras?: number;
            promedio_ms?: number | null;
            ultima_en?: string | null;
            ultimo_archivo?: string | null;
        } | null;
    };
}>();

const ICONOS: Record<string, any> = {
    producto: SquaresPlusIcon,
    material: ArchiveBoxIcon,
    estado_operacion: ArrowPathIcon,
    taller: BuildingStorefrontIcon,
    proveedor: TruckIcon,
    usuario: UsersIcon,
};

type EstadoTarjeta = {
    archivo: File | null;
    error: string | null;
    procesando: boolean;
    progreso: number | null;
};

const estado = reactive<Record<string, EstadoTarjeta>>({});

const estadoDe = (id: string): EstadoTarjeta => {
    if (!estado[id]) {
        estado[id] = { archivo: null, error: null, procesando: false, progreso: null };
    }
    return estado[id];
};

const inputId = (id: string) => `excel-input-${id}`;

/** "1,24 s", "820 ms" o "Sin datos" para el promedio de una entidad. */
function formatearTiempo(ms?: number | null): string {
    if (ms === null || ms === undefined) return 'Sin datos';
    if (ms < 1000) return `${ms} ms`;

    const segundos = ms / 1000;
    return `${segundos.toFixed(segundos < 10 ? 2 : 1)} s`;
}

function textoMuestras(muestra?: number): string {
    if (!muestra) return 'Aún sin cargas completadas';
    return `Promedio de las últimas ${muestra} ${muestra === 1 ? 'carga' : 'cargas'} completadas`;
}

function formatearFecha(iso?: string | null): string {
    if (!iso) return 'Sin registros';
    const fecha = new Date(iso);
    if (Number.isNaN(fecha.getTime())) return 'Sin registros';

    return new Intl.DateTimeFormat('es-CO', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(fecha);
}

/** Validación en cliente igual que los modales: extensión, MIME, tamaño y firma PK */
async function validarArchivoExcel(file: File): Promise<string | null> {
    const ext = file.name.split('.').pop()?.toLowerCase();
    if (ext !== 'xlsx') return 'Solo se permiten archivos .xlsx';

    if (file.type !== 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') {
        return 'El tipo MIME del archivo no corresponde a un Excel (.xlsx)';
    }

    if (file.size > 10 * 1024 * 1024) return 'El archivo supera el tamaño máximo de 10 MB';

    const buffer = await file.slice(0, 4).arrayBuffer();
    const bytes = new Uint8Array(buffer);
    const esPK = bytes[0] === 0x50 && bytes[1] === 0x4B && bytes[2] === 0x03 && bytes[3] === 0x04;
    if (!esPK) return 'El archivo no tiene una firma XLSX válida (magic bytes incorrectos)';

    return null;
}

async function onFileChange(e: Event) {
    const input = e.target as HTMLInputElement;
    const st = estadoDe(props.entidad.id);
    st.error = null;
    st.archivo = null;

    const file = input.files?.[0] ?? null;
    if (!file) return;

    const error = await validarArchivoExcel(file);
    if (error) {
        st.error = error;
        input.value = '';
        return;
    }
    st.archivo = file;
}

function limpiarInput() {
    const input = document.getElementById(inputId(props.entidad.id)) as HTMLInputElement | null;
    if (input) input.value = '';
}

function subir() {
    const st = estadoDe(props.entidad.id);
    st.error = null;

    if (!st.archivo) {
        st.error = 'Selecciona un archivo .xlsx antes de continuar';
        return;
    }

    const datos = new FormData();
    datos.append('archivo', st.archivo);

    st.procesando = true;
    st.progreso = 0;

    router.post(props.entidad.ruta, datos, {
        preserveScroll: true,
        onProgress: (p: any) => {
            st.progreso = p?.percentage ?? null;
        },
        onSuccess: () => {
            st.archivo = null;
            st.progreso = null;
            limpiarInput();
        },
        onError: (errors: Record<string, string>) => {
            st.error = Object.values(errors).join(' | ') || 'Error al subir el archivo';
            st.progreso = null;
        },
        onFinish: () => {
            st.procesando = false;
        },
    });
}
</script>

<template>
    <div class="flex flex-col rounded-xl border border-gray-200 bg-white shadow-sm">
        <!-- Cabecera de tarjeta -->
        <div class="flex items-start justify-between gap-3 p-4 border-b border-gray-100">
            <div class="flex items-center gap-3">
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-gray-950 text-white">
                    <component :is="ICONOS[entidad.id]" class="h-5 w-5" />
                </span>
                <div>
                    <h3 class="font-semibold text-gray-900">{{ entidad.nombre }}</h3>
                    <p class="text-xs text-gray-500">{{ entidad.descripcion }}</p>
                </div>
            </div>
            <span class="inline-flex items-center gap-1.5 font-mono text-[10px] uppercase tracking-widest text-emerald-700">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                Operativo
            </span>
        </div>

        <!-- Tiempo promedio de las últimas cargas -->
        <div
            class="grid grid-cols-2 divide-x divide-gray-100 border-b border-gray-100 bg-gray-50/40"
            title="Tiempo total desde que se sube el archivo hasta que termina el procesamiento. En cargas en segundo plano incluye la espera de la cola."
        >
            <div class="p-3">
                <p class="text-[9px] font-semibold uppercase tracking-[0.18em] text-gray-400">
                    Tiempo promedio
                </p>
                <p class="mt-0.5 font-mono text-sm font-semibold text-gray-900">
                    {{ formatearTiempo(entidad.metrica?.promedio_ms) }}
                </p>
                <p class="mt-0.5 text-[10px] text-gray-500">
                    {{ textoMuestras(entidad.metrica?.muestras) }}
                </p>
            </div>
            <div class="p-3">
                <p class="text-[9px] font-semibold uppercase tracking-[0.18em] text-gray-400">
                    Última carga
                </p>
                <p class="mt-0.5 text-xs font-medium text-gray-800">
                    {{ formatearFecha(entidad.metrica?.ultima_en) }}
                </p>
                <p
                    v-if="entidad.metrica?.ultimo_archivo"
                    class="mt-0.5 truncate font-mono text-[10px] text-gray-500"
                    :title="entidad.metrica?.ultimo_archivo ?? ''"
                >
                    {{ entidad.metrica?.ultimo_archivo }}
                </p>
            </div>
        </div>

        <!-- Columnas y nota -->
        <div class="flex-1 space-y-3 p-4 text-xs">
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-gray-400">
                    Columnas requeridas
                </p>
                <div class="mt-1.5 flex flex-wrap gap-1">
                    <code
                        v-for="col in entidad.columnas_requeridas"
                        :key="col"
                        class="rounded border border-red-100 bg-red-50 px-1.5 py-0.5 font-mono text-[10px] text-red-700"
                    >{{ col }}</code>
                </div>
            </div>
            <div v-if="entidad.columnas_opcionales?.length">
                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-gray-400">
                    Opcionales
                </p>
                <div class="mt-1.5 flex flex-wrap gap-1">
                    <code
                        v-for="col in entidad.columnas_opcionales"
                        :key="col"
                        class="rounded border border-gray-200 bg-gray-50 px-1.5 py-0.5 font-mono text-[10px] text-gray-600"
                    >{{ col }}</code>
                </div>
            </div>
            <p class="text-[11px] leading-relaxed text-gray-500">{{ entidad.nota }}</p>
        </div>

        <!-- Consola de carga -->
        <div class="rounded-b-xl border-t border-gray-100 bg-gray-50/60 p-4">
            <div
                v-if="estadoDe(entidad.id).error"
                class="mb-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-[11px] text-red-700"
            >
                {{ estadoDe(entidad.id).error }}
            </div>

            <div class="mb-3 flex items-center justify-between gap-2">
                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-gray-400">
                    Formato de carga
                </p>
                <a
                    :href="entidad.plantilla"
                    class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-gray-700 transition hover:border-gray-400 hover:bg-gray-50"
                >
                    <ArrowDownTrayIcon class="h-3.5 w-3.5" />
                    Descargar formato .xlsx
                </a>
            </div>

            <div class="flex gap-2">
                <input
                    :id="inputId(entidad.id)"
                    type="file"
                    accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                    class="hidden"
                    @change="onFileChange($event)"
                />
                <label
                    :for="inputId(entidad.id)"
                    class="flex min-w-0 flex-1 cursor-pointer items-center gap-2 rounded-md border border-gray-300 bg-white px-3 py-2 text-xs text-gray-600 transition hover:border-gray-400"
                >
                    <ArrowUpTrayIcon class="h-4 w-4 shrink-0 text-gray-400" />
                    <span class="truncate font-mono">
                        {{ estadoDe(entidad.id).archivo?.name ?? 'Elegir archivo .xlsx' }}
                    </span>
                </label>
                <button
                    type="button"
                    :disabled="!estadoDe(entidad.id).archivo || estadoDe(entidad.id).procesando"
                    class="shrink-0 rounded-md bg-gray-950 px-4 py-2 text-xs font-semibold uppercase tracking-wider text-white transition hover:bg-gray-800 disabled:cursor-not-allowed disabled:opacity-40"
                    @click="subir()"
                >
                    {{ estadoDe(entidad.id).procesando ? 'Subiendo…' : 'Subir' }}
                </button>
            </div>

            <div
                v-if="estadoDe(entidad.id).progreso !== null"
                class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-gray-200"
            >
                <div
                    class="h-1.5 rounded-full bg-emerald-500 transition-all duration-300"
                    :style="{ width: (estadoDe(entidad.id).progreso ?? 0) + '%' }"
                />
            </div>
        </div>
    </div>
</template>
