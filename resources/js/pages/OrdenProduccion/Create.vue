<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import InputLabel from '@/components/InputLabel.vue';
import Modal from '@/components/Modal.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import DistribucionTallasEditor from '@/pages/OrdenProduccion/DistribucionTallasEditor.vue';
import { PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/solid';
import { useForm } from '@inertiajs/vue3';
import { computed, nextTick, reactive, ref, watch, watchEffect } from 'vue';
import vSelect from 'vue-select';
import 'vue-select/dist/vue-select.css';
import { formatdinero } from '@/global.ts';

const props = defineProps({
    show: Boolean,
    title: String,
    roles: Object,
    titulos: Object, //parametros de la clase principal
    losSelect: Object,
    numberPermissions: Number,
    borrador: Object,
});
const emit = defineEmits(['close']);

type ItemDistribucion = {
    id?: number;
    grupo?: number | null;
    talla: string;
    cantidad_planificada: number | string;
    producto_id?: number | null;
    _origen?: 'producto' | 'grupo' | 'manual';
};

const distribucion = reactive({
    items: [] as Array<ItemDistribucion>,
});

const grupoTallas = ref<number | null>(null);

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
        const asignadas = nuevasFilas.map((f) => ({
            ...f,
            producto_id: pid,
        }));
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

// Solo productos con ficha técnica: sin ficha no hay requerimiento de materiales ni proceso.
const opcionesProducto = computed(() => (props.losSelect?.producto_referencia_id || []).filter((p: any) => p.ficha));

const distribucionPorProducto = (productoId: number) => {
    return distribucion.items.filter((i) => i.producto_id === productoId);
};

const totalCantidadDistribucionProducto = (productoId: number) => {
    return distribucionPorProducto(productoId).reduce((acc, item) => acc + (Number(item.cantidad_planificada) || 0), 0);
};

const resumenMateriales = computed(() => {
    if (productosSel.length === 0) {
        return [] as Array<{ material_id: number; nombre: string; necesita: number; hay: number; unidad: string }>;
    }
    const mapa = new Map<number, { material_id: number; nombre: string; necesita: number; hay: number; unidad: string }>();
    productosSel.forEach((p) => {
        const op: any = opcionesProducto.value.find((o: any) => o.id === p.id);
        const cantidad = totalCantidadDistribucionProducto(p.id);
        (op?.ficha_materiales || []).forEach((m: any) => {
            const actual = mapa.get(m.material_id) ?? {
                material_id: m.material_id,
                nombre: m.nombre,
                necesita: 0,
                hay: Number(m.existencia_actual) || 0,
                unidad: m.unidad_medida || '',
            };
            actual.necesita += (Number(m.consumo_estandar) || 0) * cantidad;
            mapa.set(m.material_id, actual);
        });
    });
    return Array.from(mapa.values()).sort((a, b) => b.necesita - b.hay - (a.necesita - a.hay));
});

// Costo de los materiales por las cantidades distribuidas de cada producto.
// El precio es el que usa el parámetro costos_promedio (promedio o último);
// sin él, se conserva el costo teórico digitado en la ficha.
const precioMaterial = (m: any) =>
    m.precio_unitario !== undefined && m.precio_unitario !== null
        ? Number(m.precio_unitario) || 0
        : Number(m.costo_asociado_teorico) || 0;

const costoFichas = computed(() =>
    productosSel.reduce((total, p) => {
        const op: any = opcionesProducto.value.find((o: any) => o.id === p.id);
        const cantidad = totalCantidadDistribucionProducto(p.id);
        const costoUnitario = (op?.ficha_materiales || []).reduce(
            (suma: number, m: any) => suma + (Number(m.consumo_estandar) || 0) * precioMaterial(m),
            0,
        );
        return total + costoUnitario * cantidad;
    }, 0),
);

const productosSinFicha = computed(() =>
    productosSel.filter((p) => {
        const op: any = opcionesProducto.value.find((o: any) => o.id === p.id);
        return !op?.ficha;
    }),
);

const hayFaltantes = computed(() => resumenMateriales.value.some((m) => m.necesita > m.hay));

const form = useForm({
    id: null as number | null,
    numero_op: '',
    cantidad_planificada: '',
    fecha_prevista_envio_taller: '',
    taller_id: '',
    tipo: 'taller',
    empresa_externa_id: '',
    user_id: '',
    id_shopify_producto: '',
    observaciones: '',
    distribucion_tallas: [] as Array<{ id?: number; producto_id?: number | null; talla: string; cantidad_planificada: number }>,
    grupo_tallas: null as number | null,
    productos: [] as number[],
    productos_cantidad: {} as Record<number, number>,
});

// Modo "continuar borrador": form.id guarda el id de la OP en Borrador a actualizar.
const prefillBorrador = ref(false);

// Al cambiar el tipo se limpia el campo que deja de aplicar
watch(
    () => form.tipo,
    (nuevoTipo) => {
        if (nuevoTipo === 'externa') {
            form.taller_id = '';
        } else {
            form.empresa_externa_id = '';
        }
    },
);

const totalTallas = computed(() => {
    return distribucion.items.reduce((acc, i) => acc + (Number(i.cantidad_planificada) || 0), 0);
});

const sinProductos = computed(() => productosSel.length === 0);

const parsearOpciones = (opciones: unknown): string[] => {
    const partes = String(opciones ?? '')
        .split(/[,-]/)
        .map((s) => s.trim())
        .filter(Boolean);
    return partes.length ? partes : ['Única'];
};

const agregarTallasDeProducto = (productoId: number, opciones: string[], distribucionPrevia: any[] = []) => {
    if (distribucionPrevia && distribucionPrevia.length > 0) {
        // La distribución guardada en el producto suele ser solo la plantilla
        // de tallas (creada con cantidad 0). Si todas vienen en cero, se usa 1
        // por talla para que la OP nazca con una cantidad planificada real y
        // los materiales de la ficha no se calculen sobre cero.
        const totalPrevio = distribucionPrevia.reduce((acc, dp) => acc + (Number(dp.cantidad_planificada) || 0), 0);

        for (const dp of distribucionPrevia) {
            const existe = distribucion.items.some(
                (i) =>
                    i.producto_id === productoId &&
                    i.talla.trim().toLowerCase() ===
                        String(dp.talla ?? '')
                            .trim()
                            .toLowerCase(),
            );
            if (!existe) {
                const cantidadPrevia = Number(dp.cantidad_planificada) || 0;
                distribucion.items.push({
                    id: dp.id,
                    grupo: dp.grupo ?? null,
                    talla: dp.talla ?? '',
                    cantidad_planificada: totalPrevio > 0 ? cantidadPrevia : 1,
                    producto_id: productoId,
                    _origen: 'producto',
                });
            }
        }
        return;
    }

    for (const talla of opciones) {
        const existe = distribucion.items.some((i) => i.producto_id === productoId && i.talla.trim().toLowerCase() === talla.trim().toLowerCase());
        if (!existe) {
            distribucion.items.push({
                talla,
                cantidad_planificada: '1',
                producto_id: productoId,
                _origen: 'producto',
            });
        }
    }
};

const quitarTallasDeProducto = (productoId: number) => {
    distribucion.items = distribucion.items.filter((i) => i.producto_id !== productoId);
};

const sincronizarProductos = (ids: number[]) => {
    for (const id of ids) {
        if (!productosSel.some((p) => p.id === id)) {
            const op = opcionesProducto.value.find((p: any) => p.id === id);
            if (!op) continue;
            const opciones = parsearOpciones(op.Opciones);
            const previo = op.distribuciones_por_tallas || [];
            productosSel.push({ id, nombre: op.name, opciones, cantidad: '1' });
            // Al continuar un borrador, las tallas se restauran del borrador, no de la plantilla del producto
            if (!prefillBorrador.value) {
                agregarTallasDeProducto(id, opciones, previo);
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

const toggleProducto = (id: number) => {
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
    form.id = null;
    distribucion.items = [];
    grupoTallas.value = null;
    modalProductoDistribucion.show = false;
    modalProductoDistribucion.productoId = null;
    productosSel.length = 0;
    selIds.value = [];
};

const prepararPayload = () => {
    form.grupo_tallas = grupoTallas.value;
    form.productos = [...selIds.value];
    // La cantidad de cada producto sale de su distribución de tallas; el
    // backend la usa para generar el kit de proceso material por material.
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

const create = () => {
    prepararPayload();
    const onSuccess = () => closeModal();
    const onError = () => null;
    // Si venimos de un borrador ("Terminar después"), se actualiza en vez de crear;
    // el backend mueve la OP de Borrador a Preparación.
    if (form.id) {
        form.put(route('OrdenProduccion.update', form.id), { preserveScroll: true, onSuccess, onError });
    } else {
        form.post(route('OrdenProduccion.store'), { preserveScroll: true, onSuccess, onError });
    }
};

// Guarda la orden con los datos actuales como Borrador y la deja recuperable.
const terminarDespues = () => {
    prepararPayload();
    form.post(route('OrdenProduccion.borrador'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => null,
    });
};

watchEffect(() => {
    if (props.show) {
        form.errors = {};
        const b: any = props.borrador;
        if (b && b.id) {
            // Modo continuar: restaurar el borrador
            form.id = b.id;
            form.numero_op = b.numero_op ?? '';
            form.cantidad_planificada = b.cantidad_planificada != null ? String(b.cantidad_planificada) : '';
            form.fecha_prevista_envio_taller = String(b.fecha_prevista_envio_taller ?? '').substring(0, 10);
            form.taller_id = b.taller_id ?? '';
            form.tipo = b.tipo === 'externa' ? 'externa' : 'taller';
            form.empresa_externa_id = b.empresa_externa_id ?? '';
            form.user_id = b.user_id ?? '';
            form.id_shopify_producto = b.id_shopify_producto ?? '';
            form.observaciones = b.observaciones ?? '';

            prefillBorrador.value = true;
            const tallas = (b.distribuciones_por_tallas || []) as any[];
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
            selIds.value = ((b.productos || []) as any[]).map((p) => p.id);
            nextTick(() => {
                prefillBorrador.value = false;
            });
        } else {
            form.id = null;
            if (!form.numero_op) {
                form.numero_op = `OP-${props.losSelect?.siguiente_numero_op ?? 100}`;
            }
        }
    }
});

const aleatorioDe = (lista: any[]): any => {
    const opciones = (lista || []).filter((o: any) => o && o.id !== undefined && o.id !== null && o.id !== '');
    return opciones.length ? opciones[Math.floor(Math.random() * opciones.length)].id : '';
};

// Solo superadmin (numberPermissions > 99): el formulario llega con los
// selects resueltos al azar para poder probar sin diligenciarlo a mano.
const aplicarSeleccionAleatoria = () => {
    const seleccion: any = props.losSelect || {};
    const empresas = seleccion.empresa_externa_id || [];
    const usaExterna = empresas.length > 0 && Math.random() < 0.5;

    form.tipo = usaExterna ? 'externa' : 'taller';
    form.taller_id = usaExterna ? '' : aleatorioDe(seleccion.taller_id || []);
    form.empresa_externa_id = usaExterna ? aleatorioDe(empresas) : '';
    form.user_id = aleatorioDe(seleccion.user_id || seleccion.responsable_id || []);

    const productos = opcionesProducto.value || [];
    if (productos.length) {
        const producto = productos[Math.floor(Math.random() * productos.length)];
        selIds.value = [producto.id];
    }
};

watch(
    () => props.show,
    (visible) => {
        if (!visible) return;
        if ((props.borrador as any)?.id) return; // continuar un borrador no se altera
        if (Number(props.numberPermissions ?? 0) <= 99) return;
        aplicarSeleccionAleatoria();
    },
    { flush: 'post' },
);
</script>

<template>
    <section class="space-y-6">
        <Modal :show="props.show" @close="closeModal" :maxWidth="'xl6'">
            <form class="p-6" @submit.prevent="create">
                <div class="mb-6 flex items-center justify-between">
                    <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                        {{ form.id ? 'Continuar' : lang().t('app.label.add') }} {{ props.title }}
                    </h2>
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

                <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div>
                        <InputLabel for="numero_op" :value="lang().t('app.label.numero_op') || 'Número OP'" />
                        <TextInput
                            id="numero_op"
                            type="text"
                            class="mt-1 block w-full cursor-not-allowed bg-gray-100 dark:bg-gray-800"
                            v-model="form.numero_op"
                            readonly
                            required
                            placeholder="OP-100"
                        />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Formato: OP-XXX (XXX es un número).</p>
                        <InputError class="mt-2" :message="form.errors.numero_op" />
                    </div>

                    <div>
                        <InputLabel
                            for="fecha_prevista_envio_taller"
                            :value="lang().t('app.label.fecha_prevista_envio_taller') || 'Fecha Prevista Envío Taller'"
                        />
                        <TextInput
                            id="fecha_prevista_envio_taller"
                            type="date"
                            class="mt-1 block w-full"
                            v-model="form.fecha_prevista_envio_taller"
                        />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Opcional.</p>
                        <InputError class="mt-2" :message="form.errors.fecha_prevista_envio_taller" />
                    </div>

                    <div>
                        <InputLabel for="tipo" value="Tipo" />
                        <div class="mt-1 grid grid-cols-2 gap-2">
                            <button
                                type="button"
                                @click="form.tipo = 'taller'"
                                :class="[
                                    'h-10 rounded-md border text-sm font-medium transition focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none',
                                    form.tipo === 'taller'
                                        ? 'border-indigo-600 bg-indigo-600 text-white shadow-sm'
                                        : 'border-gray-300 bg-white text-gray-700 hover:border-gray-400 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-600',
                                ]"
                                :aria-pressed="form.tipo === 'taller'"
                            >
                                Taller
                            </button>
                            <button
                                type="button"
                                @click="form.tipo = 'externa'"
                                :class="[
                                    'h-10 rounded-md border text-sm font-medium transition focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none',
                                    form.tipo === 'externa'
                                        ? 'border-indigo-600 bg-indigo-600 text-white shadow-sm'
                                        : 'border-gray-300 bg-white text-gray-700 hover:border-gray-400 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-600',
                                ]"
                                :aria-pressed="form.tipo === 'externa'"
                            >
                                Externa
                            </button>
                        </div>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Si es externa, selecciona la empresa; si es de taller, selecciona el taller.
                        </p>
                        <InputError class="mt-2" :message="form.errors.tipo" />
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
                            placeholder="Seleccione un taller..."
                            required
                        />
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
                            placeholder="Seleccione una empresa externa..."
                        />
                        <InputError class="mt-2" :message="form.errors.empresa_externa_id" />
                    </div>

                    <div>
                        <InputLabel for="user_id" :value="lang().t('app.label.responsable_id') || 'Responsable'" />
                        <v-select
                            id="user_id"
                            class="mt-1 block w-full"
                            :options="props.losSelect?.user_id || props.losSelect?.responsable_id || []"
                            label="name"
                            :reduce="(item: any) => item.id"
                            v-model="form.user_id"
                            placeholder="Buscar responsable..."
                            required
                        />
                        <InputError class="mt-2" :message="form.errors.user_id" />
                    </div>

                    <div class="md:col-span-2">
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

                <!-- Productos de la orden -->
                <div class="mt-6 rounded-lg border border-gray-200 bg-gray-50/50 p-4 dark:border-gray-700 dark:bg-gray-800/40">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                            Productos de la orden
                            <span v-if="productosSel.length" class="font-normal text-gray-500 dark:text-gray-400"> ({{ productosSel.length }}) </span>
                        </h3>
                        <div class="text-right">
                            <span class="block text-xs text-gray-500 dark:text-gray-400">Cantidad planificada (suma de tallas):</span>
                            <span class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ totalTallas }}</span>
                        </div>
                    </div>

                    <v-select
                        multiple
                        class="block w-full"
                        :options="opcionesProducto"
                        label="name"
                        :reduce="(item: any) => item.id"
                        v-model="selIds"
                        placeholder="Buscar y seleccionar productos..."
                    />

                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
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
                                    <th class="w-10 py-2"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="p in productosSel" :key="p.id" class="border-b border-gray-100 align-top dark:border-gray-700/60">
                                    <td class="py-3 pr-2">
                                        <input
                                            type="checkbox"
                                            class="text-primary dark:text-primary focus:ring-primary/80 dark:focus:ring-primary dark:checked:bg-primary dark:checked:border-primary rounded border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:focus:ring-offset-gray-800"
                                            :checked="selIds.includes(p.id)"
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
                                    <td class="py-3">
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
                        No hay productos seleccionados. Puede dejar la tabla en blanco, pero
                        <b>no puede guardar la orden sin al menos un producto</b>.
                    </p>

                    <!-- Materiales requeridos según la ficha técnica -->
                    <div
                        v-if="resumenMateriales.length"
                        class="mt-3 rounded-md border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900/40"
                    >
                        <h4 class="text-xs font-semibold tracking-wide text-gray-600 uppercase dark:text-gray-300">
                            Materiales requeridos (consumo ficha × total de tallas)
                        </h4>
                        <table class="mt-2 w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs text-gray-500 uppercase dark:text-gray-400">
                                    <th class="py-1 pr-2">Material</th>
                                    <th class="py-1 pr-2 text-right">Necesita</th>
                                    <th class="py-1 pr-2 text-right">Hay</th>
                                    <th class="py-1 text-right">Faltante</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="m in resumenMateriales"
                                    :key="m.material_id"
                                    :class="
                                        m.necesita > m.hay
                                            ? 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400'
                                            : 'text-gray-700 dark:text-gray-300'
                                    "
                                >
                                    <td class="px-1 py-1 pr-2">{{ m.nombre }}</td>
                                    <td class="px-1 py-1 pr-2 text-right">{{ m.necesita.toLocaleString() }} {{ m.unidad }}</td>
                                    <td class="px-1 py-1 pr-2 text-right">{{ m.hay.toLocaleString() }}</td>
                                    <td class="px-1 py-1 text-right font-semibold">
                                        {{ m.necesita > m.hay ? 'Falta: ' + (m.necesita - m.hay).toLocaleString() : '—' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <p v-if="hayFaltantes" class="mt-2 text-xs font-medium text-red-600 dark:text-red-400">
                            Inventario insuficiente: al registrar la orden se generará una notificación de faltantes.
                        </p>
                        <p v-if="productosSinFicha.length" class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                            Sin ficha técnica (no generan requerimiento):
                            {{ productosSinFicha.map((p) => p.nombre).join(', ') }}
                        </p>
                    </div>

                    <InputError class="mt-2" :message="form.errors.productos" />
                    <InputError class="mt-1" :message="form.errors.cantidad_planificada" />
                </div>

                <!-- Distribución por tallas integrada -->
                <div class="mt-6 rounded-lg border border-gray-200 bg-gray-50/50 p-4 dark:border-gray-700 dark:bg-gray-800/40">
                    <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Distribución por tallas de la orden</h3>
                        <span v-if="productosSel.length > 1" class="text-xs font-medium text-indigo-600 dark:text-indigo-400">
                            {{ productosSel.length }} productos seleccionados
                        </span>
                    </div>
                    <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">
                        Tallas cargadas automáticamente de los productos seleccionados (o de su plantilla previa). Se diferencian por producto
                        mediante etiquetas y puede editar cada producto individualmente en su modal.
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
                            :mostrar-recepcion="false"
                        />
                    </div>
                    <InputError class="mt-2" :message="form.errors.distribucion_tallas" />
                </div>

                <div class="my-8 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-x-5 gap-y-1 text-sm text-gray-600 dark:text-gray-400">
                        <span>
                            Total de productos:
                            <b class="text-gray-900 dark:text-gray-100">{{ productosSel.length }}</b>
                        </span>
                        <span>
                            Cantidad planificada:
                            <b class="text-gray-900 dark:text-gray-100">{{ totalTallas }}</b>
                        </span>
                        <span>
                            Total de tallas:
                            <b class="text-gray-900 dark:text-gray-100">{{ totalTallas }}</b>
                        </span>
                        <span>
                            Costo fichas:
                            <b class="text-gray-900 dark:text-gray-100">{{ formatdinero(costoFichas) }}</b>
                        </span>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <SecondaryButton :disabled="form.processing" @click="closeModal">
                            {{ lang().t('app.button.close') }}
                        </SecondaryButton>
                        <button
                            type="button"
                            @click="terminarDespues"
                            :disabled="form.processing"
                            class="ml-3 inline-flex items-center rounded-md border border-amber-400 bg-amber-50 px-4 py-2 text-xs font-semibold tracking-widest text-amber-700 uppercase transition hover:bg-amber-100 focus:ring-2 focus:ring-amber-400 focus:ring-offset-2 focus:outline-none disabled:opacity-50 dark:border-amber-500/60 dark:bg-amber-500/10 dark:text-amber-300 dark:hover:bg-amber-500/20"
                        >
                            Terminar después
                        </button>
                        <PrimaryButton
                            class="ml-3"
                            :class="{ 'opacity-25': form.processing || sinProductos }"
                            :disabled="form.processing || sinProductos"
                            type="submit"
                        >
                            {{ form.id ? 'Guardar' : lang().t('app.button.add') }} {{ form.processing ? '...' : '' }}
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
