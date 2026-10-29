<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import { PlusIcon } from '@heroicons/vue/24/solid';
import vSelect from 'vue-select';
import 'vue-select/dist/vue-select.css';
import Edit from './Edit.vue';
import Delete from './Delete.vue';

const props = defineProps({
    title: String,
    filters: Object,
    breadcrumbs: Object,
    fromController: Object,
    nombresTabla: Array,
    listasAgrupadas: Object,
    isSuperadmin: Boolean,
    modelNames: Array,
    costosPromedio: Object,
    diasPrevision: Object,
})
const data = reactive({
    createOpen: false,
    editOpen: false,
    deleteOpen: false,
    showAddLista: false,
})

// Todo lo editable de esta pantalla exige superadmin: los controles se
// deshabilitan y el botón de guardar queda fuera cuando no lo es.
const puedeEditar = computed(() => props.isSuperadmin)

// Parámetro entero costos_promedio: decide cómo se toma el precio de un
// material al costear una OP.
const costosForm = useForm({
    valor_int: Number(props.costosPromedio?.valor ?? 1),
})

const costoGuardado = computed(() => Number(props.costosPromedio?.valor ?? 1))
const costoSinCambios = computed(() => costosForm.valor_int === costoGuardado.value)
const opcionElegida = computed(() =>
    (props.costosPromedio?.opciones ?? []).find((opcion) => Number(opcion.value) === Number(costosForm.valor_int))
)

const guardarCostos = () => {
    if (!puedeEditar.value) return
    costosForm.post(route('parametros.costos-promedio'), {
        preserveScroll: true,
    })
}

// Parámetro entero dias_prevision_stock: umbral del banner de previsión
// en Materiales (avisa si la existencia alcanza para menos de estos días).
const diasForm = useForm({
    valor_int: Number(props.diasPrevision?.valor ?? 2),
})

const diasGuardado = computed(() => Number(props.diasPrevision?.valor ?? 2))
const diasSinCambios = computed(() => diasForm.valor_int === diasGuardado.value)

const guardarDias = () => {
    if (!puedeEditar.value) return
    diasForm.post(route('parametros.dias-prevision'), {
        preserveScroll: true,
    })
}

const addForm = useForm({
    nombre: '',
    categoria: '',
    subcategoria: '',
})

const submitLista = () => {
    if (!puedeEditar.value) return
    addForm.post(route('parametros.lista.store'), {
        preserveScroll: true,
        onSuccess: () => {
            addForm.reset()
            data.showAddLista = false
        },
    })
}
</script>

