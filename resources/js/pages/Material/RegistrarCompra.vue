<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import Modal from '@/components/Modal.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import { zilef_nuber_format } from '@/global.ts';
import { useForm } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';
import vSelect from 'vue-select';
import 'vue-select/dist/vue-select.css';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    materiales: {
        type: Array as () => any[],
        default: () => [],
    },
});

const emit = defineEmits(['close']);

const filaVacia = () => ({ material_id: '', cantidad: '' });

const state = reactive({
    errorLocal: '',
});

const form = useForm({
    materiales: [filaVacia()] as any[],
});

const opciones = computed<any[]>(() =>
    (props.materiales || []).map((material: any) => ({
        ...material,
        id: Number(material.id),
        name: [
            material.codigo_material,
            material.nombre_base,
        ].filter(Boolean).join(' — ') || ('Material #' + material.id),
    }))
);

const materialSeleccionado = (materialId: any) =>
    opciones.value.find((material: any) => Number(material.id) === Number(materialId));

const unidad = (materialId: any): string =>
    materialSeleccionado(materialId)?.unidad_inventario || '';

const existenciaActual = (materialId: any): number =>
    Number(materialSeleccionado(materialId)?.existencia_actual ?? 0);

const existenciaResultante = (fila: any): number =>
    existenciaActual(fila.material_id) + Math.max(0, Number(fila.cantidad) || 0);

const obtenerUltimaCompra = (materialId: any) => {
    const mat = materialSeleccionado(materialId);
    if (!mat || mat.ultima_compra_cantidad === null || mat.ultima_compra_cantidad === undefined) {
        return null;
    }
    return {
        cantidad: Number(mat.ultima_compra_cantidad),
        fecha: mat.ultima_compra_fecha,
        fecha_humana: mat.ultima_compra_fecha_humana,
    };
};

const alCambiarMaterial = (fila: any, opcionSeleccionada?: any) => {
    const mat = opcionSeleccionada || materialSeleccionado(fila.material_id);
    if (!mat) return;

    fila.material_id = mat.id;

    if (mat.ultima_compra_cantidad !== null && mat.ultima_compra_cantidad !== undefined) {
        fila.cantidad = String(mat.ultima_compra_cantidad);
    }
};

const aplicarSugerencia = (fila: any) => {
    const mat = materialSeleccionado(fila.material_id);
    if (mat && mat.ultima_compra_cantidad !== null && mat.ultima_compra_cantidad !== undefined) {
        fila.cantidad = String(mat.ultima_compra_cantidad);
    }
};

const filasValidas = computed(() =>
    form.materiales.filter(
        (fila: any) => fila.material_id && Number(fila.cantidad) > 0
    )
);

const compraIncompleta = computed(() =>
    form.materiales.some((fila: any) => {
        const tieneMaterial = fila.material_id !== '' && fila.material_id !== null && fila.material_id !== undefined;
        const tieneCantidad = fila.cantidad !== '' && fila.cantidad !== null && fila.cantidad !== undefined;

        if (!tieneMaterial && !tieneCantidad) return false;
        if (!tieneMaterial || !tieneCantidad) return true;

        const cantidad = Number(fila.cantidad);
        return !Number.isFinite(cantidad) || cantidad <= 0;
    })
);

const errores = computed(() => {
    const mensajes = Object.entries(form.errors)
        .filter(([campo]) => campo.startsWith('materiales'))
        .map(([, mensaje]) => mensaje);

    return [...new Set(mensajes)];
});

const reiniciar = () => {
    form.reset();
    form.clearErrors();
    form.materiales = [filaVacia()];
    state.errorLocal = '';
};

watch(
    () => props.show,
    (visible) => {
        if (visible) reiniciar();
    }
);

const agregarFila = () => {
    form.materiales.push(filaVacia());
};

const quitarFila = (indice: number) => {
    form.materiales.splice(indice, 1);
    if (!form.materiales.length) {
        form.materiales.push(filaVacia());
    }
};

const guardar = () => {
    state.errorLocal = '';

    if (compraIncompleta.value) {
        state.errorLocal = 'Completa el material y una cantidad mayor que cero.';
        return;
    }

    const filas = filasValidas.value;
    if (!filas.length) {
        state.errorLocal = 'Agrega al menos un material con cantidad.';
        return;
    }

    form.materiales = filas.map((fila: any) => ({
        material_id: Number(fila.material_id),
        cantidad: Number(fila.cantidad),
    }));

    form.post(route('Material.compra'), {
        preserveScroll: true,
        onSuccess: () => {
            emit('close');
            reiniciar();
        },
        onError: () => null,
    });
};
</script>

