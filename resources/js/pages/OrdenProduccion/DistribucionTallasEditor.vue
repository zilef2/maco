<script setup lang="ts">
import InputLabel from '@/components/InputLabel.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import { TrashIcon } from '@heroicons/vue/24/solid';
import { computed, ref, watchEffect } from 'vue';
import vSelect from 'vue-select';
import 'vue-select/dist/vue-select.css';

type FilaTalla = {
    id?: number;
    grupo?: number | null;
    talla: string;
    cantidad_planificada: number | string;
    cantidad_recibida_buena?: number | string | null;
    cantidad_pendiente_reparacion?: number | string | null;
    producto_id?: number | null;
    _origen?: 'producto' | 'grupo' | 'manual';
};

const props = defineProps({
    items: { type: Array as () => FilaTalla[], required: true },
    gruposTallas: { type: Array, default: () => [] },
    canEdit: { type: Boolean, default: true },
    // Si es false, la cantidad planificada de las filas ya guardadas (con id)
    // queda bloqueada (comportamiento del modal de distribución de una OP ya
    // creada). Por defecto true: al crear/editar la OP se puede ajustar libremente.
    planificadaEditable: { type: Boolean, default: true },
    mostrarRecepcion: { type: Boolean, default: true },
    grupoId: { type: [Number, String, Object, null], default: null },
});

const emit = defineEmits(['update:items', 'update:grupoId']);

const filas = ref<FilaTalla[]>([]);

// Valor del campo "Todos": al presionar Enter (o el botón) se copia a
// cantidad_planificada de todas las tallas visibles en el editor.
const todos = ref<number | string>('');

watchEffect(() => {
    filas.value = (props.items || []).map((i) => ({ ...i }));
});

const emitir = () => {
    emit(
        'update:items',
        filas.value.map((i) => ({ ...i })),
    );
};

const total = computed(() => {
    return filas.value.reduce((acc, item) => acc + (Number(item.cantidad_planificada) || 0), 0);
});

const agregarTalla = () => {
    filas.value.push({ talla: '', cantidad_planificada: '1', _origen: 'manual' });
    emitir();
};

const eliminarTalla = (index: number) => {
    filas.value.splice(index, 1);
    emitir();
};

const aplicarATodos = () => {
    const valor = todos.value;
    if (valor === '' || valor === null || valor === undefined) return;
    // Unifica el formato a número si es válido, si no, conserva el texto.
    const numero = Number(valor);
    const aplicable = Number.isFinite(numero) && numero >= 0 ? numero : valor;
    filas.value.forEach((f) => {
        f.cantidad_planificada = aplicable as number | string;
    });
    emitir();
};

const elegirGrupo = (grupo: any) => {
    const g = grupo ?? (props.gruposTallas || []).find((x: any) => x.id === props.grupoId);
    if (!g || !g.tallas) {
        return;
    }
    emit('update:grupoId', g.id);
    const existentes = new Set(filas.value.map((i) => (i.talla ?? '').trim().toLowerCase()));
    (g.tallas || []).forEach((t: any) => {
        if (
            !existentes.has(
                String(t.talla ?? '')
                    .trim()
                    .toLowerCase(),
            )
        ) {
            filas.value.push({
                id: t.id,
                grupo: g.id,
                talla: t.talla ?? '',
                cantidad_planificada: String(t.cantidad_planificada ?? '1'),
                cantidad_recibida_buena: null,
                cantidad_pendiente_reparacion: null,
                _origen: 'grupo',
            });
        }
    });
    emitir();
};
</script>

