<script setup lang="ts">
import DangerButton from '@/components/DangerButton.vue';
import Modal from '@/components/Modal.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import axios from 'axios';
import { computed, reactive, watch } from 'vue';

const props = defineProps({
    show: Boolean,
    title: String,
    materiales: {
        type: Array as () => any[],
        default: () => [],
    },
});

const emit = defineEmits(['close', 'eliminados']);

type Dependencia = { id: number; numero_op?: string; estado?: string; nombre?: string };
type MaterialConDependencias = {
    id: number;
    codigo: string | null;
    nombre: string;
    ordenes: Dependencia[];
    fichas: Dependencia[];
};

const state = reactive({
    cargando: false,
    eliminando: false,
    error: '',
    materiales: [] as MaterialConDependencias[],
    ordenes: [] as Dependencia[],
    fichas: [] as Dependencia[],
    conDependientes: false,
});

const cabecera = () => ({
    'Content-Type': 'application/json',
    Accept: 'application/json',
    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
});

const tieneDependencias = computed(() => state.ordenes.length > 0 || state.fichas.length > 0);

const materialesSinDependencias = computed(
    () => state.materiales.filter((m) => !m.ordenes.length && !m.fichas.length).length,
);

// Con la opción desactivada solo se borran los materiales sueltos.
const cantidadAEliminar = computed(() =>
    state.conDependientes ? state.materiales.length : materialesSinDependencias.value,
);

const cargarDependencias = async () => {
    state.cargando = true;
    state.error = '';
    state.conDependientes = false;

    try {
        const { data } = await axios.post(route('Material.dependencias-bulk'), {
            id: props.materiales.map((m: any) => m.id),
        }, { headers: cabecera() });

        state.materiales = data.materiales ?? [];
        state.ordenes = data.ordenes ?? [];
        state.fichas = data.fichas ?? [];
    } catch {
        state.error = 'No fue posible revisar las relaciones de los materiales.';
    } finally {
        state.cargando = false;
    }
};

const eliminar = async () => {
    state.eliminando = true;
    state.error = '';

    try {
        const { data } = await axios.post(route('Material.destroy-bulk'), {
            id: props.materiales.map((m: any) => m.id),
            con_dependientes: state.conDependientes,
        }, { headers: cabecera() });

        emit('eliminados', data);
        emit('close');
    } catch {
        state.error = 'No fue posible eliminar los materiales.';
    } finally {
        state.eliminando = false;
    }
};

watch(
    () => props.show,
    (isOpen) => {
        if (isOpen && props.materiales.length) {
            cargarDependencias();
        } else {
            state.materiales = [];
            state.ordenes = [];
            state.fichas = [];
            state.error = '';
        }
    }
);
</script>

<template>
    <section class="space-y-6">
        <Modal :show="props.show" @close="emit('close')" :maxWidth="'xl3'" :closeable="true">
            <div class="p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    Eliminar {{ props.title }} en masa
                </h2>

                <p v-if="state.cargando" class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Revisando en qué ordenes de producción y fichas técnicas están los
                    {{ props.materiales.length }} materiales seleccionados...
                </p>

                <p v-else-if="state.error" class="mt-2 text-sm font-medium text-red-600 dark:text-red-400">
                    {{ state.error }}
                </p>

                <template v-else>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        ¿Eliminar
                        <span class="font-semibold">{{ cantidadAEliminar }}</span>
                        de {{ props.materiales.length }} {{ props.materiales.length === 1 ? 'material' : 'materiales' }}?
                        Esta acción no se puede deshacer.
                    </p>

                    <div
                        v-if="tieneDependencias && !state.cargando"
                        class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-4 dark:border-amber-500/40 dark:bg-amber-500/10"
                    >
                        <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">
                            {{ state.materiales.length - materialesSinDependencias }}
                            {{ state.materiales.length - materialesSinDependencias === 1 ? 'material está' : 'materiales están' }}
                            relacionado con
                            {{ state.ordenes.length }} orden(es) de producción y {{ state.fichas.length }} ficha(s) técnica(s):
                        </p>

                        <ul class="mt-3 max-h-72 space-y-3 overflow-y-auto">
                            <li
                                v-for="material in state.materiales.filter((m) => m.ordenes.length || m.fichas.length)"
                                :key="material.id"
                                class="text-sm text-amber-900 dark:text-amber-200"
                            >
                                <p class="font-semibold">
                                    {{ material.codigo ? `${material.codigo} · ` : '' }}{{ material.nombre }}
                                </p>
                                <p v-if="material.ordenes.length" class="mt-1 flex flex-wrap gap-1.5">
                                    <span class="text-xs font-medium uppercase tracking-wide opacity-70">OP</span>
                                    <span
                                        v-for="op in material.ordenes"
                                        :key="op.id"
                                        class="rounded-full border border-amber-400/60 bg-white/70 px-2 py-0.5 text-xs dark:bg-gray-900/40"
                                    >
                                        {{ op.numero_op }} ({{ op.estado }})
                                    </span>
                                </p>
                                <p v-if="material.fichas.length" class="mt-1 flex flex-wrap gap-1.5">
                                    <span class="text-xs font-medium uppercase tracking-wide opacity-70">Ficha</span>
                                    <span
                                        v-for="ficha in material.fichas"
                                        :key="ficha.id"
                                        class="rounded-full border border-amber-400/60 bg-white/70 px-2 py-0.5 text-xs dark:bg-gray-900/40"
                                    >
                                        {{ ficha.nombre }}
                                    </span>
                                </p>
                            </li>
                        </ul>

                        <label class="mt-4 flex items-start gap-2 text-sm font-medium text-amber-900 dark:text-amber-200">
                            <input
                                v-model="state.conDependientes"
                                type="checkbox"
                                class="mt-0.5 rounded border-amber-400 text-red-600 focus:ring-red-500"
                            />
                            Eliminar también las {{ state.ordenes.length }} orden(es) de producción y las
                            {{ state.fichas.length }} ficha(s) técnica(s) listadas, con sus movimientos, kits,
                            recepciones y cierres.
                        </label>

                        <p v-if="!state.conDependientes && materialesSinDependencias === 0" class="mt-2 text-xs text-red-700 dark:text-red-300">
                            Ningún material se puede eliminar: todos están relacionados. Activa la opción para
                            eliminar también las ordenes y fichas.
                        </p>
                    </div>
                </template>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton :disabled="state.eliminando" @click="emit('close')">
                        Cerrar
                    </SecondaryButton>
                    <DangerButton
                        :disabled="state.cargando || state.eliminando || cantidadAEliminar === 0"
                        :class="{ 'opacity-25': state.eliminando }"
                        @click="eliminar"
                    >
                        {{ state.eliminando ? 'Eliminando...' : `Eliminar (${cantidadAEliminar})` }}
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </section>
</template>
