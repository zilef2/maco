<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import Breadcrumb from '@/components/Breadcrumb.vue';
import TextInput from '@/components/TextInput.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SelectInput from '@/components/SelectInput.vue';
import { reactive, ref, computed, watch } from 'vue';

import pkg from 'lodash';

import Pagination from '@/components/Pagination.vue';
import { ChevronUpDownIcon, PencilIcon, TrashIcon, ArrowUpTrayIcon, ArrowPathIcon, SquaresPlusIcon, EllipsisVerticalIcon, ArrowDownTrayIcon } from '@heroicons/vue/24/solid';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import Create from '@/pages/OrdenProduccion/Create.vue';
import Edit from '@/pages/OrdenProduccion/Edit.vue';
import Delete from '@/pages/OrdenProduccion/Delete.vue';
import DistribucionTallas from '@/pages/OrdenProduccion/DistribucionTallas.vue';
import KitTallerOPModal from '@/pages/OrdenProduccion/KitTallerOPModal.vue';
import CambiarEstadoModal from '@/pages/OrdenProduccion/CambiarEstadoModal.vue';
import SacarMaterialesModal from '@/pages/ProcesoExterno/SacarMaterialesModal.vue';

import Checkbox from '@/components/Checkbox.vue';

import { formatDate, zilef_nuber_format } from '@/global.ts';

const { _, debounce, pickBy } = pkg
const props = defineProps({
    fromController: Object,
    total: Number,
    filters: Object,
    breadcrumbs: Object,
    perPage: Number,

    title: String,

    numberPermissions: Number,
    losSelect: Object,
    titulos: Array,
    borradorUltimo: Object,
})

const defaultPerPage = [
    { label: '5', value: 5 },
    { label: '10', value: 10 },
    { label: '25', value: 25 },
    { label: '50', value: 50 },
    { label: '100', value: 100 },
];

const data = reactive({
    params: {
        search: props.filters?.search,
        field: props.filters?.field,
        order: props.filters?.order,
        perPage: props.perPage,
    },
    OrdenProducciono: null as any,
    selectedId: [] as number[],
    multipleSelect: false,
    createOpen: false,
    borradorCrear: null as any,
    editOpen: false,
    deleteOpen: false,
    distribucionOpen: false,
    distribucionOp: null as any,
    kitTallerOpen: false,
    kitTallerOp: null as any,
    cambioEstadoOpen: false,
    cambioEstadoOp: null as any,
    salidaOpen: false,
    salidaOp: null as any,
    dataSet: (usePage().props as any)?.app?.perpage ?? defaultPerPage,
})

const order = (field: string) => {
    data.params.field = field
    data.params.order = data.params.order === "asc" ? "desc" : "asc"
}

// Skeleton: se muestra mientras Inertia vuelve a pedir datos de ESTA misma página
// (buscar, paginar, crear/editar con redirección back), no en navegaciones a otras rutas.
const cargando = ref(false)
const rutaIndex = new URL(String(route('OrdenProduccion.index')), window.location.origin).pathname
router.on('start', (event) => {
    const url = event.detail.visit.url
    const pathname = url instanceof URL ? url.pathname : new URL(String(url), window.location.origin).pathname
    if (pathname === rutaIndex) {
        cargando.value = true
    }
})
router.on('finish', () => {
    cargando.value = false
})

watch(() => _.cloneDeep(data.params), debounce(() => {
    const params = pickBy(data.params)
    router.get(route("OrdenProduccion.index"), params, {
        replace: true,
        preserveState: true,
        preserveScroll: true,
    })
}, 150))

const selectAll = (event: any) => {
    if (event.target.checked === false) {
        data.selectedId = []
    } else {
        props.fromController?.data.forEach((OrdenProduccion: any) => {
            data.selectedId.push(OrdenProduccion.id)
        })
    }
}
const select = () => data.multipleSelect = props.fromController?.data.length === data.selectedId.length;

const abrirDistribucion = (op: any) => {
    data.distribucionOp = op ?? null
    data.distribucionOpen = true
}

const abrirNuevo = () => {
    data.borradorCrear = null
    data.createOpen = true
}

const abrirContinuarUltima = () => {
    data.borradorCrear = props.borradorUltimo ?? null
    data.createOpen = true
}

const abrirKitTaller = (op: any) => {
    data.kitTallerOp = op ?? null
    data.kitTallerOpen = true
}

const abrirCambioEstado = (op: any) => {
    data.cambioEstadoOp = op ?? null
    data.cambioEstadoOpen = true
}

const authCan = (usePage().props as any)?.auth?.can ?? {}
const puede = (perms: string[]) => perms.some((p) => authCan[p])

