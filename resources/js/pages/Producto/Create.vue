<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import InputLabel from '@/components/InputLabel.vue';
import Modal from '@/components/Modal.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import EditorMateriales from '@/pages/FichaTecnicaMateriales/EditorMateriales.vue';
import { dineroANumero } from '@/global.ts';
import { CheckCircleIcon, ClipboardDocumentListIcon, CubeIcon, DocumentTextIcon, ShoppingBagIcon } from '@heroicons/vue/24/solid';
import { useForm } from '@inertiajs/vue3';
import { computed, watchEffect } from 'vue';
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
    }>(),
    {
        show: false,
        title: '',
        roles: () => ({}),
        titulos: () => [],
        losSelect: () => ({}),
        numberPermissions: 0,
    }
);

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const camposSelect = [
    { campo: 'Categoria_original', subcategoria: 'categoria_original' },
    { campo: 'Familia_normalizada', subcategoria: 'familia_normalizada' },
    { campo: 'Control_de_variante', subcategoria: 'control_de_variante' },
    { campo: 'Opciones', subcategoria: 'opciones' },
] as const;

const optionsPara = (campo: string) => props.losSelect?.[campo] ?? [];
const faltanOpciones = computed(() => camposSelect.filter(({ campo }) => !optionsPara(campo).length));

const form = useForm({
    Codigo_interno: '',
    ID_producto_Shopify: '',
    Categoria_original: '',
    Familia_normalizada: '',
    Titulo_Shopify: '',
    Control_de_variante: '',
    Opciones: '',
    Estado: 1,
    Observaciones: '',
    descripcion: '',
    precio_predicho: '' as string | number,
    precio_preventa: '' as string | number,
    materiales: [] as any[],
});

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
    form
        .transform((datos) => ({
            ...datos,
            materiales: datos.materiales
                .filter((fila: any) => fila.material_componente_id)
                .map((fila: any) => ({
                    ...fila,
                    consumo_estandar: dineroANumero(fila.consumo_estandar),
                    costo_asociado_teorico: dineroANumero(fila.costo_asociado_teorico),
                })),
        }))
        .post(route('Producto.store'), {
            preserveScroll: true,
            onSuccess: () => {
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
        if ((props.numberPermissions ?? 0) > 99) {
            const valueRAn = Math.floor(Math.random() * 9 + 1)
            form.Codigo_interno = 'PROD-' + valueRAn
            form.ID_producto_Shopify = 'SHP-' + valueRAn
            form.Categoria_original = 'Bolso manos libres'
            form.Familia_normalizada = 'Bolso'
            form.Titulo_Shopify = 'Producto Genérico ' + valueRAn
            form.Control_de_variante = 'Color / Talla'
            form.Opciones = 'Negro, Café'
            form.Estado = 1
            form.Observaciones = 'Observación genérica ' + valueRAn
            form.descripcion = 'Descripción genérica ' + valueRAn
            form.precio_predicho = valueRAn * 10000
            form.precio_preventa = valueRAn * 12000
        }
    }
});
</script>

