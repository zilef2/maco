<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import {
    ArrowPathIcon,
    CheckIcon,
    ChevronDownIcon,
    ChevronRightIcon,
    CubeIcon,
    ExclamationTriangleIcon,
    MagnifyingGlassIcon,
    PrinterIcon,
    TagIcon,
    XMarkIcon,
} from '@heroicons/vue/24/solid';

import { formatdinero, zilef_nuber_format } from '@/global.ts';

type OrdenOp = {
    numero_op: string;
    requerido: number;
};

type OrdenFila = {
    material_id: number;
    codigo: string | null;
    nombre: string | null;
    categoria: string | null;
    codigo_categoria: string | null;
    unidad: string | null;
    requerido: number;
    existencia: number;
    faltante: number;
    precio: number | null;
    precio_fecha: string | null;
    proveedor: string | null;
    importe: number | null;
    ordenes: OrdenOp[];
};

const props = defineProps({
    orden: { type: Object as () => any, default: () => ({ filas: [] }) },
    breadcrumbs: { type: Array as () => Array<{ label?: string; href?: string }>, default: () => [] },
    title: { type: String, default: 'Orden de compra' },
});

const page = usePage();
const puedeEditarPrecio = computed(() => Boolean((page.props as any).auth?.can?.['update Material']));

const filas = computed<OrdenFila[]>(() => (props.orden?.filas ?? []) as OrdenFila[]);

const sinSigno = (valor: number) => formatdinero(valor).replace('$', '');

/** Precio listo para escribir en el campo: 1.250 o 1.250,50 cuando tiene centavos. */
const textoPrecio = (valor: number | null): string => {
    if (valor === null || valor === undefined) return '';

    const numero = Math.round(Number(valor) * 100) / 100;
    const centavos = Math.round((numero - Math.trunc(numero)) * 100);

    return centavos ? `${sinSigno(Math.trunc(numero))},${String(centavos).padStart(2, '0')}` : sinSigno(numero);
};

/**
 * Lo escrito en el campo: el punto separa miles y la coma decimales; un punto
 * seguido de una o dos cifras también vale como decimal (12.5).
 */
const leerPrecio = (texto: any): number | null => {
    const limpio = String(texto ?? '').replace(/[^\d.,]/g, '');
    if (!limpio) return null;

    const coma = limpio.lastIndexOf(',');
    const parteEntera = coma >= 0 ? limpio.slice(0, coma) : limpio.replace(/\.\d{1,2}$/, '');
    const decimales = coma >= 0
        ? limpio.slice(coma + 1).replace(/[^\d]/g, '').slice(0, 2)
        : (limpio.match(/\.(\d{1,2})$/)?.[1] ?? '');

    const numero = Number(`${parteEntera.replace(/[^\d]/g, '') || '0'}.${decimales || '0'}`);

    return Number.isFinite(numero) && numero > 0 ? Math.round(numero * 100) / 100 : null;
};

/** El precio escrito en pantalla y el precio con el que llegó la fila. */
const precios = reactive<Record<number, string>>({});
const precioBase = reactive<Record<number, number | null>>({});

const sincronizar = () => {
    filas.value.forEach((fila) => {
        precioBase[fila.material_id] = fila.precio;
        precios[fila.material_id] = textoPrecio(fila.precio);
    });
};

sincronizar();
watch(() => props.orden, sincronizar);

const precioEscrito = (fila: OrdenFila): number | null => leerPrecio(precios[fila.material_id]);

const precioRechazado = (fila: OrdenFila): boolean =>
    String(precios[fila.material_id] ?? '').trim() !== '' && precioEscrito(fila) === null;

const importeFila = (fila: OrdenFila): number | null => {
    const precio = precioEscrito(fila);

    return precio === null ? null : Math.round(fila.faltante * precio * 100) / 100;
};

const cantidad = (valor: number | null | undefined) => {
    const numero = Number(valor ?? 0);

    return zilef_nuber_format(numero, numero % 1 ? 2 : 0);
};

/** La fecha cruda, sin pasar por Date: el horario del navegador correría un día. */
const fechaCorta = (valor: string | null) => String(valor ?? '').slice(0, 10);

const totalEstimado = computed(() =>
    Math.round(filas.value.reduce((suma, fila) => suma + (importeFila(fila) ?? 0), 0) * 100) / 100,
);

const sinPrecio = computed(() => filas.value.filter((fila) => precioEscrito(fila) === null));

const ordenesPedidas = computed(() => {
    const numeros = new Set<string>();
    filas.value.forEach((fila) => (fila.ordenes ?? []).forEach((op) => numeros.add(op.numero_op)));

    return numeros.size;
});

