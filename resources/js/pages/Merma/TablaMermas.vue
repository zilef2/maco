<script setup lang="ts">
import { ChevronDownIcon, ChevronUpDownIcon, ChevronUpIcon, EyeIcon, ClipboardDocumentListIcon, FunnelIcon, MagnifyingGlassIcon, RectangleStackIcon, XMarkIcon } from '@heroicons/vue/24/solid';
import DataPagination from '@/components/DataPagination.vue';
import { useTablaClient, type ColumnaTabla } from '@/composables/useTablaClient';
import { formatDate, zilef_nuber_format } from '@/global.ts';

const props = defineProps<{
    filas: any[];
    totales: any;
    hayBusqueda?: boolean;
    hayFiltros?: boolean;
    hayRangoFechas?: boolean;
    nombreMes?: (mes: string) => string;
    dinero?: (valor: any) => string;
    piezas?: (valor: any) => string;
    porcentaje?: (merma: any) => string;
    fecha?: (valor: any) => string;
    texto?: (valor: any, fallback?: string) => string;
}>();

const emit = defineEmits(['ver-detalle', 'abrir-orden']);

const columnas: ColumnaTabla[] = [
    { key: 'numero_op', label: 'OP', sortable: true, align: 'left', headerClass: 'min-w-[120px]' },
    { key: 'objetivo', label: 'Producto / material', sortable: true, align: 'left', headerClass: 'min-w-[260px]' },
    { key: 'destino_nombre', label: 'Taller / Empresa externa', sortable: true, align: 'left', headerClass: 'min-w-[180px]' },
    { key: 'talla', label: 'Talla', sortable: true, align: 'center', headerClass: 'w-[90px]' },
    { key: 'cantidad', label: 'Piezas perdidas', sortable: true, align: 'center', headerClass: 'w-[140px]' },
    { key: 'porcentaje', label: 'Merma / esperado', sortable: true, align: 'right', valor: (m: any) => (Number(m.esperada) > 0 ? (Number(m.cantidad) / Number(m.esperada)) * 100 : null), headerClass: 'w-[120px]' },
    { key: 'costo_unitario', label: 'Costo unitario', sortable: true, align: 'right', headerClass: 'w-[140px]' },
    { key: 'costo_total_perdida', label: 'Valor perdido', sortable: true, align: 'right', headerClass: 'w-[150px]' },
    { key: 'fecha_registro', label: 'Fecha de recepción', sortable: true, align: 'right', headerClass: 'w-[160px]' },
    { key: 'responsable', label: 'Registrado por', sortable: true, align: 'left', headerClass: 'min-w-[160px]' },
];

const tabla = useTablaClient(() => props.filas ?? [], {
    columnas,
    busquedaPor: ['numero_op', 'objetivo', 'talla', 'responsable', 'estado_op'],
    porPagina: 10,
    orden: { key: 'fecha_registro', dir: 'desc' },
});

const {
    busqueda,
    porPagina,
    paginaActual,
    paginas,
    desde: desdeFila,
    hasta,
    visibles,
    filtradas: filasDeTabla,
    total,
    hayBusquedaLocal,
    cambiarPagina,
    ordenarPor,
    indicador,
    ariaSort,
    limpiar,
} = tabla;

const hayBusqueda = props.hayBusqueda ?? hayBusquedaLocal;

const texto = props.texto ?? ((valor: any, fallback = '—'): string => { const resultado = String(valor ?? '').trim(); return resultado !== '' ? resultado : fallback; });
const dinero = props.dinero ?? (zilef_nuber_format as any);
const piezas = props.piezas ?? ((valor: any): string => (valor === null || valor === undefined ? '—' : `${zilef_nuber_format(valor, 0, false)}`));




const porcentaje = (merma: any): string => {
    const esperada = Number(merma?.esperada) || 0;
    if (esperada <= 0) return '—';

    const resultado = ((Number(merma.cantidad) || 0) / esperada) * 100;

    return `${Number(resultado.toFixed(1))}%`;
};



const fecha = props.fecha ?? ((valor: any): string => (valor ? formatDate(valor, 'fecha') : '—'));

const verDetalle = (merma: any) => emit('ver-detalle', merma);
const abrirOrden = (merma: any) => emit('abrir-orden', merma);

const selectClass = 'h-9 rounded-md border border-input bg-background px-2 text-sm text-foreground shadow-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30';

</script>

