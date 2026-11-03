<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import DataPagination from '@/components/DataPagination.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

import {
    ArrowPathIcon,
    ArrowUpTrayIcon,
    ChevronDownIcon,
    ChevronRightIcon,
    ChevronUpDownIcon,
    ChevronUpIcon,
    CubeIcon,
    EllipsisVerticalIcon,
    FunnelIcon,
    MagnifyingGlassIcon,
    PencilIcon,
    PlusIcon,
    SquaresPlusIcon,
    TrashIcon,
    XMarkIcon,
} from '@heroicons/vue/24/solid';

import Create from '@/pages/Producto/Create.vue';
import Edit from '@/pages/Producto/Edit.vue';
import Delete from '@/pages/Producto/Delete.vue';
import ImportarProductoModal from '@/pages/Producto/ImportarProductoModal.vue';
import DistribucionTallas from '@/pages/Producto/DistribucionTallas.vue';
import ModalDependenciasProducto from '@/pages/Producto/ModalDependenciasProducto.vue';

import { zilef_nuber_format } from '@/global.ts';

const props = defineProps({
    productos: { type: Array as () => any[], default: () => [] },
    total: Number,
    breadcrumbs: { type: Array as () => Array<{ label?: string; href?: string }>, default: () => [] },
    perPage: Number,
    title: String,
    numberPermissions: Number,
    losSelect: Object,
    titulos: Array,
});

/* --------------------------------------------------------------------------
 * Tabla 100% en cliente: los productos llegan una sola vez y aquí se buscan,
 * filtran, ordenan y paginan sin volver al backend. Solo las acciones de
 * cada fila (tallas, editar, eliminar, cargar Excel) hablan con él.
 * ------------------------------------------------------------------------ */

const filas = computed<any[]>(() => props.productos ?? []);

const busqueda = ref('');
const estadoFiltro = ref('');
const categoriaFiltro = ref('');
const familiaFiltro = ref('');
const controlVarianteFiltro = ref('');
const opcionFiltro = ref('');
const precioPreventaFiltro = ref('');
const precioEstimadoFiltro = ref('');
const tallasFiltro = ref('');
const cantidadFiltro = ref('');
const fichaFiltro = ref('');

const sortKey = ref('updated_at');
const sortDir = ref<'asc' | 'desc'>('desc');
const porPagina = ref<number>(props.perPage ?? 10);
const pagina = ref(1);
const cargando = ref(false);

const columnas = [
    { key: 'Codigo_interno', label: 'Código', sortable: true, align: 'left', headerClass: 'min-w-[120px]', cellClass: 'min-w-[120px]' },
    { key: 'Titulo_Shopify', label: 'Producto', sortable: true, align: 'left', headerClass: 'min-w-[260px]', cellClass: 'min-w-[260px]' },
    { key: 'cantidad', label: 'Cantidad', sortable: false, align: 'right', headerClass: 'w-[100px]', cellClass: 'w-[100px]' },
    { key: 'Categoria_original', label: 'Categoría', sortable: true, align: 'left', headerClass: 'hidden min-w-[190px] lg:table-cell', cellClass: 'hidden min-w-[190px] lg:table-cell' },
    { key: 'Control_de_variante', label: 'Variante', sortable: true, align: 'left', headerClass: 'hidden min-w-[170px] xl:table-cell', cellClass: 'hidden min-w-[170px] xl:table-cell' },
    { key: 'Estado', label: 'Estado', sortable: true, align: 'left', headerClass: 'w-[110px]', cellClass: 'w-[110px]' },
    { key: 'precio_venta_producto', label: 'Precio', sortable: true, align: 'right', headerClass: 'w-[140px]', cellClass: 'w-[140px]' },
    { key: 'tallas', label: 'Tallas', sortable: false, align: 'left', headerClass: 'hidden min-w-[150px] md:table-cell', cellClass: 'hidden min-w-[150px] md:table-cell' },
] as const;

const titulos = [
    { order: 'Codigo_interno', label: 'Codigo_interno', type: 'string', default: 'COD-001' },
    { order: 'ID_producto_Shopify', label: 'ID_producto_Shopify', type: 'string' },
    { order: 'Categoria_original', label: 'Categoria_original', type: 'string' },
    { order: 'Familia_normalizada', label: 'Familia_normalizada', type: 'string' },
    { order: 'Titulo_Shopify', label: 'Titulo_Shopify', type: 'string' },
    { order: 'Control_de_variante', label: 'Control_de_variante', type: 'string' },
    { order: 'Opciones', label: 'Opciones', type: 'string' },
    { order: 'Estado', label: 'Estado', type: 'string' },
    { order: 'Observaciones', label: 'Observaciones', type: 'string' },
    { order: 'descripcion', label: 'descripcion', type: 'text' },
    { order: 'costo_anticipado', label: 'costo_anticipado', type: 'number' },
    { order: 'precio_venta_producto', label: 'precio_venta_producto', type: 'number' },
];

const data = reactive({
    Productoo: null as any,
    createOpen: false,
    editOpen: false,
    deleteOpen: false,
    importOpen: false,
    distribucionOpen: false,
    distribucionProducto: null as any,
    deleteBulkOpen: false,
    deletingBulk: false,
    modalBloqueadosOpen: false,
    productosBloqueados: [] as any[],
    productosEliminados: 0,
});

