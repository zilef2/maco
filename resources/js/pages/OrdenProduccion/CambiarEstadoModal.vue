<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import InputLabel from '@/components/InputLabel.vue';
import Modal from '@/components/Modal.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import vSelect from "vue-select";
import "vue-select/dist/vue-select.css";
import { zilef_nuber_format } from '@/global.ts';

const props = defineProps({
    show: Boolean,
    title: String,
    op: Object,
    estados: Array,
    proveedores: { type: Array, default: () => [] },
    // Parámetro mostrar_resta_materiales_taller: si es falso, no se muestra
    // cuánto se resta del inventario al pasar a «En taller».
    mostrarRestaMateriales: { type: Boolean, default: false },
});

const emit = defineEmits(["close"]);

const form = useForm({
    estado_op_id: '' as string | number,
    estado_op_secundario_id: '' as string | number,
    recepciones: [] as any[],
    proceso_externo: {
        proveedor_destino_id: '' as string | number,
        etapa_componente: '',
        bodega_origen: '',
        servicio_proceso: '',
        fecha_prevista_regreso: '',
        resultado_observaciones: '',
        items: [] as any[],
    },
    fecha_regreso_real: '',
    cierre: {
        materiales_sobrantes: '' as string | number | null,
        perdidas_mermas: '' as string | number | null,
        costo_real_final: '' as string | number | null,
    },
});

const normalizar = (valor: any) => String(valor ?? '').trim().toLowerCase();
const hoy = () => new Date().toISOString().split('T')[0];

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

const estadoSecundarioSeleccionado = computed(() =>
    (props.estados || []).find((e: any) => Number(e.id) === Number(form.estado_op_secundario_id))
);

const estadoSecundarioActual = computed(() =>
    (props.estados || []).find(
        (e: any) => Number(e.id) === Number((props.op as any)?.estado_op_secundario_id)
    )
);

// El proceso externo se registra al entrar al estado, ya sea como principal
// o como estado simultáneo ("Esperando materiales" + "Procesos externos").
const destinoEsProcesoExterno = computed(() =>
    estadoObjetivo.value?.name === 'Procesos externos'
    || estadoSecundarioSeleccionado.value?.name === 'Procesos externos'
);

const estabaEnProcesoExterno = computed(() =>
    estadoActual.value?.name === 'Procesos externos'
    || estadoSecundarioActual.value?.name === 'Procesos externos'
);

const mostrarProcesoExterno = computed(() =>
    destinoEsProcesoExterno.value && !estabaEnProcesoExterno.value
);

const destinoEsListaParaTaller = computed(() =>
    form.estado_op_id !== ''
        ? estadoSeleccionado.value?.name === 'Lista para taller'
        : estadoActual.value?.name === 'Lista para taller'
);

const procesosPendientes = computed<any[]>(() =>
    (props.op as any)?.procesos_externos_pendientes || []
);

const mostrarRegresoReal = computed(() =>
    destinoEsListaParaTaller.value && procesosPendientes.value.length > 0
);

// Proveedor destino sugerido: el taller o la empresa externa de la OP,
// buscando su nombre en el catálogo de proveedores.
const proveedorSugerido = computed(() => {
    const op: any = props.op || {};
    const nombreDestino = op.tipo === 'externa'
        ? op.empresa_externa_nombre
        : op.taller_nombre;

    if (!nombreDestino) return null;

    return (props.proveedores || []).find(
        (proveedor: any) => normalizar(proveedor.name ?? proveedor.label) === normalizar(nombreDestino)
    ) ?? null;
});

// Materiales que salen: se toman del kit de la OP y su cantidad requerida.
const materialesProceso = computed<any[]>(() =>
    ((props.op as any)?.materiales_detalle || [])
        .filter((material: any) => Number(material.requerida) > 0)
        .map((material: any) => ({
            material_origen_id: material.material_id,
            nombre: material.nombre,
            cantidad_salida: Number(material.requerida),
            unidad_medida: material.unidad || '',
            disponible: Number(material.disponible) || 0,
            incluir: true,
        }))
);