const cambios = computed(() =>
    filas.value.filter((fila) => {
        const precio = precioEscrito(fila);

        return precio !== null && precio !== precioBase[fila.material_id];
    }),
);

const busqueda = ref('');
const soloSinPrecio = ref(false);
const criterio = ref<'faltante' | 'importe'>('faltante');
const abiertos = reactive<Record<number, boolean>>({});

const criterios = [
    { value: 'faltante', label: 'Por faltante' },
    { value: 'importe', label: 'Por importe' },
] as const;

type Modo = 'lista' | 'detalle';

const CLAVE_MODO = 'maco:Material.OrdenCompra.modo';
const CLAVE_MARCAS = 'maco:Material.OrdenCompra.marcados';

const leerLocal = (clave: string): string => {
    try {
        return globalThis.localStorage?.getItem(clave) ?? '';
    } catch {
        return '';
    }
};

const guardarLocal = (clave: string, valor: string): void => {
    try {
        globalThis.localStorage?.setItem(clave, valor);
    } catch {
        // Sin almacenamiento (modo privado) la pantalla sigue funcionando.
    }
};

const idsDeMarcas = (): number[] => {
    try {
        const guardado = JSON.parse(leerLocal(CLAVE_MARCAS) || '[]');

        return Array.isArray(guardado) ? guardado.map(Number).filter((id) => Number.isFinite(id)) : [];
    } catch {
        return [];
    }
};

const modos = [
    { value: 'lista', label: 'Lista', titulo: 'Lista de compras: un casillero por material' },
    { value: 'detalle', label: 'Detalle', titulo: 'Bodega, precio e importe de cada material' },
] as const;

const modo = ref<Modo>(leerLocal(CLAVE_MODO) === 'detalle' ? 'detalle' : 'lista');

/** Lo que ya se consiguió en el local; vive en el teléfono, no en la base. */
const marcados = reactive<Record<number, boolean>>(Object.fromEntries(idsDeMarcas().map((id) => [id, true])));

const guardarEstadoLocal = (): void => {
    guardarLocal(CLAVE_MODO, modo.value);
    const ids = filas.value.map((fila) => fila.material_id);
    const vigentes = Object.keys(marcados)
        .map(Number)
        .filter((id) => ids.includes(id));
    guardarLocal(CLAVE_MARCAS, JSON.stringify(vigentes));
};

const alternarMarca = (fila: OrdenFila): void => {
    if (marcados[fila.material_id]) {
        delete marcados[fila.material_id];
    } else {
        marcados[fila.material_id] = true;
    }

    guardarEstadoLocal();
};

const limpiarMarcas = (): void => {
    Object.keys(marcados).forEach((id) => delete marcados[Number(id)]);
    guardarEstadoLocal();
};

const marcadosCount = computed(() => filas.value.filter((fila) => marcados[fila.material_id]).length);

const progreso = computed(() =>
    filas.value.length ? Math.round((marcadosCount.value / filas.value.length) * 100) : 0,
);

watch(modo, (nuevo) => {
    guardarEstadoLocal();

    // En la lista no se ve ningún precio: dejar el filtro activo cortaría la
    // lista sin ningún control visible que lo explique.
    if (nuevo === 'lista') soloSinPrecio.value = false;
});

/** Precio con el que llegó la fila (para ordenar sin interferir con lo que se escribe). */
const precioParaOrden = (fila: OrdenFila): number | null => precioBase[fila.material_id] ?? fila.precio;

/** Importe usando el precio base, no el que se está escribiendo en pantalla. */
const importeBase = (fila: OrdenFila): number | null => {
    const precio = precioParaOrden(fila);

    return precio === null ? null : Math.round(fila.faltante * precio * 100) / 100;
};

const visibles = computed(() => {
    const texto = busqueda.value.trim().toLowerCase();

    const lista = filas.value.filter((fila) => {
        if (soloSinPrecio.value && precioParaOrden(fila) !== null) return false;
        if (texto === '') return true;

        return [fila.codigo, fila.nombre, fila.proveedor]
            .some((valor) => String(valor ?? '').toLowerCase().includes(texto));
    });

    const valor = (fila: OrdenFila) =>
        criterio.value === 'faltante' ? Number(fila.faltante) : (importeBase(fila) ?? 0);

    return [...lista].sort((a, b) => {
        // Los que todavía no tienen precio van primero: son los únicos que
        // dejan el total sin calcular. Se usa el precio base para no
        // reordenar mientras se escribe un nuevo precio.
        const falta = (fila: OrdenFila) => (precioParaOrden(fila) === null ? 0 : 1);
        if (modo.value === 'detalle' && falta(a) !== falta(b)) return falta(a) - falta(b);

        return valor(b) - valor(a);
    });
});

