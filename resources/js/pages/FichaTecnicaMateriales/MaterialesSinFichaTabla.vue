<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import Delete from '@/pages/Material/Delete.vue';
import Edit from '@/pages/Material/Edit.vue';
import {
    ChevronDownIcon,
    ChevronUpDownIcon,
    ChevronUpIcon,
    CubeIcon,
    EllipsisVerticalIcon,
    FunnelIcon,
    MagnifyingGlassIcon,
    PencilIcon,
    TrashIcon,
    XMarkIcon,
} from '@heroicons/vue/24/solid';
import { reactive } from 'vue';
import { zilef_nuber_format } from '@/global.ts';
import { useTablaClient, type ColumnaTabla } from '@/composables/useTablaClient';

const props = defineProps({
    materiales: { type: Array as () => any[], default: () => [] },
    losSelect: { type: Object as () => any, default: () => ({}) },
    titulos: { type: Array as () => any[], default: () => [] },
    numberPermissions: Number,
    title: String,
});

const ordenesDe = (material: any): string[] => {
    const lista = material?.ordenes_con_material ?? [];
    return Array.isArray(lista) ? lista.map((numeroOp: any) => String(numeroOp)) : [];
};

const columnas: ColumnaTabla[] = [
    { key: 'codigo_material', label: 'Código', sortable: true, align: 'left', headerClass: 'min-w-[130px]' },
    { key: 'nombre_base', label: 'Material', sortable: true, align: 'left', headerClass: 'min-w-[240px]' },
    { key: 'unidad_inventario', label: 'Unidad', sortable: true, align: 'left', headerClass: 'w-[130px]' },
    {
        key: 'existencia_actual',
        label: 'Existencia',
        sortable: true,
        align: 'right',
        headerClass: 'w-[120px]',
        valor: (material: any) => Number(material.existencia_actual ?? 0),
    },
    {
        key: 'costo_actual',
        label: 'Costo actual',
        sortable: true,
        align: 'right',
        headerClass: 'w-[140px]',
        valor: (material: any) => Number(material.costo_actual ?? 0),
    },
    { key: 'estado', label: 'Estado', sortable: true, align: 'left', headerClass: 'w-[110px]' },
    {
        key: 'ordenes_con_material',
        label: 'OPs',
        sortable: true,
        align: 'left',
        headerClass: 'hidden min-w-[150px] lg:table-cell',
        valor: (material: any) => ordenesDe(material).length,
    },
];

