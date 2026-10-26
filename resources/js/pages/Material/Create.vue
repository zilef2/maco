<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import InputLabel from '@/components/InputLabel.vue';
import Modal from '@/components/Modal.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import { ClipboardDocumentListIcon, ExclamationTriangleIcon, SwatchIcon } from '@heroicons/vue/24/solid';
import { useForm} from '@inertiajs/vue3';
import { onMounted, computed, ref, watchEffect } from 'vue';
import '@vuepic/vue-datepicker/dist/main.css'
import vSelect from "vue-select";
import "vue-select/dist/vue-select.css";

// --------------------------- ** -------------------------

const props = defineProps({
    show: Boolean,
    title: String,
    roles: Object,
    titulos: Object, //parametros de la clase principal
    losSelect: Object,
    numberPermissions: Number,
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
});
onMounted(() => {
    if (props.numberPermissions === 100) {

        const valueRAn = Math.floor(Math.random() * (1000) + 1)
        form.codigo_material = (valueRAn);
        form.categoria = 'categoria genenerica ' + (valueRAn);
        form.nombre_base = 'nombre genenerico ' + (valueRAn);
        form.unidad_inventario = 'unidad genenerica ' + (valueRAn);
        form.maneja_color = false;
        form.maneja_talla = false;
        form.maneja_otra_variante = false;
        form.estado = 'Activo';
        form.variante_atributo_sugerido = props.losSelect?.variante_atributo_sugerido?.[0]?.nombre ?? ('variante genenerica ' + (valueRAn));
        form.proveedor_habitual = props.losSelect?.proveedor_habitual?.[0]?.id ?? null;
        form.observaciones = 'observaciones genenericas ' + (valueRAn);

        // form.hora_inicial = '0'+valueRAn+':00'//temp
        // form.fecha = '2023-06-01'

    }
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
const camposBooleanos = printForm.filter((campo) => campo.type === 'boolean');

const hayProveedores = computed(() => (props.losSelect?.proveedor_habitual?.length || 0) > 0);

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

const camposFaltantes = ref<string[]>([]);

function ValidarVacios() {
    const faltantes: string[] = [];
    printForm.forEach(element => {
        const valor = form[element.idd];
        if (typeof valor === 'boolean') return; // los campos sí/no (maneja_color, etc.): false es válido
        // 0 es un valor válido (material_procesado, existencia_actual, estado); solo cuenta vacío real.
        if (valor === '' || valor === null || valor === undefined) {
            faltantes.push(element.idd);
        }
    });
    // Validar precio de última compra (obligatorio)
    if (!form.last_purchase_price || form.last_purchase_price === '' || Number(form.last_purchase_price) < 0) {
        faltantes.push('last_purchase_price');
    }
    camposFaltantes.value = faltantes;
    return faltantes.length === 0;
}

const create = () => {
    if (ValidarVacios()) {
        // console.log("🧈 debu pieza_id:", form.pieza_id);
        form.post(route('Material.store'), {
            preserveScroll: true,
            onSuccess: () => {
                emit("close")
                form.reset()
            },
            onError: () => null,
            onFinish: () => null,
        })
    }
}

watchEffect(() => {
    if (props.show) {
        form.errors = {}
        camposFaltantes.value = [];
        if (form.material_procesado === '' || form.material_procesado == null) {
            form.material_procesado = 0;
        }
        if (form.estado === '' || form.estado == null) {
            form.estado = 'Activo';
        }
    }
});
</script>

<template>
    <section class="space-y-6">
        <Modal :show="props.show" @close="emit('close')" :maxWidth="'xl4'">
            <form class="p-0" @submit.prevent="create">
                <!-- Encabezado -->
                <div class="px-6 pt-6 pb-4 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-indigo-50 via-white to-violet-50 dark:from-gray-800 dark:via-gray-800 dark:to-gray-800 rounded-t-lg">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-600 text-white">
                            <ClipboardDocumentListIcon class="w-5 h-5" />
                        </span>
                        {{ lang().t('app.label.add') }} {{ props.title }}
                    </h2>
                    <p class="mt-1.5 text-sm text-gray-500 dark:text-gray-400 pl-10">
                        Completa los datos del nuevo material.
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

                                <!-- proveedor habitual: seleccionable de la entidad Proveedor (FK) -->
                                <div v-else-if="atributosform.idd === 'proveedor_habitual'">
                                    <InputLabel :for="atributosform.label"
                                        :value="lang().t('app.label')[atributosform.label] || atributosform.label" />
                                    <v-select v-if="hayProveedores"
                                        :id="atributosform.idd"
                                        class="mt-1 block w-full"
                                        :options="props.losSelect?.proveedor_habitual || []"
                                        label="nombre"
                                        :reduce="(item: any) => item.id"
                                        v-model="form[atributosform.idd]"
                                        :placeholder="atributosform.label"
                                    />
                                    <div v-else
                                        class="mt-1 flex items-start gap-2 rounded-lg border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/20 px-3 py-2">
                                        <ExclamationTriangleIcon class="w-4 h-4 text-amber-500 shrink-0 mt-0.5" />
                                        <p class="text-xs text-amber-700 dark:text-amber-300">
                                            No hay proveedores registrados. Registre al menos un proveedor antes de crear materiales.
                                        </p>
                                    </div>
                                    <InputError class="mt-2" :message="form.errors[atributosform.idd]" />
                                </div>

                                <!-- selects desde parámetros (categoría: material) -->
                                <div v-else-if="['unidad_inventario', 'categoria', 'variante_atributo_sugerido'].includes(atributosform.idd)">
                                    <InputLabel :for="atributosform.label"
                                        :value="lang().t('app.label')[atributosform.label] || atributosform.label" />
                                    <v-select v-if="props.losSelect?.[atributosform.idd]?.length"
                                        :id="atributosform.idd"
                                        class="mt-1 block w-full"
                                        :options="props.losSelect[atributosform.idd]"
                                        label="nombre"
                                        :reduce="(item: any) => item.nombre"
                                        v-model="form[atributosform.idd]"
                                        :placeholder="atributosform.label"
                                    />
                                    <p class="mt-1 text-xs text-red-300 dark:text-red-400" v-else>Falta configurar la subcategoria: {{ atributosform.label }}</p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Se carga desde parámetros (categoría: material).
                                    </p>
                                    <InputError class="mt-2" :message="form.errors[atributosform.idd]" />
                                </div>

                                <div v-else-if="atributosform.type === 'foreign'" id="SelectVue">
                                    <label name="labelSelectVue"> {{ atributosform.label }} </label>
                                    <v-select :options="props.losSelect[0]" v-model="form[atributosform.idd]"
                                        :reduce="element => element.value" label="name"></v-select>
                                    <InputError class="mt-2" :message="form.errors[atributosform.idd]" />
                                </div>

                                <!-- tiempo -->
                                <div v-else-if="atributosform.type === 'time'" id="SelectVue">
                                    <InputLabel :for="atributosform.label"
                                        :value="lang().t('app.label')[atributosform.label]" />
                                    <TextInput :id="atributosform.idd" :type="atributosform.type" class="mt-1 block w-full"
                                        v-model="form[atributosform.idd]" required :placeholder="atributosform.label"
                                        :error="form.errors[atributosform.idd]" step="3600" />
                                    <InputError class="mt-2" :message="form.errors[atributosform.idd]" />
                                </div>

                                <!-- normal -->
                                <div v-else>
                                    <InputLabel :for="atributosform.label"
                                        :value="lang().t('app.label')[atributosform.label]" />
                                    <TextInput :id="atributosform.idd" :type="atributosform.type" class="mt-1 block w-full"
                                        v-model="form[atributosform.idd]" required :placeholder="atributosform.label"
                                        :error="form.errors[atributosform.idd]" />
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

                    <!-- Precio de última compra -->
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 flex items-center gap-1.5 mb-4">
                            <span class="inline-flex h-4 w-4 items-center justify-center rounded bg-indigo-600 text-white text-[10px]">
                                $
                            </span>
                            Precio de última compra
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4 pl-6">
                            Este campo es obligatorio. Registre el precio de la última compra del material.
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
                                    required
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
                                    :error="form.errors.last_purchase_date"
                                    required />
                                <InputError class="mt-2" :message="form.errors.last_purchase_date" />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Fecha de la última compra
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pie -->
                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between gap-3 bg-gray-50 dark:bg-gray-800/50 rounded-b-lg">
                    <p v-if="camposFaltantes.length" class="flex items-center gap-1.5 text-sm text-red-600 dark:text-red-400">
                        <ExclamationTriangleIcon class="w-4 h-4 shrink-0" />
                        Campos faltantes: {{ camposFaltantes.join(', ') }}
                    </p>
                    <span v-else></span>
                    <div class="flex gap-3">
                        <SecondaryButton :disabled="form.processing" @click="emit('close')"> {{ lang().t('app.button.close')
                            }}
                        </SecondaryButton>
                        <PrimaryButton class="ml-3" :class="{ 'opacity-25': form.processing || !hayProveedores }"
                            :disabled="form.processing || !hayProveedores">
                            {{ lang().t('app.button.add') }} {{ form.processing ? '...' : '' }}
                        </PrimaryButton>
                    </div>
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
