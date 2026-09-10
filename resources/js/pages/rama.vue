<script setup lang="ts">
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AppLayout from '@/layouts/AppLayout.vue';

import DataExplicada from '@/pages/DataExplicada.vue';
import DetalleActuacion from '@/pages/DetalleActuacion.vue';
import DetalleProceso from '@/pages/DetalleProceso.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import pkg from 'lodash';
import { PropType, onBeforeUnmount, onMounted, reactive, watch } from 'vue';
import { Actuacion, ProcesoDataType, ProcesoDetalle } from './commoninterfaces.ts';

const { debounce, pickBy } = pkg;

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

const props = defineProps({
    title: String,
    Numprocesos: Number,
    UltimaActuacion: String,
    filters: Object,
    proceso: {
        type: Object as PropType<ProcesoDetalle | null>, // Indicas que es un Objeto o null
        default: null, // El valor por defecto es null, no un array vacío
    },
    obtenerDetalleProceso: {
        type: Object as PropType<ProcesoDataType | null>, // Indicas que es un Objeto o null
        default: null, // El valor por defecto es null, no un array vacío
    },
    Actuaciones: {
        type: Object as PropType<Actuacion[] | null>, // Indicas que es un Objeto o null
        default: null, // El valor por defecto es null, no un array vacío
    },
});

const data = reactive({
    params: {
        search: props.filters?.search,
    },
});

// <!--<editor-fold desc="mounted handleclipboard">-->
onMounted(() => {
    handleClipboardRead(); // Al montar
    window.addEventListener('focus', onFocusOrVisible);
    document.addEventListener('visibilitychange', onFocusOrVisible);
});

onBeforeUnmount(() => {
    window.removeEventListener('focus', onFocusOrVisible);
    document.removeEventListener('visibilitychange', onFocusOrVisible);
});

const handleClipboardRead = async () => {
    if (document.visibilityState !== 'visible') return;

    try {
        const clip = await navigator.clipboard.readText();
        if (clip === null || clip === undefined) {
            console.warn('🚫 Portapapeles vacío o no accesible');
            return;
        }
        const isnumericou = Number.isInteger(parseInt(clip));
        if (clip && clip.length > 0 && isnumericou) {
            data.params.search = clip;
        }
    } catch (e) {
        console.warn('🚫 Error leyendo portapapeles:', e);
        // alert('🚫 Error leyendo portapapeles:', e);
        // Limpiar solo si el portapapeles falla y quieres dejarlo vacío
        // data.params.search = '';
    }
};

const onFocusOrVisible = () => handleClipboardRead();

// <!--</editor-fold>-->

