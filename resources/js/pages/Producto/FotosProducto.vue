<script setup lang="ts">
import { XMarkIcon } from '@heroicons/vue/24/solid';
import { ref, watch } from 'vue';

interface FotoProducto {
    campo: string;
    etiqueta: string;
    archivo?: string | null;
    url: string | null;
}

const props = withDefaults(defineProps<{ fotos?: FotoProducto[] }>(), {
    fotos: () => [],
});

// Al hacer clic, el cuadro ampliado aparece de primero, centrado y de 250 px.
const seleccionada = ref<FotoProducto | null>(null);

const alternar = (foto: FotoProducto) => {
    seleccionada.value = seleccionada.value?.campo === foto.campo ? null : foto;
};

watch(
    () => props.fotos,
    (fotos) => {
        const previa = seleccionada.value;
        if (!previa) return;
        const actual = fotos.find((foto) => foto.campo === previa.campo);
        // Si la foto cambió o se quitó, el cuadro se cierra para no mostrar algo viejo.
        seleccionada.value = actual?.url && actual.archivo === previa.archivo ? actual : null;
    },
);
</script>

<template>
    <Transition enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 -translate-y-1 scale-95" enter-to-class="opacity-100 translate-y-0 scale-100"
        leave-active-class="transition duration-150 ease-in" leave-from-class="opacity-100 scale-100"
        leave-to-class="opacity-0 scale-95">
        <div v-if="seleccionada" class="mb-3 flex w-full justify-center">
            <div
                class="w-[250px] rounded-xl border border-gray-200 bg-white p-2 shadow-lg dark:border-gray-700 dark:bg-gray-900">
                <div class="overflow-hidden rounded-lg bg-gray-50 dark:bg-gray-800/60">
                    <img :src="seleccionada.url ?? ''" :alt="seleccionada.etiqueta"
                        class="max-h-[250px] w-full object-contain" />
                </div>
                <div class="mt-2 flex items-center justify-between gap-2 px-1">
                    <span class="text-xs font-semibold text-gray-600 dark:text-gray-300">
                        {{ seleccionada.etiqueta }}
                    </span>
                    <button type="button" @click="seleccionada = null" aria-label="Cerrar vista ampliada"
                        class="inline-flex h-6 w-6 items-center justify-center rounded-md text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200">
                        <XMarkIcon class="h-4 w-4" />
                    </button>
                </div>
            </div>
        </div>
    </Transition>

    <div v-if="fotos.length" class="flex flex-wrap gap-3">
        <figure v-for="foto in fotos" :key="foto.campo" class="group w-[100px]">
            <button type="button" @click="alternar(foto)" :aria-pressed="seleccionada?.campo === foto.campo"
                :aria-label="`Ampliar foto ${foto.etiqueta}`"
                class="block h-[100px] w-[100px] cursor-zoom-in overflow-hidden rounded-lg border bg-gray-50 transition duration-200 dark:bg-gray-900/40"
                :class="seleccionada?.campo === foto.campo
                    ? 'scale-105 border-indigo-500 ring-2 ring-indigo-400'
                    : 'border-gray-200 hover:border-indigo-400 hover:shadow-md dark:border-gray-700'">
                <img :src="foto.url ?? ''" :alt="foto.etiqueta"
                    class="h-full w-full object-cover transition-transform duration-300 ease-out group-hover:scale-110" />
            </button>
            <figcaption class="mt-1 text-center text-[11px] font-medium text-gray-500 dark:text-gray-400">
                {{ foto.etiqueta }}
            </figcaption>
        </figure>
    </div>
</template>
