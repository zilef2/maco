<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import InputLabel from '@/components/InputLabel.vue';
import Modal from '@/components/Modal.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';
import vSelect from "vue-select";
import "vue-select/dist/vue-select.css";
import { zilef_nuber_format } from '@/global.ts';
import { rangoFlujo } from '@/composables/flujoEstadosOp';
import { resumenRecepcionCosto } from '@/composables/costoRecepcionOp';

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
    fecha_recepcion: '' as string,
    fecha_compromiso: '' as string,
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
});

const normalizar = (valor: any) => String(valor ?? '').trim().toLowerCase();
const hoy = () => {
    const fecha = new Date();
    fecha.setMinutes(fecha.getMinutes() - fecha.getTimezoneOffset());
    return fecha.toISOString().split('T')[0];
};

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

// El orden del flujo vive en flujoEstadosOp; aquí solo se usa para etiquetar
// una transición como avance o retroceso. Las transiciones permitidas las
// decide el backend.
const rangoEstado = (estado: any) => rangoFlujo(estado?.name);

const transiciones = computed(() => {
    const actual = rangoEstado(estadoActual.value);
    return opcionesPermitidas.value
        .slice()
        .sort((a: any, b: any) => rangoEstado(a) - rangoEstado(b))
        .map((estado: any) => ({ estado, avanza: rangoEstado(estado) > actual }));
});

const transicionesAvance = computed(() => transiciones.value.filter((t) => t.avanza));
const transicionesRetroceso = computed(() => transiciones.value.filter((t) => !t.avanza));

const estaElegido = (id: any) => String(form.estado_op_id) === String(id);

// Re-hacer clic sobre el estado elegido vuelve a "sin cambio seleccionado".
const elegirEstado = (id: any) => {
    form.estado_op_id = estaElegido(id) ? '' : id;
};

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
    form.estado_op_id !== '' && estadoSeleccionado.value?.name === 'Cerrada'
);

// ── Cierre de la OP ──────────────────────────────────────────────────────
// El resumen de cierre se muestra al elegir «Cerrada» y también cuando la
// recepción queda completa. Los valores se calculan solos antes de guardar.
const mostrarCierre = computed(() =>
    estadoSeleccionado.value?.name === 'Cerrada' || seCierraAutomatica.value
);

const avanceRecepcionGuardado = ref(false);

// Fila cuya suma se está animando cuando el Enter encuentra una fila que no cuadra.
const filaSumaAnimada = ref<string | null>(null);
let temporizadorSumaAnimada: ReturnType<typeof setTimeout> | null = null;
let tokenSumaAnimada = 0;

// La fila completa se tiñe de rojo durante 1 s y luego tarda 3 s en volver
// a su color original (4 s en total).
const filaErrorAnimada = ref<string | null>(null);
let temporizadorErrorFila: ReturnType<typeof setTimeout> | null = null;
let tokenErrorFila = 0;

const claveFilaRecepcion = (fila: any, indice: number): string =>
    String(fila?.distribucion_id ?? indice);

const animarSumaFila = async (fila: any, indice: number) => {
    const token = ++tokenSumaAnimada;

    if (temporizadorSumaAnimada) {
        clearTimeout(temporizadorSumaAnimada);
        temporizadorSumaAnimada = null;
    }

    // Se quita y se vuelve a poner para que la animación se reinicie si el
    // usuario presiona Enter varias veces sobre la misma fila.
    filaSumaAnimada.value = null;
    await nextTick();

    if (token !== tokenSumaAnimada) return;

    filaSumaAnimada.value = claveFilaRecepcion(fila, indice);
    temporizadorSumaAnimada = setTimeout(() => {
        if (token !== tokenSumaAnimada) return;

        filaSumaAnimada.value = null;
        temporizadorSumaAnimada = null;
    }, 650);
};

const animarErrorFila = async (fila: any, indice: number) => {
    const token = ++tokenErrorFila;

    if (temporizadorErrorFila) {
        clearTimeout(temporizadorErrorFila);
        temporizadorErrorFila = null;
    }

    filaErrorAnimada.value = null;
    await nextTick();

    if (token !== tokenErrorFila) return;

    filaErrorAnimada.value = claveFilaRecepcion(fila, indice);
    temporizadorErrorFila = setTimeout(() => {
        if (token !== tokenErrorFila) return;

        filaErrorAnimada.value = null;
        temporizadorErrorFila = null;
    }, 4000);
};

