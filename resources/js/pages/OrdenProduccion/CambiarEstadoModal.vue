<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import InputLabel from '@/components/InputLabel.vue';
import Modal from '@/components/Modal.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import vSelect from "vue-select";
import "vue-select/dist/vue-select.css";

const props = defineProps({
    show: Boolean,
    title: String,
    op: Object,
    estados: Array,
});

const emit = defineEmits(["close"]);

const form = useForm({
    estado_op_id: '' as string | number,
    estado_op_secundario_id: '' as string | number,
    recepciones: [] as any[],
});

// Único par de estados que puede coexistir en una OP (regla de negocio).
const PAR_COEXISTENCIA = ['Esperando materiales', 'Procesos externos'] as const;

const estadoActual = computed(() =>
    (props.estados || []).find((e: any) => Number(e.id) === Number(props.op?.estado_op_id))
);

const opcionesPermitidas = computed(() => {
    const permitidos: number[] = estadoActual.value?.permitidos || [];
    return (props.estados || []).filter((e: any) => permitidos.includes(e.id));
});

const estadoSeleccionado = computed(() =>
    (props.estados || []).find((e: any) => Number(e.id) === Number(form.estado_op_id))
);

// El estado "objetivo" es el seleccionado, o el actual si aún no se elige otro.
const estadoObjetivo = computed(() => estadoSeleccionado.value ?? estadoActual.value);

// Coexistencia permitida solo para el par {Esperando materiales, Procesos externos}.
const puedeCoexistir = computed(() =>
    PAR_COEXISTENCIA.includes(estadoObjetivo.value?.name as any)
);

// El compañero del par: el otro estado de los dos que pueden simultaneidad.
const estadosCompaneros = computed(() => {
    const objetivo = estadoObjetivo.value?.name;
    if (!puedeCoexistir.value) return [];
    const companero = PAR_COEXISTENCIA.find((n) => n !== objetivo);
    return (props.estados || []).filter((e: any) => e.name === companero);
});

// Si el objetivo sale del par, no hay secundario.
watch(estadoObjetivo, (nuevo) => {
    if (!PAR_COEXISTENCIA.includes(nuevo?.name as any)) {
        form.estado_op_secundario_id = '';
    }
});

// La recepción de calidad se muestra cuando la OP ya está en "Recibiendo"
// o cuando se selecciona "Recibiendo" como nuevo estado.
const esRecepcion = computed(() => estadoSeleccionado.value?.name === 'Recibiendo');
const mostrarTablaRecepcion = computed(() =>
    estadoActual.value?.name === 'Recibiendo' || esRecepcion.value
);

// Nombre de respaldo cuando la OP tiene un único producto.
const productoSuelto = computed(() => {
    const productos = (props.op as any)?.productos || [];
    if (productos.length !== 1) return null;
    const p = productos[0];
    return { id: p.id, nombre: p.Titulo_Shopify || p.Codigo_interno || ('Producto #' + p.id) };
});

// Tallas de la OP con el producto al que pertenecen (lo sabe la distribución).
const tallasOp = computed(() =>
    ((props.op as any)?.distribuciones_detalle || []).map((d: any) => ({
        distribucion_id: d.id,
        talla: d.talla,
        esperada: Number(d.cantidad_planificada) || 0,
        producto_id: d.producto_id ?? productoSuelto.value?.id ?? null,
        producto_nombre: d.producto_nombre
            ?? productoSuelto.value?.nombre
            ?? null,
    }))
);

const claveRecepcion = (productoId: any, talla: any) => `${productoId ?? ''}|${talla ?? ''}`;

const nuevaFila = (talla: any, existente?: any) => ({
    distribucion_id: talla.distribucion_id,
    talla: talla.talla,
    esperada: talla.esperada,
    producto_id: talla.producto_id,
    producto_nombre: talla.producto_nombre,
    cantidad_aprobada: (existente?.cantidad_aprobada ?? null) as number | null,
    cantidad_en_reparacion: (existente?.cantidad_en_reparacion ?? null) as number | null,
    cantidad_no_recuperable: (existente?.cantidad_no_recuperable ?? null) as number | null,
});

// Cruza las tallas de la OP con la recepción ya registrada, para que al abrir
// una OP en "Recibiendo" se puedan ajustar los valores existentes.
const filasRecepcion = computed(() => {
    const existentes = new Map<string, any>();
    for (const recepcion of ((props.op as any)?.recepciones_detalle || [])) {
        existentes.set(claveRecepcion(recepcion.producto_id, recepcion.talla), recepcion);
    }

    return tallasOp.value.map((talla: any) =>
        nuevaFila(talla, existentes.get(claveRecepcion(talla.producto_id, talla.talla)))
    );
});

