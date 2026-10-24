<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import Modal from '@/components/Modal.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { CheckBadgeIcon, ExclamationTriangleIcon, XMarkIcon } from '@heroicons/vue/24/solid';

const props = defineProps({
    show: Boolean,
    title: String,
    KitProcesoa: Object,
    proveedores: { type: Array, default: () => [] },
    estadosRequeridos: { type: Array, default: () => [] },
});

const emit = defineEmits(['close']);

const cargando = ref(false);
const errorCarga = ref<string | null>(null);
const orden = ref<any>(null);

const form = useForm({
    filas: [] as any[],
});

const opId = computed(() => (props.KitProcesoa as any)?.orden_produccion_id ?? orden.value?.id ?? null);
const opNumero = computed(
    () => orden.value?.numero_op || (props.KitProcesoa as any)?.orden_produccion_numero || 'la OP',
);

const formatear = (valor: any) => {
    if (valor === null || valor === undefined || valor === '') return '—';
    return Number(valor).toFixed(2);
};

const estaListo = (fila: any) =>
    String(fila.estado_requerido ?? '').trim() !== '' && Boolean(fila.proveedor_id);

const total = computed(() => form.filas.length);
const listos = computed(() => form.filas.filter((fila: any) => estaListo(fila)).length);
const completo = computed(() => total.value > 0 && listos.value === total.value);
const progreso = computed(() => (total.value > 0 ? Math.round((listos.value / total.value) * 100) : 0));

const faltanDatos = computed(() => form.filas.some((fila: any) => !estaListo(fila)));

const cargar = async () => {
    if (!opId.value) {
        errorCarga.value = 'No se pudo identificar la orden de producción del kit.';
        return;
    }

    cargando.value = true;
    errorCarga.value = null;

    try {
        const respuesta = await fetch(route('KitProceso.por-orden', { id: opId.value }), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
            credentials: 'same-origin',
        });

        if (!respuesta.ok) {
            throw new Error('No se pudo cargar la lista de kits de la orden.');
        }

        const payload = await respuesta.json();
        orden.value = payload.orden ?? null;

        form.filas = (payload.kits ?? []).map((kit: any) => ({
            id: kit.id,
            material_codigo: kit.material_codigo,
            material_nombre: kit.material_nombre,
            unidad: kit.unidad,
            cantidad_requerida: kit.cantidad_requerida,
            cantidad_disponible: kit.cantidad_disponible,
            estado_requerido: kit.estado_requerido ?? '',
            proveedor_id: kit.proveedor_id ?? null,
            observaciones: kit.observaciones ?? '',
        }));

        form.clearErrors();
    } catch (error: any) {
        errorCarga.value = error?.message ?? 'Error al cargar los kits.';
    } finally {
        cargando.value = false;
    }
};

watch(
    () => props.show,
    (visible) => {
        if (visible) {
            cargar();
        } else {
            form.reset();
            form.clearErrors();
            orden.value = null;
            errorCarga.value = null;
        }
    },
);

watch(
    () => props.KitProcesoa,
    () => {
        if (props.show) {
            cargar();
        }
    },
);

const update = () => {
    if (!opId.value) return;

    form.put(route('KitProceso.update-por-orden', { id: opId.value }), {
        preserveScroll: true,
        onSuccess: () => {
            emit('close');
        },
        onError: () => null,
    });
};
</script>

