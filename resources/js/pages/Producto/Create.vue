<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import InputLabel from '@/components/InputLabel.vue';
import Modal from '@/components/Modal.vue';
import MoneyInput from '@/components/MoneyInput.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import EditorMateriales from '@/pages/FichaTecnicaMateriales/EditorMateriales.vue';
import EditorProcesos from '@/pages/Producto/EditorProcesos.vue';
import { MAX_TALLAS_POR_RANGO, TALLAS_ALFABETICAS, useTallasVariante } from '@/composables/useTallasVariante';
import { dineroANumero, formatdinero } from '@/global.ts';
import { CheckCircleIcon, ClipboardDocumentListIcon, Cog6ToothIcon, CubeIcon, DocumentTextIcon, ShoppingBagIcon, CurrencyDollarIcon } from '@heroicons/vue/24/solid';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch, watchEffect } from 'vue';
import vSelect from 'vue-select';
import 'vue-select/dist/vue-select.css';

const props = withDefaults(
    defineProps<{
        show: boolean;
        title?: string;
        roles?: any;
        titulos?: any[];
        losSelect?: any;
        numberPermissions?: number;
        productos?: any[];
        metodoCostos?: number;
        metodoCostosLabel?: string;
    }>(),
    {
        show: false,
        title: '',
        roles: () => ({}),
        titulos: () => [],
        losSelect: () => ({}),
        numberPermissions: 0,
        productos: () => [],
        metodoCostos: 1,
        metodoCostosLabel: '',
    }
);

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const camposSelect = [
    { campo: 'Categoria_original', subcategoria: 'categoria_original' },
    { campo: 'Familia_normalizada', subcategoria: 'familia_normalizada' },
    { campo: 'Control_de_variante', subcategoria: 'control_de_variante' },
] as const;

const optionsPara = (campo: string) => props.losSelect?.[campo] ?? [];
const faltanOpciones = computed(() => camposSelect.filter(({ campo }) => !optionsPara(campo).length));

// Coste de producción: los materiales de la ficha más los procesos, calculado
// con los dos métodos de precio del material (su último precio registrado y el
// promedio de todos). Los dos se guardan en la BD; el que manda es el que esté
// vigente en el parámetro costos_promedio.
const materialesOpciones = computed<any[]>(() => props.losSelect?.materiales ?? []);

// Una fila cuyo material no está en la lista conserva su costo teórico.
const precioMaterial = (fila: any, campo: 'costo_unitario' | 'costo_unitario_promedio'): number => {
    const material = materialesOpciones.value.find(
        (opcion: any) => String(opcion.id) === String(fila.material_componente_id),
    );
    return material ? Number(material[campo]) || 0 : dineroANumero(fila.costo_asociado_teorico);
};

const costeCon = (campo: 'costo_unitario' | 'costo_unitario_promedio'): number => {
    const materiales = (form.materiales ?? []).reduce(
        (suma, fila: any) => suma + dineroANumero(fila.consumo_estandar) * precioMaterial(fila, campo),
        0,
    );
    const procesos = (form.procesos ?? []).reduce(
        (suma, fila: any) => suma + dineroANumero(fila.coste_proceso),
        0,
    );
    return Math.round((materiales + procesos) * 100) / 100;
};

const costeUltimo = computed(() => costeCon('costo_unitario'));
const costePromedio = computed(() => costeCon('costo_unitario_promedio'));

// 2 = último precio digitado del material; 1 = promedio de sus cambios de precio.
const usaUltimoPrecio = computed(() => Number(props.metodoCostos) === 2);
const costeProduccion = computed(() => (usaUltimoPrecio.value ? costeUltimo.value : costePromedio.value));

// El precio de venta automático: 300% del coste, redondeado al millar superior.
const precioVentaAutomatico = computed(() => Math.ceil((costeProduccion.value * 3) / 1000) * 1000);

const precioVentaDisplay = computed(() => form.precio_venta_producto ? Number(form.precio_venta_producto) : precioVentaAutomatico.value);
const esPrecioManual = computed(() => !!form.precio_venta_producto);