// Acciones de la fila agrupadas en el FAB (agregar aquí las futuras)
const accionesFila = computed(() => {
    const arr: Array<{ id: string; label: string; icon: any; visible?: (op: any) => boolean }> = []
    if (puede(['update OrdenProduccion'])) {
        arr.push({ id: 'edit', label: 'Editar', icon: PencilIcon })
    }
    if (puede(['create KitTaller'])) {
        arr.push({ id: 'kit', label: 'Kit Taller / Importar Excel', icon: ArrowUpTrayIcon })
    }
    arr.push({ id: 'tallas', label: 'Editar tallas', icon: SquaresPlusIcon })
    if (puede(['create ProcesoExterno'])) {
        arr.push({
            id: 'salida', label: 'Sacar materiales al taller', icon: ArrowDownTrayIcon,
            visible: (op: any) => op?.estado_nombre === 'Lista para taller',
        })
    }
    if (puede(['delete OrdenProduccion'])) {
        arr.push({ id: 'delete', label: 'Eliminar', icon: TrashIcon })
    }
    return arr
})

const accionesVisibles = (op: any) => accionesFila.value.filter((a) => !a.visible || a.visible(op))

const onAccionFila = (id: string, op: any) => {
    if (id === 'edit') {
        data.OrdenProducciono = op
        data.editOpen = true
    }
    if (id === 'kit') abrirKitTaller(op)
    if (id === 'tallas') abrirDistribucion(op)
    if (id === 'salida') {
        data.salidaOp = op
        data.salidaOpen = true
    }
    if (id === 'delete') {
        data.OrdenProducciono = op
        data.deleteOpen = true
    }
}

const tooltipProductos = (op: any) => {
    const escapar = (s: string) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    const lineas = (op?.productos || []).map((p: any) =>
        escapar(String(p.Titulo_Shopify || p.Codigo_interno || 'Producto #' + p.id)))
    if (!lineas.length) {
        return undefined
    }
    return { content: lineas.join('<br><br>'), html: true, delay: { show: 200, hide: 100 } }
}

const titulos = [
    { order: 'numero_op', label: 'numero_op', type: 'string' },
    { order: 'producto_referencia_id', label: 'producto_referencia_id', type: 'foreign', nameid: 'producto_nombre' },
    { order: 'cantidad_planificada', label: 'cantidad_planificada', type: 'integer' },
    { order: 'fecha_prevista_envio_taller', label: 'fecha_prevista_envio_taller', type: 'date' },
    { order: 'taller_id', label: 'taller_id', type: 'foreign', nameid: 'taller_nombre' },
    { order: 'estado_op_id', label: 'estado_op_id', type: 'foreign', nameid: 'estado_nombre' },
    { order: 'user_id', label: 'user_id', type: 'foreign', nameid: 'responsable_nombre' },
    { order: 'id_shopify_producto', label: 'id_shopify_producto', type: 'string' },
    { order: 'observaciones', label: 'observaciones', type: 'text' },
];

</script>

