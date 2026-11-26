<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import PrimaryButton from '@/components/PrimaryButton.vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { computed, onMounted, reactive, ref, watch } from 'vue';

import Create from '@/pages/OrdenProduccion/Create.vue';
import Edit from '@/pages/OrdenProduccion/Edit.vue';
import Delete from '@/pages/OrdenProduccion/Delete.vue';
import DistribucionTallas from '@/pages/OrdenProduccion/DistribucionTallas.vue';
import KitProcesoOPModal from '@/pages/OrdenProduccion/KitProcesoOPModal.vue';
import CambiarEstadoModal from '@/pages/OrdenProduccion/CambiarEstadoModal.vue';
import DetalleEstadoModal from '@/pages/OrdenProduccion/DetalleEstadoModal.vue';
import MaterialesOPModal from '@/pages/OrdenProduccion/MaterialesOPModal.vue';

import {
    ArrowPathIcon,
    ArrowRightIcon,
    ArrowUturnLeftIcon,
    CalendarDaysIcon,
    ChartBarIcon,
    CheckCircleIcon,
    ChevronDoubleLeftIcon,
    ChevronDoubleRightIcon,
    ChevronDownIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    ChevronUpDownIcon,
    ChevronUpIcon,
    ClipboardDocumentCheckIcon,
    ClipboardDocumentListIcon,
    CubeIcon,
    EllipsisVerticalIcon,
    ExclamationTriangleIcon,
    FunnelIcon,
    MagnifyingGlassIcon,
    PencilIcon,
    PlusIcon,
    TrashIcon,
    WrenchScrewdriverIcon,
    XMarkIcon,
} from '@heroicons/vue/24/solid';

import { formatDate, formatdinero, zilef_nuber_format } from '@/global.ts';

const props = defineProps({
    ordenes: { type: Array as () => any[] | null, default: null },
    total: Number,
    breadcrumbs: Array as () => Array<{ label?: string; href?: string }>,
    perPage: Number,
    title: String,
    numberPermissions: Number,
    losSelect: Object,
    borradorUltimo: Object,
    mostrarRestaMateriales: { type: Boolean, default: false },
    metodoCostos: Number,
    metodoCostosLabel: String,
});

/* --------------------------------------------------------------------------
 * Tabla 100% en cliente: los datos llegan una sola vez y aquí se buscan,
 * filtran, ordenan y paginan sin volver al backend. Solo las acciones de
 * cada fila (editar, estado, tallas, kit, salida, eliminar) hablan con él.
 * ------------------------------------------------------------------------ */

const filas = computed<any[]>(() => (props.ordenes as any[]) ?? []);

const busqueda = ref('');
const estadoFiltro = ref<number | 'todos'>('todos');
const responsableFiltro = ref<number | 'todos'>('todos');
const tallerFiltro = ref<number | 'todos'>('todos');
const sortKey = ref('numero_op');
const sortDir = ref<'asc' | 'desc'>('desc');
const porPagina = ref<number>(props.perPage ?? 10);
const pagina = ref(1);

const normalizar = (valor: any): string =>
    String(valor ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/\s+/g, ' ')
        .trim();

const esCerrada = (op: any): boolean => normalizar(op?.estado_nombre) === 'cerrada';

const preparadas = computed(() =>
    filas.value.map((op: any) => ({
        ...op,
        // La búsqueda libre solo cubre código de orden y nombres de producto;
        // estado y responsable tienen su propio filtro.
        _busqueda: normalizar(
            [
                op.numero_op,
                op.producto_nombre,
                op.productos_nombres,
                op.materiales_nombres,
                ...(op.materiales_objetivo || []).map((m: any) => m.nombre),
            ].join(' '),
        ),
    })),
);

const baseFiltrada = computed(() =>
    preparadas.value.filter((op: any) => {
        if (busqueda.value.trim() && !op._busqueda.includes(normalizar(busqueda.value))) {
            return false;
        }
        if (responsableFiltro.value !== 'todos' && Number(op.user_id) !== Number(responsableFiltro.value)) {
            return false;
        }
        if (tallerFiltro.value !== 'todos' && Number(op.taller_id) !== Number(tallerFiltro.value)) {
            return false;
        }
        return true;
    }),
);

const estadosDisponibles = computed(() => {
    const orden = ((props.losSelect as any)?.estado_op_id ?? []) as any[];
    const ordenMap = new Map<number, number>();
    orden.forEach((e: any, i: number) => ordenMap.set(Number(e.id), i));

    // Conteos dentro del alcance actual (búsqueda, responsable y taller).
    const conteos = new Map<number, number>();
    baseFiltrada.value.forEach((op: any) => {
        const id = Number(op.estado_op_id);
        if (!id) return;
        conteos.set(id, (conteos.get(id) ?? 0) + 1);
    });

    // Las opciones se arman con todas las órdenes para que la lista no cambie
    // de tamaño al mover los demás filtros.
    const grupos = new Map<number, { id: number; nombre: string; count: number }>();
    filas.value.forEach((op: any) => {
        const id = Number(op.estado_op_id);
        if (!id || grupos.has(id)) return;
        grupos.set(id, {
            id,
            nombre: op.estado_nombre || `Estado #${id}`,
            count: 0,
        });
    });
    grupos.forEach((grupo) => {
        grupo.count = conteos.get(grupo.id) ?? 0;
    });

    return Array.from(grupos.values()).sort((a, b) => {
        const oa = ordenMap.has(a.id) ? ordenMap.get(a.id)! : 999;
        const ob = ordenMap.has(b.id) ? ordenMap.get(b.id)! : 999;
        return oa - ob || a.nombre.localeCompare(b.nombre, 'es');
    });
});

const estadoCerradaId = computed(() =>
    estadosDisponibles.value.find((estado) => normalizar(estado.nombre) === 'cerrada')?.id ?? null,
);

// Las OP cerradas se ocultan por defecto. Solo aparecen cuando el usuario
// filtra explícitamente por el estado "Cerrada".
const filtrandoCerradas = computed(
    () => estadoCerradaId.value !== null && Number(estadoFiltro.value) === estadoCerradaId.value,
);

const cerradasOcultas = computed(
    () => !filtrandoCerradas.value
        && estadoFiltro.value === 'todos'
        && baseFiltrada.value.some((op: any) => esCerrada(op)),
);

const mostrarCerradas = () => {
    if (estadoCerradaId.value !== null) {
        estadoFiltro.value = estadoCerradaId.value;
    }
};

const filtradas = computed(() =>
    baseFiltrada.value.filter((op: any) => {
        if (esCerrada(op) && !filtrandoCerradas.value) return false;
        if (estadoFiltro.value === 'todos') return true;
        return Number(op.estado_op_id) === Number(estadoFiltro.value);
    }),
);

const talleresDisponibles = computed(() => {
    const mapa = new Map<number, string>();
    filas.value.forEach((op: any) => {
        if (op.taller_id) {
            mapa.set(Number(op.taller_id), op.taller_nombre || `Taller #${op.taller_id}`);
        }
    });
    return Array.from(mapa.entries())
        .map(([id, nombre]) => ({ id, nombre }))
        .sort((a, b) => a.nombre.localeCompare(b.nombre, 'es'));
});

const responsablesDisponibles = computed(() => {
    const mapa = new Map<number, string>();
    filas.value.forEach((op: any) => {
        if (op.user_id) {
            mapa.set(Number(op.user_id), op.responsable_nombre || `Usuario #${op.user_id}`);
        }
    });
    return Array.from(mapa.entries())
        .map(([id, nombre]) => ({ id, nombre }))
        .sort((a, b) => a.nombre.localeCompare(b.nombre, 'es'));
});

const collator = new Intl.Collator('es', { numeric: true, sensitivity: 'base' });

const valorOrden = (op: any, key: string): any => {
    switch (key) {
        case 'producto':
            return nombreProductoOp(op);
        case 'taller':
            return (op.taller_nombre || op.empresa_externa_nombre) ?? '';
        case 'estado':
            return op.estado_nombre ?? '';
        case 'responsable':
            return op.responsable_nombre ?? '';
        case 'costo_total':
            return Number(op.costo_total) || 0;
        default:
            return op[key];
    }
};

const ESTADO_ESPERANDO_MATERIALES = 'Esperando materiales';

// La cola de «Esperando materiales» es FIFO: la OP con turno 1 es la próxima en
// salir en cuanto una compra complete su kit, así que esa fila se muestra de
// primera en la tabla sin importar la columna ni el sentido del orden elegido.
// El resto de las OPs conserva el orden que el usuario aplicó.
const esPrimeroEnCola = (op: any): boolean =>
    normalizar(op?.estado_nombre) === normalizar(ESTADO_ESPERANDO_MATERIALES)
    && Number(op?.cola_posicion) === 1;

const ordenadas = computed(() => {
    const dir = sortDir.value === 'asc' ? 1 : -1;
    const key = sortKey.value;

    return [...filtradas.value].sort((a: any, b: any) => {
        const primeroA = esPrimeroEnCola(a);
        const primeroB = esPrimeroEnCola(b);
        if (primeroA !== primeroB) return primeroA ? -1 : 1;

        const va = valorOrden(a, key);
        const vb = valorOrden(b, key);
        const nuloA = va === null || va === undefined || va === '';
        const nuloB = vb === null || vb === undefined || vb === '';

        if (nuloA && nuloB) return 0;
        if (nuloA) return 1;
        if (nuloB) return -1;
        if (typeof va === 'number' && typeof vb === 'number') return (va - vb) * dir;
        return collator.compare(String(va), String(vb)) * dir;
    });
});

const estadoCoincide = (op: any, nombre: string): boolean =>
    normalizar(op?.estado_nombre) === normalizar(nombre);

// El faltante proyectado descuenta lo que otras OP se comprometieron a producir,
// así que es el que decide si la orden realmente puede arrancar.
const tieneFaltantes = (op: any): boolean =>
    Array.isArray(op?.materiales_faltantes_proyectados) && op.materiales_faltantes_proyectados.length > 0;

// Los faltantes solo se vigilan antes de que el material salga al taller:
// desde "En taller" el inventario ya se descontó y la comparación dejaría
// de reflejar una necesidad real de compra.
const ESTADOS_SIN_FALTANTES = ['En taller', 'Recibiendo', 'Terminada', 'Cerrada'];
const faltantesVigentes = (op: any): boolean =>
    tieneFaltantes(op) && !ESTADOS_SIN_FALTANTES.some((nombre) => estadoCoincide(op, nombre));

const etiquetaOp = (op: any): string => op?.numero_op || (op?.id ? `OP #${op.id}` : 'OP');