<template>
    <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
        <div class="flex flex-wrap items-center gap-2 border-b border-border p-3">
            <div class="relative min-w-[220px] flex-1">
                <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <input
                    v-model="busqueda"
                    type="text"
                    aria-label="Buscar merma"
                    placeholder="Buscar por OP, producto, material, talla o responsable…"
                    class="h-9 w-full rounded-md border border-input bg-background pl-9 pr-9 text-sm text-foreground shadow-sm placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30"
                />
                <button
                    v-if="busqueda"
                    type="button"
                    @click="limpiar"
                    aria-label="Limpiar búsqueda de mermas"
                    class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-muted-foreground transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                >
                    <XMarkIcon class="h-3.5 w-3.5" />
                </button>
            </div>

            <select v-model.number="porPagina" aria-label="Mermas por página" :class="selectClass">
                <option value="10">10 / página</option>
                <option value="25">25 / página</option>
                <option value="50">50 / página</option>
                <option value="100">100 / página</option>
            </select>

            <button
                v-if="hayBusqueda"
                type="button"
                @click="limpiar"
                class="inline-flex h-9 items-center gap-1.5 rounded-md border border-border bg-card px-3 text-xs font-medium text-foreground shadow-sm transition hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
            >
                <XMarkIcon class="h-3.5 w-3.5" />
                Limpiar
            </button>
        </div>

        <div class="merma-scroll max-h-[68vh] overflow-auto overscroll-contain">
            <table class="w-full min-w-[1600px] border-collapse text-sm">
                <thead>
                    <tr>
                        <th scope="col" :aria-sort="ariaSort('numero_op')" class="bg-muted px-3 py-2.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground text-left min-w-[120px]">
                            <button type="button" @click="ordenarPor('numero_op')" class="group/sort inline-flex items-center gap-1 rounded-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                                <span>OP</span>
                                <span class="text-muted-foreground/60 transition-colors group-hover/sort:text-foreground">
                                    <ChevronUpIcon v-if="indicador('numero_op') === 'asc'" class="h-3 w-3" />
                                    <ChevronDownIcon v-else-if="indicador('numero_op') === 'desc'" class="h-3 w-3" />
                                    <ChevronUpDownIcon v-else class="h-3 w-3 opacity-0 transition-opacity group-hover/sort:opacity-100" />
                                </span>
                            </button>
                        </th>
                        <th scope="col" :aria-sort="ariaSort('objetivo')" class="bg-muted px-3 py-2.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground text-left min-w-[260px]">
                            <button type="button" @click="ordenarPor('objetivo')" class="group/sort inline-flex items-center gap-1 rounded-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                                <span>Producto / material</span>
                                <span class="text-muted-foreground/60 transition-colors group-hover/sort:text-foreground">
                                    <ChevronUpIcon v-if="indicador('objetivo') === 'asc'" class="h-3 w-3" />
                                    <ChevronDownIcon v-else-if="indicador('objetivo') === 'desc'" class="h-3 w-3" />
                                    <ChevronUpDownIcon v-else class="h-3 w-3 opacity-0 transition-opacity group-hover/sort:opacity-100" />
                                </span>
                            </button>
                        </th>
                        <th scope="col" :aria-sort="ariaSort('destino_nombre')" class="bg-muted px-3 py-2.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground text-left min-w-[180px]">
                            <button type="button" @click="ordenarPor('destino_nombre')" class="group/sort inline-flex items-center gap-1 rounded-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                                <span>Taller / Empresa externa</span>
                                <span class="text-muted-foreground/60 transition-colors group-hover/sort:text-foreground">
                                    <ChevronUpIcon v-if="indicador('destino_nombre') === 'asc'" class="h-3 w-3" />
                                    <ChevronDownIcon v-else-if="indicador('destino_nombre') === 'desc'" class="h-3 w-3" />
                                    <ChevronUpDownIcon v-else class="h-3 w-3 opacity-0 transition-opacity group-hover/sort:opacity-100" />
                                </span>
                            </button>
                        </th>
                        <th scope="col" :aria-sort="ariaSort('talla')" class="bg-muted px-3 py-2.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground text-center w-[90px]">
                            <button type="button" @click="ordenarPor('talla')" class="group/sort inline-flex items-center gap-1 rounded-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring w-full justify-center">
                                <span>Talla</span>
                                <span class="text-muted-foreground/60 transition-colors group-hover/sort:text-foreground">
                                    <ChevronUpIcon v-if="indicador('talla') === 'asc'" class="h-3 w-3" />
                                    <ChevronDownIcon v-else-if="indicador('talla') === 'desc'" class="h-3 w-3" />
                                    <ChevronUpDownIcon v-else class="h-3 w-3 opacity-0 transition-opacity group-hover/sort:opacity-100" />
                                </span>
                            </button>
                        </th>
                        <th scope="col" :aria-sort="ariaSort('cantidad')" class="bg-muted px-3 py-2.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground text-center w-[140px]">
                            <button type="button" @click="ordenarPor('cantidad')" class="group/sort inline-flex items-center gap-1 rounded-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring w-full justify-center">
                                <span>Piezas perdidas</span>
                                <span class="text-muted-foreground/60 transition-colors group-hover/sort:text-foreground">
                                    <ChevronUpIcon v-if="indicador('cantidad') === 'asc'" class="h-3 w-3" />
                                    <ChevronDownIcon v-else-if="indicador('cantidad') === 'desc'" class="h-3 w-3" />
                                    <ChevronUpDownIcon v-else class="h-3 w-3 opacity-0 transition-opacity group-hover/sort:opacity-100" />
                                </span>
                            </button>
                        </th>
                        <th scope="col" :aria-sort="ariaSort('porcentaje')" class="bg-muted px-3 py-2.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground text-right w-[120px]">
                            <button type="button" @click="ordenarPor('porcentaje')" class="group/sort inline-flex items-center gap-1 rounded-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring w-full justify-end">
                                <span>Merma / esperado</span>
                                <span class="text-muted-foreground/60 transition-colors group-hover/sort:text-foreground">
                                    <ChevronUpIcon v-if="indicador('porcentaje') === 'asc'" class="h-3 w-3" />
                                    <ChevronDownIcon v-else-if="indicador('porcentaje') === 'desc'" class="h-3 w-3" />
                                    <ChevronUpDownIcon v-else class="h-3 w-3 opacity-0 transition-opacity group-hover/sort:opacity-100" />
                                </span>
                            </button>
                        </th>
                        <th scope="col" :aria-sort="ariaSort('costo_unitario')" class="bg-muted px-3 py-2.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground text-right w-[140px]">
                            <button type="button" @click="ordenarPor('costo_unitario')" class="group/sort inline-flex items-center gap-1 rounded-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring w-full justify-end">
                                <span>Costo unitario</span>
                                <span class="text-muted-foreground/60 transition-colors group-hover/sort:text-foreground">
                                    <ChevronUpIcon v-if="indicador('costo_unitario') === 'asc'" class="h-3 w-3" />
                                    <ChevronDownIcon v-else-if="indicador('costo_unitario') === 'desc'" class="h-3 w-3" />
                                    <ChevronUpDownIcon v-else class="h-3 w-3 opacity-0 transition-opacity group-hover/sort:opacity-100" />
                                </span>
                            </button>
                        </th>
                        <th scope="col" :aria-sort="ariaSort('costo_total_perdida')" class="bg-muted px-3 py-2.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground text-right w-[150px]">
                            <button type="button" @click="ordenarPor('costo_total_perdida')" class="group/sort inline-flex items-center gap-1 rounded-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring w-full justify-end">
                                <span>Valor perdido</span>
                                <span class="text-muted-foreground/60 transition-colors group-hover/sort:text-foreground">
                                    <ChevronUpIcon v-if="indicador('costo_total_perdida') === 'asc'" class="h-3 w-3" />
                                    <ChevronDownIcon v-else-if="indicador('costo_total_perdida') === 'desc'" class="h-3 w-3" />
                                    <ChevronUpDownIcon v-else class="h-3 w-3 opacity-0 transition-opacity group-hover/sort:opacity-100" />
                                </span>
                            </button>
                        </th>
                        <th scope="col" :aria-sort="ariaSort('fecha_registro')" class="bg-muted px-3 py-2.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground text-right w-[160px]">
                            <button type="button" @click="ordenarPor('fecha_registro')" class="group/sort inline-flex items-center gap-1 rounded-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring w-full justify-end">
                                <span>Fecha de recepción</span>
                                <span class="text-muted-foreground/60 transition-colors group-hover/sort:text-foreground">
                                    <ChevronUpIcon v-if="indicador('fecha_registro') === 'asc'" class="h-3 w-3" />
                                    <ChevronDownIcon v-else-if="indicador('fecha_registro') === 'desc'" class="h-3 w-3" />
                                    <ChevronUpDownIcon v-else class="h-3 w-3 opacity-0 transition-opacity group-hover/sort:opacity-100" />
                                </span>
                            </button>
                        </th>
                        <th scope="col" :aria-sort="ariaSort('responsable')" class="bg-muted px-3 py-2.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground text-left min-w-[160px]">
                            <button type="button" @click="ordenarPor('responsable')" class="group/sort inline-flex items-center gap-1 rounded-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                                <span>Registrado por</span>
                                <span class="text-muted-foreground/60 transition-colors group-hover/sort:text-foreground">
                                    <ChevronUpIcon v-if="indicador('responsable') === 'asc'" class="h-3 w-3" />
                                    <ChevronDownIcon v-else-if="indicador('responsable') === 'desc'" class="h-3 w-3" />
                                    <ChevronUpDownIcon v-else class="h-3 w-3 opacity-0 transition-opacity group-hover/sort:opacity-100" />
                                </span>
                            </button>
                        </th>
                        <th scope="col" class="sticky right-0 z-30 w-[88px] border-l border-border bg-muted px-2 py-2.5 text-right text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground">
                            Acciones
                        </th>
                    </tr>
                </thead>

                <tbody v-if="total === 0">
                    <tr>
                        <td :colspan="columnas.length + 1" class="px-6 py-16">
                            <div class="mx-auto flex max-w-sm flex-col items-center text-center">
                                <div class="flex h-12 w-12 items-center justify-center rounded-full border border-border bg-muted/60">
                                    <FunnelIcon v-if="hayBusqueda || props.hayFiltros" class="h-5 w-5 text-muted-foreground" />
                                    <RectangleStackIcon v-else class="h-5 w-5 text-muted-foreground" />
                                </div>
                                <h3 class="mt-4 text-sm font-semibold text-foreground">
                                    {{ hayBusqueda || props.hayFiltros ? 'Ninguna merma coincide' : 'Aún no hay mermas registradas' }}
                                </h3>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{
                                        hayBusqueda || props.hayFiltros
                                            ? 'Ajusta el periodo, el taller, la empresa o la búsqueda para ver otras mermas.'
                                            : 'Las mermas aparecen cuando una OP en «Recibiendo» registra pares en «No recuperable».'
                                    }}
                                </p>
                                <button
                                    v-if="hayBusqueda || props.hayFiltros"
                                    type="button"
                                    @click="$emit('limpiar-filtros')"
                                    class="mt-4 inline-flex items-center gap-1.5 rounded-md border border-border bg-card px-3 py-1.5 text-xs font-medium text-foreground shadow-sm transition hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                >
                                    <XMarkIcon class="h-3.5 w-3.5" />
                                    Limpiar filtros
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>

                <tbody v-else :key="paginaActual" class="merma-page">
                    <tr v-if="visibles.length > 0" class="group border-b border-border/60 transition-colors hover:bg-muted/40">
                        <td class="px-3 py-3 align-top">
                            <div class="flex flex-col gap-0.5">
                                <span class="font-medium text-foreground">{{ texto(visibles[0].numero_op) }}</span>
                                <span class="text-[11px] text-muted-foreground">{{ texto(visibles[0].estado_op, 'Sin estado') }}</span>
                            </div>
                        </td>
                        <td class="px-3 py-3 align-top">
                            <div class="flex max-w-[320px] flex-col gap-0.5">
                                <span class="truncate font-medium text-foreground" :title="visibles[0].objetivo">{{ texto(visibles[0].objetivo) }}</span>
                                <span class="text-[11px] text-muted-foreground">{{ texto(visibles[0].objetivo_tipo) }}</span>
                            </div>
                        </td>
                        <td class="px-3 py-3 align-top">
                            <div class="flex max-w-[220px] flex-col gap-0.5">
                                <span class="truncate font-medium text-foreground" :title="texto(visibles[0].destino_nombre)">{{ texto(visibles[0].destino_nombre) }}</span>
                                <span v-if="visibles[0].destino_tipo" class="text-[11px] text-muted-foreground">{{ visibles[0].destino_tipo }}</span>
                            </div>
                        </td>
                        <td class="px-3 py-3 text-center align-top">
                            <span class="inline-flex min-w-[44px] justify-center rounded-md border border-border bg-muted/60 px-2 py-1 text-xs font-medium tabular-nums text-foreground">{{ texto(visibles[0].talla) }}</span>
                        </td>
                        <td class="px-3 py-3 text-center align-top">
                            <button type="button" @click="verDetalle(visibles[0])" title="Ver la recepción que originó esta merma" :aria-label="`Ver detalle de la merma ${visibles[0].numero_op} talla ${visibles[0].talla}`" class="inline-flex items-center gap-1.5 rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-semibold tabular-nums text-red-700 shadow-sm transition hover:bg-red-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300">
                                {{ piezas(visibles[0].cantidad) }}
                                <span class="text-[11px] font-normal">{{ Number(visibles[0].cantidad) === 1 ? 'par' : 'pares' }}</span>
                            </button>
                        </td>
                        <td class="px-3 py-3 text-right align-top tabular-nums text-muted-foreground">{{ porcentaje(visibles[0]) }}
                        </td>

                        <td class="px-3 py-3 text-right align-top tabular-nums text-muted-foreground">{{ dinero(visibles[0].costo_unitario) }}</td>
                        <td class="px-3 py-3 text-right align-top"><span class="font-semibold tabular-nums text-foreground">{{ dinero(visibles[0].costo_total_perdida) }}</span></td>
                        <td class="px-3 py-3 text-right align-top text-xs tabular-nums text-muted-foreground">{{ fecha(visibles[0].fecha_registro) }}</td>
                        <td class="px-3 py-3 align-top text-sm text-foreground">{{ texto(visibles[0].responsable) }}</td>
                        <td class="sticky right-0 z-10 w-[88px] border-l border-border/60 bg-card px-2 py-2 align-middle shadow-[-8px_0_16px_-16px_rgba(0,0,0,0.35)] transition-colors group-hover:bg-muted/40">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" @click="verDetalle(visibles[0])" title="Ver detalle" :aria-label="`Ver detalle de la merma de ${visibles[0].numero_op}`" class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"><EyeIcon class="h-4 w-4" /></button>
                                <button type="button" @click="abrirOrden(visibles[0])" title="Ir a la OP" :aria-label="`Ir a la orden ${visibles[0].numero_op}`" class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"><ClipboardDocumentListIcon class="h-4 w-4" /></button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="visibles.length > 1" class="group border-b border-border/60 transition-colors hover:bg-muted/40">
                        <td class="px-3 py-3 align-top">
                            <div class="flex flex-col gap-0.5">
                                <span class="font-medium text-foreground">{{ texto(visibles[1].numero_op) }}</span>
                                <span class="text-[11px] text-muted-foreground">{{ texto(visibles[1].estado_op, 'Sin estado') }}</span>
                            </div>
                        </td>
                        <td class="px-3 py-3 align-top">
                            <div class="flex max-w-[320px] flex-col gap-0.5">
                                <span class="truncate font-medium text-foreground" :title="visibles[1].objetivo">{{ texto(visibles[1].objetivo) }}</span>
                                <span class="text-[11px] text-muted-foreground">{{ texto(visibles[1].objetivo_tipo) }}</span>
                            </div>
                        </td>
                        <td class="px-3 py-3 align-top">
                            <div class="flex max-w-[220px] flex-col gap-0.5">
                                <span class="truncate font-medium text-foreground" :title="texto(visibles[1].destino_nombre)">{{ texto(visibles[1].destino_nombre) }}</span>
                                <span v-if="visibles[1].destino_tipo" class="text-[11px] text-muted-foreground">{{ visibles[1].destino_tipo }}</span>
                            </div>
                        </td>
                        <td class="px-3 py-3 text-center align-top"><span class="inline-flex min-w-[44px] justify-center rounded-md border border-border bg-muted/60 px-2 py-1 text-xs font-medium tabular-nums text-foreground">{{ texto(visibles[1].talla) }}</span></td>
                        <td class="px-3 py-3 text-center align-top">
                            <button type="button" @click="verDetalle(visibles[1])" title="Ver la recepción que originó esta merma" :aria-label="`Ver detalle de la merma ${visibles[1].numero_op} talla ${visibles[1].talla}`" class="inline-flex items-center gap-1.5 rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-semibold tabular-nums text-red-700 shadow-sm transition hover:bg-red-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300">
                                {{ piezas(visibles[1].cantidad) }}
                                <span class="text-[11px] font-normal">{{ Number(visibles[1].cantidad) === 1 ? 'par' : 'pares' }}</span>
                            </button>
                        </td>
                        <td class="px-3 py-3 text-right align-top tabular-nums text-muted-foreground">{{ porcentaje(visibles[1]) }}</td>
                        <td class="px-3 py-3 text-right align-top tabular-nums text-muted-foreground">{{ dinero(visibles[1].costo_unitario) }}</td>
                        <td class="px-3 py-3 text-right align-top"><span class="font-semibold tabular-nums text-foreground">{{ dinero(visibles[1].costo_total_perdida) }}</span></td>
                        <td class="px-3 py-3 text-right align-top text-xs tabular-nums text-muted-foreground">{{ fecha(visibles[1].fecha_registro) }}</td>
                        <td class="px-3 py-3 align-top text-sm text-foreground">{{ texto(visibles[1].responsable) }}</td>
                        <td class="sticky right-0 z-10 w-[88px] border-l border-border/60 bg-card px-2 py-2 align-middle shadow-[-8px_0_16px_-16px_rgba(0,0,0,0.35)] transition-colors group-hover:bg-muted/40">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" @click="verDetalle(visibles[1])" title="Ver detalle" :aria-label="`Ver detalle de la merma de ${visibles[1].numero_op}`" class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"><EyeIcon class="h-4 w-4" /></button>
                                <button type="button" @click="abrirOrden(visibles[1])" title="Ir a la OP" :aria-label="`Ir a la orden ${visibles[1].numero_op}`" class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"><ClipboardDocumentListIcon class="h-4 w-4" /></button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="visibles.length > 2" class="group border-b border-border/60 transition-colors hover:bg-muted/40">
                        <td class="px-3 py-3 align-top">
                            <div class="flex flex-col gap-0.5">
                                <span class="font-medium text-foreground">{{ texto(visibles[2].numero_op) }}</span>
                                <span class="text-[11px] text-muted-foreground">{{ texto(visibles[2].estado_op, 'Sin estado') }}</span>
                            </div>
                        </td>
                        <td class="px-3 py-3 align-top">
                            <div class="flex max-w-[320px] flex-col gap-0.5">
                                <span class="truncate font-medium text-foreground" :title="visibles[2].objetivo">{{ texto(visibles[2].objetivo) }}</span>
                                <span class="text-[11px] text-muted-foreground">{{ texto(visibles[2].objetivo_tipo) }}</span>
                            </div>
                        </td>
                        <td class="px-3 py-3 align-top">
                            <div class="flex max-w-[220px] flex-col gap-0.5">
                                <span class="truncate font-medium text-foreground" :title="texto(visibles[2].destino_nombre)">{{ texto(visibles[2].destino_nombre) }}</span>
                                <span v-if="visibles[2].destino_tipo" class="text-[11px] text-muted-foreground">{{ visibles[2].destino_tipo }}</span>
                            </div>
                        </td>
                        <td class="px-3 py-3 text-center align-top"><span class="inline-flex min-w-[44px] justify-center rounded-md border border-border bg-muted/60 px-2 py-1 text-xs font-medium tabular-nums text-foreground">{{ texto(visibles[2].talla) }}</span></td>
                        <td class="px-3 py-3 text-center align-top">
                            <button type="button" @click="verDetalle(visibles[2])" title="Ver la recepción que originó esta merma" :aria-label="`Ver detalle de la merma ${visibles[2].numero_op} talla ${visibles[2].talla}`" class="inline-flex items-center gap-1.5 rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-semibold tabular-nums text-red-700 shadow-sm transition hover:bg-red-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300">
                                {{ piezas(visibles[2].cantidad) }}
                                <span class="text-[11px] font-normal">{{ Number(visibles[2].cantidad) === 1 ? 'par' : 'pares' }}</span>
                            </button>
                        </td>
                        <td class="px-3 py-3 text-right align-top tabular-nums text-muted-foreground">{{ porcentaje(visibles[2]) }}</td>
                        <td class="px-3 py-3 text-right align-top tabular-nums text-muted-foreground">{{ dinero(visibles[2].costo_unitario) }}</td>
                        <td class="px-3 py-3 text-right align-top"><span class="font-semibold tabular-nums text-foreground">{{ dinero(visibles[2].costo_total_perdida) }}</span></td>
                        <td class="px-3 py-3 text-right align-top text-xs tabular-nums text-muted-foreground">{{ fecha(visibles[2].fecha_registro) }}</td>
                        <td class="px-3 py-3 align-top text-sm text-foreground">{{ texto(visibles[2].responsable) }}</td>
                        <td class="sticky right-0 z-10 w-[88px] border-l border-border/60 bg-card px-2 py-2 align-middle shadow-[-8px_0_16px_-16px_rgba(0,0,0,0.35)] transition-colors group-hover:bg-muted/40">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" @click="verDetalle(visibles[2])" title="Ver detalle" :aria-label="`Ver detalle de la merma de ${visibles[2].numero_op}`" class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"><EyeIcon class="h-4 w-4" /></button>
                                <button type="button" @click="abrirOrden(visibles[2])" title="Ir a la OP" :aria-label="`Ir a la orden ${visibles[2].numero_op}`" class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"><ClipboardDocumentListIcon class="h-4 w-4" /></button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="visibles.length > 3" class="group border-b border-border/60 transition-colors hover:bg-muted/40">
                        <td class="px-3 py-3 align-top">
                            <div class="flex flex-col gap-0.5">
                                <span class="font-medium text-foreground">{{ texto(visibles[3].numero_op) }}</span>
                                <span class="text-[11px] text-muted-foreground">{{ texto(visibles[3].estado_op, 'Sin estado') }}</span>
                            </div>
                        </td>
                        <td class="px-3 py-3 align-top">
                            <div class="flex max-w-[320px] flex-col gap-0.5">
                                <span class="truncate font-medium text-foreground" :title="visibles[3].objetivo">{{ texto(visibles[3].objetivo) }}</span>
                                <span class="text-[11px] text-muted-foreground">{{ texto(visibles[3].objetivo_tipo) }}</span>
                            </div>
                        </td>
                        <td class="px-3 py-3 align-top">
                            <div class="flex max-w-[220px] flex-col gap-0.5">
                                <span class="truncate font-medium text-foreground" :title="texto(visibles[3].destino_nombre)">{{ texto(visibles[3].destino_nombre) }}</span>
                                <span v-if="visibles[3].destino_tipo" class="text-[11px] text-muted-foreground">{{ visibles[3].destino_tipo }}</span>
                            </div>
                        </td>
                        <td class="px-3 py-3 text-center align-top"><span class="inline-flex min-w-[44px] justify-center rounded-md border border-border bg-muted/60 px-2 py-1 text-xs font-medium tabular-nums text-foreground">{{ texto(visibles[3].talla) }}</span></td>
                        <td class="px-3 py-3 text-center align-top">
                            <button type="button" @click="verDetalle(visibles[3])" title="Ver la recepción que originó esta merma" :aria-label="`Ver detalle de la merma ${visibles[3].numero_op} talla ${visibles[3].talla}`" class="inline-flex items-center gap-1.5 rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-semibold tabular-nums text-red-700 shadow-sm transition hover:bg-red-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300">
                                {{ piezas(visibles[3].cantidad) }}
                                <span class="text-[11px] font-normal">{{ Number(visibles[3].cantidad) === 1 ? 'par' : 'pares' }}</span>
                            </button>
                        </td>
                        <td class="px-3 py-3 text-right align-top tabular-nums text-muted-foreground">{{ porcentaje(visibles[3]) }}</td>
                        <td class="px-3 py-3 text-right align-top tabular-nums text-muted-foreground">{{ dinero(visibles[3].costo_unitario) }}</td>
                        <td class="px-3 py-3 text-right align-top"><span class="font-semibold tabular-nums text-foreground">{{ dinero(visibles[3].costo_total_perdida) }}</span></td>
                        <td class="px-3 py-3 text-right align-top text-xs tabular-nums text-muted-foreground">{{ fecha(visibles[3].fecha_registro) }}</td>
                        <td class="px-3 py-3 align-top text-sm text-foreground">{{ texto(visibles[3].responsable) }}</td>
                        <td class="sticky right-0 z-10 w-[88px] border-l border-border/60 bg-card px-2 py-2 align-middle shadow-[-8px_0_16px_-16px_rgba(0,0,0,0.35)] transition-colors group-hover:bg-muted/40">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" @click="verDetalle(visibles[3])" title="Ver detalle" :aria-label="`Ver detalle de la merma de ${visibles[3].numero_op}`" class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"><EyeIcon class="h-4 w-4" /></button>
                                <button type="button" @click="abrirOrden(visibles[3])" title="Ir a la OP" :aria-label="`Ir a la orden ${visibles[3].numero_op}`" class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"><ClipboardDocumentListIcon class="h-4 w-4" /></button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="visibles.length > 4" class="group border-b border-border/60 transition-colors hover:bg-muted/40">
                        <td class="px-3 py-3 align-top">
                            <div class="flex flex-col gap-0.5">
                                <span class="font-medium text-foreground">{{ texto(visibles[4].numero_op) }}</span>
                                <span class="text-[11px] text-muted-foreground">{{ texto(visibles[4].estado_op, 'Sin estado') }}</span>
                            </div>
                        </td>
                        <td class="px-3 py-3 align-top">
                            <div class="flex max-w-[320px] flex-col gap-0.5">
                                <span class="truncate font-medium text-foreground" :title="visibles[4].objetivo">{{ texto(visibles[4].objetivo) }}</span>
                                <span class="text-[11px] text-muted-foreground">{{ texto(visibles[4].objetivo_tipo) }}</span>
                            </div>
                        </td>
                        <td class="px-3 py-3 align-top">
                            <div class="flex max-w-[220px] flex-col gap-0.5">
                                <span class="truncate font-medium text-foreground" :title="texto(visibles[4].destino_nombre)">{{ texto(visibles[4].destino_nombre) }}</span>
                                <span v-if="visibles[4].destino_tipo" class="text-[11px] text-muted-foreground">{{ visibles[4].destino_tipo }}</span>
                            </div>
                        </td>
                        <td class="px-3 py-3 text-center align-top"><span class="inline-flex min-w-[44px] justify-center rounded-md border border-border bg-muted/60 px-2 py-1 text-xs font-medium tabular-nums text-foreground">{{ texto(visibles[4].talla) }}</span></td>
                        <td class="px-3 py-3 text-center align-top">
                            <button type="button" @click="verDetalle(visibles[4])" title="Ver la recepción que originó esta merma" :aria-label="`Ver detalle de la merma ${visibles[4].numero_op} talla ${visibles[4].talla}`" class="inline-flex items-center gap-1.5 rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-semibold tabular-nums text-red-700 shadow-sm transition hover:bg-red-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300">
                                {{ piezas(visibles[4].cantidad) }}
                                <span class="text-[11px] font-normal">{{ Number(visibles[4].cantidad) === 1 ? 'par' : 'pares' }}</span>
                            </button>
                        </td>
                        <td class="px-3 py-3 text-right align-top tabular-nums text-muted-foreground">{{ porcentaje(visibles[4]) }}</td>
                        <td class="px-3 py-3 text-right align-top tabular-nums text-muted-foreground">{{ dinero(visibles[4].costo_unitario) }}</td>
                        <td class="px-3 py-3 text-right align-top"><span class="font-semibold tabular-nums text-foreground">{{ dinero(visibles[4].costo_total_perdida) }}</span></td>
                        <td class="px-3 py-3 text-right align-top text-xs tabular-nums text-muted-foreground">{{ fecha(visibles[4].fecha_registro) }}</td>
                        <td class="px-3 py-3 align-top text-sm text-foreground">{{ texto(visibles[4].responsable) }}</td>
                        <td class="sticky right-0 z-10 w-[88px] border-l border-border/60 bg-card px-2 py-2 align-middle shadow-[-8px_0_16px_-16px_rgba(0,0,0,0.35)] transition-colors group-hover:bg-muted/40">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" @click="verDetalle(visibles[4])" title="Ver detalle" :aria-label="`Ver detalle de la merma de ${visibles[4].numero_op}`" class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"><EyeIcon class="h-4 w-4" /></button>
                                <button type="button" @click="abrirOrden(visibles[4])" title="Ir a la OP" :aria-label="`Ir a la orden ${visibles[4].numero_op}`" class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"><ClipboardDocumentListIcon class="h-4 w-4" /></button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="visibles.length > 5" class="group border-b border-border/60 transition-colors hover:bg-muted/40">
                        <td class="px-3 py-3 align-top">
                            <div class="flex flex-col gap-0.5">
                                <span class="font-medium text-foreground">{{ texto(visibles[5].numero_op) }}</span>
                                <span class="text-[11px] text-muted-foreground">{{ texto(visibles[5].estado_op, 'Sin estado') }}</span>
                            </div>
                        </td>
                        <td class="px-3 py-3 align-top">
                            <div class="flex max-w-[320px] flex-col gap-0.5">
                                <span class="truncate font-medium text-foreground" :title="visibles[5].objetivo">{{ texto(visibles[5].objetivo) }}</span>
                                <span class="text-[11px] text-muted-foreground">{{ texto(visibles[5].objetivo_tipo) }}</span>
                            </div>
                        </td>
                        <td class="px-3 py-3 align-top">
                            <div class="flex max-w-[220px] flex-col gap-0.5">
                                <span class="truncate font-medium text-foreground" :title="texto(visibles[5].destino_nombre)">{{ texto(visibles[5].destino_nombre) }}</span>
                                <span v-if="visibles[5].destino_tipo" class="text-[11px] text-muted-foreground">{{ visibles[5].destino_tipo }}</span>
                            </div>
                        </td>
                        <td class="px-3 py-3 text-center align-top"><span class="inline-flex min-w-[44px] justify-center rounded-md border border-border bg-muted/60 px-2 py-1 text-xs font-medium tabular-nums text-foreground">{{ texto(visibles[5].talla) }}</span></td>
                        <td class="px-3 py-3 text-center align-top">
                            <button type="button" @click="verDetalle(visibles[5])" title="Ver la recepción que originó esta merma" :aria-label="`Ver detalle de la merma ${visibles[5].numero_op} talla ${visibles[5].talla}`" class="inline-flex items-center gap-1.5 rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-semibold tabular-nums text-red-700 shadow-sm transition hover:bg-red-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300">
                                {{ piezas(visibles[5].cantidad) }}
                                <span class="text-[11px] font-normal">{{ Number(visibles[5].cantidad) === 1 ? 'par' : 'pares' }}</span>
                            </button>
                        </td>
                        <td class="px-3 py-3 text-right align-top tabular-nums text-muted-foreground">{{ porcentaje(visibles[5]) }}</td>
                        <td class="px-3 py-3 text-right align-top tabular-nums text-muted-foreground">{{ dinero(visibles[5].costo_unitario) }}</td>
                        <td class="px-3 py-3 text-right align-top"><span class="font-semibold tabular-nums text-foreground">{{ dinero(visibles[5].costo_total_perdida) }}</span></td>
                        <td class="px-3 py-3 text-right align-top text-xs tabular-nums text-muted-foreground">{{ fecha(visibles[5].fecha_registro) }}</td>
                        <td class="px-3 py-3 align-top text-sm text-foreground">{{ texto(visibles[5].responsable) }}</td>
                        <td class="sticky right-0 z-10 w-[88px] border-l border-border/60 bg-card px-2 py-2 align-middle shadow-[-8px_0_16px_-16px_rgba(0,0,0,0.35)] transition-colors group-hover:bg-muted/40">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" @click="verDetalle(visibles[5])" title="Ver detalle" :aria-label="`Ver detalle de la merma de ${visibles[5].numero_op}`" class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"><EyeIcon class="h-4 w-4" /></button>
                                <button type="button" @click="abrirOrden(visibles[5])" title="Ir a la OP" :aria-label="`Ir a la orden ${visibles[5].numero_op}`" class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"><ClipboardDocumentListIcon class="h-4 w-4" /></button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="visibles.length > 6" class="group border-b border-border/60 transition-colors hover:bg-muted/40">
                        <td class="px-3 py-3 align-top">
                            <div class="flex flex-col gap-0.5">
                                <span class="font-medium text-foreground">{{ texto(visibles[6].numero_op) }}</span>
                                <span class="text-[11px] text-muted-foreground">{{ texto(visibles[6].estado_op, 'Sin estado') }}</span>
                            </div>
                        </td>
                        <td class="px-3 py-3 align-top">
                            <div class="flex max-w-[320px] flex-col gap-0.5">
                                <span class="truncate font-medium text-foreground" :title="visibles[6].objetivo">{{ texto(visibles[6].objetivo) }}</span>
                                <span class="text-[11px] text-muted-foreground">{{ texto(visibles[6].objetivo_tipo) }}</span>
                            </div>
                        </td>
                        <td class="px-3 py-3 align-top">
                            <div class="flex max-w-[220px] flex-col gap-0.5">
                                <span class="truncate font-medium text-foreground" :title="texto(visibles[6].destino_nombre)">{{ texto(visibles[6].destino_nombre) }}</span>
                                <span v-if="visibles[6].destino_tipo" class="text-[11px] text-muted-foreground">{{ visibles[6].destino_tipo }}</span>
                            </div>
                        </td>
                        <td class="px-3 py-3 text-center align-top"><span class="inline-flex min-w-[44px] justify-center rounded-md border border-border bg-muted/60 px-2 py-1 text-xs font-medium tabular-nums text-foreground">{{ texto(visibles[6].talla) }}</span></td>
                        <td class="px-3 py-3 text-center align-top">
                            <button type="button" @click="verDetalle(visibles[6])" title="Ver la recepción que originó esta merma" :aria-label="`Ver detalle de la merma ${visibles[6].numero_op} talla ${visibles[6].talla}`" class="inline-flex items-center gap-1.5 rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-semibold tabular-nums text-red-700 shadow-sm transition hover:bg-red-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300">
                                {{ piezas(visibles[6].cantidad) }}
                                <span class="text-[11px] font-normal">{{ Number(visibles[6].cantidad) === 1 ? 'par' : 'pares' }}</span>
                            </button>
                        </td>
                        <td class="px-3 py-3 text-right align-top tabular-nums text-muted-foreground">{{ porcentaje(visibles[6]) }}</td>
                        <td class="px-3 py-3 text-right align-top tabular-nums text-muted-foreground">{{ dinero(visibles[6].costo_unitario) }}</td>
                        <td class="px-3 py-3 text-right align-top"><span class="font-semibold tabular-nums text-foreground">{{ dinero(visibles[6].costo_total_perdida) }}</span></td>
                        <td class="px-3 py-3 text-right align-top text-xs tabular-nums text-muted-foreground">{{ fecha(visibles[6].fecha_registro) }}</td>
                        <td class="px-3 py-3 align-top text-sm text-foreground">{{ texto(visibles[6].responsable) }}</td>
                        <td class="sticky right-0 z-10 w-[88px] border-l border-border/60 bg-card px-2 py-2 align-middle shadow-[-8px_0_16px_-16px_rgba(0,0,0,0.35)] transition-colors group-hover:bg-muted/40">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" @click="verDetalle(visibles[6])" title="Ver detalle" :aria-label="`Ver detalle de la merma de ${visibles[6].numero_op}`" class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"><EyeIcon class="h-4 w-4" /></button>
                                <button type="button" @click="abrirOrden(visibles[6])" title="Ir a la OP" :aria-label="`Ir a la orden ${visibles[6].numero_op}`" class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"><ClipboardDocumentListIcon class="h-4 w-4" /></button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="visibles.length > 7" class="group border-b border-border/60 transition-colors hover:bg-muted/40">
                        <td class="px-3 py-3 align-top">
                            <div class="flex flex-col gap-0.5">
                                <span class="font-medium text-foreground">{{ texto(visibles[7].numero_op) }}</span>
                                <span class="text-[11px] text-muted-foreground">{{ texto(visibles[7].estado_op, 'Sin estado') }}</span>
                            </div>
                        </td>
                        <td class="px-3 py-3 align-top">
                            <div class="flex max-w-[320px] flex-col gap-0.5">
                                <span class="truncate font-medium text-foreground" :title="visibles[7].objetivo">{{ texto(visibles[7].objetivo) }}</span>
                                <span class="text-[11px] text-muted-foreground">{{ texto(visibles[7].objetivo_tipo) }}</span>
                            </div>
                        </td>
                        <td class="px-3 py-3 align-top">
                            <div class="flex max-w-[220px] flex-col gap-0.5">
                                <span class="truncate font-medium text-foreground" :title="texto(visibles[7].destino_nombre)">{{ texto(visibles[7].destino_nombre) }}</span>
                                <span v-if="visibles[7].destino_tipo" class="text-[11px] text-muted-foreground">{{ visibles[7].destino_tipo }}</span>
                            </div>
                        </td>
                        <td class="px-3 py-3 text-center align-top"><span class="inline-flex min-w-[44px] justify-center rounded-md border border-border bg-muted/60 px-2 py-1 text-xs font-medium tabular-nums text-foreground">{{ texto(visibles[7].talla) }}</span></td>
                        <td class="px-3 py-3 text-center align-top">
                            <button type="button" @click="verDetalle(visibles[7])" title="Ver la recepción que originó esta merma" :aria-label="`Ver detalle de la merma ${visibles[7].numero_op} talla ${visibles[7].talla}`" class="inline-flex items-center gap-1.5 rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-semibold tabular-nums text-red-700 shadow-sm transition hover:bg-red-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300">
                                {{ piezas(visibles[7].cantidad) }}
                                <span class="text-[11px] font-normal">{{ Number(visibles[7].cantidad) === 1 ? 'par' : 'pares' }}</span>
                            </button>
                        </td>
                        <td class="px-3 py-3 text-right align-top tabular-nums text-muted-foreground">{{ porcentaje(visibles[7]) }}</td>
                        <td class="px-3 py-3 text-right align-top tabular-nums text-muted-foreground">{{ dinero(visibles[7].costo_unitario) }}</td>
                        <td class="px-3 py-3 text-right align-top"><span class="font-semibold tabular-nums text-foreground">{{ dinero(visibles[7].costo_total_perdida) }}</span></td>
                        <td class="px-3 py-3 text-right align-top text-xs tabular-nums text-muted-foreground">{{ fecha(visibles[7].fecha_registro) }}</td>
                        <td class="px-3 py-3 align-top text-sm text-foreground">{{ texto(visibles[7].responsable) }}</td>
                        <td class="sticky right-0 z-10 w-[88px] border-l border-border/60 bg-card px-2 py-2 align-middle shadow-[-8px_0_16px_-16px_rgba(0,0,0,0.35)] transition-colors group-hover:bg-muted/40">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" @click="verDetalle(visibles[7])" title="Ver detalle" :aria-label="`Ver detalle de la merma de ${visibles[7].numero_op}`" class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"><EyeIcon class="h-4 w-4" /></button>
                                <button type="button" @click="abrirOrden(visibles[7])" title="Ir a la OP" :aria-label="`Ir a la orden ${visibles[7].numero_op}`" class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"><ClipboardDocumentListIcon class="h-4 w-4" /></button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="visibles.length > 8" class="group border-b border-border/60 transition-colors hover:bg-muted/40">
                        <td class="px-3 py-3 align-top">
                            <div class="flex flex-col gap-0.5">
                                <span class="font-medium text-foreground">{{ texto(visibles[8].numero_op) }}</span>
                                <span class="text-[11px] text-muted-foreground">{{ texto(visibles[8].estado_op, 'Sin estado') }}</span>
                            </div>
                        </td>
                        <td class="px-3 py-3 align-top">
                            <div class="flex max-w-[320px] flex-col gap-0.5">
                                <span class="truncate font-medium text-foreground" :title="visibles[8].objetivo">{{ texto(visibles[8].objetivo) }}</span>
                                <span class="text-[11px] text-muted-foreground">{{ texto(visibles[8].objetivo_tipo) }}</span>
                            </div>
                        </td>
                        <td class="px-3 py-3 align-top">
                            <div class="flex max-w-[220px] flex-col gap-0.5">
                                <span class="truncate font-medium text-foreground" :title="texto(visibles[8].destino_nombre)">{{ texto(visibles[8].destino_nombre) }}</span>
                                <span v-if="visibles[8].destino_tipo" class="text-[11px] text-muted-foreground">{{ visibles[8].destino_tipo }}</span>
                            </div>
                        </td>
                        <td class="px-3 py-3 text-center align-top"><span class="inline-flex min-w-[44px] justify-center rounded-md border border-border bg-muted/60 px-2 py-1 text-xs font-medium tabular-nums text-foreground">{{ texto(visibles[8].talla) }}</span></td>
                        <td class="px-3 py-3 text-center align-top">
                            <button type="button" @click="verDetalle(visibles[8])" title="Ver la recepción que originó esta merma" :aria-label="`Ver detalle de la merma ${visibles[8].numero_op} talla ${visibles[8].talla}`" class="inline-flex items-center gap-1.5 rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-semibold tabular-nums text-red-700 shadow-sm transition hover:bg-red-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300">
                                {{ piezas(visibles[8].cantidad) }}
                                <span class="text-[11px] font-normal">{{ Number(visibles[8].cantidad) === 1 ? 'par' : 'pares' }}</span>
                            </button>
                        </td>
                        <td class="px-3 py-3 text-right align-top tabular-nums text-muted-foreground">{{ porcentaje(visibles[8]) }}</td>
                        <td class="px-3 py-3 text-right align-top tabular-nums text-muted-foreground">{{ dinero(visibles[8].costo_unitario) }}</td>
                        <td class="px-3 py-3 text-right align-top"><span class="font-semibold tabular-nums text-foreground">{{ dinero(visibles[8].costo_total_perdida) }}</span></td>
                        <td class="px-3 py-3 text-right align-top text-xs tabular-nums text-muted-foreground">{{ fecha(visibles[8].fecha_registro) }}</td>
                        <td class="px-3 py-3 align-top text-sm text-foreground">{{ texto(visibles[8].responsable) }}</td>
                        <td class="sticky right-0 z-10 w-[88px] border-l border-border/60 bg-card px-2 py-2 align-middle shadow-[-8px_0_16px_-16px_rgba(0,0,0,0.35)] transition-colors group-hover:bg-muted/40">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" @click="verDetalle(visibles[8])" title="Ver detalle" :aria-label="`Ver detalle de la merma de ${visibles[8].numero_op}`" class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"><EyeIcon class="h-4 w-4" /></button>
                                <button type="button" @click="abrirOrden(visibles[8])" title="Ir a la OP" :aria-label="`Ir a la orden ${visibles[8].numero_op}`" class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"><ClipboardDocumentListIcon class="h-4 w-4" /></button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="visibles.length > 9" class="group border-b border-border/60 transition-colors hover:bg-muted/40">
                        <td class="px-3 py-3 align-top">
                            <div class="flex flex-col gap-0.5">
                                <span class="font-medium text-foreground">{{ texto(visibles[9].numero_op) }}</span>
                                <span class="text-[11px] text-muted-foreground">{{ texto(visibles[9].estado_op, 'Sin estado') }}</span>
                            </div>
                        </td>
                        <td class="px-3 py-3 align-top">
                            <div class="flex max-w-[320px] flex-col gap-0.5">
                                <span class="truncate font-medium text-foreground" :title="visibles[9].objetivo">{{ texto(visibles[9].objetivo) }}</span>
                                <span class="text-[11px] text-muted-foreground">{{ texto(visibles[9].objetivo_tipo) }}</span>
                            </div>
                        </td>
                        <td class="px-3 py-3 align-top">
                            <div class="flex max-w-[220px] flex-col gap-0.5">
                                <span class="truncate font-medium text-foreground" :title="texto(visibles[9].destino_nombre)">{{ texto(visibles[9].destino_nombre) }}</span>
                                <span v-if="visibles[9].destino_tipo" class="text-[11px] text-muted-foreground">{{ visibles[9].destino_tipo }}</span>
                            </div>
                        </td>
                        <td class="px-3 py-3 text-center align-top"><span class="inline-flex min-w-[44px] justify-center rounded-md border border-border bg-muted/60 px-2 py-1 text-xs font-medium tabular-nums text-foreground">{{ texto(visibles[9].talla) }}</span></td>
                        <td class="px-3 py-3 text-center align-top">
                            <button type="button" @click="verDetalle(visibles[9])" title="Ver la recepción que originó esta merma" :aria-label="`Ver detalle de la merma ${visibles[9].numero_op} talla ${visibles[9].talla}`" class="inline-flex items-center gap-1.5 rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-semibold tabular-nums text-red-700 shadow-sm transition hover:bg-red-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300">
                                {{ piezas(visibles[9].cantidad) }}
                                <span class="text-[11px] font-normal">{{ Number(visibles[9].cantidad) === 1 ? 'par' : 'pares' }}</span>
                            </button>
                        </td>
                        <td class="px-3 py-3 text-right align-top tabular-nums text-muted-foreground">{{ porcentaje(visibles[9]) }}</td>
                        <td class="px-3 py-3 text-right align-top tabular-nums text-muted-foreground">{{ dinero(visibles[9].costo_unitario) }}</td>
                        <td class="px-3 py-3 text-right align-top"><span class="font-semibold tabular-nums text-foreground">{{ dinero(visibles[9].costo_total_perdida) }}</span></td>
                        <td class="px-3 py-3 text-right align-top text-xs tabular-nums text-muted-foreground">{{ fecha(visibles[9].fecha_registro) }}</td>
                        <td class="px-3 py-3 align-top text-sm text-foreground">{{ texto(visibles[9].responsable) }}</td>
                        <td class="sticky right-0 z-10 w-[88px] border-l border-border/60 bg-card px-2 py-2 align-middle shadow-[-8px_0_16px_-16px_rgba(0,0,0,0.35)] transition-colors group-hover:bg-muted/40">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" @click="verDetalle(visibles[9])" title="Ver detalle" :aria-label="`Ver detalle de la merma de ${visibles[9].numero_op}`" class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"><EyeIcon class="h-4 w-4" /></button>
                                <button type="button" @click="abrirOrden(visibles[9])" title="Ir a la OP" :aria-label="`Ir a la orden ${visibles[9].numero_op}`" class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"><ClipboardDocumentListIcon class="h-4 w-4" /></button>
                            </div>
                        </td>
                    </tr>
                </tbody>

                <tfoot v-if="total > 0">
                    <tr class="sticky bottom-0 z-20 bg-muted text-foreground shadow-[0_-8px_16px_-16px_rgba(0,0,0,0.35)]">
                        <td colspan="4" class="border-t border-border px-3 py-2.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground">
                            Totales
                            <span class="ml-1 font-normal normal-case tracking-normal">({{ filasDeTabla.length }} {{ filasDeTabla.length === 1 ? 'merma' : 'mermas' }})</span>
                        </td>
                        <td class="border-t border-border px-3 py-2.5 text-center font-semibold tabular-nums text-foreground">{{ piezas(props.totales?.piezas ?? 0) }}</td>
                        <td class="border-t border-border px-3 py-2.5 text-right font-semibold tabular-nums text-foreground">
                            {{ props.totales?.porcentaje > 0 ? `${props.totales.porcentaje.toFixed(1)}%` : '—' }}</td>
                        <td class="border-t border-border px-3 py-2.5 text-right font-semibold tabular-nums text-foreground">{{ dinero(props.totales?.costoUnitario ?? 0) }}</td>
                        <td class="border-t border-border px-3 py-2.5 text-right font-semibold tabular-nums text-foreground">{{ dinero(props.totales?.valor ?? 0) }}</td>
                        <td class="border-t border-border px-3 py-2.5"></td>
                        <td class="border-t border-border px-3 py-2.5"></td>
                        <td class="sticky right-0 z-30 w-[88px] border-l border-t border-border bg-muted px-2 py-2.5"></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <DataPagination
            :current-page="paginaActual"
            :last-page="paginas"
            :from="desdeFila"
            :to="hasta"
            :total="total"
            :item-label="total === 1 ? 'merma' : 'mermas'"
            @page="cambiarPagina"
        />
    </div>
</template>

<style scoped>
@keyframes merma-page-in {
    from { opacity: 0; transform: translateY(3px); }
    to { opacity: 1; transform: translateY(0); }
}

.merma-page {
    animation: merma-page-in 180ms cubic-bezier(0.22, 1, 0.36, 1);
}

.merma-scroll {
    scrollbar-width: thin;
    scrollbar-color: var(--border) transparent;
}

.merma-scroll::-webkit-scrollbar { width: 10px; height: 10px; }
.merma-scroll::-webkit-scrollbar-track { background: transparent; }
.merma-scroll::-webkit-scrollbar-thumb {
    background: var(--border);
    border: 3px solid transparent;
    border-radius: 9999px;
    background-clip: content-box;
}

@media (prefers-reduced-motion: reduce) {
    .merma-page { animation: none; }
}
</style>
