<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
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
    FunnelIcon,
    MagnifyingGlassIcon,
    PencilIcon,
    SquaresPlusIcon,
    TrashIcon,
    XMarkIcon,
} from '@heroicons/vue/24/solid';
import { reactive } from 'vue';
import { zilef_nuber_format } from '@/global.ts';
import { useTablaClient, type ColumnaTabla } from '@/composables/useTablaClient';

const props = defineProps({
    productos: { type: Array as () => any[], default: () => [] },
    losSelect: { type: Object as () => any, default: () => ({}) },
    titulos: { type: Array as () => any[], default: () => [] },
    numberPermissions: Number,
    title: String,
});

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

// Desglose que valida la columna Cantidad: «S 10 · M 5».
const detalleTallasDe = (producto: any): string =>
    distribucionesDe(producto)
        .map((item: any) => {
            const talla = String(item?.talla ?? '').trim();
            const cantidad = Number(item?.cantidad_planificada ?? 0);
            return talla === '' || Number.isNaN(cantidad) ? '' : `${talla} ${cantidad}`;
        })
        .filter(Boolean)
        .join(' · ');

const ordenesDe = (producto: any): string[] => {
    const lista = producto?.ordenes_con_tallas ?? [];
    return Array.isArray(lista) ? lista.map((numeroOp: any) => String(numeroOp)) : [];
};

const columnas: ColumnaTabla[] = [
    { key: 'Codigo_interno', label: 'Código', sortable: true, align: 'left', headerClass: 'min-w-[130px]' },
    { key: 'Titulo_Shopify', label: 'Producto', sortable: true, align: 'left', headerClass: 'min-w-[260px]' },
    {
        key: 'cantidad',
        label: 'Cantidad',
        sortable: true,
        align: 'right',
        headerClass: 'w-[110px]',
        valor: (producto: any) => Number(producto.cantidad ?? 0),
    },
    { key: 'Categoria_original', label: 'Categoría', sortable: true, align: 'left', headerClass: 'hidden min-w-[190px] lg:table-cell' },
    { key: 'Control_de_variante', label: 'Variante', sortable: true, align: 'left', headerClass: 'hidden min-w-[170px] xl:table-cell' },
    { key: 'Estado', label: 'Estado', sortable: true, align: 'left', headerClass: 'w-[110px]' },
    {
        key: 'precio_venta_producto',
        label: 'Precio',
        sortable: true,
        align: 'right',
        headerClass: 'w-[140px]',
        valor: (producto: any) => Number(producto.precio_venta_producto ?? 0),
    },
    {
        key: 'tallas',
        label: 'Tallas',
        sortable: true,
        align: 'left',
        headerClass: 'hidden min-w-[150px] md:table-cell',
        valor: (producto: any) => tallasDe(producto).length,
    },
    {
        key: 'ordenes_con_tallas',
        label: 'OPs',
        sortable: true,
        align: 'left',
        headerClass: 'hidden min-w-[120px] lg:table-cell',
        valor: (producto: any) => ordenesDe(producto).length,
    },
];

const tabla = useTablaClient(() => props.productos ?? [], {
    columnas,
    busquedaPor: ['Codigo_interno', 'Titulo_Shopify', 'Categoria_original', 'Familia_normalizada', 'Control_de_variante', 'Opciones'],
    porPagina: 10,
    orden: { key: 'Codigo_interno', dir: 'asc' },
});

const {
    busqueda,
    porPagina,
    paginaActual,
    paginas,
    desde,
    hasta,
    visibles,
    total,
    hayBusqueda,
    totalRegistros,
    cambiarPagina,
    ordenarPor,
    indicador,
    ariaSort,
    limpiar,
} = tabla;

const data = reactive({
    Producto: null as any,
    editOpen: false,
    deleteOpen: false,
    distribucionOpen: false,
    distribucionProducto: null as any,
});

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

const selectClass =
    'h-9 rounded-md border border-input bg-background px-2 text-sm text-foreground shadow-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30';

const abrirAccion = (accion: string, producto: any) => {
    data.Producto = producto;

    if (accion === 'edit') data.editOpen = true;
    if (accion === 'tallas') {
        data.distribucionProducto = producto;
        data.distribucionOpen = true;
    }
    if (accion === 'delete') data.deleteOpen = true;
};

const iconoAccion =
    'inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';
</script>

