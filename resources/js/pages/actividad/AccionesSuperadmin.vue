<script setup lang="ts">
import Modal from '@/components/Modal.vue';
import { useForm } from '@inertiajs/vue3';
import { AlertOctagon, AlertTriangle, CheckCircle2, ShieldAlert, Trash2 } from 'lucide-vue-next';
import { computed, reactive } from 'vue';

const props = defineProps({
    esAmbienteTest: {
        type: Boolean,
        default: false,
    },
});

const FRASE_REQUERIDA = 'BORRAR TODO DEFINITIVAMENTE';

const state = reactive({
    modalAbierto: false,
    textoConfirmacion: '',
    progreso: 0,
    presionando: false,
    intervaloId: null as number | null,
    duracionTotalMs: 3000,
});

const form = useForm({
    confirmacion: '',
});

const fraseValida = computed(() => state.textoConfirmacion.trim().toUpperCase() === FRASE_REQUERIDA);

const abrirModal = () => {
    state.modalAbierto = true;
    state.textoConfirmacion = '';
    state.progreso = 0;
    state.presionando = false;
};

const cancelarPresion = () => {
    if (state.intervaloId !== null) {
        clearInterval(state.intervaloId);
        state.intervaloId = null;
    }
    state.presionando = false;
    state.progreso = 0;
};

const cerrarModal = () => {
    cancelarPresion();
    state.modalAbierto = false;
    state.textoConfirmacion = '';
    state.progreso = 0;
};

const ejecutarPurga = () => {
    form.confirmacion = state.textoConfirmacion.trim().toUpperCase();
    form.post(route('superadmin.purgar-datos-prueba'), {
        preserveScroll: true,
        onSuccess: () => {
            cerrarModal();
        },
    });
};

const iniciarPresion = () => {
    if (!fraseValida.value || form.processing) {
        return;
    }
    state.presionando = true;
    state.progreso = 0;
    const inicio = Date.now();
    state.intervaloId = window.setInterval(() => {
        const transcurrido = Date.now() - inicio;
        const porcentaje = Math.min(100, Math.round((transcurrido / state.duracionTotalMs) * 100));
        state.progreso = porcentaje;
        if (transcurrido >= state.duracionTotalMs) {
            cancelarPresion();
            ejecutarPurga();
        }
    }, 50);
};
</script>