const seleccionados = reactive<Set<number>>(new Set());

const todosSeleccionados = computed(() =>
    visibles.value.length > 0 && visibles.value.every((p: any) => seleccionados.has(p.id)),
);

const algunoSeleccionado = computed(() =>
    visibles.value.some((p: any) => seleccionados.has(p.id)) && !todosSeleccionados.value,
);

const toggleProducto = (id: number) => {
    if (seleccionados.has(id)) {
        seleccionados.delete(id);
    } else {
        seleccionados.add(id);
    }
};

const toggleTodos = () => {
    if (todosSeleccionados.value) {
        visibles.value.forEach((p: any) => seleccionados.delete(p.id));
    } else {
        visibles.value.forEach((p: any) => seleccionados.add(p.id));
    }
};

const limpiarSeleccion = () => seleccionados.clear();

const eliminarSeleccionados = () => {
    data.deleteBulkOpen = true;
};

const confirmarEliminarBulk = async () => {
    data.deletingBulk = true;
    try {
        const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';
        const respuesta = await fetch(route('Producto.destroy-bulk'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({ ids: Array.from(seleccionados) }),
        });

        const resultado = await respuesta.json();
        data.deleteBulkOpen = false;

        if (resultado?.bloqueados?.length > 0) {
            data.productosBloqueados = resultado.bloqueados;
            data.productosEliminados = Number(resultado.eliminados || 0);
            data.modalBloqueadosOpen = true;

            const idsBloqueados = new Set(resultado.bloqueados.map((b: any) => Number(b.id)));
            Array.from(seleccionados).forEach((id: number) => {
                if (!idsBloqueados.has(id)) {
                    seleccionados.delete(id);
                }
            });

            if (resultado.eliminados > 0) {
                recargar();
            }
        } else {
            seleccionados.clear();
            recargar();
        }
    } catch {
        data.deleteBulkOpen = false;
    } finally {
        data.deletingBulk = false;
    }
};

const selectClass = 'h-9 rounded-md border border-input bg-background px-2 text-sm text-foreground shadow-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30';

const normalizar = (valor: any): string =>
    String(valor ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/\s+/g, ' ')
        .trim();

