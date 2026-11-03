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

const filaVacia = () => ({ material_id: '', cantidad: '', precio: '', precio_texto: '' });

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

/**
 * Partes numéricas del precio: dígitos enteros, dígitos decimales y si el
 * usuario dejó la coma escrita esperando los centavos.
 */
type PartesPrecio = {
    entero: string;
    decimal: string;
    decimalPendiente: boolean;
};

/**
 * Lee lo escrito en el campo de precio. El punto es separador de miles y la
 * coma es decimal; si no hay coma, un punto seguido de una o dos cifras se
 * toma como decimal (12.5) y seguido de tres o más como miles (1.234).
 */
const interpretarPrecio = (texto: any): PartesPrecio => {
    const limpio = String(texto ?? '').replace(/[^\d.,]/g, '');

    if (!limpio) {
        return { entero: '', decimal: '', decimalPendiente: false };
    }

    const coma = limpio.indexOf(',');

    if (coma !== -1) {
        const decimales = limpio.slice(coma + 1).replace(/\D/g, '');

        return {
            entero: limpio.slice(0, coma).replace(/\D/g, ''),
            decimal: decimales.slice(0, 2),
            decimalPendiente: decimales.length === 0,
        };
    }

    const puntos = limpio.split('.');

    if (puntos.length > 1) {
        const cola = puntos.pop() ?? '';
        const cabeza = puntos.join('.');
        const decimales = cola.replace(/\D/g, '');

        // Un punto con una o dos cifras detrás es decimal; con tres o más, miles.
        if (decimales.length > 0 && decimales.length < 3) {
            return {
                entero: cabeza.replace(/\D/g, ''),
                decimal: decimales,
                decimalPendiente: false,
            };
        }
    }

    return {
        entero: limpio.replace(/\D/g, ''),
        decimal: '',
        decimalPendiente: false,
    };
};

/**
 * Arma el texto del campo: signo peso, puntos de miles y coma decimal.
 * 12345 -> $12.345
 */
const enmascararPrecio = (partes: PartesPrecio): string => {
    if (!partes.entero && !partes.decimal) {
        return '';
    }

    const entero = (partes.entero || '0').replace(/^0+(?=\d)/, '');
    let texto = '$' + entero.replace(/\B(?=(\d{3})+(?!\d))/g, '.');

    if (partes.decimal || partes.decimalPendiente) {
        texto += ',' + partes.decimal;
    }

    return texto;
};

/**
 * Convierte las partes del precio en el número que se guarda.
 */
const partesANumero = (partes: PartesPrecio): number | '' => {
    if (!partes.entero && !partes.decimal) {
        return '';
    }

    const entero = Number(partes.entero || '0');
    const decimal = partes.decimal ? Number(partes.decimal.padEnd(2, '0')) / 100 : 0;

    return entero + decimal;
};

/**
 * Muestra un precio ya guardado con el mismo formato del campo.
 */
const formatearPrecio = (valor: any): string => {
    if (valor === '' || valor === null || valor === undefined) {
        return '';
    }

    const numero = Number(valor);
    if (!Number.isFinite(numero)) {
        return '';
    }

    const centavos = Math.round(Math.abs(numero) * 100);
    const resto = String(centavos % 100).padStart(2, '0');

    return enmascararPrecio({
        entero: String(Math.floor(centavos / 100)),
        decimal: resto === '00' ? '' : resto,
        decimalPendiente: false,
    });
};

const ultimoPrecio = (materialId: any): number | null => {
    const mat = materialSeleccionado(materialId);
    if (!mat) {
        return null;
    }

    const precio = mat.last_purchase_price;
    if (precio === null || precio === undefined || precio === '') {
        return null;
    }

    const numero = Number(precio);

    return Number.isFinite(numero) ? numero : null;
};

const alEscribirPrecio = (fila: any, texto: any) => {
    const partes = interpretarPrecio(texto);

    fila.precio_texto = enmascararPrecio(partes);
    fila.precio = partesANumero(partes);
};

/**
 * Al salir del campo los centavos quedan siempre con dos cifras.
 */