<template>
    <div>
        <div v-if="props.gruposTallas.length" class="mb-3">
            <InputLabel for="grupo_tallas" :value="'Grupo de tallas (sugerencia)'" />
            <v-select
                id="grupo_tallas"
                class="mt-1 block w-full"
                :options="props.gruposTallas"
                label="name"
                :reduce="(item: any) => item.id"
                :model-value="props.grupoId"
                @update:model-value="elegirGrupo"
                placeholder="Seleccione un grupo para cargar sus tallas..."
            />
        </div>

        <!-- Aplicar un mismo valor a todas las tallas -->
        <div v-if="props.canEdit && filas.length" class="mb-3 flex flex-wrap items-center gap-2">
            <label for="todos" class="text-xs font-medium whitespace-nowrap text-gray-600 dark:text-gray-300"> Todos </label>
            <TextInput
                id="todos"
                type="number"
                min="0"
                v-model="todos"
                @keyup.enter="aplicarATodos"
                placeholder="Ej: 10"
                class="block w-28 text-sm"
                title="Escriba un valor y presione Enter para aplicarlo a todas las tallas"
            />
            <button
                type="button"
                @click="aplicarATodos"
                class="inline-flex items-center rounded-md border border-indigo-300 bg-indigo-50 px-2.5 py-1.5 text-xs font-medium text-indigo-700 transition hover:bg-indigo-100 dark:border-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300 dark:hover:bg-indigo-900/40"
            >
                Aplicar a todas
            </button>
            <span class="text-xs text-gray-400 dark:text-gray-500"> Escriba un valor y presione Enter para aplicar a todas las tallas. </span>
        </div>

        <div v-if="filas.length === 0" class="py-4 text-center text-sm text-gray-500 dark:text-gray-400">Sin tallas registradas.</div>

        <div v-else class="space-y-3">
            <div v-for="(item, index) in filas" :key="item.id ?? `nueva-${index}`" class="space-y-1 border-b pb-3 dark:border-gray-700">
                <div class="flex items-center space-x-2">
                    <div class="flex-1">
                        <InputLabel :for="`talla-${index}`" value="Talla" class="sr-only" />
                        <TextInput
                            :id="`talla-${index}`"
                            type="text"
                            v-model="item.talla"
                            @input="emitir"
                            :readonly="!props.canEdit"
                            placeholder="Talla (ej: S, M, L)"
                            class="block w-full text-sm"
                        />
                    </div>
                    <div class="w-24">
                        <InputLabel :for="`planificada-${index}`" value="Planificada" class="sr-only" />
                        <TextInput
                            :id="`planificada-${index}`"
                            type="number"
                            min="0"
                            v-model="item.cantidad_planificada"
                            @input="emitir"
                            :readonly="!(props.canEdit && (props.planificadaEditable || !item.id))"
                            title="Cantidad planificada"
                            placeholder="Planif."
                            class="block w-full bg-gray-100 text-sm dark:bg-gray-800"
                        />
                    </div>
                    <span
                        v-if="item.grupo != null"
                        class="inline-flex items-center rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300"
                        :title="`Grupo ${item.grupo}`"
                    >
                        G{{ item.grupo }}
                    </span>
                    <button
                        v-if="props.canEdit"
                        type="button"
                        @click="eliminarTalla(index)"
                        class="rounded-md p-2 text-red-500 transition hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-900/20"
                        title="Eliminar talla"
                    >
                        <TrashIcon class="h-5 w-5" />
                    </button>
                </div>
                <div v-if="props.mostrarRecepcion" class="flex items-center space-x-2">
                    <div class="flex-1">
                        <InputLabel :for="`recibida-${index}`" :value="'Recibida buena'" class="sr-only" />
                        <TextInput
                            :id="`recibida-${index}`"
                            type="number"
                            min="0"
                            v-model="item.cantidad_recibida_buena"
                            @input="emitir"
                            :readonly="!props.canEdit"
                            placeholder="Recibida buena"
                            class="block w-full text-sm"
                        />
                    </div>
                    <div class="flex-1">
                        <InputLabel :for="`reparacion-${index}`" :value="'Pendiente reparación'" class="sr-only" />
                        <TextInput
                            :id="`reparacion-${index}`"
                            type="number"
                            min="0"
                            v-model="item.cantidad_pendiente_reparacion"
                            @input="emitir"
                            :readonly="!props.canEdit"
                            placeholder="Pendiente reparación"
                            class="block w-full text-sm"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4 flex items-center justify-between">
            <SecondaryButton v-if="props.canEdit" type="button" @click="agregarTalla" class="text-xs"> + Agregar talla </SecondaryButton>
            <span v-else></span>
            <div class="text-right">
                <span class="block text-xs text-gray-500 dark:text-gray-400">Total planificado:</span>
                <span class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ total }}</span>
            </div>
        </div>
    </div>
</template>
