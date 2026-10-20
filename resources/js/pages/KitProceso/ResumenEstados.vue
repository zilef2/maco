<script setup lang="ts">
type EstadoResumen = {
    clave: string;
    label: string;
    count: number;
};

defineProps<{
    estados: EstadoResumen[];
}>();
</script>

<template>
    <div class="flex flex-wrap items-center gap-1.5">
        <span v-for="estado in estados" :key="estado.clave"
            class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold"
            :class="estado.clave === 'sin-estado'
                ? 'border-dashed border-amber-300 bg-amber-50/80 text-amber-800 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200'
                : 'border-amber-200/80 bg-white/80 text-amber-900 shadow-sm dark:border-amber-400/25 dark:bg-amber-400/10 dark:text-amber-100'"
            :title="`${estado.count} registro(s) con estado ${estado.label}`">
            <span class="h-1.5 w-1.5 rounded-full"
                :class="estado.clave === 'sin-estado' ? 'bg-amber-400' : 'bg-amber-500'" />
            <span>{{ estado.label }}</span>
            <span
                class="rounded-full bg-amber-100 px-1.5 py-0.5 text-[11px] font-bold tabular-nums text-amber-900 dark:bg-amber-400/20 dark:text-amber-100">
                {{ estado.count }}
            </span>
        </span>

        <span v-if="!estados.length" class="text-xs text-amber-700/70 dark:text-amber-200/70">
            Sin estados capturados
        </span>
    </div>
</template>
