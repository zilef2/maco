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
    mostrarRecepcion: { type: Boolean, default: true },
    grupoId: { type: [Number, String, Object, null], default: null },
});

const emit = defineEmits(['update:items', 'update:grupoId']);

const filas = ref<FilaTalla[]>([]);

watchEffect(() => {
    filas.value = (props.items || []).map((i) => ({ ...i }));
});

const emitir = () => {
    emit('update:items', filas.value.map((i) => ({ ...i })));
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

const elegirGrupo = (grupo: any) => {
    const g = grupo ?? (props.gruposTallas || []).find((x: any) => x.id === props.grupoId);
    if (!g || !g.tallas) {
        return;
    }
    emit('update:grupoId', g.id);
    const existentes = new Set(filas.value.map((i) => (i.talla ?? '').trim().toLowerCase()));
    (g.tallas || []).forEach((t: any) => {
        if (!existentes.has(String(t.talla ?? '').trim().toLowerCase())) {
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

        <div v-if="filas.length === 0" class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">
            Sin tallas registradas.
        </div>

        <div v-else class="space-y-3">
            <div
                v-for="(item, index) in filas"
                :key="item.id ?? `nueva-${index}`"
                class="space-y-1 border-b pb-3 dark:border-gray-700"
            >
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
                            :readonly="!(props.canEdit && !item.id)"
                            title="Cantidad planificada"
                            placeholder="Planif."
                            class="block w-full text-sm bg-gray-100 dark:bg-gray-800"
                        />
                    </div>
                    <span v-if="item.grupo != null" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300" :title="`Grupo ${item.grupo}`">
                        G{{ item.grupo }}
                    </span>
                    <button
                        v-if="props.canEdit"
                        type="button"
                        @click="eliminarTalla(index)"
                        class="p-2 text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-md transition"
                        title="Eliminar talla"
                    >
                        <TrashIcon class="w-5 h-5" />
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

        <div class="mt-4 flex justify-between items-center">
            <SecondaryButton v-if="props.canEdit" type="button" @click="agregarTalla" class="text-xs">
                + Agregar talla
            </SecondaryButton>
            <span v-else></span>
            <div class="text-right">
                <span class="text-xs text-gray-500 dark:text-gray-400 block">Total planificado:</span>
                <span class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ total }}</span>
            </div>
        </div>
    </div>
</template>
