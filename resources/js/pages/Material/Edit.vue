<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import InputLabel from '@/components/InputLabel.vue';
import Modal from '@/components/Modal.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import EditorMateriales from '@/pages/FichaTecnicaMateriales/EditorMateriales.vue';
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
    // Variantes: colores y otras variantes conviven en el mismo material.
    colores: [] as string[],
    variantes: [] as string[],
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
// Color y otra variante se derivan de las listas de la sección "Variantes".
const camposBooleanos = printForm.filter(
    (campo) => campo.type === 'boolean' && !['maneja_color', 'maneja_otra_variante'].includes(campo.idd),
);

const esMaterialProcesado = computed(() => Number(form.material_procesado) === 1);

/**
 * Las listas de variantes se capturan como texto separado por coma o punto y
 * coma. Un material puede tener colores y otras variantes a la vez, así que
 * escribir en una lista no toca la otra.
 */
const parsearListaVariantes = (texto: string): string[] =>
    Array.from(new Set(texto.split(/[,;]+/).map((valor) => valor.trim()).filter(Boolean)));

const coloresTexto = computed({
    get: () => (form.colores ?? []).join(', '),
    set: (valor: string) => {
        form.colores = parsearListaVariantes(valor);
    },
});

const variantesTexto = computed({
    get: () => (form.variantes ?? []).join(', '),
    set: (valor: string) => {
        form.variantes = parsearListaVariantes(valor);
    },
});

const fichaBloqueada = computed(() => Boolean(props.Materiala?.ficha_bloqueada));
const fichaBloqueoDetalle = computed(() => props.Materiala?.ficha_bloqueo_detalle ?? '');

const camposBloqueados = ['codigo_material', 'existencia_actual'];
const esCampoBloqueado = (campo: string) => camposBloqueados.includes(campo) && campo !== 'estado';

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

        const variantes = props.Materiala?.variants ?? [];
        form.colores = variantes.filter((v: any) => v.tipo === 'color').map((v: any) => v.nombre);
        form.variantes = variantes.filter((v: any) => v.tipo === 'variante').map((v: any) => v.nombre);
    }
})

const update = () => {
    const respaldo: Record<string, any> = {};
    camposBloqueados.forEach(campo => {
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
            camposBloqueados.forEach(campo => {
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
                            <div v-for="(atributosform, indice) in camposNormales" :key="indice">

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
                                        par, el material toma el siguiente número libre del prefijo nuevo.
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
                                        v-model="form[atributosform.idd]" :required="atributosform.idd !== 'observaciones'" :placeholder="atributosform.label"
                                        :error="form.errors[atributosform.idd]"
                                        :disabled="esCampoBloqueado(atributosform.idd)"
                                        :class="esCampoBloqueado(atributosform.idd) ? 'bg-gray-100 dark:bg-gray-800/60 cursor-not-allowed opacity-70' : ''" />
                                    <InputError class="mt-2" :message="form.errors[atributosform.idd]" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Atributos del material (una sola columna, toggles grandes) -->
                    <div v-if="camposBooleanos.length">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 flex items-center gap-1.5 mb-1">
                            <SwatchIcon class="w-4 h-4 text-gray-400 dark:text-gray-500" />
                            Atributos del material
                        </h3>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mb-3 pl-6">
                            Activa los atributos que aplican a este material.
                        </p>
                        <div class="space-y-2 max-w-2xl">
                            <div v-for="atributosform in camposBooleanos" :key="atributosform.idd"
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

                    <!-- Variantes del material -->
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 flex items-center gap-1.5 mb-1">
                            <SwatchIcon class="w-4 h-4 text-gray-400 dark:text-gray-500" />
                            Variantes del material
                        </h3>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mb-3 pl-6">
                            Registra los colores y las otras variantes (por ejemplo tallas). Separa los valores con
                            coma: un material sí puede tener las dos listas a la vez.
                        </p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                            <div>
                                <InputLabel for="colores" value="Colores" />
                                <TextInput id="colores" class="mt-1 block w-full"
                                    v-model="coloresTexto"
                                    placeholder="Rojo, Azul, Negro"
                                    :error="form.errors.colores" />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Al guardar, esta lista reemplaza los colores del material.
                                </p>
                                <InputError class="mt-2" :message="form.errors.colores" />
                            </div>
                            <div>
                                <InputLabel for="variantes" value="Otras variantes" />
                                <TextInput id="variantes" class="mt-1 block w-full"
                                    v-model="variantesTexto"
                                    placeholder="S, M, L, XL"
                                    :error="form.errors.variantes" />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Al guardar, esta lista reemplaza las otras variantes del material.
                                </p>
                                <InputError class="mt-2" :message="form.errors.variantes" />
                            </div>
                        </div>
                        <div v-if="(form.colores?.length || 0) + (form.variantes?.length || 0) > 0"
                            class="mt-3 flex flex-wrap items-center gap-1.5">
                            <span v-for="(color, i) in form.colores" :key="'color-' + i"
                                class="inline-flex items-center rounded-full border border-sky-200 bg-sky-50 px-2.5 py-1 text-[11px] font-medium text-sky-700 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-300">
                                {{ color }}
                            </span>
                            <span v-for="(variante, i) in form.variantes" :key="'variante-' + i"
                                class="inline-flex items-center rounded-full border border-violet-200 bg-violet-50 px-2.5 py-1 text-[11px] font-medium text-violet-700 dark:border-violet-500/30 dark:bg-violet-500/10 dark:text-violet-300">
                                {{ variante }}
                            </span>
                        </div>
                    </div>

                    <!-- Ficha técnica del material procesado -->
                    <div v-if="esMaterialProcesado">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 flex items-center gap-1.5 mb-1">
                            <SwatchIcon class="w-4 h-4 text-gray-400 dark:text-gray-500" />
                            Ficha técnica
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
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 flex items-center gap-1.5 mb-4">
                            <span class="inline-flex h-4 w-4 items-center justify-center rounded bg-indigo-600 text-white text-[10px]">
                                $
                            </span>
                            Precio de última compra
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4 pl-6">
                            Modifique el precio si ha cambiado. Se registrará una nueva entrada en el histórico.
                        </p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                            <div>
                                <InputLabel for="last_purchase_price" value="Precio" />
                                <TextInput id="last_purchase_price"
                                    type="number"
                                    class="mt-1 block w-full"
                                    v-model="form.last_purchase_price"
                                    placeholder="0.00"
                                    :error="form.errors.last_purchase_price"
                                    step="0.01"
                                    min="0" />
                                <InputError class="mt-2" :message="form.errors.last_purchase_price" />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Ingrese el precio unitario del último pedido
                                </p>
                            </div>
                            <div>
                                <InputLabel for="last_purchase_date" value="Fecha" />
                                <TextInput id="last_purchase_date"
                                    type="date"
                                    class="mt-1 block w-full"
                                    v-model="form.last_purchase_date"
                                    :error="form.errors.last_purchase_date" />
                                <InputError class="mt-2" :message="form.errors.last_purchase_date" />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Fecha de la última compra
                                </p>
                            </div>
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