<template>
    <section class="space-y-6">
        <Modal :show="props.show" @close="emit('close')" :maxWidth="'xl5'">
            <form class="p-0" @submit.prevent="create">
                <!-- Encabezado -->
                <div class="px-6 pt-6 pb-4 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-indigo-50 via-white to-violet-50 dark:from-gray-800 dark:via-gray-800 dark:to-gray-800 rounded-t-lg">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-600 text-white">
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
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 flex items-center gap-1.5 mb-4">
                            <ClipboardDocumentListIcon class="w-4 h-4 text-gray-400 dark:text-gray-500" />
                            Información principal
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                            <div>
                                <InputLabel for="Codigo_interno" :value="lang().t('app.label.Codigo_interno') || 'Código interno'" />
                                <TextInput id="Codigo_interno" type="text" class="mt-1 block w-full"
                                    v-model="form.Codigo_interno" required placeholder="Código interno"
                                    :error="form.errors.Codigo_interno" />
                                <InputError class="mt-2" :message="form.errors.Codigo_interno" />
                            </div>

                            <div>
                                <InputLabel for="Familia_normalizada" :value="lang().t('app.label.Familia_normalizada') || 'Familia normalizada'" />
                                <v-select
                                    v-if="optionsPara('Familia_normalizada').length"
                                    id="Familia_normalizada"
                                    :options="optionsPara('Familia_normalizada')"
                                    :reduce="(item: any) => item.nombre"
                                    label="nombre"
                                    v-model="form.Familia_normalizada"
                                    placeholder="Seleccione familia"
                                    append-to-body
                                    class="mt-1 block w-full"
                                />
                                <p v-else class="mt-1 rounded-md border border-amber-300 bg-amber-50 p-2 text-xs font-medium text-amber-800 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-200">
                                    Falta configurar familia_normalizada en la lista desplegable (categoría: Producto).
                                </p>
                                <InputError class="mt-2" :message="form.errors.Familia_normalizada" />
                            </div>

                            <div class="md:col-span-2">
                                <InputLabel for="Titulo_Shopify" :value="lang().t('app.label.Titulo_Shopify') || 'Título Shopify / Nombre'" />
                                <TextInput id="Titulo_Shopify" type="text" class="mt-1 block w-full"
                                    v-model="form.Titulo_Shopify" required placeholder="Título Shopify"
                                    :error="form.errors.Titulo_Shopify" />
                                <InputError class="mt-2" :message="form.errors.Titulo_Shopify" />
                            </div>

                            <div>
                                <InputLabel for="Categoria_original" :value="lang().t('app.label.Categoria_original') || 'Categoría original'" />
                                <v-select
                                    v-if="optionsPara('Categoria_original').length"
                                    id="Categoria_original"
                                    :options="optionsPara('Categoria_original')"
                                    :reduce="(item: any) => item.nombre"
                                    label="nombre"
                                    v-model="form.Categoria_original"
                                    placeholder="Seleccione categoría"
                                    class="mt-1 block w-full"
                                />
                                <p v-else class="mt-1 rounded-md border border-amber-300 bg-amber-50 p-2 text-xs font-medium text-amber-800 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-200">
                                    Falta configurar categoria_original en la lista desplegable (categoría: Producto).
                                </p>
                                <InputError class="mt-2" :message="form.errors.Categoria_original" />
                            </div>

                            <div>
                                <InputLabel for="Control_de_variante" :value="lang().t('app.label.Control_de_variante') || 'Control de variante'" />
                                <v-select
                                    v-if="optionsPara('Control_de_variante').length"
                                    id="Control_de_variante"
                                    :options="optionsPara('Control_de_variante')"
                                    :reduce="(item: any) => item.nombre"
                                    label="nombre"
                                    v-model="form.Control_de_variante"
                                    placeholder="Seleccione control de variante"
                                    class="mt-1 block w-full"
                                />
                                <p v-else class="mt-1 rounded-md border border-amber-300 bg-amber-50 p-2 text-xs font-medium text-amber-800 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-200">
                                    Falta configurar control_de_variante en la lista desplegable (categoría: Producto).
                                </p>
                                <InputError class="mt-2" :message="form.errors.Control_de_variante" />
                            </div>

                            <div class="md:col-span-2">
                                <InputLabel for="Opciones" :value="lang().t('app.label.Opciones') || 'Opciones'" />
                                <v-select
                                    v-if="optionsPara('Opciones').length"
                                    id="Opciones"
                                    :options="optionsPara('Opciones')"
                                    :reduce="(item: any) => item.nombre"
                                    label="nombre"
                                    v-model="form.Opciones"
                                    placeholder="Seleccione opciones"
                                    class="mt-1 block w-full"
                                />
                                <p v-else class="mt-1 rounded-md border border-amber-300 bg-amber-50 p-2 text-xs font-medium text-amber-800 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-200">
                                    Falta configurar opciones en la lista desplegable (categoría: Producto).
                                </p>
                                <InputError class="mt-2" :message="form.errors.Opciones" />
                            </div>
                        </div>
                    </div>

                    <!-- Ficha técnica de materiales -->
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                            <CubeIcon class="w-4 h-4 text-gray-400 dark:text-gray-500" />
                            Ficha técnica de materiales
                        </h3>
                        <p class="mt-1 mb-3 pl-6 text-xs text-gray-400 dark:text-gray-500">
                            Opcional. Los materiales se guardan en la ficha técnica de este producto.
                        </p>
                        <EditorMateriales v-model="form.materiales" :opciones="props.losSelect" :errors="form.errors" />
                        <InputError class="mt-2" :message="form.errors.materiales" />
                    </div>

                    <!-- Estado -->
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 flex items-center gap-1.5 mb-3">
                            <CheckCircleIcon class="w-4 h-4 text-gray-400 dark:text-gray-500" />
                            Estado
                        </h3>
                        <div
                            role="switch" tabindex="0"
                            :aria-checked="form.Estado == 1"
                            @click="toggleEstado"
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
                            <span class="relative inline-flex h-7 w-12 shrink-0 items-center rounded-full transition-colors duration-200"
                                :class="form.Estado == 1 ? 'bg-emerald-600' : 'bg-gray-300 dark:bg-gray-600'">
                                <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform duration-200"
                                    :class="form.Estado == 1 ? 'translate-x-6' : 'translate-x-1'" />
                            </span>
                        </div>
                    </div>

                    <!-- Campos opcionales -->
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 flex items-center gap-1.5 mb-1">
                            <DocumentTextIcon class="w-4 h-4 text-gray-400 dark:text-gray-500" />
                            Campos opcionales
                        </h3>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mb-3 pl-6">
                            Los siguientes campos son opcionales: puede dejarlos vacíos.
                        </p>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-4 rounded-xl border border-dashed border-gray-300 dark:border-gray-600 p-4">
                            <div>
                                <InputLabel for="ID_producto_Shopify" :value="lang().t('app.label.ID_producto_Shopify') || 'ID Shopify'" />
                                <TextInput id="ID_producto_Shopify" type="text" class="mt-1 block w-full"
                                    v-model="form.ID_producto_Shopify" placeholder="ID Producto Shopify (opcional)"
                                    :error="form.errors.ID_producto_Shopify" />
                                <InputError class="mt-2" :message="form.errors.ID_producto_Shopify" />
                            </div>

                            <div>
                                <InputLabel for="precio_predicho" :value="lang().t('app.label.precio_predicho') || 'Precio predicho'" />
                                <TextInput id="precio_predicho" type="number" step="any" min="0" class="mt-1 block w-full"
                                    v-model="form.precio_predicho" placeholder="Precio predicho (opcional)"
                                    :error="form.errors.precio_predicho" />
                                <InputError class="mt-2" :message="form.errors.precio_predicho" />
                            </div>

                            <div>
                                <InputLabel for="precio_preventa" :value="lang().t('app.label.precio_preventa') || 'Precio preventa'" />
                                <TextInput id="precio_preventa" type="number" step="any" min="0" class="mt-1 block w-full"
                                    v-model="form.precio_preventa" placeholder="Precio preventa (opcional)"
                                    :error="form.errors.precio_preventa" />
                                <InputError class="mt-2" :message="form.errors.precio_preventa" />
                            </div>

                            <div class="md:col-span-3">
                                <InputLabel for="descripcion" :value="lang().t('app.label.descripcion') || 'Descripción'" />
                                <textarea
                                    id="descripcion"
                                    rows="2"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 shadow-sm text-sm"
                                    v-model="form.descripcion"
                                    placeholder="Descripción del producto (opcional)"
                                ></textarea>
                                <InputError class="mt-2" :message="form.errors.descripcion" />
                            </div>

                            <div class="md:col-span-3">
                                <InputLabel for="Observaciones" :value="lang().t('app.label.Observaciones') || 'Observaciones'" />
                                <textarea
                                    id="Observaciones"
                                    rows="2"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 shadow-sm text-sm"
                                    v-model="form.Observaciones"
                                    placeholder="Observaciones adicionales (opcional)"
                                ></textarea>
                                <InputError class="mt-2" :message="form.errors.Observaciones" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pie -->
                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-3 bg-gray-50 dark:bg-gray-800/50 rounded-b-lg">
                    <SecondaryButton :disabled="form.processing" @click="emit('close')">
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
