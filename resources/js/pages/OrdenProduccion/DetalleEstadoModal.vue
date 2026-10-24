<script setup lang="ts">
import Modal from '@/components/Modal.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import { formatDate, zilef_nuber_format } from '@/global.ts';
import { computed } from 'vue';

const props = defineProps({
    show: Boolean,
    op: Object,
});

const emit = defineEmits(['close']);

const estado = computed(() => String((props.op as any)?.estado_nombre ?? ''));
const esPreparacion = computed(() => estado.value === 'Preparación');
const esEsperando = computed(() => estado.value === 'Esperando materiales');
const esRecepcion = computed(() => estado.value === 'Recibiendo');

const materiales = computed<any[]>(() => (props.op as any)?.materiales_detalle ?? []);
const faltantes = computed<any[]>(() => (props.op as any)?.materiales_faltantes ?? []);

const cantidad = (valor: any, decimales = 0) => {
    if (valor === null || valor === undefined || valor === '') return '—';
    const numero = Number(valor);
    if (!Number.isFinite(numero)) return '—';
    return zilef_nuber_format(numero, decimales, false);
};

// Agrupa las tallas distribuidas por producto, sumando cantidades repetidas.
const tallasPorProducto = computed(() => {
    const grupos = new Map<string, { producto_id: any; producto_nombre: string; tallas: Map<string, number>; total: number }>();

    for (const distribucion of ((props.op as any)?.distribuciones_detalle ?? [])) {
        const clave = String(distribucion.producto_id ?? 'sin-producto');
        if (!grupos.has(clave)) {
            grupos.set(clave, {
                producto_id: distribucion.producto_id,
                producto_nombre: distribucion.producto_nombre || 'Sin producto',
                tallas: new Map<string, number>(),
                total: 0,
            });
        }

        const grupo = grupos.get(clave)!;
        const talla = String(distribucion.talla ?? '—');
        const cantidadTalla = Number(distribucion.cantidad_planificada) || 0;
        grupo.tallas.set(talla, (grupo.tallas.get(talla) ?? 0) + cantidadTalla);
        grupo.total += cantidadTalla;
    }

    return Array.from(grupos.values()).map((grupo) => ({
        ...grupo,
        tallas: Array.from(grupo.tallas.entries()).map(([talla, cantidadTalla]) => ({
            talla,
            cantidad: cantidadTalla,
        })),
    }));
});

// Solo se muestran los pares que quedaron en reparación o no recuperables.
const recepciones = computed<any[]>(() =>
    ((props.op as any)?.recepciones_detalle ?? []).filter(
        (r: any) => Number(r.cantidad_en_reparacion) > 0 || Number(r.cantidad_no_recuperable) > 0
    )
);

const totalEnReparacion = computed(() =>
    recepciones.value.reduce((suma, r) => suma + (Number(r.cantidad_en_reparacion) || 0), 0)
);

const totalNoRecuperable = computed(() =>
    recepciones.value.reduce((suma, r) => suma + (Number(r.cantidad_no_recuperable) || 0), 0)
);

const fechaRecepcion = computed(() => {
    const fecha = (props.op as any)?.recepcion_desde;
    return fecha ? formatDate(fecha, 'conLaHora') : null;
});
</script>