<template>
    <div v-if="props.esAmbienteTest && can(['issuperadmin'])" class="mt-8">
        <div class="rounded-xl border border-red-500/30 bg-red-50/50 p-6 dark:border-red-500/20 dark:bg-red-950/20">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-3">
                    <div class="rounded-lg bg-red-500/10 p-2.5 text-red-600 dark:bg-red-500/20 dark:text-red-400">
                        <ShieldAlert class="h-6 w-6" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-semibold text-foreground">Acciones de Superadministrador</h3>
                            <span class="inline-flex items-center rounded-md border border-amber-500/30 bg-amber-500/10 px-2 py-0.5 text-xs font-semibold text-amber-600 dark:text-amber-400">
                                APP_ENV=test
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Herramientas de mantenimiento para entornos de prueba. Estas operaciones modifican datos críticos de forma irreversible.
                        </p>
                    </div>
                </div>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                <div class="flex flex-col justify-between rounded-lg border border-border bg-card p-4 shadow-sm">
                    <div>
                        <div class="flex items-center gap-2 text-red-600 dark:text-red-400">
                            <Trash2 class="h-4 w-4" />
                            <h4 class="text-sm font-semibold">Purgar datos operativos</h4>
                        </div>
                        <p class="mt-2 text-xs text-muted-foreground leading-relaxed">
                            Borra permanentemente todos los registros de OP, materiales, productos, talleres, empresas externas y proveedores.
                        </p>
                    </div>
                    <div class="mt-4 pt-3 border-t border-border/60">
                        <button
                            type="button"
                            @click="abrirModal"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-md bg-red-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-red-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                            Vaciar registros de prueba
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <Modal :show="state.modalAbierto" maxWidth="lg" @close="cerrarModal">
            <div class="p-6">
                <div class="flex items-start gap-3">
                    <div class="rounded-full bg-red-100 p-2.5 text-red-600 dark:bg-red-900/40 dark:text-red-400">
                        <AlertOctagon class="h-6 w-6" />
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-foreground">¿Vaciar todos los registros de prueba?</h3>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Esta acción eliminará de forma irreversible y permanente los siguientes módulos:
                        </p>
                    </div>
                </div>

                <div class="mt-4 rounded-lg border border-red-200 bg-red-50/60 p-3.5 text-xs dark:border-red-900/40 dark:bg-red-950/30">
                    <ul class="space-y-1.5 text-red-900 dark:text-red-200">
                        <li class="flex items-center gap-2">
                            <span class="h-1.5 w-1.5 rounded-full bg-red-500"></span>
                            Órdenes de Producción (OPs, procesos, cierres, recepciones, kits y mermas)
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="h-1.5 w-1.5 rounded-full bg-red-500"></span>
                            Materiales (precios, movimientos, variantes y fichas técnicas)
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="h-1.5 w-1.5 rounded-full bg-red-500"></span>
                            Productos (fichas técnicas y distribuciones de tallas)
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="h-1.5 w-1.5 rounded-full bg-red-500"></span>
                            Talleres
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="h-1.5 w-1.5 rounded-full bg-red-500"></span>
                            Empresas Externas
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="h-1.5 w-1.5 rounded-full bg-red-500"></span>
                            Proveedores
                        </li>
                    </ul>
                    <p class="mt-3 border-t border-red-200 pt-2 text-[11px] text-muted-foreground dark:border-red-900/40">
                        Los usuarios, roles, permisos y bitácoras de actividad no se verán afectados.
                    </p>
                </div>

                <div class="mt-5 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-foreground">
                            Paso 1: Escribe la frase de confirmación exacta
                        </label>
                        <div class="mt-1.5 flex items-center gap-2">
                            <code class="rounded border border-border bg-muted px-2 py-1 font-mono text-xs font-bold text-foreground select-all">
                                {{ FRASE_REQUERIDA }}
                            </code>
                            <span v-if="fraseValida" class="flex items-center gap-1 text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                                <CheckCircle2 class="h-4 w-4" />
                                Correcto
                            </span>
                        </div>
                        <input
                            v-model="state.textoConfirmacion"
                            type="text"
                            autocomplete="off"
                            spellcheck="false"
                            placeholder="Escribe la frase aquí para desbloquear..."
                            class="mt-2 block w-full rounded-md border border-input bg-background px-3 py-2 text-xs font-mono uppercase text-foreground placeholder:text-muted-foreground focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-foreground">
                            Paso 2: Mantén presionado el botón por 3 segundos
                        </label>
                        <p class="mt-0.5 text-[11px] text-muted-foreground">
                            Para evitar clics accidentales, no basta con hacer clic. Debes mantener el botón presionado continuamente.
                        </p>

                        <div class="mt-2">
                            <button
                                type="button"
                                :disabled="!fraseValida || form.processing"
                                @mousedown="iniciarPresion"
                                @mouseup="cancelarPresion"
                                @mouseleave="cancelarPresion"
                                @touchstart.prevent="iniciarPresion"
                                @touchend.prevent="cancelarPresion"
                                @touchcancel.prevent="cancelarPresion"
                                @contextmenu.prevent
                                class="relative w-full overflow-hidden rounded-md border border-red-600 bg-card py-2.5 text-xs font-bold text-red-600 shadow-sm transition select-none disabled:cursor-not-allowed disabled:border-border disabled:bg-muted disabled:text-muted-foreground"
                            >
                                <span
                                    class="absolute inset-y-0 left-0 bg-red-600 transition-all duration-75"
                                    :style="{ width: state.progreso + '%' }"
                                ></span>

                                <span
                                    class="relative z-10 flex items-center justify-center gap-2"
                                    :class="{ 'text-white': state.progreso > 40 }"
                                >
                                    <AlertTriangle class="h-4 w-4" />
                                    <template v-if="form.processing">
                                        Vaciando registros...
                                    </template>
                                    <template v-else-if="!fraseValida">
                                        Escribe la frase para habilitar
                                    </template>
                                    <template v-else-if="state.presionando">
                                        Sostén presionado... {{ state.progreso }}%
                                    </template>
                                    <template v-else>
                                        Mantener presionado para purgar (3 seg)
                                    </template>
                                </span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2 border-t border-border pt-4">
                    <button
                        type="button"
                        @click="cerrarModal"
                        :disabled="form.processing"
                        class="rounded-md border border-border bg-card px-4 py-2 text-xs font-semibold text-foreground transition hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        Cancelar
                    </button>
                </div>
            </div>
        </Modal>
    </div>
</template>