// Las fechas sin hora llegan como "YYYY-MM-DD" (o medianoche UTC). Si se
// convierten directo, en Colombia se muestran un día antes.
const formatFecha = (valor: any): string => {
    if (!valor) return '';
    const texto = String(valor);
    const soloFecha = texto.match(/^(\d{4}-\d{2}-\d{2})(?:[T ]00:00:00(?:\.\d+)?Z?)?$/);
    return formatDate(soloFecha ? new Date(`${soloFecha[1]}T12:00:00`) : texto);
};

const opsConFaltantes = computed(() => ordenadas.value.filter(faltantesVigentes));
const opsEnTaller = computed(() => ordenadas.value.filter((op: any) => estadoCoincide(op, 'En taller')));
const opsRecibiendo = computed(() => ordenadas.value.filter((op: any) => estadoCoincide(op, 'Recibiendo')));
const opsSinFaltantes = computed(() => ordenadas.value.length - opsConFaltantes.value.length);

// Fecha local en formato YYYY-MM-DD para comparar sin desfases de zona horaria.
const hoyISO = (): string => {
    const fecha = new Date();
    fecha.setMinutes(fecha.getMinutes() - fecha.getTimezoneOffset());
    return fecha.toISOString().slice(0, 10);
};

// Compromisos de reparación abiertos cuya fecha es hoy o futura. Cada entrada
// conserva la OP y el producto para poder identificarla en el resumen.
const compromisosProximos = computed(() => {
    const hoy = hoyISO();

    return ordenadas.value
        .flatMap((op: any) =>
            ((op.compromisos_detalle || []) as any[])
                .filter((compromiso: any) =>
                    compromiso?.fecha_compromiso
                    && String(compromiso.fecha_compromiso).slice(0, 10) >= hoy
                )
                .map((compromiso: any) => ({ ...compromiso, op }))
        )
        .sort((a: any, b: any) =>
            String(a.fecha_compromiso).localeCompare(String(b.fecha_compromiso))
            || etiquetaOp(a.op).localeCompare(etiquetaOp(b.op), 'es')
        );
});

// Agrupa los compromisos próximos por OP para ver de un vistazo todo lo que
// tiene pendiente cada orden.
const compromisosPorOp = computed(() => {
    const grupos = new Map<string, { op: any; compromisos: any[] }>();

    for (const compromiso of compromisosProximos.value) {
        const clave = String(compromiso.op?.id ?? '');
        if (!clave) continue;

        if (!grupos.has(clave)) {
            grupos.set(clave, { op: compromiso.op, compromisos: [] });
        }

        grupos.get(clave)!.compromisos.push(compromiso);
    }

    for (const grupo of grupos.values()) {
        grupo.compromisos.sort((a, b) =>
            String(a.fecha_compromiso).localeCompare(String(b.fecha_compromiso))
        );
    }

    return Array.from(grupos.values()).sort((a, b) => {
        const fechaA = String(a.compromisos[0]?.fecha_compromiso ?? '');
        const fechaB = String(b.compromisos[0]?.fecha_compromiso ?? '');
        return fechaA.localeCompare(fechaB)
            || etiquetaOp(a.op).localeCompare(etiquetaOp(b.op), 'es');
    });
});

// Cada OP se muestra compacta; al hacer clic se despliegan todos sus pendientes.
const compromisosExpandidos = reactive<Record<string, boolean>>({});

const alternarCompromisosOp = (id: number | string) => {
    const clave = String(id);
    compromisosExpandidos[clave] = !compromisosExpandidos[clave];
};

// Materiales que faltan, agrupados por nombre + unidad para mostrarlos de forma
// compacta. Cada grupo guarda las OP que los necesitan y de dónde se espera
// cubrir la necesidad (compromisos con OP productoras).
const materialesFaltantes = computed(() => {
    const grupos = new Map<string, {
        clave: string;
        nombre: string;
        unidad: string;
        faltante: number;
        ops: Array<{ op: any; faltante: number; productos: string[]; origenes: any[] }>;
    }>();

    for (const op of opsConFaltantes.value) {
        for (const material of ((op as any).materiales_faltantes_proyectados || [])) {
            const nombre = String(material?.nombre || 'Material');
            const unidad = String(material?.unidad || '').trim();
            const clave = `${material?.material_id ?? nombre}|${unidad}`;
            const faltante = Number(material?.faltante_proyectado) || 0;
            const productos = Array.isArray(material?.productos)
                ? material.productos.map((producto: any) => String(producto)).filter(Boolean)
                : [];
            const origenes = Array.isArray(material?.origenes) ? material.origenes : [];

            if (!grupos.has(clave)) {
                grupos.set(clave, { clave, nombre, unidad, faltante: 0, ops: [] });
            }

            const grupo = grupos.get(clave)!;
            grupo.faltante += faltante;

            const detalle = grupo.ops.find((item) => item.op.id === op.id);
            if (detalle) {
                detalle.faltante += faltante;
                detalle.productos = Array.from(new Set([...detalle.productos, ...productos]));
            } else {
                grupo.ops.push({ op, faltante, productos, origenes });
            }
        }
    }

    return Array.from(grupos.values()).sort((a, b) =>
        a.nombre.localeCompare(b.nombre, 'es')
        || a.unidad.localeCompare(b.unidad, 'es')
    );
});

const materialesExpandidos = reactive<Record<string, boolean>>({});

const alternarMaterialFaltante = (clave: string) => {
    materialesExpandidos[clave] = !materialesExpandidos[clave];
};

const totalUnidades = computed(() =>
    ordenadas.value.reduce((total: number, op: any) => total + (Number(op.cantidad_planificada) || 0), 0),
);

const totalCostoFichas = computed(() =>
    ordenadas.value.reduce((total: number, op: any) => total + (Number(op.costo_fichas) || 0), 0),
);

const totalCostoProceso = computed(() =>
    ordenadas.value.reduce((total: number, op: any) => total + (Number(op.costo_proceso) || 0), 0),
);

const totalCostoOp = computed(() => totalCostoFichas.value + totalCostoProceso.value);

const resumenEstados = computed(() => {
    const conteos = new Map<number, number>();
    ordenadas.value.forEach((op: any) => {
        const id = Number(op.estado_op_id);
        if (!id) return;
        conteos.set(id, (conteos.get(id) ?? 0) + 1);
    });

    return estadosDisponibles.value
        .map((estado) => ({ ...estado, count: conteos.get(estado.id) ?? 0 }))
        .filter((estado) => estado.count > 0);
});

const totalFiltrado = computed(() => ordenadas.value.length);
const paginas = computed(() => Math.max(1, Math.ceil(totalFiltrado.value / porPagina.value)));
const paginaActual = computed(() => Math.min(pagina.value, paginas.value));

const rango = computed(() => {
    if (!totalFiltrado.value) return { desde: 0, hasta: 0 };
    const desde = (paginaActual.value - 1) * porPagina.value + 1;
    const hasta = Math.min(paginaActual.value * porPagina.value, totalFiltrado.value);
    return { desde, hasta };
});

const visibles = computed(() => {
    const inicio = (paginaActual.value - 1) * porPagina.value;
    return ordenadas.value.slice(inicio, inicio + porPagina.value);
});

const paginasVisibles = computed<Array<number | '...'>>(() => {
    const total = paginas.value;
    const actual = paginaActual.value;
    const ventana = 5;
    let inicio = Math.max(1, actual - 2);
    const fin = Math.min(total, inicio + ventana - 1);
    inicio = Math.max(1, fin - ventana + 1);

    const salida: Array<number | '...'> = [];
    if (inicio > 1) {
        salida.push(1);
        if (inicio > 2) salida.push('...');
    }
    for (let i = inicio; i <= fin; i++) salida.push(i);
    if (fin < total) {
        if (fin < total - 1) salida.push('...');
        salida.push(total);
    }
    return salida;
});

const cambiarPagina = (p: number) => {
    pagina.value = Math.min(Math.max(1, p), paginas.value);
};

const ordenarPor = (key: string) => {
    if (sortKey.value === key) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = key;
        sortDir.value = 'asc';
    }
    pagina.value = 1;
};

const indicador = (key: string) => (sortKey.value === key ? sortDir.value : null);
const ariaSort = (key: string): 'ascending' | 'descending' | 'none' =>
    sortKey.value !== key ? 'none' : sortDir.value === 'asc' ? 'ascending' : 'descending';

const hayFiltros = computed(
    () => busqueda.value.trim() !== ''
        || estadoFiltro.value !== 'todos'
        || responsableFiltro.value !== 'todos'
        || tallerFiltro.value !== 'todos',
);

const limpiarFiltros = () => {
    busqueda.value = '';
    estadoFiltro.value = 'todos';
    responsableFiltro.value = 'todos';
    tallerFiltro.value = 'todos';
};

watch([busqueda, estadoFiltro, responsableFiltro, tallerFiltro, porPagina], () => {
    pagina.value = 1;
});

watch(paginas, (n) => {
    if (pagina.value > n) pagina.value = n;
});

/* ------------------------------- Estilos -------------------------------- */

const ESTADO_ESTILOS: Record<string, { dot: string; badge: string }> = {
    borrador: {
        dot: 'bg-amber-500',
        badge: 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300',
    },
    preparacion: {
        dot: 'bg-blue-500',
        badge: 'border-blue-200 bg-blue-50 text-blue-800 dark:border-blue-500/30 dark:bg-blue-500/10 dark:text-blue-300',
    },
    esperando_materiales: {
        dot: 'bg-orange-500',
        badge: 'border-orange-200 bg-orange-50 text-orange-800 dark:border-orange-500/30 dark:bg-orange-500/10 dark:text-orange-300',
    },
    lista_para_taller: {
        dot: 'bg-emerald-500',
        badge: 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300',
    },
    en_taller: {
        dot: 'bg-cyan-500',
        badge: 'border-cyan-200 bg-cyan-50 text-cyan-800 dark:border-cyan-500/30 dark:bg-cyan-500/10 dark:text-cyan-300',
    },
    recibiendo: {
        dot: 'bg-yellow-400',
        badge: 'border-yellow-300 bg-yellow-50 text-yellow-800 dark:border-yellow-500/30 dark:bg-yellow-400/10 dark:text-yellow-300',
    },
    pendiente: {
        dot: 'bg-rose-500',
        badge: 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300',
    },
    terminada: {
        dot: 'bg-green-500',
        badge: 'border-green-200 bg-green-50 text-green-800 dark:border-green-500/30 dark:bg-green-500/10 dark:text-green-300',
    },
    cerrada: {
        dot: 'bg-slate-400',
        badge: 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-500/30 dark:bg-slate-500/10 dark:text-slate-300',
    },
};

