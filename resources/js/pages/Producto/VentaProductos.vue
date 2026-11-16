<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import InputLabel from '@/components/InputLabel.vue';
import Modal from '@/components/Modal.vue';
import MoneyInput from '@/components/MoneyInput.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';
import vSelect from 'vue-select';
import 'vue-select/dist/vue-select.css';

const props = withDefaults(
    defineProps<{
        show: boolean;
        productos?: any[];
    }>(),
    {
        show: false,
        productos: () => [],
    }
);

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'saved'): void;
}>();

type TallaDisponible = { talla: string; disponible: number };

type ProductoVendible = {
    id: number;
    name: string;
    precio: number;
    tallas: TallaDisponible[];
};

const filaVacia = () => ({
    producto_id: '' as string | number,
    talla: '',
    cantidad: '',
    precio_unitario: '' as string | number,
});

const form = useForm({
    forma_pago: 'contado',
    forma_pago_detalle: '',
    descuento_tipo: 'monto',
    descuento_valor: '' as string | number,
    impuesto_tipo: 'monto',
    impuesto_valor: '' as string | number,
    observaciones: '',
    detalles: [filaVacia()] as any[],
});

const state = reactive({
    errorLocal: '',
    intentoEnvio: false,
});

/** Solo entran los productos con existencia recibida mayor a cero. */
const catalogo = computed<ProductoVendible[]>(() =>
    (props.productos ?? [])
        .map((producto: any) => {
            const filas = producto?.distribuciones_por_tallas ?? producto?.distribucionesPorTallas ?? [];
            const porTalla = new Map<string, number>();

            (Array.isArray(filas) ? filas : []).forEach((fila: any) => {
                const talla = String(fila?.talla ?? '').trim();
                const disponible = Number(fila?.cantidad_recibida_buena ?? 0);
                if (!talla || !Number.isFinite(disponible) || disponible <= 0) return;
                porTalla.set(talla, (porTalla.get(talla) ?? 0) + disponible);
            });

            return {
                id: Number(producto.id),
                name: [producto.Codigo_interno, producto.Titulo_Shopify].filter(Boolean).join(' — ')
                    || 'Producto #' + producto.id,
                precio: Number(producto.precio_venta_producto ?? 0) || 0,
                tallas: Array.from(porTalla, ([talla, disponible]) => ({ talla, disponible }))
                    .sort((a, b) => a.talla.localeCompare(b.talla, 'es', { numeric: true })),
            };
        })
        .filter((producto) => producto.tallas.length > 0)
        .sort((a, b) => a.name.localeCompare(b.name, 'es', { numeric: true }))
);

const productoDe = (productoId: any): ProductoVendible | undefined =>
    catalogo.value.find((producto) => producto.id === Number(productoId));

const tallasDe = (productoId: any): TallaDisponible[] => productoDe(productoId)?.tallas ?? [];

const disponibleDe = (productoId: any, talla: string): number =>
    tallasDe(productoId).find((opcion) => opcion.talla === talla)?.disponible ?? 0;

const tieneDatos = (fila: any): boolean =>
    [fila.producto_id, fila.talla, fila.cantidad, fila.precio_unitario]
        .some((valor) => String(valor ?? '').trim() !== '');

/** Cantidad del mismo producto+talla repartida en las demás filas. */
const vendidoEnOtrasFilas = (indice: number, fila: any): number =>
    form.detalles.reduce((suma: number, otra: any, i: number) => {
        if (i === indice) return suma;
        const mismaTalla = Number(otra.producto_id) === Number(fila.producto_id)
            && String(otra.talla ?? '') === String(fila.talla ?? '');
        return mismaTalla ? suma + (Number(otra.cantidad) || 0) : suma;
    }, 0);

const disponibleParaFila = (fila: any, indice: number): number =>
    Math.max(0, disponibleDe(fila.producto_id, String(fila.talla ?? '')) - vendidoEnOtrasFilas(indice, fila));

