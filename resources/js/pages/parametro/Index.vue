<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import { PlusIcon, XMarkIcon } from '@heroicons/vue/24/solid';
import Create from './Create.vue';
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
})

const data = reactive({
    createOpen: false,
    editOpen: false,
    deleteOpen: false,
    showAddLista: false,
})

const addForm = useForm({
    nombre: '',
    categoria: '',
    subcategoria: '',
})

const submitLista = () => {
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

            <!-- ─── Parámetro del sistema ────────────────────────────────── -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow overflow-hidden">
                <div class="flex justify-between items-center px-5 py-3 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wide">
                        Parámetro del sistema
                    </h2>
                    <div class="flex gap-2">
                        <button
                            v-show="can(['edit parametro'])"
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
                        <tr class="border-t border-gray-100 dark:border-gray-700">
                            <td class="px-4 py-2">—</td>
                            <td class="px-4 py-2">{{ fromController?.nombre }}</td>
                            <td class="px-4 py-2">{{ fromController?.valor }}</td>
                            <td class="px-4 py-2">{{ fromController?.valor2 }}</td>
                            <td class="px-4 py-2">{{ fromController?.valor_date }}</td>
                            <td class="px-4 py-2">{{ fromController?.valor_int }}</td>
                            <td class="px-4 py-2">{{ fromController?.valor_double }}</td>
                            <td class="px-4 py-2">{{ fromController?.valor_string1m }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- ─── Listas Desplegables ──────────────────────────────────── -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow overflow-hidden">
                <div class="flex justify-between items-center px-5 py-3 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wide">
                        Listas Desplegables
                    </h2>
                    <button
                        v-if="isSuperadmin"
                        @click="data.showAddLista = !data.showAddLista"
                        class="inline-flex items-center gap-1 text-xs px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white transition"
                    >
                        <PlusIcon class="w-3.5 h-3.5" />
                        Nueva opción
                    </button>
                </div>

                <!-- Formulario para agregar (solo superadmin) -->
                <div v-if="data.showAddLista && isSuperadmin" class="px-5 py-4 bg-emerald-50 dark:bg-emerald-900/20 border-b border-emerald-200 dark:border-emerald-800">
                    <form @submit.prevent="submitLista" class="flex flex-wrap gap-3 items-end">
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-medium text-gray-600 dark:text-gray-300">Categoría</label>
                            <input
                                v-model="addForm.categoria"
                                type="text"
                                required
                                placeholder="ej: presupuesto"
                                class="text-sm border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-1.5 bg-white dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-emerald-500 outline-none"
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

        </div>

        <!-- Modales existentes -->
        <Edit
            :show="data.editOpen"
            @close="data.editOpen = false"
            :parametro="props.fromController"
            v-show="can(['edit parametro'])"
            :title="props.title"
        />
        <Delete
            :show="data.deleteOpen"
            @close="data.deleteOpen = false"
            :parametro="props.fromController"
            v-show="can(['delete parametro'])"
            :title="props.title"
        />

    </AppLayout>
</template>
