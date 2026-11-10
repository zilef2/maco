<script setup lang="ts">
import Modal from '@/components/Modal.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import { formatDate, zilef_nuber_format } from '@/global.ts';
import { computed } from 'vue';

const props = defineProps({
    show: Boolean,
    material: { type: Object as () => any, default: null },
    proyeccion: { type: Object as () => any, default: null },
});

const emit = defineEmits(['close']);

// Las fechas sin hora llegan como "YYYY-MM-DD"; en Colombia un Date directo
// mostraría el día anterior.
const fecha = (valor: any): string => {
    if (!valor) return '—';
    const texto = String(valor);
    const soloFecha = texto.match(/^(\d{4}-\d{2}-\d{2})(?:[T ]00:00:00(?:\.\d+)?Z?)?$/);
    return soloFecha ? formatDate(new Date(`${soloFecha[1]}T12:00:00`)) : formatDate(texto);
};

const cantidad = (valor: any, decimales = 2): string => {
    if (valor === null || valor === undefined || valor === '') return '—';
    const numero = Number(valor);
    if (!Number.isFinite(numero)) return '—';
    return zilef_nuber_format(numero, decimales, false);
};

const unidad = computed(() => String(props.proyeccion?.unidad ?? props.material?.unidad_inventario ?? '').trim());

const resumen = computed(() => ({
    fisico: Number(props.proyeccion?.fisico ?? props.material?.existencia_actual ?? 0),
    pendiente: Number(props.proyeccion?.pendiente ?? 0),
    comprometido: Number(props.proyeccion?.comprometido ?? 0),
    proyectado: Number(props.proyeccion?.proyectado ?? 0),
}));

const productores = computed<any[]>(() => {
    const filas = (props.proyeccion?.productores ?? []) as any[];
    return filas.filter((fila) => Number(fila?.pendiente) > 0 || Number(fila?.comprometido) > 0);
});

const consumidores = computed<any[]>(() => (props.proyeccion?.consumidores ?? []) as any[]);

const origenesTexto = (fila: any): string => {
    const origenes = (fila?.compromisos ?? []) as any[];
    if (!origenes.length) return '';

    return origenes
        .map((origen: any) => `${origen.numero_op ?? 'OP'} (${cantidad(origen.pendiente ?? origen.cantidad)})`)
        .join(', ');
};
</script>

