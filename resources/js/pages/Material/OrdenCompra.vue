<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import {
    ArrowPathIcon,
    ChevronDownIcon,
    ChevronRightIcon,
    CubeIcon,
    ExclamationTriangleIcon,
    MagnifyingGlassIcon,
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

const visibles = computed(() => {
    const texto = busqueda.value.trim().toLowerCase();

    const lista = filas.value.filter((fila) => {
        if (soloSinPrecio.value && precioEscrito(fila) !== null) return false;
        if (texto === '') return true;

        return [fila.codigo, fila.nombre, fila.proveedor]
            .some((valor) => String(valor ?? '').toLowerCase().includes(texto));
    });

    const valor = (fila: OrdenFila) =>
        criterio.value === 'faltante' ? Number(fila.faltante) : (importeFila(fila) ?? 0);

    return [...lista].sort((a, b) => {
        // Los que todavía no tienen precio van primero: son los únicos que
        // dejan el total sin calcular.
        const falta = (fila: OrdenFila) => (precioEscrito(fila) === null ? 0 : 1);
        if (falta(a) !== falta(b)) return falta(a) - falta(b);

        return valor(b) - valor(a);
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
                    <h1 class="text-2xl font-semibold tracking-tight text-foreground">Orden de compra</h1>
                    <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
                        Cuánto hace falta comprar de cada material para cubrir las órdenes de producción que aún no
                        entran a taller: lo que piden las OP menos lo que hay en bodega. Acá no se registra ninguna
                        compra; se lee la necesidad y se actualiza el precio del material cuando el vigente ya no es
                        el de la calle.
                        <Link :href="route('Explicaciones.index', { categoria: 'Material', subcategoria: 'Orden de compra' })"
                            class="font-semibold text-foreground underline decoration-dotted underline-offset-2 transition hover:text-blue-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring dark:hover:text-blue-300">
                            Ver explicación
                        </Link>
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <button type="button" @click="recargar" :disabled="cargando" title="Actualizar datos"
                        aria-label="Actualizar datos"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-border bg-card text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:pointer-events-none disabled:opacity-50">
                        <ArrowPathIcon class="h-4 w-4" :class="{ 'animate-spin': cargando }" />
                    </button>
                    <Link :href="route('Material.index')"
                        class="inline-flex h-9 items-center gap-1.5 rounded-md border border-border bg-card px-3 text-xs font-semibold text-foreground shadow-sm transition hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background">
                        Volver a materiales
                    </Link>
                </div>
            </div>

            <!-- Sin carencias: las OPs abiertas ya están cubiertas -->
            <div v-if="!filas.length"
                class="flex flex-col items-start gap-3 rounded-xl border border-border bg-card p-6 shadow-sm sm:flex-row sm:items-center">
                <span
                    class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                    <CubeIcon class="h-5 w-5" />
                </span>
                <div class="min-w-0">
                    <h2 class="text-sm font-semibold text-foreground">No hay nada que comprar</h2>
                    <p class="mt-1 text-sm leading-relaxed text-muted-foreground">
                        La existencia actual cubre el kit de todas las órdenes de producción abiertas. Las OPs que ya
                        están en taller o cerradas no se cuentan: su material ya fue descontado.
                    </p>
                </div>
            </div>

            <template v-else>
                <!-- Toolbar: búsqueda, alertas y criterio de orden -->
                <div class="flex flex-wrap items-center gap-2">
                    <div class="relative min-w-[220px] flex-1 sm:max-w-sm">
                        <MagnifyingGlassIcon
                            class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <input v-model="busqueda" type="search" aria-label="Buscar en la orden de compra"
                            placeholder="Buscar código, material o proveedor…"
                            class="h-9 w-full rounded-md border border-input bg-background pl-9 pr-9 text-sm text-foreground shadow-sm placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30" />
                        <button v-if="busqueda" type="button" @click="busqueda = ''" aria-label="Limpiar búsqueda"
                            class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-muted-foreground transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                            <XMarkIcon class="h-3.5 w-3.5" />
                        </button>
                    </div>

                    <button type="button" @click="soloSinPrecio = !soloSinPrecio" :aria-pressed="soloSinPrecio"
                        :class="[
                            'inline-flex h-9 items-center gap-2 rounded-full border px-3 text-xs font-medium shadow-sm transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500/40 focus-visible:ring-offset-2 focus-visible:ring-offset-background',
                            soloSinPrecio
                                ? 'border-amber-500 bg-amber-500 text-white hover:bg-amber-600'
                                : 'border-border bg-card text-muted-foreground hover:bg-muted hover:text-foreground',
                        ]">
                        <ExclamationTriangleIcon class="h-3.5 w-3.5" />
                        Sin precio
                        <span class="tabular-nums opacity-80">{{ sinPrecio.length }}</span>
                    </button>

                    <div class="inline-flex h-9 items-stretch overflow-hidden rounded-md border border-border bg-card shadow-sm"
                        role="group" aria-label="Ordenar la lista">
                        <button v-for="opcion in criterios" :key="opcion.value" type="button"
                            @click="criterio = opcion.value" :aria-pressed="criterio === opcion.value" :class="[
                                'px-3 text-xs font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring',
                                criterio === opcion.value
                                    ? 'bg-neutral-900 text-white hover:bg-neutral-800 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-200'
                                    : 'bg-card text-muted-foreground hover:bg-muted hover:text-foreground',
                            ]">
                            {{ opcion.label }}
                        </button>
                    </div>

                    <p class="ml-auto text-xs tabular-nums text-muted-foreground" role="status" aria-live="polite">
                        {{ visibles.length }} de {{ filas.length }} materiales
                    </p>
                </div>

                <div v-if="errorLocal || erroresServidor.length" role="alert"
                    class="rounded-lg border border-red-300 bg-red-50/80 px-3 py-2 text-xs leading-relaxed text-red-800 dark:border-red-500/40 dark:bg-red-500/10 dark:text-red-200">
                    <p v-for="mensaje in [errorLocal, ...erroresServidor].filter(Boolean)" :key="mensaje">
                        {{ mensaje }}
                    </p>
                </div>

                <!-- La orden: una fila por material, con las OPs que lo piden escondidas -->
                <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[1000px] border-collapse text-sm">
                            <thead>
                                <tr class="border-b border-border bg-muted text-left text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground">
                                    <th scope="col" class="px-3 py-2.5">Material</th>
                                    <th scope="col" class="px-3 py-2.5">En bodega / lo que piden</th>
                                    <th scope="col" class="px-3 py-2.5 text-right">Hay que comprar</th>
                                    <th scope="col" class="w-[178px] px-3 py-2.5 text-right">Precio unitario</th>
                                    <th scope="col" class="w-[132px] px-3 py-2.5 text-right">Importe</th>
                                    <th scope="col" class="w-10 px-2 py-2.5"><span class="sr-only">Órdenes</span></th>
                                </tr>
                            </thead>

                            <tbody v-if="!visibles.length">
                                <tr>
                                    <td colspan="6" class="px-4 py-10 text-center">
                                        <p class="text-sm font-medium text-foreground">
                                            Ningún material de la orden coincide con el filtro
                                        </p>
                                        <button type="button" @click="limpiarFiltros"
                                            class="mt-2 rounded px-1.5 py-0.5 text-xs font-semibold text-blue-700 underline transition hover:text-blue-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring dark:text-blue-300">
                                            Quitar filtros
                                        </button>
                                    </td>
                                </tr>
                            </tbody>

                            <tbody v-else>
                                <template v-for="fila in visibles" :key="fila.material_id">
                                    <tr class="border-b border-border/60 transition-colors last:border-0 hover:bg-muted/40">
                                        <td class="px-3 py-3 align-top">
                                            <div class="flex max-w-[300px] flex-col gap-0.5">
                                                <span class="font-semibold tabular-nums tracking-tight text-foreground">
                                                    {{ fila.codigo || '—' }}
                                                </span>
                                                <span class="truncate text-sm text-foreground" :title="fila.nombre ?? ''">
                                                    {{ fila.nombre || '—' }}
                                                </span>
                                                <span v-if="fila.proveedor" class="truncate text-[11px] text-muted-foreground"
                                                    :title="fila.proveedor">
                                                    {{ fila.proveedor }}
                                                </span>
                                            </div>
                                        </td>

                                        <td class="px-3 py-3 align-top">
                                            <div class="flex flex-col gap-0.5 text-xs tabular-nums text-muted-foreground">
                                                <span>Hay {{ cantidad(fila.existencia) }} {{ fila.unidad || '' }}</span>
                                                <span>Piden {{ cantidad(fila.requerido) }} {{ fila.unidad || '' }}</span>
                                            </div>
                                        </td>

                                        <td class="px-3 py-3 text-right align-top">
                                            <p class="text-lg font-semibold leading-tight tabular-nums text-foreground">
                                                {{ cantidad(fila.faltante) }}
                                            </p>
                                            <p class="text-[11px] uppercase tracking-[0.08em] text-muted-foreground">
                                                {{ fila.unidad || 'unidad' }}
                                            </p>
                                        </td>

                                        <td class="px-3 py-3 text-right align-top">
                                            <div class="flex flex-col items-end gap-1">
                                                <div class="relative">
                                                    <span
                                                        class="pointer-events-none absolute left-2 top-1/2 -translate-y-1/2 text-xs text-muted-foreground">$</span>
                                                    <input v-model="precios[fila.material_id]" type="text" inputmode="numeric"
                                                        :disabled="!puedeEditarPrecio"
                                                        :aria-invalid="precioRechazado(fila)"
                                                        :aria-label="`Precio unitario de ${fila.codigo || fila.nombre}`"
                                                        placeholder="0"
                                                        :class="[
                                                            'h-9 w-[132px] rounded-md border bg-background pl-6 pr-2 text-right text-sm tabular-nums text-foreground shadow-sm transition placeholder:text-muted-foreground focus:outline-none focus:ring-2 disabled:cursor-not-allowed disabled:bg-muted disabled:text-muted-foreground',
                                                            precioRechazado(fila)
                                                                ? 'border-red-500 focus:border-red-500 focus:ring-red-500/30'
                                                                : (precioEscrito(fila) === null
                                                                    ? 'border-amber-500 focus:border-amber-500 focus:ring-amber-500/30'
                                                                    : 'border-input focus:border-ring focus:ring-ring/30'),
                                                        ]" />
                                                </div>
                                                <p v-if="fila.precio_fecha" class="text-[11px] tabular-nums text-muted-foreground">
                                                    vigente desde {{ fechaCorta(fila.precio_fecha) }}
                                                </p>
                                                <p v-else class="text-[11px] text-muted-foreground">
                                                    sin compras previas
                                                </p>
                                            </div>
                                        </td>

                                        <td class="px-3 py-3 text-right align-top">
                                            <span
                                                class="text-sm font-medium tabular-nums text-foreground">
                                                {{ importeFila(fila) === null ? '—' : formatdinero(importeFila(fila) as number) }}
                                            </span>
                                        </td>

                                        <td class="px-2 py-3 align-top">
                                            <button type="button" @click="abiertos[fila.material_id] = !abiertos[fila.material_id]"
                                                :aria-expanded="Boolean(abiertos[fila.material_id])"
                                                :aria-label="`Ver las órdenes que piden ${fila.codigo || fila.nombre}`"
                                                class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-transparent text-muted-foreground transition hover:border-border hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                                                <ChevronDownIcon class="h-4 w-4 transition-transform duration-200"
                                                    :class="abiertos[fila.material_id] ? 'rotate-180' : ''" />
                                            </button>
                                        </td>
                                    </tr>

                                    <tr v-if="abiertos[fila.material_id]" class="border-b border-border/60 last:border-0">
                                        <td colspan="6" class="bg-muted/30 px-4 pb-3.5 pt-1">
                                            <div class="fade-in flex flex-wrap items-center gap-2">
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
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pie fijo: el total de la orden y la única acción de la pantalla -->
                <div :class="[
                    'sticky bottom-0 z-20 flex flex-wrap items-center justify-between gap-x-4 gap-y-3 rounded-xl border bg-card/95 px-4 py-3 shadow-lg backdrop-blur transition-colors',
                    cambios.length
                        ? 'border-sky-500/60 dark:border-sky-400/50'
                        : 'border-border',
                ]">
                    <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                        <span
                            class="text-[10px] font-semibold uppercase tracking-[0.12em] text-muted-foreground">Total de la orden</span>
                        <span class="text-2xl font-semibold tabular-nums text-foreground">
                            {{ formatdinero(totalEstimado) }}
                        </span>
                        <span class="text-xs tabular-nums text-muted-foreground">
                            {{ filas.length }} material(es) · {{ ordenesPedidas }} OP(s) abiertas
                        </span>
                        <span v-if="sinPrecio.length"
                            class="inline-flex items-center gap-1.5 rounded-full border border-amber-400/70 bg-amber-50 px-2.5 py-1 text-[11px] font-medium text-amber-800 dark:border-amber-400/40 dark:bg-amber-400/10 dark:text-amber-200">
                            <ExclamationTriangleIcon class="h-3.5 w-3.5" />
                            {{ sinPrecio.length }} sin precio: no entran al total
                        </span>
                    </div>

                    <div class="flex items-center gap-2">
                        <span v-if="cambios.length" class="text-xs font-medium tabular-nums text-sky-700 dark:text-sky-300">
                            {{ cambios.length }} precio(s) sin guardar
                        </span>
                        <SecondaryButton v-if="cambios.length" type="button" class="normal-case tracking-normal"
                            @click="descartar">
                            Descartar
                        </SecondaryButton>
                        <PrimaryButton type="button"
                            class="h-9 gap-1.5 normal-case tracking-normal disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="!puedeEditarPrecio || !cambios.length || form.processing" @click="guardar">
                            <TagIcon class="h-4 w-4" />
                            {{ form.processing ? 'Guardando…' : 'Guardar precios' }}
                        </PrimaryButton>
                    </div>
                </div>

                <p v-if="!puedeEditarPrecio" class="text-xs text-muted-foreground">
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

input::selection {
    background: color-mix(in oklab, var(--color-ring, #6366f1) 28%, transparent);
}
</style>
