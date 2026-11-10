<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import InputLabel from '@/components/InputLabel.vue';
import Modal from '@/components/Modal.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import DistribucionTallasEditor from './DistribucionTallasEditor.vue';
import { LockClosedIcon, PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/solid';
import { Link, useForm } from '@inertiajs/vue3';
import { computed, nextTick, reactive, ref, watch } from 'vue';
import vSelect from 'vue-select';
import 'vue-select/dist/vue-select.css';
import { formatDate, formatdinero } from '@/global.ts';
import { sePuedeEditarPlan } from '@/composables/flujoEstadosOp';

const props = defineProps({
    show: Boolean,
    title: String,
    OrdenProducciona: Object,
    titulos: Object,
    losSelect: Object,
    numberPermissions: Number,
});
const emit = defineEmits(['close']);

type ItemDistribucion = {
    id?: number;
    grupo?: number | null;
    talla: string;
    cantidad_planificada: number | string;
    producto_id?: number | null;
    material_id?: number | null;
    _origen?: 'producto' | 'grupo' | 'manual';
};

type MaterialSeleccionado = {
    id: number;
    nombre: string;
    unidad: string;
    cantidad: string;
};

const op = computed<any>(() => (props.OrdenProducciona ?? {}) as any);

const estadoActualTexto = computed(() => op.value.estado_nombre || 'Sin estado');

const fechaCreacionFormateada = computed(() => {
    const fecha: any = op.value.created_at;
    return fecha ? formatDate(fecha, false) : '';
});

/**
 * Reglas de edición (ver la explicación «Estado de la OP» en /Explicaciones):
 * numero_op, tipo y responsable nunca se cambian desde aquí; la fecha prevista
 * de envío, el destino, los productos y las tallas se congelan desde «En taller».
 */
const planBloqueado = computed(() => !sePuedeEditarPlan(op.value.estado_nombre));

const distribucion = reactive({
    items: [] as Array<ItemDistribucion>,
});

const grupoTallas = ref<number | null>(null);

// La OP puede producir productos o materiales procesados. El modo se deduce
// de los objetivos adjuntos y no se cambia desde la edición.
const esOpMaterial = ref(false);

const modalProductoDistribucion = reactive({
    show: false,
    productoId: null as number | null,
});

const productoEnEdicion = computed(() => {
    if (!modalProductoDistribucion.productoId) return null;
    return productosSel.find((p) => p.id === modalProductoDistribucion.productoId) ?? null;
});

const distribucionProductoEnEdicion = computed<ItemDistribucion[]>({
    get: () => {
        if (!modalProductoDistribucion.productoId) return [];
        return distribucion.items.filter((i) => i.producto_id === modalProductoDistribucion.productoId);
    },
    set: (nuevasFilas) => {
        if (!modalProductoDistribucion.productoId) return;
        const pid = modalProductoDistribucion.productoId;
        const ajenas = distribucion.items.filter((i) => i.producto_id !== pid);
        const asignadas = nuevasFilas.map((f) => ({ ...f, producto_id: pid }));
        distribucion.items = [...ajenas, ...asignadas];
    },
});

const abrirModalDistribucionProducto = (productoId: number) => {
    modalProductoDistribucion.productoId = productoId;
    modalProductoDistribucion.show = true;
};

const cerrarModalDistribucionProducto = () => {
    modalProductoDistribucion.show = false;
    modalProductoDistribucion.productoId = null;
};

const productosSel = reactive([] as Array<{ id: number; nombre: string; opciones: string[]; cantidad: string }>);
const selIds = ref<number[]>([]);

const materialesSel = reactive([] as MaterialSeleccionado[]);
const materialesSelIds = ref<number[]>([]);

const opcionesMaterial = computed(() => {
    const conFicha = (props.losSelect?.materiales_procesados || []).filter((m: any) => m.ficha);
    const ids = new Set(conFicha.map((m: any) => Number(m.id)));
    const adjuntos = ((op.value.materiales || []) as any[])
        .filter((m) => !ids.has(Number(m.id)))
        .map((m) => ({
            id: Number(m.id),
            name: m.codigo_material ? `${m.codigo_material} — ${m.nombre_base}` : (m.nombre_base || `Material #${m.id}`),
            unidad_inventario: m.unidad_inventario || 'u',
            ficha: { nombre: 'Ficha actual' },
            ficha_materiales: [],
        }));

    return [...conFicha, ...adjuntos];
});

const materialDe = (id: number) => opcionesMaterial.value.find((m: any) => Number(m.id) === Number(id));

const totalMateriales = computed(() =>
    materialesSel.reduce((total, m) => total + (Number(m.cantidad) || 0), 0),
);

const cantidadPlanificada = computed(() =>
    esOpMaterial.value ? totalMateriales.value : totalTallas.value,
);

const sinObjetivos = computed(() =>
    esOpMaterial.value ? materialesSel.length === 0 : productosSel.length === 0,
);

// Como en el alta: solo los productos con ficha técnica generan requerimiento
// de materiales. Se suman los que ya están adjuntos a esta OP (pueden no tener
// ficha) para que editar no los desprenda del kit.
const opcionesProducto = computed(() => {
    const conFicha = (props.losSelect?.producto_referencia_id || []).filter((p: any) => p.ficha);
    const ids = new Set(conFicha.map((p: any) => Number(p.id)));
    const adjuntos = ((op.value.productos || []) as any[])
        .filter((p) => !ids.has(Number(p.id)))
        .map((p) => ({
            id: Number(p.id),
            name: p.Titulo_Shopify || p.Codigo_interno || `Producto #${p.id}`,
            Opciones: p.Opciones ?? '',
            distribuciones_por_tallas: [],
            costo_proceso_total: (p.procesos || []).reduce((s: number, x: any) => s + (Number(x.coste_proceso) || 0), 0),
        }));
    return [...conFicha, ...adjuntos];
});

const distribucionPorProducto = (productoId: number) => distribucion.items.filter((i) => i.producto_id === productoId);

const totalCantidadDistribucionProducto = (productoId: number) =>
    distribucionPorProducto(productoId).reduce((acc, item) => acc + (Number(item.cantidad_planificada) || 0), 0);

const consumoPropioDelMaterial = (m: any): any => {
    const propioId = Number(op.value.id) || null;
    if (!propioId) return null;
    return (m.consumidores || []).find((c: any) => Number(c.op_id) === propioId) ?? null;
};

const proyeccionDelMaterial = (m: any) => {
    const proyectado = Number(m.proyectado) || 0;
    const propio = consumoPropioDelMaterial(m);
    // Lo ya reservado para esta misma OP no resta su propia disponibilidad.
    return proyectado + (Number(propio?.comprometido) || 0);
};

// Lo que esta OP puede tomar de cada productora: lo libre más lo que ya tiene
// reservado con ella (esa reserva es propia, no de terceros).
const productoresDelMaterial = (m: any): any[] => {
    const propio = consumoPropioDelMaterial(m);
    const propioPorOrigen = new Map<number, number>(
        (propio?.compromisos || []).map((c: any) => [
            Number(c.op_id),
            Math.max(0, Number(c.pendiente) || 0),
        ]),
    );

    return (m.productores || [])
        .filter((p: any) => Number(p.pendiente) > 0)
        .map((p: any) => ({
            ...p,
            libre: (Number(p.libre) || 0) + (propioPorOrigen.get(Number(p.op_id)) || 0),
        }));
};

const resumenMateriales = computed(() => {
    const mapa = new Map<
        number,
        {
            material_id: number;
            nombre: string;
            necesita: number;
            fisico: number;
            pendiente: number;
            comprometido: number;
            proyectado: number;
            unidad: string;
            productores: any[];
        }
    >();

    const sumarFicha = (ficha: any[], cantidad: number) => {
        (ficha || []).forEach((m: any) => {
            const actual = mapa.get(m.material_id) ?? {
                material_id: m.material_id,
                nombre: m.nombre,
                necesita: 0,
                fisico: Number(m.fisico ?? m.existencia_actual) || 0,
                pendiente: Number(m.pendiente) || 0,
                // Lo reservado por esta misma OP no compite contra ella.
                comprometido:
                    (Number(m.comprometido) || 0)
                    - (Number(consumoPropioDelMaterial(m)?.comprometido) || 0),
                proyectado: proyeccionDelMaterial(m),
                unidad: m.unidad_medida || '',
                productores: productoresDelMaterial(m),
            };
            actual.necesita += (Number(m.consumo_estandar) || 0) * cantidad;
            mapa.set(m.material_id, actual);
        });
    };

    productosSel.forEach((p) => {
        const opcion: any = opcionesProducto.value.find((o: any) => o.id === p.id);
        sumarFicha(opcion?.ficha_materiales, totalCantidadDistribucionProducto(p.id));
    });

    materialesSel.forEach((m) => {
        const opcion: any = materialDe(m.id);
        sumarFicha(opcion?.ficha_materiales, Number(m.cantidad) || 0);
    });

    return Array.from(mapa.values()).sort(
        (a, b) => b.necesita - b.proyectado - (a.necesita - a.proyectado),
    );
});

// Costo de los materiales por las cantidades distribuidas de cada producto.
// El precio es el que usa el parámetro costos_promedio (promedio o último);
// sin él, se conserva el costo teórico digitado en la ficha.
const precioMaterial = (m: any) =>
    m.precio_unitario !== undefined && m.precio_unitario !== null
        ? Number(m.precio_unitario) || 0
        : Number(m.costo_asociado_teorico) || 0;

const costoFichas = computed(() => {
    const costoDeFicha = (ficha: any[]) =>
        (ficha || []).reduce(
            (suma: number, m: any) => suma + (Number(m.consumo_estandar) || 0) * precioMaterial(m),
            0,
        );

    const costoProductos = productosSel.reduce((total, p) => {
        const opcion: any = opcionesProducto.value.find((o: any) => o.id === p.id);
        return total + costoDeFicha(opcion?.ficha_materiales) * totalCantidadDistribucionProducto(p.id);
    }, 0);

    const costoMateriales = materialesSel.reduce((total, m) => {
        const opcion: any = materialDe(m.id);
        return total + costoDeFicha(opcion?.ficha_materiales) * (Number(m.cantidad) || 0);
    }, 0);

    return costoProductos + costoMateriales;
});

const costoProceso = computed(() => Number(form.costo_proceso) || 0);
const costoTotal = computed(() => costoFichas.value + costoProceso.value);

const productosSinFicha = computed(() =>
    productosSel.filter((p) => {
        const op: any = opcionesProducto.value.find((o: any) => o.id === p.id);
        return !op?.ficha;
    }),
);

const hayFaltantes = computed(() => resumenMateriales.value.some((m) => m.necesita > m.proyectado));

const form = useForm({
    numero_op: '',
    cantidad_planificada: '',
    costo_proceso: 0,
    fecha_prevista_envio_taller: '',
    taller_id: '',
    tipo: 'taller',
    empresa_externa_id: '',
    id_shopify_producto: '',
    observaciones: '',
    distribucion_tallas: [] as Array<{ id?: number; producto_id?: number | null; talla: string; cantidad_planificada: number }>,
    grupo_tallas: null as number | null,
    productos: [] as number[],
    productos_cantidad: {} as Record<number, number>,
    materiales: [] as number[],
    materiales_cantidad: {} as Record<number, number>,
});

// El formulario muestra el número, el tipo y la fecha aunque estén bloqueados,
// pero esos campos nunca viajan en el update: así el backend conserva el valor
// registrado y no depende de que el navegador haya ocultado el input.
const CAMPOS_NUNCA_EDITABLES = ['numero_op', 'tipo'];
const CAMPOS_DEL_PLAN = [
    'fecha_prevista_envio_taller',
    'taller_id',
    'empresa_externa_id',
    'cantidad_planificada',
    'productos',
    'productos_cantidad',
    'materiales',
    'materiales_cantidad',
    'distribucion_tallas',
    'grupo_tallas',
];

form.transform((datos: any) => {
    const enviados = { ...datos };
    [...CAMPOS_NUNCA_EDITABLES, ...(planBloqueado.value ? CAMPOS_DEL_PLAN : [])].forEach((campo) => delete enviados[campo]);
    return enviados;
});

const totalTallas = computed(() => distribucion.items.reduce((acc, i) => acc + (Number(i.cantidad_planificada) || 0), 0));

// Costo de proceso de la OP: suma de los procesos de cada producto por las
// unidades de ese producto en la orden. No se digita; el backend guarda esta
// misma fórmula al recalcular los costos.
const costoProcesoDerivado = computed(() => {
    if (esOpMaterial.value) return 0;

    const cantidades: Record<number, number> = {};
    let sinProducto = 0;
    for (const item of distribucion.items) {
        const cantidad = Number(item.cantidad_planificada) || 0;
        if (item.producto_id) {
            cantidades[item.producto_id] = (cantidades[item.producto_id] || 0) + cantidad;
        } else {
            sinProducto += cantidad;
        }
    }

    const destino = productosSel[0]?.id;
    if (!Object.keys(cantidades).length) {
        if (destino) cantidades[destino] = totalTallas.value || (Number(form.cantidad_planificada) || 0);
    } else if (sinProducto > 0 && destino) {
        cantidades[destino] = (cantidades[destino] || 0) + sinProducto;
    }

    return productosSel.reduce((total: number, p) => {
        const opcion: any = opcionesProducto.value.find((o: any) => o.id === p.id);
        return total + (Number(opcion?.costo_proceso_total) || 0) * (cantidades[p.id] || 0);
    }, 0);
});

watch(
    costoProcesoDerivado,
    (valor) => {
        form.costo_proceso = Math.round(valor);
    },
    { immediate: true },
);

const parsearOpciones = (opciones: unknown): string[] => {
    const partes = String(opciones ?? '')
        .split(/[,-]/)
        .map((s) => s.trim())
        .filter(Boolean);
    return partes.length ? partes : ['Única'];
};

const agregarTallasDeProducto = (productoId: number, opciones: string[]) => {
    for (const talla of opciones) {
        const existe = distribucion.items.some((i) => i.producto_id === productoId && i.talla.trim().toLowerCase() === talla.trim().toLowerCase());
        if (!existe) {
            distribucion.items.push({ talla, cantidad_planificada: '1', producto_id: productoId, _origen: 'producto' });
        }
    }
};

const quitarTallasDeProducto = (productoId: number) => {
    distribucion.items = distribucion.items.filter((i) => i.producto_id !== productoId);
};

// Mientras se carga la OP se evita que la selección siembre tallas nuevas: las
// tallas reales ya vienen del registro de la orden.
const cargandoDatos = ref(false);

const sincronizarProductos = (ids: number[]) => {
    for (const id of ids) {
        if (!productosSel.some((p) => p.id === id)) {
            const op: any = opcionesProducto.value.find((p: any) => p.id === id);
            if (!op) continue;
            productosSel.push({ id, nombre: op.name, opciones: parsearOpciones(op.Opciones), cantidad: '1' });
            if (!cargandoDatos.value) {
                agregarTallasDeProducto(id, parsearOpciones(op.Opciones));
            }
        }
    }
    for (let i = productosSel.length - 1; i >= 0; i--) {
        if (!ids.includes(productosSel[i].id)) {
            quitarTallasDeProducto(productosSel[i].id);
            productosSel.splice(i, 1);
        }
    }
};

watch(selIds, (ids) => sincronizarProductos([...ids]));

const sincronizarMateriales = (ids: number[]) => {
    for (const id of ids) {
        if (!materialesSel.some((m) => m.id === id)) {
            const opcion = materialDe(id);
            if (!opcion) continue;
            materialesSel.push({
                id,
                nombre: opcion.name,
                unidad: opcion.unidad_inventario || 'u',
                cantidad: '1',
            });
        }
    }

    for (let i = materialesSel.length - 1; i >= 0; i--) {
        if (!ids.includes(materialesSel[i].id)) {
            materialesSel.splice(i, 1);
        }
    }
};

watch(materialesSelIds, (ids) => sincronizarMateriales([...ids]));

const toggleProducto = (id: number) => {
    if (planBloqueado.value) return;
    const i = selIds.value.indexOf(id);
    if (i >= 0) {
        selIds.value.splice(i, 1);
    } else {
        selIds.value.push(id);
    }
};

const quitarProducto = (id: number) => {
    toggleProducto(id);
};

const closeModal = () => {
    emit('close');
    form.reset();
    distribucion.items = [];
    grupoTallas.value = null;
    modalProductoDistribucion.show = false;
    modalProductoDistribucion.productoId = null;
    productosSel.length = 0;
    selIds.value = [];
    materialesSel.length = 0;
    materialesSelIds.value = [];
    esOpMaterial.value = false;
};

const prepararPayload = () => {
    form.grupo_tallas = grupoTallas.value;

    if (esOpMaterial.value) {
        form.productos = [];
        form.productos_cantidad = {};
        form.materiales = [...materialesSelIds.value];
        form.materiales_cantidad = Object.fromEntries(
            materialesSel.map((m) => [m.id, Number(m.cantidad) || 1]),
        );
        form.cantidad_planificada = String(totalMateriales.value);
        form.distribucion_tallas = [];
        return;
    }

    form.materiales = [];
    form.materiales_cantidad = {};
    form.productos = [...selIds.value];
    form.productos_cantidad = Object.fromEntries(productosSel.map((p: any) => [p.id, totalCantidadDistribucionProducto(p.id)]));
    form.cantidad_planificada = String(totalTallas.value);
    form.distribucion_tallas = distribucion.items
        .filter((item) => (item.talla && item.talla.trim() !== '') || Number(item.cantidad_planificada) > 0)
        .map((item) => ({
            ...(item.id ? { id: item.id } : {}),
            producto_id: item.producto_id ?? null,
            talla: (item.talla ?? '').trim(),
            cantidad_planificada: Number(item.cantidad_planificada) || 0,
        }));
};

const update = () => {
    prepararPayload();
    form.put(route('OrdenProduccion.update', op.value.id), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => null,
    });
};