<template>

    <Head :title="props.title" />

    <AppLayout>
        <Breadcrumb :title="title" :breadcrumbs="breadcrumbs" class="capitalize text-xl font-bold" />
        <div class="space-y-4">
            <div class="px-4 sm:px-0">
                <div class="rounded-lg overflow-hidden w-fit flex items-center gap-1">
                    <PrimaryButton class="m-1" @click="abrirNuevo" v-if="can(['create OrdenProduccion'])">
                        {{ lang().t('app.button.add') }}
                    </PrimaryButton>

                    <button v-if="can(['create OrdenProduccion']) && props.borradorUltimo" type="button"
                        @click="abrirContinuarUltima"
                        class="m-1 inline-flex items-center gap-1.5 rounded-md border border-amber-400 bg-amber-50 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-amber-700 transition hover:bg-amber-100 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:ring-offset-2 dark:border-amber-500/60 dark:bg-amber-500/10 dark:text-amber-300 dark:hover:bg-amber-500/20">
                        <ArrowPathIcon class="h-4 w-4" />
                        Continuar última OP ({{ props.borradorUltimo.numero_op }})
                    </button>

                    <Create v-if="can(['create OrdenProduccion'])" :numberPermissions="props.numberPermissions"
                        :titulos="titulos" :show="data.createOpen" @close="data.createOpen = false; data.borradorCrear = null"
                        :title="props.title" :losSelect="props.losSelect" :borrador="data.borradorCrear" />

                    <Edit v-if="can(['update OrdenProduccion'])" :titulos="titulos"
                        :numberPermissions="props.numberPermissions" :show="data.editOpen"
                        @close="data.editOpen = false" :OrdenProducciona="data.OrdenProducciono" :title="props.title"
                        :losSelect="props.losSelect" />

                    <Delete v-if="can(['delete OrdenProduccion'])" :numberPermissions="props.numberPermissions"
                        :show="data.deleteOpen" @close="data.deleteOpen = false"
                        :OrdenProducciona="data.OrdenProducciono" :title="props.title" />

                    <DistribucionTallas :show="data.distribucionOpen" @close="data.distribucionOpen = false"
                        :title="props.title" :ordenId="data.distribucionOp?.id"
                        :numeroOp="data.distribucionOp?.numero_op"
                        :items="data.distribucionOp?.distribuciones_por_tallas || []"
                        :canEdit="can(['update OrdenProduccion'])" />

                    <KitTallerOPModal :show="data.kitTallerOpen" @close="data.kitTallerOpen = false"
                        :ordenProduccion="data.kitTallerOp" :title="props.title" />

                    <CambiarEstadoModal :show="data.cambioEstadoOpen" @close="data.cambioEstadoOpen = false"
                        :title="props.title" :op="data.cambioEstadoOp"
                        :estados="props.losSelect?.estado_op_id || []" />

                    <SacarMaterialesModal :show="data.salidaOpen" @close="data.salidaOpen = false"
                        :op="data.salidaOp" />
                </div>
            </div>
            <div class="relative bg-white dark:bg-gray-800 shadow sm:rounded-lg w-max min-w-full">
                <div class="flex justify-between p-2">
                    <div class="flex space-x-2">
                        <SelectInput v-model="data.params.perPage" :dataSet="data.dataSet" />
                    </div>
                    <TextInput v-if="props.numberPermissions && props.numberPermissions > 1"
                        v-model="data.params.search" type="text" class="block w-4/6 md:w-3/6 lg:w-2/6 rounded-lg"
                        :placeholder="lang().t('app.placeholder.search')" />
                </div>
                <div>
                    <table v-if="props.total && props.total > 0" class="w-full">
                        <thead class="uppercase text-sm border-t border-gray-200 dark:border-gray-700">
                            <tr class="dark:bg-gray-900/50 text-left">
                                <th class="px-2 py-4 text-center">
                                    <Checkbox v-model:checked="data.multipleSelect" @change="selectAll" />
                                </th>
                                <th v-if="props.numberPermissions && props.numberPermissions > 1" class="px-2 py-4">
                                    Accion</th>

                                <th class="px-2 py-4 text-center">#</th>
                                <th class="px-2 py-4">Fecha</th>
                                <th v-for="titulo in titulos" :key="titulo.order" class="px-2 py-4 cursor-pointer"
                                    v-on:click="order(titulo['order'])">
                                    <div class="flex justify-between items-center">
                                        <span>{{ lang().t('app.label.' + titulo['label']) }}</span>
                                        <ChevronUpDownIcon class="w-4 h-4" />
                                    </div>
                                </th>
                                <th class="px-2 py-4">Tallas</th>
                                <th class="px-2 py-4">Productos</th>
                            </tr>
                        </thead>
                        <tbody v-if="cargando">
                            <tr v-for="n in 8" :key="'skeleton-' + n"
                                class="border-t border-gray-200 dark:border-gray-700">
                                <td :colspan="titulos.length + 6" class="px-2 py-3">
                                    <div class="flex items-center gap-4">
                                        <div class="h-4 w-4 rounded bg-gray-200 dark:bg-gray-800 animate-pulse"></div>
                                        <div class="h-4 w-10 rounded bg-gray-200 dark:bg-gray-800 animate-pulse"></div>
                                        <div class="h-4 flex-1 rounded bg-gray-200 dark:bg-gray-800 animate-pulse"></div>
                                        <div class="h-4 w-20 rounded bg-gray-200 dark:bg-gray-800 animate-pulse"></div>
                                        <div class="h-4 w-16 rounded bg-gray-200 dark:bg-gray-800 animate-pulse"></div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                        <tbody v-else>
                            <tr v-for="(claseFromController, indexu) in props.fromController?.data" :key="indexu"
                                class="border-t border-gray-200 dark:border-gray-700 hover:bg-gray-200/30 hover:dark:bg-gray-900/20">

                                <td class="whitespace-nowrap py-4 px-2 sm:py-3 text-center">
                                    <input
                                        class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-primary dark:text-primary shadow-sm focus:ring-primary/80 dark:focus:ring-primary dark:focus:ring-offset-gray-800 dark:checked:bg-primary dark:checked:border-primary"
                                        type="checkbox" @change="select" :value="claseFromController.id"
                                        v-model="data.selectedId" />
                                </td>
                                <td v-if="props.numberPermissions && props.numberPermissions > 1"
                                    class="whitespace-nowrap py-4 px-2 sm:py-3">
                                    <div class="flex items-center gap-1.5">
                                        <button v-if="puede(['update OrdenProduccion'])" type="button"
                                            @click="abrirCambioEstado(claseFromController)"
                                            title="Cambiar estado"
                                            class="inline-flex items-center gap-1.5 h-8 rounded-lg border border-gray-200 bg-white px-2 text-xs font-medium text-gray-600 shadow-sm transition hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-primary dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white">
                                            <ArrowPathIcon class="h-4 w-4" />
                                            <span>Cambiar estado</span>
                                        </button>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 shadow-sm transition hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-primary dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white"
                                                title="Acciones">
                                                <EllipsisVerticalIcon class="h-4 w-4" />
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="start" class="w-56 p-1">
                                                <DropdownMenuItem
                                                    v-for="accion in accionesVisibles(claseFromController)"
                                                    :key="accion.id"
                                                    @click="onAccionFila(accion.id, claseFromController)"
                                                    :variant="accion.id === 'delete' ? 'destructive' : 'default'"
                                                    class="flex cursor-pointer items-center gap-2 px-2.5 py-1.5 text-xs font-medium">
                                                    <component :is="accion.icon" class="h-4 w-4" />
                                                    <span>{{ accion.label }}</span>
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap py-4 px-2 sm:py-3 text-center">{{ ++indexu }}</td>
                                <td class="whitespace-nowrap py-4 px-2 sm:py-3">
                                    {{ formatDate(claseFromController.created_at, false) }}
                                </td>
                                <td v-for="titulo in titulos" :key="titulo.order"
                                    class="whitespace-nowrap py-4 px-2 sm:py-3">
                                    <span v-if="titulo['order'] === 'observaciones'">
                                        <span class="block max-w-[240px] truncate"
                                            v-tooltip="claseFromController[titulo['order']] ? { content: claseFromController[titulo['order']], delay: { show: 200, hide: 100 } } : undefined">{{
                                            claseFromController[titulo['order']]
                                            || '—' }}</span>
                                    </span>
                                    <span v-else-if="titulo['type'] === 'text' || titulo['type'] === 'string'"> {{
                                        claseFromController[titulo['order']] }} </span>
                                    <span v-if="titulo['type'] === 'number' || titulo['type'] === 'integer'"> {{
                                        zilef_nuber_format(claseFromController[titulo['order']], 0, false) }} </span>
                                    <span v-if="titulo['type'] === 'dinero'"> {{
                                        zilef_nuber_format(claseFromController[titulo['order']], 0, true) }} </span>
                                    <span v-if="titulo['type'] === 'date'"> {{
                                        formatDate(claseFromController[titulo['order']], false) }} </span>
                                    <span v-if="titulo['type'] === 'datetime'"> {{
                                        formatDate(claseFromController[titulo['order']], true) }} </span>
                                    <span v-if="titulo['type'] === 'foreign'">
                                        {{ claseFromController?.[titulo?.['nameid']] ?? '' }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap py-4 px-2 sm:py-3">
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">
                                        {{ (claseFromController.distribuciones_por_tallas || []).length }} tallas
                                    </span>
                                </td>
                                <td class="whitespace-nowrap py-4 px-2 sm:py-3 max-w-[260px]">
                                    <span class="flex items-center gap-2">
                                        <span
                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                                            {{ (claseFromController.productos || []).length }} productos
                                        </span>
                                        <span class="block max-w-[180px] truncate text-sm text-gray-600 dark:text-gray-300"
                                            v-tooltip="tooltipProductos(claseFromController)">
                                            {{ claseFromController.productos_nombres || '—' }}
                                        </span>
                                    </span>
                                </td>

                            </tr>
                            <tr class="border-t border-gray-600">
                                <td v-if="props.numberPermissions && props.numberPermissions > 1"
                                    class="whitespace-nowrap py-4 w-12 px-2 sm:py-3 text-center"> -
                                </td>
                                <td class="whitespace-nowrap py-4 px-2 sm:py-3 text-center"> Total: </td>
                                <td class="whitespace-nowrap py-4 px-2 sm:py-3 text-center">
                                    {{ props.total }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <h2 v-else class="text-center text-xl my-8">Sin Registros</h2>
                </div>
                <div v-if="props.total && props.total > 0"
                    class="flex justify-between items-center p-2 border-t border-gray-200 dark:border-gray-700">
                    <Pagination :links="props.fromController" :filters="data.params" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