const abrirModal = () => {
    form.errors = {} as any;
    form.reset();
    form.estado_op_id = '';
    form.estado_op_secundario_id = (props.op as any)?.estado_op_secundario_id ?? '';
    form.recepciones = mostrarTablaRecepcion.value
        ? filasRecepcion.value.map((fila: any) => ({ ...fila }))
        : [];
};

const alCambiarEstado = () => {
    if (!mostrarTablaRecepcion.value) {
        form.recepciones = [];
        return;
    }

    // Conserva lo que el usuario ya escribió al cambiar de estado.
    const actuales = new Map(
        form.recepciones.map((fila: any) => [claveRecepcion(fila.producto_id, fila.talla), fila])
    );

    form.recepciones = filasRecepcion.value.map((fila: any) => {
        const actual = actuales.get(claveRecepcion(fila.producto_id, fila.talla));
        return actual ? { ...fila, ...actual } : { ...fila };
    });
};

watch(() => form.estado_op_id, alCambiarEstado);

const valorValido = (valor: any) =>
    valor === '' || valor === null || valor === undefined || (Number.isFinite(Number(valor)) && Number(valor) >= 0);

const numeroONulo = (valor: any): number | null => {
    if (valor === '' || valor === null || valor === undefined) return null;
    const numero = Math.trunc(Number(valor));
    return Number.isFinite(numero) && numero >= 0 ? numero : null;
};

const sumaFila = (fila: any): number =>
    (numeroONulo(fila.cantidad_aprobada) ?? 0)
    + (numeroONulo(fila.cantidad_en_reparacion) ?? 0)
    + (numeroONulo(fila.cantidad_no_recuperable) ?? 0);

const filaCuadra = (fila: any): boolean => sumaFila(fila) === Number(fila.esperada);

const filaValida = (fila: any): boolean => {
    const sinNegativos = [fila.cantidad_aprobada, fila.cantidad_en_reparacion, fila.cantidad_no_recuperable]
        .every(valorValido);
    return sinNegativos && filaCuadra(fila);
};

// Misma talla para el mismo producto: la recepción quedaría duplicada.
const clavesDuplicadas = computed(() => {
    const vistas = new Set<string>();
    const repetidas = new Set<string>();
    for (const fila of form.recepciones) {
        const clave = `${fila.producto_id ?? ''}|${fila.talla}`;
        if (vistas.has(clave)) repetidas.add(clave);
        vistas.add(clave);
    }
    return [...repetidas];
});

const filasCompletas = computed(() => form.recepciones.every(filaValida));

const puedeGuardar = computed(() => {
    if (!mostrarTablaRecepcion.value) return true;
    if (!form.recepciones.length) return true; // OP sin tallas: no hay recepción que cuadrar
    return filasCompletas.value && !clavesDuplicadas.value.length;
});

// Con un estado del par se puede guardar aunque no se cambie el principal (solo la coexistencia).
// Una OP que ya está en "Recibiendo" también puede guardar para actualizar la recepción.
const puedeEnviar = computed(() =>
    form.estado_op_id !== ''
    || puedeCoexistir.value
    || estadoActual.value?.name === 'Recibiendo'
);

const totalEsperado = computed(() =>
    form.recepciones.reduce((suma, fila: any) => suma + Number(fila.esperada || 0), 0)
);

const totalRecibido = computed(() =>
    form.recepciones.reduce((suma, fila: any) => suma + sumaFila(fila), 0)
);

const totalEnReparacion = computed(() =>
    form.recepciones.reduce(
        (suma, fila: any) => suma + (numeroONulo(fila.cantidad_en_reparacion) ?? 0),
        0
    )
);

// El destino es "Recibiendo" si se seleccionó ese estado o si la OP ya está
// en él y el usuario no eligió otro estado explícitamente.
const destinoEsRecibiendo = computed(() =>
    form.estado_op_id !== ''
        ? estadoSeleccionado.value?.name === 'Recibiendo'
        : estadoActual.value?.name === 'Recibiendo'
);

const pasaraAPendiente = computed(() =>
    mostrarTablaRecepcion.value
    && destinoEsRecibiendo.value
    && totalEnReparacion.value === 0
);

const erroresRecepcion = computed(() => {
    const mensajes = Object.entries(form.errors)
        .filter(([campo]) => campo.startsWith('recepciones'))
        .map(([, mensaje]) => mensaje);
    return [...new Set(mensajes)];
});

const guardar = () => {
    form
        .transform((datos) => ({
            ...datos,
            estado_op_id: datos.estado_op_id || props.op?.estado_op_id,
            recepciones: datos.recepciones.map((fila: any) => ({
                distribucion_id: fila.distribucion_id,
                cantidad_aprobada: numeroONulo(fila.cantidad_aprobada),
                cantidad_en_reparacion: numeroONulo(fila.cantidad_en_reparacion),
                cantidad_no_recuperable: numeroONulo(fila.cantidad_no_recuperable),
            })),
        }))
        .put(route('OrdenProduccion.cambiarEstado', props.op?.id), {
            preserveScroll: true,
            onSuccess: () => emit("close"),
            onError: () => null,
        });
};