const estiloEstado = (nombre: string) =>
    ESTADO_ESTILOS[normalizar(nombre).replace(/\s+/g, '_')] ?? {
        dot: 'bg-muted-foreground/50',
        badge: 'border-border bg-muted text-muted-foreground',
    };

// Qué pasa y quién interviene en cada estado (Borrador no se explica: es una
// OP sin confirmar y no genera trabajo para nadie).
const GUIA_ESTADOS: Record<string, { area: string; texto: string }> = {
    preparacion: {
        area: 'Planeación',
        texto: 'revisa la ficha técnica y reúne los materiales del kit; el taller todavía no interviene.',
    },
    esperando_materiales: {
        area: 'Compras y bodega',
        texto: 'consiguen el material que falta; la OP espera su turno en la cola y pasa sola a «Lista para taller» cuando una compra completa el kit.',
    },
    lista_para_taller: {
        area: 'Bodega',
        texto: 'entrega el kit completo y la ficha de ensamble al taller asignado.',
    },
    en_taller: {
        area: 'Taller de ensamble',
        texto: 'recibe el kit y arma la OP; al entrar se descuenta el material del inventario.',
    },
    recibiendo: {
        area: 'Control de calidad',
        texto: 'recibe los pares y registra cuántos quedan aprobados, en reparación y no recuperables.',
    },
    pendiente: {
        area: 'Taller y calidad',
        texto: 'corrigen los pares en reparación; la OP espera esa corrección para poder cerrarse.',
    },
    cerrada: {
        area: 'Administración',
        texto: 'el sistema calcula las mermas y el costo real final con los productos recibidos. La OP ya no cambia de estado.',
    },
};

// Entidades del dominio que se resaltan en azul dentro de la guía.
const ENTIDADES = [
    'ficha de ensamble',
    'ficha técnica',
    'costo real final',
    'proveedor externo',
    'materiales',
    'inventario',
    'material',
    'mermas',
    'taller',
    'pares',
    'kit',
    'OP',
].sort((a, b) => b.length - a.length);

const segmentosEntidades = (texto: string) => {
    const patron = new RegExp(`\\b(${ENTIDADES.join('|')})\\b`, 'giu');
    return String(texto ?? '')
        .split(patron)
        .filter((parte) => parte !== '')
        .map((parte) => ({
            texto: parte,
            entidad: ENTIDADES.some((entidad) => entidad.toLowerCase() === parte.toLowerCase()),
        }));
};

const guiaEstados = computed(() =>
    (((props.losSelect as any)?.estado_op_id ?? []) as any[])
        .filter((estado: any) => normalizar(estado.name) !== 'borrador')
        .map((estado: any) => {
            const clave = normalizar(estado.name).replace(/\s+/g, '_');
            const guia = GUIA_ESTADOS[clave];
            const texto = guia?.texto ?? estado.regla_clave ?? '';
            return {
                id: estado.id,
                nombre: estado.name,
                area: guia?.area ?? 'Equipo',
                texto,
                partes: segmentosEntidades(texto),
            };
        })
);

// Estados que muestran información adicional al hacer clic. El resto
// (Borrador, Lista para taller, En taller, Cerrada) no tiene detalle y su
// vínculo queda deshabilitado.
const ESTADOS_CON_DETALLE = ['Preparación', 'Esperando materiales', 'Recibiendo'];
const estadoConDetalle = (nombre: string | null | undefined) =>
    ESTADOS_CON_DETALLE.includes(String(nombre ?? ''));

const columnas = [
    { key: 'numero_op', label: 'Orden', sortable: true, align: 'left', headerClass: 'min-w-[130px]', cellClass: 'min-w-[130px]' },
    { key: 'producto', label: 'Producto', sortable: true, align: 'left', headerClass: 'min-w-[200px]', cellClass: 'min-w-[200px]' },
    { key: 'cantidad_planificada', label: 'Cantidad', sortable: true, align: 'right', headerClass: 'w-[100px]', cellClass: 'w-[100px]' },
    { key: 'costo_total', label: 'Costo OP', sortable: true, align: 'right', headerClass: 'w-[175px]', cellClass: 'w-[175px]' },
    { key: 'taller', label: 'Envío taller', sortable: true, align: 'left', headerClass: 'hidden min-w-[150px] md:table-cell', cellClass: 'hidden min-w-[150px] md:table-cell' },
    { key: 'estado', label: 'Estado', sortable: true, align: 'left', headerClass: 'min-w-[150px]', cellClass: 'min-w-[150px]' },
    { key: 'contexto', label: '-', sortable: false, align: 'left', headerClass: 'min-w-[160px]', cellClass: 'min-w-[160px]' },
    { key: 'responsable', label: 'Responsable', sortable: true, align: 'left', headerClass: 'hidden min-w-[120px] lg:table-cell', cellClass: 'hidden min-w-[120px] lg:table-cell' },
    { key: 'observaciones', label: 'Observaciones', sortable: false, align: 'left', headerClass: 'hidden min-w-[200px] 2xl:table-cell', cellClass: 'hidden min-w-[200px] 2xl:table-cell' },
] as const;

/* ------------------------------- Acciones ------------------------------- */

const authCan = ((usePage().props as any)?.auth?.can ?? {}) as Record<string, boolean>;
const puede = (perms: string[]) => perms.some((p) => authCan[p]);

const data = reactive({
    createOpen: false,
    borradorCrear: null as any,
    editOpen: false,
    deleteOpen: false,
    ordenEditando: null as any,
    distribucionOpen: false,
    distribucionOp: null as any,
    kitProcesoOpen: false,
    kitProcesoOp: null as any,
    cambioEstadoOpen: false,
    cambioEstadoOp: null as any,
    detalleEstadoOpen: false,
    detalleEstadoOp: null as any,
    materialesOpen: false,
    materialesOp: null as any,
});

const abrirNuevo = () => {
    data.borradorCrear = null;
    data.createOpen = true;
};

const abrirContinuarUltima = () => {
    data.borradorCrear = props.borradorUltimo ?? null;
    data.createOpen = true;
};

const abrirDistribucion = (op: any) => {
    data.distribucionOp = op ?? null;
    data.distribucionOpen = true;
};

const abrirKitProceso = (op: any) => {
    data.kitProcesoOp = op ?? null;
    data.kitProcesoOpen = true;
};

const abrirCambioEstado = (op: any) => {
    data.cambioEstadoOp = op ?? null;
    data.cambioEstadoOpen = true;
};

const abrirDetalleEstado = (op: any) => {
    if (!estadoConDetalle(op?.estado_nombre)) return;
    data.detalleEstadoOp = op ?? null;
    data.detalleEstadoOpen = true;
};

// Inventario vs. necesidad de la OP: accesible desde la columna Cantidad
// para cualquier estado.
const abrirMaterialesOp = (op: any) => {
    data.materialesOp = op ?? null;
    data.materialesOpen = true;
};

const accionesFila = computed(() => {
    const arr: Array<{ id: string; label: string; icon: any; visible?: (op: any) => boolean }> = [];
    if (puede(['update OrdenProduccion'])) {
        arr.push({ id: 'edit', label: 'Editar', icon: PencilIcon });
    }
    if (puede(['delete OrdenProduccion'])) {
        arr.push({ id: 'delete', label: 'Eliminar', icon: TrashIcon });
    }
    return arr;
});

const accionesVisibles = (op: any) => accionesFila.value.filter((a) => !a.visible || a.visible(op));

const onAccionFila = (id: string, op: any) => {
    if (id === 'edit') {
        data.editOpen = true;
        data.deleteOpen = false;
        data.distribucionOpen = false;
        data.kitProcesoOpen = false;
        data.cambioEstadoOpen = false;
        data.materialesOpen = false;
        data.ordenEditando = op;
    }
    if (id === 'kit') abrirKitProceso(op);
    if (id === 'tallas') abrirDistribucion(op);
    if (id === 'delete') {
        data.ordenEditando = op;
        data.deleteOpen = true;
    }
};

// Una OP sin producto de referencia igual produce: su titular salen del kit de productos.
const nombreProductoOp = (op: any) =>
    op.producto_nombre || op.productos_nombres || op.materiales_nombres || '';

const detalleProductoOp = (op: any) => {
    if (op.materiales_nombres) {
        const cantidad = (op.materiales_objetivo || []).length;
        return cantidad
            ? (cantidad === 1 ? 'material procesado' : 'materiales procesados')
            : 'Material procesado';
    }
    if (op.producto_nombre) return op.productos_nombres || 'Sin productos en el kit';
    const kit = (op.productos || []).length;
    return kit ? (kit === 1 ? 'producto del kit' : 'productos del kit') : 'Sin productos';
};

const tooltipProductos = (op: any) => {
    const escapar = (s: string) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    const lineas = (op?.productos || []).map((p: any) =>
        escapar(String(p.Titulo_Shopify || p.Codigo_interno || 'Producto #' + p.id)),
    );
    if (!lineas.length) return undefined;
    return { content: lineas.join('<br><br>'), html: true, delay: { show: 200, hide: 100 } };
};

const tooltipFaltantes = (op: any) => {
    const escapar = (s: string) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    const lineas = (op?.materiales_faltantes_proyectados || []).map((m: any) => {
        const cantidad = zilef_nuber_format(m.faltante_proyectado ?? 0, 2, false);
        const unidad = escapar(String(m.unidad ?? '')).trim();
        const productos = Array.isArray(m.productos)
            ? m.productos.map((producto: any) => escapar(String(producto))).filter(Boolean)
            : [];
        const detalleProducto = productos.length ? ` · ${productos.join(', ')}` : '';
        const origenes = Array.isArray(m.origenes) && m.origenes.length
            ? ' · de ' + m.origenes
                .map((origen: any) => `${escapar(String(origen.numero_op ?? 'OP'))} (${zilef_nuber_format(origen.pendiente ?? origen.cantidad ?? 0, 2, false)})`)
                .join(', ')
            : ' · sin producción comprometida';
        return `${escapar(String(m.nombre ?? 'Material'))}: faltan ${cantidad}${unidad ? ' ' + unidad : ''}${detalleProducto}${origenes}`;
    });
    if (!lineas.length) return undefined;
    return { content: lineas.join('<br>'), html: true, delay: { show: 200, hide: 100 } };
};

/* -------------------------- Carga / recarga ----------------------------- */

const carga = reactive({
    activa: false,
    tablaLista: false,
    resumenListo: false,
});
const rutaIndex = new URL(String(route('OrdenProduccion.index')), window.location.origin).pathname;

watch(
    () => props.ordenes,
    (nuevasOrdenes) => {
        if (Array.isArray(nuevasOrdenes)) {
            carga.tablaLista = true;
            setTimeout(() => {
                carga.resumenListo = true;
            }, 500);
        } else {
            carga.tablaLista = false;
            carga.resumenListo = false;
        }
    },
    { immediate: true },
);