/** Impide digitar una cantidad mayor a la existencia libre de la talla. */
const limitarCantidad = (fila: any, indice: number, evento: Event) => {
    if (!fila.talla) return;

    const input = evento.target as HTMLInputElement;
    const entero = Math.floor(Number(input.value));
    if (!Number.isFinite(entero)) return;

    const disponible = disponibleParaFila(fila, indice);
    if (entero > disponible) {
        fila.cantidad = String(disponible);
        input.value = String(disponible);
    }
};

const numero = (valor: any): number => {
    const limpio = String(valor ?? '').replace(/[^\d.-]/g, '');
    const convertido = Number(limpio);
    return Number.isFinite(convertido) ? convertido : 0;
};

const redondear = (valor: number): number => Math.round(valor * 100) / 100;

const importeFila = (fila: any): number => {
    const cantidad = Math.floor(numero(fila.cantidad));
    const precio = numero(fila.precio_unitario);
    return cantidad > 0 && precio > 0 ? redondear(cantidad * precio) : 0;
};

const cantidadFila = (fila: any): number => Math.max(0, Math.floor(numero(fila.cantidad)));

const totalVendidos = computed(() =>
    form.detalles.reduce((suma: number, fila: any) => suma + cantidadFila(fila), 0)
);

// Suma de lo que quedará en cada talla, igual al «Quedarán» de cada fila.
const totalDisponibleRestante = computed(() =>
    form.detalles.reduce(
        (suma: number, fila: any, indice: number) =>
            fila.talla ? suma + Math.max(0, disponibleParaFila(fila, indice) - cantidadFila(fila)) : suma,
        0
    )
);

const totalPreciosUnitarios = computed(() =>
    redondear(
        form.detalles.reduce(
            (suma: number, fila: any) => suma + Math.max(0, numero(fila.precio_unitario)),
            0
        )
    )
);

const subtotal = computed(() =>
    redondear(form.detalles.reduce((suma: number, fila: any) => suma + importeFila(fila), 0))
);

const descuentoMonto = computed(() => {
    const valor = numero(form.descuento_valor);
    if (form.descuento_tipo === 'porcentaje') {
        return redondear(subtotal.value * Math.min(Math.max(valor, 0), 100) / 100);
    }
    return Math.min(Math.max(valor, 0), subtotal.value);
});

const impuestoMonto = computed(() => {
    const valor = numero(form.impuesto_valor);
    if (form.impuesto_tipo === 'porcentaje') {
        return redondear(subtotal.value * Math.min(Math.max(valor, 0), 100) / 100);
    }
    return Math.max(valor, 0);
});

const total = computed(() =>
    Math.max(0, redondear(subtotal.value - descuentoMonto.value + impuestoMonto.value))
);