<template>
    <Head :title="props.title" />

    <AppLayout>
        <Breadcrumb :title="title" :breadcrumbs="breadcrumbs" />

        <div class="space-y-6 p-4">

            <!-- ─── Costos de materiales en las OPs ────────────────────────── -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow overflow-hidden">
                <div class="flex justify-between items-center px-5 py-3 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wide">
                        Costos de materiales en las OPs
                    </h2>
                    <span class="text-xs text-gray-400 dark:text-gray-500">
                        parámetro entero · {{ props.costosPromedio?.nombre }}
                    </span>
                    <span v-if="!puedeEditar" class="text-xs font-medium text-amber-600 dark:text-amber-400">
                        Solo lectura · requiere superadmin
                    </span>
                </div>

                <form class="p-5 space-y-4" @submit.prevent="guardarCostos">
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        Así se toma el precio unitario de cada material al calcular el costo de un producto
                        construido con los materiales de su ficha técnica.
                    </p>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <label
                            v-for="opcion in props.costosPromedio?.opciones ?? []"
                            :key="opcion.value"
                            class="flex gap-3 rounded-xl border p-4 transition"
                            :class="[
                                !puedeEditar ? 'cursor-not-allowed opacity-60' : 'cursor-pointer',
                                Number(costosForm.valor_int) === Number(opcion.value)
                                    ? 'border-sky-500 bg-sky-50 dark:border-sky-400 dark:bg-sky-900/30'
                                    : 'border-gray-200 hover:border-gray-300 dark:border-gray-700 dark:hover:border-gray-600',
                            ]"
                        >
                            <input
                                type="radio"
                                name="costos_promedio"
                                :value="opcion.value"
                                v-model="costosForm.valor_int"
                                :disabled="!puedeEditar"
                                class="mt-1"
                            />
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold text-gray-900 dark:text-gray-100">
                                    {{ opcion.label }}
                                </span>
                                <span class="block mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ opcion.desc }}
                                </span>
                            </span>
                        </label>
                    </div>

                    <p v-if="costosForm.errors.valor_int" class="text-xs text-red-600 dark:text-red-400">
                        {{ costosForm.errors.valor_int }}
                    </p>

                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-4 dark:border-gray-700">
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Guardado:
                            <b class="text-gray-800 dark:text-gray-100">{{ props.costosPromedio?.nombre }} = {{ costoGuardado }}</b>
                            <span v-if="opcionElegida && costoSinCambios"> · {{ opcionElegida.desc }}</span>
                            <span v-if="!costoSinCambios" class="ml-2 font-medium text-amber-600 dark:text-amber-400">
                                Cambio sin guardar
                            </span>
                        </p>
                        <button
                            v-if="puedeEditar"
                            type="submit"
                            :disabled="costosForm.processing || costoSinCambios"
                            class="text-xs px-4 py-1.5 rounded-lg bg-sky-600 hover:bg-sky-700 text-white transition disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {{ costosForm.processing ? 'Guardando...' : 'Guardar' }}
                        </button>
                    </div>

                    <p class="text-[11px] text-gray-400 dark:text-gray-500">
                        Al cambiar el método se recalculan y quedan guardados los precios de las OPs abiertas;
                        cada cambio de precio se anota en el historial de la OP. Las OPs cerradas no se tocan.
                    </p>
                </form>
            </div>

            <!-- ─── Días de previsión de existencias ───────────────────────── -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow overflow-hidden">
                <div class="flex justify-between items-center px-5 py-3 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wide">
                        Días de previsión de existencias
                    </h2>
                    <span class="text-xs text-gray-400 dark:text-gray-500">
                        parámetro entero · {{ props.diasPrevision?.nombre }}
                    </span>
                    <span v-if="!puedeEditar" class="text-xs font-medium text-amber-600 dark:text-amber-400">
                        Solo lectura · requiere superadmin
                    </span>
                </div>

                <form class="p-5 space-y-4" @submit.prevent="guardarDias">
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        El banner de la pantalla de Materiales avisa de cada material cuyo stock alcanza para
                        menos de estos días, usando el gasto diario promedio calculado con las salidas a taller
                        de las OPs (últimos 30 días). Por defecto: 2 días.
                    </p>

                    <div class="flex items-center gap-3">
                        <label for="dias_prevision" class="text-sm font-medium text-gray-700 dark:text-gray-200">
                            Avisar si quedan menos de
                        </label>
                        <input
                            id="dias_prevision"
                            type="number"
                            min="1"
                            max="30"
                            step="1"
                            v-model="diasForm.valor_int"
                            :disabled="!puedeEditar"
                            class="w-24 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-1.5 text-sm text-right tabular-nums focus:ring-2 focus:ring-sky-500 outline-none disabled:opacity-60 disabled:cursor-not-allowed"
                        />
                        <span class="text-sm text-gray-600 dark:text-gray-300">días</span>
                    </div>

                    <p v-if="diasForm.errors.valor_int" class="text-xs text-red-600 dark:text-red-400">
                        {{ diasForm.errors.valor_int }}
                    </p>

                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-4 dark:border-gray-700">
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Guardado:
                            <b class="text-gray-800 dark:text-gray-100">{{ props.diasPrevision?.nombre }} = {{ diasGuardado }}</b>
                            <span v-if="!diasSinCambios" class="ml-2 font-medium text-amber-600 dark:text-amber-400">
                                Cambio sin guardar
                            </span>
                        </p>
                        <button
                            v-if="puedeEditar"
                            type="submit"
                            :disabled="diasForm.processing || diasSinCambios"
                            class="text-xs px-4 py-1.5 rounded-lg bg-sky-600 hover:bg-sky-700 text-white transition disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {{ diasForm.processing ? 'Guardando...' : 'Guardar' }}
                        </button>
                    </div>

                    <p class="text-[11px] text-gray-400 dark:text-gray-500">
                        Ejemplo: si un material gasta 100 al día y hay 299 en existencia, alcanza para 2 días;
                        con el valor en 2 el banner lo lista.
                    </p>
                </form>
            </div>

            <!-- ─── Listas Desplegables ──────────────────────────────────── -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow overflow-hidden">
                <div class="flex justify-between items-center px-5 py-3 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wide">
                        Listas Desplegables
                    </h2>
                    <button
                        v-if="puedeEditar"
                        @click="data.showAddLista = !data.showAddLista"
                        class="inline-flex items-center gap-1 text-xs px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white transition"
                    >
                        <PlusIcon class="w-3.5 h-3.5" />
                        Nueva opción
                    </button>
                </div>

                <!-- Formulario para agregar (solo superadmin) -->
                <div v-if="data.showAddLista && puedeEditar" class="px-5 py-4 bg-emerald-50 dark:bg-emerald-900/20 border-b border-emerald-200 dark:border-emerald-800">
                    <form @submit.prevent="submitLista" class="flex flex-wrap gap-3 items-end">
                        <div class="flex flex-col gap-1 min-w-[180px]">
                            <label class="text-xs font-medium text-gray-600 dark:text-gray-300">Categoría (modelo)</label>
                            <vSelect
                                v-model="addForm.categoria"
                                :options="props.modelNames"
                                :clearable="false"
                                placeholder="Selecciona modelo..."
                                class="vs--sm"
                                dropdown-max-height="300px"
                            />
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-medium text-gray-600 dark:text-gray-300">Subcategoría</label>
                            <input
                                v-model="addForm.subcategoria"
                                type="text"
                                required
                                placeholder="ej: estado"
                                class="text-sm border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-1.5 bg-white dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-emerald-500 outline-none"
                            />
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-medium text-gray-600 dark:text-gray-300">Nombre / Opción</label>
                            <input
                                v-model="addForm.nombre"
                                type="text"
                                required
                                placeholder="ej: Aprobado"
                                class="text-sm border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-1.5 bg-white dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-emerald-500 outline-none"
                            />
                        </div>
                        <div class="flex gap-2">
                            <button
                                type="submit"
                                :disabled="addForm.processing"
                                class="text-xs px-4 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white transition disabled:opacity-50"
                            >
                                {{ addForm.processing ? 'Guardando...' : 'Guardar' }}
                            </button>
                            <button
                                type="button"
                                @click="data.showAddLista = false"
                                class="text-xs px-3 py-1.5 rounded-lg bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 transition"
                            >
                                Cancelar
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Tabla agrupada por categoría → subcategoría -->
                <div v-for="(subcategorias, categoria) in listasAgrupadas" :key="categoria" class="border-b border-gray-100 dark:border-gray-700 last:border-0">
                    <!-- Cabecera categoría -->
                    <div class="px-5 py-2 bg-gray-100 dark:bg-gray-900 flex items-center gap-2">
                        <span class="text-xs font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">{{ categoria }}</span>
                    </div>

                    <div v-for="(items, subcategoria) in subcategorias" :key="subcategoria" class="px-5 pb-2">
                        <!-- Subcategoría label -->
                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mt-3 mb-1.5 tracking-wide">
                            {{ subcategoria }}
                        </p>
                        <!-- Chips / badges de solo lectura -->
                        <div class="flex flex-wrap gap-2">
                            <span
                                v-for="item in items"
                                :key="item.id"
                                class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-sky-100 dark:bg-sky-900/40 text-sky-800 dark:text-sky-300 border border-sky-200 dark:border-sky-700"
                            >
                                {{ item.nombre }}
                            </span>
                        </div>
                    </div>
                </div>

                <div v-if="!listasAgrupadas || Object.keys(listasAgrupadas).length === 0"
                    class="px-5 py-6 text-center text-sm text-gray-400"
                >
                    No hay listas desplegables configuradas.
                </div>
            </div>

            <!-- ─── Parámetros del sistema (al fondo, solo superadmin editable) ─── -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow overflow-hidden">
                <div class="flex justify-between items-center px-5 py-3 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wide">
                        Parámetros del sistema
                    </h2>
                    <div class="flex gap-2">
                        <button
                            v-if="puedeEditar"
                            @click="data.editOpen = true"
                            class="inline-flex items-center gap-1 text-xs px-3 py-1.5 rounded-lg bg-sky-600 hover:bg-sky-700 text-white transition"
                        >
                            Editar
                        </button>
                    </div>
                </div>

                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900 text-xs uppercase text-gray-500 dark:text-gray-400">
                        <tr>
                            <th v-for="(col, i) in nombresTabla?.[0]" :key="i" class="px-4 py-2 text-left">{{ col }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in fromController" class="border-t border-gray-100 dark:border-gray-700">
                            <td class="px-4 py-2">—</td>
                            <td class="px-4 py-2">{{ item?.nombre }}</td>
                            <td class="px-4 py-2">{{ item?.valor }}</td>
                            <td class="px-4 py-2">{{ item?.valor2 }}</td>
                            <td class="px-4 py-2">{{ item?.valor_date }}</td>
                            <td class="px-4 py-2">{{ item?.valor_int }}</td>
                            <td class="px-4 py-2">{{ item?.valor_double }}</td>
                            <td class="px-4 py-2">{{ item?.valor_string1m }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>

        <!-- Modales existentes -->
        <Edit
            v-if="puedeEditar"
            :show="data.editOpen"
            @close="data.editOpen = false"
            :parametro="props.fromController"
            :title="props.title"
        />
        <Delete
            v-if="puedeEditar"
            :show="data.deleteOpen"
            @close="data.deleteOpen = false"
            :parametro="props.fromController"
            :title="props.title"
        />

    </AppLayout>
</template>
