<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import SelectInput from '@/components/SelectInput.vue';
import TextInput from '@/components/TextInput.vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import Delete from '@/pages/Producto/Delete.vue';
import DistribucionTallas from '@/pages/Producto/DistribucionTallas.vue';
import Edit from '@/pages/Producto/Edit.vue';
import {
    ChevronDownIcon,
    ChevronUpDownIcon,
    ChevronUpIcon,
    CubeIcon,
    EllipsisVerticalIcon,
    MagnifyingGlassIcon,
    PencilIcon,
    SquaresPlusIcon,
    TrashIcon,
    XMarkIcon,
} from '@heroicons/vue/24/solid';
import { router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import pkg from 'lodash';
import { zilef_nuber_format } from '@/global.ts';

const { debounce, pickBy } = pkg;

const props = defineProps({
    productos: { type: Object as () => any, default: () => ({}) },
    filters: { type: Object as () => any, default: () => ({}) },
    losSelect: { type: Object as () => any, default: () => ({}) },
    titulos: { type: Array as () => any[], default: () => [] },
    numberPermissions: Number,
    title: String,
});

const defaultPerPage = [
    { label: '5', value: 5 },
    { label: '10', value: 10 },
    { label: '25', value: 25 },
    { label: '50', value: 50 },
    { label: '100', value: 100 },
];

const params = reactive({
    psearch: String(props.filters?.psearch ?? ''),
    pperPage: Number(props.filters?.pperPage ?? 10),
});

const sortKey = ref(String(props.filters?.pfield ?? 'Codigo_interno'));
const sortDir = ref<'asc' | 'desc'>(props.filters?.porder === 'desc' ? 'desc' : 'asc');

const data = reactive({
    Producto: null as any,
    editOpen: false,
    deleteOpen: false,
    distribucionOpen: false,
    distribucionProducto: null as any,
    dataSet: (usePage().props as any)?.app?.perpage ?? defaultPerPage,
});

const filas = computed<any[]>(() => props.productos?.data ?? []);
const totalRegistros = computed(() => Number(props.productos?.total ?? 0));
const hayBusqueda = computed(() => params.psearch.trim() !== '');

const columnas = [
    { key: 'Codigo_interno', label: 'Código', sortable: true, align: 'left', headerClass: 'min-w-[120px]', cellClass: 'min-w-[120px]' },
    { key: 'Titulo_Shopify', label: 'Producto', sortable: true, align: 'left', headerClass: 'min-w-[240px]', cellClass: 'min-w-[240px]' },
    { key: 'cantidad', label: 'Cantidad', sortable: false, align: 'right', headerClass: 'w-[100px]', cellClass: 'w-[100px]' },
    { key: 'Categoria_original', label: 'Categoría', sortable: true, align: 'left', headerClass: 'hidden min-w-[180px] lg:table-cell', cellClass: 'hidden min-w-[180px] lg:table-cell' },
    { key: 'Control_de_variante', label: 'Variante', sortable: true, align: 'left', headerClass: 'hidden min-w-[160px] xl:table-cell', cellClass: 'hidden min-w-[160px] xl:table-cell' },
    { key: 'Estado', label: 'Estado', sortable: true, align: 'left', headerClass: 'w-[110px]', cellClass: 'w-[110px]' },
    { key: 'precio_preventa', label: 'Precio', sortable: true, align: 'right', headerClass: 'w-[140px]', cellClass: 'w-[140px]' },
    { key: 'precio_estimado', label: 'Precio estimado', sortable: true, align: 'right', headerClass: 'w-[150px]', cellClass: 'w-[150px]' },
    { key: 'tallas', label: 'Tallas', sortable: false, align: 'left', headerClass: 'hidden min-w-[150px] md:table-cell', cellClass: 'hidden min-w-[150px] md:table-cell' },
];

// Solo se reenvían los filtros de la tabla de productos: los de fichas se leen
// de la página para no perder la búsqueda/orden del listado principal.
const navegar = (pagina = 1) => {
    const filtrosPagina = (usePage().props as any).filters ?? {};
    router.get(
        route('FichaTecnicaMateriales.index'),
        pickBy({
            search: filtrosPagina.search,
            field: filtrosPagina.field,
            order: filtrosPagina.order,
            perPage: filtrosPagina.perPage,
            psearch: params.psearch,
            pfield: sortKey.value,
            porder: sortDir.value,
            pperPage: params.pperPage,
            ppage: pagina,
        }),
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

watch(
    () => params.psearch,
    debounce(() => navegar(), 250),
);

watch(() => params.pperPage, () => navegar());

const ordenarPor = (key: string) => {
    if (sortKey.value === key) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = key;
        sortDir.value = 'asc';
    }
    navegar();
};

const indicador = (key: string) => (sortKey.value === key ? sortDir.value : null);

const limpiarBusqueda = () => {
    params.psearch = '';
};

const abrirAccion = (accion: string, producto: any) => {
    data.Producto = producto;

    if (accion === 'edit') data.editOpen = true;
    if (accion === 'tallas') {
        data.distribucionProducto = producto;
        data.distribucionOpen = true;
    }
    if (accion === 'delete') data.deleteOpen = true;
};

const distribucionesDe = (producto: any): any[] => {
    const lista = producto?.distribuciones_por_tallas ?? producto?.distribucionesPorTallas ?? [];
    return Array.isArray(lista) ? lista : [];
};

const tallasDe = (producto: any): string[] =>
    Array.from(
        new Set(
            distribucionesDe(producto)
                .map((item: any) => String(item?.talla ?? '').trim())
                .filter((talla: string) => talla !== ''),
        ),
    );

const detalleTallasDe = (producto: any): string =>
    distribucionesDe(producto)
        .map((item: any) => {
            const talla = String(item?.talla ?? '').trim();
            const cantidad = Number(item?.cantidad_planificada ?? 0);
            return talla === '' || Number.isNaN(cantidad) ? '' : `${talla} ${cantidad}`;
        })
        .filter(Boolean)
        .join(' · ');

const estadoNormalizado = (valor: any): 'Activo' | 'Inactivo' => {
    const texto = String(valor ?? '').trim().toLowerCase();
    return ['1', 'activo', 'activa', 'true', 'si', 'sí'].includes(texto) ? 'Activo' : 'Inactivo';
};

const ESTADO_ESTILOS = {
    activo: {
        dot: 'bg-emerald-500',
        badge: 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300',
    },
    inactivo: {
        dot: 'bg-slate-400',
        badge: 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-500/30 dark:bg-slate-500/10 dark:text-slate-300',
    },
};

const estadoEstilo = (valor: any) => ESTADO_ESTILOS[estadoNormalizado(valor) === 'Activo' ? 'activo' : 'inactivo'];

const texto = (valor: any, fallback = '—'): string => {
    const resultado = String(valor ?? '').trim();
    return resultado !== '' ? resultado : fallback;
};

const formatearPrecio = (valor: any, decimales = 0): string =>
    valor === null || valor === undefined || valor === '' ? '—' : zilef_nuber_format(valor, decimales, true);

const decimalesDe = (valor: any): number =>
    Number.isFinite(Number(valor)) && Math.round(Number(valor) * 100) % 100 !== 0 ? 2 : 0;
</script>

<template>
    <section class="relative bg-white dark:bg-gray-800 shadow sm:rounded-lg w-max min-w-full">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-3 py-2.5 dark:border-gray-700">
            <div class="min-w-0">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Productos sin ficha técnica</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    <span class="font-medium tabular-nums">{{ totalRegistros }}</span>
                    {{ totalRegistros === 1 ? 'producto espera' : 'productos esperan' }} su ficha
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <div class="relative min-w-[220px]">
                    <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <TextInput
                        v-model="params.psearch"
                        type="search"
                        aria-label="Buscar productos sin ficha"
                        placeholder="Buscar código, título o categoría…"
                        class="block h-9 w-full rounded-lg pl-9 pr-9 text-sm"
                    />
                    <button
                        v-if="hayBusqueda"
                        type="button"
                        @click="limpiarBusqueda"
                        aria-label="Limpiar búsqueda de productos"
                        class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700"
                    >
                        <XMarkIcon class="h-3.5 w-3.5" />
                    </button>
                </div>
                <SelectInput v-model="params.pperPage" :dataSet="data.dataSet" />
            </div>
        </div>

        <div class="max-h-[60vh] overflow-auto">
            <table v-if="filas.length" class="w-full min-w-[1240px] border-collapse text-sm">
                <thead class="uppercase">
                    <tr class="text-left dark:bg-gray-900/50">
                        <th
                            v-for="col in columnas"
                            :key="col.key"
                            scope="col"
                            :class="['border-b border-t border-gray-200 px-3 py-3 text-[11px] font-semibold tracking-wide text-gray-500 dark:border-gray-700 dark:text-gray-400', col.align === 'right' ? 'text-right' : 'text-left', col.headerClass]"
                        >
                            <button
                                v-if="col.sortable"
                                type="button"
                                @click="ordenarPor(col.key)"
                                :class="[
                                    'inline-flex items-center gap-1 hover:text-gray-800 dark:hover:text-gray-200',
                                    col.align === 'right' ? 'w-full justify-end' : '',
                                ]"
                            >
                                <span>{{ col.label }}</span>
                                <ChevronUpIcon v-if="indicador(col.key) === 'asc'" class="h-3.5 w-3.5" />
                                <ChevronDownIcon v-else-if="indicador(col.key) === 'desc'" class="h-3.5 w-3.5" />
                                <ChevronUpDownIcon v-else class="h-3.5 w-3.5 opacity-40" />
                            </button>
                            <span v-else>{{ col.label }}</span>
                        </th>

                        <th class="w-[104px] border-b border-t border-gray-200 px-2 py-3 text-right text-[11px] font-semibold tracking-wide text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            Acciones
                        </th>
                    </tr>
                </thead>

                <tbody>
                    <tr
                        v-for="producto in filas"
                        :key="producto.id"
                        class="border-b border-gray-200 transition-colors last:border-0 hover:bg-gray-200/30 dark:border-gray-700 dark:hover:bg-gray-900/20"
                    >
                        <td class="px-3 py-3 align-top">
                            <div class="flex flex-col gap-0.5">
                                <span class="font-semibold tabular-nums text-gray-900 dark:text-gray-100">
                                    {{ texto(producto.Codigo_interno) }}
                                </span>
                                <span
                                    v-if="producto.ID_producto_Shopify"
                                    class="block max-w-[140px] truncate text-[10px] text-gray-500 dark:text-gray-400"
                                    :title="producto.ID_producto_Shopify"
                                >
                                    {{ producto.ID_producto_Shopify }}
                                </span>
                            </div>
                        </td>

                        <td class="px-3 py-3 align-top">
                            <div class="flex max-w-[300px] flex-col gap-0.5">
                                <span class="truncate font-medium text-gray-900 dark:text-gray-100" :title="producto.Titulo_Shopify">
                                    {{ texto(producto.Titulo_Shopify) }}
                                </span>
                                <span class="truncate text-[11px] text-gray-500 dark:text-gray-400" :title="producto.descripcion">
                                    {{ texto(producto.descripcion, 'Sin descripción') }}
                                </span>
                            </div>
                        </td>

                        <td class="px-3 py-3 text-right align-top">
                            <div class="flex flex-col items-end gap-0.5">
                                <span class="font-semibold tabular-nums text-gray-900 dark:text-gray-100">
                                    {{ Number(producto.cantidad ?? 0) }}
                                </span>
                                <span
                                    v-if="detalleTallasDe(producto)"
                                    class="max-w-[130px] truncate text-[11px] tabular-nums text-gray-500 dark:text-gray-400"
                                    :title="detalleTallasDe(producto)"
                                >
                                    {{ detalleTallasDe(producto) }}
                                </span>
                            </div>
                        </td>

                        <td class="hidden px-3 py-3 align-top lg:table-cell">
                            <div class="flex max-w-[210px] flex-col gap-0.5">
                                <span class="truncate text-sm text-gray-900 dark:text-gray-100" :title="producto.Categoria_original">
                                    {{ texto(producto.Categoria_original) }}
                                </span>
                                <span class="truncate text-[11px] text-gray-500 dark:text-gray-400" :title="producto.Familia_normalizada">
                                    {{ texto(producto.Familia_normalizada, 'Sin familia') }}
                                </span>
                            </div>
                        </td>

                        <td class="hidden px-3 py-3 align-top xl:table-cell">
                            <div class="flex max-w-[190px] flex-col gap-0.5">
                                <span class="truncate text-sm text-gray-900 dark:text-gray-100" :title="producto.Control_de_variante">
                                    {{ texto(producto.Control_de_variante) }}
                                </span>
                                <span class="truncate text-[11px] text-gray-500 dark:text-gray-400" :title="producto.Opciones">
                                    {{ texto(producto.Opciones, 'Sin opciones') }}
                                </span>
                            </div>
                        </td>

                        <td class="px-3 py-3 align-top">
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[11px] font-medium"
                                :class="estadoEstilo(producto.Estado).badge"
                            >
                                <span class="h-1.5 w-1.5 rounded-full" :class="estadoEstilo(producto.Estado).dot"></span>
                                {{ estadoNormalizado(producto.Estado) }}
                            </span>
                        </td>

                        <td class="px-3 py-3 text-right align-top">
                            <div class="flex flex-col items-end gap-0.5">
                                <span class="font-semibold tabular-nums text-gray-900 dark:text-gray-100">
                                    {{ formatearPrecio(producto.precio_preventa) }}
                                </span>
                                <span class="text-[11px] tabular-nums text-gray-500 dark:text-gray-400">
                                    Predicho {{ formatearPrecio(producto.precio_predicho) }}
                                </span>
                            </div>
                        </td>

                        <td class="px-3 py-3 text-right align-top">
                            <span class="font-medium tabular-nums text-gray-900 dark:text-gray-100">
                                {{ formatearPrecio(producto.precio_estimado, decimalesDe(producto.precio_estimado)) }}
                            </span>
                        </td>

                        <td class="hidden px-3 py-3 align-top md:table-cell">
                            <div class="flex max-w-[190px] flex-wrap items-center gap-1">
                                <span
                                    v-for="talla in tallasDe(producto).slice(0, 3)"
                                    :key="talla"
                                    class="inline-flex min-w-6 items-center justify-center rounded border border-gray-300 bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold text-gray-600 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300"
                                >
                                    {{ talla }}
                                </span>
                                <span
                                    v-if="tallasDe(producto).length > 3"
                                    class="inline-flex min-w-6 items-center justify-center rounded border border-gray-300 bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold text-gray-600 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300"
                                    :title="tallasDe(producto).slice(3).join(', ')"
                                >
                                    +{{ tallasDe(producto).length - 3 }}
                                </span>
                                <span v-if="!tallasDe(producto).length" class="text-xs text-gray-400">—</span>
                            </div>
                        </td>

                        <td class="w-[104px] border-l border-gray-200 px-2 py-2 align-middle dark:border-gray-700">
                            <div class="flex items-center justify-end gap-1">
                                <button
                                    v-if="can(['update Producto'])"
                                    type="button"
                                    @click="abrirAccion('edit', producto)"
                                    title="Editar"
                                    :aria-label="`Editar ${producto.Codigo_interno || producto.Titulo_Shopify}`"
                                    class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-gray-300 text-gray-500 transition hover:bg-gray-100 hover:text-gray-800 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700"
                                >
                                    <PencilIcon class="h-4 w-4" />
                                </button>
                                <DropdownMenu v-if="can(['update Producto']) || can(['delete Producto'])">
                                    <DropdownMenuTrigger
                                        type="button"
                                        title="Acciones"
                                        :aria-label="`Acciones de ${producto.Codigo_interno || producto.Titulo_Shopify}`"
                                        class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-gray-300 text-gray-500 transition hover:bg-gray-100 hover:text-gray-800 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700"
                                    >
                                        <EllipsisVerticalIcon class="h-4 w-4" />
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end" class="w-56 p-1">
                                        <DropdownMenuItem
                                            v-if="can(['update Producto'])"
                                            @click="abrirAccion('tallas', producto)"
                                            class="flex cursor-pointer items-center gap-2 px-2.5 py-1.5 text-xs font-medium"
                                        >
                                            <SquaresPlusIcon class="h-4 w-4" />
                                            <span>Tallas</span>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            v-if="can(['delete Producto'])"
                                            @click="abrirAccion('delete', producto)"
                                            variant="destructive"
                                            class="flex cursor-pointer items-center gap-2 px-2.5 py-1.5 text-xs font-medium"
                                        >
                                            <TrashIcon class="h-4 w-4" />
                                            <span>Eliminar</span>
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div v-else class="flex flex-col items-center justify-center gap-2 px-6 py-12 text-center">
                <CubeIcon class="h-6 w-6 text-gray-400" />
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ hayBusqueda ? 'Ningún producto sin ficha coincide con la búsqueda.' : 'Todos los productos tienen ficha técnica.' }}
                </p>
                <button
                    v-if="hayBusqueda"
                    type="button"
                    @click="limpiarBusqueda"
                    class="text-xs font-medium text-indigo-600 hover:text-indigo-700 dark:text-indigo-400"
                >
                    Limpiar búsqueda
                </button>
            </div>
        </div>

        <div v-if="totalRegistros > Number(props.productos?.per_page ?? 10)" class="border-t border-gray-200 dark:border-gray-700">
            <DataPagination
                :current-page="Number(props.productos?.current_page ?? 1)"
                :last-page="Number(props.productos?.last_page ?? 1)"
                :from="Number(props.productos?.from ?? 0)"
                :to="Number(props.productos?.to ?? 0)"
                :total="totalRegistros"
                item-label="productos"
                @page="navegar"
            />
        </div>

        <Edit
            v-if="can(['update Producto'])"
            :number-permissions="props.numberPermissions"
            :titulos="props.titulos"
            :show="data.editOpen"
            @close="data.editOpen = false"
            :Productoa="data.Producto"
            title="Producto"
            :losSelect="props.losSelect"
        />

        <Delete
            v-if="can(['delete Producto'])"
            :show="data.deleteOpen"
            @close="data.deleteOpen = false"
            :Productoa="data.Producto"
            title="Producto"
        />

        <DistribucionTallas
            v-if="can(['update Producto'])"
            :show="data.distribucionOpen"
            @close="data.distribucionOpen = false"
            :title="props.title"
            :productoId="data.distribucionProducto?.id"
            :productoLabel="data.distribucionProducto?.Codigo_interno"
            :items="distribucionesDe(data.distribucionProducto)"
            :ordenesTallas="(data.distribucionProducto as any)?.ordenes_con_tallas || []"
        />
    </section>
</template>
