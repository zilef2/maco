<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import InputLabel from '@/components/InputLabel.vue';
import Modal from '@/components/Modal.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import EditorMateriales from '@/pages/FichaTecnicaMateriales/EditorMateriales.vue';
import { formatdinero } from '@/global.ts';
import { ClipboardDocumentListIcon, SwatchIcon } from '@heroicons/vue/24/solid';
import { useForm } from '@inertiajs/vue3';
import { normalizarFragmentoCodigo } from '@/lib/utils';
import { computed, watchEffect } from 'vue';
import '@vuepic/vue-datepicker/dist/main.css'
import vSelect from "vue-select";
import "vue-select/dist/vue-select.css";

// --------------------------- ** -------------------------

const props = defineProps({
    show: Boolean,
    title: String,
    Materiala: Object,
    titulos: Object, //parametros de la clase principal
    losSelect: Object,
})
const emit = defineEmits(["close"]);

//very usefull
const justNames = props.titulos.map(names => {
    if (names['order'] !== 'noquiero'
        // &&
        // names['order'] !== 'noquiero1'
    )
        return names['order']
})
const form = useForm({
    ...Object.fromEntries(justNames.map(field => [field, ''])),
    // Deben existir desde el inicio: useForm solo envía las claves iniciales.
    last_purchase_price: '',
    last_purchase_date: new Date().toISOString().split('T')[0],
    materiales: [] as any[],
});

const printForm: Array<{ idd: string; label: string; type: string }> = [];
props.titulos.forEach((names: any) => {
    if (names['order'] !== 'noquiero'
        // && names['order'] !== 'noquiero1'
    )
        printForm.push({
            idd: names['order'], label: names['label'], type: names['type']
        })
});

const camposNormales = printForm.filter((campo) => campo.type !== 'boolean');
const camposBooleanos = printForm.filter(
    (campo) => campo.type === 'boolean' && !['generico', 'maneja_color', 'maneja_otra_variante'].includes(campo.idd),
);

// Un genérico no maneja talla, color ni diseño propios y su precio lo aporta el
// promedio de sus similares, así que estos campos se ocultan y no se envían.
const camposOmitidosPorGenerico = [
    'existencia_actual',
    'modelo_diseno_acabado',
    'color',
    'talla_tamano',
    'variante_atributo_sugerido',
    'proveedor_habitual',
];

const esGenerico = computed(() => Boolean(form.generico));

const camposVisibles = computed(() => camposNormales.filter(
    (campo) => !(esGenerico.value && camposOmitidosPorGenerico.includes(campo.idd)),
));

const camposBooleanosVisibles = computed(() => camposBooleanos.filter(
    (campo) => !(esGenerico.value && campo.idd === 'maneja_talla'),
));

const esMaterialProcesado = computed(() => Number(form.material_procesado) === 1);

const fichaBloqueada = computed(() => Boolean(props.Materiala?.ficha_bloqueada));
const fichaBloqueoDetalle = computed(() => props.Materiala?.ficha_bloqueo_detalle ?? '');

// Histórico de precios del material (tabla material_prices): cada compra o
// cambio de precio anota una fila. De ahí salen el «último precio» (la primera)
// y el «precio promedio» (la media de todas) que usan los costos.
const historialPrecios = computed<any[]>(() =>
    [...((props.Materiala?.prices ?? []) as any[])].sort(
        (a, b) =>
            String(b.fecha ?? '').localeCompare(String(a.fecha ?? '')) ||
            Number(b.id ?? 0) - Number(a.id ?? 0),
    ),
);

const precioPromedio = computed<number>(() => {
    const filas = historialPrecios.value;
    if (!filas.length) return 0;
    const suma = filas.reduce((total, fila) => total + (Number(fila.precio) || 0), 0);
    return Math.round((suma / filas.length) * 100) / 100;
});

const fechaCorta = (valor: any): string => String(valor ?? '').slice(0, 10);

const camposBloqueados = ['codigo_material', 'existencia_actual'];
const esCampoBloqueado = (campo: string) => camposBloqueados.includes(campo) && campo !== 'estado';