<template>
    <section class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
        <div class="flex flex-col gap-2.5 border-b border-border p-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="min-w-0">
                    <h2 class="text-sm font-semibold text-foreground">Productos sin ficha técnica</h2>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        <span class="tabular-nums font-medium text-foreground">{{ totalRegistros }}</span>
                        {{ totalRegistros === 1 ? 'producto espera' : 'productos esperan' }} su ficha. Se la asigna
                        desde Editar, en la sección «Ficha técnica de materiales».
                    </p>
                </div>

                <p v-if="hayBusqueda" class="text-xs tabular-nums text-muted-foreground">
                    <span class="font-medium text-foreground">{{ total }}</span> de {{ totalRegistros }} coinciden
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <div class="relative min-w-[220px] flex-1">
                    <MagnifyingGlassIcon
                        class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <input v-model="busqueda" type="text" aria-label="Buscar productos sin ficha"
                        placeholder="Buscar por código, título, categoría o variante…"
                        class="h-9 w-full rounded-md border border-input bg-background pl-9 pr-9 text-sm text-foreground shadow-sm placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30" />
                    <button v-if="busqueda" type="button" @click="limpiar" aria-label="Limpiar búsqueda de productos"
                        class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-muted-foreground transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                        <XMarkIcon class="h-3.5 w-3.5" />
                    </button>
                </div>

                <select v-model.number="porPagina" aria-label="Productos por página" :class="selectClass">
                    <option v-for="n in [10, 25, 50, 100]" :key="n" :value="n">{{ n }} / página</option>
                </select>
            </div>
        </div>

        <div class="mat-scroll max-h-[68vh] overflow-auto overscroll-contain">
            <table v-if="total > 0" class="w-full min-w-[1330px] border-collapse text-sm">
                <thead>
                    <tr>
                        <th v-for="col in columnas" :key="col.key" scope="col"
                            :aria-sort="col.sortable ? ariaSort(col.key) : undefined" :class="[
                                'bg-muted px-3 py-2.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground',
                                col.align === 'right' ? 'text-right' : 'text-left',
                                col.headerClass,
                            ]">
                            <button v-if="col.sortable" type="button" @click="ordenarPor(col.key)" :class="[
                                'group/sort inline-flex items-center gap-1 rounded-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                                col.align === 'right' ? 'w-full justify-end' : '',
                            ]">
                                <span>{{ col.label }}</span>
                                <span
                                    class="text-muted-foreground/60 transition-colors group-hover/sort:text-foreground">
                                    <ChevronUpIcon v-if="indicador(col.key) === 'asc'" class="h-3 w-3" />
                                    <ChevronDownIcon v-else-if="indicador(col.key) === 'desc'" class="h-3 w-3" />
                                    <ChevronUpDownIcon v-else
                                        class="h-3 w-3 opacity-0 transition-opacity group-hover/sort:opacity-100" />
                                </span>
                            </button>
                            <span v-else>{{ col.label }}</span>
                        </th>

                        <th scope="col"
                            class="sticky right-0 z-30 w-[104px] border-l border-border bg-muted px-2 py-2.5 text-right text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground">
                            Acciones
                        </th>
                    </tr>
                </thead>

                <tbody :key="paginaActual" class="mat-page">
                    <tr v-for="producto in visibles" :key="producto.id"
                        class="group border-b border-border/60 transition-colors last:border-0 hover:bg-muted/40">
                        <td class="px-3 py-3 align-top">
                            <div class="flex flex-col gap-0.5">
                                <span class="font-semibold tabular-nums tracking-tight text-foreground">
                                    {{ texto(producto.Codigo_interno) }}
                                </span>
                                <span v-if="producto.ID_producto_Shopify"
                                    class="block max-w-[140px] truncate text-[10px] text-muted-foreground"
                                    :title="producto.ID_producto_Shopify">
                                    {{ producto.ID_producto_Shopify }}
                                </span>
                            </div>
                        </td>

                        <td class="px-3 py-3 align-top">
                            <div class="flex max-w-[300px] flex-col gap-0.5">
                                <span class="truncate font-medium text-foreground" :title="producto.Titulo_Shopify">
                                    {{ texto(producto.Titulo_Shopify) }}
                                </span>
                                <span class="truncate text-[11px] text-muted-foreground" :title="producto.descripcion">
                                    {{ texto(producto.descripcion, 'Sin descripción') }}
                                </span>
                            </div>
                        </td>

                        <td class="px-3 py-3 text-right align-top">
                            <div class="flex flex-col items-end gap-0.5">
                                <span class="font-semibold tabular-nums text-foreground">
                                    {{ Number(producto.cantidad ?? 0) }}
                                </span>
                                <span v-if="detalleTallasDe(producto)"
                                    class="max-w-[130px] truncate text-[11px] tabular-nums text-muted-foreground"
                                    :title="detalleTallasDe(producto)">
                                    {{ detalleTallasDe(producto) }}
                                </span>
                            </div>
                        </td>

                        <td class="hidden px-3 py-3 align-top lg:table-cell">
                            <div class="flex max-w-[210px] flex-col gap-0.5">
                                <span class="truncate text-sm text-foreground" :title="producto.Categoria_original">
                                    {{ texto(producto.Categoria_original) }}
                                </span>
                                <span class="truncate text-[11px] text-muted-foreground"
                                    :title="producto.Familia_normalizada">
                                    {{ texto(producto.Familia_normalizada, 'Sin familia') }}
                                </span>
                            </div>
                        </td>

                        <td class="hidden px-3 py-3 align-top xl:table-cell">
                            <div class="flex max-w-[190px] flex-col gap-0.5">
                                <span class="truncate text-sm text-foreground" :title="producto.Control_de_variante">
                                    {{ texto(producto.Control_de_variante) }}
                                </span>
                                <span class="truncate text-[11px] text-muted-foreground" :title="producto.Opciones">
                                    {{ texto(producto.Opciones, 'Sin opciones') }}
                                </span>
                            </div>
                        </td>

                        <td class="px-3 py-3 align-top">
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[11px] font-medium"
                                :class="estadoEstilo(producto.Estado).badge">
                                <span class="h-1.5 w-1.5 rounded-full"
                                    :class="estadoEstilo(producto.Estado).dot"></span>
                                {{ estadoNormalizado(producto.Estado) }}
                            </span>
                        </td>

                        <td class="px-3 py-3 text-right align-top">
                            <div class="flex flex-col items-end gap-0.5">
                                <span class="font-semibold tabular-nums text-foreground">
                                    {{ formatearPrecio(producto.precio_venta_producto) }}
                                </span>
                                <span class="text-[11px] tabular-nums text-muted-foreground">
                                    Anticipado {{ formatearPrecio(producto.costo_anticipado) }}
                                </span>
                            </div>
                        </td>

                        <td class="hidden px-3 py-3 align-top md:table-cell">
                            <div class="flex max-w-[190px] flex-wrap items-center gap-1">
                                <span v-for="talla in tallasDe(producto).slice(0, 3)" :key="talla"
                                    class="inline-flex min-w-6 items-center justify-center rounded border border-border bg-muted/60 px-1.5 py-0.5 text-[10px] font-semibold text-muted-foreground">
                                    {{ talla }}
                                </span>
                                <span v-if="tallasDe(producto).length > 3"
                                    class="inline-flex min-w-6 items-center justify-center rounded border border-border bg-muted/60 px-1.5 py-0.5 text-[10px] font-semibold text-muted-foreground"
                                    :title="tallasDe(producto).slice(3).join(', ')">
                                    +{{ tallasDe(producto).length - 3 }}
                                </span>
                                <span v-if="!tallasDe(producto).length" class="text-xs text-muted-foreground">—</span>
                            </div>
                        </td>

                        <td class="hidden px-3 py-3 align-top lg:table-cell">
                            <div class="flex max-w-[170px] flex-wrap items-center gap-1">
                                <span v-for="numeroOp in ordenesDe(producto).slice(0, 2)" :key="numeroOp"
                                    class="inline-flex items-center rounded border border-border bg-muted/60 px-1.5 py-0.5 text-[10px] font-semibold tabular-nums text-muted-foreground"
                                    :title="numeroOp">
                                    {{ numeroOp }}
                                </span>
                                <span v-if="ordenesDe(producto).length > 2"
                                    class="inline-flex items-center rounded border border-border bg-muted/60 px-1.5 py-0.5 text-[10px] font-semibold text-muted-foreground"
                                    :title="ordenesDe(producto).slice(2).join(', ')">
                                    +{{ ordenesDe(producto).length - 2 }}
                                </span>
                                <span v-if="!ordenesDe(producto).length" class="text-xs text-muted-foreground">—</span>
                            </div>
                        </td>

                        <td
                            class="sticky right-0 z-10 w-[104px] border-l border-border/60 bg-card px-2 py-2 align-middle shadow-[-8px_0_16px_-16px_rgba(0,0,0,0.35)] transition-colors group-hover:bg-muted/40">
                            <div class="flex items-center justify-end gap-1">
                                <button v-if="can(['update Producto'])" type="button"
                                    @click="abrirAccion('tallas', producto)" title="Tallas"
                                    :aria-label="`Ver tallas de ${producto.Codigo_interno || producto.Titulo_Shopify}`"
                                    :class="iconoAccion">
                                    <SquaresPlusIcon class="h-4 w-4" />
                                </button>
                                <button v-if="can(['update Producto'])" type="button"
                                    @click="abrirAccion('edit', producto)" title="Editar y asignar ficha"
                                    :aria-label="`Editar ${producto.Codigo_interno || producto.Titulo_Shopify}`"
                                    :class="iconoAccion">
                                    <PencilIcon class="h-4 w-4" />
                                </button>
                                <DropdownMenu v-if="can(['delete Producto'])">
                                    <DropdownMenuTrigger type="button" title="Acciones"
                                        :aria-label="`Acciones de ${producto.Codigo_interno || producto.Titulo_Shopify}`"
                                        :class="iconoAccion">
                                        <EllipsisVerticalIcon class="h-4 w-4" />
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end" class="w-56 p-1">
                                        <DropdownMenuItem @click="abrirAccion('delete', producto)" variant="destructive"
                                            class="flex cursor-pointer items-center gap-2 px-2.5 py-1.5 text-xs font-medium">
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

            <div v-else class="flex flex-col items-center justify-center gap-3 px-6 py-16 text-center">
                <div class="flex h-12 w-12 items-center justify-center rounded-full border border-border bg-muted/60">
                    <FunnelIcon v-if="hayBusqueda" class="h-5 w-5 text-muted-foreground" />
                    <CubeIcon v-else class="h-5 w-5 text-muted-foreground" />
                </div>
                <h3 class="text-sm font-semibold text-foreground">
                    {{ hayBusqueda ? 'Ningún producto sin ficha coincide' : 'Todos los productos tienen ficha técnica'
                    }}
                </h3>
                <p v-if="hayBusqueda" class="max-w-sm text-sm text-muted-foreground">
                    Ajusta el texto de búsqueda para ver más resultados.
                </p>
                <button v-if="hayBusqueda" type="button" @click="limpiar"
                    class="inline-flex items-center gap-1.5 rounded-md border border-border bg-card px-3 py-1.5 text-xs font-medium text-foreground shadow-sm transition hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                    <XMarkIcon class="h-3.5 w-3.5" />
                    Limpiar búsqueda
                </button>
            </div>
        </div>

        <DataPagination v-if="total > 0" :current-page="paginaActual" :last-page="paginas" :from="desde" :to="hasta"
            :total="total" :item-label="total === 1 ? 'producto' : 'productos'" @page="cambiarPagina" />

        <Edit v-if="can(['update Producto'])" :number-permissions="props.numberPermissions" :titulos="props.titulos"
            :show="data.editOpen" @close="data.editOpen = false" :Productoa="data.Producto" title="Producto"
            :losSelect="props.losSelect" />

        <Delete v-if="can(['delete Producto'])" :show="data.deleteOpen" @close="data.deleteOpen = false"
            :Productoa="data.Producto" title="Producto" />

        <DistribucionTallas v-if="can(['update Producto'])" :show="data.distribucionOpen"
            @close="data.distribucionOpen = false" :title="props.title" :productoId="data.distribucionProducto?.id"
            :productoLabel="data.distribucionProducto?.Codigo_interno"
            :items="distribucionesDe(data.distribucionProducto)"
            :ordenesTallas="ordenesDe(data.distribucionProducto)" />
    </section>
</template>

<style scoped>
@keyframes mat-page-in {
    from {
        opacity: 0;
        transform: translateY(3px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.mat-page {
    animation: mat-page-in 180ms cubic-bezier(0.22, 1, 0.36, 1);
}

.mat-scroll {
    scrollbar-width: thin;
    scrollbar-color: var(--border) transparent;
}

.mat-scroll::-webkit-scrollbar {
    width: 10px;
    height: 10px;
}

.mat-scroll::-webkit-scrollbar-track {
    background: transparent;
}

.mat-scroll::-webkit-scrollbar-thumb {
    background: var(--border);
    border: 3px solid transparent;
    border-radius: 9999px;
    background-clip: content-box;
}

@media (prefers-reduced-motion: reduce) {
    .mat-page {
        animation: none;
    }
}
</style>