// Ganancia: diferencia entre el precio venta automático y el coste de
// producción, en dinero y como porcentaje del coste.
const ganancia = computed(() => {
    const precioBase = form.precio_venta_producto ? Number(form.precio_venta_producto) : precioVentaAutomatico.value;
    const diferencia = precioBase - costeProduccion.value;
    return {
        valor: diferencia,
        porcentaje: costeProduccion.value > 0
            ? (diferencia / costeProduccion.value) * 100
            : null,
    };
});

// Convertir precio a formato X.990 (ej: 50000 → 49990, 121000 → 120990)
const aproximarPrecio990 = () => {
    const base = precioVentaAutomatico.value;
    const miles = Math.floor(base / 1000);
    if (miles > 0) {
        form.precio_venta_producto = (miles - 1) * 1000 + 990;
    }
};

const form = useForm({
    Codigo_interno: '',
    ID_producto_Shopify: '',
    Categoria_original: '',
    Familia_normalizada: '',
    Titulo_Shopify: '',
    Control_de_variante: '',
    Opciones: '',
    tallas: [] as string[],
    Estado: 1,
    Observaciones: '',
    descripcion: '',
    instrucciones: '',
    costo_anticipado: '' as string | number,
    materiales: [] as any[],
    procesos: [] as any[],
    precio_venta_producto: '' as string | number,
});

// El código se asigna solo: PT-001 con el último número registrado + 1.
// Se recuerda el último creado en esta sesión porque la lista no se recarga
// hasta que el usuario actualice el Index.
const ultimoCodigoCreado = ref('');

const codigoAutomatico = computed(() => {
    let maximo = 0;
    const considerar = (codigo: unknown) => {
        const coincidencia = /^PT-(\d+)$/i.exec(String(codigo ?? '').trim());
        const numero = coincidencia ? Number(coincidencia[1]) : 0;
        if (numero > maximo) maximo = numero;
    };
    (props.productos ?? []).forEach((producto: any) => considerar(producto?.Codigo_interno));
    considerar(ultimoCodigoCreado.value);
    return `PT-${String(maximo + 1).padStart(3, '0')}`;
});

const { modo, rango, tallasEscogidas, tallas, alternarTalla, rangoDemasiadoGrande, opcionesTexto, limpiar } =
    useTallasVariante(() => form.Control_de_variante);

// Las tallas y el resumen de opciones siempre derivan del control de variante.
watch([modo, tallas], () => {
    form.tallas = tallas.value;
    form.Opciones = opcionesTexto.value;
}, { immediate: true });

const toggleEstado = () => {
    form.Estado = form.Estado == 1 ? 0 : 1;
};

const create = () => {
    if (faltanOpciones.value.length) {
        faltanOpciones.value.forEach(({ campo, subcategoria }) => {
            form.setError(campo, `Falta configurar ${subcategoria} en la lista desplegable (categoría: Producto).`);
        });
        return;
    }
    if (modo.value === 'numerica' && !tallas.value.length) {
        form.setError('Opciones', rangoDemasiadoGrande.value
            ? `El rango debe generar como máximo ${MAX_TALLAS_POR_RANGO} tallas.`
            : 'Indique un rango válido (desde ≤ hasta).');
        return;
    }
    if (modo.value === 'alfabetica' && !tallas.value.length) {
        form.setError('Opciones', 'Seleccione al menos una talla.');
        return;
    }
    // El costo anticipado solo acepta dígitos.
    form.costo_anticipado = String(form.costo_anticipado ?? '').replace(/\D/g, '');
    form
        .transform((datos) => ({
            ...datos,
            costo_anticipado: String(datos.costo_anticipado ?? '').replace(/\D/g, ''),
            precio_venta_producto: datos.precio_venta_producto ? Number(datos.precio_venta_producto) : precioVentaAutomatico.value,
            materiales: datos.materiales
                .filter((fila: any) => fila.material_componente_id)
                .map((fila: any) => ({
                    ...fila,
                    consumo_estandar: dineroANumero(fila.consumo_estandar),
                    costo_asociado_teorico: dineroANumero(fila.costo_asociado_teorico),
                })),
            procesos: datos.procesos
                .filter((fila: any) => String(fila.nombre ?? '').trim() !== '')
                .map((fila: any) => ({
                    nombre: String(fila.nombre).trim(),
                    coste_proceso: dineroANumero(fila.coste_proceso),
                })),
        }))
        .post(route('Producto.store'), {
            preserveScroll: true,
            onSuccess: () => {
                ultimoCodigoCreado.value = form.Codigo_interno;
                emit('close');
                form.reset();
            },
            onError: () => null,
            onFinish: () => null,
        });
};