const nombreCategoria = (fila: OrdenFila): string =>
    String(fila.categoria ?? fila.codigo_categoria ?? '').trim() || 'Sin categoría';

/** Cada grupo es un pasillo del local: primero lo que falta, después lo marcado. */
const grupos = computed(() => {
    const mapa = new Map<string, OrdenFila[]>();

    visibles.value.forEach((fila) => {
        const clave = nombreCategoria(fila);
        const items = mapa.get(clave);
        if (items) {
            items.push(fila);
        } else {
            mapa.set(clave, [fila]);
        }
    });

    return [...mapa].map(([categoria, items]) => {
        const listos = items.filter((fila) => marcados[fila.material_id]);

        return {
            categoria,
            items: [...items.filter((fila) => !listos.includes(fila)), ...listos],
            listos: listos.length,
        };
    });
});

const limpiarFiltros = () => {
    busqueda.value = '';
    soloSinPrecio.value = false;
};

const form = useForm<{ materiales: Array<{ material_id: number; precio: number }> }>({ materiales: [] });

const errorLocal = ref('');

const erroresServidor = computed(() => [...new Set(Object.values(form.errors))]);

const descartar = () => {
    errorLocal.value = '';
    filas.value.forEach((fila) => {
        precios[fila.material_id] = textoPrecio(precioBase[fila.material_id]);
    });
};

const guardar = () => {
    errorLocal.value = '';

    const rechazados = filas.value.filter(precioRechazado);
    if (rechazados.length) {
        errorLocal.value =
            `El precio de ${rechazados.map((fila) => fila.codigo || fila.nombre).join(', ')} no sirve: `
            + 'escribe un número mayor que cero.';

        return;
    }

    if (!cambios.value.length) return;

    form.materiales = cambios.value.map((fila) => ({
        material_id: fila.material_id,
        precio: precioEscrito(fila) as number,
    }));

    form.post(route('Material.orden-compra.precios'), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            errorLocal.value = '';
        },
    });
};

const cargando = ref(false);

const recargar = () => {
    cargando.value = true;
    router.reload({ only: ['orden'], onFinish: () => (cargando.value = false) });
};
</script>