watch(() => props.show, (visible) => {
    if (visible) {
        abrirModal();
    }
});
</script>

<template>
    <section class="space-y-6">
        <Modal :show="props.show" @close="emit('close')" :maxWidth="mostrarTablaRecepcion ? 'xl6' : 'md'">
            <form class="p-6" @submit.prevent="guardar">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Cambiar estado
                    <span v-if="props.op?.numero_op" class="text-sm font-normal text-gray-500 dark:text-gray-400">
                        — OP {{ props.op.numero_op }}
                    </span>
                </h2>

                <div class="mt-4 rounded-md border border-gray-200 dark:border-gray-700 p-3">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Estado actual</p>
                    <p class="text-base font-semibold text-gray-900 dark:text-gray-100">
                        {{ estadoActual?.name || 'Sin estado' }}
                        <span v-if="(props.op as any)?.estado_secundario_nombre" class="text-violet-600 dark:text-violet-400">
                            + {{ (props.op as any).estado_secundario_nombre }}
                        </span>
                    </p>
                    <p v-if="estadoActual?.regla_clave" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ estadoActual.regla_clave }}
                    </p>
                </div>

                <div class="mt-4">
                    <InputLabel for="estado_nuevo" value="Nuevo estado" />
                    <v-select
                        v-if="opcionesPermitidas.length"
                        id="estado_nuevo"
                        class="mt-1 block w-full"
                        :options="opcionesPermitidas"
                        label="name"
                        :reduce="(item: any) => item.id"
                        v-model="form.estado_op_id"
                        placeholder="Seleccione el nuevo estado..."
                    />
                    <p v-else class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Este estado es final: no admite transiciones.
                    </p>
                    <InputError class="mt-2" :message="form.errors.estado_op_id" />
                </div>

                <!-- Coexistencia de estados (única excepción: Esperando materiales + Procesos externos) -->
                <div v-if="puedeCoexistir && estadosCompaneros.length" class="mt-4">
                    <label class="flex cursor-pointer items-start gap-2.5 rounded-md border border-gray-200 p-3 transition hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800/60"
                        :class="{ 'border-violet-300 bg-violet-50/60 dark:border-violet-500/40 dark:bg-violet-500/10': form.estado_op_secundario_id !== '' }">
                        <input type="checkbox" class="mt-0.5 rounded border-gray-300 text-violet-600 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-900"
                            :checked="form.estado_op_secundario_id !== ''"
                            @change="form.estado_op_secundario_id = ($event.target as HTMLInputElement).checked ? estadosCompaneros[0].id : ''" />
                        <span>
                            <span class="block text-sm font-medium text-gray-800 dark:text-gray-200">
                                Además en «{{ estadosCompaneros[0].name }}»
                            </span>
                            <span class="block text-xs text-gray-500 dark:text-gray-400">
                                Es la única combinación permitida; al salir de estos dos estados se limpia automáticamente.
                            </span>
                        </span>
                    </label>
                    <InputError class="mt-2" :message="form.errors.estado_op_secundario_id" />
                </div>

                <!-- Recepción de calidad -->
                <div v-if="mostrarTablaRecepcion" class="mt-5">
                    <div class="rounded-lg border border-indigo-200 bg-indigo-50/70 px-4 py-3 dark:border-indigo-800 dark:bg-indigo-900/20" role="status" aria-live="polite">
                        <p class="text-sm font-medium text-indigo-900 dark:text-indigo-100">
                            {{ estadoActual?.name === 'Recibiendo'
                                ? 'Actualice la recepción de calidad.'
                                : 'Se realizará la recepción calidad a continuación.' }}
                        </p>
                        <p class="mt-1 text-xs text-indigo-800/80 dark:text-indigo-200/80">
                            Registre por talla las cantidades aprobadas, en reparación y no recuperables.
                            La suma de las tres debe ser igual a la cantidad esperada de cada talla.
                        </p>
                        <p
                            v-if="pasaraAPendiente"
                            class="mt-2 inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300"
                        >
                            Sin pares en reparación: la OP pasará a «Pendiente» al guardar.
                        </p>
                        <p
                            v-else-if="destinoEsRecibiendo"
                            class="mt-2 inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-500/15 dark:text-amber-300"
                        >
                            Quedan pares en reparación: la OP quedará en «Recibiendo».
                        </p>
                    </div>

                    <div class="mt-3 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[820px] text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200 bg-gray-50 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400">
                                        <th scope="col" class="px-3 py-2 font-semibold">Producto</th>
                                        <th scope="col" class="w-28 px-3 py-2 font-semibold">Talla</th>
                                        <th scope="col" class="w-20 px-3 py-2 text-right font-semibold">Esperada</th>
                                        <th scope="col" class="w-24 px-3 py-2 font-semibold">Aprobada</th>
                                        <th scope="col" class="w-24 px-3 py-2 font-semibold">En reparación</th>
                                        <th scope="col" class="w-24 px-3 py-2 font-semibold">No recuperable</th>
                                        <th scope="col" class="w-20 px-3 py-2 text-right font-semibold">Recibida</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                                    <tr v-for="(fila, indice) in form.recepciones" :key="fila.distribucion_id ?? indice">
                                        <td class="px-3 py-2 text-sm text-gray-700 dark:text-gray-300">
                                            {{ fila.producto_nombre || '—' }}
                                        </td>
                                        <td class="px-3 py-2 font-medium text-gray-900 dark:text-gray-100">
                                            {{ fila.talla }}
                                        </td>
                                        <td class="px-3 py-2 text-right tabular-nums text-gray-500 dark:text-gray-400">
                                            {{ fila.esperada }}
                                        </td>
                                        <td class="px-3 py-2">
                                            <TextInput
                                                type="number" min="0" step="1" placeholder="0"
                                                class="block h-9 w-20 px-2 text-right tabular-nums"
                                                v-model="fila.cantidad_aprobada"
                                                :aria-label="`Cantidad aprobada talla ${fila.talla}`"
                                            />
                                        </td>
                                        <td class="px-3 py-2">
                                            <TextInput
                                                type="number" min="0" step="1" placeholder="0"
                                                class="block h-9 w-20 px-2 text-right tabular-nums"
                                                v-model="fila.cantidad_en_reparacion"
                                                :aria-label="`Cantidad en reparación talla ${fila.talla}`"
                                            />
                                        </td>
                                        <td class="px-3 py-2">
                                            <TextInput
                                                type="number" min="0" step="1" placeholder="0"
                                                class="block h-9 w-20 px-2 text-right tabular-nums"
                                                v-model="fila.cantidad_no_recuperable"
                                                :aria-label="`Cantidad no recuperable talla ${fila.talla}`"
                                            />
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            <span
                                                class="font-semibold tabular-nums"
                                                :class="filaCuadra(fila)
                                                    ? 'text-gray-900 dark:text-gray-100'
                                                    : 'text-amber-600 dark:text-amber-400'"
                                                :title="filaCuadra(fila) ? '' : `Debe sumar ${fila.esperada}`"
                                            >
                                                {{ sumaFila(fila) }}
                                            </span>
                                        </td>
                                    </tr>

                                    <tr v-if="!form.recepciones.length">
                                        <td colspan="7" class="px-3 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                            Esta OP no tiene tallas distribuidas, así que no se generarán
                                            registros de recepción.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div v-if="form.recepciones.length" class="mt-2 flex flex-wrap items-center justify-between gap-2 text-sm">
                        <p class="text-gray-500 dark:text-gray-400">
                            Recibidas:
                            <span class="font-semibold tabular-nums"
                                :class="totalRecibido === totalEsperado
                                    ? 'text-gray-900 dark:text-gray-100'
                                    : 'text-amber-600 dark:text-amber-400'">
                                {{ totalRecibido }}
                            </span>
                            de
                            <span class="font-semibold tabular-nums text-gray-900 dark:text-gray-100">{{ totalEsperado }}</span>
                        </p>
                        <p v-if="!filasCompletas" class="text-amber-600 dark:text-amber-400">
                            Cuadre las tres cantidades en cada talla para continuar.
                        </p>
                    </div>

                    <p v-if="clavesDuplicadas.length" class="mt-1 text-sm text-red-600 dark:text-red-400">
                        Hay tallas repetidas para el mismo producto.
                    </p>
                    <p v-for="(mensaje, indice) in erroresRecepcion" :key="indice"
                        class="mt-1 text-sm text-red-600 dark:text-red-400">
                        {{ mensaje }}
                    </p>
                    <InputError class="mt-2" :message="form.errors.recepciones" />
                </div>

                <div class="my-8 flex justify-end">
                    <SecondaryButton :disabled="form.processing" type="button" @click="emit('close')">
                        {{ lang().t('app.button.close') }}
                    </SecondaryButton>
                    <PrimaryButton
                        v-if="opcionesPermitidas.length || puedeCoexistir || estadoActual?.name === 'Recibiendo'"
                        class="ml-3"
                        :class="{ 'opacity-25': form.processing || !puedeEnviar || !puedeGuardar }"
                        :disabled="form.processing || !puedeEnviar || !puedeGuardar"
                        type="submit"
                    >
                        {{ form.processing ? lang().t('app.button.save') + '...' : lang().t('app.button.save') }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    </section>
</template>
