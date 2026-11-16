<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { ArrowLeftIcon, PrinterIcon } from '@heroicons/vue/24/solid';

import { formatdinero, zilef_nuber_format } from '@/global.ts';

type OrdenOp = {
    numero_op: string;
    requerido: number;
};

type OrdenFila = {
    material_id: number;
    codigo: string | null;
    nombre: string | null;
    categoria: string | null;
    codigo_categoria: string | null;
    unidad: string | null;
    requerido: number;
    existencia: number;
    faltante: number;
    precio: number | null;
    importe: number | null;
    ordenes: OrdenOp[];
};

const props = defineProps({
    orden: { type: Object as () => any, default: () => ({ filas: [] }) },
});

const filas = computed<OrdenFila[]>(() => (props.orden?.filas ?? []) as OrdenFila[]);

const cantidad = (valor: number | null | undefined) => {
    const numero = Number(valor ?? 0);

    return zilef_nuber_format(numero, numero % 1 ? 2 : 0);
};

const conPrecios = ref(false);

const hoy = new Intl.DateTimeFormat('es-AR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
}).format(new Date());

const nombreCategoria = (fila: OrdenFila): string =>
    String(fila.categoria ?? fila.codigo_categoria ?? '').trim() || 'Sin categoría';

/** Un bloque por categoría, con lo más urgente arriba dentro de cada bloque. */
const grupos = computed(() => {
    const mapa = new Map<string, OrdenFila[]>();

    [...filas.value]
        .sort((a, b) => Number(b.faltante) - Number(a.faltante))
        .forEach((fila) => {
            const clave = nombreCategoria(fila);
            const items = mapa.get(clave);
            if (items) {
                items.push(fila);
            } else {
                mapa.set(clave, [fila]);
            }
        });

    return [...mapa].map(([categoria, items]) => ({ categoria, items }));
});

const columnas = computed(() => (conPrecios.value ? 5 : 3));

const ordenes = computed(() => {
    const numeros = new Set<string>();
    filas.value.forEach((fila) => (fila.ordenes ?? []).forEach((op) => numeros.add(op.numero_op)));

    return [...numeros].sort();
});

const total = computed(() =>
    Math.round(filas.value.reduce((suma, fila) => suma + Number(fila.importe ?? 0), 0) * 100) / 100,
);

const sinPrecio = computed(() => filas.value.filter((fila) => fila.precio === null).length);

const imprimir = () => window.print();
</script>