router.on('start', (event) => {
    const url = event.detail.visit.url;
    const pathname = url instanceof URL ? url.pathname : new URL(String(url), window.location.origin).pathname;
    if (pathname === rutaIndex) {
        carga.activa = true;
        carga.tablaLista = false;
        carga.resumenListo = false;
    }
});
router.on('finish', () => {
    carga.activa = false;
});

const recargar = () => {
    carga.tablaLista = false;
    carga.resumenListo = false;
    router.reload({ only: ['ordenes', 'total', 'borradorUltimo', 'losSelect'], preserveScroll: true });
};

/* Títulos para los modales de crear/editar (mismo contrato de antes). */
const titulos = [
    { order: 'numero_op', label: 'numero_op', type: 'string' },
    { order: 'producto_referencia_id', label: 'producto_referencia_id', type: 'foreign', nameid: 'producto_nombre' },
    { order: 'cantidad_planificada', label: 'cantidad_planificada', type: 'integer' },
    { order: 'fecha_prevista_envio_taller', label: 'fecha_prevista_envio_taller', type: 'date' },
    { order: 'taller_id', label: 'taller_id', type: 'foreign', nameid: 'taller_nombre' },
    { order: 'estado_op_id', label: 'estado_op_id', type: 'foreign', nameid: 'estado_nombre' },
    { order: 'user_id', label: 'user_id', type: 'foreign', nameid: 'responsable_nombre' },
    { order: 'id_shopify_producto', label: 'id_shopify_producto', type: 'string' },
    { order: 'observaciones', label: 'observaciones', type: 'text' },
];
const breadcrumbCollapsed = ref(false);

</script>

