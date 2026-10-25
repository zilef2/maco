<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import { ClipboardDocumentIcon, InformationCircleIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/solid';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { categoriasOrdenadas, explicaciones, type Explicacion } from '@/data/explicaciones';

const props = defineProps({
    title: String,
    // Vienen por query (?categoria=OP&subcategoria=Estado de la OP) para que
    // otra pantalla pueda enlazar directo a su explicación.
    categoria: String,
    subcategoria: String,
});

const busqueda = ref('');
const categoriaActiva = ref<string>(props.categoria || 'todas');
const subcategoriaActiva = ref<string>(props.subcategoria || 'todas');

const copiado = ref('');

const normalizar = (valor: unknown): string =>
    String(valor ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();

/** Todo el texto de una explicación, para que la búsqueda también cubra el cuerpo. */
const textoIndexable = (explicacion: Explicacion): string => {
    const partes: string[] = [explicacion.titulo, explicacion.resumen, explicacion.categoria, explicacion.subcategoria, ...(explicacion.etiquetas || [])];
    explicacion.bloques.forEach((bloque) => {
        if (bloque.tipo === 'texto' || bloque.tipo === 'nota' || bloque.tipo === 'subtitulo') partes.push(bloque.texto);
        if (bloque.tipo === 'lista' || bloque.tipo === 'pasos') partes.push(...bloque.items);
        if (bloque.tipo === 'tabla') partes.push(...bloque.columnas, ...bloque.filas.flat());
        if (bloque.tipo === 'preguntas') bloque.pares.forEach((par) => partes.push(par.pregunta, par.respuesta));
    });
    (explicacion.fuentes || []).forEach((fuente) => partes.push(fuente));
    return normalizar(partes.join(' '));
};

const filtradas = computed(() => {
    const texto = normalizar(busqueda.value);
    return explicaciones.filter((explicacion) => {
        if (categoriaActiva.value !== 'todas' && explicacion.categoria !== categoriaActiva.value) return false;
        if (subcategoriaActiva.value !== 'todas' && explicacion.subcategoria !== subcategoriaActiva.value) return false;
        return !texto || textoIndexable(explicacion).includes(texto);
    });
});

const subcategoriasDisponibles = computed(() => {
    const grupo = categoriasOrdenadas.find((c) => c.categoria === categoriaActiva.value);
    return grupo ? grupo.subcategorias : [];
});

const elegirCategoria = (categoria: string) => {
    categoriaActiva.value = categoria;
    subcategoriaActiva.value = 'todas';
};

// Las explicaciones se agrupan por categoría y subcategoría para que la página
// siga creciendo sin convertirse en una lista plana.
const grupos = computed(() => {
    const mapa = new Map<string, { categoria: string; subcategorias: Map<string, Explicacion[]> }>();
    filtradas.value.forEach((explicacion) => {
        const categoria = mapa.get(explicacion.categoria) ?? {
            categoria: explicacion.categoriaLabel,
            subcategorias: new Map<string, Explicacion[]>(),
        };
        const lista = categoria.subcategorias.get(explicacion.subcategoria) ?? [];
        lista.push(explicacion);
        categoria.subcategorias.set(explicacion.subcategoria, lista);
        mapa.set(explicacion.categoria, categoria);
    });
    return Array.from(mapa.values()).map((grupo) => ({
        categoria: grupo.categoria,
        subcategorias: Array.from(grupo.subcategorias.entries()).map(([subcategoria, items]) => ({ subcategoria, items })),
    }));
});

const tonoNota = (tono: 'info' | 'alerta' | 'ok'): string => {
    if (tono === 'alerta') {
        return 'border-amber-200 bg-amber-50/70 text-amber-800 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-300';
    }
    if (tono === 'ok') {
        return 'border-emerald-200 bg-emerald-50/70 text-emerald-800 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-300';
    }
    return 'border-indigo-200 bg-indigo-50/70 text-indigo-800 dark:border-indigo-800/60 dark:bg-indigo-950/40 dark:text-indigo-300';
};

const copiar = async (explicacion: Explicacion) => {
    try {
        await navigator.clipboard.writeText(JSON.stringify(explicacion, null, 2));
        copiado.value = explicacion.id;
        setTimeout(() => (copiado.value = ''), 2000);
    } catch {
        copiado.value = '';
    }
};
</script>

<template>
    <Head :title="props.title || 'Explicaciones'" />

    <AppLayout>
        <div class="flex flex-col gap-4 p-4 sm:p-6">
            <nav class="flex items-center gap-1.5 text-xs text-muted-foreground" aria-label="Ruta de navegación">
                <Link :href="route('dashboard')" class="rounded-sm transition-colors hover:text-foreground">Inicio</Link>
                <span>/</span>
                <span class="text-foreground">Explicaciones</span>
            </nav>

            <header class="flex flex-col gap-2">
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Explicaciones</h1>
                <p class="max-w-3xl text-sm text-gray-600 dark:text-gray-400">
                    Reglas del sistema escritas para que las lean tanto las personas como una IA: cada explicación trae su resumen, la tabla de
                    campos, los casos límite y los archivos donde está implementada. Contenido en
                    <code class="rounded bg-gray-100 px-1 py-0.5 text-xs dark:bg-gray-800">resources/js/data/explicaciones.ts</code>.
                </p>
            </header>

            <div class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900/40 sm:flex-row sm:items-center">
                <div class="relative flex-1">
                    <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <TextInput
                        type="search"
                        class="block w-full pl-9 text-sm"
                        v-model="busqueda"
                        placeholder="Buscar por título, regla o palabra clave…"
                        aria-label="Buscar explicaciones"
                    />
                </div>
                <div class="flex flex-wrap items-center gap-1.5">
                    <button
                        type="button"
                        @click="elegirCategoria('todas')"
                        :class="[
                            'rounded-full border px-3 py-1 text-xs font-medium transition',
                            categoriaActiva === 'todas'
                                ? 'border-indigo-600 bg-indigo-600 text-white'
                                : 'border-gray-300 bg-white text-gray-600 hover:border-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300',
                        ]"
                    >
                        Todas
                    </button>
                    <button
                        v-for="grupo in categoriasOrdenadas"
                        :key="grupo.categoria"
                        type="button"
                        @click="elegirCategoria(grupo.categoria)"
                        :class="[
                            'rounded-full border px-3 py-1 text-xs font-medium transition',
                            categoriaActiva === grupo.categoria
                                ? 'border-indigo-600 bg-indigo-600 text-white'
                                : 'border-gray-300 bg-white text-gray-600 hover:border-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300',
                        ]"
                    >
                        {{ grupo.categoria }}
                    </button>
                </div>
            </div>

            <div v-if="categoriaActiva !== 'todas' && subcategoriasDisponibles.length > 1" class="flex flex-wrap items-center gap-1.5">
                <button
                    type="button"
                    @click="subcategoriaActiva = 'todas'"
                    :class="[
                        'rounded-md border px-2.5 py-1 text-xs font-medium transition',
                        subcategoriaActiva === 'todas'
                            ? 'border-gray-400 bg-gray-100 text-gray-800 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200'
                            : 'border-transparent text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800',
                    ]"
                >
                    Todas las subcategorías
                </button>
                <button
                    v-for="subcategoria in subcategoriasDisponibles"
                    :key="subcategoria"
                    type="button"
                    @click="subcategoriaActiva = subcategoria"
                    :class="[
                        'rounded-md border px-2.5 py-1 text-xs font-medium transition',
                        subcategoriaActiva === subcategoria
                            ? 'border-gray-400 bg-gray-100 text-gray-800 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200'
                            : 'border-transparent text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800',
                    ]"
                >
                    {{ subcategoria }}
                </button>
            </div>

            <p v-if="!filtradas.length" class="rounded-lg border border-dashed border-gray-300 px-4 py-8 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                No hay explicaciones para esa búsqueda.
            </p>

            <section v-for="grupo in grupos" :key="grupo.categoria" class="flex flex-col gap-6">
                <div v-for="sub in grupo.subcategorias" :key="sub.subcategoria" class="flex flex-col gap-3">
                    <h2 class="flex items-center gap-2 text-sm font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                        <InformationCircleIcon class="h-4 w-4" />
                        {{ grupo.categoria }} — {{ sub.subcategoria }}
                    </h2>

                    <article
                        v-for="explicacion in sub.items"
                        :key="explicacion.id"
                        :id="explicacion.id"
                        class="scroll-mt-24 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900/40"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="max-w-3xl">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ explicacion.titulo }}</h3>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ explicacion.resumen }}</p>
                            </div>
                            <div class="flex flex-col items-end gap-2">
                                <SecondaryButton type="button" class="text-xs" @click="copiar(explicacion)">
                                    <ClipboardDocumentIcon class="mr-1 h-4 w-4" />
                                    {{ copiado === explicacion.id ? 'Copiado' : 'Copiar para IA' }}
                                </SecondaryButton>
                                <span class="text-[11px] text-gray-400 dark:text-gray-500">Actualizada: {{ explicacion.actualizada }}</span>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-col gap-4">
                            <template v-for="(bloque, index) in explicacion.bloques" :key="`${explicacion.id}-${index}`">
                                <p v-if="bloque.tipo === 'texto'" class="text-sm text-gray-700 dark:text-gray-300">
                                    {{ bloque.texto }}
                                </p>

                                <h4 v-else-if="bloque.tipo === 'subtitulo'"
                                    class="pt-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">
                                    {{ bloque.texto }}
                                </h4>

                                <ol v-else-if="bloque.tipo === 'pasos'" class="space-y-1.5">
                                    <li v-for="(paso, i) in bloque.items" :key="i" class="flex gap-2 text-sm text-gray-700 dark:text-gray-300">
                                        <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-[11px] font-bold text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300">
                                            {{ i + 1 }}
                                        </span>
                                        <span>{{ paso }}</span>
                                    </li>
                                </ol>

                                <ul v-else-if="bloque.tipo === 'lista'" class="space-y-1.5">
                                    <li v-for="(item, i) in bloque.items" :key="i" class="flex gap-2 text-sm text-gray-700 dark:text-gray-300">
                                        <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-gray-400"></span>
                                        <span>{{ item }}</span>
                                    </li>
                                </ul>

                                <div v-else-if="bloque.tipo === 'nota'" :class="['rounded-lg border px-4 py-3 text-sm', tonoNota(bloque.tono)]">
                                    {{ bloque.texto }}
                                </div>

                                <div v-else-if="bloque.tipo === 'tabla'" class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr class="border-b border-gray-200 bg-gray-50 text-left text-[11px] font-semibold tracking-wider text-gray-500 uppercase dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400">
                                                <th v-for="columna in bloque.columnas" :key="columna" class="px-3 py-2 font-semibold">{{ columna }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                                            <tr v-for="(fila, i) in bloque.filas" :key="i">
                                                <td
                                                    v-for="(celda, j) in fila"
                                                    :key="j"
                                                    :class="[
                                                        'px-3 py-2',
                                                        j === 0 ? 'font-medium text-gray-900 dark:text-gray-100' : 'text-gray-600 dark:text-gray-300',
                                                    ]"
                                                >
                                                    {{ celda }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <dl v-else-if="bloque.tipo === 'preguntas'" class="divide-y divide-gray-100 dark:divide-gray-700/60">
                                    <div v-for="(par, i) in bloque.pares" :key="i" class="py-3">
                                        <dt class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ par.pregunta }}</dt>
                                        <dd class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ par.respuesta }}</dd>
                                    </div>
                                </dl>
                            </template>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-1.5">
                            <span
                                v-for="etiqueta in explicacion.etiquetas"
                                :key="etiqueta"
                                class="rounded-full border border-gray-200 bg-gray-50 px-2 py-0.5 text-[11px] text-gray-500 dark:border-gray-700 dark:bg-gray-800/60 dark:text-gray-400"
                            >
                                {{ etiqueta }}
                            </span>
                        </div>

                        <details v-if="explicacion.fuentes?.length" class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                            <summary class="cursor-pointer font-medium hover:text-gray-700 dark:hover:text-gray-300">Dónde está implementado</summary>
                            <ul class="mt-2 space-y-1">
                                <li v-for="fuente in explicacion.fuentes" :key="fuente" class="font-mono">{{ fuente }}</li>
                            </ul>
                        </details>
                    </article>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