// Campos que la edición nunca envía: el precio y la fecha de última compra solo
// los define el formulario «Registrar compra». En un genérico se suman los que
// no aplican, para no pisar lo que ya tienen guardado.
const camposFueraDeEnvio = computed(() => Array.from(new Set([
    ...camposBloqueados,
    'last_purchase_price',
    'last_purchase_date',
    ...(esGenerico.value ? camposOmitidosPorGenerico : []),
])));

const estadoActivo = (valor: any): boolean => {
    const texto = String(valor ?? '').trim().toLowerCase();
    return texto === 'activo' || texto === '1' || texto === 'true';
};

const toggleEstado = () => {
    form.estado = estadoActivo(form.estado) ? 'Inactivo' : 'Activo';
};

const toggleActivo = (campo: string, valor: any): boolean => {
    if (campo === 'material_procesado') {
        return Number(valor) === 1;
    }
    return !!valor;
};

const toggleBoolean = (campo: string) => {
    if (campo === 'material_procesado') {
        form[campo] = Number(form[campo]) === 1 ? 0 : 1;
        return;
    }
    form[campo] = !form[campo];
};

const descripcionToggle = (campo: string, activo: boolean): string => {
    if (campo === 'material_procesado') {
        return activo ? 'Sí, este material es procesado' : 'No, este material no es procesado';
    }
    if (campo === 'generico') {
        return activo
            ? 'Sí, es genérico: sin talla, color, diseño, proveedor, existencia ni gasto diario propios; se codifica con el 0 del par (COM-CAP-0), su precio es el promedio de sus similares y la OP elige el material concreto de su familia'
            : 'No, es un material específico con sus variantes y su precio de compra';
    }
    return activo ? 'Sí, este material lo maneja' : 'No aplica para este material';
};

watchEffect(() => {
    if (props.show) {
        form.errors = {}
        props.titulos.forEach(names => {
            const valor = props.Materiala[names['order']];
            form[names['order']] = names['type'] === 'boolean'
                ? Number(valor) === 1 || valor === true
                : valor;
        });
        // Se normaliza desde el prop, no desde form: leer form.estado aquí convertiría
        // el efecto en dependiente de sí mismo y desharía el toggle al pulsarlo.
        form.estado = estadoActivo(props.Materiala?.estado) ? 'Activo' : 'Inactivo';
        
        // Cargar precio de última compra
        if (props.Materiala && props.Materiala.last_purchase_price) {
            form.last_purchase_price = props.Materiala.last_purchase_price;
            form.last_purchase_date = props.Materiala.last_purchase_date || new Date().toISOString().split('T')[0];
        } else {
            form.last_purchase_price = '';
            form.last_purchase_date = new Date().toISOString().split('T')[0];
        }

        form.materiales = (props.Materiala?.ficha_materiales ?? []).map((fila: any) => ({
            material_componente_id: fila.material_componente_id,
            consumo_estandar: fila.consumo_estandar,
            unidad_medida: fila.unidad_medida ?? '',
            costo_asociado_teorico: fila.costo_asociado_teorico ?? '',
        }));
    }
})

const update = () => {
    const respaldo: Record<string, any> = {};
    camposFueraDeEnvio.value.forEach(campo => {
        respaldo[campo] = form[campo];
        delete form[campo];
    });

    form.put(route('Material.update', props.Materiala?.id), {
        preserveScroll: true,
        onSuccess: () => {
            emit("close")
            form.reset()
        },
        onError: () => null,
        onFinish: () => {
            camposFueraDeEnvio.value.forEach(campo => {
                form[campo] = respaldo[campo];
            });
        },
    })
}
</script>