watch(
    () => [
        form.fecha_recepcion,
        form.fecha_compromiso,
        form.recepciones.map((f: any) => [
            f.cantidad_aprobada,
            f.cantidad_en_reparacion,
            f.cantidad_no_recuperable,
        ]),
    ],
    () => { avanceRecepcionGuardado.value = false; },
    { deep: true }
);

const guardarAvanceRecepcion = () => {
    form
        .transform((datos) => ({
            fecha_recepcion: datos.fecha_recepcion || null,
            // La fecha de compromiso es una sola para todos los pares en
            // reparación, igual que en el guardado definitivo.
            fecha_compromiso: datos.fecha_compromiso || null,
            recepciones: datos.recepciones.map((fila: any) => ({
                distribucion_id: fila.distribucion_id,
                cantidad_aprobada: numeroONulo(fila.cantidad_aprobada),
                cantidad_en_reparacion: numeroONulo(fila.cantidad_en_reparacion),
                cantidad_no_recuperable: numeroONulo(fila.cantidad_no_recuperable),
            })),
        }))
        .put(route('OrdenProduccion.recepcionBorrador', props.op?.id), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => { avanceRecepcionGuardado.value = true; },
            onError: () => { avanceRecepcionGuardado.value = false; },
        });
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

// La recepción de calidad se muestra cuando la OP ya está en "Recibiendo",
// cuando se selecciona "Recibiendo" como nuevo estado, o cuando ya tiene al
// menos un producto recibido (la OP cuenta como Recibiendo aunque el estado
// guardado todavía no lo refleje: el formulario sale sin hacer clic).
const esRecepcion = computed(() => estadoSeleccionado.value?.name === 'Recibiendo');
const tieneProductosRecibidos = computed(() =>
    ((props.op as any)?.recepciones_detalle || []).some((recepcion: any) =>
        (Number(recepcion.cantidad_recibida) || 0) > 0
        || (Number(recepcion.cantidad_aprobada) || 0) > 0
        || (Number(recepcion.cantidad_en_reparacion) || 0) > 0
        || (Number(recepcion.cantidad_no_recuperable) || 0) > 0)
);
const mostrarTablaRecepcion = computed(() =>
    estadoActual.value?.name === 'Recibiendo'
    || esRecepcion.value
    || tieneProductosRecibidos.value
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

const kitDescuadrado = (kit: any): boolean => {
    // La cantidad preparada es opcional: si no está registrada, se asume la
    // cantidad requerida y no se marca como descuadre.
    if (
        kit?.cantidad_preparada === null
        || kit?.cantidad_preparada === undefined
        || kit?.cantidad_preparada === ''
    ) {
        return false;
    }

    return Math.abs(Number(kit.cantidad_preparada) - Number(kit.cantidad_requerida || 0)) > 0.004;
};

const kitSinStock = (kit: any): boolean =>
    Number(kit.cantidad_disponible || 0) < Number(kit.cantidad_requerida || 0);

const problemasResta = computed(() =>
    kitsResta.value.filter((kit) => kitDescuadrado(kit) || kitSinStock(kit))
);

const cantidadKit = (valor: any) => zilef_nuber_format(valor ?? 0, 2, false);

// Moneda sin centavos cuando el valor es redondo, como en el detalle del estado.
const dinero = (valor: any): string => {
    if (valor === null || valor === undefined || valor === '') return '—';
    const numero = Number(valor);
    if (!Number.isFinite(numero)) return '—';
    return zilef_nuber_format(numero, Math.round(numero * 100) % 100 === 0 ? 0 : 2, true);
};

// Lo preparado es la cantidad que hay en el material; si se registró una
// preparación explícita en el kit, esa manda.
const cantidadPreparadaKit = (kit: any) =>
    cantidadKit(kit?.cantidad_preparada ?? kit?.cantidad_disponible);

// Lo que queda después de restar la requerida.
const cantidadRestaKit = (kit: any) => {
    if (kitDescuadrado(kit) || kitSinStock(kit)) return '—';
    const preparada = Number(kit?.cantidad_preparada ?? kit?.cantidad_disponible ?? 0);
    return cantidadKit(preparada - Number(kit?.cantidad_requerida ?? 0));
};

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

    return tallasOp.value.map((talla: any) => {
        const clave = claveRecepcion(talla.producto_id, talla.talla);
        return nuevaFila(talla, existentes.get(clave));
    });
});