const formatearMonto = (valor: number): string =>
    '$' + (Number(valor) || 0).toLocaleString('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: 2 });

const fechaVenta = computed(() =>
    new Date().toLocaleDateString('es-CO', { day: '2-digit', month: 'long', year: 'numeric' })
);

const errorFila = (fila: any, indice: number): string | null => {
    if (!fila.producto_id) return 'Seleccione el producto.';

    if (!String(fila.talla ?? '').trim()) return 'Seleccione la talla.';

    const cantidad = Number(fila.cantidad);
    if (!Number.isInteger(cantidad) || cantidad <= 0) {
        return 'La cantidad debe ser un número entero mayor que cero.';
    }

    const disponible = disponibleParaFila(fila, indice);
    if (cantidad > disponible) {
        return `Solo hay ${disponible} disponible(s) en esa talla.`;
    }

    if (!(numero(fila.precio_unitario) > 0)) {
        return 'El precio debe ser mayor que cero.';
    }

    return null;
};

const filasConDatos = computed(() =>
    form.detalles
        .map((fila: any, indice: number) => ({ fila, indice }))
        .filter(({ fila }) => tieneDatos(fila))
);

const errorVisible = (fila: any, indice: number): string | null => {
    if (!tieneDatos(fila)) return null;
    if (!state.intentoEnvio && !(Number(fila.cantidad) > disponibleParaFila(fila, indice))) {
        return null;
    }
    return errorFila(fila, indice);
};

const alCambiarProducto = (fila: any) => {
    fila.talla = '';
    fila.cantidad = '';

    const producto = productoDe(fila.producto_id);
    const precio = producto?.precio ?? 0;
    fila.precio_unitario = precio > 0 ? String(Math.round(precio)) : '';

    // Si el producto solo tiene una talla con existencia, se elige sola.
    const tallas = producto?.tallas ?? [];
    if (tallas.length === 1) {
        fila.talla = tallas[0].talla;
    }
};

const agregarFila = () => {
    form.detalles.push(filaVacia());
};

const ultimaFila = computed<any | null>(() =>
    form.detalles.length ? form.detalles[form.detalles.length - 1] : null
);

/** Otra talla del mismo producto con existencia libre para duplicar la fila. */
const tallaDuplicable = computed<TallaDisponible | null>(() => {
    const fila = ultimaFila.value;
    if (!fila || !fila.producto_id || !String(fila.talla ?? '').trim()) return null;

    const indice = form.detalles.length - 1;
    return (
        tallasDe(fila.producto_id).find(
            (opcion) =>
                opcion.talla !== String(fila.talla) &&
                disponibleParaFila({ ...fila, talla: opcion.talla }, indice) > 0
        ) ?? null
    );
});

/** Repite el producto de la última fila en otra talla, con la misma cantidad. */
const duplicarUltimaFila = () => {
    const fila = ultimaFila.value;
    const opcion = tallaDuplicable.value;
    if (!fila || !opcion) return;

    const indice = form.detalles.length - 1;
    const disponible = disponibleParaFila({ ...fila, talla: opcion.talla }, indice);
    const cantidad = Math.floor(numero(fila.cantidad));

    form.detalles.push({
        producto_id: fila.producto_id,
        talla: opcion.talla,
        cantidad: cantidad > 0 ? String(Math.min(cantidad, disponible)) : '',
        precio_unitario: fila.precio_unitario,
    });
};

const quitarFila = (indice: number) => {
    form.detalles.splice(indice, 1);
    if (!form.detalles.length) {
        form.detalles.push(filaVacia());
    }
};

const reiniciar = () => {
    form.reset();
    form.clearErrors();
    form.detalles = [filaVacia()];
    state.errorLocal = '';
    state.intentoEnvio = false;
};

watch(
    () => props.show,
    (visible) => {
        if (visible) {
            reiniciar();
        }
    }
);

const guardar = () => {
    state.errorLocal = '';
    state.intentoEnvio = true;

    if (!filasConDatos.value.length) {
        state.errorLocal = 'Agrega al menos un producto vendido.';
        return;
    }

    const errores = filasConDatos.value
        .map(({ fila, indice }) => errorFila(fila, indice))
        .filter((mensaje): mensaje is string => mensaje !== null);

    if (errores.length) {
        state.errorLocal = errores[0];
        return;
    }

    if (form.forma_pago === 'otro' && String(form.forma_pago_detalle ?? '').trim() === '') {
        state.errorLocal = 'Escriba la forma de pago.';
        return;
    }

    if (form.descuento_tipo === 'porcentaje' && numero(form.descuento_valor) > 100) {
        state.errorLocal = 'El descuento no puede superar el 100%.';
        return;
    }

    if (form.impuesto_tipo === 'porcentaje' && numero(form.impuesto_valor) > 100) {
        state.errorLocal = 'El impuesto no puede superar el 100%.';
        return;
    }

    form
        .transform((datos) => ({
            ...datos,
            descuento_valor: numero(datos.descuento_valor),
            impuesto_valor: numero(datos.impuesto_valor),
            detalles: filasConDatos.value.map(({ fila }) => ({
                producto_id: Number(fila.producto_id),
                talla: String(fila.talla),
                cantidad: Math.floor(numero(fila.cantidad)),
                precio_unitario: numero(fila.precio_unitario),
            })),
        }))
        .post(route('Venta.store'), {
            preserveScroll: true,
            onSuccess: () => {
                emit('saved');
                emit('close');
                reiniciar();
            },
            onError: () => null,
        });
};
</script>

<template>
    <section class="space-y-6">
        <Modal :show="props.show" @close="emit('close')" maxWidth="xl7">
            <form class="p-0" @submit.prevent="guardar">
                <div class="border-b border-gray-200 dark:border-gray-700 px-6 pt-6 pb-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                Venta de productos
                            </h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Registra los productos vendidos. La cantidad se descuenta de la existencia recibida de cada talla.
                            </p>
                        </div>
                        <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-right dark:border-gray-700 dark:bg-gray-900/40">
                            <span class="block text-[10px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Fecha de la venta (automática)
                            </span>
                            <span class="block text-sm font-medium text-gray-800 dark:text-gray-200">{{ fechaVenta }}</span>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-5 space-y-5 max-h-[70vh] overflow-y-auto scrollbar-table">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Solo se listan productos con existencia recibida mayor a cero.
                        </p>
                        <SecondaryButton type="button" class="text-xs" @click="agregarFila">
                            + Agregar producto
                        </SecondaryButton>
                    </div>

                    <p v-if="!catalogo.length"
                        class="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-200">
                        No hay productos con existencia recibida mayor a cero. Reciba una orden de producción para cargar inventario.
                    </p>

                    <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[1000px] text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200 bg-gray-50 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400">
                                        <th scope="col" class="px-3 py-2 font-semibold">Producto</th>
                                        <th scope="col" class="w-44 px-3 py-2 font-semibold">Talla</th>
                                        <th scope="col" class="w-32 px-3 py-2 font-semibold">Cantidad</th>
                                        <th scope="col" class="w-36 px-3 py-2 text-right font-semibold">Disponible</th>
                                        <th scope="col" class="w-40 px-3 py-2 text-right font-semibold">Precio unitario</th>
                                        <th scope="col" class="w-36 px-3 py-2 text-right font-semibold">Total</th>
                                        <th scope="col" class="w-16 px-3 py-2 font-semibold"><span class="sr-only">Quitar</span></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                                    <tr v-for="(fila, indice) in form.detalles" :key="indice">
                                        <td class="px-3 py-2 align-top">
                                            <v-select :options="catalogo" label="name"
                                                :reduce="(item: any) => item.id" v-model="fila.producto_id"
                                                placeholder="Seleccione el producto..." append-to-body
                                                :aria-label="`Producto vendido ${indice + 1}`"
                                                @option:selected="alCambiarProducto(fila)" />
                                        </td>
                                        <td class="px-3 py-2 align-top">
                                            <select v-model="fila.talla"
                                                :disabled="!fila.producto_id"
                                                class="h-9 w-full rounded-md border border-gray-300 bg-white px-2 text-sm text-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-400 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                                :aria-label="`Talla vendida ${indice + 1}`">
                                                <option value="">Talla</option>
                                                <option v-for="opcion in tallasDe(fila.producto_id)" :key="opcion.talla"
                                                    :value="opcion.talla">
                                                    {{ opcion.talla }} · {{ opcion.disponible }} disp.
                                                </option>
                                            </select>
                                        </td>
                                        <td class="px-3 py-2 align-top">
                                            <TextInput type="number" min="1" step="1"
                                                :max="disponibleParaFila(fila, indice) || undefined"
                                                class="block h-9 w-full px-2 text-right tabular-nums"
                                                v-model="fila.cantidad" placeholder="0"
                                                @input="limitarCantidad(fila, indice, $event)"
                                                :aria-label="`Cantidad vendida ${indice + 1}`" />
                                        </td>
                                        <td class="px-3 py-2 text-right align-top">
                                            <div class="tabular-nums text-gray-700 dark:text-gray-300">
                                                {{ fila.talla ? disponibleParaFila(fila, indice) : '—' }}
                                            </div>
                                            <div v-if="fila.talla && Number(fila.cantidad) > 0"
                                                class="text-[11px] text-gray-500 dark:text-gray-400">
                                                Quedarán {{ Math.max(0, disponibleParaFila(fila, indice) - (Number(fila.cantidad) || 0)) }}
                                            </div>
                                        </td>
                                        <td class="px-3 py-2 align-top">
                                            <MoneyInput class="block h-9 w-full px-2"
                                                v-model="fila.precio_unitario" placeholder="$0"
                                                :error="errorVisible(fila, indice) ?? undefined"
                                                :aria-label="`Precio unitario ${indice + 1}`" />
                                        </td>
                                        <td class="px-3 py-2 text-right align-top">
                                            <div class="tabular-nums font-medium text-gray-800 dark:text-gray-200">
                                                {{ importeFila(fila) > 0 ? formatearMonto(importeFila(fila)) : '—' }}
                                            </div>
                                        </td>
                                        <td class="px-3 py-2 text-right align-top">
                                            <button type="button"
                                                class="rounded-md px-2 py-1 text-xs font-semibold text-rose-600 transition hover:bg-rose-50 hover:text-rose-700 dark:text-rose-400 dark:hover:bg-rose-500/10"
                                                :aria-label="`Quitar producto ${indice + 1}`"
                                                @click="quitarFila(indice)">
                                                Quitar
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="border-t border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-900/40">
                                        <td colspan="5"
                                            class="px-3 py-2 text-right text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                            Subtotal
                                        </td>
                                        <td class="px-3 py-2 text-right text-sm font-bold tabular-nums text-gray-900 dark:text-gray-100">
                                            {{ formatearMonto(subtotal) }}
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            <SecondaryButton type="button" class="whitespace-nowrap"
                                                :disabled="!tallaDuplicable"
                                                :title="tallaDuplicable
                                                    ? 'Agrega el producto de la última fila con otra talla y la misma cantidad.'
                                                    : 'El producto de la última fila no tiene otra talla con existencia.'"
                                                @click="duplicarUltimaFila">
                                                + Otra talla
                                            </SecondaryButton>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <template v-for="(fila, indice) in form.detalles" :key="'error-' + indice">
                        <p v-if="errorVisible(fila, indice)" class="text-[11px] text-red-600 dark:text-red-400">
                            Fila {{ indice + 1 }}: {{ errorVisible(fila, indice) }}
                        </p>
                    </template>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-x-5 gap-y-4">
                        <div>
                            <InputLabel for="forma_pago" value="Forma de pago" />
                            <select id="forma_pago" v-model="form.forma_pago"
                                class="mt-1 block h-10 w-full rounded-md border border-gray-300 bg-white px-3 text-sm text-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                <option value="contado">Contado</option>
                                <option value="credito">Crédito</option>
                                <option value="otro">Otro</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.forma_pago" />
                        </div>

                        <div v-if="form.forma_pago === 'otro'">
                            <InputLabel for="forma_pago_detalle" value="Detalle de la forma de pago" />
                            <TextInput id="forma_pago_detalle" type="text" class="mt-1 block h-10 w-full"
                                v-model="form.forma_pago_detalle" placeholder="Ej. transferencia, Nequi…"
                                :error="form.errors.forma_pago_detalle" />
                            <InputError class="mt-2" :message="form.errors.forma_pago_detalle" />
                        </div>

                        <div>
                            <InputLabel for="descuento_valor" value="Descuento" />
                            <div class="mt-1 flex items-stretch gap-2">
                                <select v-model="form.descuento_tipo"
                                    class="h-10 w-24 shrink-0 rounded-md border border-gray-300 bg-white px-2 text-sm text-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                    aria-label="Tipo de descuento">
                                    <option value="monto">$</option>
                                    <option value="porcentaje">%</option>
                                </select>
                                <MoneyInput v-if="form.descuento_tipo === 'monto'" class="block h-10 w-full px-3"
                                    v-model="form.descuento_valor" placeholder="$0"
                                    :error="form.errors.descuento_valor" />
                                <TextInput v-else type="number" min="0" max="100" step="0.01"
                                    class="block h-10 w-full px-3 text-right tabular-nums"
                                    v-model="form.descuento_valor" placeholder="0" />
                            </div>
                            <InputError class="mt-2" :message="form.errors.descuento_valor" />
                        </div>

                        <div>
                            <InputLabel for="impuesto_valor" value="Impuesto" />
                            <div class="mt-1 flex items-stretch gap-2">
                                <select v-model="form.impuesto_tipo"
                                    class="h-10 w-24 shrink-0 rounded-md border border-gray-300 bg-white px-2 text-sm text-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                    aria-label="Tipo de impuesto">
                                    <option value="monto">$</option>
                                    <option value="porcentaje">%</option>
                                </select>
                                <MoneyInput v-if="form.impuesto_tipo === 'monto'" class="block h-10 w-full px-3"
                                    v-model="form.impuesto_valor" placeholder="$0"
                                    :error="form.errors.impuesto_valor" />
                                <TextInput v-else type="number" min="0" max="100" step="0.01"
                                    class="block h-10 w-full px-3 text-right tabular-nums"
                                    v-model="form.impuesto_valor" placeholder="0" />
                            </div>
                            <InputError class="mt-2" :message="form.errors.impuesto_valor" />
                        </div>

                        <div class="md:col-span-3">
                            <InputLabel for="observaciones" value="Observaciones" class="!text-xs" />
                            <textarea id="observaciones" rows="2"
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                v-model="form.observaciones"
                                placeholder="Observaciones de la venta (opcional)"></textarea>
                            <InputError class="mt-2" :message="form.errors.observaciones" />
                        </div>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                        <dl class="ml-auto max-w-sm space-y-1 text-sm">
                            <div class="flex justify-between tabular-nums">
                                <dt class="text-gray-500 dark:text-gray-400">Unidades vendidas</dt>
                                <dd class="font-medium text-gray-800 dark:text-gray-200">{{ totalVendidos }}</dd>
                            </div>
                            <div class="flex justify-between tabular-nums">
                                <dt class="text-gray-500 dark:text-gray-400">Existencia restante</dt>
                                <dd class="font-medium text-gray-800 dark:text-gray-200">{{ totalDisponibleRestante }}</dd>
                            </div>
                            <div class="flex justify-between border-b border-gray-200 pb-1 tabular-nums dark:border-gray-700">
                                <dt class="text-gray-500 dark:text-gray-400">Suma de precios unitarios</dt>
                                <dd class="font-medium text-gray-800 dark:text-gray-200">{{ formatearMonto(totalPreciosUnitarios) }}</dd>
                            </div>
                            <div class="flex justify-between tabular-nums">
                                <dt class="text-gray-500 dark:text-gray-400">Subtotal</dt>
                                <dd class="text-gray-800 dark:text-gray-200">{{ formatearMonto(subtotal) }}</dd>
                            </div>
                            <div class="flex justify-between tabular-nums">
                                <dt class="text-gray-500 dark:text-gray-400">Descuento</dt>
                                <dd class="text-rose-600 dark:text-rose-400">- {{ formatearMonto(descuentoMonto) }}</dd>
                            </div>
                            <div class="flex justify-between tabular-nums">
                                <dt class="text-gray-500 dark:text-gray-400">Impuesto</dt>
                                <dd class="text-gray-800 dark:text-gray-200">+ {{ formatearMonto(impuestoMonto) }}</dd>
                            </div>
                            <div class="flex justify-between border-t border-gray-200 pt-1 text-base font-semibold tabular-nums dark:border-gray-700">
                                <dt class="text-gray-800 dark:text-gray-100">Ingreso por venta registrado</dt>
                                <dd class="text-emerald-700 dark:text-emerald-300">{{ formatearMonto(total) }}</dd>
                            </div>
                        </dl>
                    </div>

                    <p v-if="state.errorLocal"
                        class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:border-rose-500/40 dark:bg-rose-500/10 dark:text-rose-300">
                        {{ state.errorLocal }}
                    </p>

                    <InputError :message="form.errors.detalles" />
                </div>

                <div class="flex justify-end gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-800/50">
                    <SecondaryButton type="button" :disabled="form.processing" @click="emit('close')">
                        Cancelar
                    </SecondaryButton>
                    <PrimaryButton type="submit" class="ml-3"
                        :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                        {{ form.processing ? 'Registrando...' : 'Registrar venta' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    </section>
</template>