<template>
    <section class="space-y-6">
        <Modal :show="props.show" @close="emit('close')" :maxWidth="'xl6'">
            <form class="p-0" @submit.prevent="update">
                <!-- Encabezado -->
                <div class="px-6 pt-6 pb-4 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-indigo-50 via-white to-violet-50 dark:from-gray-800 dark:via-gray-800 dark:to-gray-800 rounded-t-lg">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-600 text-white">
                            <ClipboardDocumentListIcon class="w-5 h-5" />
                        </span>
                        {{ lang().t('app.label.edit') }} {{ props.title }}
                    </h2>
                    <p class="mt-1.5 text-sm text-gray-500 dark:text-gray-400 pl-10">
                        Modifica los datos del material. La existencia no es editable y el código se arma con el par
                        categoría/base.
                    </p>
                </div>

                <div class="px-6 py-5 space-y-7 max-h-[70vh] overflow-y-auto scrollbar-table">
                    <!-- Información general -->
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 flex items-center gap-1.5 mb-4">
                            <ClipboardDocumentListIcon class="w-4 h-4 text-gray-400 dark:text-gray-500" />
                            Información general
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                            <template v-for="(atributosform, indice) in camposVisibles" :key="indice">

                                <!-- estado: toggle Activo/Inactivo -->
                                <div v-if="atributosform.idd === 'estado'" class="md:col-span-2">
                                    <div role="switch" tabindex="0"
                                        :aria-checked="estadoActivo(form.estado)"
                                        @click="toggleEstado"
                                        @keydown.space.prevent="toggleEstado"
                                        class="flex items-center justify-between gap-4 px-4 py-3 rounded-xl border cursor-pointer transition-colors"
                                        :class="estadoActivo(form.estado)
                                            ? 'border-emerald-300 dark:border-emerald-700 bg-emerald-50/70 dark:bg-emerald-900/20'
                                            : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900/40 hover:border-gray-300 dark:hover:border-gray-600'">
                                        <div>
                                            <span class="block text-sm font-medium text-gray-800 dark:text-gray-200">
                                                {{ lang().t('app.label')[atributosform.label] || atributosform.label }}
                                            </span>
                                            <span class="block text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                                {{ estadoActivo(form.estado) ? 'Activo' : 'Inactivo' }}
                                            </span>
                                        </div>
                                        <span class="relative inline-flex h-7 w-12 shrink-0 items-center rounded-full transition-colors duration-200"
                                            :class="estadoActivo(form.estado) ? 'bg-emerald-600' : 'bg-gray-300 dark:bg-gray-600'">
                                            <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform duration-200"
                                                :class="estadoActivo(form.estado) ? 'translate-x-6' : 'translate-x-1'" />
                                        </span>
                                    </div>
                                    <InputError class="mt-2" :message="form.errors.estado" />
                                </div>

                                <!-- código material: lo arma el servidor con el par categoría/base -->
                                <div v-else-if="atributosform.idd === 'codigo_material'">
                                    <InputLabel :for="atributosform.label"
                                        :value="lang().t('app.label')[atributosform.label] || atributosform.label" />
                                    <TextInput :id="atributosform.idd" class="mt-1 block w-full bg-gray-100 dark:bg-gray-800/60 cursor-not-allowed opacity-70"
                                        v-model="form[atributosform.idd]" disabled />
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Se forma con el código de la categoría y el código base (TEX-LFI-4). Si cambias el
                                        par, el material toma el siguiente número libre del prefijo nuevo. Los genéricos
                                        siempre llevan el 0: COM-CAP-0.
                                    </p>
                                    <InputError class="mt-2" :message="form.errors[atributosform.idd]" />
                                </div>

                                <!-- par de códigos que forma el prefijo del material -->
                                <div v-else-if="['codigo_categoria', 'codigo_base'].includes(atributosform.idd)">
                                    <InputLabel :for="atributosform.label"
                                        :value="lang().t('app.label')[atributosform.label] || atributosform.label" />
                                    <TextInput :id="atributosform.idd" type="text"
                                        class="mt-1 block w-full uppercase tracking-widest"
                                        v-model="form[atributosform.idd]" :maxlength="3"
                                        :placeholder="atributosform.idd === 'codigo_categoria' ? 'TEX' : 'LFI'"
                                        @blur="form[atributosform.idd] = normalizarFragmentoCodigo(form[atributosform.idd])"
                                        :error="form.errors[atributosform.idd]" />
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Hasta 3 letras. Determina el prefijo del código del material.
                                    </p>
                                    <InputError class="mt-2" :message="form.errors[atributosform.idd]" />
                                </div>

                                <!-- proveedor habitual: FK hacia Proveedor -->
                                <div v-else-if="atributosform.idd === 'proveedor_habitual'">
                                    <InputLabel :for="atributosform.label"
                                        :value="lang().t('app.label')[atributosform.label] || atributosform.label" />
                                    <v-select :id="atributosform.idd"
                                        class="mt-1 block w-full"
                                        :options="props.losSelect?.proveedor_habitual || []"
                                        label="nombre"
                                        :reduce="(item: any) => item.id"
                                        v-model="form[atributosform.idd]"
                                        :placeholder="atributosform.label"
                                        :disabled="esCampoBloqueado(atributosform.idd)" />
                                    <InputError class="mt-2" :message="form.errors[atributosform.idd]" />
                                </div>

                                <div v-else-if="atributosform.type === 'foreign'" id="SelectVue">
                                    <label name="labelSelectVue"> {{ lang().t('app.label')[atributosform.label] || atributosform.label }} </label>
                                    <v-select :options="props.losSelect[0]" v-model="form[atributosform.idd]"
                                        :reduce="element => element.value" label="name"
                                        :disabled="esCampoBloqueado(atributosform.idd)"></v-select>
                                    <InputError class="mt-2" :message="form.errors[atributosform.idd]" />
                                </div>

                                <!-- tiempo -->
                                <div v-else-if="atributosform.type === 'time'" id="SelectVue">
                                    <InputLabel :for="atributosform.label"
                                        :value="lang().t('app.label')[atributosform.label]" />
                                    <TextInput :id="atributosform.idd" :type="atributosform.type" class="mt-1 block w-full"
                                        v-model="form[atributosform.idd]" required :placeholder="atributosform.label"
                                        :error="form.errors[atributosform.idd]" step="3600"
                                        :disabled="esCampoBloqueado(atributosform.idd)" />
                                    <InputError class="mt-2" :message="form.errors[atributosform.idd]" />
                                </div>

                                <!-- normal -->
                                <div v-else>
                                    <InputLabel :for="atributosform.label"
                                        :value="lang().t('app.label')[atributosform.label]" />
                                    <TextInput :id="atributosform.idd" :type="atributosform.type" class="mt-1 block w-full"
                                        v-model="form[atributosform.idd]" :required="!['observaciones', 'modelo_diseno_acabado', 'color', 'talla_tamano', 'variante_atributo_sugerido'].includes(atributosform.idd)" :placeholder="atributosform.label"
                                        :error="form.errors[atributosform.idd]"
                                        :disabled="esCampoBloqueado(atributosform.idd)"
                                        :class="esCampoBloqueado(atributosform.idd) ? 'bg-gray-100 dark:bg-gray-800/60 cursor-not-allowed opacity-70' : ''" />
                                    <InputError class="mt-2" :message="form.errors[atributosform.idd]" />
                                </div>

                                <!-- ¿genérico? manda sobre talla, color, diseño, proveedor y precio -->
                                <div v-if="atributosform.idd === 'codigo_categoria'"
                                    role="switch" tabindex="0"
                                    :aria-checked="esGenerico"
                                    @click="toggleBoolean('generico')"
                                    @keydown.space.prevent="toggleBoolean('generico')"
                                    class="md:col-span-2 flex items-center justify-between gap-4 px-4 py-3 rounded-xl border cursor-pointer transition-colors"
                                    :class="esGenerico
                                        ? 'border-indigo-300 dark:border-indigo-700 bg-indigo-50/70 dark:bg-indigo-900/20'
                                        : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900/40 hover:border-gray-300 dark:hover:border-gray-600'">
                                    <div>
                                        <span class="block text-sm font-medium text-gray-800 dark:text-gray-200">
                                            {{ lang().t('app.label')['generico'] || 'Material genérico' }}
                                        </span>
                                        <span class="block text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                            {{ descripcionToggle('generico', esGenerico) }}
                                        </span>
                                        <InputError class="mt-1" :message="form.errors.generico" />
                                    </div>
                                    <span class="relative inline-flex h-7 w-12 shrink-0 items-center rounded-full transition-colors duration-200"
                                        :class="esGenerico ? 'bg-indigo-600' : 'bg-gray-300 dark:bg-gray-600'">
                                        <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform duration-200"
                                            :class="esGenerico ? 'translate-x-6' : 'translate-x-1'" />
                                    </span>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Atributos del material (una sola columna, toggles grandes) -->
                    <div v-if="camposBooleanosVisibles.length">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 flex items-center gap-1.5 mb-1">
                            <SwatchIcon class="w-4 h-4 text-gray-400 dark:text-gray-500" />
                            Atributos del material
                        </h3>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mb-3 pl-6">
                            Activa los atributos que aplican a este material.
                        </p>
                        <div class="space-y-2 max-w-2xl">
                            <div v-for="atributosform in camposBooleanosVisibles" :key="atributosform.idd"
                                role="switch" tabindex="0"
                                :aria-checked="toggleActivo(atributosform.idd, form[atributosform.idd])"
                                @click="toggleBoolean(atributosform.idd)"
                                @keydown.space.prevent="toggleBoolean(atributosform.idd)"
                                class="flex items-center justify-between gap-4 px-4 py-3 rounded-xl border cursor-pointer transition-colors"
                                :class="toggleActivo(atributosform.idd, form[atributosform.idd])
                                    ? 'border-indigo-300 dark:border-indigo-700 bg-indigo-50/70 dark:bg-indigo-900/20'
                                    : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900/40 hover:border-gray-300 dark:hover:border-gray-600'">
                                <div>
                                    <span class="block text-sm font-medium text-gray-800 dark:text-gray-200">
                                        {{ lang().t('app.label')[atributosform.label] || atributosform.label }}
                                    </span>
                                    <span class="block text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                        {{ descripcionToggle(atributosform.idd, toggleActivo(atributosform.idd, form[atributosform.idd])) }}
                                    </span>
                                    <InputError class="mt-1" :message="form.errors[atributosform.idd]" />
                                </div>
                                <span class="relative inline-flex h-7 w-12 shrink-0 items-center rounded-full transition-colors duration-200"
                                    :class="toggleActivo(atributosform.idd, form[atributosform.idd]) ? 'bg-indigo-600' : 'bg-gray-300 dark:bg-gray-600'">
                                    <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform duration-200"
                                        :class="toggleActivo(atributosform.idd, form[atributosform.idd]) ? 'translate-x-6' : 'translate-x-1'" />
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Ficha técnica del material procesado -->
                    <div v-if="esMaterialProcesado">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 flex items-center gap-1.5 mb-1">
                            <SwatchIcon class="w-4 h-4 text-gray-400 dark:text-gray-500" />
                            Ficha de Producto
                        </h3>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mb-3 pl-6">
                            Materiales y consumos necesarios para fabricar una unidad de este material procesado.
                        </p>
                        <div v-if="fichaBloqueada"
                            class="mb-3 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-200">
                            La ficha no se puede modificar porque el material está en la OP {{ fichaBloqueoDetalle }}.
                        </div>
                        <EditorMateriales v-model="form.materiales" :opciones="props.losSelect"
                            :errors="form.errors" :disabled="fichaBloqueada" />
                        <InputError class="mt-2" :message="form.errors.materiales" />
                        <InputError class="mt-2" :message="form.errors.materiales_procesados" />
                    </div>

                    <!-- Precio de última compra -->
                    <div>
                        <template v-if="!esGenerico">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 flex items-center gap-1.5 mb-4">
                            <span class="inline-flex h-4 w-4 items-center justify-center rounded bg-indigo-600 text-white text-[10px]">
                                $
                            </span>
                            Precio de última compra
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4 pl-6">
                            Solo se puede modificar desde el formulario «Registrar compra». Aquí se muestra como referencia.
                        </p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                            <div>
                                <InputLabel for="last_purchase_price" value="Precio" />
                                <TextInput id="last_purchase_price"
                                    type="number"
                                    class="mt-1 block w-full bg-gray-100 dark:bg-gray-800/60 cursor-not-allowed opacity-70"
                                    v-model="form.last_purchase_price"
                                    placeholder="0.00"
                                    :error="form.errors.last_purchase_price"
                                    step="0.01"
                                    min="0"
                                    disabled />
                                <InputError class="mt-2" :message="form.errors.last_purchase_price" />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Se actualiza al registrar una compra
                                </p>
                            </div>
                            <div>
                                <InputLabel for="last_purchase_date" value="Fecha" />
                                <TextInput id="last_purchase_date"
                                    type="date"
                                    class="mt-1 block w-full bg-gray-100 dark:bg-gray-800/60 cursor-not-allowed opacity-70"
                                    v-model="form.last_purchase_date"
                                    :error="form.errors.last_purchase_date"
                                    disabled />
                                <InputError class="mt-2" :message="form.errors.last_purchase_date" />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    La define cada registro de compra
                                </p>
                            </div>
                        </div>
                        </template>
                        <div v-else
                            class="rounded-xl border border-indigo-200 dark:border-indigo-800 bg-indigo-50/60 dark:bg-indigo-900/20 px-4 py-3 mb-4">
                            <p class="text-sm font-medium text-gray-800 dark:text-gray-200">
                                Un material genérico no registra precio ni fecha de compra propios
                            </p>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                Su precio es el promedio de los precios de sus similares de la misma categoría y nombre
                                base; lo actualiza la acción de materiales genéricos.
                            </p>
                        </div>

                        <!-- Histórico de precios (material_prices) -->
                        <div class="mt-5">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Histórico de precios
                                    <span class="ml-1 font-medium normal-case tracking-normal text-gray-400 dark:text-gray-500">
                                        {{ historialPrecios.length }}
                                        {{ historialPrecios.length === 1 ? 'registro' : 'registros' }}
                                    </span>
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Promedio:
                                    <span class="font-semibold tabular-nums text-gray-700 dark:text-gray-200">
                                        {{ formatdinero(precioPromedio) }}
                                    </span>
                                </p>
                            </div>
                            <div
                                class="mt-2 max-h-44 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700">
                                <table class="w-full text-xs">
                                    <thead
                                        class="sticky top-0 bg-gray-50 text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                        <tr>
                                            <th class="px-3 py-1.5 text-left font-medium">Fecha</th>
                                            <th class="px-3 py-1.5 text-right font-medium">Precio</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                        <tr v-for="(fila, indice) in historialPrecios" :key="fila.id ?? indice">
                                            <td
                                                class="px-3 py-1.5 tabular-nums text-gray-600 dark:text-gray-300">
                                                {{ fechaCorta(fila.fecha) }}
                                            </td>
                                            <td
                                                class="px-3 py-1.5 text-right font-semibold tabular-nums text-gray-800 dark:text-gray-100">
                                                {{ formatdinero(fila.precio) }}
                                                <span v-if="indice === 0"
                                                    class="ml-1 rounded-full bg-sky-100 px-1.5 py-0.5 text-[10px] font-medium text-sky-800 dark:bg-sky-900/40 dark:text-sky-200">
                                                    último
                                                </span>
                                            </td>
                                        </tr>
                                        <tr v-if="!historialPrecios.length">
                                            <td colspan="2"
                                                class="px-3 py-3 text-center text-gray-400 dark:text-gray-500">
                                                Este material todavía no tiene precios registrados.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                La primera fila es el «último precio» y la media de todas es el «precio promedio»: el
                                parámetro «Costos de materiales en las OPs» decide cuál de los dos se usa.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Pie -->
                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-3 bg-gray-50 dark:bg-gray-800/50 rounded-b-lg">
                    <SecondaryButton :disabled="form.processing" @click="emit('close')"> {{ lang().t('app.button.close')
                        }}
                    </SecondaryButton>
                    <PrimaryButton class="ml-3" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                        {{ lang().t('app.button.save') }} {{ form.processing ? '...' : '' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    </section>
</template>

<style>
[name="labelSelectVue"],
.muted {
    color: #1b416699;
}

[name="labelSelectVue"] {
    /* font-size: 22px; */
    font-weight: 600;
}
</style>