const estadoNormalizado = (valor: any): 'Activo' | 'Inactivo' => {
    const texto = normalizar(valor);
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

const estadoEstilo = (valor: any) =>
    ESTADO_ESTILOS[estadoNormalizado(valor) === 'Activo' ? 'activo' : 'inactivo'];

const segmentoClass = (activo: boolean) => [
    'whitespace-nowrap border-l border-border px-2.5 text-xs font-medium transition first:border-l-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring',
    activo
        ? 'bg-foreground text-background shadow-sm'
        : 'text-muted-foreground hover:bg-muted hover:text-foreground',
];

const formatearPrecio = (valor: any, decimales = 0): string =>
    valor === null || valor === undefined || valor === '' ? '—' : zilef_nuber_format(valor, decimales, true);

// El precio estimado es decimal: solo muestra centavos cuando realmente los trae.
const decimalesDe = (valor: any): number =>
    Number.isFinite(Number(valor)) && Math.round(Number(valor) * 100) % 100 !== 0 ? 2 : 0;

const texto = (valor: any, fallback = '—'): string => {
    const resultado = String(valor ?? '').trim();
    return resultado !== '' ? resultado : fallback;
};

const tallasDe = (producto: any): string[] => {
    const lista = producto?.distribuciones_por_tallas ?? producto?.distribucionesPorTallas ?? [];
    if (!Array.isArray(lista)) return [];
    return Array.from(
        new Set(
            lista
                .map((talla: any) => String(talla?.talla ?? '').trim())
                .filter((talla: string) => talla !== ''),
        ),
    );
};

// Desglose que valida la columna Cantidad: «S 10 · M 5».
const detalleTallasDe = (producto: any): string => {
    const lista = producto?.distribuciones_por_tallas ?? producto?.distribucionesPorTallas ?? [];
    if (!Array.isArray(lista)) return '';
    return lista
        .map((item: any) => {
            const talla = String(item?.talla ?? '').trim();
            const cantidad = Number(item?.cantidad_planificada ?? 0);
            return talla === '' || Number.isNaN(cantidad) ? '' : `${talla} ${cantidad}`;
        })
        .filter(Boolean)
        .join(' · ');
};

const distribucionesDe = (producto: any): any[] => {
    const lista = producto?.distribuciones_por_tallas ?? producto?.distribucionesPorTallas ?? [];
    return Array.isArray(lista) ? lista : [];
};

const tieneTallas = (producto: any): boolean => tallasDe(producto).length > 0;

const tieneCantidad = (producto: any): boolean =>
    distribucionesDe(producto).some((item: any) => Number(item?.cantidad_planificada ?? 0) > 0);

const tienePrecio = (valor: any): boolean =>
    valor !== null && valor !== undefined && valor !== '' && Number(valor) > 0;

// Cada fila trae lo que necesitan los filtros sin volver a recorrer la BD.
const preparadas = computed(() =>
    filas.value.map((producto: any) => ({
        ...producto,
        _estado: estadoNormalizado(producto.Estado),
        _tieneTallas: tieneTallas(producto),
        _tieneCantidad: tieneCantidad(producto),
        _tieneFicha: Number(producto.ficha_id ?? 0) > 0,
        _busqueda: normalizar(
            [producto.Codigo_interno, producto.ID_producto_Shopify, producto.Titulo_Shopify].join(' '),
        ),
    })),
);

// Búsqueda y categoría reducen el universo; las listas del desplegable
// «Más filtros» se calculan sobre este subconjunto, como en Materiales.
// El estado se aplica más adelante para que sus chips siempre muestren
// el total real y no se auto-excluyan al elegir uno.
const baseFiltrada = computed(() =>
    preparadas.value.filter((producto: any) => {
        if (busqueda.value.trim() && !producto._busqueda.includes(normalizar(busqueda.value))) {
            return false;
        }
        if (categoriaFiltro.value && normalizar(producto.Categoria_original) !== normalizar(categoriaFiltro.value)) {
            return false;
        }
        return true;
    }),
);

const opcionesDe = (campo: string): Array<{ value: string; label: string; count: number }> => {
    const grupos = new Map<string, { value: string; label: string; count: number }>();
    baseFiltrada.value.forEach((producto: any) => {
        const raw = String(producto[campo] ?? '').trim();
        if (!raw) return;
        const clave = normalizar(raw);
        const previo = grupos.get(clave);
        if (previo) {
            previo.count++;
        } else {
            grupos.set(clave, { value: raw, label: raw, count: 1 });
        }
    });
    return Array.from(grupos.values()).sort((a, b) => a.value.localeCompare(b.value, 'es'));
};

const categoriasDisponibles = computed(() => opcionesDe('Categoria_original'));
const familiasDisponibles = computed(() => opcionesDe('Familia_normalizada'));
const controlesVarianteDisponibles = computed(() => opcionesDe('Control_de_variante'));

const opcionesEstado = computed(() => [
    { value: '', label: 'Ambos', count: baseFiltrada.value.length },
    { value: 'Activo', label: 'Activos', count: baseFiltrada.value.filter((p: any) => p._estado === 'Activo').length },
    { value: 'Inactivo', label: 'Inactivos', count: baseFiltrada.value.filter((p: any) => p._estado === 'Inactivo').length },
]);

const opcionesPrecio = (campo: string) => [
    { value: '', label: 'Todos' },
    { value: 'con_precio', label: `Con precio (${baseFiltrada.value.filter((p: any) => tienePrecio(p[campo])).length})` },
    { value: 'sin_precio', label: `Sin precio (${baseFiltrada.value.filter((p: any) => !tienePrecio(p[campo])).length})` },
];

const opcionesBinarias = (campo: string, textos: { con: string; sin: string }) => [
    { value: '', label: 'Todas' },
    { value: 'con', label: `${textos.con} (${baseFiltrada.value.filter((p: any) => p[campo]).length})` },
    { value: 'sin', label: `${textos.sin} (${baseFiltrada.value.filter((p: any) => !p[campo]).length})` },
];

const opcionesTallas = computed(() => opcionesBinarias('_tieneTallas', { con: 'Con tallas', sin: 'Sin tallas' }));
const opcionesCantidad = computed(() => opcionesBinarias('_tieneCantidad', { con: 'Con cantidad', sin: 'Sin cantidad' }));
const opcionesFicha = computed(() => opcionesBinarias('_tieneFicha', { con: 'Con ficha', sin: 'Sin ficha' }));
const opcionesPrecioPreventa = computed(() => opcionesPrecio('precio_venta_producto'));

const filtradas = computed(() =>
    baseFiltrada.value.filter((producto: any) => {
        if (estadoFiltro.value && producto._estado !== estadoFiltro.value) {
            return false;
        }
        if (familiaFiltro.value && normalizar(producto.Familia_normalizada) !== normalizar(familiaFiltro.value)) {
            return false;
        }
        if (
            controlVarianteFiltro.value
            && normalizar(producto.Control_de_variante) !== normalizar(controlVarianteFiltro.value)
        ) {
            return false;
        }
        if (
            opcionFiltro.value.trim()
            && !normalizar(producto.Opciones).includes(normalizar(opcionFiltro.value))
        ) {
            return false;
        }
        if (precioPreventaFiltro.value === 'con_precio' && !tienePrecio(producto.precio_venta_producto)) return false;
        if (precioPreventaFiltro.value === 'sin_precio' && tienePrecio(producto.precio_venta_producto)) return false;
        if (tallasFiltro.value === 'con' && !tieneTallas(producto)) return false;
        if (tallasFiltro.value === 'sin' && tieneTallas(producto)) return false;
        if (cantidadFiltro.value === 'con' && !tieneCantidad(producto)) return false;
        if (cantidadFiltro.value === 'sin' && tieneCantidad(producto)) return false;
        if (fichaFiltro.value === 'con' && !Number(producto.ficha_id ?? 0)) return false;
        if (fichaFiltro.value === 'sin' && Number(producto.ficha_id ?? 0)) return false;
        return true;
    }),
);

const collator = new Intl.Collator('es', { numeric: true, sensitivity: 'base' });

const valorOrden = (producto: any, key: string): any =>
    key === 'Estado' ? producto._estado : producto[key];

const ordenadas = computed(() => {
    const dir = sortDir.value === 'asc' ? 1 : -1;
    const key = sortKey.value;

    return [...filtradas.value].sort((a: any, b: any) => {
        const va = valorOrden(a, key);
        const vb = valorOrden(b, key);
        const nuloA = va === null || va === undefined || va === '';
        const nuloB = vb === null || vb === undefined || vb === '';

        if (nuloA && nuloB) return 0;
        if (nuloA) return 1;
        if (nuloB) return -1;
        if (typeof va === 'number' && typeof vb === 'number') return (va - vb) * dir;
        return collator.compare(String(va), String(vb)) * dir;
    });
});

const totalFiltrado = computed(() => ordenadas.value.length);
const totalRegistros = computed(() => totalFiltrado.value);
const totalProductos = computed(() => filas.value.length);
const paginas = computed(() => Math.max(1, Math.ceil(totalFiltrado.value / porPagina.value)));
const paginaActual = computed(() => Math.min(pagina.value, paginas.value));
const ultimaPagina = computed(() => paginas.value);
const desde = computed(() => (totalFiltrado.value === 0 ? 0 : (paginaActual.value - 1) * porPagina.value + 1));
const hasta = computed(() => Math.min(paginaActual.value * porPagina.value, totalFiltrado.value));

const visibles = computed(() => {
    const inicio = (paginaActual.value - 1) * porPagina.value;
    return ordenadas.value.slice(inicio, inicio + porPagina.value);
});
const filtrosAvanzados = computed(
    () =>
        [
            familiaFiltro.value,
            controlVarianteFiltro.value,
            opcionFiltro.value,
            precioPreventaFiltro.value,
            precioEstimadoFiltro.value,
            tallasFiltro.value,
            cantidadFiltro.value,
            fichaFiltro.value,
        ].filter((valor) => String(valor ?? '').trim() !== '').length,
);

const filtrosActivos = computed(
    () =>
        filtrosAvanzados.value
        + (busqueda.value.trim() !== '' ? 1 : 0)
        + (estadoFiltro.value !== '' ? 1 : 0)
        + (categoriaFiltro.value !== '' ? 1 : 0),
);

const hayFiltros = computed(() => filtrosActivos.value > 0);

const cambiarPagina = (p: number) => {
    pagina.value = Math.min(Math.max(1, p), paginas.value);
};

const ordenarPor = (key: string) => {
    if (sortKey.value === key) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = key;
        sortDir.value = 'asc';
    }
    pagina.value = 1;
};