<template>
    <section class="space-y-6">
        <Modal :show="props.show" @close="emit('close')" maxWidth="xl4">
            <form class="p-6 space-y-4" @submit.prevent="guardar">
                <div class="border-b border-gray-200 pb-3 dark:border-gray-700">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        Registrar compra de materiales
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Registra uno o varios materiales comprados. Al seleccionar cada material, se sugerirá automáticamente la cantidad comprada la última vez.
                    </p>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Las cantidades se suman a la existencia actual.
                    </p>
                    <SecondaryButton type="button" class="text-xs" @click="agregarFila">
                        + Agregar material
                    </SecondaryButton>
                </div>

                <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[760px] text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 bg-gray-50 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400">
                                    <th scope="col" class="px-3 py-2 font-semibold">Material</th>
                                    <th scope="col" class="w-52 px-3 py-2 font-semibold">Cantidad sugerida</th>
                                    <th scope="col" class="w-44 px-3 py-2 text-right font-semibold">Existencia actual</th>
                                    <th scope="col" class="w-16 px-3 py-2 font-semibold"><span class="sr-only">Quitar</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                                <tr v-for="(fila, indice) in form.materiales" :key="indice">
                                    <td class="px-3 py-2 align-top">
                                        <v-select
                                            :options="opciones"
                                            label="name"
                                            :reduce="(item: any) => item.id"
                                            v-model="fila.material_id"
                                            placeholder="Seleccione el material..."
                                            append-to-body
                                            :aria-label="`Material comprado ${indice + 1}`"
                                            @option:selected="(opt: any) => alCambiarMaterial(fila, opt)"
                                        />
                                    </td>
                                    <td class="px-3 py-2 align-top">
                                        <div class="flex items-center gap-1.5">
                                            <TextInput
                                                type="number"
                                                min="0.01"
                                                step="0.01"
                                                class="block h-9 w-full px-2 text-right tabular-nums"
                                                v-model="fila.cantidad"
                                                placeholder="0"
                                                :aria-label="`Cantidad comprada ${indice + 1}`"
                                            />
                                            <span class="w-10 shrink-0 text-xs text-gray-500 dark:text-gray-400">
                                                {{ unidad(fila.material_id) || '—' }}
                                            </span>
                                        </div>

                                        <div
                                            v-if="obtenerUltimaCompra(fila.material_id)"
                                            class="mt-1 flex items-center justify-between text-[11px] text-blue-600 dark:text-blue-400"
                                        >
                                            <span class="truncate" :title="obtenerUltimaCompra(fila.material_id)?.fecha ? `Última compra: ${obtenerUltimaCompra(fila.material_id)?.fecha}` : ''">
                                                Última compra: <b>{{ zilef_nuber_format(obtenerUltimaCompra(fila.material_id)?.cantidad, 2) }} {{ unidad(fila.material_id) }}</b>
                                            </span>
                                            <button
                                                v-if="Number(fila.cantidad) !== Number(obtenerUltimaCompra(fila.material_id)?.cantidad)"
                                                type="button"
                                                class="ml-1 shrink-0 font-medium text-blue-700 underline hover:text-blue-800 dark:text-blue-300 dark:hover:text-blue-200"
                                                @click="aplicarSugerencia(fila)"
                                            >
                                                Usar
                                            </button>
                                        </div>
                                        <div
                                            v-else-if="fila.material_id"
                                            class="mt-1 text-[11px] text-gray-400 dark:text-gray-500"
                                        >
                                            Sin compras previas
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums text-gray-700 dark:text-gray-300 align-top">
                                        <div>
                                            {{ zilef_nuber_format(existenciaActual(fila.material_id), 2) }}
                                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ unidad(fila.material_id) }}
                                            </span>
                                        </div>
                                        <div
                                            v-if="fila.material_id && Number(fila.cantidad) > 0"
                                            class="text-[11px] text-emerald-600 dark:text-emerald-400"
                                        >
                                            Quedará en {{ zilef_nuber_format(existenciaResultante(fila), 2) }}
                                            {{ unidad(fila.material_id) }}
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-right align-top">
                                        <button
                                            type="button"
                                            class="rounded-md px-2 py-1 text-xs font-semibold text-rose-600 transition hover:bg-rose-50 hover:text-rose-700 dark:text-rose-400 dark:hover:bg-rose-500/10"
                                            :aria-label="`Quitar material ${indice + 1}`"
                                            @click="quitarFila(indice)"
                                        >
                                            Quitar
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <p v-if="state.errorLocal" class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:border-rose-500/40 dark:bg-rose-500/10 dark:text-rose-300">
                    {{ state.errorLocal }}
                </p>

                <InputError v-for="mensaje in errores" :key="mensaje" :message="mensaje" />

                <div class="flex justify-end border-t border-gray-200 pt-3 dark:border-gray-700">
                    <SecondaryButton type="button" :disabled="form.processing" @click="emit('close')">
                        Cancelar
                    </SecondaryButton>
                    <PrimaryButton
                        type="submit"
                        class="ml-3"
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Registrando...' : 'Registrar compra' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    </section>
</template>
