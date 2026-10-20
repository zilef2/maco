<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Breadcrumb from '@/components/Breadcrumb.vue';
import { Head, router } from '@inertiajs/vue3';
import { ArchiveBoxIcon, ArrowPathIcon, ArrowUpTrayIcon, BuildingStorefrontIcon, SquaresPlusIcon, TruckIcon } from '@heroicons/vue/24/solid';
import { computed, reactive } from 'vue';

type EntidadExcel = {
    clave: string;
    nombre: string;
    grupo: string;
    tiene_excel: boolean;
};

const props = defineProps({
    breadcrumbs: Object,
    title: String,
    entidades: Array as () => Array<{
        id: string;
        nombre: string;
        descripcion: string;
        ruta: string;
        columnas_requeridas: string[];
        columnas_opcionales: string[];
        nota: string;
    }>,
    entidadesExcel: Array as () => EntidadExcel[],
});

const guardandoEntidad = reactive<Record<string, boolean>>({});

const totalConExcel = computed(
    () => (props.entidadesExcel ?? []).filter((entidad) => entidad.tiene_excel).length,
);

/** Indica si la entidad necesita Excel (marcada) o no (desmarcada). */
function toggleEntidad(entidad: EntidadExcel) {
    if (guardandoEntidad[entidad.clave]) return;

    guardandoEntidad[entidad.clave] = true;
    router.post(
        route('ImportarExcel.toggleEntidad'),
        { clave: entidad.clave },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                guardandoEntidad[entidad.clave] = false;
            },
        },
    );
}

