<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import Modal from '@/components/Modal.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import { zilef_nuber_format } from '@/global.ts';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import vSelect from 'vue-select';
import 'vue-select/dist/vue-select.css';

const props = defineProps({
    show: Boolean,
    materiales: {
        type: Array as () => any[],
        default: () => [],
    },
});

const emit = defineEmits(['close']);

const filaVacia = () => ({ material_id: '', cantidad: '' });

const form = useForm({
    materiales: [filaVacia()] as any[],
});

const errorLocal = ref('');

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
    errorLocal.value = '';
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
    errorLocal.value = '';

    if (compraIncompleta.value) {
        errorLocal.value = 'Completa el material y una cantidad mayor que cero.';
        return;
    }

    const filas = filasValidas.value;
    if (!filas.length) {
        errorLocal.value = 'Agrega al menos un material con cantidad.';
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
                        Registra uno o varios materiales comprados. La unidad se toma automáticamente
                        del material y el ingreso queda en su historial de movimientos.
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
                        <table class="w-full min-w-[720px] text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 bg-gray-50 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400">
                                    <th scope="col" class="px-3 py-2 font-semibold">Material</th>
                                    <th scope="col" class="w-40 px-3 py-2 font-semibold">Cantidad</th>
                                    <th scope="col" class="w-44 px-3 py-2 text-right font-semibold">Existencia actual</th>
                                    <th scope="col" class="w-16 px-3 py-2 font-semibold"><span class="sr-only">Quitar</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                                <tr v-for="(fila, indice) in form.materiales" :key="indice">
                                    <td class="px-3 py-2">
                                        <v-select
                                            :options="opciones"
                                            label="name"
                                            :reduce="(item: any) => item.id"
                                            v-model="fila.material_id"
                                            placeholder="Seleccione el material..."
                                            append-to-body
                                            :aria-label="`Material comprado ${indice + 1}`"
                                        />
                                    </td>
                                    <td class="px-3 py-2">
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
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums text-gray-700 dark:text-gray-300">
                                        <div>
                                            {{ zilef_nuber_format(existenciaActual(fila.material_id), 2) }}
                                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ unidad(fila.material_id) }}
                                            </span>
                                        </div>
                                        <div v-if="fila.material_id && Number(fila.cantidad) > 0"
                                            class="text-[11px] text-emerald-600 dark:text-emerald-400">
                                            Quedará en {{ zilef_nuber_format(existenciaResultante(fila), 2) }}
                                            {{ unidad(fila.material_id) }}
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-right">
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

                <p v-if="errorLocal" class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:border-rose-500/40 dark:bg-rose-500/10 dark:text-rose-300">
                    {{ errorLocal }}
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
