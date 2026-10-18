<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import InputLabel from '@/components/InputLabel.vue';
import Modal from '@/components/Modal.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import { PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/solid';
import { useForm } from '@inertiajs/vue3';
import { computed, reactive, ref, watch, watchEffect } from 'vue';
import vSelect from 'vue-select';
import 'vue-select/dist/vue-select.css';
import DistribucionTallasEditor from '@/pages/OrdenProduccion/DistribucionTallasEditor.vue';

const props = defineProps({
    show: Boolean,
    title: String,
    roles: Object,
    titulos: Object, //parametros de la clase principal
    losSelect: Object,
    numberPermissions: Number,
});
const emit = defineEmits(["close"]);

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

const opcionesProducto = computed(() => props.losSelect?.producto_referencia_id || []);

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
        const cantidad = Number(p.cantidad) || 0;
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
    return Array.from(mapa.values()).sort((a, b) => (b.necesita - b.hay) - (a.necesita - a.hay));
});

const productosSinFicha = computed(() =>
    productosSel.filter((p) => {
        const op: any = opcionesProducto.value.find((o: any) => o.id === p.id);
        return !op?.ficha;
    })
);

const hayFaltantes = computed(() => resumenMateriales.value.some((m) => m.necesita > m.hay));

const form = useForm({
    numero_op: '',
    cantidad_planificada: '',
    fecha_creacion: '',
    fecha_prevista_envio_taller: '',
    taller_id: '',
    user_id: '',
    id_shopify_producto: '',
    observaciones: '',
    distribucion_tallas: [] as Array<{ id?: number; talla: string; cantidad_planificada: number }>,
    grupo_tallas: null as number | null,
    productos: [] as number[],
    productos_cantidad: {} as Record<number, number>,
});

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
        for (const dp of distribucionPrevia) {
            const existe = distribucion.items.some(
                (i) => i.producto_id === productoId && i.talla.trim().toLowerCase() === String(dp.talla ?? '').trim().toLowerCase()
            );
            if (!existe) {
                distribucion.items.push({
                    id: dp.id,
                    grupo: dp.grupo ?? null,
                    talla: dp.talla ?? '',
                    cantidad_planificada: dp.cantidad_planificada ?? 1,
                    producto_id: productoId,
                    _origen: 'producto',
                });
            }
        }
        return;
    }

    for (const talla of opciones) {
        const existe = distribucion.items.some(
            (i) => i.producto_id === productoId && i.talla.trim().toLowerCase() === talla.trim().toLowerCase()
        );
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
            agregarTallasDeProducto(id, opciones, previo);
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
    emit("close");
    form.reset();
    distribucion.items = [];
    grupoTallas.value = null;
    modalProductoDistribucion.show = false;
    modalProductoDistribucion.productoId = null;
    productosSel.length = 0;
    selIds.value = [];
};

const create = () => {
    form.grupo_tallas = grupoTallas.value;
    form.productos = [...selIds.value];
    form.cantidad_planificada = String(totalTallas.value);
    form.distribucion_tallas = distribucion.items
        .filter((item) => (item.talla && item.talla.trim() !== '') || Number(item.cantidad_planificada) > 0)
        .map((item) => ({
            ...(item.id ? { id: item.id } : {}),
            talla: (item.talla ?? '').trim(),
            cantidad_planificada: Number(item.cantidad_planificada) || 0,
        }));
    form.post(route('OrdenProduccion.store'), {
        preserveScroll: true,
        onSuccess: () => {
            closeModal();
        },
        onError: () => null,
        onFinish: () => null,
    });
};

watchEffect(() => {
    if (props.show) {
        form.errors = {};
        if (!form.numero_op) {
            form.numero_op = `OP-${props.losSelect?.siguiente_numero_op ?? 100}`;
        }
        if (!form.fecha_creacion) {
            const d = new Date();
            d.setFullYear(d.getFullYear() - 1);
            form.fecha_creacion = d.toISOString().split('T')[0];
        }
    }
});

const fechaMaxima = computed(() => {
    const fecha = new Date();
    fecha.setDate(fecha.getDate() + 10);

    // Formato YYYY-MM-DD
    const yyyy = fecha.getFullYear();
    const mm = String(fecha.getMonth() + 1).padStart(2, '0');
    const dd = String(fecha.getDate()).padStart(2, '0');

    return `${yyyy}-${mm}-${dd}`;
});
</script>