const tabla = useTablaClient(() => props.materiales ?? [], {
    columnas,
    busquedaPor: ['codigo_material', 'nombre_base', 'categoria', 'unidad_inventario', 'proveedor_habitual'],
    porPagina: 10,
    orden: { key: 'codigo_material', dir: 'asc' },
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
    Material: null as any,
    editOpen: false,
    deleteOpen: false,
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

const formatearCantidad = (valor: any): string => {
    const numero = Number(valor ?? 0);
    return Number.isFinite(numero) ? zilef_nuber_format(numero, decimalesDe(numero), false) : '—';
};

const decimalesDe = (valor: any): number =>
    Number.isFinite(Number(valor)) && Math.round(Number(valor) * 100) % 100 !== 0 ? 2 : 0;

const selectClass =
    'h-9 rounded-md border border-input bg-background px-2 text-sm text-foreground shadow-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30';

const abrirAccion = (accion: string, material: any) => {
    data.Material = material;

    if (accion === 'edit') data.editOpen = true;
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
                    <h2 class="text-sm font-semibold text-foreground">Materiales con proceso sin ficha técnica</h2>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        <span class="tabular-nums font-medium text-foreground">{{ totalRegistros }}</span>
                        {{ totalRegistros === 1 ? 'material espera' : 'materiales esperan' }} su ficha. Se la asigna
                        desde Editar, en la sección «Ficha técnica de materiales».
                    </p>
                </div>

                <p v-if="hayBusqueda" class="text-xs tabular-nums text-muted-foreground">
                    <span class="font-medium text-foreground">{{ total }}</span> de {{ totalRegistros }} coinciden
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <div class="relative min-w-[220px] flex-1">
                    <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <input
                        v-model="busqueda"
                        type="text"
                        aria-label="Buscar materiales sin ficha"
                        placeholder="Buscar por código, nombre, categoría o proveedor…"
                        class="h-9 w-full rounded-md border border-input bg-background pl-9 pr-9 text-sm text-foreground shadow-sm placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30"
                    />
                    <button
                        v-if="busqueda"
                        type="button"
                        @click="limpiar"
                        aria-label="Limpiar búsqueda de materiales"
                        class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-muted-foreground transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        <XMarkIcon class="h-3.5 w-3.5" />
                    </button>
                </div>

                <select v-model.number="porPagina" aria-label="Materiales por página" :class="selectClass">
                    <option v-for="n in [10, 25, 50, 100]" :key="n" :value="n">{{ n }} / página</option>
                </select>
            </div>
        </div>

        <div class="mat-scroll max-h-[68vh] overflow-auto overscroll-contain">
            <table v-if="total > 0" class="w-full min-w-[1140px] border-collapse text-sm">
                <thead>
                    <tr>
                        <th
                            v-for="col in columnas"
                            :key="col.key"
                            scope="col"
                            :aria-sort="col.sortable ? ariaSort(col.key) : undefined"
                            :class="[
                                'bg-muted px-3 py-2.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground',
                                col.align === 'right' ? 'text-right' : 'text-left',
                                col.headerClass,
                            ]"
                        >
                            <button
                                v-if="col.sortable"
                                type="button"
                                @click="ordenarPor(col.key)"
                                :class="[
                                    'group/sort inline-flex items-center gap-1 rounded-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                                    col.align === 'right' ? 'w-full justify-end' : '',
                                ]"
                            >
                                <span>{{ col.label }}</span>
                                <span class="text-muted-foreground/60 transition-colors group-hover/sort:text-foreground">
                                    <ChevronUpIcon v-if="indicador(col.key) === 'asc'" class="h-3 w-3" />
                                    <ChevronDownIcon v-else-if="indicador(col.key) === 'desc'" class="h-3 w-3" />
                                    <ChevronUpDownIcon v-else class="h-3 w-3 opacity-0 transition-opacity group-hover/sort:opacity-100" />
                                </span>
                            </button>
                            <span v-else>{{ col.label }}</span>
                        </th>

                        <th
                            scope="col"
                            class="sticky right-0 z-30 w-[92px] border-l border-border bg-muted px-2 py-2.5 text-right text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground"
                        >
                            Acciones
                        </th>
                    </tr>
                </thead>

                <tbody :key="paginaActual" class="mat-page">
                    <tr
                        v-for="material in visibles"
                        :key="material.id"
                        class="group border-b border-border/60 transition-colors last:border-0 hover:bg-muted/40"
                    >
                        <td class="px-3 py-3 align-top">
                            <span class="font-semibold tabular-nums tracking-tight text-foreground">
                                {{ texto(material.codigo_material) }}
                            </span>
                        </td>

                        <td class="px-3 py-3 align-top">
                            <div class="flex max-w-[300px] flex-col gap-0.5">
                                <span class="truncate font-medium text-foreground" :title="material.nombre_base">
                                    {{ texto(material.nombre_base) }}
                                </span>
                                <span class="truncate text-[11px] text-muted-foreground" :title="material.categoria">
                                    {{ texto(material.categoria, 'Sin categoría') }}
                                </span>
                            </div>
                        </td>

                        <td class="px-3 py-3 align-top">
                            <span class="text-sm text-foreground">{{ texto(material.unidad_inventario) }}</span>
                        </td>

                        <td class="px-3 py-3 text-right align-top">
                            <span class="font-semibold tabular-nums text-foreground">
                                {{ formatearCantidad(material.existencia_actual) }}
                            </span>
                        </td>

                        <td class="px-3 py-3 text-right align-top">
                            <div class="flex flex-col items-end gap-0.5">
                                <span class="font-semibold tabular-nums text-foreground">
                                    {{ formatearPrecio(material.costo_actual, decimalesDe(material.costo_actual)) }}
                                </span>
                                <span v-if="!Number(material.costo_actual)" class="text-[11px] text-amber-600 dark:text-amber-400">
                                    sin precio registrado
                                </span>
                            </div>
                        </td>

                        <td class="px-3 py-3 align-top">
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[11px] font-medium"
                                :class="estadoEstilo(material.estado).badge"
                            >
                                <span class="h-1.5 w-1.5 rounded-full" :class="estadoEstilo(material.estado).dot"></span>
                                {{ estadoNormalizado(material.estado) }}
                            </span>
                        </td>

                        <td class="hidden px-3 py-3 align-top lg:table-cell">
                            <div class="flex max-w-[190px] flex-wrap items-center gap-1">
                                <span
                                    v-for="numeroOp in ordenesDe(material).slice(0, 2)"
                                    :key="numeroOp"
                                    class="inline-flex items-center rounded border border-border bg-muted/60 px-1.5 py-0.5 text-[10px] font-semibold tabular-nums text-muted-foreground"
                                    :title="material.ficha_bloqueo_detalle"
                                >
                                    {{ numeroOp }}
                                </span>
                                <span
                                    v-if="ordenesDe(material).length > 2"
                                    class="inline-flex items-center rounded border border-border bg-muted/60 px-1.5 py-0.5 text-[10px] font-semibold text-muted-foreground"
                                    :title="ordenesDe(material).slice(2).join(', ')"
                                >
                                    +{{ ordenesDe(material).length - 2 }}
                                </span>
                                <span v-if="!ordenesDe(material).length" class="text-xs text-muted-foreground">—</span>
                            </div>
                        </td>

                        <td
                            class="sticky right-0 z-10 w-[92px] border-l border-border/60 bg-card px-2 py-2 align-middle shadow-[-8px_0_16px_-16px_rgba(0,0,0,0.35)] transition-colors group-hover:bg-muted/40"
                        >
                            <div class="flex items-center justify-end gap-1">
                                <button
                                    v-if="can(['update Material'])"
                                    type="button"
                                    @click="abrirAccion('edit', material)"
                                    title="Editar y asignar ficha"
                                    :aria-label="`Editar ${material.codigo_material || material.nombre_base}`"
                                    :class="iconoAccion"
                                >
                                    <PencilIcon class="h-4 w-4" />
                                </button>
                                <DropdownMenu v-if="can(['delete Material'])">
                                    <DropdownMenuTrigger
                                        type="button"
                                        title="Acciones"
                                        :aria-label="`Acciones de ${material.codigo_material || material.nombre_base}`"
                                        :class="iconoAccion"
                                    >
                                        <EllipsisVerticalIcon class="h-4 w-4" />
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end" class="w-56 p-1">
                                        <DropdownMenuItem
                                            @click="abrirAccion('delete', material)"
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

            <div v-else class="flex flex-col items-center justify-center gap-3 px-6 py-16 text-center">
                <div class="flex h-12 w-12 items-center justify-center rounded-full border border-border bg-muted/60">
                    <FunnelIcon v-if="hayBusqueda" class="h-5 w-5 text-muted-foreground" />
                    <CubeIcon v-else class="h-5 w-5 text-muted-foreground" />
                </div>
                <h3 class="text-sm font-semibold text-foreground">
                    {{ hayBusqueda ? 'Ningún material sin ficha coincide' : 'Todos los materiales con proceso tienen ficha técnica' }}
                </h3>
                <p v-if="hayBusqueda" class="max-w-sm text-sm text-muted-foreground">
                    Ajusta el texto de búsqueda para ver más resultados.
                </p>
                <button
                    v-if="hayBusqueda"
                    type="button"
                    @click="limpiar"
                    class="inline-flex items-center gap-1.5 rounded-md border border-border bg-card px-3 py-1.5 text-xs font-medium text-foreground shadow-sm transition hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                >
                    <XMarkIcon class="h-3.5 w-3.5" />
                    Limpiar búsqueda
                </button>
            </div>
        </div>

        <DataPagination
            v-if="total > 0"
            :current-page="paginaActual"
            :last-page="paginas"
            :from="desde"
            :to="hasta"
            :total="total"
            :item-label="total === 1 ? 'material' : 'materiales'"
            @page="cambiarPagina"
        />

        <Edit
            v-if="can(['update Material'])"
            :show="data.editOpen"
            @close="data.editOpen = false"
            :Materiala="data.Material"
            title="Material"
            :losSelect="props.losSelect"
            :titulos="props.titulos"
        />

        <Delete
            v-if="can(['delete Material'])"
            :show="data.deleteOpen"
            @close="data.deleteOpen = false"
            :Materiala="data.Material"
            title="Material"
        />
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