const fechaRecepcionInicial = () => {
    const existente = ((props.op as any)?.recepciones_detalle || [])
        .find((recepcion: any) => recepcion?.fecha_recepcion);

    return existente?.fecha_recepcion
        ? String(existente.fecha_recepcion).slice(0, 10)
        : hoy();
};

// Una sola fecha de compromiso pendiente para todos los pares que queden en
// reparación en esta recepción.
const fechaCompromisoInicial = () => {
    const existente = ((props.op as any)?.compromisos_detalle || [])
        .find((compromiso: any) => compromiso?.fecha_compromiso);

    return existente?.fecha_compromiso
        ? String(existente.fecha_compromiso).slice(0, 10)
        : '';
};

const abrirModal = () => {
    form.errors = {} as any;
    form.reset();
    form.estado_op_id = '';
    form.estado_op_secundario_id = (props.op as any)?.estado_op_secundario_id ?? '';
    form.fecha_recepcion = fechaRecepcionInicial();
    form.fecha_compromiso = fechaCompromisoInicial();
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

const filaSuperada = (fila: any): boolean => sumaFila(fila) > Number(fila.esperada);
const filaCuadra = (fila: any): boolean => sumaFila(fila) === Number(fila.esperada);

const filaValida = (fila: any): boolean => {
    const sinNegativos = [fila.cantidad_aprobada, fila.cantidad_en_reparacion, fila.cantidad_no_recuperable]
        .every(valorValido);
    return sinNegativos && !filaSuperada(fila);
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
const filasListas = computed(() => form.recepciones.every(filaCuadra));

const puedeGuardar = computed(() => {
    if (destinoEsCerrada.value && totalEnReparacionEfectivo.value > 0) return false;

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

    if (mostrarTablaRecepcion.value && !form.fecha_recepcion) return false;

    // Si queda algo en reparación, hace falta una única fecha de compromiso.
    if (faltaFechaCompromiso.value) return false;

    if (!mostrarTablaRecepcion.value) return true;
    if (!form.recepciones.length) return true;
    return filasListas.value && !clavesDuplicadas.value.length;
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

// Si la tabla de recepción está visible manda lo digitado; si no, lo que ya
// quedó guardado en la OP.
const filasCosto = computed(() => {
    if (form.recepciones.length) return form.recepciones;

    return ((props.op as any)?.recepciones_detalle || []).map((recepcion: any) => ({
        producto_id: recepcion.producto_id,
        cantidad_aprobada: recepcion.cantidad_aprobada,
        cantidad_en_reparacion: recepcion.cantidad_en_reparacion,
        cantidad_no_recuperable: recepcion.cantidad_no_recuperable,
    }));
});

// Costo de la OP con lo que se va digitando: aprobadas y no recuperables valen
// sus materiales, las en reparación aún no se suman.
const costoRecepcion = computed(() =>
    resumenRecepcionCosto(
        filasCosto.value,
        (props.op as any)?.costo_unitario_productos as Record<string, number> | undefined
    )
);

// El cierre se calcula con lo recibido; no se digita.
const costoRealFinal = computed(() => costoRecepcion.value.costoOp);
const perdidasMermas = computed(() => costoRecepcion.value.costoNoRecuperables);
const costoCalculadoOp = computed(() => Number((props.op as any)?.costo_fichas) || 0);

const totalAprobadas = computed(() =>
    form.recepciones.reduce(
        (suma, fila: any) => suma + (numeroONulo(fila.cantidad_aprobada) ?? 0),
        0
    )
);

const totalNoRecuperables = computed(() =>
    form.recepciones.reduce(
        (suma, fila: any) => suma + (numeroONulo(fila.cantidad_no_recuperable) ?? 0),
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

// Los pares que se van a reparación comparten una sola fecha de compromiso.
const filasConReparacion = computed(() =>
    form.recepciones.filter((fila: any) => (numeroONulo(fila.cantidad_en_reparacion) ?? 0) > 0)
);

const faltaFechaCompromiso = computed(() =>
    filasConReparacion.value.length > 0 && !form.fecha_compromiso
);

// Cuando cada talla cuadró con lo esperado y no quedó nada en reparación, la OP
// ya no tiene pendientes: el sistema la cierra al guardar (el usuario no elige
// «Cerrada»).
const seCierraAutomatica = computed(() =>
    destinoEsRecibiendo.value
    && form.recepciones.length > 0
    && filasListas.value
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
            fecha_recepcion: mostrarTablaRecepcion.value ? (datos.fecha_recepcion || null) : undefined,
            recepciones: datos.recepciones.map((fila: any) => ({
                distribucion_id: fila.distribucion_id,
                cantidad_aprobada: numeroONulo(fila.cantidad_aprobada),
                cantidad_en_reparacion: numeroONulo(fila.cantidad_en_reparacion),
                cantidad_no_recuperable: numeroONulo(fila.cantidad_no_recuperable),
                // La fecha es única para toda la recepción: se replica en cada
                // talla que queda en reparación.
                fecha_compromiso: (numeroONulo(fila.cantidad_en_reparacion) ?? 0) > 0
                    ? (datos.fecha_compromiso || null)
                    : null,
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
        }))
        .put(route('OrdenProduccion.cambiarEstado', props.op?.id), {
            preserveScroll: true,
            onSuccess: () => emit("close"),
            onError: () => null,
        });
};

const enfocarFilaRecepcion = (indice: number) => {
    const input = document.getElementById(`recepcion-aprobada-${indice}`) as HTMLInputElement | null;
    input?.focus();
    input?.select();
};

// Enter recorre la recepción fila por fila. Si la fila no cuadra, se anima su
// suma y no se avanza; en la última fila, Enter guarda como el botón.
const manejarEnterRecepcion = (indice: number) => {
    if (!mostrarTablaRecepcion.value) return;

    const fila = form.recepciones[indice];
    if (!fila) return;

    if (!filaCuadra(fila)) {
        animarSumaFila(fila, indice);
        animarErrorFila(fila, indice);
        return;
    }

    if (indice < form.recepciones.length - 1) {
        enfocarFilaRecepcion(indice + 1);
        return;
    }

    if (!form.processing && puedeEnviar.value && puedeGuardar.value) {
        guardar();
    }
};

watch(() => props.show, (visible) => {
    if (visible) {
        abrirModal();
        posicionarScrollRecepcion();
        return;
    }
    soltarScrollRecepcion();
});

// ── Scroll de la recepción ───────────────────────────────────────────────
// Al abrir el modal se restaura el scroll que el usuario dejó guardado; si no
// hay uno, salta a la primera fila vacía. La posición se guarda en localStorage
// por OP, así al reabrir se retoma donde se quedó.
const claveScrollRecepcion = () => `op-recepcion-scroll-${(props.op as any)?.id ?? ''}`;

const primeraFilaVacia = (): number =>
    form.recepciones.findIndex((fila: any) =>
        [fila.cantidad_aprobada, fila.cantidad_en_reparacion, fila.cantidad_no_recuperable]
            .every((valor) => numeroONulo(valor) === null));

let regionScrollRecepcion: HTMLElement | null = null;
let guardandoScrollRecepcion: (() => void) | null = null;

const soltarScrollRecepcion = () => {
    if (regionScrollRecepcion && guardandoScrollRecepcion) {
        regionScrollRecepcion.removeEventListener('scroll', guardandoScrollRecepcion);
    }
    regionScrollRecepcion = null;
    guardandoScrollRecepcion = null;
};

const posicionarScrollRecepcion = async () => {
    if (!mostrarTablaRecepcion.value || !form.recepciones.length) return;

    await nextTick();

    const indice = primeraFilaVacia();
    const input = indice >= 0 ? document.getElementById(`recepcion-aprobada-${indice}`) : null;
    const region = (input?.closest('[scroll-region]')
        ?? document.querySelector('[scroll-region]')) as HTMLElement | null;
    if (!region) return;

    const guardado = Number(localStorage.getItem(claveScrollRecepcion()));
    if (guardado > 0) {
        region.scrollTop = guardado;
    } else if (input) {
        input.scrollIntoView({ block: 'center' });
    }

    guardandoScrollRecepcion = () => {
        localStorage.setItem(claveScrollRecepcion(), String(region.scrollTop));
    };
    regionScrollRecepcion = region;
    region.addEventListener('scroll', guardandoScrollRecepcion);
};
</script>

<template>
    <section class="space-y-6">
        <Modal :show="props.show" @close="emit('close')" :maxWidth="(mostrarTablaRecepcion || mostrarProcesoExterno || mostrarRegresoReal || mostrarRestaMateriales || mostrarCierre) ? 'xl6' : 'md'">
            <form class="p-6" @submit.prevent="guardar">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Cambiar estado
                    <span v-if="props.op?.numero_op" class="text-sm font-normal text-gray-500 dark:text-gray-400">
                        — {{ props.op.numero_op }}
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

                    <!-- Recepción de calidad: totales por columna y costo real final. -->
                    <div
                        v-if="mostrarTablaRecepcion"
                        class="mt-3 border-t border-gray-200 pt-3 dark:border-gray-700"
                    >
                        <div class="grid gap-x-4 gap-y-2 sm:grid-cols-4">
                            <div>
                                <span class="block text-[11px] uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Aprobadas
                                </span>
                                <span class="font-semibold tabular-nums text-emerald-700 dark:text-emerald-300">
                                    {{ costoRecepcion.aprobadas }}
                                </span>
                            </div>
                            <div>
                                <span class="block text-[11px] uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    En reparación
                                </span>
                                <span class="font-semibold tabular-nums text-amber-700 dark:text-amber-300">
                                    {{ costoRecepcion.enReparacion }}
                                </span>
                            </div>
                            <div>
                                <span class="block text-[11px] uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    No recuperables
                                </span>
                                <span class="font-semibold tabular-nums text-rose-700 dark:text-rose-300">
                                    {{ costoRecepcion.noRecuperables }}
                                </span>
                            </div>
                            <div>
                                <span class="block text-[11px] uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Costo real final
                                </span>
                                <span class="font-semibold tabular-nums text-gray-900 dark:text-gray-100">
                                    {{ dinero(costoRealFinal) }}
                                </span>
                            </div>
                        </div>
                        <p class="mt-2 text-[11px] text-gray-500 dark:text-gray-400">
                            Los {{ costoRecepcion.aprobadas }} aprobados ({{ dinero(costoRecepcion.costoAprobadas) }})
                            y los {{ costoRecepcion.noRecuperables }} no recuperables ({{ dinero(costoRecepcion.costoNoRecuperables) }})
                            valen sus materiales de la OP; los {{ costoRecepcion.enReparacion }} en reparación no se suman
                            hasta que regresen aprobados.
                        </p>
                    </div>
                </div>

                <div class="mt-4">
                    <InputLabel value="Nuevo estado" />

                    <p v-if="!opcionesPermitidas.length" class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ estadoActual?.name === 'Recibiendo'
                            ? 'Aquí se actualiza la recepción de calidad. La OP se cierra sola cuando cada talla cuadra con lo esperado y no quedan pares en reparación.'
                            : 'Este estado es final: no admite transiciones.' }}
                    </p>

                    <div v-else class="mt-2 space-y-3">
                        <div v-if="transicionesAvance.length">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">
                                Avanzar
                            </p>
                            <div class="mt-1.5 flex flex-wrap gap-2">
                                <button v-for="transicion in transicionesAvance" :key="transicion.estado.id"
                                    type="button" @click="elegirEstado(transicion.estado.id)"
                                    :aria-pressed="estaElegido(transicion.estado.id)"
                                    :title="transicion.estado.regla_clave || ''"
                                    class="inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-sm font-medium transition-colors"
                                    :class="estaElegido(transicion.estado.id)
                                        ? 'border-emerald-600 bg-emerald-600 text-white'
                                        : 'border-emerald-200 bg-white text-emerald-800 hover:border-emerald-400 dark:border-emerald-500/30 dark:bg-gray-900/40 dark:text-emerald-200 dark:hover:border-emerald-400/60'">
                                    {{ transicion.estado.name }}
                                    <span aria-hidden="true">&rarr;</span>
                                </button>
                            </div>
                        </div>

                        <div v-if="transicionesRetroceso.length">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-amber-700 dark:text-amber-400">
                                Retroceder
                            </p>
                            <div class="mt-1.5 flex flex-wrap gap-2">
                                <button v-for="transicion in transicionesRetroceso" :key="transicion.estado.id"
                                    type="button" @click="elegirEstado(transicion.estado.id)"
                                    :aria-pressed="estaElegido(transicion.estado.id)"
                                    :title="transicion.estado.regla_clave || ''"
                                    class="inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-sm font-medium transition-colors"
                                    :class="estaElegido(transicion.estado.id)
                                        ? 'border-amber-600 bg-amber-600 text-white'
                                        : 'border-amber-200 bg-white text-amber-800 hover:border-amber-400 dark:border-amber-500/30 dark:bg-gray-900/40 dark:text-amber-200 dark:hover:border-amber-400/60'">
                                    <span aria-hidden="true">&larr;</span>
                                    {{ transicion.estado.name }}
                                </button>
                            </div>
                        </div>

                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Desde «{{ estadoActual?.name || 'Sin estado' }}». Haga clic de nuevo sobre un estado para dejar sin cambio.
                        </p>
                    </div>

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
                            en el histórico de movimientos. Si registras una cantidad preparada, debe coincidir con
                            la requerida.
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
                                        <th scope="col" class="w-24 px-3 py-2 text-right font-semibold">Quedan</th>
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
                                            {{ cantidadPreparadaKit(kit) }}
                                        </td>
                                        <td class="px-3 py-2 text-right font-semibold tabular-nums text-gray-900 dark:text-gray-100">
                                            {{ cantidadRestaKit(kit) }}
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
                                                Listo
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
                            Todos los pares en reparación comparten una sola fecha de compromiso pendiente.
                        </p>
                        <p
                            v-if="seCierraAutomatica"
                            class="mt-2 inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300"
                        >
                            Recepción completa y sin pares en reparación: la OP se cierra al guardar.
                        </p>
                        <p
                            v-else-if="filasConReparacion.length"
                            class="mt-2 inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-500/15 dark:text-amber-300"
                        >
                            Quedan {{ totalEnReparacion }} {{ totalEnReparacion === 1 ? 'par' : 'pares' }}
                            en reparación: la OP seguirá en «Recibiendo» hasta que se cumpla el compromiso.
                        </p>
                    </div>

                    <div class="mt-3 grid gap-3 sm:grid-cols-2 sm:items-end xl:grid-cols-[minmax(0,220px)_minmax(0,240px)_1fr]">
                        <div>
                            <InputLabel for="fecha_recepcion" value="Fecha de recepción *" />
                            <TextInput
                                id="fecha_recepcion"
                                type="date"
                                class="mt-1 block w-full"
                                v-model="form.fecha_recepcion"
                            />
                            <InputError class="mt-1" :message="form.errors.fecha_recepcion" />
                        </div>
                        <div v-if="totalEnReparacion > 0">
                            <InputLabel for="fecha_compromiso" value="Compromiso pendiente *" />
                            <TextInput
                                id="fecha_compromiso"
                                type="date"
                                class="mt-1 block w-full"
                                v-model="form.fecha_compromiso"
                            />
                            <InputError class="mt-1" :message="form.errors.fecha_compromiso" />
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 sm:pb-2">
                            <template v-if="totalEnReparacion > 0">
                                Una sola fecha para los {{ totalEnReparacion }}
                                {{ totalEnReparacion === 1 ? 'par' : 'pares' }} en reparación.
                            </template>
                            <template v-else>
                                Se guardará en todas las tallas de esta recepción.
                            </template>
                        </p>
                    </div>

                    <div class="mt-3 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[840px] text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200 bg-gray-50 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400">
                                        <th scope="col" class="px-3 py-2 font-semibold">Producto</th>
                                        <th scope="col" class="w-24 px-3 py-2 font-semibold">Talla</th>
                                        <th scope="col" class="w-20 px-3 py-2 text-right font-semibold">Esperada</th>
                                        <th scope="col" class="w-24 px-3 py-2 font-semibold">Aprobada</th>
                                        <th scope="col" class="w-24 px-3 py-2 font-semibold">En reparación</th>
                                        <th scope="col" class="w-24 px-3 py-2 font-semibold">No recuperable</th>
                                        <th scope="col" class="w-20 px-3 py-2 text-right font-semibold">Recibida</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                                    <tr
                                        v-for="(fila, indice) in form.recepciones"
                                        :key="fila.distribucion_id ?? indice"
                                        :class="filaErrorAnimada === claveFilaRecepcion(fila, indice)
                                            ? 'fila-recibida-error'
                                            : ''"
                                    >
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
                                                :id="`recepcion-aprobada-${indice}`"
                                                type="number" min="0" step="1" placeholder="0"
                                                class="block h-9 w-20 px-2 text-right tabular-nums"
                                                v-model="fila.cantidad_aprobada"
                                                @keydown.enter.prevent="manejarEnterRecepcion(indice)"
                                                :aria-label="`Cantidad aprobada talla ${fila.talla}`"
                                            />
                                        </td>
                                        <td class="px-3 py-2">
                                            <TextInput
                                                type="number" min="0" step="1" placeholder="0"
                                                class="block h-9 w-20 px-2 text-right tabular-nums"
                                                v-model="fila.cantidad_en_reparacion"
                                                @keydown.enter.prevent="manejarEnterRecepcion(indice)"
                                                :aria-label="`Cantidad en reparación talla ${fila.talla}`"
                                            />
                                        </td>
                                        <td class="px-3 py-2">
                                            <TextInput
                                                type="number" min="0" step="1" placeholder="0"
                                                class="block h-9 w-20 px-2 text-right tabular-nums"
                                                v-model="fila.cantidad_no_recuperable"
                                                @keydown.enter.prevent="manejarEnterRecepcion(indice)"
                                                :aria-label="`Cantidad no recuperable talla ${fila.talla}`"
                                            />
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            <span
                                                class="inline-block font-semibold tabular-nums"
                                                :class="[
                                                    filaSuperada(fila)
                                                        ? 'text-rose-600 dark:text-rose-400'
                                                        : filaCuadra(fila)
                                                            ? 'text-emerald-600 dark:text-emerald-400'
                                                            : 'text-gray-900 dark:text-gray-100',
                                                    filaSumaAnimada === claveFilaRecepcion(fila, indice)
                                                        ? 'suma-recibida-pop'
                                                        : '',
                                                ]"
                                                :title="filaSuperada(fila)
                                                    ? `Supera el máximo (${fila.esperada})`
                                                    : filaCuadra(fila)
                                                        ? 'Cuadra exacto'
                                                        : `Máximo: ${fila.esperada}`"
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
                                <tfoot v-if="form.recepciones.length">
                                    <tr class="border-t border-gray-200 bg-gray-50 text-[11px] font-semibold uppercase tracking-wider text-gray-600 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-300">
                                        <td class="px-3 py-2" colspan="2">Totales</td>
                                        <td class="px-3 py-2 text-right tabular-nums">{{ totalEsperado }}</td>
                                        <td class="px-3 py-2 tabular-nums text-emerald-700 dark:text-emerald-300">{{ totalAprobadas }}</td>
                                        <td class="px-3 py-2 tabular-nums text-amber-700 dark:text-amber-300">{{ totalEnReparacion }}</td>
                                        <td class="px-3 py-2 tabular-nums text-rose-700 dark:text-rose-300">{{ totalNoRecuperables }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums">{{ totalRecibido }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <div v-if="form.recepciones.length" class="mt-2 flex flex-wrap items-center justify-between gap-2 text-sm">
                        <p class="text-gray-500 dark:text-gray-400">
                            Recibidas:
                            <span class="font-semibold tabular-nums"
                                :class="totalRecibido > totalEsperado
                                    ? 'text-rose-600 dark:text-rose-400'
                                    : totalRecibido === totalEsperado
                                        ? 'text-emerald-600 dark:text-emerald-400'
                                        : 'text-gray-900 dark:text-gray-100'">
                                {{ totalRecibido }}
                            </span>
                            de
                            <span class="font-semibold tabular-nums text-gray-900 dark:text-gray-100">{{ totalEsperado }}</span>
                        </p>
                        <div class="flex items-center gap-3">
                            <p v-if="form.recepciones.some(filaSuperada)" class="text-rose-600 dark:text-rose-400">
                                Una o más filas superan la cantidad esperada.
                            </p>
                            <p v-else-if="!filasListas" class="text-amber-600 dark:text-amber-400">
                                Puede guardar avance o completar las cantidades para enviar.
                            </p>
                            <p v-else-if="faltaFechaCompromiso" class="text-amber-600 dark:text-amber-400">
                                Indique la fecha de compromiso pendiente para los pares en reparación.
                            </p>
                            <span v-if="avanceRecepcionGuardado" class="text-xs font-medium text-emerald-700 dark:text-emerald-300">
                                Avance guardado
                            </span>
                            <SecondaryButton
                                v-if="!form.recepciones.some(filaSuperada)"
                                type="button"
                                :disabled="form.processing || !form.fecha_recepcion"
                                @click="guardarAvanceRecepcion"
                            >
                                Guardar avance
                            </SecondaryButton>
                        </div>
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

                <!-- Cierre de la OP: valores calculados, sin digitación -->
                <div v-if="mostrarCierre" class="mt-5">
                    <div class="rounded-lg border border-emerald-200 bg-emerald-50/70 px-4 py-3 dark:border-emerald-800 dark:bg-emerald-900/20" role="status">
                        <p class="text-sm font-medium text-emerald-900 dark:text-emerald-100">
                            Cierre de la orden de producción
                        </p>
                        <p class="mt-1 text-xs text-emerald-800/80 dark:text-emerald-200/80">
                            Las pérdidas en mermas y el costo real final se calculan automáticamente
                            con los productos recibidos y los costos guardados en la OP.
                        </p>
                    </div>

                    <dl class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div class="rounded-lg border border-rose-200 bg-rose-50/50 p-3 dark:border-rose-500/30 dark:bg-rose-500/10">
                            <dt class="text-[11px] font-semibold uppercase tracking-wider text-rose-700 dark:text-rose-300">
                                Pérdidas en mermas
                            </dt>
                            <dd class="mt-1 text-lg font-semibold tabular-nums text-rose-800 dark:text-rose-200">
                                {{ dinero(perdidasMermas) }}
                            </dd>
                            <p class="mt-1 text-[11px] text-rose-700/80 dark:text-rose-300/80">
                                {{ costoRecepcion.noRecuperables }}
                                {{ costoRecepcion.noRecuperables === 1 ? 'producto no recuperable' : 'productos no recuperables' }}
                                por su costo unitario.
                            </p>
                        </div>

                        <div class="rounded-lg border border-gray-200 bg-gray-50/70 p-3 dark:border-gray-700 dark:bg-gray-900/40">
                            <dt class="text-[11px] font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                Costo real final
                            </dt>
                            <dd class="mt-1 text-lg font-semibold tabular-nums text-gray-900 dark:text-gray-100">
                                {{ dinero(costoRealFinal) }}
                            </dd>
                            <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                                Suma de los productos aprobados y no recuperables recibidos.
                            </p>
                        </div>

                        <div class="rounded-lg border border-indigo-200 bg-indigo-50/50 p-3 dark:border-indigo-500/30 dark:bg-indigo-500/10">
                            <dt class="text-[11px] font-semibold uppercase tracking-wider text-indigo-700 dark:text-indigo-300">
                                Costo calculado al crear la OP
                            </dt>
                            <dd class="mt-1 text-lg font-semibold tabular-nums text-indigo-800 dark:text-indigo-200">
                                {{ dinero(costoCalculadoOp) }}
                            </dd>
                            <p class="mt-1 text-[11px] text-indigo-700/80 dark:text-indigo-300/80">
                                Al recibir todos los productos, ambos valores deben coincidir.
                            </p>
                        </div>
                    </dl>
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

<style scoped>
@keyframes fila-recibida-error {
    0%,
    25% {
        background-color: var(--fila-error-bg);
    }
    100% {
        background-color: transparent;
    }
}

.fila-recibida-error {
    --fila-error-bg: rgb(252 165 165);
    animation: fila-recibida-error 4s linear;
}

:global(.dark) .fila-recibida-error {
    --fila-error-bg: rgb(153 27 27 / 0.6);
}

@keyframes suma-recibida-pop {
    0% {
        transform: scale(1);
    }
    35% {
        transform: scale(1.45);
        color: rgb(225 29 72);
    }
    65% {
        transform: scale(1.45);
        color: rgb(225 29 72);
    }
    100% {
        transform: scale(1);
    }
}

.suma-recibida-pop {
    animation: suma-recibida-pop 620ms ease-out;
    transform-origin: right center;
    will-change: transform;
}

@media (prefers-reduced-motion: reduce) {
    .suma-recibida-pop {
        animation: none;
    }
}
</style>