<template>
    <section class="space-y-6">
        <Modal :show="props.show" @close="closeModal" :maxWidth="'xl6'">
            <form class="p-6" @submit.prevent="create">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-6">
                    {{ lang().t('app.label.add') }} {{ props.title }}
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <InputLabel for="numero_op" :value="lang().t('app.label.numero_op') || 'Número OP'" />
                        <TextInput id="numero_op" type="text"
                            class="mt-1 block w-full bg-gray-100 dark:bg-gray-800 cursor-not-allowed"
                            v-model="form.numero_op" readonly required placeholder="OP-100" />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Formato: OP-XXX (XXX es un número).</p>
                        <InputError class="mt-2" :message="form.errors.numero_op" />
                    </div>

                    <div>
                        <InputLabel for="fecha_creacion"
                            :value="lang().t('app.label.fecha_creacion') || 'Fecha Creación'" />
                        <!-- :max="fechaMaxima" -->
                        <TextInput id="fecha_creacion" type="date" class="mt-1 block w-full"
                            v-model="form.fecha_creacion" required />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Debe ser del {{ fechaMaxima }} o
                            anterior.</p>
                        <InputError class="mt-2" :message="form.errors.fecha_creacion" />
                    </div>

                    <div>
                        <InputLabel for="fecha_prevista_envio_taller"
                            :value="lang().t('app.label.fecha_prevista_envio_taller') || 'Fecha Prevista Envío Taller'" />
                        <TextInput id="fecha_prevista_envio_taller" type="date" class="mt-1 block w-full"
                            v-model="form.fecha_prevista_envio_taller" />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Opcional.</p>
                        <InputError class="mt-2" :message="form.errors.fecha_prevista_envio_taller" />
                    </div>

                    <div>
                        <InputLabel for="taller_id" :value="lang().t('app.label.taller') || 'Taller'" />
                        <v-select id="taller_id" class="mt-1 block w-full" :options="props.losSelect?.taller_id || []"
                            label="name" :reduce="(item: any) => item.id" v-model="form.taller_id"
                            placeholder="Seleccione un taller..." required />
                        <InputError class="mt-2" :message="form.errors.taller_id" />
                    </div>

                    <div>
                        <InputLabel for="user_id" :value="lang().t('app.label.responsable_id') || 'Responsable'" />
                        <v-select id="user_id" class="mt-1 block w-full"
                            :options="props.losSelect?.user_id || props.losSelect?.responsable_id || []" label="name"
                            :reduce="(item: any) => item.id" v-model="form.user_id" placeholder="Buscar responsable..."
                            required />
                        <InputError class="mt-2" :message="form.errors.user_id" />
                    </div>

                    <div>
                        <InputLabel for="id_shopify_producto"
                            :value="lang().t('app.label.id_shopify_producto') || 'ID Shopify Producto'" />
                        <TextInput id="id_shopify_producto" type="text" class="mt-1 block w-full"
                            v-model="form.id_shopify_producto" placeholder="ID Shopify (opcional)" />
                        <InputError class="mt-2" :message="form.errors.id_shopify_producto" />
                    </div>

                    <div class="md:col-span-2">
                        <InputLabel for="observaciones"
                            :value="lang().t('app.label.observaciones') || 'Observaciones'" />
                        <textarea id="observaciones" v-model="form.observaciones" rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 shadow-sm"
                            placeholder="Observaciones de la orden..."></textarea>
                        <InputError class="mt-2" :message="form.errors.observaciones" />
                    </div>
                </div>

                <!-- Productos de la orden -->
                <div
                    class="mt-6 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/40 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                            Productos de la orden
                            <span v-if="productosSel.length" class="font-normal text-gray-500 dark:text-gray-400">
                                ({{ productosSel.length }})
                            </span>
                        </h3>
                        <div class="text-right">
                            <span class="text-xs text-gray-500 dark:text-gray-400 block">Cantidad planificada (suma de
                                tallas):</span>
                            <span class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ totalTallas }}</span>
                        </div>
                    </div>

                    <v-select multiple class="block w-full" :options="opcionesProducto" label="name"
                        :reduce="(item: any) => item.id" v-model="selIds"
                        placeholder="Buscar y seleccionar productos..." />

                    <div v-if="productosSel.length" class="mt-3 overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr
                                    class="border-b border-gray-200 dark:border-gray-700 text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    <th class="py-2 pr-2 w-10"></th>
                                    <th class="py-2 pr-2">Producto</th>
                                    <th class="py-2 pr-2 w-28">Cantidad</th>
                                    <th class="py-2 pr-2">Distribución por Tallas</th>
                                    <th class="py-2 w-10"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="p in productosSel" :key="p.id"
                                    class="border-b border-gray-100 dark:border-gray-700/60 align-top">
                                    <td class="py-3 pr-2">
                                        <input type="checkbox"
                                            class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-primary dark:text-primary shadow-sm focus:ring-primary/80 dark:focus:ring-primary dark:focus:ring-offset-gray-800 dark:checked:bg-primary dark:checked:border-primary"
                                            :checked="selIds.includes(p.id)" @change="toggleProducto(p.id)" />
                                    </td>
                                    <td class="py-3 pr-2 font-medium text-gray-800 dark:text-gray-200">
                                        {{ p.nombre }}
                                    </td>
                                    <td class="py-3 pr-2">
                                        <span class="font-semibold text-gray-900 dark:text-gray-100"
                                            title="Suma de las cantidades de sus tallas">
                                            {{ totalCantidadDistribucionProducto(p.id) }}
                                        </span>
                                    </td>
                                    <td class="py-3 pr-2">
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <template v-if="distribucionPorProducto(p.id).length">
                                                <span v-for="(item, idx) in distribucionPorProducto(p.id)"
                                                    :key="item.id ?? `dp-${p.id}-${idx}`"
                                                    class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/60"
                                                    :title="`Talla ${item.talla}: ${item.cantidad_planificada} unidades`">
                                                    <span class="font-semibold mr-1">{{ item.talla }}:</span>
                                                    <span>{{ item.cantidad_planificada }}</span>
                                                </span>
                                                <span class="text-xs text-gray-400 dark:text-gray-500 font-medium ml-1">
                                                    (Total: {{ totalCantidadDistribucionProducto(p.id) }})
                                                </span>
                                            </template>
                                            <span v-else class="text-xs text-gray-400 dark:text-gray-500 italic">
                                                Sin tallas asignadas
                                            </span>

                                            <button type="button" @click="abrirModalDistribucionProducto(p.id)"
                                                class="ml-2 inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium text-indigo-600 dark:text-indigo-400 bg-white dark:bg-gray-800 border border-indigo-300 dark:border-indigo-700 rounded hover:bg-indigo-50 dark:hover:bg-indigo-950/60 transition shadow-xs"
                                                title="Editar distribución específica de este producto">
                                                <PencilSquareIcon class="w-3.5 h-3.5" />
                                                <span>Editar tallas</span>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="py-3">
                                        <button type="button" @click="quitarProducto(p.id)"
                                            class="p-1.5 text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-md transition"
                                            title="Quitar producto">
                                            <TrashIcon class="w-4 h-4" />
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
                    <div v-if="resumenMateriales.length"
                        class="mt-3 rounded-md border border-gray-200 dark:border-gray-700 p-3 bg-white dark:bg-gray-900/40">
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">
                            Materiales requeridos (ficha técnica × cantidad)
                        </h4>
                        <table class="mt-2 w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                                    <th class="py-1 pr-2">Material</th>
                                    <th class="py-1 pr-2 text-right">Necesita</th>
                                    <th class="py-1 pr-2 text-right">Hay</th>
                                    <th class="py-1 text-right">Faltante</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="m in resumenMateriales" :key="m.material_id" :class="m.necesita > m.hay
                                    ? 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400'
                                    : 'text-gray-700 dark:text-gray-300'">
                                    <td class="py-1 pr-2 px-1">{{ m.nombre }}</td>
                                    <td class="py-1 pr-2 px-1 text-right">{{ m.necesita.toLocaleString() }} {{ m.unidad
                                        }}</td>
                                    <td class="py-1 pr-2 px-1 text-right">{{ m.hay.toLocaleString() }}</td>
                                    <td class="py-1 px-1 text-right font-semibold">
                                        {{ m.necesita > m.hay ? 'Falta: ' + (m.necesita - m.hay).toLocaleString() : '—'
                                        }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <p v-if="hayFaltantes" class="mt-2 text-xs font-medium text-red-600 dark:text-red-400">
                            Inventario insuficiente: al registrar la orden se generará una notificación de faltantes.
                        </p>
                        <p v-if="productosSinFicha.length" class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                            Sin ficha técnica (no generan requerimiento):
                            {{productosSinFicha.map((p) => p.nombre).join(', ')}}
                        </p>
                    </div>

                    <InputError class="mt-2" :message="form.errors.productos" />
                    <InputError class="mt-1" :message="form.errors.cantidad_planificada" />
                </div>

                <!-- Distribución por tallas integrada -->
                <div
                    class="mt-6 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/40 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-1">
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                            Distribución por tallas de la orden
                        </h3>
                        <span v-if="productosSel.length > 1"
                            class="text-xs text-indigo-600 dark:text-indigo-400 font-medium">
                            {{ productosSel.length }} productos seleccionados
                        </span>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                        Tallas cargadas automáticamente de los productos seleccionados (o de su plantilla previa). Se
                        diferencian por
                        producto mediante etiquetas y puede editar cada producto individualmente en su modal.
                    </p>

                    <!-- Lista diferenciada por producto si hay más de 1 producto -->
                    <div v-if="productosSel.length > 1" class="space-y-4 mb-4">
                        <div v-for="p in productosSel" :key="`card-prod-${p.id}`"
                            class="rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900/60 p-3">
                            <div
                                class="flex items-center justify-between pb-2 border-b border-gray-100 dark:border-gray-800">
                                <div class="flex items-center gap-2">
                                    <span class="inline-block w-2.5 h-2.5 rounded-full bg-indigo-500"></span>
                                    <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ p.nombre
                                        }}</span>
                                    <span class="text-xs text-gray-400 dark:text-gray-500">(Cant. producto: {{
                                        totalCantidadDistribucionProducto(p.id) }})</span>
                                </div>
                                <button type="button" @click="abrirModalDistribucionProducto(p.id)"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/50 border border-indigo-200 dark:border-indigo-800/60 rounded-md hover:bg-indigo-100 dark:hover:bg-indigo-900/40 transition">
                                    <PencilSquareIcon class="w-3.5 h-3.5" />
                                    <span>Editar distribución</span>
                                </button>
                            </div>

                            <div v-if="distribucionPorProducto(p.id).length"
                                class="mt-2.5 flex flex-wrap gap-2 items-center">
                                <span v-for="(item, idx) in distribucionPorProducto(p.id)"
                                    :key="item.id ?? `tag-${p.id}-${idx}`"
                                    class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200 border border-gray-200 dark:border-gray-700">
                                    <span class="font-bold mr-1 text-indigo-600 dark:text-indigo-400">{{ item.talla
                                        }}:</span>
                                    <span>{{ item.cantidad_planificada }}</span>
                                </span>
                                <span class="text-xs font-semibold text-gray-600 dark:text-gray-400 ml-auto">
                                    Total: {{ totalCantidadDistribucionProducto(p.id) }}
                                </span>
                            </div>
                            <p v-else class="mt-2 text-xs text-gray-400 italic">
                                Sin distribución definida para este producto.
                            </p>
                        </div>
                    </div>

                    <!-- Editor global si es 1 solo producto o si no hay ninguno -->
                    <div v-else>
                        <DistribucionTallasEditor v-model:items="distribucion.items" v-model:grupo-id="grupoTallas"
                            :grupos-tallas="props.losSelect?.grupos_tallas || []" :mostrar-recepcion="false" />
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
                    </div>
                    <div class="flex items-center">
                        <SecondaryButton :disabled="form.processing" @click="closeModal">
                            {{ lang().t('app.button.close') }}
                        </SecondaryButton>
                        <PrimaryButton class="ml-3" :class="{ 'opacity-25': form.processing || sinProductos }"
                            :disabled="form.processing || sinProductos" type="submit">
                            {{ lang().t('app.button.add') }} {{ form.processing ? '...' : '' }}
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
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                            Distribución por Tallas
                        </h3>
                        <p v-if="productoEnEdicion" class="text-sm font-normal text-indigo-600 dark:text-indigo-400">
                            {{ productoEnEdicion.nombre }}
                        </p>
                    </div>
                    <button type="button" @click="cerrarModalDistribucionProducto"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-lg font-bold">
                        ✕
                    </button>
                </div>

                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Ajuste las cantidades de tallas exclusivamente para este producto.
                </p>

                <div class="mt-4">
                    <DistribucionTallasEditor v-model:items="distribucionProductoEnEdicion"
                        :grupos-tallas="props.losSelect?.grupos_tallas || []" :mostrar-recepcion="false" />
                </div>

                <div class="mt-6 flex justify-end space-x-3 border-t pt-4 dark:border-gray-700">
                    <PrimaryButton type="button" @click="cerrarModalDistribucionProducto">
                        Aceptar
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </section>
</template>