const alPerderFocoPrecio = (fila: any) => {
    if (!fila.precio_texto) {
        return;
    }

    const partes = interpretarPrecio(fila.precio_texto);

    fila.precio_texto = enmascararPrecio({
        entero: partes.entero,
        decimal: partes.decimal ? partes.decimal.padEnd(2, '0') : '',
        decimalPendiente: false,
    });
};

const alCambiarMaterial = (fila: any, opcionSeleccionada?: any) => {
    const mat = opcionSeleccionada || materialSeleccionado(fila.material_id);
    if (!mat) return;

    fila.material_id = mat.id;

    if (mat.ultima_compra_cantidad !== null && mat.ultima_compra_cantidad !== undefined) {
        fila.cantidad = String(mat.ultima_compra_cantidad);
    }

    // El precio se sugiere con el último registrado del material.
    const precioSugerido = ultimoPrecio(fila.material_id);
    fila.precio = precioSugerido ?? '';
    fila.precio_texto = precioSugerido !== null ? formatearPrecio(precioSugerido) : '';
};

const aplicarSugerencia = (fila: any) => {
    const mat = materialSeleccionado(fila.material_id);
    if (mat && mat.ultima_compra_cantidad !== null && mat.ultima_compra_cantidad !== undefined) {
        fila.cantidad = String(mat.ultima_compra_cantidad);
    }
};

const filasValidas = computed(() =>
    form.materiales.filter(
        (fila: any) => fila.material_id && Number(fila.cantidad) > 0 && Number(fila.precio) > 0
    )
);

const compraIncompleta = computed(() =>
    form.materiales.some((fila: any) => {
        const tieneMaterial = fila.material_id !== '' && fila.material_id !== null && fila.material_id !== undefined;
        const tieneCantidad = fila.cantidad !== '' && fila.cantidad !== null && fila.cantidad !== undefined;
        const tienePrecio = fila.precio !== '' && fila.precio !== null && fila.precio !== undefined;

        if (!tieneMaterial && !tieneCantidad && !tienePrecio) return false;
        if (!tieneMaterial || !tieneCantidad || !tienePrecio) return true;

        const cantidad = Number(fila.cantidad);
        const precio = Number(fila.precio);

        return !Number.isFinite(cantidad) || cantidad <= 0
            || !Number.isFinite(precio) || precio <= 0;
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
        state.errorLocal = 'Completa el material, la cantidad y el precio, ambos mayores que cero.';
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
        precio: Number(fila.precio),
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
        <Modal :show="props.show" @close="emit('close')" maxWidth="xl7">
            <form class="p-6 space-y-4" @submit.prevent="guardar">
                <div class="border-b border-gray-200 pb-3 dark:border-gray-700">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        Registrar compra de materiales
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Registra uno o varios materiales comprados. Al seleccionar cada material, se sugerirá automáticamente la cantidad y el precio de la última compra.
                    </p>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Las cantidades se suman a la existencia actual y el precio queda como el último precio del material.
                    </p>
                    <SecondaryButton type="button" class="text-xs" @click="agregarFila">
                        + Agregar material
                    </SecondaryButton>
                </div>

                <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[920px] text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 bg-gray-50 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400">
                                    <th scope="col" class="px-3 py-2 font-semibold">Material</th>
                                    <th scope="col" class="w-52 px-3 py-2 font-semibold">Cantidad sugerida</th>
                                    <th scope="col" class="w-40 px-3 py-2 text-right font-semibold">Precio</th>
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
                                    <td class="px-3 py-2 align-top">
                                        <TextInput
                                            type="text"
                                            inputmode="numeric"
                                            autocomplete="off"
                                            class="block h-9 w-full px-2 text-right tabular-nums"
                                            :model-value="fila.precio_texto"
                                            @update:model-value="alEscribirPrecio(fila, $event)"
                                            @blur="alPerderFocoPrecio(fila)"
                                            placeholder="$0"
                                            :aria-label="`Precio de compra ${indice + 1}`"
                                        />

                                        <div
                                            v-if="ultimoPrecio(fila.material_id) !== null"
                                            class="mt-1 text-[11px] text-gray-500 dark:text-gray-400"
                                        >
                                            Último precio: <b>{{ formatearPrecio(ultimoPrecio(fila.material_id)) }}</b>
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