<template>
    <Modal :show="props.show" @close="emit('close')" maxWidth="xl6" closeable>
        <div class="p-6">
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-200 pb-3 dark:border-gray-700">
                <div class="min-w-0">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Inventario proyectado</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ props.material?.nombre_base || props.proyeccion?.nombre || 'Material' }}
                        <span v-if="props.material?.codigo_material" class="text-xs">
                            · Código {{ props.material.codigo_material }}
                        </span>
                    </p>
                </div>
                <SecondaryButton type="button" @click="emit('close')">Cerrar</SecondaryButton>
            </div>

            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                Proyectado = físico + producción pendiente − compromisos con otras OP. Las OP se cubren en orden de llegada.
            </p>

            <div class="mt-4 grid grid-cols-2 gap-2.5 md:grid-cols-4">
                <div class="rounded-lg border border-gray-200 bg-gray-50/60 p-3 dark:border-gray-700 dark:bg-gray-800/40">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">Físico</p>
                    <p class="mt-1 text-xl font-semibold tabular-nums text-gray-900 dark:text-gray-100">
                        {{ cantidad(resumen.fisico) }}
                    </p>
                    <p class="text-[11px] text-gray-500 dark:text-gray-400">{{ unidad || 'unidades' }}</p>
                </div>
                <div class="rounded-lg border border-sky-200/70 bg-sky-50/50 p-3 dark:border-sky-500/20 dark:bg-sky-500/5">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.08em] text-sky-700 dark:text-sky-300">En producción</p>
                    <p class="mt-1 text-xl font-semibold tabular-nums text-sky-800 dark:text-sky-300">{{ cantidad(resumen.pendiente) }}</p>
                    <p class="text-[11px] text-sky-700/80 dark:text-sky-300/80">pendiente de recibir</p>
                </div>
                <div class="rounded-lg border border-amber-200/70 bg-amber-50/50 p-3 dark:border-amber-500/20 dark:bg-amber-500/5">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.08em] text-amber-700 dark:text-amber-300">Comprometido</p>
                    <p class="mt-1 text-xl font-semibold tabular-nums text-amber-800 dark:text-amber-300">{{ cantidad(resumen.comprometido) }}</p>
                    <p class="text-[11px] text-amber-700/80 dark:text-amber-300/80">reservado a otras OP</p>
                </div>
                <div
                    class="rounded-lg border p-3"
                    :class="resumen.proyectado < 0
                        ? 'border-rose-200/70 bg-rose-50/50 dark:border-rose-500/20 dark:bg-rose-500/5'
                        : 'border-emerald-200/70 bg-emerald-50/50 dark:border-emerald-500/20 dark:bg-emerald-500/5'"
                >
                    <p
                        class="text-[10px] font-semibold uppercase tracking-[0.08em]"
                        :class="resumen.proyectado < 0 ? 'text-rose-700 dark:text-rose-300' : 'text-emerald-700 dark:text-emerald-300'"
                    >
                        Proyectado
                    </p>
                    <p
                        class="mt-1 text-xl font-semibold tabular-nums"
                        :class="resumen.proyectado < 0 ? 'text-rose-800 dark:text-rose-300' : 'text-emerald-800 dark:text-emerald-300'"
                    >
                        {{ cantidad(resumen.proyectado) }}
                    </p>
                    <p
                        class="text-[11px]"
                        :class="resumen.proyectado < 0 ? 'text-rose-700/80 dark:text-rose-300/80' : 'text-emerald-700/80 dark:text-emerald-300/80'"
                    >
                        disponible tras compromisos
                    </p>
                </div>
            </div>

            <!-- OP que producirán este material -->
            <section class="mt-6">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">OP que lo producirán</h3>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Lo planificado que aún no ingresa a inventario. «Comprometido» ya está reservado para otras OP.
                </p>

                <div v-if="productores.length" class="mt-3 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[760px] text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 bg-gray-50 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400">
                                    <th scope="col" class="px-3 py-2 font-semibold">OP</th>
                                    <th scope="col" class="w-36 px-3 py-2 font-semibold">Estado</th>
                                    <th scope="col" class="w-28 px-3 py-2 font-semibold">Fecha</th>
                                    <th scope="col" class="w-28 px-3 py-2 text-right font-semibold">Planificado</th>
                                    <th scope="col" class="w-28 px-3 py-2 text-right font-semibold">Recibido</th>
                                    <th scope="col" class="w-28 px-3 py-2 text-right font-semibold">Pendiente</th>
                                    <th scope="col" class="w-32 px-3 py-2 text-right font-semibold">Comprometido</th>
                                    <th scope="col" class="w-24 px-3 py-2 text-right font-semibold">Libre</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                                <tr v-for="fila in productores" :key="fila.op_id">
                                    <td class="px-3 py-2 font-medium text-gray-900 dark:text-gray-100">{{ fila.numero_op || `OP #${fila.op_id}` }}</td>
                                    <td class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400">{{ fila.estado || '—' }}</td>
                                    <td class="px-3 py-2 text-xs tabular-nums text-gray-500 dark:text-gray-400">{{ fecha(fila.fecha) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-gray-500 dark:text-gray-400">{{ cantidad(fila.planificado) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-gray-500 dark:text-gray-400">{{ cantidad(fila.recibido) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums font-medium text-gray-900 dark:text-gray-100">{{ cantidad(fila.pendiente) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-amber-700 dark:text-amber-300">{{ cantidad(fila.comprometido) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums font-semibold text-emerald-700 dark:text-emerald-300">{{ cantidad(fila.libre) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <p v-else class="mt-3 rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-3 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400">
                    Ninguna OP tiene producción pendiente de este material.
                </p>
            </section>

            <!-- OP que lo consumen -->
            <section class="mt-6">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">OP que lo consumirán</h3>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Necesidad según la ficha y cuánto queda disponible para cada OP después de los compromisos previos.
                </p>

                <div v-if="consumidores.length" class="mt-3 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[940px] text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 bg-gray-50 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400">
                                    <th scope="col" class="px-3 py-2 font-semibold">OP</th>
                                    <th scope="col" class="w-36 px-3 py-2 font-semibold">Estado</th>
                                    <th scope="col" class="w-28 px-3 py-2 font-semibold">Fecha</th>
                                    <th scope="col" class="w-28 px-3 py-2 text-right font-semibold">Necesita</th>
                                    <th scope="col" class="w-32 px-3 py-2 text-right font-semibold">Comprometido</th>
                                    <th scope="col" class="w-32 px-3 py-2 text-right font-semibold">Disponible</th>
                                    <th scope="col" class="w-32 px-3 py-2 text-right font-semibold">Faltante</th>
                                    <th scope="col" class="min-w-[180px] px-3 py-2 font-semibold">De dónde</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                                <tr v-for="fila in consumidores" :key="fila.op_id">
                                    <td class="px-3 py-2 font-medium text-gray-900 dark:text-gray-100">{{ fila.numero_op || `OP #${fila.op_id}` }}</td>
                                    <td class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400">{{ fila.estado || '—' }}</td>
                                    <td class="px-3 py-2 text-xs tabular-nums text-gray-500 dark:text-gray-400">{{ fecha(fila.fecha) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums font-medium text-gray-900 dark:text-gray-100">{{ cantidad(fila.requerida) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-amber-700 dark:text-amber-300">{{ cantidad(fila.comprometido) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-gray-700 dark:text-gray-300">{{ cantidad(fila.disponible_proyectado) }}</td>
                                    <td
                                        class="px-3 py-2 text-right tabular-nums font-semibold"
                                        :class="Number(fila.faltante_proyectado) > 0 ? 'text-rose-700 dark:text-rose-300' : 'text-emerald-700 dark:text-emerald-300'"
                                    >
                                        {{ Number(fila.faltante_proyectado) > 0 ? cantidad(fila.faltante_proyectado) : '0' }}
                                    </td>
                                    <td
                                        class="max-w-[240px] truncate px-3 py-2 text-xs text-gray-500 dark:text-gray-400"
                                        :title="origenesTexto(fila) || 'Sin compromisos de otras OP'"
                                    >
                                        {{ origenesTexto(fila) || 'Sin compromisos' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <p v-else class="mt-3 rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-3 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400">
                    Ninguna OP abierta consume este material.
                </p>
            </section>
        </div>
    </Modal>
</template>