const cargarOp = () => {
    if (!props.show) return;
    form.errors = {};
    const registro = op.value;

    cargandoDatos.value = true;
    productosSel.length = 0;
    materialesSel.length = 0;
    distribucion.items = [];

    form.numero_op = registro.numero_op ?? '';
    form.cantidad_planificada = registro.cantidad_planificada != null ? String(registro.cantidad_planificada) : '';
    form.fecha_prevista_envio_taller = String(registro.fecha_prevista_envio_taller ?? '').substring(0, 10);
    form.taller_id = registro.taller_id ?? '';
    form.tipo = registro.tipo ?? (registro.externa ? 'externa' : 'taller');
    form.empresa_externa_id = registro.empresa_externa_id ?? '';
    form.id_shopify_producto = registro.id_shopify_producto ?? '';
    form.observaciones = registro.observaciones ?? '';

    const materialesRegistro = ((registro.materiales || []) as any[]).map((m) => ({
        id: Number(m.id),
        nombre: m.codigo_material ? `${m.codigo_material} — ${m.nombre_base}` : (m.nombre_base || `Material #${m.id}`),
        unidad: m.unidad_inventario || 'u',
        cantidad: String(m.pivot?.cantidad ?? 1),
    }));

    esOpMaterial.value = materialesRegistro.length > 0;

    if (esOpMaterial.value) {
        materialesSel.splice(0, materialesSel.length, ...materialesRegistro);
        materialesSelIds.value = materialesRegistro.map((m) => m.id);
        selIds.value = [];
        distribucion.items = [];
        grupoTallas.value = null;
    } else {
        materialesSel.splice(0, materialesSel.length);
        materialesSelIds.value = [];
        const tallas = ((registro.distribuciones_por_tallas || []) as any[]).filter((t) => !t.material_id);
        distribucion.items = tallas.map((t) => ({
            id: t.id,
            grupo: t.grupo ?? null,
            talla: t.talla ?? '',
            cantidad_planificada: t.cantidad_planificada ?? 0,
            producto_id: t.producto_id ?? null,
            _origen: 'manual',
        }));
        const conGrupo = tallas.find((t) => t.grupo != null);
        grupoTallas.value = conGrupo ? conGrupo.grupo : null;

        selIds.value = ((registro.productos || []) as any[]).map((p) => Number(p.id));
    }

    nextTick(() => {
        cargandoDatos.value = false;
    });
};