watch(
    () => data.params.search,
    debounce((newSearch: any, oldSearch: any) => {
        if (newSearch === oldSearch) {
            // No ha cambiado, no hacer nada
            return;
        }
        if (!newSearch || newSearch === '') return;

        const numbernewSearch = parseInt(newSearch);

        if (isNaN(numbernewSearch) || numbernewSearch < 0) return;
        if (!esRadicacionValida(newSearch)) return;

        const params = pickBy(data.params);
        router.get(route('rama'), params, {
            replace: true,
            preserveState: true,
            preserveScroll: true,
        });
    }, 475),
);
function esRadicacionValida(value: any): boolean {
    // Debe tener exactamente 23 caracteres y todos ser dígitos
    return typeof value === 'string' && value.length === 23 && /^[0-9]{23}$/.test(value);
}
const procesosVigilados = ['05266310500120220004300', '05360310500220240023300', '05360310500220240014100'];
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="grid auto-rows-min gap-4 md:grid-cols-2">
                <div class="border-sidebar-border/70 dark:border-sidebar-border relative mx-2 mt-10 w-full overflow-auto rounded-xl border">
                    <input
                        type="text"
                        v-model="data.params.search"
                        class="w-full rounded border px-1 py-2 focus:ring focus:outline-none"
                        placeholder="Número de radicación"
                    />
                </div>
                <div class="border-sidebar-border/70 dark:border-sidebar-border relative mx-2 mt-10 w-full overflow-auto rounded-xl border">
                    <Link :href="route('ProcesosConsultados')">
                        <PrimaryButton class="m-1"> Ir a Procesos Consultados </PrimaryButton>
                    </Link>
                </div>
            </div>
            <div class="border-sidebar-border/70 dark:border-sidebar-border relative min-h-[100vh] flex-1 rounded-xl border md:min-h-min">
                <div
                    v-if="props.proceso?.validacioncini"
                    class="xs:max-w-[500px] mx-auto w-full rounded-2xl bg-white p-8 hover:shadow-lg lg:max-w-[700px] 2xl:max-w-[900px] dark:bg-black"
                >
                    <h1 class="relative mb-8 overflow-hidden text-center text-4xl font-extrabold text-blue-600 dark:text-blue-400">
                        Detalles del Proceso Judicial
                        <span
                            class="via-opacity-30 animate-shine pointer-events-none absolute top-0 left-0 h-full w-full bg-gradient-to-r from-transparent via-blue-100 to-transparent"
                        ></span>
                    </h1>
                    <div class="space-y-4">
                        <DataExplicada v-if="props.proceso" :proceso="props.proceso" />

                        <div v-if="props.proceso?.validacioncini" class="flex justify-center">
                            <span class="font-semibold text-gray-600 dark:text-white">¿Es privado?</span>
                            <span class="text-gray-900 dark:text-white">
                                {{ props.proceso?.esPrivado == '1' ? '✅' : '❌' }}
                            </span>
                        </div>
                        <div v-if="Array.isArray(props.proceso) && props.proceso.length > 1" class="flex justify-center">
                            Hay mas de un proceso ( {{ props.proceso?.length }} )
                        </div>

                        <DetalleActuacion v-if="props.proceso?.validacioncini && props.Actuaciones" :actuaciones="props.Actuaciones" />

                        <DetalleProceso
                            v-if="props.proceso?.validacioncini && props.obtenerDetalleProceso"
                            :proceso="props.obtenerDetalleProceso"
                            :UltimaActuacion="UltimaActuacion"
                        />
                    </div>
                </div>
                <div v-else class="relative w-full max-w-2xl overflow-hidden rounded-2xl text-white bg-slate-800 p-6 shadow-2xl">
                    <!-- Fondo animado -->
                    <div class="absolute inset-0 animate-pulse bg-gradient-to-r from-purple-600/20 to-cyan-500/20"></div>
                    <div class="animate-spin-slow absolute -top-20 -right-20 h-60 w-60 rounded-full bg-purple-500/30 blur-3xl"></div>
                    <div class="absolute -bottom-20 -left-20 h-60 w-60 animate-bounce rounded-full bg-cyan-500/30 blur-3xl"></div>

                    <!-- Contenido -->
                    <div class="relative">
                        <h1 class="mb-4 flex items-center gap-2 text-2xl font-bold">📑 Procesos Vigilados</h1>

                        <ul class="space-y-2">
                            <li class="animate-fade-in-down rounded-lg bg-slate-700/60 p-3 shadow transition hover:bg-slate-700">
                                05266310500120220004300
                            </li>
                            <li class="animate-fade-in-down rounded-lg bg-slate-700/60 p-3 shadow transition delay-200 hover:bg-slate-700">
                                05360310500220240023300
                            </li>
                            <li class="animate-fade-in-down rounded-lg bg-slate-700/60 p-3 shadow transition delay-400 hover:bg-slate-700">
                                05360310500220240014100
                            </li>
                        </ul>

                        <div class="mt-6 text-right">
                            <button
                                onclick="window.print()"
                                class="rounded-lg bg-gradient-to-r from-purple-500 to-cyan-500 px-4 py-2 font-semibold text-white shadow transition hover:scale-105"
                            >
                                🖨️ Imprimir
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Define la animación de brillo para Tailwind */
@keyframes shine {
    0% {
        transform: translateX(-100%) skewX(-20deg);
    }
    50% {
        transform: translateX(200%) skewX(-20deg);
    }
    100% {
        transform: translateX(-100%) skewX(-20deg);
    }
}

/* Agrega la animación a Tailwind */
.animate-shine {
    animation: shine 3s ease-in-out 1;
    animation-delay: 1ms;
}
</style>