<template>
    <section class="space-y-6">
        <Modal :show="props.show" @close="emit('close')" :maxWidth="'xl6'">
            <form class="p-6" @submit.prevent="update">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                Completar kits de {{ opNumero }}
                            </h2>
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide"
                                :class="completo
                                    ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300'
                                    : 'bg-amber-100 text-amber-800 dark:bg-amber-400/15 dark:text-amber-200'">
                                {{ listos }} / {{ total }} listos
                            </span>
                        </div>
                        <p class="mt-1 max-w-2xl text-sm text-gray-500 dark:text-gray-400">
                            Esta es la lista completa de materiales de la OP. Completa el estado requerido
                            y el proveedor de cada fila.
                        </p>
                    </div>
                    <button type="button" @click="emit('close')"
                        class="rounded-md p-1.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                        aria-label="Cerrar">
                        <XMarkIcon class="h-5 w-5" />
                    </button>
                </div>

                <div class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                    <div class="h-full rounded-full transition-all duration-300"
                        :class="completo ? 'bg-emerald-500' : 'bg-amber-400'" :style="{ width: `${progreso}%` }" />
                </div>

                <div v-if="cargando" class="flex items-center justify-center gap-3 py-16 text-sm text-gray-500 dark:text-gray-400">
                    <span
                        class="h-5 w-5 animate-spin rounded-full border-2 border-gray-300 border-t-indigo-600 dark:border-gray-600 dark:border-t-indigo-400" />
                    Cargando los materiales de la OP…
                </div>

                <div v-else-if="errorCarga"
                    class="mt-6 flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300">
                    <ExclamationTriangleIcon class="mt-0.5 h-4 w-4 shrink-0" />
                    <div>
                        <p class="font-semibold">No se pudo abrir el kit</p>
                        <p>{{ errorCarga }}</p>
                        <button type="button" @click="cargar"
                            class="mt-2 font-semibold underline underline-offset-2">
                            Intentar de nuevo
                        </button>
                    </div>
                </div>

                <div v-else-if="form.filas.length === 0"
                    class="mt-6 rounded-xl border border-dashed border-gray-300 px-6 py-12 text-center text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400">
                    Esta orden no tiene materiales de kit registrados.
                </div>

                <div v-else class="mt-5 overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                    <table class="w-full min-w-[900px] text-sm">
                        <thead
                            class="bg-gray-50/80 text-[11px] font-semibold uppercase tracking-[0.08em] text-gray-500 dark:bg-gray-900/40 dark:text-gray-400">
                            <tr class="text-left">
                                <th class="px-3 py-3">Material</th>
                                <th class="px-3 py-3 text-right">Requerida</th>
                                <th class="px-3 py-3 text-right">Disponible</th>
                                <th class="bg-amber-50/70 px-3 py-3 dark:bg-amber-500/[0.06]">
                                    Estado requerido <span class="text-amber-500">*</span>
                                </th>
                                <th class="bg-amber-50/70 px-3 py-3 dark:bg-amber-500/[0.06]">
                                    Proveedor <span class="text-amber-500">*</span>
                                </th>
                                <th class="px-3 py-3 text-center">Listo</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr v-for="(fila, index) in form.filas" :key="fila.id"
                                class="border-t border-gray-100 align-middle dark:border-gray-700/60">
                                <td class="px-3 py-2.5">
                                    <div class="min-w-[11rem] max-w-[16rem]">
                                        <p class="font-medium text-gray-900 dark:text-gray-100">
                                            {{ fila.material_nombre || '—' }}
                                        </p>
                                        <p v-if="fila.material_codigo" class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                            {{ fila.material_codigo }}
                                        </p>
                                    </div>
                                </td>

                                <td class="px-3 py-2.5 text-right tabular-nums whitespace-nowrap text-gray-700 dark:text-gray-200">
                                    {{ formatear(fila.cantidad_requerida) }}
                                    <span v-if="fila.unidad" class="ml-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ fila.unidad }}
                                    </span>
                                </td>

                                <td class="px-3 py-2.5 text-right tabular-nums whitespace-nowrap">
                                    <span class="text-gray-700 dark:text-gray-200">{{ formatear(fila.cantidad_disponible) }}</span>
                                    <p class="text-[10px] font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                        Inventario
                                    </p>
                                </td>

                                <td class="bg-amber-50/30 px-3 py-2.5 dark:bg-amber-500/[0.03]">
                                    <input v-model="fila.estado_requerido" type="text"
                                        list="kit-estados-requeridos" autocomplete="off"
                                        class="w-full min-w-[9rem] rounded-md border-gray-300 bg-white py-1.5 text-sm shadow-sm placeholder:text-gray-400 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:placeholder:text-gray-500"
                                        placeholder="Ej. Materia prima" />
                                    <InputError class="mt-1" :message="form.errors[`filas.${index}.estado_requerido`]" />
                                </td>

                                <td class="bg-amber-50/30 px-3 py-2.5 dark:bg-amber-500/[0.03]">
                                    <select v-model="fila.proveedor_id"
                                        class="w-full min-w-[10rem] rounded-md border-gray-300 bg-white py-1.5 text-sm shadow-sm focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
                                        <option :value="null">Selecciona…</option>
                                        <option v-for="proveedor in props.proveedores" :key="proveedor.id"
                                            :value="proveedor.id">
                                            {{ proveedor.nombre }}
                                        </option>
                                    </select>
                                    <InputError class="mt-1" :message="form.errors[`filas.${index}.proveedor_id`]" />
                                </td>

                                <td class="px-3 py-2.5 text-center">
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide"
                                        :class="estaListo(fila)
                                            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300'
                                            : 'bg-gray-100 text-gray-500 dark:bg-gray-700/50 dark:text-gray-400'">
                                        <CheckBadgeIcon v-if="estaListo(fila)" class="h-3.5 w-3.5" />
                                        {{ estaListo(fila) ? 'Listo' : 'Pendiente' }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <datalist id="kit-estados-requeridos">
                    <option v-for="estado in props.estadosRequeridos" :key="estado" :value="estado" />
                </datalist>

                <div v-if="props.proveedores.length === 0"
                    class="mt-4 rounded-lg border border-amber-200 bg-amber-50/70 p-3 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
                    No hay proveedores registrados.
                    <Link :href="route('Proveedor.index')" class="font-semibold underline underline-offset-2">
                        Crear un proveedor
                    </Link>
                    antes de completar los kits.
                </div>

                <div class="mt-5 flex flex-col gap-3 rounded-lg bg-gray-50 px-4 py-3 text-xs text-gray-500 dark:bg-gray-900/30 dark:text-gray-400 sm:flex-row sm:items-center sm:justify-between">
                    <p class="max-w-2xl">
                        <b class="text-gray-700 dark:text-gray-200">Disponible</b> es la existencia del material en inventario.
                        El kit queda <b class="text-gray-700 dark:text-gray-200">listo</b> cuando la fila tiene
                        estado requerido y proveedor.
                    </p>
                    <p v-if="faltanDatos" class="shrink-0 font-semibold text-amber-700 dark:text-amber-300">
                        Completa estado y proveedor en todas las filas.
                    </p>
                </div>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton type="button" :disabled="form.processing" @click="emit('close')">
                        Cancelar
                    </SecondaryButton>
                    <PrimaryButton class="ml-3" :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing || form.filas.length === 0 || faltanDatos">
                        {{ form.processing ? 'Guardando…' : 'Guardar cambios' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    </section>
</template>