watch([() => props.show, () => op.value.id], cargarOp, { immediate: true, flush: 'post' });
</script>

<template>
    <section class="space-y-6">
        <Modal :show="props.show" @close="closeModal" :maxWidth="'xl6'">
            <form class="p-6" @submit.prevent="update">
                <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-3">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                            {{ lang().t('app.label.edit') }} {{ props.title }} — {{ form.numero_op || `#${op.id}` }}
                        </h2>
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                        >
                            Responsable:
                            <b class="font-semibold">{{ op.responsable_nombre || 'Sin responsable' }}</b>
                        </span>
                        <span
                            class="inline-flex items-center rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-700 dark:border-indigo-800/60 dark:bg-indigo-950/40 dark:text-indigo-300"
                        >
                            Estado: {{ estadoActualTexto }}
                        </span>
                    </div>
                    <button
                        type="button"
                        @click="closeModal"
                        class="-mr-2 rounded-md px-2 text-xl leading-none font-bold text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                        title="Cerrar"
                        aria-label="Cerrar"
                    >
                        ✕
                    </button>
                </div>

                <div v-if="fechaCreacionFormateada" class="mb-4 text-xs text-gray-500 dark:text-gray-400">Creada: {{ fechaCreacionFormateada }}</div>

                <div
                    v-if="planBloqueado"
                    class="mb-6 flex flex-wrap items-center gap-2 rounded-lg border border-amber-200 bg-amber-50/70 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-300"
                    role="status"
                >
                    <LockClosedIcon class="h-4 w-4 shrink-0" />
                    <span>
                        La OP está en «{{ estadoActualTexto }}». Desde «En taller» la fecha de envío, el destino, los productos y las tallas quedan
                        congelados porque el kit ya salió de bodega.
                    </span>
                    <Link
                        :href="route('Explicaciones.index', { categoria: 'OP', subcategoria: 'Estado de la OP' })"
                        class="font-semibold underline decoration-dotted underline-offset-2 hover:text-amber-900 dark:hover:text-amber-200"
                    >
                        Ver explicación
                    </Link>
                </div>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div>
                        <InputLabel for="numero_op" :value="lang().t('app.label.numero_op') || 'Número OP'" />
                        <TextInput
                            id="numero_op"
                            type="text"
                            class="mt-1 block w-full cursor-not-allowed bg-gray-100 dark:bg-gray-800"
                            v-model="form.numero_op"
                            readonly
                        />
                        <p class="mt-1 flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                            <LockClosedIcon class="h-3.5 w-3.5" /> Se asigna al crear la OP y no se cambia.
                        </p>
                        <InputError class="mt-2" :message="form.errors.numero_op" />
                    </div>

                    <div>
                        <InputLabel for="fecha_prevista_envio_taller" :value="lang().t('app.label.fecha_prevista_envio_taller') || 'Fecha Prevista Envío Taller'" />
                        <TextInput
                            id="fecha_prevista_envio_taller"
                            type="date"
                            class="mt-1 block w-full"
                            :class="planBloqueado ? 'cursor-not-allowed bg-gray-100 dark:bg-gray-800' : ''"
                            v-model="form.fecha_prevista_envio_taller"
                            :disabled="planBloqueado"
                        />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ planBloqueado ? 'Bloqueada por el estado de la OP.' : 'Opcional.' }}
                        </p>
                        <InputError class="mt-2" :message="form.errors.fecha_prevista_envio_taller" />
                    </div>

                    <div>
                        <InputLabel for="tipo" value="Tipo" />
                        <div class="mt-1 grid grid-cols-2 gap-2" aria-disabled="true">
                            <span
                                :class="[
                                    'flex h-10 items-center justify-center rounded-md border text-sm font-medium',
                                    form.tipo === 'taller'
                                        ? 'border-indigo-600 bg-indigo-600 text-white'
                                        : 'border-gray-200 bg-gray-50 text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-500',
                                ]"
                            >
                                Taller
                            </span>
                            <span
                                :class="[
                                    'flex h-10 items-center justify-center rounded-md border text-sm font-medium',
                                    form.tipo === 'externa'
                                        ? 'border-indigo-600 bg-indigo-600 text-white'
                                        : 'border-gray-200 bg-gray-50 text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-500',
                                ]"
                            >
                                Externa
                            </span>
                        </div>
                        <p class="mt-1 flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                            <LockClosedIcon class="h-3.5 w-3.5" /> El tipo se elige al crear la orden.
                        </p>
                    </div>

                    <div>
                        <InputLabel value="Costo del proceso" />
                        <div
                            class="mt-1 flex h-10 w-full items-center justify-between rounded-md border border-gray-200 bg-gray-50 px-3 dark:border-gray-700 dark:bg-gray-900/40"
                        >
                            <span class="text-sm font-semibold tabular-nums text-gray-900 dark:text-gray-100">
                                {{ formatdinero(costoProcesoDerivado) }}
                            </span>
                            <span class="text-[11px] font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                Automático
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Suma de los procesos de cada producto de la OP por su cantidad planificada. Se recalcula solo.
                        </p>
                    </div>

                    <div v-if="form.tipo !== 'externa'">
                        <InputLabel for="taller_id" :value="lang().t('app.label.taller') || 'Taller'" />
                        <v-select
                            id="taller_id"
                            class="mt-1 block w-full"
                            :options="props.losSelect?.taller_id || []"
                            label="name"
                            :reduce="(item: any) => item.id"
                            v-model="form.taller_id"
                            :disabled="planBloqueado"
                            placeholder="Seleccione un taller..."
                        />
                        <p v-if="planBloqueado" class="mt-1 flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                            <LockClosedIcon class="h-3.5 w-3.5" /> Bloqueado por el estado de la OP.
                        </p>
                        <InputError class="mt-2" :message="form.errors.taller_id" />
                    </div>

                    <div v-else>
                        <InputLabel for="empresa_externa_id" value="Empresa externa" />
                        <v-select
                            id="empresa_externa_id"
                            class="mt-1 block w-full"
                            :options="props.losSelect?.empresa_externa_id || []"
                            label="name"
                            :reduce="(item: any) => item.id"
                            v-model="form.empresa_externa_id"
                            :disabled="planBloqueado"
                            placeholder="Seleccione una empresa externa..."
                        />
                        <p v-if="planBloqueado" class="mt-1 flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                            <LockClosedIcon class="h-3.5 w-3.5" /> Bloqueado por el estado de la OP.
                        </p>
                        <InputError class="mt-2" :message="form.errors.empresa_externa_id" />
                    </div>

                    <div>
                        <InputLabel for="id_shopify_producto" :value="lang().t('app.label.id_shopify_producto') || 'ID Shopify Producto'" />
                        <TextInput
                            id="id_shopify_producto"
                            type="text"
                            class="mt-1 block w-full"
                            v-model="form.id_shopify_producto"
                            placeholder="ID Shopify (opcional)"
                        />
                        <InputError class="mt-2" :message="form.errors.id_shopify_producto" />
                    </div>

                    <div class="md:col-span-3">
                        <InputLabel for="observaciones" :value="lang().t('app.label.observaciones') || 'Observaciones'" />
                        <textarea
                            id="observaciones"
                            v-model="form.observaciones"
                            rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-indigo-600 dark:focus:ring-indigo-600"
                            placeholder="Observaciones de la orden..."
                        ></textarea>
                        <InputError class="mt-2" :message="form.errors.observaciones" />
                    </div>
                </div>

                <!-- Materiales procesados de la orden -->
                <div v-if="esOpMaterial" class="mt-6 rounded-lg border border-amber-200 bg-amber-50/40 p-4 dark:border-amber-900/60 dark:bg-amber-950/20">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                            Materiales procesados de la orden
                            <span v-if="materialesSel.length" class="font-normal text-gray-500 dark:text-gray-400">
                                ({{ materialesSel.length }})
                            </span>
                            <LockClosedIcon v-if="planBloqueado" class="ml-1 inline h-4 w-4 text-amber-500" />
                        </h3>
                        <div class="text-right">
                            <span class="block text-xs text-gray-500 dark:text-gray-400">Cantidad planificada:</span>
                            <span class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ totalMateriales }}</span>
                        </div>
                    </div>

                    <v-select
                        v-if="!planBloqueado"
                        multiple
                        class="block w-full"
                        :options="opcionesMaterial"
                        label="name"
                        :reduce="(item: any) => item.id"
                        v-model="materialesSelIds"
                        placeholder="Buscar y seleccionar materiales procesados..."
                    />
                    <p v-else class="text-xs text-gray-500 dark:text-gray-400">
                        Lista en solo lectura: para cambiar los materiales hay que retroceder la OP antes de «En taller».
                    </p>

                    <div v-if="materialesSel.length" class="mt-3 overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-amber-200 text-left text-xs tracking-wide text-gray-500 uppercase dark:border-amber-900/60 dark:text-gray-400">
                                    <th class="py-2 pr-2">Material</th>
                                    <th class="w-32 py-2 pr-2">Cantidad</th>
                                    <th class="py-2 pr-2">Ficha técnica</th>
                                    <th v-if="!planBloqueado" class="w-10 py-2"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="m in materialesSel" :key="m.id" class="border-b border-amber-100 align-top dark:border-amber-900/40">
                                    <td class="py-3 pr-2 font-medium text-gray-800 dark:text-gray-200">{{ m.nombre }}</td>
                                    <td class="py-3 pr-2">
                                        <div class="flex items-center gap-1.5">
                                            <input
                                                v-model="m.cantidad"
                                                type="number"
                                                min="1"
                                                step="1"
                                                :disabled="planBloqueado"
                                                class="w-20 rounded-md border-gray-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:disabled:bg-gray-800"
                                            />
                                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ m.unidad }}</span>
                                        </div>
                                    </td>
                                    <td class="py-3 pr-2 text-xs text-gray-600 dark:text-gray-300">
                                        {{ materialDe(m.id)?.ficha?.nombre || '—' }}
                                        <span class="block text-gray-400 dark:text-gray-500">
                                            {{ (materialDe(m.id)?.ficha_materiales || []).length }} componente(s)
                                        </span>
                                    </td>
                                    <td v-if="!planBloqueado" class="py-3">
                                        <button
                                            type="button"
                                            @click="materialesSelIds = materialesSelIds.filter((id) => id !== m.id)"
                                            class="rounded-md p-1.5 text-red-500 transition hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-900/20"
                                            title="Quitar material"
                                        >
                                            <TrashIcon class="h-4 w-4" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="mt-3 text-sm text-amber-600 dark:text-amber-400">
                        Esta orden no tiene materiales procesados registrados.
                    </p>

                    <InputError class="mt-2" :message="form.errors.materiales" />
                    <InputError class="mt-1" :message="form.errors.materiales_cantidad" />
                    <InputError class="mt-1" :message="form.errors.cantidad_planificada" />
                </div>

                <!-- Productos de la orden -->
                <div v-else class="mt-6 rounded-lg border border-gray-200 bg-gray-50/50 p-4 dark:border-gray-700 dark:bg-gray-800/40">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                            Productos de la orden
                            <span v-if="productosSel.length" class="font-normal text-gray-500 dark:text-gray-400"> ({{ productosSel.length }}) </span>
                            <LockClosedIcon v-if="planBloqueado" class="ml-1 inline h-4 w-4 text-amber-500" />
                        </h3>
                        <div class="text-right">
                            <span class="block text-xs text-gray-500 dark:text-gray-400">Cantidad planificada (suma de tallas):</span>
                            <span class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ totalTallas }}</span>
                        </div>
                    </div>

                    <v-select
                        v-if="!planBloqueado"
                        multiple
                        class="block w-full"
                        :options="opcionesProducto"
                        label="name"
                        :reduce="(item: any) => item.id"
                        v-model="selIds"
                        placeholder="Buscar y seleccionar productos..."
                    />
                    <p v-else class="text-xs text-gray-500 dark:text-gray-400">
                        Lista en solo lectura: para cambiar productos hay que retroceder la OP antes de «En taller».
                    </p>

                    <p v-if="!planBloqueado" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Solo se muestran los productos que tienen ficha técnica (los demás no generan requerimiento de materiales).
                    </p>

                    <div v-if="productosSel.length" class="mt-3 overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr
                                    class="border-b border-gray-200 text-left text-xs tracking-wide text-gray-500 uppercase dark:border-gray-700 dark:text-gray-400"
                                >
                                    <th class="w-10 py-2 pr-2"></th>
                                    <th class="py-2 pr-2">Producto</th>
                                    <th class="w-28 py-2 pr-2">Cantidad</th>
                                    <th class="py-2 pr-2">Distribución por Tallas</th>
                                    <th v-if="!planBloqueado" class="w-10 py-2"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="p in productosSel" :key="p.id" class="border-b border-gray-100 align-top dark:border-gray-700/60">
                                    <td class="py-3 pr-2">
                                        <input
                                            type="checkbox"
                                            class="text-primary dark:text-primary focus:ring-primary/80 dark:focus:ring-primary dark:checked:bg-primary dark:checked:border-primary rounded border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:focus:ring-offset-gray-800"
                                            :checked="selIds.includes(p.id)"
                                            :disabled="planBloqueado"
                                            @change="toggleProducto(p.id)"
                                        />
                                    </td>
                                    <td class="py-3 pr-2 font-medium text-gray-800 dark:text-gray-200">
                                        {{ p.nombre }}
                                    </td>
                                    <td class="py-3 pr-2">
                                        <span class="font-semibold text-gray-900 dark:text-gray-100" title="Suma de las cantidades de sus tallas">
                                            {{ totalCantidadDistribucionProducto(p.id) }}
                                        </span>
                                    </td>
                                    <td class="py-3 pr-2">
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <template v-if="distribucionPorProducto(p.id).length">
                                                <span
                                                    v-for="(item, idx) in distribucionPorProducto(p.id)"
                                                    :key="item.id ?? `dp-${p.id}-${idx}`"
                                                    class="inline-flex items-center rounded-md border border-indigo-200 bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:border-indigo-800/60 dark:bg-indigo-950/40 dark:text-indigo-300"
                                                    :title="`Talla ${item.talla}: ${item.cantidad_planificada} unidades`"
                                                >
                                                    <span class="mr-1 font-semibold">{{ item.talla }}:</span>
                                                    <span>{{ item.cantidad_planificada }}</span>
                                                </span>
                                                <span class="ml-1 text-xs font-medium text-gray-400 dark:text-gray-500">
                                                    (Total: {{ totalCantidadDistribucionProducto(p.id) }})
                                                </span>
                                            </template>
                                            <span v-else class="text-xs text-gray-400 italic dark:text-gray-500"> Sin tallas asignadas </span>

                                            <button
                                                v-if="!planBloqueado"
                                                type="button"
                                                @click="abrirModalDistribucionProducto(p.id)"
                                                class="ml-2 inline-flex items-center gap-1 rounded border border-indigo-300 bg-white px-2 py-0.5 text-xs font-medium text-indigo-600 shadow-xs transition hover:bg-indigo-50 dark:border-indigo-700 dark:bg-gray-800 dark:text-indigo-400 dark:hover:bg-indigo-950/60"
                                                title="Editar distribución específica de este producto"
                                            >
                                                <PencilSquareIcon class="h-3.5 w-3.5" />
                                                <span>Editar tallas</span>
                                            </button>
                                        </div>
                                    </td>
                                    <td v-if="!planBloqueado" class="py-3">
                                        <button
                                            type="button"
                                            @click="quitarProducto(p.id)"
                                            class="rounded-md p-1.5 text-red-500 transition hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-900/20"
                                            title="Quitar producto"
                                        >
                                            <TrashIcon class="h-4 w-4" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="mt-3 text-sm text-amber-600 dark:text-amber-400">
                        Esta orden no tiene productos registrados. <b>No se puede guardar sin al menos un producto.</b>
                    </p>

                    <InputError class="mt-2" :message="form.errors.productos" />
                    <InputError class="mt-1" :message="form.errors.cantidad_planificada" />
                </div>

                <!-- Materiales requeridos según la ficha de los objetivos -->
                <div
                    v-if="resumenMateriales.length"
                    class="mt-6 rounded-lg border border-gray-200 bg-gray-50/50 p-4 dark:border-gray-700 dark:bg-gray-800/40"
                >
                    <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                        Materiales requeridos
                        <span class="font-normal text-gray-500 dark:text-gray-400">
                            (consumo ficha × {{ esOpMaterial ? 'cantidad del material' : 'total de tallas' }})
                        </span>
                    </h4>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Proyectado = físico + producción pendiente − compromisos con otras OP.
                    </p>
                    <div class="mt-2 overflow-x-auto">
                        <table class="w-full min-w-[860px] text-sm">
                            <thead>
                                <tr class="text-left text-xs text-gray-500 uppercase dark:text-gray-400">
                                    <th class="py-1 pr-2">Material</th>
                                    <th class="py-1 pr-2 text-right">Necesita</th>
                                    <th class="py-1 pr-2 text-right">Físico</th>
                                    <th class="py-1 pr-2 text-right">En producción</th>
                                    <th class="py-1 pr-2 text-right">Comprometido</th>
                                    <th class="py-1 pr-2 text-right">Proyectado</th>
                                    <th class="py-1 pr-2 text-right">Faltante</th>
                                    <th class="py-1">De dónde</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="m in resumenMateriales"
                                    :key="m.material_id"
                                    :class="
                                        m.necesita > m.proyectado
                                            ? 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400'
                                            : 'text-gray-700 dark:text-gray-300'
                                    "
                                >
                                    <td class="px-1 py-1 pr-2">{{ m.nombre }}</td>
                                    <td class="px-1 py-1 pr-2 text-right">
                                        {{ m.necesita.toLocaleString() }} {{ m.unidad }}
                                    </td>
                                    <td class="px-1 py-1 pr-2 text-right">{{ m.fisico.toLocaleString() }}</td>
                                    <td class="px-1 py-1 pr-2 text-right">{{ m.pendiente.toLocaleString() }}</td>
                                    <td class="px-1 py-1 pr-2 text-right">{{ m.comprometido.toLocaleString() }}</td>
                                    <td class="px-1 py-1 pr-2 text-right font-medium">{{ m.proyectado.toLocaleString() }}</td>
                                    <td class="px-1 py-1 pr-2 text-right font-semibold">
                                        {{
                                            m.necesita > m.proyectado
                                                ? 'Falta: ' + (m.necesita - m.proyectado).toLocaleString()
                                                : '—'
                                        }}
                                    </td>
                                    <td class="px-1 py-1 text-xs">
                                        <span v-if="m.productores.length">
                                            {{
                                                m.productores
                                                    .map((p: any) => `${p.numero_op} (${Number(p.libre).toLocaleString()})`)
                                                    .join(', ')
                                            }}
                                        </span>
                                        <span v-else class="text-gray-400 dark:text-gray-500">Sin producción pendiente</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-if="hayFaltantes" class="mt-2 text-xs font-medium text-red-600 dark:text-red-400">
                        Inventario proyectado insuficiente: al guardar la orden se generará una notificación de faltantes.
                    </p>
                    <p
                        v-if="!esOpMaterial && productosSinFicha.length"
                        class="mt-1 text-xs text-amber-600 dark:text-amber-400"
                    >
                        Sin ficha técnica (no generan requerimiento):
                        {{ productosSinFicha.map((p) => p.nombre).join(', ') }}
                    </p>
                </div>

                <!-- Distribución por tallas integrada -->
                <div v-if="!esOpMaterial" class="mt-6 rounded-lg border border-gray-200 bg-gray-50/50 p-4 dark:border-gray-700 dark:bg-gray-800/40">
                    <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                            Distribución por tallas de la orden
                            <LockClosedIcon v-if="planBloqueado" class="ml-1 inline h-4 w-4 text-amber-500" />
                        </h3>
                        <span v-if="productosSel.length > 1" class="text-xs font-medium text-indigo-600 dark:text-indigo-400">
                            {{ productosSel.length }} productos seleccionados
                        </span>
                    </div>
                    <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">
                        {{
                            planBloqueado
                                ? 'Cantidades en solo lectura: el kit ya salió de bodega al pasar a «En taller».'
                                : 'Tallas cargadas de la orden (o de la plantilla del producto al agregar uno nuevo). Se diferencian por producto mediante etiquetas y puede editar cada producto en su modal.'
                        }}
                    </p>

                    <!-- Lista diferenciada por producto si hay más de 1 producto -->
                    <div v-if="productosSel.length > 1" class="mb-4 space-y-4">
                        <div
                            v-for="p in productosSel"
                            :key="`card-prod-${p.id}`"
                            class="rounded-md border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900/60"
                        >
                            <div class="flex items-center justify-between border-b border-gray-100 pb-2 dark:border-gray-800">
                                <div class="flex items-center gap-2">
                                    <span class="inline-block h-2.5 w-2.5 rounded-full bg-indigo-500"></span>
                                    <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ p.nombre }}</span>
                                    <span class="text-xs text-gray-400 dark:text-gray-500"
                                        >(Cant. producto: {{ totalCantidadDistribucionProducto(p.id) }})</span
                                    >
                                </div>
                                <button
                                    v-if="!planBloqueado"
                                    type="button"
                                    @click="abrirModalDistribucionProducto(p.id)"
                                    class="inline-flex items-center gap-1 rounded-md border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-600 transition hover:bg-indigo-100 dark:border-indigo-800/60 dark:bg-indigo-950/50 dark:text-indigo-400 dark:hover:bg-indigo-900/40"
                                >
                                    <PencilSquareIcon class="h-3.5 w-3.5" />
                                    <span>Editar distribución</span>
                                </button>
                            </div>

                            <div v-if="distribucionPorProducto(p.id).length" class="mt-2.5 flex flex-wrap items-center gap-2">
                                <span
                                    v-for="(item, idx) in distribucionPorProducto(p.id)"
                                    :key="item.id ?? `tag-${p.id}-${idx}`"
                                    class="inline-flex items-center rounded-md border border-gray-200 bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                                >
                                    <span class="mr-1 font-bold text-indigo-600 dark:text-indigo-400">{{ item.talla }}:</span>
                                    <span>{{ item.cantidad_planificada }}</span>
                                </span>
                                <span class="ml-auto text-xs font-semibold text-gray-600 dark:text-gray-400">
                                    Total: {{ totalCantidadDistribucionProducto(p.id) }}
                                </span>
                            </div>
                            <p v-else class="mt-2 text-xs text-gray-400 italic">Sin distribución definida para este producto.</p>
                        </div>
                    </div>

                    <!-- Editor global si es 1 solo producto o si no hay ninguno -->
                    <div v-else>
                        <DistribucionTallasEditor
                            v-model:items="distribucion.items"
                            v-model:grupo-id="grupoTallas"
                            :grupos-tallas="props.losSelect?.grupos_tallas || []"
                            :can-edit="!planBloqueado"
                            :mostrar-recepcion="false"
                        />
                    </div>
                    <InputError class="mt-2" :message="form.errors.distribucion_tallas" />
                </div>

                <div class="my-8 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-x-5 gap-y-1 text-sm text-gray-600 dark:text-gray-400">
                        <span>
                            {{ esOpMaterial ? 'Materiales procesados:' : 'Total de productos:' }}
                            <b class="text-gray-900 dark:text-gray-100">
                                {{ esOpMaterial ? materialesSel.length : productosSel.length }}
                            </b>
                        </span>
                        <span>
                            Cantidad planificada:
                            <b class="text-gray-900 dark:text-gray-100">{{ cantidadPlanificada }}</b>
                        </span>
                        <span>
                            Costo fichas:
                            <b class="text-gray-900 dark:text-gray-100">{{ formatdinero(costoFichas) }}</b>
                        </span>
                        <span>
                            Costo del proceso:
                            <b class="text-gray-900 dark:text-gray-100">{{ formatdinero(costoProceso) }}</b>
                        </span>
                        <span>
                            Costo total:
                            <b class="text-gray-900 dark:text-gray-100">{{ formatdinero(costoTotal) }}</b>
                        </span>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <SecondaryButton :disabled="form.processing" @click="closeModal">
                            {{ lang().t('app.button.close') }}
                        </SecondaryButton>
                        <PrimaryButton
                            class="ml-3"
                            :class="{ 'opacity-25': form.processing || sinObjetivos }"
                            :disabled="form.processing || sinObjetivos"
                            type="submit"
                        >
                            {{ lang().t('app.button.save') }} {{ form.processing ? '...' : '' }}
                        </PrimaryButton>
                    </div>
                </div>
            </form>
        </Modal>

        <!-- Modal para editar la distribución específica de un producto -->
        <Modal :show="modalProductoDistribucion.show" @close="cerrarModalDistribucionProducto" :maxWidth="'lg'">
            <div class="p-6">
                <div class="flex items-center justify-between border-b pb-3 dark:border-gray-700">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Distribución por Tallas</h3>
                        <p v-if="productoEnEdicion" class="text-sm font-normal text-indigo-600 dark:text-indigo-400">
                            {{ productoEnEdicion.nombre }}
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="cerrarModalDistribucionProducto"
                        class="text-lg font-bold text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                    >
                        ✕
                    </button>
                </div>

                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Ajuste las cantidades de tallas exclusivamente para este producto.</p>

                <div class="mt-4">
                    <DistribucionTallasEditor
                        v-model:items="distribucionProductoEnEdicion"
                        :grupos-tallas="props.losSelect?.grupos_tallas || []"
                        :mostrar-recepcion="false"
                    />
                </div>

                <div class="mt-6 flex justify-end space-x-3 border-t pt-4 dark:border-gray-700">
                    <PrimaryButton type="button" @click="cerrarModalDistribucionProducto"> Aceptar </PrimaryButton>
                </div>
            </div>
        </Modal>
    </section>
</template>