watchEffect(() => {
    if (props.show) {
        form.errors = {}
        form.reset()
        limpiar();
        if ((props.numberPermissions ?? 0) > 99) {
            const valueRAn = Math.floor(Math.random() * 9 + 1)
            form.ID_producto_Shopify = 'SHP-' + valueRAn
            form.Categoria_original = 'Bolso manos libres'
            form.Familia_normalizada = 'Bolso'
            form.Titulo_Shopify = 'Producto Genérico ' + valueRAn
            form.Control_de_variante = 'Talla numerica'
            rango.desde = '21';
            rango.hasta = '40';
            form.Estado = 1
            form.Observaciones = 'Observación genérica ' + valueRAn
            form.descripcion = 'Descripción genérica ' + valueRAn
            form.costo_anticipado = valueRAn * 10000
        }
    }
});

// Se asigna después del reset del efecto anterior, solo al abrir el modal.
// Así, si el listado se recarga con el modal abierto, no se borra lo digitado.
watch(
    () => props.show,
    (visible) => {
        if (visible) form.Codigo_interno = codigoAutomatico.value;
    },
    { immediate: true, flush: 'post' },
);
</script>

<template>
    <section class="space-y-6">
        <Modal :show="props.show" @close="emit('close')" :maxWidth="'xl7'">
            <form class="p-0" @submit.prevent="create">
                <!-- Encabezado -->
                <div
                    class="px-6 pt-6 pb-4 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-indigo-50 via-white to-violet-50 dark:from-gray-800 dark:via-gray-800 dark:to-gray-800 rounded-t-lg">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                        <span
                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-600 text-white">
                            <ShoppingBagIcon class="w-5 h-5" />
                        </span>
                        {{ lang().t('app.label.add') }} {{ props.title }}
                    </h2>
                    <p class="mt-1.5 text-sm text-gray-500 dark:text-gray-400 pl-10">
                        Complete los datos del nuevo producto.
                    </p>
                </div>

                <div class="px-6 py-5 space-y-7 max-h-[70vh] overflow-y-auto scrollbar-table">
                    <!-- Información principal -->
                    <div>
                        <h3
                            class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 flex items-center gap-1.5 mb-4">
                            <ClipboardDocumentListIcon class="w-4 h-4 text-gray-400 dark:text-gray-500" />
                            Información principal
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-x-6 gap-y-4">
                            <div>
                                <InputLabel for="Codigo_interno"
                                    :value="lang().t('app.label.Codigo_interno') || 'Código interno'" />
                                <input id="Codigo_interno" type="text" readonly aria-readonly="true"
                                    :value="form.Codigo_interno"
                                    class="mt-1 block w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-semibold tabular-nums text-gray-700 shadow-sm focus:outline-none dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-200" />
                                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                    Se asigna automáticamente: último código PT registrado + 1.
                                </p>
                                <InputError class="mt-2" :message="form.errors.Codigo_interno" />
                            </div>

                            <div class="md:col-span-2">
                                <InputLabel for="Titulo_Shopify"
                                    :value="lang().t('app.label.Titulo_Shopify') || 'Título Shopify / Nombre'" />
                                <TextInput id="Titulo_Shopify" type="text" class="mt-1 block w-full"
                                    v-model="form.Titulo_Shopify" required placeholder="Título Shopify"
                                    :error="form.errors.Titulo_Shopify" />
                                <InputError class="mt-2" :message="form.errors.Titulo_Shopify" />
                            </div>

                            <div>
                                <InputLabel for="Familia_normalizada"
                                    :value="lang().t('app.label.Familia_normalizada') || 'Familia normalizada'" />
                                <v-select v-if="optionsPara('Familia_normalizada').length" id="Familia_normalizada"
                                    :options="optionsPara('Familia_normalizada')" :reduce="(item: any) => item.nombre"
                                    label="nombre" v-model="form.Familia_normalizada" placeholder="Seleccione familia"
                                    append-to-body class="mt-1 block w-full" />
                                <p v-else
                                    class="mt-1 rounded-md border border-amber-300 bg-amber-50 p-2 text-xs font-medium text-amber-800 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-200">
                                    Falta configurar familia_normalizada en la lista desplegable (categoría: Producto).
                                </p>
                                <InputError class="mt-2" :message="form.errors.Familia_normalizada" />
                            </div>

                            <div class="md:col-span-2">
                                <InputLabel for="descripcion"
                                    :value="lang().t('app.label.descripcion') || 'Descripción'" />
                                <textarea id="descripcion" rows="2"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 shadow-sm text-sm"
                                    v-model="form.descripcion"
                                    placeholder="Descripción del producto (opcional)"></textarea>
                                <InputError class="mt-2" :message="form.errors.descripcion" />
                            </div>

                            <div class="md:col-span-2 xl:col-span-3">
                                <InputLabel for="instrucciones" value="Instrucciones de ensamble" />
                                <textarea id="instrucciones" rows="2"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 shadow-sm text-sm"
                                    v-model="form.instrucciones"
                                    placeholder="Instrucciones de ensamble (opcional)"></textarea>
                                <InputError class="mt-2" :message="form.errors.instrucciones" />
                            </div>

                            <div>
                                <InputLabel for="Categoria_original"
                                    :value="lang().t('app.label.Categoria_original') || 'Categoría original'" />
                                <v-select v-if="optionsPara('Categoria_original').length" id="Categoria_original"
                                    :options="optionsPara('Categoria_original')" :reduce="(item: any) => item.nombre"
                                    label="nombre" v-model="form.Categoria_original" placeholder="Seleccione categoría"
                                    class="mt-1 block w-full" />
                                <p v-else
                                    class="mt-1 rounded-md border border-amber-300 bg-amber-50 p-2 text-xs font-medium text-amber-800 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-200">
                                    Falta configurar categoria_original en la lista desplegable (categoría: Producto).
                                </p>
                                <InputError class="mt-2" :message="form.errors.Categoria_original" />
                            </div>

                            <div>
                                <InputLabel for="Control_de_variante"
                                    :value="lang().t('app.label.Control_de_variante') || 'Control de variante'" />
                                <v-select v-if="optionsPara('Control_de_variante').length" id="Control_de_variante"
                                    :options="optionsPara('Control_de_variante')" :reduce="(item: any) => item.nombre"
                                    label="nombre" v-model="form.Control_de_variante"
                                    placeholder="Seleccione control de variante" class="mt-1 block w-full" />
                                <p v-else
                                    class="mt-1 rounded-md border border-amber-300 bg-amber-50 p-2 text-xs font-medium text-amber-800 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-200">
                                    Falta configurar control_de_variante en la lista desplegable (categoría: Producto).
                                </p>
                                <InputError class="mt-2" :message="form.errors.Control_de_variante" />
                            </div>

                            <div>
                                <InputLabel for="Opciones" :value="lang().t('app.label.Opciones') || 'Opciones'" />

                                <!-- Sin talla: las opciones no aplican -->
                                <p v-if="modo === 'sin-talla'"
                                    class="mt-1 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-500 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400">
                                    «{{ form.Control_de_variante }}» no usa opciones: se guardará sin tallas.
                                </p>

                                <!-- Talla numérica: rango desde/hasta -->
                                <div v-else-if="modo === 'numerica'">
                                    <div class="mt-1 flex items-end gap-3">
                                        <div class="flex-1">
                                            <InputLabel for="talla_desde" value="Desde" class="!text-xs" />
                                            <TextInput id="talla_desde" type="number" min="0" step="1"
                                                class="mt-1 block w-full" v-model="rango.desde" placeholder="21" />
                                        </div>
                                        <span class="pb-2.5 text-gray-400 dark:text-gray-500">—</span>
                                        <div class="flex-1">
                                            <InputLabel for="talla_hasta" value="Hasta" class="!text-xs" />
                                            <TextInput id="talla_hasta" type="number" min="0" step="1"
                                                class="mt-1 block w-full" v-model="rango.hasta" placeholder="40" />
                                        </div>
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        <span v-if="tallas.length">
                                            Se crearán {{ tallas.length }} distribuciones:
                                            {{ tallas.slice(0, 6).join(', ') }}{{ tallas.length > 6 ? ', …' : '' }}
                                        </span>
                                        <span v-else-if="rangoDemasiadoGrande"
                                            class="text-amber-600 dark:text-amber-400">
                                            El rango debe generar como máximo {{ MAX_TALLAS_POR_RANGO }} tallas.
                                        </span>
                                        <span v-else>
                                            Ingrese el rango de tallas, por ejemplo 21 a 40.
                                        </span>
                                    </p>
                                </div>

                                <!-- Talla alfabética: elección individual -->
                                <div v-else-if="modo === 'alfabetica'" class="mt-1 flex flex-wrap gap-2">
                                    <button v-for="talla in TALLAS_ALFABETICAS" :key="talla" type="button"
                                        @click="alternarTalla(talla)" :aria-pressed="tallasEscogidas.includes(talla)"
                                        class="h-9 min-w-[3rem] rounded-lg border px-3 text-sm font-medium transition-colors"
                                        :class="tallasEscogidas.includes(talla)
                                            ? 'border-indigo-600 bg-indigo-600 text-white'
                                            : 'border-gray-300 bg-white text-gray-700 hover:border-indigo-400 dark:border-gray-600 dark:bg-gray-900/40 dark:text-gray-300'">
                                        {{ talla }}
                                    </button>
                                    <p class="w-full text-xs text-gray-500 dark:text-gray-400">
                                        {{ tallas.length ? `Seleccionadas: ${tallas.join(', ')}` : 'Elija una o más tallas.' }}
                                    </p>
                                </div>

                                <p v-else
                                    class="mt-1 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-500 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400">
                                    Seleccione primero el control de variante.
                                </p>

                                <p v-if="modo === 'numerica' || modo === 'alfabetica'"
                                    class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                    Las cantidades planificadas de cada talla se ajustan luego desde el botón «Tallas»
                                    del listado.
                                </p>

                                <InputError class="mt-2" :message="form.errors.Opciones" />
                            </div>
                        </div>
                    </div>

                    <!-- Ficha técnica de materiales -->
                    <div>
                        <h3
                            class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                            <CubeIcon class="w-4 h-4 text-gray-400 dark:text-gray-500" />
                            Ficha técnica de materiales
                        </h3>
                        <p class="mt-1 mb-3 pl-6 text-xs text-gray-400 dark:text-gray-500">
                            Opcional. Solo se admiten materiales genéricos; se guardan en la ficha técnica de este
                            producto.
                        </p>
                        <EditorMateriales v-model="form.materiales" :opciones="props.losSelect" :errors="form.errors" />
                        <InputError class="mt-2" :message="form.errors.materiales" />
                        <InputError class="mt-1" :message="form.errors.productos" />
                    </div>

                    <!-- Procesos -->
                    <div>
                        <h3
                            class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                            <Cog6ToothIcon class="w-4 h-4 text-gray-400 dark:text-gray-500" />
                            Procesos
                        </h3>
                        <p class="mt-1 mb-3 pl-6 text-xs text-gray-400 dark:text-gray-500">
                            Opcional. El total de estos costes por unidad forma el costo de proceso de la OP.
                        </p>
                        <EditorProcesos v-model="form.procesos" :opciones="props.losSelect?.procesos ?? []" :errors="form.errors" />
                        <InputError class="mt-2" :message="form.errors.procesos" />
                    </div>

                    <!-- Estado -->
                    <div>
                        <h3
                            class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 flex items-center gap-1.5 mb-3">
                            <CheckCircleIcon class="w-4 h-4 text-gray-400 dark:text-gray-500" />
                            Estado
                        </h3>
                        <div role="switch" tabindex="0" :aria-checked="form.Estado == 1" @click="toggleEstado"
                            @keydown.space.prevent="toggleEstado"
                            class="flex items-center justify-between gap-4 px-4 py-3 rounded-xl border cursor-pointer transition-colors"
                            :class="form.Estado == 1
                                ? 'border-emerald-300 dark:border-emerald-700 bg-emerald-50/70 dark:bg-emerald-900/20'
                                : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900/40 hover:border-gray-300 dark:hover:border-gray-600'">
                            <div>
                                <span class="block text-sm font-medium text-gray-800 dark:text-gray-200">Estado</span>
                                <span class="block text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                    {{ form.Estado == 1 ? 'Sí — activo (se guarda como 1)' : 'No — inactivo (se guarda como 0)' }}
                                </span>
                            </div>
                            <span
                                class="relative inline-flex h-7 w-12 shrink-0 items-center rounded-full transition-colors duration-200"
                                :class="form.Estado == 1 ? 'bg-emerald-600' : 'bg-gray-300 dark:bg-gray-600'">
                                <span
                                    class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform duration-200"
                                    :class="form.Estado == 1 ? 'translate-x-6' : 'translate-x-1'" />
                            </span>
                        </div>
                    </div>

                    <!-- Campos opcionales -->
                    <div>
                        <h3
                            class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 flex items-center gap-1.5 mb-1">
                            <DocumentTextIcon class="w-4 h-4 text-gray-400 dark:text-gray-500" />
                            Campos opcionales
                        </h3>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mb-3 pl-6">
                            Los siguientes campos son opcionales: puede dejarlos vacíos.
                        </p>
                        <div
                            class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-4 gap-x-6 gap-y-4 rounded-xl border border-dashed border-gray-300 dark:border-gray-600 p-4">
                            <div>
                                <InputLabel for="ID_producto_Shopify"
                                    :value="lang().t('app.label.ID_producto_Shopify') || 'ID Shopify'" />
                                <TextInput id="ID_producto_Shopify" type="text" class="mt-1 block w-full"
                                    v-model="form.ID_producto_Shopify" placeholder="ID Producto Shopify (opcional)"
                                    :error="form.errors.ID_producto_Shopify" />
                                <InputError class="mt-2" :message="form.errors.ID_producto_Shopify" />
                            </div>

                            <!-- <div>
                                <InputLabel for="costo_anticipado"
                                    :value="lang().t('app.label.costo_anticipado') || 'Costo anticipado'" />
                                <MoneyInput id="costo_anticipado" class="mt-1 block w-full"
                                    v-model="form.costo_anticipado" placeholder="$0"
                                    :error="form.errors.costo_anticipado" />
                                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Solo números, sin decimales.
                                </p>
                                <InputError class="mt-2" :message="form.errors.costo_anticipado" />
                            </div> -->

                            <div>
                                <InputLabel for="coste_produccion_promedio" value="Coste producción · promedio" />
                                <div id="coste_produccion_promedio"
                                    class="mt-1 flex h-10 w-full items-center justify-between gap-2 rounded-md border border-gray-200 bg-gray-50 px-3 text-sm font-semibold text-gray-700 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-200">
                                    <span>{{ formatdinero(costePromedio) }}</span>
                                    <span :title="props.metodoCostosLabel"
                                        :class="usaUltimoPrecio
                                            ? 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300'
                                            : 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200'"
                                        class="rounded-full px-2 py-0.5 text-[10px] font-medium">
                                        {{ usaUltimoPrecio ? 'Referencia' : 'Vigente' }}
                                    </span>
                                </div>
                                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                    Materiales de la ficha al promedio de sus precios + procesos, por unidad.
                                </p>
                            </div>

                            <div>
                                <InputLabel for="coste_produccion_ultimo" value="Coste producción · último precio" />
                                <div id="coste_produccion_ultimo"
                                    class="mt-1 flex h-10 w-full items-center justify-between gap-2 rounded-md border border-gray-200 bg-gray-50 px-3 text-sm font-semibold text-gray-700 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-200">
                                    <span>{{ formatdinero(costeUltimo) }}</span>
                                    <span :title="props.metodoCostosLabel"
                                        :class="usaUltimoPrecio
                                            ? 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200'
                                            : 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300'"
                                        class="rounded-full px-2 py-0.5 text-[10px] font-medium">
                                        {{ usaUltimoPrecio ? 'Vigente' : 'Referencia' }}
                                    </span>
                                </div>
                                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                    Los mismos materiales al precio de su compra más reciente. Se recalculan con cada OP
                                    que use el producto.
                                </p>
                            </div>

                            <div>
                                <InputLabel for="precio_venta_producto"
                                    :value="lang().t('app.label.precio_venta_producto') || 'Precio preventa'" />
                                <div id="precio_venta_producto"
                                    class="mt-1 flex h-10 w-full items-center justify-between gap-2 rounded-md border border-gray-200 bg-gray-50 px-3 text-sm font-semibold text-gray-700 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-200">
                                    <span>{{ formatdinero(precioVentaDisplay) }}</span>
                                    <div class="flex items-center gap-2">
                                        <span
                                            :class="esPrecioManual
                                                ? 'rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-medium text-amber-800 dark:bg-amber-900/40 dark:text-amber-200'
                                                : 'rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-medium text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200'">
                                            {{ esPrecioManual ? 'Manual (.990)' : '300% del coste' }}
                                        </span>
                                        <button type="button" @click="aproximarPrecio990" v-show="!esPrecioManual"
                                            class="p-1.5 rounded-lg text-gray-500 hover:text-gray-700 hover:bg-gray-200 dark:text-gray-400 dark:hover:bg-gray-800 transition-colors"
                                            v-tooltip="lang().t('app.tooltip.aproximar_precio_990') || 'Aproximar a X.990'">
                                            <CurrencyDollarIcon class="w-4 h-4" />
                                        </button>
                                    </div>
                                </div>
                                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                    Se calcula solo: un 300% del coste vigente, redondeado al millar superior.
                                    <span v-if="esPrecioManual" class="text-amber-600 dark:text-amber-400 ml-1">
                                        (Convertido a formato .990)
                                    </span>
                                </p>
                                <InputError class="mt-2" :message="form.errors.precio_venta_producto" />
                            </div>

                            <div>
                                <InputLabel for="ganancia_producto" value="Ganancia" />
                                <div id="ganancia_producto"
                                    class="mt-1 flex h-10 w-full items-center gap-2 rounded-md border border-gray-200 bg-gray-50 px-3 text-sm font-semibold dark:border-gray-700 dark:bg-gray-900/40">
                                    <span
                                        :class="ganancia.valor >= 0
                                            ? 'text-emerald-600 dark:text-emerald-400'
                                            : 'text-rose-600 dark:text-rose-400'">
                                        {{ formatdinero(ganancia.valor) }}
                                    </span>
                                    <span v-if="ganancia.porcentaje !== null"
                                        class="text-xs font-medium text-gray-500 dark:text-gray-400">
                                        ({{ ganancia.porcentaje >= 0 ? '+' : '' }}{{ ganancia.porcentaje.toFixed(1)
                                        }}% del coste)
                                    </span>
                                </div>
                                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                    Diferencia entre el precio venta y el coste vigente; se recalcula al guardar
                                    el producto y con cada OP que lo use.
                                </p>
                            </div>

                            <div class="md:col-span-3 xl:col-span-4">
                                <InputLabel for="Observaciones"
                                    :value="lang().t('app.label.Observaciones') || 'Observaciones'" />
                                <textarea id="Observaciones" rows="2"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 shadow-sm text-sm"
                                    v-model="form.Observaciones"
                                    placeholder="Observaciones adicionales (opcional)"></textarea>
                                <InputError class="mt-2" :message="form.errors.Observaciones" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pie -->
                <div
                    class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-3 bg-gray-50 dark:bg-gray-800/50 rounded-b-lg">
                    <SecondaryButton :disabled="form.processing" type="button" @click="emit('close')">
                        {{ lang().t('app.button.close') }}
                    </SecondaryButton>
                    <PrimaryButton class="ml-3" :class="{ 'opacity-25': form.processing }" :disabled="form.processing"
                        type="submit">
                        {{ lang().t('app.button.add') }} {{ form.processing ? '...' : '' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    </section>
</template>