<template>
    <Modal :show="props.show" @close="emit('close')" maxWidth="xl6" closeable>
        <div class="p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Detalle del estado
                        <span v-if="props.op?.numero_op" class="text-sm font-normal text-gray-500 dark:text-gray-400">
                            — {{ props.op.numero_op }}
                        </span>
                    </h2>
                    <span
                        class="mt-2 inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-semibold text-gray-700 dark:border-gray-700 dark:bg-gray-900/60 dark:text-gray-200"
                    >
                        {{ estado || 'Sin estado' }}
                    </span>
                </div>

                <SecondaryButton type="button" @click="emit('close')">
                    Cerrar
                </SecondaryButton>
            </div>

            <!-- Preparación: materiales necesarios y tallas por producto -->
            <template v-if="esPreparacion">
                <section class="mt-5">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Materiales necesarios</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Necesidad total calculada desde la ficha técnica de los productos de la OP.
                    </p>

                    <div v-if="materiales.length" class="mt-3 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[560px] text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200 bg-gray-50 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400">
                                        <th scope="col" class="px-3 py-2 font-semibold">Material</th>
                                        <th scope="col" class="w-24 px-3 py-2 font-semibold">Unidad</th>
                                        <th scope="col" class="w-32 px-3 py-2 text-right font-semibold">Se necesita</th>
                                        <th scope="col" class="w-32 px-3 py-2 text-right font-semibold">Disponible</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                                    <tr v-for="material in materiales" :key="material.material_id">
                                        <td class="px-3 py-2 text-sm text-gray-700 dark:text-gray-300">{{ material.nombre }}</td>
                                        <td class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">{{ material.unidad || '—' }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums font-medium text-gray-900 dark:text-gray-100">
                                            {{ cantidad(material.requerida, 2) }}
                                        </td>
                                        <td class="px-3 py-2 text-right tabular-nums text-gray-500 dark:text-gray-400">
                                            {{ cantidad(material.disponible, 2) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <p v-else class="mt-3 rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-4 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400">
                        La OP no tiene materiales de ficha técnica registrados.
                    </p>
                </section>

                <section class="mt-6">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Tallas por producto</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Cuántas tallas y pares se planificaron para cada producto.
                    </p>

                    <div v-if="tallasPorProducto.length" class="mt-3 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[640px] text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200 bg-gray-50 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400">
                                        <th scope="col" class="px-3 py-2 font-semibold">Producto</th>
                                        <th scope="col" class="px-3 py-2 font-semibold">Tallas</th>
                                        <th scope="col" class="w-24 px-3 py-2 text-right font-semibold">Pares</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                                    <tr v-for="grupo in tallasPorProducto" :key="grupo.producto_id ?? grupo.producto_nombre">
                                        <td class="px-3 py-2 text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {{ grupo.producto_nombre }}
                                        </td>
                                        <td class="px-3 py-2">
                                            <div class="flex flex-wrap gap-1.5">
                                                <span
                                                    v-for="talla in grupo.tallas"
                                                    :key="talla.talla"
                                                    class="inline-flex items-center gap-1 rounded-full border border-gray-200 bg-gray-50 px-2 py-0.5 text-[11px] font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-900/60 dark:text-gray-300"
                                                >
                                                    {{ talla.talla }}
                                                    <span class="tabular-nums text-gray-500 dark:text-gray-400">× {{ cantidad(talla.cantidad) }}</span>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-3 py-2 text-right tabular-nums font-semibold text-gray-900 dark:text-gray-100">
                                            {{ cantidad(grupo.total) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <p v-else class="mt-3 rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-4 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400">
                        La OP no tiene tallas distribuidas.
                    </p>
                </section>
            </template>

            <!-- Esperando materiales: solo los faltantes -->
            <section v-else-if="esEsperando" class="mt-5">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Materiales que hacen falta</h3>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Diferencia entre lo que necesita la OP y la existencia actual del material.
                </p>

                <div v-if="faltantes.length" class="mt-3 overflow-hidden rounded-lg border border-amber-200 dark:border-amber-500/40">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[640px] text-sm">
                            <thead>
                                <tr class="border-b border-amber-200 bg-amber-50 text-left text-[11px] font-semibold uppercase tracking-wider text-amber-800 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200">
                                    <th scope="col" class="px-3 py-2 font-semibold">Material</th>
                                    <th scope="col" class="w-24 px-3 py-2 font-semibold">Unidad</th>
                                    <th scope="col" class="w-28 px-3 py-2 text-right font-semibold">Necesita</th>
                                    <th scope="col" class="w-28 px-3 py-2 text-right font-semibold">Hay</th>
                                    <th scope="col" class="w-28 px-3 py-2 text-right font-semibold">Falta</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-amber-100 bg-white dark:divide-amber-500/20 dark:bg-transparent">
                                <tr v-for="material in faltantes" :key="material.material_id">
                                    <td class="px-3 py-2 text-sm text-gray-700 dark:text-gray-300">{{ material.nombre }}</td>
                                    <td class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">{{ material.unidad || '—' }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-gray-500 dark:text-gray-400">
                                        {{ cantidad(material.requerida, 2) }}
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums text-gray-500 dark:text-gray-400">
                                        {{ cantidad(material.disponible, 2) }}
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums font-semibold text-amber-700 dark:text-amber-300">
                                        {{ cantidad(material.faltante, 2) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <p v-else class="mt-3 rounded-lg border border-dashed border-emerald-300 bg-emerald-50 px-4 py-4 text-sm text-emerald-800 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-200">
                    No hay materiales faltantes: la existencia actual cubre la necesidad de la OP.
                </p>
            </section>

            <!-- Recibiendo: pares en reparación o no recuperables -->
            <template v-else-if="esRecepcion">
                <div class="mt-5 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-900/40">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">En recepción desde</p>
                        <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">
                            {{ fechaRecepcion || 'Sin registro' }}
                        </p>
                    </div>
                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-500/40 dark:bg-amber-500/10">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-amber-700 dark:text-amber-300">En reparación</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums text-amber-800 dark:text-amber-200">{{ cantidad(totalEnReparacion) }}</p>
                    </div>
                    <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 dark:border-rose-500/40 dark:bg-rose-500/10">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-rose-700 dark:text-rose-300">No recuperables</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums text-rose-800 dark:text-rose-200">{{ cantidad(totalNoRecuperable) }}</p>
                    </div>
                </div>

                <section class="mt-5">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Pares en reparación o no recuperables</h3>

                    <div v-if="recepciones.length" class="mt-3 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[820px] text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200 bg-gray-50 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400">
                                        <th scope="col" class="px-3 py-2 font-semibold">Producto</th>
                                        <th scope="col" class="w-24 px-3 py-2 font-semibold">Talla</th>
                                        <th scope="col" class="w-24 px-3 py-2 text-right font-semibold">Esperada</th>
                                        <th scope="col" class="w-24 px-3 py-2 text-right font-semibold">Aprobada</th>
                                        <th scope="col" class="w-28 px-3 py-2 text-right font-semibold">En reparación</th>
                                        <th scope="col" class="w-28 px-3 py-2 text-right font-semibold">No recuperable</th>
                                        <th scope="col" class="w-24 px-3 py-2 text-right font-semibold">Recibida</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                                    <tr v-for="recepcion in recepciones" :key="recepcion.id">
                                        <td class="px-3 py-2 text-sm text-gray-700 dark:text-gray-300">{{ recepcion.producto_nombre || '—' }}</td>
                                        <td class="px-3 py-2 text-sm font-medium text-gray-900 dark:text-gray-100">{{ recepcion.talla }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums text-gray-500 dark:text-gray-400">{{ cantidad(recepcion.cantidad_esperada) }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums text-gray-700 dark:text-gray-300">{{ cantidad(recepcion.cantidad_aprobada) }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums font-semibold text-amber-700 dark:text-amber-300">{{ cantidad(recepcion.cantidad_en_reparacion) }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums font-semibold text-rose-700 dark:text-rose-300">{{ cantidad(recepcion.cantidad_no_recuperable) }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums text-gray-700 dark:text-gray-300">{{ cantidad(recepcion.cantidad_recibida) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <p v-else class="mt-3 rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-4 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400">
                        No hay productos en reparación ni no recuperables registrados para esta OP.
                    </p>
                </section>
            </template>
        </div>
    </Modal>
</template>