const indicador = (key: string) => (sortKey.value === key ? sortDir.value : null);
const ariaSort = (key: string): 'ascending' | 'descending' | 'none' =>
    sortKey.value !== key ? 'none' : sortDir.value === 'asc' ? 'ascending' : 'descending';

watch(
    [
        busqueda,
        estadoFiltro,
        categoriaFiltro,
        familiaFiltro,
        controlVarianteFiltro,
        opcionFiltro,
        precioPreventaFiltro,
        precioEstimadoFiltro,
        tallasFiltro,
        cantidadFiltro,
        fichaFiltro,
        porPagina,
    ],
    () => {
        pagina.value = 1;
    },
);

watch(paginas, (n) => {
    if (pagina.value > n) pagina.value = n;
});

watch(
    [busqueda, estadoFiltro, categoriaFiltro, familiaFiltro, controlVarianteFiltro, opcionFiltro, precioPreventaFiltro, precioEstimadoFiltro, tallasFiltro, cantidadFiltro, fichaFiltro, pagina],
    () => {
        seleccionados.clear();
    },
);

const accionFila = (accion: string, producto: any) => {
    data.Productoo = producto;

    if (accion === 'edit') data.editOpen = true;
    if (accion === 'tallas') {
        data.distribucionProducto = producto;
        data.distribucionOpen = true;
    }
    if (accion === 'delete') data.deleteOpen = true;
};

const limpiarFiltros = () => {
    busqueda.value = '';
    estadoFiltro.value = '';
    categoriaFiltro.value = '';
    familiaFiltro.value = '';
    controlVarianteFiltro.value = '';
    opcionFiltro.value = '';
    precioPreventaFiltro.value = '';
    precioEstimadoFiltro.value = '';
    tallasFiltro.value = '';
    cantidadFiltro.value = '';
    fichaFiltro.value = '';
};

const rutaIndex = new URL(String(route('Producto.index')), window.location.origin).pathname;

router.on('start', (event) => {
    const url = event.detail.visit.url;
    const pathname = url instanceof URL ? url.pathname : new URL(String(url), window.location.origin).pathname;
    if (pathname === rutaIndex) cargando.value = true;
});
router.on('finish', () => {
    cargando.value = false;
});

const recargar = () => {
    router.reload({ only: ['productos', 'total', 'perPage'], preserveScroll: true });
};
</script>