<template>

    <Head :title="props.title" />

    <AppLayout>
        <div class="flex flex-col gap-4 p-4 sm:p-6">
            <nav class="flex items-center gap-1.5 text-xs text-muted-foreground" aria-label="Ruta de navegación">
                <Link :href="route('dashboard')"
                    class="rounded-sm transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                    Dashboard
                </Link>
                <template v-for="(miga, i) in props.breadcrumbs" :key="i">
                    <ChevronRightIcon class="h-3 w-3 opacity-60" />
                    <Link v-if="miga.href" :href="miga.href"
                        class="rounded-sm transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                        {{ miga.label }}
                    </Link>
                    <span v-else class="font-medium text-foreground">{{ miga.label }}</span>
                </template>
            </nav>

            <div class="flex flex-wrap items-end justify-between gap-3">
                <div class="min-w-0 max-w-3xl">
                    <h1 class="text-xl font-semibold tracking-tight text-foreground sm:text-2xl">Orden de compra</h1>
                    <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
                        Cuánto hace falta comprar de cada material para cubrir las órdenes de producción que aún no
                        entran a taller.
                        <span class="hidden sm:inline">
                            Acá no se registra ninguna compra: en Lista se marca lo que ya se tiene y en Detalle se
                            actualiza el precio del material cuando el vigente ya no es el de la calle.
                        </span>
                        <Link :href="route('Explicaciones.index', { categoria: 'Material', subcategoria: 'Orden de compra' })"
                            class="whitespace-nowrap font-semibold text-foreground underline decoration-dotted underline-offset-2 transition hover:text-blue-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring dark:hover:text-blue-300">
                            Ver explicación
                        </Link>
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" @click="recargar" :disabled="cargando" title="Actualizar datos"
                        aria-label="Actualizar datos"
                        class="inline-flex h-11 w-11 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:pointer-events-none disabled:opacity-50 sm:h-9 sm:w-9">
                        <ArrowPathIcon class="h-4 w-4" :class="{ 'animate-spin': cargando }" />
                    </button>
                    <Link v-if="filas.length" :href="route('Material.orden-compra.imprimir')" target="_blank"
                        rel="noopener" title="Abrir la versión imprimible"
                        aria-label="Abrir la versión imprimible o en PDF de la orden de compra"
                        class="inline-flex h-11 items-center justify-center gap-1.5 rounded-md border border-border bg-card px-4 text-sm font-semibold text-foreground shadow-sm transition hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background sm:h-9 sm:px-3 sm:text-xs">
                        <PrinterIcon class="h-4 w-4 shrink-0 sm:h-3.5 sm:w-3.5" />
                        <span class="sm:hidden">Imprimir</span>
                        <span class="hidden sm:inline">Imprimir / PDF</span>
                    </Link>
                    <Link :href="route('Material.index')" aria-label="Volver a materiales"
                        class="inline-flex h-11 items-center justify-center gap-1.5 rounded-md border border-border bg-card px-4 text-sm font-semibold text-foreground shadow-sm transition hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background sm:h-9 sm:px-3 sm:text-xs">
                        <span class="sm:hidden">Materiales</span>
                        <span class="hidden sm:inline">Volver a materiales</span>
                    </Link>
                </div>
            </div>

            <!-- Sin carencias: las OPs abiertas ya están cubiertas -->
            <div v-if="!filas.length"
                class="flex flex-col items-start gap-3 rounded-xl border border-border bg-card p-5 shadow-sm sm:flex-row sm:items-center sm:p-6">
                <span
                    class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                    <CubeIcon class="h-5 w-5" />
                </span>
                <div class="min-w-0">
                    <h2 class="text-base font-semibold text-foreground sm:text-lg">No hay nada que comprar</h2>
                    <p class="mt-1 text-sm leading-relaxed text-muted-foreground">
                        La existencia actual cubre el kit de todas las órdenes de producción abiertas. Las OPs que ya
                        están en taller o cerradas no se cuentan: su material ya fue descontado.
                    </p>
                </div>
            </div>

            <template v-else>
                <!-- Toolbar: búsqueda, alertas y criterio de orden -->
                <div class="flex flex-wrap items-center gap-2">
                    <div class="relative basis-full min-w-0 flex-1 sm:flex-initial sm:max-w-sm">
                        <MagnifyingGlassIcon
                            class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <input v-model="busqueda" type="search" aria-label="Buscar en la orden de compra"
                            placeholder="Buscar código, material o proveedor…"
                            class="h-11 w-full rounded-md border border-input bg-background pl-9 pr-10 text-base text-foreground shadow-sm placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30 sm:h-9 sm:text-sm" />
                        <button v-if="busqueda" type="button" @click="busqueda = ''" aria-label="Limpiar búsqueda"
                            class="absolute right-1.5 top-1/2 -translate-y-1/2 rounded p-2.5 text-muted-foreground transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                            <XMarkIcon class="h-4 w-4" />
                        </button>
                    </div>

                    <div class="inline-flex h-11 min-w-0 flex-1 items-stretch overflow-hidden rounded-md border border-border bg-card shadow-sm sm:w-auto sm:flex-none"
                        role="group" aria-label="Organización de la orden">
                        <button v-for="opcion in modos" :key="opcion.value" type="button" :title="opcion.titulo"
                            @click="modo = opcion.value" :aria-pressed="modo === opcion.value" :class="[
                                'flex-1 px-2 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring sm:px-3 sm:text-xs',
                                modo === opcion.value
                                    ? 'bg-neutral-900 text-white hover:bg-neutral-800 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-200'
                                    : 'bg-card text-muted-foreground hover:bg-muted hover:text-foreground',
                            ]">
                            {{ opcion.label }}
                        </button>
                    </div>

                    <template v-if="modo === 'detalle'">
                        <button type="button" @click="soloSinPrecio = !soloSinPrecio" :aria-pressed="soloSinPrecio"
                            :class="[
                                'inline-flex h-11 flex-1 items-center justify-center gap-2 rounded-full border px-4 text-sm font-medium shadow-sm transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500/40 focus-visible:ring-offset-2 focus-visible:ring-offset-background sm:flex-none sm:px-3 sm:text-xs',
                                soloSinPrecio
                                    ? 'border-amber-500 bg-amber-500 text-white hover:bg-amber-600'
                                    : 'border-border bg-card text-muted-foreground hover:bg-muted hover:text-foreground',
                            ]">
                            <ExclamationTriangleIcon class="h-4 w-4 sm:h-3.5 sm:w-3.5" />
                            Sin precio
                            <span class="tabular-nums opacity-80">{{ sinPrecio.length }}</span>
                        </button>

                        <div class="inline-flex h-11 w-full items-stretch overflow-hidden rounded-md border border-border bg-card shadow-sm sm:h-9 sm:w-auto"
                            role="group" aria-label="Ordenar la lista">
                            <button v-for="opcion in criterios" :key="opcion.value" type="button"
                                @click="criterio = opcion.value" :aria-pressed="criterio === opcion.value" :class="[
                                    'flex-1 px-3 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring sm:text-xs',
                                    criterio === opcion.value
                                        ? 'bg-neutral-900 text-white hover:bg-neutral-800 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-200'
                                        : 'bg-card text-muted-foreground hover:bg-muted hover:text-foreground',
                                ]">
                                {{ opcion.label }}
                            </button>
                        </div>
                    </template>

                    <p class="ml-auto text-xs tabular-nums text-muted-foreground" role="status" aria-live="polite">
                        {{ visibles.length }} de {{ filas.length }} materiales
                        <template v-if="modo === 'lista'"> · {{ marcadosCount }} marcados</template>
                    </p>
                </div>

                <div v-if="errorLocal || erroresServidor.length" role="alert"
                    class="rounded-lg border border-red-300 bg-red-50/80 px-3 py-2.5 text-sm leading-relaxed text-red-800 dark:border-red-500/40 dark:bg-red-500/10 dark:text-red-200">
                    <p v-for="mensaje in [errorLocal, ...erroresServidor].filter(Boolean)" :key="mensaje">
                        {{ mensaje }}
                    </p>
                </div>

                <p v-if="!visibles.length" class="rounded-xl border border-border bg-card px-4 py-10 text-center shadow-sm">
                    <span class="block text-sm text-muted-foreground">
                        Ningún material de la orden coincide con el filtro
                    </span>
                    <button type="button" @click="limpiarFiltros"
                        class="mt-2 rounded px-1.5 py-1 text-sm font-semibold text-blue-700 underline transition hover:text-blue-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring dark:text-blue-300 dark:hover:text-blue-200">
                        Quitar filtros
                    </button>
                </p>

                <div v-else-if="modo === 'detalle'" class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                    <!-- La orden: tarjetas apiladas en celular, grilla de columnas en escritorio -->
                    <div aria-hidden="true"
                        class="hidden gap-x-3 border-b border-border bg-muted px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground xl:grid xl:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_104px_minmax(168px,1.1fr)_116px_36px]">
                        <span>Material</span>
                        <span>En bodega / lo que piden</span>
                        <span class="text-right">Hay que comprar</span>
                        <span class="text-right">Precio unitario</span>
                        <span class="text-right">Importe</span>
                        <span>Órdenes</span>
                    </div>

                    <ul role="list" class="divide-y divide-border/60">
                        <li v-for="fila in visibles" :key="fila.material_id"
                            class="grid grid-cols-[minmax(0,1fr)_auto] items-start gap-x-3 gap-y-3 p-3 transition-colors hover:bg-muted/40 xl:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_104px_minmax(168px,1.1fr)_116px_36px] xl:gap-x-3 xl:px-4 xl:py-3">
                            <!-- Material -->
                            <div class="col-start-1 min-w-0 row-start-1 xl:col-start-1 xl:row-start-1">
                                <p class="sr-only xl:hidden">
                                    Material
                                </p>
                                <p class="font-semibold tabular-nums tracking-tight text-foreground">
                                    {{ fila.codigo || '—' }}
                                </p>
                                <p class="truncate text-sm text-foreground" :title="fila.nombre ?? ''">
                                    {{ fila.nombre || '—' }}
                                </p>
                                <p v-if="fila.proveedor" class="truncate text-[11px] text-muted-foreground"
                                    :title="fila.proveedor">
                                    {{ fila.proveedor }}
                                </p>
                            </div>

                            <!-- En bodega / lo que piden -->
                            <div class="col-start-1 flex flex-col gap-0.5 row-start-2 xl:col-start-2 xl:row-start-1">
                                <p class="sr-only xl:hidden">
                                    En bodega y lo que piden las órdenes
                                </p>
                                <p class="text-xs tabular-nums text-muted-foreground">
                                    Hay {{ cantidad(fila.existencia) }} {{ fila.unidad || '' }}
                                </p>
                                <p class="text-xs tabular-nums text-muted-foreground">
                                    Piden {{ cantidad(fila.requerido) }} {{ fila.unidad || '' }}
                                </p>
                            </div>

                            <!-- Hay que comprar -->
                            <div class="col-start-2 justify-self-end row-start-1 text-right xl:col-start-3 xl:row-start-1">
                                <p class="text-[11px] uppercase tracking-[0.08em] text-muted-foreground xl:sr-only">
                                    Comprar
                                </p>
                                <p class="text-2xl font-semibold leading-tight tabular-nums text-foreground xl:text-lg">
                                    {{ cantidad(fila.faltante) }}
                                </p>
                                <p class="text-[11px] uppercase tracking-[0.08em] text-muted-foreground">
                                    {{ fila.unidad || 'unidad' }}
                                </p>
                            </div>

                            <!-- Precio unitario -->
                            <div class="col-span-full col-start-1 row-start-3 xl:col-span-1 xl:col-start-4 xl:row-start-1">
                                <p class="text-[11px] uppercase tracking-[0.08em] text-muted-foreground xl:sr-only">
                                    Precio unitario
                                </p>
                                <div class="relative mt-1 xl:mt-0 xl:text-right">
                                    <span
                                        class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-sm text-muted-foreground">$</span>
                                    <input v-model="precios[fila.material_id]" type="text" inputmode="decimal"
                                        :disabled="!puedeEditarPrecio" :aria-invalid="precioRechazado(fila)"
                                        :aria-label="`Precio unitario de ${fila.codigo || fila.nombre}`" placeholder="0"
                                        :class="[
                                            'h-11 w-full max-w-sm rounded-md border bg-background pl-7 pr-3 text-base tabular-nums text-foreground shadow-sm transition placeholder:text-muted-foreground focus:outline-none focus:ring-2 disabled:cursor-not-allowed disabled:bg-muted disabled:text-muted-foreground xl:h-9 xl:max-w-none xl:w-[152px] xl:pl-6 xl:text-sm',
                                            precioRechazado(fila)
                                                ? 'border-red-500 focus:border-red-500 focus:ring-red-500/30'
                                                : (precioEscrito(fila) === null
                                                    ? 'border-amber-500 focus:border-amber-500 focus:ring-amber-500/30'
                                                    : 'border-input focus:border-ring focus:ring-ring/30'),
                                        ]" />
                                </div>
                                <p v-if="fila.precio_fecha"
                                    class="mt-1 text-[11px] tabular-nums text-muted-foreground xl:text-right">
                                    vigente desde {{ fechaCorta(fila.precio_fecha) }}
                                </p>
                                <p v-else class="mt-1 text-[11px] text-muted-foreground xl:text-right">
                                    sin compras previas
                                </p>
                            </div>

                            <!-- Importe -->
                            <div class="col-span-full col-start-1 flex items-baseline justify-between gap-3 row-start-4 lg:max-w-sm xl:col-span-1 xl:col-start-5 xl:max-w-none xl:block xl:row-start-1 xl:text-right">
                                <p class="text-[11px] uppercase tracking-[0.08em] text-muted-foreground xl:sr-only">
                                    Importe
                                </p>
                                <p class="text-base font-medium tabular-nums text-foreground xl:text-sm">
                                    {{ importeFila(fila) === null ? '—' : formatdinero(importeFila(fila) as number) }}
                                </p>
                            </div>

                            <!-- Órdenes que piden este material -->
                            <button type="button" @click="abiertos[fila.material_id] = !abiertos[fila.material_id]"
                                :aria-expanded="Boolean(abiertos[fila.material_id])"
                                :aria-label="`Ver las ${(fila.ordenes ?? []).length} órdenes que piden ${fila.codigo || fila.nombre}`"
                                class="col-start-2 inline-flex h-11 items-center justify-end gap-1.5 row-start-2 self-start justify-self-end rounded-md border border-border bg-card px-3.5 text-sm font-medium tabular-nums text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring active:bg-muted xl:col-start-6 xl:h-8 xl:w-8 xl:justify-center xl:border-transparent xl:bg-transparent xl:px-0 xl:shadow-none xl:active:bg-muted xl:row-start-1">
                                <span class="xl:hidden">{{ (fila.ordenes ?? []).length }} OP</span>
                                <ChevronDownIcon class="h-4 w-4 shrink-0 transition-transform duration-200"
                                    :class="abiertos[fila.material_id] ? 'rotate-180' : ''" />
                            </button>

                            <!-- Detalle por orden -->
                            <div v-if="abiertos[fila.material_id]"
                                class="fade-in col-span-full col-start-1 flex flex-wrap items-center gap-2 rounded-lg bg-muted/50 p-2.5 row-start-5 xl:bg-muted/30 xl:col-start-1 xl:p-0 xl:pt-2 xl:row-start-2">
                                <span
                                    class="text-[10px] font-semibold uppercase tracking-[0.08em] text-muted-foreground">
                                    Lo piden {{ (fila.ordenes ?? []).length }} orden(es)
                                </span>
                                <span v-for="op in fila.ordenes" :key="op.numero_op"
                                    class="inline-flex items-center gap-1.5 rounded-full border border-border bg-card px-2.5 py-1 text-[11px] tabular-nums text-foreground shadow-xs">
                                    <span class="font-semibold">{{ op.numero_op }}</span>
                                    <span class="text-muted-foreground">
                                        pide {{ cantidad(op.requerido) }} {{ fila.unidad || '' }}
                                    </span>
                                </span>
                            </div>
                        </li>
                    </ul>
                </div>

                <div v-else class="flex flex-col gap-3">
                    <!-- Lista de compras: un bloque por categoría, casillero por material -->
                    <section v-for="grupo in grupos" :key="grupo.categoria"
                        class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                        <h2 class="flex items-baseline justify-between gap-3 border-b border-border bg-muted px-3 py-2 sm:px-4">
                            <span class="truncate text-xs font-semibold uppercase tracking-[0.08em] text-muted-foreground">
                                {{ grupo.categoria }}
                            </span>
                            <span class="shrink-0 text-[11px] font-medium tabular-nums text-muted-foreground">
                                {{ grupo.listos }}/{{ grupo.items.length }}
                            </span>
                        </h2>

                        <ul role="list" class="divide-y divide-border/60">
                            <li v-for="fila in grupo.items" :key="fila.material_id">
                                <button type="button" @click="alternarMarca(fila)"
                                    :aria-pressed="Boolean(marcados[fila.material_id])"
                                    :class="[
                                        'flex min-h-[68px] w-full items-center gap-3 px-3 text-left transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring sm:px-4',
                                        marcados[fila.material_id] ? 'bg-muted/40' : 'hover:bg-muted/40',
                                    ]" :aria-label="marcados[fila.material_id]
                                        ? `Quitar de comprado: ${fila.nombre || fila.codigo}`
                                        : `Marcar como comprado: ${fila.nombre || fila.codigo}, ${cantidad(fila.faltante)} ${fila.unidad || 'unidades'}`">
                                    <span :class="[
                                        'flex h-7 w-7 shrink-0 items-center justify-center rounded-md border-2 transition-colors',
                                        marcados[fila.material_id]
                                            ? 'border-emerald-600 bg-emerald-600 text-white'
                                            : 'border-input bg-background',
                                    ]" aria-hidden="true">
                                        <CheckIcon v-if="marcados[fila.material_id]" class="h-4 w-4" />
                                    </span>

                                    <span class="min-w-0 flex-1">
                                        <span :title="fila.nombre ?? ''" :class="[
                                            'block truncate text-base font-semibold leading-tight transition-colors',
                                            marcados[fila.material_id]
                                                ? 'text-muted-foreground line-through'
                                                : 'text-foreground',
                                        ]">
                                            {{ fila.nombre || '—' }}
                                        </span>
                                        <span class="mt-1 block truncate text-xs tabular-nums text-muted-foreground">
                                            {{ fila.codigo || '—' }}
                                        </span>
                                    </span>

                                    <span class="shrink-0 text-right">
                                        <span :class="[
                                            'block text-xl font-semibold leading-none tabular-nums',
                                            marcados[fila.material_id] ? 'text-muted-foreground' : 'text-foreground',
                                        ]">
                                            {{ cantidad(fila.faltante) }}
                                        </span>
                                        <span class="mt-1 block text-[10px] uppercase tracking-[0.08em] text-muted-foreground">
                                            {{ fila.unidad || 'unidades' }}
                                        </span>
                                    </span>
                                </button>
                            </li>
                        </ul>
                    </section>
                </div>

                <!-- Pie fijo: lo único que se puede hacer en la pantalla -->
                <div :class="[
                    'pie-orden sticky bottom-0 z-20 flex flex-wrap items-center justify-between gap-x-4 gap-y-2 rounded-xl border bg-card/95 px-3 py-3 shadow-lg backdrop-blur transition-colors sm:gap-y-3 sm:px-4',
                    modo === 'detalle' && cambios.length
                        ? 'border-sky-500/60 dark:border-sky-400/50'
                        : 'border-border',
                ]">
                    <template v-if="modo === 'lista'">
                        <div class="flex min-w-0 flex-wrap items-baseline gap-x-2.5 gap-y-1 sm:gap-x-3">
                            <span
                                class="text-[10px] font-semibold uppercase tracking-[0.12em] text-muted-foreground">Ya comprados</span>
                            <span class="text-2xl font-semibold tabular-nums text-foreground" role="status"
                                aria-live="polite">
                                {{ marcadosCount }} de {{ filas.length }}
                            </span>
                            <span class="text-xs tabular-nums text-muted-foreground">
                                {{ progreso }}% · {{ ordenesPedidas }} OP(s)
                            </span>
                            <button v-if="cambios.length" type="button" @click="modo = 'detalle'"
                                title="Volver al detalle para guardarlos"
                                class="inline-flex h-9 items-center gap-1.5 rounded-full border border-sky-400/70 bg-sky-50 px-3 text-[11px] font-medium tabular-nums text-sky-800 transition hover:bg-sky-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/40 dark:border-sky-400/40 dark:bg-sky-400/10 dark:text-sky-200">
                                <TagIcon class="h-3.5 w-3.5" />
                                {{ cambios.length }} precio(s) sin guardar
                            </button>
                        </div>

                        <div v-if="marcadosCount" class="flex w-full items-center gap-2 sm:w-auto sm:justify-end">
                            <SecondaryButton type="button"
                                class="h-11 flex-1 normal-case tracking-normal sm:h-9 sm:flex-none" @click="limpiarMarcas">
                                Limpiar marcas
                            </SecondaryButton>
                        </div>

                        <div class="h-1.5 basis-full overflow-hidden rounded-full bg-muted" aria-hidden="true">
                            <div class="h-full rounded-full bg-emerald-600 transition-all duration-200"
                                :style="{ width: `${progreso}%` }" />
                        </div>
                    </template>

                    <template v-else>
                        <div class="flex min-w-0 flex-wrap items-baseline gap-x-2.5 gap-y-1 sm:gap-x-3">
                            <span
                                class="text-[10px] font-semibold uppercase tracking-[0.12em] text-muted-foreground">Total de la orden</span>
                            <span class="text-2xl font-semibold tabular-nums text-foreground">
                                {{ formatdinero(totalEstimado) }}
                            </span>
                            <span class="text-xs tabular-nums text-muted-foreground">
                                {{ filas.length }} material(es) · {{ ordenesPedidas }} OP(s)
                            </span>
                            <span v-if="sinPrecio.length"
                                class="inline-flex items-center gap-1.5 rounded-full border border-amber-400/70 bg-amber-50 px-2.5 py-1 text-[11px] font-medium text-amber-800 dark:border-amber-400/40 dark:bg-amber-400/10 dark:text-amber-200">
                                <ExclamationTriangleIcon class="h-3.5 w-3.5" />
                                {{ sinPrecio.length }} sin precio
                            </span>
                            <span v-if="cambios.length"
                                class="inline-flex items-center gap-1.5 rounded-full border border-sky-400/70 bg-sky-50 px-2.5 py-1 text-[11px] font-medium tabular-nums text-sky-800 dark:border-sky-400/40 dark:bg-sky-400/10 dark:text-sky-200 sm:hidden"
                                role="status" aria-live="polite">
                                {{ cambios.length }} sin guardar
                            </span>
                        </div>

                        <div class="flex w-full items-center gap-2 sm:w-auto sm:justify-end">
                            <span v-if="cambios.length"
                                class="hidden text-xs font-medium tabular-nums text-sky-700 dark:text-sky-300 sm:inline">
                                {{ cambios.length }} precio(s) sin guardar
                            </span>
                            <SecondaryButton v-if="cambios.length" type="button"
                                class="h-11 flex-1 normal-case tracking-normal sm:h-9 sm:flex-none" @click="descartar">
                                Descartar
                            </SecondaryButton>
                            <PrimaryButton type="button"
                                class="h-11 flex-1 gap-1.5 normal-case tracking-normal disabled:cursor-not-allowed disabled:opacity-50 sm:h-9 sm:flex-none"
                                :disabled="!puedeEditarPrecio || !cambios.length || form.processing" @click="guardar">
                                <TagIcon class="h-4 w-4" />
                                {{ form.processing ? 'Guardando…' : 'Guardar precios' }}
                            </PrimaryButton>
                        </div>
                    </template>
                </div>

                <p v-if="modo === 'detalle' && !puedeEditarPrecio" class="text-xs text-muted-foreground">
                    Necesitas permiso de actualización de materiales para cambiar precios.
                </p>
            </template>
        </div>
    </AppLayout>

</template>

<style scoped>
.fade-in {
    animation: ordenFade 180ms ease-out;
}

@keyframes ordenFade {
    from {
        opacity: 0;
        transform: translateY(-2px);
    }

    to {
        opacity: 1;
        transform: none;
    }
}

@media (prefers-reduced-motion: reduce) {
    .fade-in {
        animation: none;
    }
}

input {
    -webkit-appearance: none;
    appearance: none;
}

/* En celular el buscador ya tiene su propio botón para limpiar. */
@media (max-width: 639px) {
    input[type='search']::-webkit-search-cancel-button {
        -webkit-appearance: none;
        appearance: none;
    }
}

input::selection {
    background: color-mix(in oklab, var(--color-ring, #6366f1) 28%, transparent);
}

/* El pie va pegado abajo: en iPhone el gestito de inicio lo taparía. */
.pie-orden {
    padding-bottom: max(0.75rem, env(safe-area-inset-bottom, 0px));
}
</style>