const itemsProcesoSeleccionados = computed(() =>
    form.proceso_externo.items.filter((item: any) => item.incluir)
);

// Una OP no puede cerrarse mientras queden pares en reparación.
const destinoEsCerrada = computed(() =>
    form.estado_op_id !== ''
        ? estadoSeleccionado.value?.name === 'Cerrada'
        : estadoActual.value?.name === 'Cerrada'
);

// ── Cierre de la OP ──────────────────────────────────────────────────────
// Solo cuando se selecciona «Cerrada» como nuevo estado (no al abrir una OP ya cerrada).
const mostrarCierre = computed(() => estadoSeleccionado.value?.name === 'Cerrada');

const avanceGuardado = ref(false);

const cierreLleno = (valor: any) =>
    valor !== '' && valor !== null && valor !== undefined;

const cierreCompleto = computed(() =>
    cierreLleno(form.cierre.materiales_sobrantes)
    && cierreLleno(form.cierre.perdidas_mermas)
    && cierreLleno(form.cierre.costo_real_final)
);

const faltanCierre = computed(() => {
    const faltan: string[] = [];
    if (!cierreLleno(form.cierre.materiales_sobrantes)) faltan.push('materiales sobrantes o recuperados');
    if (!cierreLleno(form.cierre.perdidas_mermas)) faltan.push('pérdidas en mermas (0 si no hubo)');
    if (!cierreLleno(form.cierre.costo_real_final)) faltan.push('costo real final');
    return faltan;
});

watch(
    () => [form.cierre.materiales_sobrantes, form.cierre.perdidas_mermas, form.cierre.costo_real_final],
    () => { avanceGuardado.value = false; }
);

// Guarda el avance del cierre sin cerrar la OP.
const guardarAvance = () => {
    form
        .transform((datos) => ({
            materiales_sobrantes: datos.cierre.materiales_sobrantes === '' ? null : datos.cierre.materiales_sobrantes,
            perdidas_mermas: datos.cierre.perdidas_mermas === '' ? null : datos.cierre.perdidas_mermas,
            costo_real_final: datos.cierre.costo_real_final === '' ? null : datos.cierre.costo_real_final,
        }))
        .put(route('OrdenProduccion.cierre', props.op?.id), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => { avanceGuardado.value = true; },
            onError: () => { avanceGuardado.value = false; },
        });
};

const numeroDecimalONulo = (valor: any): number | null => {
    if (valor === '' || valor === null || valor === undefined) return null;
    const numero = Number(valor);
    return Number.isFinite(numero) && numero >= 0 ? numero : null;
};

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

// ── Resta de materiales al pasar a «En taller» ──────────────────────────
const destinoEsEnTaller = computed(
    () => form.estado_op_id !== '' && estadoSeleccionado.value?.name === 'En taller'
);

const mostrarRestaMateriales = computed(
    () => destinoEsEnTaller.value && props.mostrarRestaMateriales
);

const kitsResta = ref<any[]>([]);
const cargandoResta = ref(false);
const errorResta = ref<string | null>(null);

const cargarKitsResta = async () => {
    kitsResta.value = [];
    errorResta.value = null;

    if (!(props.op as any)?.id) return;

    cargandoResta.value = true;
    try {
        const respuesta = await fetch(route('KitProceso.por-orden', { id: (props.op as any).id }), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
        });
        if (!respuesta.ok) {
            throw new Error('HTTP ' + respuesta.status);
        }
        const json = await respuesta.json();
        kitsResta.value = json.kits ?? [];
    } catch {
        errorResta.value = 'No se pudo cargar el kit de esta orden de producción.';
    } finally {
        cargandoResta.value = false;
    }
};

watch(mostrarRestaMateriales, (visible) => {
    if (visible) {
        cargarKitsResta();
        return;
    }
    kitsResta.value = [];
    errorResta.value = null;
});

