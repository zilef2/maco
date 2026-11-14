<script setup lang="ts">
import Modal from '@/components/Modal.vue';
import { router, useForm } from '@inertiajs/vue3';
import { AlertOctagon, AlertTriangle, CheckCircle2, Layers, Loader2, ShieldAlert, Shuffle, Trash2 } from 'lucide-vue-next';
import { computed, reactive } from 'vue';

const props = defineProps({
    esAmbienteTest: {
        type: Boolean,
        default: false,
    },
    materiales: {
        type: Object as () => {
            total: number;
            sin_existencia: number;
            sin_precio: number;
            rango_existencia: string;
            rango_precio: string;
            genericos: { grupos: number; con_generico: number; sin_generico: number; genericos: number };
        },
        default: () => ({
            total: 0,
            sin_existencia: 0,
            sin_precio: 0,
            rango_existencia: '',
            rango_precio: '',
            genericos: { grupos: 0, con_generico: 0, sin_generico: 0, genericos: 0 },
        }),
    },
});

interface AccionPurgable {
    clave: string;
    titulo: string;
    descripcion: string;
    boton: string;
    frase: string;
    pregunta: string;
    procesando: string;
    sostener: string;
    borrar: () => string;
    modulos: string[];
    nota: string;
}

const ACCIONES: Record<string, AccionPurgable> = {
    operativos: {
        clave: 'operativos',
        titulo: 'Purgar datos operativos',
        descripcion: 'Borra permanentemente todos los registros de OP, materiales, productos, talleres, empresas externas y proveedores.',
        boton: 'Vaciar registros de prueba',
        frase: 'BORRAR TODO DEFINITIVAMENTE',
        pregunta: '¿Vaciar todos los registros de prueba?',
        procesando: 'Vaciando registros...',
        sostener: 'Mantener presionado para purgar (3 seg)',
        borrar: () => route('superadmin.purgar-datos-prueba'),
        modulos: [
            'Órdenes de Producción (OPs, procesos, cierres, recepciones, kits y mermas)',
            'Materiales (precios, movimientos, variantes y fichas técnicas)',
            'Productos (fichas técnicas y distribuciones de tallas)',
            'Talleres',
            'Empresas Externas',
            'Proveedores',
        ],
        nota: 'Los usuarios, roles, permisos y bitácoras de actividad no se verán afectados.',
    },
    materiales: {
        clave: 'materiales',
        titulo: 'Borrar todos los materiales',
        descripcion:
            'Borra los materiales y todo lo que cuelga de ellos: variantes, precios, movimientos, fichas técnicas y las líneas de kit que los usan.',
        boton: 'Borrar los materiales',
        frase: 'BORRAR TODOS LOS MATERIALES',
        pregunta: '¿Borrar los {n} materiales?',
        procesando: 'Borrando materiales...',
        sostener: 'Mantener presionado para borrar (3 seg)',
        borrar: () => route('superadmin.purgar-materiales'),
        modulos: [
            'Materiales con sus variantes, precios y movimientos de inventario',
            'Fichas técnicas de material y componentes de ensamble',
            'Líneas de kit y procesos externos que apuntan a un material',
            'Costos de material ya liquidados en las órdenes de producción',
        ],
        nota: 'Productos, talleres, proveedores y usuarios no se borran: solo pierden el material que referenciaban.',
    },
};

const numero = (valor: number) => valor.toLocaleString('es-CO');

interface AjusteDirecto {
    clave: string;
    titulo: string;
    descripcion: () => string;
    boton: string;
    procesando: string;
    ruta: () => string;
    detalle: () => string;
    /** true cuando el ajuste solo tiene sentido en entorno de prueba. */
    soloTest?: boolean;
}

/** Ajustes de datos de prueba: se presionan y ya, sin frase ni botón de sostén. */
const AJUSTES: AjusteDirecto[] = [
    {
        clave: 'cero',
        soloTest: true,
        titulo: 'Existencias en cero',
        descripcion: () => 'Pone en 0 la existencia actual de los materiales. No toca precios, variantes ni movimientos.',
        boton: 'Poner existencias en 0',
        procesando: 'Poniendo en 0...',
        ruta: () => route('superadmin.existencias-cero'),
        detalle: () => `${numero(props.materiales.total - props.materiales.sin_existencia)} materiales con existencia distinta de 0`,
    },
    {
        clave: 'azar',
        soloTest: true,
        titulo: 'Existencias y precios al azar',
        descripcion: () =>
            `Da valor solo a lo que no tiene: existencia ${props.materiales.rango_existencia} a los que están en 0 y precio ${props.materiales.rango_precio} a los que no tienen ninguna fila de precio.`,
        boton: 'Rellenar los que no tienen',
        procesando: 'Rellenando...',
        ruta: () => route('superadmin.materiales-al-azar'),
        detalle: () => `${numero(props.materiales.sin_existencia)} sin existencia · ${numero(props.materiales.sin_precio)} sin precio`,
    },
    {
        clave: 'genericos',
        titulo: 'Materiales genéricos',
        descripcion: () =>
            'Crea el material genérico que falta por cada par categoría-base (no maneja talla ni color) y le pone como precio el promedio de los precios de sus similares. Se puede repetir: solo crea los que faltan y refresca el precio si cambió.',
        boton: 'Crear los genéricos que faltan',
        procesando: 'Creando genéricos...',
        ruta: () => route('superadmin.materiales-genericos'),
        detalle: () =>
            `${numero(props.materiales.genericos?.grupos ?? 0)} combinaciones · ${numero(props.materiales.genericos?.sin_generico ?? 0)} sin genérico · ${numero(props.materiales.genericos?.genericos ?? 0)} genéricos`,
    },
];

