<script setup lang="ts">
import Modal from '@/components/Modal.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import {
    CheckCircleIcon,
    ExclamationTriangleIcon,
    XMarkIcon,
} from '@heroicons/vue/24/solid';

interface ProductoBloqueado {
    id: number;
    codigo: string;
    titulo: string;
    motivos: string[];
}

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    bloqueados: {
        type: Array as () => ProductoBloqueado[],
        default: () => [],
    },
    eliminados: {
        type: Number,
        default: 0,
    },
});

const emit = defineEmits(['close']);
</script>

<template>
    <Modal :show="props.show" @close="emit('close')" maxWidth="xl">
        <div class="p-6">
            <div class="flex items-start gap-4">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400">
                    <ExclamationTriangleIcon class="h-6 w-6" />
                </div>
                <div class="min-w-0 flex-1">
                    <h2 class="text-lg font-semibold text-foreground">
                        No se pueden eliminar {{ props.bloqueados.length === 1 ? 'el producto' : 'estos productos' }}
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ props.bloqueados.length === 1 ? 'El siguiente producto tiene' : 'Los siguientes productos tienen' }}
                        registros ligados en el sistema y no se pueden borrar para no romper la integridad de datos.
                    </p>
                </div>
                <button
                    type="button"
                    @click="emit('close')"
                    class="rounded-lg p-1 text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                >
                    <XMarkIcon class="h-5 w-5" />
                </button>
            </div>

            <div
                v-if="props.eliminados > 0"
                class="mt-4 flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-800 dark:border-emerald-800/40 dark:bg-emerald-950/30 dark:text-emerald-300"
            >
                <CheckCircleIcon class="h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                <span>
                    Se {{ props.eliminados === 1 ? 'eliminó 1 producto' : `eliminaron ${props.eliminados} productos` }} que no tenía registros vinculados.
                </span>
            </div>

            <div class="mt-4 max-h-[50vh] space-y-3 overflow-y-auto pr-1">
                <div
                    v-for="prod in props.bloqueados"
                    :key="prod.id"
                    class="rounded-lg border border-border bg-muted/40 p-3.5"
                >
                    <div class="flex flex-wrap items-baseline gap-2">
                        <span class="rounded bg-background px-2 py-0.5 font-mono text-xs font-bold text-foreground ring-1 ring-border">
                            {{ prod.codigo }}
                        </span>
                        <span class="text-sm font-medium text-foreground">
                            {{ prod.titulo }}
                        </span>
                    </div>

                    <div class="mt-2.5 space-y-1">
                        <div
                            v-for="(motivo, i) in prod.motivos"
                            :key="i"
                            class="flex items-center gap-2 text-xs text-muted-foreground"
                        >
                            <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-amber-500" />
                            <span>{{ motivo }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <SecondaryButton type="button" @click="emit('close')">
                    Entendido
                </SecondaryButton>
            </div>
        </div>
    </Modal>
</template>