const kitDescuadrado = (kit: any): boolean =>
    Math.abs(Number(kit.cantidad_preparada || 0) - Number(kit.cantidad_requerida || 0)) > 0.004;

const kitSinStock = (kit: any): boolean =>
    Number(kit.cantidad_disponible || 0) < Number(kit.cantidad_requerida || 0);

const problemasResta = computed(() =>
    kitsResta.value.filter((kit) => kitDescuadrado(kit) || kitSinStock(kit))
);

const cantidadKit = (valor: any) => zilef_nuber_format(valor ?? 0, 2, false);

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
    form.proceso_externo = {
        proveedor_destino_id: proveedorSugerido.value?.id ?? '',
        etapa_componente: '',
        bodega_origen: '',
        servicio_proceso: '',
        fecha_prevista_regreso: '',
        resultado_observaciones: '',
        items: materialesProceso.value.map((material: any) => ({ ...material })),
    };
    form.fecha_regreso_real = procesosPendientes.value.length ? hoy() : '';
    const cierre: any = (props.op as any)?.cierre_detalle;
    form.cierre = {
        materiales_sobrantes: cierre?.materiales_sobrantes ?? '',
        perdidas_mermas: cierre?.perdidas_mermas ?? '',
        costo_real_final: cierre?.costo_real_final ?? '',
    };
    avanceGuardado.value = false;
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
    if (destinoEsCerrada.value && totalEnReparacionEfectivo.value > 0) return false;

    // Cerrar exige los tres datos del cierre.
    if (mostrarCierre.value && !cierreCompleto.value) return false;

    // Para pasar a «En taller» el kit debe cuadrar y tener existencia.
    if (mostrarRestaMateriales.value && (cargandoResta.value || problemasResta.value.length)) {
        return false;
    }

    if (mostrarProcesoExterno.value) {
        if (!form.proceso_externo.proveedor_destino_id
            || !String(form.proceso_externo.etapa_componente || '').trim()
            || !itemsProcesoSeleccionados.value.length) {
            return false;
        }
    }

    if (mostrarRegresoReal.value && !form.fecha_regreso_real) return false;

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

// Reparaciones que ya están guardadas en la OP.
const reparacionesPersistidas = computed(() =>
    ((props.op as any)?.recepciones_detalle || []).reduce(
        (suma: number, recepcion: any) => suma + (Number(recepcion.cantidad_en_reparacion) || 0),
        0
    )
);