<template>
    <Head :title="props.title" />

    <AppLayout>
        <div class="flex flex-col gap-4 p-4 sm:p-6">
            <!-- Encabezado -->
        <!-- boton colapsar se mueve a la fila del titulo -->
        <div class="flex flex-col gap-2">
            <nav v-if="!breadcrumbCollapsed" class="flex items-center gap-1.5 text-xs text-muted-foreground" aria-label="Ruta de navegación">
                    <Link
                        :href="route('dashboard')"
                        class="rounded-sm transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        Dashboard
                    </Link>
                    <template v-for="(miga, i) in (props.breadcrumbs || [])" :key="i">
                        <ChevronRightIcon class="h-3 w-3 opacity-60" />
                        <Link
                            v-if="miga.href"
                            :href="miga.href"
                            class="rounded-sm transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            {{ miga.label }}
                        </Link>
                        <span v-else class="font-medium text-foreground">{{ miga.label }}</span>
                    </template>
                </nav>

                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div class="min-w-0" v-if="!breadcrumbCollapsed">
                        <h1 class="text-2xl font-semibold tracking-tight text-foreground">Órdenes de producción</h1>
                        <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground" role="status" aria-live="polite">
                            <span v-if="!carga.tablaLista" class="inline-flex h-4 w-28 animate-pulse rounded bg-muted"></span>
                            <span v-else class="tabular-nums">
                                <span class="font-medium text-foreground">{{ totalFiltrado }}</span>
                                de {{ filas.length }} órdenes
                            </span>
                            <span
                                v-if="carga.tablaLista && hayFiltros"
                                class="inline-flex items-center gap-1 rounded-full border border-border bg-muted/60 px-2 py-0.5 text-[11px] font-medium text-foreground"
                            >
                                <FunnelIcon class="h-3 w-3" />
                                Filtros activos
                            </span>
                        </p>
                    </div>

                    <button
                        @click="breadcrumbCollapsed = !breadcrumbCollapsed"
                        class="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                        :aria-label="breadcrumbCollapsed ? 'Mostrar título y breadcrumb' : 'Ocultar título y breadcrumb'"
                    >
                        <ChevronDownIcon v-if="breadcrumbCollapsed" class="h-4 w-4" />
                        <ChevronUpIcon v-else class="h-4 w-4" />
                    </button>

                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            @click="recargar"
                            :disabled="carga.activa"
                            title="Actualizar datos"
                            aria-label="Actualizar datos"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:pointer-events-none disabled:opacity-50"
                        >
                            <ArrowPathIcon class="h-4 w-4" :class="{ 'animate-spin': carga.activa }" />
                        </button>

                        <button
                            v-if="can(['create OrdenProduccion']) && props.borradorUltimo"
                            type="button"
                            @click="abrirContinuarUltima"
                            class="inline-flex h-9 items-center gap-1.5 rounded-md border border-amber-300 bg-amber-50 px-3 text-xs font-semibold text-amber-800 shadow-sm transition hover:bg-amber-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 focus-visible:ring-offset-2 focus-visible:ring-offset-background dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-300 dark:hover:bg-amber-500/20"
                        >
                            <ArrowUturnLeftIcon class="h-4 w-4" />
                            Continuar {{ (props.borradorUltimo as any)?.numero_op }}
                        </button>

                        <PrimaryButton v-if="can(['create OrdenProduccion'])" type="button" class="h-9" @click="abrirNuevo">
                            <PlusIcon class="mr-1.5 h-4 w-4" />
                            Nueva orden
                        </PrimaryButton>
                    </div>
                </div>
            </div>

            <!-- Superficie principal: barra de filtros + tabla + paginación -->
            <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <!-- Barra de filtros -->
                <div class="flex flex-col gap-2.5 border-b border-border p-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="relative min-w-[220px] flex-1">
                            <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <input
                                v-model="busqueda"
                                type="search"
                                aria-label="Buscar por código de orden o producto"
                                placeholder="Buscar por código de OP o producto…"
                                class="h-9 w-full rounded-md border border-input bg-background pl-9 pr-9 text-sm text-foreground shadow-sm placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30"
                            />
                            <button
                                v-if="busqueda"
                                type="button"
                                @click="busqueda = ''"
                                aria-label="Limpiar búsqueda"
                                class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-muted-foreground transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            >
                                <XMarkIcon class="h-3.5 w-3.5" />
                            </button>
                        </div>

                        <select
                            v-model="estadoFiltro"
                            aria-label="Filtrar por estado"
                            class="h-9 max-w-[190px] rounded-md border border-input bg-background px-2 text-sm text-foreground shadow-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30"
                        >
                            <option value="todos">Todos (sin cerradas)</option>
                            <option v-for="estado in estadosDisponibles" :key="estado.id" :value="estado.id">
                                {{ estado.nombre }} ({{ estado.count }})
                            </option>
                        </select>

                        <select
                            v-model="responsableFiltro"
                            aria-label="Filtrar por responsable"
                            class="h-9 max-w-[190px] rounded-md border border-input bg-background px-2 text-sm text-foreground shadow-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30"
                        >
                            <option value="todos">Todos los responsables</option>
                            <option v-for="responsable in responsablesDisponibles" :key="responsable.id" :value="responsable.id">
                                {{ responsable.nombre }}
                            </option>
                        </select>

                        <select
                            v-model="tallerFiltro"
                            aria-label="Filtrar por taller"
                            class="h-9 max-w-[190px] rounded-md border border-input bg-background px-2 text-sm text-foreground shadow-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30"
                        >
                            <option value="todos">Todos los talleres</option>
                            <option v-for="taller in talleresDisponibles" :key="taller.id" :value="taller.id">
                                {{ taller.nombre }}
                            </option>
                        </select>

                        <select
                            v-model.number="porPagina"
                            aria-label="Filas por página"
                            class="h-9 rounded-md border border-input bg-background px-2 text-sm text-foreground shadow-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30"
                        >
                            <option v-for="n in [10, 25, 50, 100]" :key="n" :value="n">{{ n }} / página</option>
                        </select>

                        <button
                            v-if="hayFiltros"
                            type="button"
                            @click="limpiarFiltros"
                            class="inline-flex h-9 items-center gap-1.5 rounded-md border border-border bg-card px-3 text-xs font-medium text-foreground shadow-sm transition hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                        >
                            <XMarkIcon class="h-3.5 w-3.5" />
                            Limpiar
                        </button>
                    </div>
                </div>

                <!-- Tabla -->
                <div class="op-scroll max-h-[68vh] overflow-auto">
                    <table class="w-full min-w-[1260px] border-collapse text-sm">
                        <thead>
                            <tr>
                                <th
                                    v-for="col in columnas"
                                    :key="col.key"
                                    scope="col"
                                    :aria-sort="col.sortable ? ariaSort(col.key) : undefined"
                                    :title="col.key === 'contexto' ? 'Información según el estado' : undefined"
                                    :aria-label="col.key === 'contexto' ? 'Información según el estado' : undefined"
                                    :class="[
                                        'bg-muted px-3 py-2.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground',
                                        col.align === 'right' ? 'text-right' : 'text-left',
                                        col.headerClass,
                                    ]"
                                >
                                    <button
                                        v-if="col.sortable"
                                        type="button"
                                        @click="ordenarPor(col.key)"
                                        :class="[
                                            'group/sort inline-flex items-center gap-1 rounded-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background',
                                            col.align === 'right' ? 'w-full justify-end' : '',
                                        ]"
                                    >
                                        <span>{{ col.label }}</span>
                                        <span class="text-muted-foreground/60 transition-colors group-hover/sort:text-foreground">
                                            <ChevronUpIcon v-if="indicador(col.key) === 'asc'" class="h-3 w-3" />
                                            <ChevronDownIcon v-else-if="indicador(col.key) === 'desc'" class="h-3 w-3" />
                                            <ChevronUpDownIcon v-else class="h-3 w-3 opacity-0 transition-opacity group-hover/sort:opacity-100" />
                                        </span>
                                    </button>
                                    <span v-else>{{ col.label }}</span>
                                </th>

                                <th
                                    scope="col"
                                    class="sticky right-0 z-30 w-[104px] border-l border-border bg-muted px-2 py-2.5 text-right text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground"
                                >
                                    Acciones
                                </th>
                            </tr>
                        </thead>

                        <!-- Cargando -->
                        <tbody v-if="carga.activa || !carga.tablaLista">
                            <tr v-for="n in porPagina" :key="'skeleton-' + n" class="border-b border-border/60">
                                <td class="px-3 py-3 align-top">
                                    <div class="animate-pulse space-y-1.5">
                                        <div class="h-3.5 w-20 rounded bg-muted"></div>
                                        <div class="h-3 w-28 rounded bg-muted/60"></div>
                                    </div>
                                </td>
                                <td class="px-3 py-3 align-top">
                                    <div class="animate-pulse space-y-1.5">
                                        <div class="h-3.5 w-40 rounded bg-muted"></div>
                                        <div class="h-3 w-24 rounded bg-muted/60"></div>
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-right align-top">
                                    <div class="animate-pulse flex flex-col items-end gap-1.5">
                                        <div class="h-3.5 w-12 rounded bg-muted"></div>
                                        <div class="h-3 w-16 rounded bg-muted/60"></div>
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-right align-top">
                                    <div class="animate-pulse space-y-1.5">
                                        <div class="h-3 w-28 rounded bg-muted/60"></div>
                                        <div class="h-3 w-28 rounded bg-muted/60"></div>
                                        <div class="h-3.5 w-24 rounded bg-muted mt-1"></div>
                                    </div>
                                </td>
                                <td class="hidden px-3 py-3 align-top md:table-cell">
                                    <div class="animate-pulse space-y-1.5">
                                        <div class="h-3.5 w-28 rounded bg-muted"></div>
                                        <div class="h-3 w-20 rounded bg-muted/60"></div>
                                    </div>
                                </td>
                                <td class="px-3 py-3 align-top">
                                    <div class="animate-pulse">
                                        <div class="h-5 w-28 rounded-full bg-muted"></div>
                                    </div>
                                </td>
                                <td class="px-3 py-3 align-top">
                                    <div class="animate-pulse">
                                        <div class="h-5 w-24 rounded-full bg-muted/60"></div>
                                    </div>
                                </td>
                                <td class="hidden px-3 py-3 align-top lg:table-cell">
                                    <div class="animate-pulse">
                                        <div class="h-3.5 w-24 rounded bg-muted"></div>
                                    </div>
                                </td>
                                <td class="hidden px-3 py-3 align-top 2xl:table-cell">
                                    <div class="animate-pulse">
                                        <div class="h-3 w-36 rounded bg-muted/60"></div>
                                    </div>
                                </td>
                                <td class="sticky right-0 z-10 w-[104px] border-l border-border/60 bg-card px-2 py-2 align-middle">
                                    <div class="animate-pulse flex items-center justify-end gap-1">
                                        <div class="h-7 w-7 rounded-md bg-muted"></div>
                                        <div class="h-7 w-7 rounded-md bg-muted"></div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>

                        <!-- Filas -->
                        <tbody v-else-if="carga.tablaLista && visibles.length" :key="paginaActual" class="op-page">
                            <tr
                                v-for="op in visibles"
                                :key="op.id"
                                class="group border-b border-border/60 transition-colors last:border-0 hover:bg-muted/40"
                            >
                                <!-- Orden -->
                                <td class="px-3 py-3 align-top">
                                    <div class="flex flex-col gap-0.5">
                                        <span class="font-semibold tabular-nums tracking-tight text-foreground">
                                            {{ op.numero_op || '—' }}
                                        </span>
                                        <span class="text-[11px] text-muted-foreground">
                                            {{ op.created_at ? 'Creada ' + formatDate(op.created_at) : 'Sin fecha' }}
                                        </span>
                                    </div>
                                </td>

                                <!-- Producto -->
                                <td class="px-3 py-3 align-top">
                                    <div class="flex max-w-[220px] flex-col gap-0.5">
                                        <span class="truncate font-medium text-foreground" :title="nombreProductoOp(op)">
                                            {{ nombreProductoOp(op) || 'Sin productos' }}
                                        </span>
                                        <span class="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                                            <span class="inline-flex min-w-4 items-center justify-center rounded-full border border-border bg-muted/60 px-1.5 py-0.5 font-medium tabular-nums">
                                                {{ (op.productos || []).length }}
                                            </span>
                                            <span class="truncate" v-tooltip="tooltipProductos(op)">
                                                {{ detalleProductoOp(op) }}
                                            </span>
                                        </span>
                                    </div>
                                </td>

                                <!-- Cantidad: clic para ver materiales vs. inventario -->
                                <td class="px-3 py-3 text-right align-top">
                                    <button
                                        type="button"
                                        @click="abrirMaterialesOp(op)"
                                        title="Ver materiales necesarios y existencia"
                                        aria-haspopup="dialog"
                                        class="group flex flex-col items-end gap-0.5 rounded-md px-1 py-0.5 -mx-1 transition hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                                    >
                                        <span class="inline-flex items-center gap-1 font-semibold tabular-nums text-foreground underline decoration-dotted underline-offset-4 group-hover:decoration-solid">
                                            {{ zilef_nuber_format(op.cantidad_planificada, 0, false) }}
                                            <ChevronRightIcon class="h-3 w-3 text-muted-foreground opacity-60" />
                                        </span>
                                        <span class="text-[11px] tabular-nums text-muted-foreground">
                                            {{ (op.distribuciones_por_tallas || []).length }} tallas
                                        </span>
                                    </button>
                                </td>

                                <!-- Costos de la OP: fichas + proceso = total -->
                                <td class="px-3 py-3 text-right align-top">
                                    <div class="flex flex-col gap-0.5">
                                        <div class="flex items-baseline justify-between gap-2 text-[11px]">
                                            <span class="text-muted-foreground">Costo fichas</span>
                                            <span class="font-semibold tabular-nums text-foreground">
                                                {{ formatdinero(op.costo_fichas) }}
                                            </span>
                                        </div>
                                        <div class="flex items-baseline justify-between gap-2 text-[11px]">
                                            <span class="text-muted-foreground">Costo proceso</span>
                                            <span class="tabular-nums text-foreground">
                                                {{ formatdinero(op.costo_proceso) }}
                                            </span>
                                        </div>
                                        <div class="mt-1 flex items-baseline justify-between gap-2 border-t border-border pt-1">
                                            <span class="text-[10px] font-semibold uppercase tracking-[0.08em] text-muted-foreground">
                                                Total
                                            </span>
                                            <span class="font-semibold tabular-nums text-foreground">
                                                {{ formatdinero(op.costo_total) }}
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <!-- Envío taller: taller o proceso externo seleccionado -->
                                <td class="hidden px-3 py-3 align-top md:table-cell">
                                    <div class="flex flex-col gap-0.5">
                                        <span class="text-sm text-foreground">{{ op.taller_nombre || op.empresa_externa_nombre || '—' }}</span>
                                        <span v-if="op.tipo === 'externa'" class="text-[10px] font-medium uppercase tracking-wide text-violet-600 dark:text-violet-300">Proceso externo</span>
                                        <span v-if="op.fecha_prevista_envio_taller" class="text-[11px] tabular-nums text-muted-foreground">
                                            {{ formatFecha(op.fecha_prevista_envio_taller) }}
                                        </span>
                                    </div>
                                </td>

                                <!-- Estado -->
                                <td class="px-3 py-3 align-top">
                                    <button
                                        v-if="estadoConDetalle(op.estado_nombre)"
                                        type="button"
                                        @click="abrirDetalleEstado(op)"
                                        title="Ver detalle del estado"
                                        aria-haspopup="dialog"
                                        class="inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[11px] font-medium transition hover:brightness-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background dark:hover:brightness-110"
                                        :class="estiloEstado(op.estado_nombre).badge"
                                    >
                                        <span class="h-1.5 w-1.5 rounded-full" :class="estiloEstado(op.estado_nombre).dot"></span>
                                        {{ op.estado_nombre || 'Sin estado' }}
                                        <ChevronRightIcon class="h-3 w-3 opacity-60" />
                                    </button>
                                    <span
                                        v-else
                                        class="inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[11px] font-medium"
                                        :class="estiloEstado(op.estado_nombre).badge"
                                    >
                                        <span class="h-1.5 w-1.5 rounded-full" :class="estiloEstado(op.estado_nombre).dot"></span>
                                        {{ op.estado_nombre || 'Sin estado' }}
                                    </span>
                                </td>

                                <!-- Información según el estado -->
                                <td class="px-3 py-3 align-top">
                                    <div v-if="estadoCoincide(op, 'Esperando materiales')" class="flex flex-col items-start gap-1">
                                        <span
                                            v-if="op.cola_posicion"
                                            class="text-[11px] font-medium tabular-nums text-muted-foreground"
                                            title="Orden de salida de la cola: la primera pasa a «Lista para taller» en cuanto una compra complete su kit"
                                        >
                                            Turno {{ op.cola_posicion }} en la cola
                                        </span>
                                        <span
                                            v-if="tieneFaltantes(op)"
                                            v-tooltip="tooltipFaltantes(op)"
                                            class="inline-flex items-center gap-1.5 rounded-full border border-rose-200 bg-rose-50 px-2 py-0.5 text-[11px] font-medium text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300"
                                        >
                                            <ExclamationTriangleIcon class="h-3 w-3" />
                                            Faltan materiales
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300"
                                        >
                                            <CheckCircleIcon class="h-3 w-3" />
                                            Materiales completos
                                        </span>
                                        <span v-if="tieneFaltantes(op)" class="text-[11px] tabular-nums text-muted-foreground">
                                            {{ op.materiales_faltantes_proyectados.length }}
                                            {{ op.materiales_faltantes_proyectados.length === 1 ? 'material faltante' : 'materiales faltantes' }}
                                            en la proyección
                                        </span>
                                    </div>

                                    <div v-else-if="estadoCoincide(op, 'En taller')" class="flex flex-col gap-0.5">
                                        <span class="text-[10px] font-semibold uppercase tracking-[0.08em] text-muted-foreground">
                                            Envío previsto
                                        </span>
                                        <span v-if="op.fecha_prevista_envio_taller" class="text-sm tabular-nums text-foreground">
                                            {{ formatFecha(op.fecha_prevista_envio_taller) }}
                                        </span>
                                        <span v-else class="text-xs text-muted-foreground">Sin fecha</span>
                                    </div>

                                    <div v-else-if="estadoCoincide(op, 'Recibiendo')" class="flex flex-col gap-0.5">
                                        <span class="text-[10px] font-semibold uppercase tracking-[0.08em] text-muted-foreground">
                                            Fecha recepción
                                        </span>
                                        <span v-if="op.recepcion_desde" class="text-sm tabular-nums text-foreground">
                                            {{ formatDate(op.recepcion_desde, 'conLaHora') }}
                                        </span>
                                        <span v-else class="text-xs text-muted-foreground">Sin fecha</span>
                                    </div>

                                    <span v-else class="text-xs text-muted-foreground">—</span>
                                </td>

                                <!-- Responsable -->
                                <td class="hidden px-3 py-3 align-top lg:table-cell">
                                    <span class="text-sm text-foreground">{{ op.responsable_nombre || '—' }}</span>
                                </td>

                                <!-- Observaciones -->
                                <td class="hidden px-3 py-3 align-top 2xl:table-cell">
                                    <span
                                        v-if="op.observaciones"
                                        v-tooltip="{ content: op.observaciones, delay: { show: 200, hide: 100 } }"
                                        class="block max-w-[220px] truncate text-xs text-muted-foreground"
                                    >
                                        {{ op.observaciones }}
                                    </span>
                                    <span v-else class="text-xs text-muted-foreground">—</span>
                                </td>

                                <!-- Acciones -->
                                <td
                                    class="sticky right-0 z-10 w-[104px] border-l border-border/60 bg-card px-2 py-2 align-middle shadow-[-8px_0_16px_-16px_rgba(0,0,0,0.35)] transition-colors group-hover:bg-muted/40"
                                >
                                    <div class="flex items-center justify-end gap-1">
                                        <button
                                            v-if="puede(['update OrdenProduccion'])"
                                            type="button"
                                            @click="abrirCambioEstado(op)"
                                            title="Cambiar estado"
                                            aria-label="Cambiar estado"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-emerald-600 shadow-sm transition hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2 focus-visible:ring-offset-background dark:text-emerald-400 dark:hover:bg-emerald-950/40 dark:hover:text-emerald-300"
                                        >
                                            <ArrowRightIcon class="h-3.5 w-3.5" />
                                        </button>

                                        <DropdownMenu>
                                            <DropdownMenuTrigger
                                                type="button"
                                                title="Más acciones"
                                                aria-label="Más acciones"
                                                class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                                            >
                                                <EllipsisVerticalIcon class="h-4 w-4" />
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" class="w-60 p-1">
                                                <DropdownMenuItem
                                                    v-for="accion in accionesVisibles(op)"
                                                    :key="accion.id"
                                                    @click="onAccionFila(accion.id, op)"
                                                    :variant="accion.id === 'delete' ? 'destructive' : 'default'"
                                                    class="flex cursor-pointer items-center gap-2 px-2.5 py-1.5 text-xs font-medium"
                                                >
                                                    <component :is="accion.icon" class="h-4 w-4" />
                                                    <span>{{ accion.label }}</span>
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </td>
                            </tr>
                        </tbody>

                        <!-- Sin resultados -->
                        <tbody v-else>
                            <tr>
                                <td :colspan="columnas.length + 1" class="px-6 py-16">
                                    <div class="mx-auto flex max-w-sm flex-col items-center text-center">
                                        <div class="flex h-12 w-12 items-center justify-center rounded-full border border-border bg-muted/60">
                                            <FunnelIcon v-if="hayFiltros || cerradasOcultas" class="h-5 w-5 text-muted-foreground" />
                                            <ClipboardDocumentListIcon v-else class="h-5 w-5 text-muted-foreground" />
                                        </div>
                                        <h3 class="mt-4 text-sm font-semibold text-foreground">
                                            {{
                                                cerradasOcultas
                                                    ? 'No hay órdenes abiertas'
                                                    : hayFiltros
                                                      ? 'No hay órdenes que coincidan'
                                                      : 'Aún no hay órdenes de producción'
                                            }}
                                        </h3>
                                        <p class="mt-1 text-sm text-muted-foreground">
                                            {{
                                                cerradasOcultas
                                                    ? 'Las órdenes cerradas están ocultas. Activa el filtro «Cerrada» para verlas.'
                                                    : hayFiltros
                                                      ? 'Ajusta la búsqueda o limpia los filtros para ver más resultados.'
                                                      : 'Crea la primera orden para empezar a planificar la producción.'
                                            }}
                                        </p>
                                        <button
                                            v-if="cerradasOcultas"
                                            type="button"
                                            @click="mostrarCerradas"
                                            class="mt-4 inline-flex items-center gap-1.5 rounded-md border border-border bg-card px-3 py-1.5 text-xs font-medium text-foreground shadow-sm transition hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                                        >
                                            <FunnelIcon class="h-3.5 w-3.5" />
                                            Ver cerradas
                                        </button>
                                        <button
                                            v-else-if="hayFiltros"
                                            type="button"
                                            @click="limpiarFiltros"
                                            class="mt-4 inline-flex items-center gap-1.5 rounded-md border border-border bg-card px-3 py-1.5 text-xs font-medium text-foreground shadow-sm transition hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                                        >
                                            <XMarkIcon class="h-3.5 w-3.5" />
                                            Limpiar filtros
                                        </button>
                                        <PrimaryButton v-else-if="can(['create OrdenProduccion'])" type="button" class="mt-4" @click="abrirNuevo">
                                            <PlusIcon class="mr-1.5 h-4 w-4" />
                                            Crear la primera orden
                                        </PrimaryButton>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <footer
                    v-if="!carga.activa && totalFiltrado > 0"
                    class="flex flex-wrap items-center justify-between gap-3 border-t border-border bg-card px-3 py-2.5 sm:px-4"
                >
                    <p class="text-xs text-muted-foreground" role="status" aria-live="polite">
                        Mostrando
                        <span class="font-medium tabular-nums text-foreground">{{ rango.desde }}–{{ rango.hasta }}</span>
                        de
                        <span class="font-medium tabular-nums text-foreground">{{ totalFiltrado }}</span>
                    </p>

                    <nav class="flex items-center gap-1" aria-label="Paginación">
                        <button
                            type="button"
                            @click="cambiarPagina(1)"
                            :disabled="paginaActual === 1"
                            aria-label="Primera página"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-40"
                        >
                            <ChevronDoubleLeftIcon class="h-3.5 w-3.5" />
                        </button>
                        <button
                            type="button"
                            @click="cambiarPagina(paginaActual - 1)"
                            :disabled="paginaActual === 1"
                            aria-label="Página anterior"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-40"
                        >
                            <ChevronLeftIcon class="h-4 w-4" />
                        </button>

                        <template v-for="(p, i) in paginasVisibles" :key="i">
                            <span v-if="p === '...'" class="px-1 text-xs text-muted-foreground">…</span>
                            <button
                                v-else
                                type="button"
                                @click="cambiarPagina(Number(p))"
                                :aria-current="p === paginaActual ? 'page' : undefined"
                                :class="[
                                    'inline-flex h-8 min-w-8 items-center justify-center rounded-md px-2 text-xs font-medium tabular-nums transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                                    p === paginaActual
                                        ? 'bg-foreground text-background shadow-sm'
                                        : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                                ]"
                            >
                                {{ p }}
                            </button>
                        </template>

                        <button
                            type="button"
                            @click="cambiarPagina(paginaActual + 1)"
                            :disabled="paginaActual === paginas"
                            aria-label="Página siguiente"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-40"
                        >
                            <ChevronRightIcon class="h-4 w-4" />
                        </button>
                        <button
                            type="button"
                            @click="cambiarPagina(paginas)"
                            :disabled="paginaActual === paginas"
                            aria-label="Última página"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-40"
                        >
                            <ChevronDoubleRightIcon class="h-3.5 w-3.5" />
                        </button>
                    </nav>
                </footer>
            </div>

            <!-- Resumen bajo la tabla: totales y órdenes que requieren atención -->
            <section v-if="!carga.resumenListo || filas.length" class="rounded-xl border border-border bg-card shadow-sm">
                <template v-if="!carga.resumenListo">
                    <!-- Skeleton resumen -->
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-border px-4 py-3">
                        <div class="flex items-start gap-2.5">
                            <div class="h-8 w-8 animate-pulse rounded-lg bg-muted"></div>
                            <div class="space-y-1.5">
                                <div class="h-3.5 w-32 animate-pulse rounded bg-muted"></div>
                                <div class="h-3 w-56 animate-pulse rounded bg-muted/60"></div>
                            </div>
                        </div>
                        <div class="h-3 w-16 animate-pulse rounded bg-muted/60"></div>
                    </div>
                    <div class="flex flex-col gap-3 p-4">
                        <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                            <div v-for="n in 6" :key="n" class="animate-pulse rounded-lg border border-border bg-background p-3 space-y-2">
                                <div class="h-2.5 w-16 rounded bg-muted/60"></div>
                                <div class="h-7 w-12 rounded bg-muted"></div>
                                <div class="h-2.5 w-24 rounded bg-muted/60"></div>
                            </div>
                        </div>
                        <div class="grid gap-2.5 lg:grid-cols-2">
                            <div v-for="n in 3" :key="n" class="animate-pulse rounded-lg border border-border bg-background p-3 space-y-2">
                                <div class="flex items-center justify-between">
                                    <div class="h-3 w-28 rounded bg-muted"></div>
                                    <div class="h-5 w-10 rounded-full bg-muted/60"></div>
                                </div>
                                <div class="h-2.5 w-48 rounded bg-muted/60"></div>
                                <div class="h-28 rounded-md bg-muted/40"></div>
                            </div>
                        </div>
                    </div>
                </template>

                <template v-else>
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-border px-4 py-3">
                    <div class="flex items-start gap-2.5">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-border bg-muted/60">
                            <ChartBarIcon class="h-4 w-4 text-muted-foreground" />
                        </span>
                        <div>
                            <h2 class="text-sm font-semibold text-foreground">Resumen de órdenes</h2>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                {{
                                    hayFiltros
                                        ? 'Totales de las órdenes que coinciden con los filtros actuales.'
                                        : 'Totales generales y órdenes que requieren atención.'
                                }}
                            </p>
                        </div>
                    </div>
                    <span class="text-xs tabular-nums text-muted-foreground">
                        {{ totalFiltrado }} {{ totalFiltrado === 1 ? 'orden' : 'órdenes' }}
                    </span>
                </div>

                <div class="flex flex-col gap-3 p-4">
                    <!-- Totales principales -->
                    <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                        <div class="rounded-lg border border-border bg-background p-3">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.08em] text-muted-foreground">Órdenes</p>
                            <p class="mt-1 text-2xl font-semibold tabular-nums text-foreground">{{ totalFiltrado }}</p>
                            <p class="mt-0.5 text-[11px] text-muted-foreground">
                                {{ filas.length > totalFiltrado ? `de ${filas.length} en total` : 'registradas' }}
                            </p>
                        </div>

                        <div class="rounded-lg border border-border bg-background p-3">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.08em] text-muted-foreground">Unidades</p>
                            <p class="mt-1 text-2xl font-semibold tabular-nums text-foreground">
                                {{ zilef_nuber_format(totalUnidades, 0, false) }}
                            </p>
                            <span class="mt-0.5 inline-flex items-center gap-1 text-[11px] text-muted-foreground">
                                <CubeIcon class="h-3 w-3" />
                                planificadas
                            </span>
                        </div>

                        <div class="rounded-lg border border-border bg-background p-3">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.08em] text-muted-foreground">Costo OP</p>
                            <p class="mt-1 text-xl font-semibold tabular-nums text-foreground">
                                {{ formatdinero(totalCostoOp) }}
                            </p>
                            <p class="mt-0.5 text-[11px] text-muted-foreground">
                                {{ formatdinero(totalCostoFichas) }} fichas + {{ formatdinero(totalCostoProceso) }} proceso
                            </p>
                        </div>

                        <div class="rounded-lg border border-cyan-200/70 bg-cyan-50/40 p-3 dark:border-cyan-500/20 dark:bg-cyan-500/5">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.08em] text-cyan-700 dark:text-cyan-300">En taller</p>
                            <p class="mt-1 text-2xl font-semibold tabular-nums text-cyan-800 dark:text-cyan-300">{{ opsEnTaller.length }}</p>
                            <p class="mt-0.5 text-[11px] text-cyan-700/80 dark:text-cyan-300/80">en ensamble</p>
                        </div>

                        <div class="rounded-lg border border-amber-200/70 bg-amber-50/40 p-3 dark:border-amber-500/20 dark:bg-amber-500/5">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.08em] text-amber-700 dark:text-amber-300">Recibiendo</p>
                            <p class="mt-1 text-2xl font-semibold tabular-nums text-amber-800 dark:text-amber-300">{{ opsRecibiendo.length }}</p>
                            <p class="mt-0.5 text-[11px] text-amber-700/80 dark:text-amber-300/80">en control de calidad</p>
                        </div>

                        <div
                            class="rounded-lg border p-3"
                            :class="opsConFaltantes.length
                                ? 'border-rose-200/70 bg-rose-50/40 dark:border-rose-500/20 dark:bg-rose-500/5'
                                : 'border-emerald-200/70 bg-emerald-50/40 dark:border-emerald-500/20 dark:bg-emerald-500/5'"
                        >
                            <p
                                class="text-[10px] font-semibold uppercase tracking-[0.08em]"
                                :class="opsConFaltantes.length ? 'text-rose-700 dark:text-rose-300' : 'text-emerald-700 dark:text-emerald-300'"
                            >
                                Con faltantes
                            </p>
                            <p
                                class="mt-1 text-2xl font-semibold tabular-nums"
                                :class="opsConFaltantes.length ? 'text-rose-800 dark:text-rose-300' : 'text-emerald-800 dark:text-emerald-300'"
                            >
                                {{ opsConFaltantes.length }}
                            </p>
                            <p
                                class="mt-0.5 text-[11px]"
                                :class="opsConFaltantes.length ? 'text-rose-700/80 dark:text-rose-300/80' : 'text-emerald-700/80 dark:text-emerald-300/80'"
                            >
                                {{ opsSinFaltantes }} sin faltantes proyectados
                            </p>
                        </div>
                    </div>

                    <!-- Listados por situación -->
                    <div class="grid gap-2.5 lg:grid-cols-2">
                        <div class="contents">
                        <div class="order-2 rounded-lg border border-rose-200/70 bg-rose-50/40 p-3 dark:border-rose-500/20 dark:bg-rose-500/5">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <h3 class="inline-flex items-center gap-1.5 text-xs font-semibold text-rose-800 dark:text-rose-300">
                                    <ExclamationTriangleIcon class="h-4 w-4" />
                                    Faltantes proyectados
                                </h3>
                                <span class="rounded-full border border-rose-200 bg-white/60 px-2 py-0.5 text-[10px] font-semibold tabular-nums text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300">
                                    {{ materialesFaltantes.length }}
                                    {{ materialesFaltantes.length === 1 ? 'material' : 'materiales' }}
                                </span>
                            </div>
                            <p class="mt-0.5 text-[11px] text-rose-700/80 dark:text-rose-300/80">
                                Faltante tras descontar lo comprometido con otras OP. Haz clic para ver las OP y sus orígenes.
                            </p>

                            <div v-if="materialesFaltantes.length" class="mt-2 max-h-56 space-y-1.5 overflow-y-auto pr-1">
                                <div
                                    v-for="(material, indiceMaterial) in materialesFaltantes"
                                    :key="material.clave"
                                    class="overflow-hidden rounded-md border border-rose-200 bg-white/70 dark:border-rose-500/30 dark:bg-rose-500/10"
                                >
                                    <button
                                        type="button"
                                        @click="alternarMaterialFaltante(material.clave)"
                                        class="flex w-full items-center justify-between gap-2 px-2 py-1.5 text-left transition hover:bg-rose-100/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring dark:hover:bg-rose-500/15"
                                        :aria-expanded="materialesExpandidos[material.clave] ? 'true' : 'false'"
                                        :aria-controls="`faltantes-material-${indiceMaterial}`"
                                        :title="materialesExpandidos[material.clave] ? 'Ocultar órdenes' : 'Ver órdenes con este faltante'"
                                    >
                                        <span class="truncate text-[11px] font-semibold text-rose-900 dark:text-rose-200" :title="material.nombre">
                                            {{ material.nombre }}
                                        </span>
                                        <span class="flex shrink-0 items-center gap-1.5">
                                            <span class="text-[11px] font-medium tabular-nums text-rose-700 dark:text-rose-300">
                                                Faltan {{ zilef_nuber_format(material.faltante, 2, false) }}<template v-if="material.unidad"> {{ material.unidad }}</template>
                                            </span>
                                            <ChevronDownIcon
                                                class="h-3.5 w-3.5 text-rose-500 transition-transform dark:text-rose-300"
                                                :class="{ 'rotate-180': materialesExpandidos[material.clave] }"
                                            />
                                        </span>
                                    </button>

                                    <div
                                        v-if="materialesExpandidos[material.clave]"
                                        :id="`faltantes-material-${indiceMaterial}`"
                                        class="border-t border-rose-200/70 dark:border-rose-500/20"
                                    >
                                        <ul class="divide-y divide-rose-100/70 dark:divide-rose-500/20">
                                            <li
                                                v-for="detalle in material.ops"
                                                :key="detalle.op.id"
                                                class="px-2 py-1 text-[11px] text-rose-900 dark:text-rose-200"
                                            >
                                                <div class="flex flex-wrap items-center gap-x-1.5 gap-y-0.5">
                                                    <span class="font-semibold">{{ etiquetaOp(detalle.op) }}</span>
                                                    <template v-if="detalle.productos.length || detalle.op.productos_nombres || detalle.op.producto_nombre">
                                                        <span class="text-rose-400 dark:text-rose-500">·</span>
                                                        <span class="font-medium">
                                                            {{ detalle.productos.length ? detalle.productos.join(', ') : (detalle.op.productos_nombres || detalle.op.producto_nombre) }}
                                                        </span>
                                                    </template>
                                                    <span class="ml-auto tabular-nums text-rose-700/70 dark:text-rose-300/70">
                                                        Faltan {{ zilef_nuber_format(detalle.faltante, 2, false) }}<template v-if="material.unidad"> {{ material.unidad }}</template>
                                                    </span>
                                                </div>
                                                <div
                                                    v-if="detalle.origenes.length"
                                                    class="mt-0.5 flex flex-wrap items-center gap-x-1.5 text-[10px] text-rose-700/70 dark:text-rose-300/70"
                                                >
                                                    <span class="font-semibold uppercase tracking-[0.06em]">De</span>
                                                    <span
                                                        v-for="origen in detalle.origenes"
                                                        :key="`${origen.op_id}-${origen.numero_op}`"
                                                        class="rounded border border-rose-200/70 bg-white/60 px-1 py-px tabular-nums dark:border-rose-500/30 dark:bg-rose-500/10"
                                                        :title="origen.estado ? `Estado de la OP productora: ${origen.estado}` : undefined"
                                                    >
                                                        {{ origen.numero_op }}
                                                        ({{ zilef_nuber_format(origen.pendiente ?? origen.cantidad ?? 0, 2, false) }})
                                                    </span>
                                                </div>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <p v-else class="mt-2 inline-flex items-center gap-1.5 text-xs text-emerald-700 dark:text-emerald-300">
                                <CheckCircleIcon class="h-3.5 w-3.5" />
                                Ninguna orden tiene faltantes vigentes.
                            </p>
                        </div>

                        <div class="order-3 rounded-lg border border-cyan-200/70 bg-cyan-50/40 p-3 dark:border-cyan-500/20 dark:bg-cyan-500/5">
                            <div class="flex items-center justify-between gap-2">
                                <h3 class="inline-flex items-center gap-1.5 text-xs font-semibold text-cyan-800 dark:text-cyan-300">
                                    <WrenchScrewdriverIcon class="h-4 w-4" />
                                    En taller
                                </h3>
                                <span class="rounded-full border border-cyan-200 bg-white/60 px-2 py-0.5 text-[10px] font-semibold tabular-nums text-cyan-700 dark:border-cyan-500/30 dark:bg-cyan-500/10 dark:text-cyan-300">
                                    {{ opsEnTaller.length }}
                                </span>
                            </div>

                            <div v-if="opsEnTaller.length" class="mt-2 flex max-h-32 flex-wrap gap-1.5 overflow-y-auto pr-1">
                                <span
                                    v-for="op in opsEnTaller"
                                    :key="op.id"
                                    :title="op.producto_nombre || etiquetaOp(op)"
                                    class="inline-flex items-center rounded-full border border-cyan-200 bg-white/70 px-2 py-0.5 text-[11px] font-medium tabular-nums text-cyan-800 dark:border-cyan-500/30 dark:bg-cyan-500/10 dark:text-cyan-300"
                                >
                                    {{ etiquetaOp(op) }}
                                </span>
                            </div>
                            <p v-else class="mt-2 text-xs text-muted-foreground">Ninguna orden está en taller.</p>
                        </div>

                        <div class="order-4 rounded-lg border border-amber-200/70 bg-amber-50/40 p-3 dark:border-amber-500/20 dark:bg-amber-500/5">
                            <div class="flex items-center justify-between gap-2">
                                <h3 class="inline-flex items-center gap-1.5 text-xs font-semibold text-amber-800 dark:text-amber-300">
                                    <ClipboardDocumentCheckIcon class="h-4 w-4" />
                                    Recibiendo
                                </h3>
                                <span class="rounded-full border border-amber-200 bg-white/60 px-2 py-0.5 text-[10px] font-semibold tabular-nums text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
                                    {{ opsRecibiendo.length }}
                                </span>
                            </div>

                            <div v-if="opsRecibiendo.length" class="mt-2 flex max-h-32 flex-wrap gap-1.5 overflow-y-auto pr-1">
                                <span
                                    v-for="op in opsRecibiendo"
                                    :key="op.id"
                                    :title="op.producto_nombre || etiquetaOp(op)"
                                    class="inline-flex items-center rounded-full border border-amber-200 bg-white/70 px-2 py-0.5 text-[11px] font-medium tabular-nums text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300"
                                >
                                    {{ etiquetaOp(op) }}
                                </span>
                            </div>
                            <p v-else class="mt-2 text-xs text-muted-foreground">Ninguna orden está en recepción.</p>
                        </div>
                    </div>

                    <!-- Compromisos de reparación próximos -->
                    <div class="order-1 rounded-lg border border-violet-200/70 bg-violet-50/40 p-3 dark:border-violet-500/20 dark:bg-violet-500/5">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h3 class="inline-flex items-center gap-1.5 text-xs font-semibold text-violet-800 dark:text-violet-300">
                                <CalendarDaysIcon class="h-4 w-4" />
                                Compromisos pendientes
                            </h3>
                            <span class="rounded-full border border-violet-200 bg-white/60 px-2 py-0.5 text-[10px] font-semibold tabular-nums text-violet-700 dark:border-violet-500/30 dark:bg-violet-500/10 dark:text-violet-300">
                                {{ compromisosPorOp.length }}
                                {{ compromisosPorOp.length === 1 ? 'OP' : 'OPs' }}
                                · {{ compromisosProximos.length }}
                                {{ compromisosProximos.length === 1 ? 'fecha' : 'fechas' }}
                            </span>
                        </div>
                        <p class="mt-0.5 text-[11px] text-violet-700/80 dark:text-violet-300/80">
                            Fecha más próxima por OP. Haz clic en una OP para ver todos sus pendientes.
                        </p>

                        <div v-if="compromisosPorOp.length" class="mt-2 max-h-56 space-y-1.5 overflow-y-auto pr-1">
                            <div
                                v-for="grupo in compromisosPorOp"
                                :key="grupo.op.id"
                                class="overflow-hidden rounded-md border border-violet-200 bg-white/70 dark:border-violet-500/30 dark:bg-violet-500/10"
                            >
                                <button
                                    type="button"
                                    @click="alternarCompromisosOp(grupo.op.id)"
                                    class="flex w-full items-center justify-between gap-2 px-2 py-1.5 text-left transition hover:bg-violet-100/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring dark:hover:bg-violet-500/15"
                                    :aria-expanded="compromisosExpandidos[grupo.op.id] ? 'true' : 'false'"
                                    :aria-controls="`compromisos-op-${grupo.op.id}`"
                                    :title="compromisosExpandidos[grupo.op.id] ? 'Ocultar compromisos' : 'Ver todos los compromisos'"
                                >
                                    <span class="text-[11px] font-semibold text-violet-900 dark:text-violet-200">
                                        {{ etiquetaOp(grupo.op) }}
                                    </span>
                                    <span class="flex items-center gap-1.5">
                                        <span
                                            class="text-[11px] font-medium tabular-nums text-violet-700 dark:text-violet-300"
                                            title="Fecha de compromiso más próxima"
                                        >
                                            {{ formatFecha(grupo.compromisos[0]?.fecha_compromiso) }}
                                        </span>
                                        <ChevronDownIcon
                                            class="h-3.5 w-3.5 text-violet-500 transition-transform dark:text-violet-300"
                                            :class="{ 'rotate-180': compromisosExpandidos[grupo.op.id] }"
                                        />
                                    </span>
                                </button>

                                <div
                                    v-if="compromisosExpandidos[grupo.op.id]"
                                    :id="`compromisos-op-${grupo.op.id}`"
                                    class="border-t border-violet-200/70 dark:border-violet-500/20"
                                >
                                    <ul class="divide-y divide-violet-100/70 dark:divide-violet-500/20">
                                        <li
                                            v-for="compromiso in grupo.compromisos"
                                            :key="compromiso.id"
                                            class="flex flex-wrap items-center gap-x-1.5 gap-y-0.5 px-2 py-1 text-[11px] text-violet-900 dark:text-violet-200"
                                        >
                                            <span class="font-semibold tabular-nums">
                                                {{ formatFecha(compromiso.fecha_compromiso) }}
                                            </span>
                                            <span class="text-violet-400 dark:text-violet-500">·</span>
                                            <span class="font-medium">
                                                {{ compromiso.material_nombre || compromiso.producto_nombre || compromiso.op?.productos_nombres || compromiso.op?.materiales_nombres || compromiso.op?.producto_nombre || 'Producto de la OP' }}
                                            </span>
                                            <span v-if="compromiso.talla" class="text-violet-700/70 dark:text-violet-300/70">
                                                · Talla {{ compromiso.talla }}
                                            </span>
                                            <span
                                                v-if="compromiso.cantidad_en_reparacion"
                                                class="text-violet-700/70 dark:text-violet-300/70"
                                            >
                                                · {{ compromiso.cantidad_en_reparacion }} par(es)
                                            </span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <p v-else class="mt-2 inline-flex items-center gap-1.5 text-xs text-emerald-700 dark:text-emerald-300">
                            <CheckCircleIcon class="h-3.5 w-3.5" />
                            No hay fechas futuras de compromiso pendiente.
                        </p>
                    </div>
                    </div>

                    <!-- Distribución por estado -->
                    <div class="border-t border-border pt-3">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <h3 class="text-[10px] font-semibold uppercase tracking-[0.08em] text-muted-foreground">
                                Distribución por estado
                            </h3>
                            <span class="text-[10px] text-muted-foreground">
                                Una OP en coexistencia cuenta en ambos estados.
                            </span>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            <span
                                v-for="estado in resumenEstados"
                                :key="estado.id"
                                class="inline-flex items-center gap-1.5 rounded-full border px-2 py-1 text-[11px] font-medium"
                                :class="estiloEstado(estado.nombre).badge"
                            >
                                <span class="h-1.5 w-1.5 rounded-full" :class="estiloEstado(estado.nombre).dot"></span>
                                {{ estado.nombre }}
                                <span class="rounded-full bg-white/60 px-1.5 py-0.5 text-[10px] font-semibold tabular-nums dark:bg-black/20">
                                    {{ estado.count }}
                                </span>
                            </span>
                            <span v-if="!resumenEstados.length" class="text-xs text-muted-foreground">
                                Sin estados para mostrar.
                            </span>
                        </div>
                    </div>
                </div>
                </template>
            </section>

            <!-- Guía de estados: qué pasa y quién interviene -->
            <section class="rounded-xl border border-border bg-card shadow-sm">
                <details class="group">
                    <summary
                        class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-2.5 [&::-webkit-details-marker]:hidden"
                    >
                        <div class="min-w-0">
                            <h2 class="text-xs font-semibold text-foreground">Qué pasa en cada estado</h2>
                            <p class="mt-0.5 text-[11px] text-muted-foreground">
                                Quién interviene y qué se espera de la orden en cada paso del proceso.
                            </p>
                        </div>
                        <ChevronDownIcon
                            class="h-3.5 w-3.5 shrink-0 text-muted-foreground transition-transform group-open:rotate-180"
                        />
                    </summary>

                    <dl class="grid gap-x-4 gap-y-2 border-t border-border px-4 py-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div v-for="estado in guiaEstados" :key="estado.id" class="flex gap-2">
                            <span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full" :class="estiloEstado(estado.nombre).dot"></span>
                            <div class="min-w-0">
                                <dt class="text-[11px] font-semibold text-foreground">{{ estado.nombre }}</dt>
                                <dd class="mt-0.5 text-[11px] leading-relaxed text-muted-foreground">
                                    <span class="font-medium text-foreground/80">{{ estado.area }}</span>
                                    —
                                    <template v-for="(parte, indice) in estado.partes" :key="indice">
                                        <span
                                            v-if="parte.entidad"
                                            class="font-bold text-blue-600 dark:text-blue-400"
                                        >{{ parte.texto }}</span>
                                        <span v-else>{{ parte.texto }}</span>
                                    </template>
                                </dd>
                            </div>
                        </div>
                    </dl>
                </details>
            </section>
        </div>

        <!-- Modales (acciones que sí hablan con el backend) -->
        <Create
            v-if="can(['create OrdenProduccion'])"
            :numberPermissions="props.numberPermissions"
            :titulos="titulos"
            :show="data.createOpen"
            @close="data.createOpen = false; data.borradorCrear = null"
            :title="props.title"
            :losSelect="props.losSelect"
            :borrador="data.borradorCrear"
        />

        <Edit
            v-if="can(['update OrdenProduccion'])"
            :titulos="titulos"
            :numberPermissions="props.numberPermissions"
            :show="data.editOpen"
            @close="data.editOpen = false"
            :OrdenProducciona="(data as any).ordenEditando"
            :title="props.title"
            :losSelect="props.losSelect"
        />

        <Delete
            v-if="can(['delete OrdenProduccion'])"
            :numberPermissions="props.numberPermissions"
            :show="data.deleteOpen"
            @close="data.deleteOpen = false"
            :OrdenProducciona="(data as any).ordenEditando"
            :title="props.title"
        />

        <DistribucionTallas
            :show="data.distribucionOpen"
            @close="data.distribucionOpen = false"
            :title="props.title"
            :ordenId="data.distribucionOp?.id"
            :numeroOp="data.distribucionOp?.numero_op"
            :items="data.distribucionOp?.distribuciones_por_tallas || []"
            :canEdit="can(['update OrdenProduccion'])"
        />

        <KitProcesoOPModal
            :show="data.kitProcesoOpen"
            @close="data.kitProcesoOpen = false"
            :ordenProduccion="data.kitProcesoOp"
        />

        <CambiarEstadoModal
            :show="data.cambioEstadoOpen"
            @close="data.cambioEstadoOpen = false"
            :title="props.title"
            :op="data.cambioEstadoOp"
            :estados="(props.losSelect as any)?.estado_op_id || []"
            :mostrarRestaMateriales="props.mostrarRestaMateriales"
        />

        <DetalleEstadoModal
            :show="data.detalleEstadoOpen"
            @close="data.detalleEstadoOpen = false"
            :op="data.detalleEstadoOp"
            :metodo-costos-label="props.metodoCostosLabel"
        />

        <MaterialesOPModal :show="data.materialesOpen" @close="data.materialesOpen = false" :op="data.materialesOp" />
    </AppLayout>
</template>

<style scoped>
@keyframes op-page-in {
    from {
        opacity: 0;
        transform: translateY(3px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.op-page {
    animation: op-page-in 180ms cubic-bezier(0.22, 1, 0.36, 1);
}

.op-scroll {
    scrollbar-width: thin;
    scrollbar-color: var(--border) transparent;
}

.op-scroll::-webkit-scrollbar {
    width: 10px;
    height: 10px;
}

.op-scroll::-webkit-scrollbar-track {
    background: transparent;
}

.op-scroll::-webkit-scrollbar-thumb {
    background: var(--border);
    border: 3px solid transparent;
    border-radius: 9999px;
    background-clip: content-box;
}

@media (prefers-reduced-motion: reduce) {
    .op-page {
        animation: none;
    }
}
</style>