const ICONOS: Record<string, any> = {
    producto: SquaresPlusIcon,
    material: ArchiveBoxIcon,
    estado_operacion: ArrowPathIcon,
    taller: BuildingStorefrontIcon,
    proveedor: TruckIcon,
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

async function onFileChange(entidad: any, e: Event) {
    const input = e.target as HTMLInputElement;
    const st = estadoDe(entidad.id);
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

function limpiarInput(id: string) {
    const input = document.getElementById(inputId(id)) as HTMLInputElement | null;
    if (input) input.value = '';
}

function subir(entidad: any) {
    const st = estadoDe(entidad.id);
    st.error = null;

    if (!st.archivo) {
        st.error = 'Selecciona un archivo .xlsx antes de continuar';
        return;
    }

    const datos = new FormData();
    datos.append('archivo', st.archivo);

    st.procesando = true;
    st.progreso = 0;

    router.post(entidad.ruta, datos, {
        preserveScroll: true,
        onProgress: (p: any) => {
            st.progreso = p?.percentage ?? null;
        },
        onSuccess: () => {
            st.archivo = null;
            st.progreso = null;
            limpiarInput(entidad.id);
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
    <AppLayout>
        <!-- <Head :title="title" /> -->
        <!-- <Breadcrumb :title="title" :breadcrumbs="breadcrumbs" class="capitalize text-xl font-bold" /> -->

        <!-- Workspace: lienzo claro cálido, contenido operativo denso -->
        <div class="mt-2 space-y-5 rounded-xl border border-gray-200 bg-[#f4f1ea] p-4 sm:p-6">

            <!-- Shell oscuro frosted: cabecera de mando -->
            <div class="flex flex-col gap-4 rounded-xl border border-white/10 bg-gray-950/70 p-5 text-white shadow-xl backdrop-blur-xl sm:p-6 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-[0.28em] text-white/50">
                        Macondas / Carga excel
                    </p>
                    <h2 class="mt-1 text-2xl font-bold tracking-tight">Centro de Importación</h2>
                    <p class="mt-1 max-w-2xl text-sm text-white/60">
                        Panel único para las cargas masivas por Excel. Cada módulo valida el archivo
                        antes de procesarlo y reporta el resultado en pantalla.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <span class="rounded-md border border-white/15 px-2.5 py-1.5 font-mono text-[16px] uppercase tracking-widest text-white/80">
                        {{ props.entidades?.length ?? 0 }} módulos activos
                    </span>
                    <span class="rounded-md border border-white/15 px-2.5 py-1.5 font-mono text-[16px] uppercase tracking-widest text-white/80">
                        .xlsx
                    </span>
                    <span class="rounded-md border border-white/15 px-2.5 py-1.5 font-mono text-[16px] uppercase tracking-widest text-white/80">
                        máx 10 MB
                    </span>
                </div>
            </div>

            <!-- Zona informativa: cobertura Excel por entidad -->
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-gray-100 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-gray-400">
                            Cobertura de importación
                        </p>
                        <h3 class="mt-0.5 text-sm font-semibold text-gray-900">
                            Entidades que necesitan Excel
                        </h3>
                        <p class="mt-0.5 text-xs text-gray-500">
                            Haz clic en una entidad para marcarla o desmarcarla. Desmarcada significa que
                            no necesita carga por Excel. El estado queda guardado en el parámetro
                            <code class="rounded bg-gray-100 px-1 py-0.5 font-mono text-[10px] text-gray-600">importar_excel_entidades</code>.
                        </p>
                    </div>
                    <span class="inline-flex shrink-0 items-center gap-2 self-start rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-[10px] font-semibold uppercase tracking-widest text-emerald-700">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                        {{ totalConExcel }} de {{ props.entidadesExcel?.length ?? 0 }} con Excel
                    </span>
                </div>

                <div class="grid grid-cols-1 gap-2 p-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                    <button
                        v-for="entidad in props.entidadesExcel"
                        :key="entidad.clave"
                        type="button"
                        role="switch"
                        :aria-checked="entidad.tiene_excel"
                        :aria-label="`${entidad.nombre}: ${entidad.tiene_excel ? 'necesita Excel' : 'no necesita Excel'}`"
                        :disabled="guardandoEntidad[entidad.clave]"
                        class="group flex items-center justify-between gap-2 rounded-lg border px-3 py-2 text-left transition disabled:cursor-wait disabled:opacity-60"
                        :class="
                            entidad.tiene_excel
                                ? 'border-emerald-200 bg-emerald-50/70 hover:border-emerald-300'
                                : 'border-gray-200 bg-gray-50/70 hover:border-gray-300'
                        "
                        @click="toggleEntidad(entidad)"
                    >
                        <span class="min-w-0">
                            <span class="block truncate text-xs font-semibold text-gray-800">
                                {{ entidad.nombre }}
                            </span>
                            <span class="block truncate text-[9px] uppercase tracking-[0.16em] text-gray-400">
                                {{ entidad.grupo }}
                            </span>
                        </span>
                        <span class="flex shrink-0 items-center gap-2">
                            <span
                                class="text-[9px] font-semibold uppercase tracking-wider"
                                :class="entidad.tiene_excel ? 'text-emerald-700' : 'text-gray-400'"
                            >
                                {{ entidad.tiene_excel ? 'Con Excel' : 'Sin Excel' }}
                            </span>
                            <span
                                class="relative inline-flex h-5 w-9 items-center rounded-full transition"
                                :class="entidad.tiene_excel ? 'bg-emerald-500' : 'bg-gray-300'"
                            >
                                <span
                                    class="inline-block h-3.5 w-3.5 rounded-full bg-white shadow transition"
                                    :class="entidad.tiene_excel ? 'translate-x-[18px]' : 'translate-x-[2px]'"
                                />
                            </span>
                        </span>
                    </button>
                </div>
            </div>

            <!-- Tarjetas de entidades -->
            <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
                <div
                    v-for="entidad in props.entidades"
                    :key="entidad.id"
                    class="flex flex-col rounded-xl border border-gray-200 bg-white shadow-sm"
                >
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

                        <div class="flex gap-2">
                            <input
                                :id="inputId(entidad.id)"
                                type="file"
                                accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                                class="hidden"
                                @change="onFileChange(entidad, $event)"
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
                                @click="subir(entidad)"
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
            </div>

            <!-- Frase de cierre operativo -->
            <p class="text-center font-mono text-[10px] uppercase tracking-[0.2em] text-gray-400">
                Los resultados de cada importación se muestran como notificación y quedan registrados en el log del sistema
            </p>
        </div>
    </AppLayout>
</template>