// Si la tabla de recepción está visible manda lo digitado; si no, lo guardado.
const totalEnReparacionEfectivo = computed(() =>
    (mostrarTablaRecepcion.value && form.recepciones.length)
        ? totalEnReparacion.value
        : reparacionesPersistidas.value
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
            proceso_externo: mostrarProcesoExterno.value ? {
                proveedor_destino_id: datos.proceso_externo.proveedor_destino_id,
                etapa_componente: datos.proceso_externo.etapa_componente,
                bodega_origen: datos.proceso_externo.bodega_origen,
                servicio_proceso: datos.proceso_externo.servicio_proceso,
                fecha_prevista_regreso: datos.proceso_externo.fecha_prevista_regreso,
                resultado_observaciones: datos.proceso_externo.resultado_observaciones,
                items: datos.proceso_externo.items
                    .filter((item: any) => item.incluir)
                    .map((item: any) => ({
                        material_origen_id: item.material_origen_id,
                        cantidad_salida: item.cantidad_salida,
                        unidad_medida: item.unidad_medida,
                    })),
            } : undefined,
            fecha_regreso_real: mostrarRegresoReal.value ? datos.fecha_regreso_real : undefined,
            cierre: mostrarCierre.value ? {
                materiales_sobrantes: numeroONulo(datos.cierre.materiales_sobrantes),
                perdidas_mermas: numeroONulo(datos.cierre.perdidas_mermas),
                costo_real_final: numeroDecimalONulo(datos.cierre.costo_real_final),
            } : undefined,
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
        <Modal :show="props.show" @close="emit('close')" :maxWidth="(mostrarTablaRecepcion || mostrarProcesoExterno || mostrarRegresoReal || mostrarRestaMateriales || mostrarCierre) ? 'xl6' : 'md'">
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

                    <p
                        v-if="destinoEsCerrada && totalEnReparacionEfectivo > 0"
                        class="mt-2 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:border-rose-500/40 dark:bg-rose-500/10 dark:text-rose-300"
                        role="alert"
                    >
                        No se puede cerrar la OP: aún hay
                        <span class="font-semibold">{{ totalEnReparacionEfectivo }}</span>
                        {{ totalEnReparacionEfectivo === 1 ? 'par' : 'pares' }} en reparación.
                    </p>
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

                <!-- Resta de materiales al pasar a «En taller» -->
                <div v-if="mostrarRestaMateriales" class="mt-5">
                    <div class="rounded-lg border border-emerald-200 bg-emerald-50/70 px-4 py-3 dark:border-emerald-800 dark:bg-emerald-900/20" role="status" aria-live="polite">
                        <p class="text-sm font-medium text-emerald-900 dark:text-emerald-100">
                            Al pasar a «En taller» el kit sale de bodega.
                        </p>
                        <p class="mt-1 text-xs text-emerald-800/80 dark:text-emerald-200/80">
                            Se descontará del inventario la cantidad requerida de cada material y quedará registrada
                            en el histórico de movimientos. La cantidad preparada debe ser igual a la requerida.
                        </p>
                    </div>

                    <div v-if="cargandoResta" class="mt-3 px-1 py-2 text-sm text-gray-500 dark:text-gray-400">
                        Cargando materiales…
                    </div>

                    <div v-else-if="errorResta"
                        class="mt-3 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:border-rose-500/40 dark:bg-rose-500/10 dark:text-rose-300">
                        {{ errorResta }}
                    </div>

                    <p v-else-if="!kitsResta.length"
                        class="mt-3 rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                        Esta OP no tiene materiales de kit, así que no se descontará inventario.
                    </p>

                    <div v-else class="mt-3 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[720px] text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200 bg-gray-50 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400">
                                        <th scope="col" class="px-3 py-2 font-semibold">Material</th>
                                        <th scope="col" class="w-24 px-3 py-2 text-right font-semibold">Requerida</th>
                                        <th scope="col" class="w-24 px-3 py-2 text-right font-semibold">Preparada</th>
                                        <th scope="col" class="w-24 px-3 py-2 text-right font-semibold">Se resta</th>
                                        <th scope="col" class="w-20 px-3 py-2 font-semibold">Unidad</th>
                                        <th scope="col" class="w-40 px-3 py-2 font-semibold">Estado</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                                    <tr v-for="kit in kitsResta" :key="kit.id">
                                        <td class="px-3 py-2 text-gray-700 dark:text-gray-300">
                                            {{ [kit.material_codigo, kit.material_nombre].filter(Boolean).join(' — ') || '—' }}
                                        </td>
                                        <td class="px-3 py-2 text-right tabular-nums text-gray-700 dark:text-gray-300">
                                            {{ cantidadKit(kit.cantidad_requerida) }}
                                        </td>
                                        <td class="px-3 py-2 text-right tabular-nums"
                                            :class="kitDescuadrado(kit)
                                                ? 'font-semibold text-amber-600 dark:text-amber-400'
                                                : 'text-gray-700 dark:text-gray-300'">
                                            {{ cantidadKit(kit.cantidad_preparada) }}
                                        </td>
                                        <td class="px-3 py-2 text-right font-semibold tabular-nums text-gray-900 dark:text-gray-100">
                                            {{ (kitDescuadrado(kit) || kitSinStock(kit)) ? '—' : cantidadKit(kit.cantidad_requerida) }}
                                        </td>
                                        <td class="px-3 py-2 text-gray-500 dark:text-gray-400">
                                            {{ kit.unidad || '—' }}
                                        </td>
                                        <td class="px-3 py-2">
                                            <span v-if="kitDescuadrado(kit)"
                                                class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide text-amber-800 dark:bg-amber-500/15 dark:text-amber-300">
                                                Preparada ≠ requerida
                                            </span>
                                            <span v-else-if="kitSinStock(kit)"
                                                class="inline-flex items-center rounded-full bg-rose-100 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide text-rose-800 dark:bg-rose-500/15 dark:text-rose-300">
                                                Sin stock
                                            </span>
                                            <span v-else
                                                class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300">
                                                Se resta
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <p v-if="problemasResta.length" class="mt-2 text-sm text-rose-600 dark:text-rose-400">
                        Corrige el kit antes de continuar:
                        {{ problemasResta.length }} material(es) con cantidades diferentes o sin existencia.
                    </p>
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

                <!-- Registro de salida a proceso externo -->
                <div v-if="mostrarProcesoExterno" class="mt-5 space-y-3">
                    <div class="rounded-lg border border-cyan-200 bg-cyan-50/70 px-4 py-3 dark:border-cyan-800 dark:bg-cyan-900/20" role="status">
                        <p class="text-sm font-medium text-cyan-900 dark:text-cyan-100">
                            Salida a proceso externo
                        </p>
                        <p class="mt-1 text-xs text-cyan-800/80 dark:text-cyan-200/80">
                            Los materiales y las cantidades se toman del kit de la OP.
                            La fecha de salida se registrará con la fecha y hora actual.
                        </p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <InputLabel for="proveedor_destino_id" value="Proveedor destino *" />
                            <v-select
                                id="proveedor_destino_id"
                                class="mt-1 block w-full"
                                :options="props.proveedores || []"
                                label="name"
                                :reduce="(item: any) => item.id"
                                v-model="form.proceso_externo.proveedor_destino_id"
                                placeholder="Seleccione el proveedor..."
                            />
                            <InputError class="mt-1" :message="form.errors['proceso_externo.proveedor_destino_id']" />
                        </div>

                        <div>
                            <InputLabel for="etapa_componente" value="Etapa o componente *" />
                            <TextInput
                                id="etapa_componente"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="form.proceso_externo.etapa_componente"
                                placeholder="Ej. Bordado, estampado, lavandería..."
                            />
                            <InputError class="mt-1" :message="form.errors['proceso_externo.etapa_componente']" />
                        </div>

                        <div>
                            <InputLabel for="fecha_prevista_regreso" value="Fecha prevista de regreso" />
                            <TextInput
                                id="fecha_prevista_regreso"
                                type="date"
                                class="mt-1 block w-full"
                                v-model="form.proceso_externo.fecha_prevista_regreso"
                            />
                            <InputError class="mt-1" :message="form.errors['proceso_externo.fecha_prevista_regreso']" />
                        </div>

                        <div>
                            <InputLabel for="bodega_origen" value="Bodega de origen" />
                            <TextInput
                                id="bodega_origen"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="form.proceso_externo.bodega_origen"
                                placeholder="Opcional"
                            />
                        </div>

                        <div>
                            <InputLabel for="servicio_proceso" value="Servicio o proceso" />
                            <TextInput
                                id="servicio_proceso"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="form.proceso_externo.servicio_proceso"
                                placeholder="Opcional"
                            />
                        </div>

                        <div>
                            <InputLabel for="resultado_observaciones" value="Observaciones" />
                            <TextInput
                                id="resultado_observaciones"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="form.proceso_externo.resultado_observaciones"
                                placeholder="Opcional"
                            />
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[560px] text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200 bg-gray-50 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400">
                                        <th scope="col" class="w-16 px-3 py-2 font-semibold">Incluir</th>
                                        <th scope="col" class="px-3 py-2 font-semibold">Material</th>
                                        <th scope="col" class="w-28 px-3 py-2 text-right font-semibold">Cantidad</th>
                                        <th scope="col" class="w-24 px-3 py-2 font-semibold">Unidad</th>
                                        <th scope="col" class="w-28 px-3 py-2 text-right font-semibold">Stock</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                                    <tr v-for="(item, indice) in form.proceso_externo.items" :key="item.material_origen_id ?? indice">
                                        <td class="px-3 py-2">
                                            <input
                                                type="checkbox"
                                                class="rounded border-gray-300 text-cyan-600 focus:ring-cyan-500 dark:border-gray-700 dark:bg-gray-900"
                                                v-model="item.incluir"
                                                :aria-label="`Incluir ${item.nombre}`"
                                            />
                                        </td>
                                        <td class="px-3 py-2 text-gray-700 dark:text-gray-300">
                                            {{ item.nombre || '—' }}
                                        </td>
                                        <td class="px-3 py-2 text-right font-medium tabular-nums text-gray-900 dark:text-gray-100">
                                            {{ item.cantidad_salida }}
                                        </td>
                                        <td class="px-3 py-2 text-gray-600 dark:text-gray-300">
                                            {{ item.unidad_medida || '—' }}
                                        </td>
                                        <td class="px-3 py-2 text-right tabular-nums"
                                            :class="item.disponible < item.cantidad_salida
                                                ? 'text-amber-600 dark:text-amber-400'
                                                : 'text-gray-500 dark:text-gray-400'">
                                            {{ item.disponible }}
                                        </td>
                                    </tr>
                                    <tr v-if="!form.proceso_externo.items.length">
                                        <td colspan="5" class="px-3 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                            Esta OP no tiene materiales en el kit; no es posible registrar el proceso externo.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <p v-if="itemsProcesoSeleccionados.length"
                        class="text-xs text-gray-500 dark:text-gray-400">
                        Se registrarán {{ itemsProcesoSeleccionados.length }}
                        {{ itemsProcesoSeleccionados.length === 1 ? 'material' : 'materiales' }}.
                    </p>

                    <InputError :message="form.errors.proceso_externo" />
                    <InputError :message="form.errors['proceso_externo.items']" />
                </div>

                <!-- Regreso de procesos externos al pasar a Lista para taller -->
                <div v-else-if="mostrarRegresoReal" class="mt-5 space-y-3">
                    <div class="rounded-lg border border-emerald-200 bg-emerald-50/70 px-4 py-3 dark:border-emerald-800 dark:bg-emerald-900/20" role="status">
                        <p class="text-sm font-medium text-emerald-900 dark:text-emerald-100">
                            Regreso de procesos externos
                        </p>
                        <p class="mt-1 text-xs text-emerald-800/80 dark:text-emerald-200/80">
                            Al pasar a «Lista para taller» debe indicar la fecha real de regreso
                            de los materiales que están fuera.
                        </p>
                    </div>

                    <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[520px] text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200 bg-gray-50 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400">
                                        <th scope="col" class="px-3 py-2 font-semibold">Material</th>
                                        <th scope="col" class="w-28 px-3 py-2 text-right font-semibold">Cantidad</th>
                                        <th scope="col" class="px-3 py-2 font-semibold">Proveedor</th>
                                        <th scope="col" class="w-36 px-3 py-2 font-semibold">Salida</th>
                                        <th scope="col" class="w-36 px-3 py-2 font-semibold">Previsto</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                                    <tr v-for="proceso in procesosPendientes" :key="proceso.id">
                                        <td class="px-3 py-2 text-gray-700 dark:text-gray-300">
                                            {{ proceso.material_nombre || '—' }}
                                        </td>
                                        <td class="px-3 py-2 text-right tabular-nums text-gray-900 dark:text-gray-100">
                                            {{ proceso.cantidad_salida }}
                                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ proceso.unidad_medida }}</span>
                                        </td>
                                        <td class="px-3 py-2 text-gray-600 dark:text-gray-300">
                                            {{ proceso.proveedor_nombre || '—' }}
                                        </td>
                                        <td class="px-3 py-2 text-gray-500 dark:text-gray-400">
                                            {{ proceso.fecha_salida ? proceso.fecha_salida.slice(0, 10) : '—' }}
                                        </td>
                                        <td class="px-3 py-2 text-gray-500 dark:text-gray-400">
                                            {{ proceso.fecha_prevista_regreso || '—' }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="sm:w-64">
                        <InputLabel for="fecha_regreso_real" value="Fecha real de regreso *" />
                        <TextInput
                            id="fecha_regreso_real"
                            type="date"
                            class="mt-1 block w-full"
                            v-model="form.fecha_regreso_real"
                        />
                        <InputError class="mt-1" :message="form.errors.fecha_regreso_real" />
                    </div>
                </div>

                <!-- Cierre de la OP -->
                <div v-if="mostrarCierre" class="mt-5">
                    <div class="rounded-lg border border-amber-200 bg-amber-50/70 px-4 py-3 dark:border-amber-800 dark:bg-amber-900/20" role="status">
                        <p class="text-sm font-medium text-amber-900 dark:text-amber-100">
                            Cierre de la orden de producción
                        </p>
                        <p class="mt-1 text-xs text-amber-800/80 dark:text-amber-200/80">
                            Para cerrar la OP deben quedar registrados los materiales sobrantes o recuperados,
                            las pérdidas en mermas y el costo real final. Esta información se guardará en el
                            cierre de la OP.
                        </p>
                    </div>

                    <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <InputLabel for="materiales_sobrantes" value="Materiales sobrantes o recuperados *" />
                            <TextInput
                                id="materiales_sobrantes"
                                type="number"
                                min="0"
                                step="1"
                                class="mt-1 block w-full"
                                v-model="form.cierre.materiales_sobrantes"
                                placeholder="Cantidad"
                            />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Cantidad total registrada.</p>
                            <InputError class="mt-1" :message="form.errors.materiales_sobrantes" />
                        </div>

                        <div>
                            <InputLabel for="perdidas_mermas" value="Pérdidas en mermas *" />
                            <TextInput
                                id="perdidas_mermas"
                                type="number"
                                min="0"
                                step="1"
                                class="mt-1 block w-full"
                                v-model="form.cierre.perdidas_mermas"
                                placeholder="0 si no hubo"
                            />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Si no hubo pérdidas, deja 0.</p>
                            <InputError class="mt-1" :message="form.errors.perdidas_mermas" />
                        </div>

                        <div>
                            <InputLabel for="costo_real_final" value="Costo real final *" />
                            <TextInput
                                id="costo_real_final"
                                type="number"
                                min="0"
                                step="any"
                                class="mt-1 block w-full"
                                v-model="form.cierre.costo_real_final"
                                placeholder="Costo"
                            />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Aún no se calcula automáticamente; ingrésalo tú.
                            </p>
                            <InputError class="mt-1" :message="form.errors.costo_real_final" />
                        </div>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                        <p v-if="!cierreCompleto" class="text-sm text-amber-700 dark:text-amber-300">
                            Completa {{ faltanCierre.join(', ') }} para poder cerrar la OP.
                        </p>
                        <p v-else class="text-sm text-emerald-700 dark:text-emerald-300">
                            Cierre completo: la OP puede cerrarse.
                        </p>

                        <div class="flex items-center gap-3">
                            <span v-if="avanceGuardado" class="text-xs font-medium text-emerald-700 dark:text-emerald-300">
                                Avance guardado
                            </span>
                            <SecondaryButton type="button" :disabled="form.processing" @click="guardarAvance">
                                Guardar avance
                            </SecondaryButton>
                        </div>
                    </div>

                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        «Guardar avance» conserva lo diligenciado sin cerrar la OP; podrás terminarlo después.
                    </p>

                    <InputError class="mt-2" :message="form.errors.cierre" />
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
