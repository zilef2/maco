<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Breadcrumb from '@/components/Breadcrumb.vue';
import TarjetaEntidad from './TarjetaEntidad.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ArrowDownTrayIcon, ChevronDownIcon, ChevronUpIcon } from '@heroicons/vue/24/solid';
import { computed, reactive, ref } from 'vue';

type EntidadExcel = {
    clave: string;
    nombre: string;
    grupo: string;
    tiene_excel: boolean;
};

type MetricaExcel = {
    muestras: number;
    promedio_ms: number | null;
    ultima_en: string | null;
    ultimo_archivo: string | null;
};

const props = defineProps({
    breadcrumbs: Object,
    title: String,
    entidades: Array as () => Array<{
        id: string;
        nombre: string;
        grupo: string;
        descripcion: string;
        ruta: string;
        columnas_requeridas: string[];
        columnas_opcionales: string[];
        nota: string;
        plantilla: string;
        metrica: MetricaExcel | null;
    }>,
    entidadesExcel: Array as () => EntidadExcel[],
});

const metricasPorClave = computed<Record<string, MetricaExcel | null | undefined>>(() =>
    Object.fromEntries((props.entidades ?? []).map((entidad) => [entidad.id, entidad.metrica])),
);

/** "1,24 s", "820 ms" o "Sin datos" para el promedio de una entidad. */
function formatearTiempo(ms?: number | null): string {
    if (ms === null || ms === undefined) return 'Sin datos';
    if (ms < 1000) return `${ms} ms`;

    const segundos = ms / 1000;
    return `${segundos.toFixed(segundos < 10 ? 2 : 1)} s`;
}

const guardandoEntidad = reactive<Record<string, boolean>>({});

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

const authCan = ((usePage().props as any)?.auth?.can ?? {}) as Record<string, boolean>;
const esSuper = computed(() => authCan['issuperadmin'] === true);

/**
 * Tarjetas que solo puede ver el super (permiso `issuperadmin`).
 * "Estados de Operación" y "Usuarios" quedan fuera del grid principal y
 * se muestran al final de la vista, dentro de la zona plegable
 * «Exceles opcionales» que hay que abrir con un clic.
 */
const CLAVES_OPCIONALES_SUPER = ['estado_operacion', 'usuario'];

/** Entidades del grid principal (todas menos las opcionales de super). */
const entidadesPrincipales = computed(() =>
    (props.entidades ?? []).filter((entidad) => !CLAVES_OPCIONALES_SUPER.includes(entidad.id)),
);

/** Entidades de «Exceles opcionales»: Estados de Operación y Usuarios. */
const entidadesOpcionalesSuper = computed(() =>
    (props.entidades ?? []).filter((entidad) => CLAVES_OPCIONALES_SUPER.includes(entidad.id)),
);

/** Switches de cobertura visibles: todos para super, sin las dos opcionales para el resto. */
const entidadesExcelVisibles = computed(() => {
    const todas = props.entidadesExcel ?? [];
    if (esSuper.value) return todas;
    return todas.filter((entidad) => !CLAVES_OPCIONALES_SUPER.includes(entidad.clave));
});

/** Contador "con Excel" consistente con lo visible. */
const totalConExcel = computed(
    () => entidadesExcelVisibles.value.filter((entidad) => entidad.tiene_excel).length,
);

const opcionalesAbierto = ref(false);

function alternarOpcionales() {
    opcionalesAbierto.value = !opcionalesAbierto.value;
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
                        {{ totalConExcel }} de {{ entidadesExcelVisibles.length }} con Excel
                    </span>
                </div>

                <div class="grid grid-cols-1 gap-2 p-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                    <button
                        v-for="entidad in entidadesExcelVisibles"
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
                            <span
                                v-if="metricasPorClave[entidad.clave]?.promedio_ms != null"
                                class="block font-mono text-[9px] text-emerald-700"
                            >
                                Prom. {{ formatearTiempo(metricasPorClave[entidad.clave]?.promedio_ms) }}
                                · {{ metricasPorClave[entidad.clave]?.muestras }}
                                {{ metricasPorClave[entidad.clave]?.muestras === 1 ? 'carga' : 'cargas' }}
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

            <!-- Tarjetas de entidades (sin las opcionales de super) -->
            <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
                <TarjetaEntidad
                    v-for="entidad in entidadesPrincipales"
                    :key="entidad.id"
                    :entidad="entidad"
                />
            </div>

            <!-- Exceles opcionales: solo visibles para el super, al final de la vista y tras hacer clic -->
            <div
                v-if="esSuper"
                class="rounded-xl border border-dashed border-gray-300 bg-white/60 shadow-sm"
            >
                <button
                    type="button"
                    class="flex w-full items-center justify-between gap-3 p-4 text-left transition hover:bg-gray-50"
                    :aria-expanded="opcionalesAbierto"
                    aria-controls="exceles-opcionales-panel"
                    @click="alternarOpcionales"
                >
                    <span class="flex items-center gap-3">
                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-gray-950 text-white">
                            <ArrowDownTrayIcon class="h-5 w-5" />
                        </span>
                        <span>
                            <span class="block text-sm font-semibold text-gray-900">Exceles opcionales</span>
                            <span class="block text-xs text-gray-500">
                                Cargas avanzadas: Estados de Operación y Usuarios. Haz clic para
                                {{ opcionalesAbierto ? 'ocultarlas' : 'mostrarlas' }}.
                            </span>
                        </span>
                    </span>
                    <span class="flex items-center gap-2">
                        <span class="rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-widest text-amber-700">
                            Solo super
                        </span>
                        <component
                            :is="opcionalesAbierto ? ChevronUpIcon : ChevronDownIcon"
                            class="h-5 w-5 text-gray-400 transition"
                        />
                    </span>
                </button>

                <div id="exceles-opcionales-panel" class="border-t border-gray-200">
                    <div v-if="opcionalesAbierto" class="grid grid-cols-1 gap-4 p-4 xl:grid-cols-3">
                        <TarjetaEntidad
                            v-for="entidad in entidadesOpcionalesSuper"
                            :key="entidad.id"
                            :entidad="entidad"
                        />
                    </div>
                    <p
                        v-else
                        class="px-4 py-3 text-center font-mono text-[10px] uppercase tracking-[0.2em] text-gray-400"
                    >
                        Clic arriba para desplegar las cargas opcionales
                    </p>
                </div>
            </div>

            <!-- Frase de cierre operativo -->
            <p class="text-center font-mono text-[10px] uppercase tracking-[0.2em] text-gray-400">
                Los resultados de cada importación se muestran como notificación y quedan registrados en el log del sistema
            </p>
        </div>
    </AppLayout>
</template>