const ajustesVisibles = computed(() => AJUSTES.filter((ajuste) => !ajuste.soloTest || props.esAmbienteTest));

const state = reactive({
    accion: null as AccionPurgable | null,
    procesando: null as string | null,
    textoConfirmacion: '',
    progreso: 0,
    presionando: false,
    intervaloId: null as number | null,
    duracionTotalMs: 3000,
});

const form = useForm({
    confirmacion: '',
});

const modalAbierto = computed(() => state.accion !== null);

const acciones = Object.values(ACCIONES);

const fraseValida = computed(() => state.accion !== null && state.textoConfirmacion.trim().toUpperCase() === state.accion.frase);

const pregunta = computed(() => (state.accion?.pregunta ?? '').replace('{n}', numero(props.materiales.total)));

const ejecutarAjuste = (ajuste: AjusteDirecto) => {
    if (state.procesando !== null) {
        return;
    }

    state.procesando = ajuste.clave;
    router.post(
        ajuste.ruta(),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                state.procesando = null;
            },
        },
    );
};

const abrirModal = (accion: AccionPurgable) => {
    state.accion = accion;
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
    state.accion = null;
    state.textoConfirmacion = '';
    state.progreso = 0;
};

const ejecutarPurga = () => {
    const accion = state.accion;
    if (!accion) return;

    form.confirmacion = state.textoConfirmacion.trim().toUpperCase();
    form.post(accion.borrar(), {
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
    <div v-if="can(['issuperadmin'])" class="mt-8">
        <div class="rounded-xl border border-red-500/30 bg-red-50/50 p-6 dark:border-red-500/20 dark:bg-red-950/20">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-3">
                    <div class="rounded-lg bg-red-500/10 p-2.5 text-red-600 dark:bg-red-500/20 dark:text-red-400">
                        <ShieldAlert class="h-6 w-6" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-foreground text-base font-semibold">Acciones de Superadministrador</h3>
                            <span
                                v-if="props.esAmbienteTest"
                                class="inline-flex items-center rounded-md border border-amber-500/30 bg-amber-500/10 px-2 py-0.5 text-xs font-semibold text-amber-600 dark:text-amber-400"
                            >
                                APP_ENV=test
                            </span>
                        </div>
                        <p class="text-muted-foreground mt-1 text-xs">
                            Herramientas de mantenimiento. Las operaciones en rojo modifican datos críticos de forma irreversible y solo aparecen en entorno de prueba.
                        </p>
                    </div>
                </div>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                <template v-for="accion in acciones" :key="accion.clave">
                    <div
                        v-if="props.esAmbienteTest"
                        class="border-border bg-card flex flex-col justify-between rounded-lg border p-4 shadow-sm"
                    >
                        <div>
                            <div class="flex items-center gap-2 text-red-600 dark:text-red-400">
                                <Trash2 class="h-4 w-4" />
                                <h4 class="text-sm font-semibold">{{ accion.titulo }}</h4>
                            </div>
                            <p class="text-muted-foreground mt-2 text-xs leading-relaxed">
                                {{ accion.descripcion }}
                            </p>
                            <p v-if="accion.clave === 'materiales'" class="text-foreground mt-2 font-mono text-xs">
                                {{ numero(props.materiales.total) }} materiales ahora
                            </p>
                        </div>
                        <div class="border-border/60 mt-4 border-t pt-3">
                            <button
                                type="button"
                                @click="abrirModal(accion)"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-md bg-red-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-red-700 focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2 focus-visible:outline-none"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                                {{ accion.boton }}
                            </button>
                        </div>
                    </div>
                </template>

                <div
                    v-for="ajuste in ajustesVisibles"
                    :key="ajuste.clave"
                    class="border-border bg-card flex flex-col justify-between rounded-lg border p-4 shadow-sm"
                >
                    <div>
                        <div class="flex items-center gap-2 text-amber-600 dark:text-amber-400">
                            <component :is="ajuste.clave === 'genericos' ? Layers : Shuffle" class="h-4 w-4" />
                            <h4 class="text-sm font-semibold">{{ ajuste.titulo }}</h4>
                        </div>
                        <p class="text-muted-foreground mt-2 text-xs leading-relaxed">
                            {{ ajuste.descripcion() }}
                        </p>
                        <p class="text-foreground mt-2 font-mono text-xs">{{ ajuste.detalle() }}</p>
                    </div>
                    <div class="border-border/60 mt-4 border-t pt-3">
                        <button
                            type="button"
                            :disabled="state.procesando !== null"
                            @click="ejecutarAjuste(ajuste)"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-md bg-amber-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-amber-700 focus-visible:ring-2 focus-visible:ring-amber-500 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <Loader2 v-if="state.procesando === ajuste.clave" class="h-3.5 w-3.5 animate-spin" />
                            <component :is="ajuste.clave === 'genericos' ? Layers : Shuffle" v-else class="h-3.5 w-3.5" />
                            {{ state.procesando === ajuste.clave ? ajuste.procesando : ajuste.boton }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <Modal :show="modalAbierto" max-width="lg" @close="cerrarModal">
            <div v-if="state.accion" class="p-6">
                <div class="flex items-start gap-3">
                    <div class="rounded-full bg-red-100 p-2.5 text-red-600 dark:bg-red-900/40 dark:text-red-400">
                        <AlertOctagon class="h-6 w-6" />
                    </div>
                    <div>
                        <h3 class="text-foreground text-base font-semibold">{{ pregunta }}</h3>
                        <p class="text-muted-foreground mt-1 text-xs">
                            Esta acción eliminará de forma irreversible y permanente los siguientes módulos:
                        </p>
                    </div>
                </div>

                <div class="mt-4 rounded-lg border border-red-200 bg-red-50/60 p-3.5 text-xs dark:border-red-900/40 dark:bg-red-950/30">
                    <ul class="space-y-1.5 text-red-900 dark:text-red-200">
                        <li v-for="modulo in state.accion.modulos" :key="modulo" class="flex items-center gap-2">
                            <span class="h-1.5 w-1.5 rounded-full bg-red-500"></span>
                            {{ modulo }}
                        </li>
                    </ul>
                    <p class="text-muted-foreground mt-3 border-t border-red-200 pt-2 text-[11px] dark:border-red-900/40">
                        {{ state.accion.nota }}
                    </p>
                </div>

                <div class="mt-5 space-y-4">
                    <div>
                        <label class="text-foreground block text-xs font-semibold"> Paso 1: Escribe la frase de confirmación exacta </label>
                        <div class="mt-1.5 flex items-center gap-2">
                            <code class="border-border bg-muted text-foreground rounded border px-2 py-1 font-mono text-xs font-bold select-all">
                                {{ state.accion.frase }}
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
                            class="border-input bg-background text-foreground placeholder:text-muted-foreground mt-2 block w-full rounded-md border px-3 py-2 font-mono text-xs uppercase focus:border-red-500 focus:ring-1 focus:ring-red-500 focus:outline-none"
                        />
                    </div>

                    <div>
                        <label class="text-foreground block text-xs font-semibold"> Paso 2: Mantén presionado el botón por 3 segundos </label>
                        <p class="text-muted-foreground mt-0.5 text-[11px]">
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
                                class="bg-card disabled:border-border disabled:bg-muted disabled:text-muted-foreground relative w-full overflow-hidden rounded-md border border-red-600 py-2.5 text-xs font-bold text-red-600 shadow-sm transition select-none disabled:cursor-not-allowed"
                            >
                                <span
                                    class="absolute inset-y-0 left-0 bg-red-600 transition-all duration-75"
                                    :style="{ width: state.progreso + '%' }"
                                ></span>

                                <span class="relative z-10 flex items-center justify-center gap-2" :class="{ 'text-white': state.progreso > 40 }">
                                    <AlertTriangle class="h-4 w-4" />
                                    <template v-if="form.processing">
                                        {{ state.accion.procesando }}
                                    </template>
                                    <template v-else-if="!fraseValida"> Escribe la frase para habilitar </template>
                                    <template v-else-if="state.presionando"> Sostén presionado... {{ state.progreso }}% </template>
                                    <template v-else>
                                        {{ state.accion.sostener }}
                                    </template>
                                </span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="border-border mt-6 flex justify-end gap-2 border-t pt-4">
                    <button
                        type="button"
                        @click="cerrarModal"
                        :disabled="form.processing"
                        class="border-border bg-card text-foreground hover:bg-muted focus-visible:ring-ring rounded-md border px-4 py-2 text-xs font-semibold transition focus-visible:ring-2 focus-visible:outline-none"
                    >
                        Cancelar
                    </button>
                </div>
            </div>
        </Modal>
    </div>
</template>