<template>

    <Head :title="props.title" />

    <AppLayout>
        <div class="flex flex-col gap-4 p-4 sm:p-6">
            <div class="flex flex-col gap-3">
                <nav class="flex items-center gap-1.5 text-xs text-muted-foreground" aria-label="Ruta de navegación">
                    <Link :href="route('dashboard')"
                        class="rounded-sm transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                        Dashboard
                    </Link>
                    <template v-for="(miga, i) in (props.breadcrumbs || [])" :key="i">
                        <ChevronRightIcon class="h-3 w-3 opacity-60" />
                        <Link v-if="miga.href" :href="miga.href"
                            class="rounded-sm transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                            {{ miga.label }}
                        </Link>
                        <span v-else class="font-medium text-foreground">{{ miga.label }}</span>
                    </template>
                </nav>

                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div class="min-w-0">
                        <h1 class="text-2xl font-semibold tracking-tight text-foreground">Productos</h1>
                        <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground"
                            role="status" aria-live="polite">
                            <span class="tabular-nums">
                                <span class="font-medium text-foreground">{{ totalRegistros }}</span>
                                {{ totalRegistros === 1 ? 'producto' : 'productos' }}
                            </span>
                            <span v-if="hayFiltros" class="text-border">·</span>
                            <span v-if="hayFiltros" class="tabular-nums">
                                de <span class="font-medium text-foreground">{{ totalProductos }}</span>
                            </span>
                            <span v-if="totalRegistros !== visibles.length" class="text-border">·</span>
                            <span v-if="totalRegistros !== visibles.length" class="tabular-nums">
                                <span class="font-medium text-foreground">{{ visibles.length }}</span>
                                en esta página
                            </span>
                            <span v-if="hayFiltros"
                                class="inline-flex items-center gap-1 rounded-full border border-border bg-muted/60 px-2 py-0.5 text-[11px] font-medium text-foreground">
                                <FunnelIcon class="h-3 w-3" />
                                {{ filtrosActivos }}
                                {{ filtrosActivos === 1 ? 'filtro activo' : 'filtros activos' }}
                            </span>
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" @click="recargar" :disabled="cargando" title="Actualizar datos"
                            aria-label="Actualizar datos"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:pointer-events-none disabled:opacity-50">
                            <ArrowPathIcon class="h-4 w-4" :class="{ 'animate-spin': cargando }" />
                        </button>

                        <PrimaryButton v-if="can(['create Producto'])" type="button"
                            class="h-9 bg-emerald-600 hover:bg-emerald-700 focus:ring-emerald-500"
                            @click="data.importOpen = true">
                            <ArrowUpTrayIcon class="mr-1.5 h-4 w-4" />
                            Cargar Excel
                        </PrimaryButton>

                        <PrimaryButton v-if="can(['create Producto'])" type="button" class="h-9"
                            @click="data.createOpen = true">
                            <PlusIcon class="mr-1.5 h-4 w-4" />
                            Nuevo producto
                        </PrimaryButton>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <div class="flex flex-col gap-2.5 border-b border-border p-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="relative min-w-[220px] flex-1">
                            <MagnifyingGlassIcon
                                class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <input v-model="busqueda" type="search" aria-label="Buscar productos"
                                placeholder="Buscar por código o título…"
                                class="h-9 w-full rounded-md border border-input bg-background pl-9 pr-9 text-sm text-foreground shadow-sm placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30" />
                            <button v-if="busqueda" type="button" @click="busqueda = ''" aria-label="Limpiar búsqueda"
                                class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-muted-foreground transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                                <XMarkIcon class="h-3.5 w-3.5" />
                            </button>
                        </div>

                        <select v-model="categoriaFiltro" aria-label="Filtrar por categoría"
                            :class="[selectClass, 'max-w-[220px]']">
                            <option value="">Todas las categorías</option>
                            <option v-for="opcion in categoriasDisponibles" :key="opcion.value" :value="opcion.value">
                                {{ opcion.label }}
                            </option>
                        </select>

                        <div class="flex items-center gap-1.5">
                            <span
                                class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Estado</span>
                            <div class="inline-flex h-9 items-stretch overflow-hidden rounded-md border border-border bg-card shadow-sm"
                                role="group" aria-label="Filtrar productos por estado">
                                <button v-for="opcion in opcionesEstado" :key="opcion.value || 'todos'" type="button"
                                    @click="estadoFiltro = opcion.value" :aria-pressed="estadoFiltro === opcion.value"
                                    :class="segmentoClass(estadoFiltro === opcion.value)">
                                    {{ opcion.label }}
                                    <span class="ml-1 tabular-nums opacity-70">{{ opcion.count }}</span>
                                </button>
                            </div>
                        </div>

                        <DropdownMenu>
                            <DropdownMenuTrigger type="button" :class="[
                                'inline-flex h-9 items-center gap-1.5 rounded-md border border-border bg-card px-3 text-xs font-medium text-foreground shadow-sm transition hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background',
                                filtrosAvanzados > 0 ? 'border-foreground/20 bg-muted' : '',
                            ]">
                                <FunnelIcon class="h-3.5 w-3.5" />
                                Más filtros
                                <span v-if="filtrosAvanzados > 0" class="tabular-nums">{{ filtrosAvanzados }}</span>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" class="max-h-[70vh] w-72 overflow-y-auto p-3">
                                <div class="flex flex-col gap-2.5" @click.stop>
                                    <label class="flex flex-col gap-1 text-xs font-medium text-muted-foreground">
                                        Familia
                                        <select v-model="familiaFiltro" :class="selectClass">
                                            <option value="">Todas</option>
                                            <option v-for="opcion in familiasDisponibles" :key="opcion.value"
                                                :value="opcion.value">
                                                {{ opcion.label }}
                                            </option>
                                        </select>
                                    </label>

                                    <label class="flex flex-col gap-1 text-xs font-medium text-muted-foreground">
                                        Control de variante
                                        <select v-model="controlVarianteFiltro" :class="selectClass">
                                            <option value="">Todos</option>
                                            <option v-for="opcion in controlesVarianteDisponibles" :key="opcion.value"
                                                :value="opcion.value">
                                                {{ opcion.label }}
                                            </option>
                                        </select>
                                    </label>

                                    <label class="flex flex-col gap-1 text-xs font-medium text-muted-foreground">
                                        Opciones contiene
                                        <input v-model="opcionFiltro" type="search" placeholder="Ej. S, M, L o 21-30"
                                            :class="selectClass" />
                                    </label>

                                    <label class="flex flex-col gap-1 text-xs font-medium text-muted-foreground">
                                        Precio preventa
                                        <select v-model="precioPreventaFiltro" :class="selectClass">
                                            <option v-for="opcion in opcionesPrecioPreventa" :key="opcion.value"
                                                :value="opcion.value">
                                                {{ opcion.label }}
                                            </option>
                                        </select>
                                    </label>

                                    <label class="flex flex-col gap-1 text-xs font-medium text-muted-foreground">
                                        Precio estimado
                                        <select v-model="precioEstimadoFiltro" :class="selectClass">
                                            <option v-for="opcion in opcionesPrecioEstimado" :key="opcion.value"
                                                :value="opcion.value">
                                                {{ opcion.label }}
                                            </option>
                                        </select>
                                    </label>

                                    <label class="flex flex-col gap-1 text-xs font-medium text-muted-foreground">
                                        Tallas
                                        <select v-model="tallasFiltro" :class="selectClass">
                                            <option v-for="opcion in opcionesTallas" :key="opcion.value"
                                                :value="opcion.value">
                                                {{ opcion.label }}
                                            </option>
                                        </select>
                                    </label>

                                    <label class="flex flex-col gap-1 text-xs font-medium text-muted-foreground">
                                        Cantidad planificada
                                        <select v-model="cantidadFiltro" :class="selectClass">
                                            <option v-for="opcion in opcionesCantidad" :key="opcion.value"
                                                :value="opcion.value">
                                                {{ opcion.label }}
                                            </option>
                                        </select>
                                    </label>

                                    <label class="flex flex-col gap-1 text-xs font-medium text-muted-foreground">
                                        Ficha técnica
                                        <select v-model="fichaFiltro" :class="selectClass">
                                            <option v-for="opcion in opcionesFicha" :key="opcion.value"
                                                :value="opcion.value">
                                                {{ opcion.label }}
                                            </option>
                                        </select>
                                    </label>
                                </div>
                            </DropdownMenuContent>
                        </DropdownMenu>

                        <select v-model.number="porPagina" aria-label="Registros por página" :class="selectClass">
                            <option v-for="n in [10, 25, 50, 100]" :key="n" :value="n">{{ n }} / página</option>
                        </select>

                        <button v-if="hayFiltros" type="button" @click="limpiarFiltros"
                            class="inline-flex h-9 items-center gap-1.5 rounded-md border border-border bg-card px-3 text-xs font-medium text-foreground shadow-sm transition hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background">
                            <XMarkIcon class="h-3.5 w-3.5" />
                            Limpiar
                        </button>
                    </div>
                </div>

                <div class="mat-scroll max-h-[68vh] overflow-auto overscroll-contain">
                    <table class="w-full min-w-[1330px] border-collapse text-sm">
                        <thead>
                            <tr>
                                <th v-if="can(['issuperadmin'])" scope="col" class="w-10 bg-muted px-3 py-2.5">
                                    <input type="checkbox" :checked="todosSeleccionados"
                                        :indeterminate="algunoSeleccionado" @change="toggleTodos"
                                        class="h-4 w-4 rounded border-border accent-foreground"
                                        aria-label="Seleccionar todos" />
                                </th>
                                <th v-for="col in columnas" :key="col.key" scope="col"
                                    :aria-sort="col.sortable ? ariaSort(col.key) : undefined" :class="[
                                        'bg-muted px-3 py-2.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground',
                                        col.align === 'right' ? 'text-right' : 'text-left',
                                        col.headerClass,
                                    ]">
                                    <button v-if="col.sortable" type="button" @click="ordenarPor(col.key)" :class="[
                                        'group/sort inline-flex items-center gap-1 rounded-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background',
                                        col.align === 'right' ? 'w-full justify-end' : '',
                                    ]">
                                        <span>{{ col.label }}</span>
                                        <span
                                            class="text-muted-foreground/60 transition-colors group-hover/sort:text-foreground">
                                            <ChevronUpIcon v-if="indicador(col.key) === 'asc'" class="h-3 w-3" />
                                            <ChevronDownIcon v-else-if="indicador(col.key) === 'desc'"
                                                class="h-3 w-3" />
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

                        <tbody v-if="cargando">
                            <tr v-for="n in 8" :key="'skeleton-' + n" class="border-b border-border/60">
                                <td :colspan="columnas.length + 1" class="px-4 py-3">
                                    <div class="flex items-center gap-4">
                                        <div class="h-3.5 w-20 animate-pulse rounded bg-muted"></div>
                                        <div class="h-3.5 w-56 animate-pulse rounded bg-muted"></div>
                                        <div class="h-3.5 flex-1 animate-pulse rounded bg-muted"></div>
                                        <div class="h-3.5 w-24 animate-pulse rounded bg-muted"></div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>

                        <tbody v-else-if="visibles.length" :key="paginaActual" class="mat-page">
                            <tr v-for="producto in visibles" :key="producto.id"
                                class="group border-b border-border/60 transition-colors last:border-0 hover:bg-muted/40"
                                :class="{ 'bg-primary/5': seleccionados.has(producto.id) }">
                                <td v-if="can(['issuperadmin'])" class="w-10 px-3 py-3 align-top">
                                    <input type="checkbox" :checked="seleccionados.has(producto.id)"
                                        @change="toggleProducto(producto.id)"
                                        class="h-4 w-4 rounded border-border accent-foreground"
                                        :aria-label="`Seleccionar ${producto.Codigo_interno || producto.Titulo_Shopify}`" />
                                </td>
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
                                        <span class="truncate font-medium text-foreground"
                                            :title="producto.Titulo_Shopify">
                                            {{ texto(producto.Titulo_Shopify) }}
                                        </span>
                                        <span class="truncate text-[11px] text-muted-foreground"
                                            :title="producto.descripcion">
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
                                        <span class="truncate text-sm text-foreground"
                                            :title="producto.Categoria_original">
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
                                        <span class="truncate text-sm text-foreground"
                                            :title="producto.Control_de_variante">
                                            {{ texto(producto.Control_de_variante) }}
                                        </span>
                                        <span class="truncate text-[11px] text-muted-foreground"
                                            :title="producto.Opciones">
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
                                        <span v-if="!tallasDe(producto).length"
                                            class="text-xs text-muted-foreground">—</span>
                                    </div>
                                </td>

                                <td
                                    class="sticky right-0 z-10 w-[104px] border-l border-border/60 bg-card px-2 py-2 align-middle shadow-[-8px_0_16px_-16px_rgba(0,0,0,0.35)] transition-colors group-hover:bg-muted/40">
                                    <div class="flex items-center justify-end gap-1">
                                        <button v-if="can(['update Producto'])" type="button"
                                            @click="accionFila('tallas', producto)" title="Tallas"
                                            :aria-label="`Editar tallas de ${producto.Codigo_interno || producto.Titulo_Shopify}`"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background">
                                            <SquaresPlusIcon class="h-4 w-4" />
                                        </button>
                                        <button v-if="can(['update Producto'])" type="button"
                                            @click="accionFila('edit', producto)" title="Editar"
                                            :aria-label="`Editar ${producto.Codigo_interno || producto.Titulo_Shopify}`"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background">
                                            <PencilIcon class="h-4 w-4" />
                                        </button>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger type="button" title="Acciones"
                                                :aria-label="`Acciones de ${producto.Codigo_interno || producto.Titulo_Shopify}`"
                                                class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background">
                                                <EllipsisVerticalIcon class="h-4 w-4" />
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" class="w-56 p-1">
                                                <DropdownMenuItem v-if="can(['delete Producto'])"
                                                    @click="accionFila('delete', producto)" variant="destructive"
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

                        <tbody v-else>
                            <tr>
                                <td :colspan="columnas.length + 1" class="px-6 py-16">
                                    <div class="mx-auto flex max-w-sm flex-col items-center text-center">
                                        <div
                                            class="flex h-12 w-12 items-center justify-center rounded-full border border-border bg-muted/60">
                                            <FunnelIcon v-if="hayFiltros" class="h-5 w-5 text-muted-foreground" />
                                            <CubeIcon v-else class="h-5 w-5 text-muted-foreground" />
                                        </div>
                                        <h3 class="mt-4 text-sm font-semibold text-foreground">
                                            {{ hayFiltros ? 'No hay productos que coincidan' : 'Aún no hay productos' }}
                                        </h3>
                                        <p class="mt-1 text-sm text-muted-foreground">
                                            {{
                                                hayFiltros
                                                    ? 'Ajusta los filtros o límpialos para ver más resultados.'
                                                    : 'Crea el primer producto o carga un Excel para empezar.'
                                            }}
                                        </p>
                                        <button v-if="hayFiltros" type="button" @click="limpiarFiltros"
                                            class="mt-4 inline-flex items-center gap-1.5 rounded-md border border-border bg-card px-3 py-1.5 text-xs font-medium text-foreground shadow-sm transition hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background">
                                            <XMarkIcon class="h-3.5 w-3.5" />
                                            Limpiar filtros
                                        </button>
                                        <PrimaryButton v-else-if="can(['create Producto'])" type="button" class="mt-4"
                                            @click="data.createOpen = true">
                                            <PlusIcon class="mr-1.5 h-4 w-4" />
                                            Crear el primer producto
                                        </PrimaryButton>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <DataPagination v-if="!cargando" :current-page="paginaActual" :last-page="ultimaPagina" :from="desde"
                    :to="hasta" :total="totalRegistros" :item-label="totalRegistros === 1 ? 'producto' : 'productos'"
                    @page="cambiarPagina" />
            </div>
        </div>

        <Teleport to="body">
            <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="translate-y-4 opacity-0"
                enter-to-class="translate-y-0 opacity-100" leave-active-class="transition duration-150 ease-in"
                leave-from-class="translate-y-0 opacity-100" leave-to-class="translate-y-4 opacity-0">
                <div v-if="can(['issuperadmin']) && seleccionados.size > 0"
                    class="fixed bottom-6 left-1/2 z-50 -translate-x-1/2">
                    <div
                        class="flex items-center gap-3 rounded-xl border border-border bg-card px-4 py-2.5 shadow-xl ring-1 ring-black/5">
                        <span class="text-sm font-medium text-foreground">
                            {{ seleccionados.size }}
                            {{ seleccionados.size === 1 ? 'producto seleccionado' : 'productos seleccionados' }}
                        </span>
                        <div class="h-4 w-px bg-border" />
                        <button type="button" @click="eliminarSeleccionados"
                            class="inline-flex items-center gap-1.5 rounded-md bg-red-600 px-3 py-1.5 text-xs font-medium text-white shadow-sm transition hover:bg-red-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2 focus-visible:ring-offset-background">
                            <TrashIcon class="h-3.5 w-3.5" />
                            Eliminar
                        </button>
                        <button type="button" @click="limpiarSeleccion"
                            class="inline-flex items-center gap-1 rounded-md px-2 py-1.5 text-xs font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                            <XMarkIcon class="h-3.5 w-3.5" />
                            Cancelar
                        </button>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <Teleport to="body">
            <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0"
                enter-to-class="opacity-100" leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100" leave-to-class="opacity-0">
                <div v-if="data.deleteBulkOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="data.deleteBulkOpen = false" />
                    <div class="relative w-full max-w-md rounded-xl border border-border bg-card p-6 shadow-xl">
                        <h2 class="text-base font-semibold text-foreground">Eliminar productos</h2>
                        <p class="mt-2 text-sm text-muted-foreground">
                            ¿Estás seguro de que deseas eliminar
                            <span class="font-semibold text-foreground">{{ seleccionados.size }}</span>
                            {{ seleccionados.size === 1 ? 'producto' : 'productos' }}?
                            Esta acción no se puede deshacer.
                        </p>
                        <div class="mt-6 flex justify-end gap-2">
                            <button type="button" @click="data.deleteBulkOpen = false" :disabled="data.deletingBulk"
                                class="inline-flex items-center rounded-md border border-border bg-card px-4 py-2 text-sm font-medium text-foreground shadow-sm transition hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50">
                                Cancelar
                            </button>
                            <button type="button" @click="confirmarEliminarBulk" :disabled="data.deletingBulk"
                                class="inline-flex items-center gap-1.5 rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-red-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:pointer-events-none disabled:opacity-50">
                                <TrashIcon class="h-4 w-4" />
                                {{ data.deletingBulk ? 'Eliminando...' : 'Sí, eliminar' }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <ImportarProductoModal :show="data.importOpen" @close="data.importOpen = false" />

        <Create v-if="can(['create Producto'])" :numberPermissions="props.numberPermissions" :titulos="titulos"
            :show="data.createOpen" @close="data.createOpen = false" :title="props.title"
            :losSelect="props.losSelect" />

        <Edit v-if="can(['update Producto'])" :titulos="titulos" :numberPermissions="props.numberPermissions"
            :show="data.editOpen" @close="data.editOpen = false" :Productoa="data.Productoo" :title="props.title"
            :losSelect="props.losSelect" />

        <Delete v-if="can(['delete Producto'])" :numberPermissions="props.numberPermissions" :show="data.deleteOpen"
            @close="data.deleteOpen = false" :Productoa="data.Productoo" :title="props.title" />

        <DistribucionTallas v-if="can(['update Producto'])" :show="data.distribucionOpen"
            @close="data.distribucionOpen = false" :title="props.title" :productoId="data.distribucionProducto?.id"
            :productoLabel="data.distribucionProducto?.Codigo_interno"
            :items="data.distribucionProducto?.distribuciones_por_tallas || []"
            :ordenesTallas="data.distribucionProducto?.ordenes_con_tallas || []" />

        <ModalDependenciasProducto :show="data.modalBloqueadosOpen" :bloqueados="data.productosBloqueados"
            :eliminados="data.productosEliminados" @close="data.modalBloqueadosOpen = false" />
    </AppLayout>
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