<template>

    <Head title="Orden de compra (imprimible)" />

    <div class="min-h-screen bg-white px-5 py-6 text-neutral-900 sm:px-8 print:px-0">
        <div class="no-print flex flex-wrap items-center gap-2 border-b border-neutral-200 pb-4">
            <button type="button" @click="imprimir"
                class="inline-flex h-11 items-center gap-2 rounded-md bg-neutral-900 px-4 text-sm font-semibold text-white transition hover:bg-neutral-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-500 focus-visible:ring-offset-2">
                <PrinterIcon class="h-4 w-4" />
                Imprimir o guardar como PDF
            </button>
            <Link :href="route('Material.orden-compra')"
                class="inline-flex h-11 items-center gap-1.5 rounded-md border border-neutral-300 px-4 text-sm font-semibold text-neutral-700 transition hover:bg-neutral-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-500 focus-visible:ring-offset-2">
                <ArrowLeftIcon class="h-4 w-4" />
                Volver a la orden
            </Link>
            <label v-if="filas.length"
                class="ml-auto inline-flex h-11 cursor-pointer items-center gap-2 rounded-md border border-neutral-300 px-3 text-sm font-medium text-neutral-700 transition hover:bg-neutral-100">
                <input v-model="conPrecios" type="checkbox"
                    class="h-4 w-4 rounded border-neutral-400 text-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-500" />
                Con precios
            </label>
        </div>

        <header class="flex flex-wrap items-end justify-between gap-x-6 gap-y-2 border-b-2 border-neutral-900 py-4">
            <div class="min-w-0">
                <h1 class="text-2xl font-semibold tracking-tight">Lista de compra</h1>
                <p class="mt-1 text-sm text-neutral-600">
                    {{ ordenes.length }} órdenes de producción abiertas · {{ filas.length }} material(es)
                </p>
            </div>
            <p class="text-sm tabular-nums text-neutral-600">
                impresa el {{ hoy }}
            </p>
        </header>

        <p v-if="!filas.length" class="py-10 text-center text-sm text-neutral-600">
            No hay nada que comprar: la existencia actual cubre el kit de todas las órdenes abiertas.
        </p>

        <template v-else>
            <p class="mt-3 text-xs leading-relaxed text-neutral-500">
                La cantidad es lo que falta para cubrir los kits, ya descontando lo que hay en bodega.
                Marcá la casilla en el papel a medida que conseguís cada material.
            </p>

            <table class="mt-4 w-full border-collapse text-sm">
                <thead>
                    <tr class="border-b border-neutral-300 text-left text-[11px] uppercase tracking-[0.08em] text-neutral-500">
                        <th scope="col" class="w-10 py-2 font-semibold"></th>
                        <th scope="col" class="py-2 font-semibold">Material</th>
                        <th scope="col" class="py-2 text-right font-semibold">Comprar</th>
                        <th v-if="conPrecios" scope="col" class="py-2 text-right font-semibold">Precio</th>
                        <th v-if="conPrecios" scope="col" class="py-2 text-right font-semibold">Importe</th>
                    </tr>
                </thead>

                <tbody v-for="grupo in grupos" :key="grupo.categoria">
                    <tr class="border-y border-neutral-200 bg-neutral-100">
                        <th :colspan="columnas" scope="colgroup"
                            class="px-2 py-1.5 text-left text-[11px] font-semibold uppercase tracking-[0.08em] text-neutral-700">
                            {{ grupo.categoria }}
                            <span class="font-normal normal-case tracking-normal text-neutral-500">
                                · {{ grupo.items.length }} material(es)
                            </span>
                        </th>
                    </tr>

                    <tr v-for="fila in grupo.items" :key="fila.material_id" class="border-b border-neutral-200">
                        <td class="py-2.5 pl-2">
                            <span class="block h-4 w-4 rounded-sm border-[1.5px] border-neutral-500"></span>
                        </td>
                        <td class="py-2.5 pr-3">
                            <span class="block font-medium leading-tight">{{ fila.nombre || '—' }}</span>
                            <span class="mt-0.5 block text-xs tabular-nums text-neutral-500">
                                {{ fila.codigo || '—' }}
                            </span>
                        </td>
                        <td class="py-2.5 text-right">
                            <span class="text-base font-semibold tabular-nums">{{ cantidad(fila.faltante) }}</span>
                            <span class="ml-1 text-xs uppercase text-neutral-500">{{ fila.unidad || 'unid.' }}</span>
                        </td>
                        <td v-if="conPrecios" class="py-2.5 text-right tabular-nums">
                            {{ fila.precio === null ? '—' : formatdinero(fila.precio) }}
                        </td>
                        <td v-if="conPrecios" class="py-2.5 text-right tabular-nums">
                            {{ fila.importe === null ? '—' : formatdinero(fila.importe) }}
                        </td>
                    </tr>
                </tbody>

                <tfoot v-if="conPrecios">
                    <tr>
                        <th :colspan="columnas - 1" scope="row" class="py-2.5 text-right font-semibold">
                            Total estimado
                        </th>
                        <td class="py-2.5 text-right text-base font-semibold tabular-nums">
                            {{ formatdinero(total) }}
                        </td>
                    </tr>
                </tfoot>
            </table>

            <p v-if="conPrecios && sinPrecio" class="mt-2 text-xs text-neutral-500">
                {{ sinPrecio }} material(es) todavía sin precio: no entran al total.
            </p>

            <p class="no-print mt-6 text-xs text-neutral-500">
                Los precios son el último valor registrado de cada material, solo como referencia.
            </p>
        </template>
    </div>

</template>

<style scoped>
@media print {
    .no-print {
        display: none !important;
    }

    table {
        font-size: 11pt;
    }

    tbody tr.bg-neutral-100 {
        background: #f2f2f2 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    tr {
        break-inside: avoid;
    }
}
</style>
