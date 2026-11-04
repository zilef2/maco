<script setup lang="ts">
import { computed, onMounted, onUnmounted, reactive } from 'vue';
import { Search, X, PackageCheck, Boxes, Wrench, TrendingDown, Coins, Scale, ArrowDownToLine, CalendarDays } from 'lucide-vue-next';

interface DetalleItem {
    producto?: string;
    op?: string;
    talla?: string;
    cantidad?: number;
    fecha?: string;
    observaciones?: string;
    material?: string;
    precio_unitario?: number;
    costo_total?: number;
    planeado?: number;
    real?: number;
    desviacion?: number;
    tipo?: string;
    motivo?: string;
    estado?: string;
    [key: string]: any;
}

const props = defineProps<{
    show: boolean;
    tipo: string;
    titulo: string;
    items: DetalleItem[];
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const state = reactive({
    busqueda: '',
});

const iconoPorTipo = computed(() => {
    switch (props.tipo) {
        case 'pares_aprobados':
            return PackageCheck;
        case 'pares_recibidos':
            return Boxes;
        case 'en_reparacion':
            return Wrench;
        case 'no_recuperables':
            return TrendingDown;
        case 'costo_materiales':
            return Coins;
        case 'desviacion_costos':
            return Scale;
        case 'movimientos':
            return ArrowDownToLine;
        case 'ops_activas':
            return CalendarDays;
        default:
            return Boxes;
    }
});

const formatoPlata = (v: number) => '$' + Math.round(v || 0).toLocaleString('es-CO');
const formatoNumero = (v: number) => (v || 0).toLocaleString('es-CO');

const itemsFiltrados = computed(() => {
    const q = state.busqueda.trim().toLowerCase();
    if (!q) return props.items || [];

    return (props.items || []).filter((item) => {
        return (
            (item.producto && item.producto.toLowerCase().includes(q)) ||
            (item.op && item.op.toLowerCase().includes(q)) ||
            (item.material && item.material.toLowerCase().includes(q)) ||
            (item.talla && String(item.talla).toLowerCase().includes(q)) ||
            (item.motivo && item.motivo.toLowerCase().includes(q)) ||
            (item.estado && item.estado.toLowerCase().includes(q)) ||
            (item.tipo && item.tipo.toLowerCase().includes(q)) ||
            (item.fecha && item.fecha.toLowerCase().includes(q)) ||
            (item.observaciones && item.observaciones.toLowerCase().includes(q))
        );
    });
});

const totalSuma = computed(() => {
    const lista = itemsFiltrados.value;
    if (['pares_recibidos', 'pares_aprobados', 'en_reparacion', 'no_recuperables', 'ops_activas'].includes(props.tipo)) {
        return lista.reduce((acc, curr) => acc + (Number(curr.cantidad) || 0), 0);
    }
    if (props.tipo === 'costo_materiales') {
        return lista.reduce((acc, curr) => acc + (Number(curr.costo_total) || 0), 0);
    }
    if (props.tipo === 'desviacion_costos') {
        return lista.reduce((acc, curr) => acc + (Number(curr.desviacion) || 0), 0);
    }
    return lista.length;
});

const cerrar = () => {
    state.busqueda = '';
    emit('close');
};

const alPresionarEscape = (e: KeyboardEvent) => {
    if (e.key === 'Escape' && props.show) {
        cerrar();
    }
};

onMounted(() => {
    window.addEventListener('keydown', alPresionarEscape);
});

onUnmounted(() => {
    window.removeEventListener('keydown', alPresionarEscape);
});
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6">
        <div class="fixed inset-0 bg-black/60 backdrop-blur-xs transition-opacity" @click="cerrar" />

        <div
            class="relative z-10 flex max-h-[90vh] w-full max-w-4xl flex-col rounded-2xl border border-border bg-card shadow-2xl transition-all"
        >
            <div class="flex items-center justify-between border-b border-border px-6 py-4">
                <div class="flex items-center gap-3">
                    <div class="grid h-10 w-10 place-items-center rounded-xl bg-primary/10 text-primary">
                        <component :is="iconoPorTipo" class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-foreground">
                            {{ titulo }}
                        </h3>
                        <p class="text-xs text-muted-foreground">
                            Detalle acumulado del mes en curso
                        </p>
                    </div>
                </div>

                <button
                    type="button"
                    class="grid h-8 w-8 place-items-center rounded-lg text-muted-foreground transition-colors hover:bg-muted hover:text-foreground cursor-pointer"
                    @click="cerrar"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>

            <div class="flex flex-col gap-3 border-b border-border bg-muted/30 px-6 py-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="relative w-full sm:max-w-xs">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <input
                        v-model="state.busqueda"
                        type="text"
                        placeholder="Buscar por producto, OP, fecha..."
                        class="h-9 w-full rounded-lg border border-input bg-background pl-9 pr-3 text-xs text-foreground placeholder:text-muted-foreground focus:border-primary focus:outline-hidden focus:ring-1 focus:ring-primary"
                    />
                </div>

                <div class="flex items-center gap-4 text-xs">
                    <span class="text-muted-foreground">
                        Registros: <strong class="text-foreground">{{ itemsFiltrados.length }}</strong>
                    </span>
                    <span
                        v-if="['pares_recibidos', 'pares_aprobados', 'en_reparacion', 'no_recuperables', 'ops_activas'].includes(tipo)"
                        class="text-muted-foreground"
                    >
                        Total pares: <strong class="text-primary font-bold">{{ formatoNumero(totalSuma) }}</strong>
                    </span>
                    <span v-else-if="tipo === 'costo_materiales'" class="text-muted-foreground">
                        Costo total: <strong class="text-primary font-bold">{{ formatoPlata(totalSuma) }}</strong>
                    </span>
                    <span v-else-if="tipo === 'desviacion_costos'" class="text-muted-foreground">
                        Desviación total:
                        <strong :class="totalSuma > 0 ? 'text-red-500 font-bold' : 'text-emerald-500 font-bold'">
                            {{ totalSuma >= 0 ? '+' : '' }}{{ formatoPlata(totalSuma) }}
                        </strong>
                    </span>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto px-6 py-4">
                <div v-if="itemsFiltrados.length === 0" class="flex flex-col items-center justify-center py-12 text-center">
                    <div class="grid h-12 w-12 place-items-center rounded-2xl bg-muted text-muted-foreground">
                        <component :is="iconoPorTipo" class="h-6 w-6" />
                    </div>
                    <p class="mt-3 text-sm font-medium text-foreground">
                        {{ state.busqueda ? 'No se encontraron resultados para la búsqueda' : 'No hay registros en este período' }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ state.busqueda ? 'Intenta buscar con otros términos.' : 'Aún no se han registrado datos este mes.' }}
                    </p>
                </div>

                <div v-else class="overflow-x-auto rounded-lg border border-border">
                    <table
                        v-if="['pares_recibidos', 'pares_aprobados', 'en_reparacion', 'no_recuperables'].includes(tipo)"
                        class="w-full text-left text-xs"
                    >
                        <thead class="border-b border-border bg-muted/50 font-semibold text-muted-foreground">
                            <tr>
                                <th class="px-3 py-2.5">Producto</th>
                                <th class="px-3 py-2.5">OP</th>
                                <th class="px-3 py-2.5 text-center">Talla</th>
                                <th class="px-3 py-2.5 text-right">Cantidad</th>
                                <th class="px-3 py-2.5">Fecha</th>
                                <th class="px-3 py-2.5">Observaciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="(item, idx) in itemsFiltrados" :key="idx" class="transition-colors hover:bg-muted/30">
                                <td class="px-3 py-2.5 font-medium text-foreground">
                                    {{ item.producto }}
                                </td>
                                <td class="px-3 py-2.5 font-mono text-[11px] text-muted-foreground">
                                    {{ item.op }}
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    <span class="inline-block rounded-md bg-muted px-2 py-0.5 text-[11px] font-semibold text-foreground">
                                        {{ item.talla }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5 text-right font-bold tabular-nums text-foreground">
                                    {{ formatoNumero(item.cantidad || 0) }}
                                </td>
                                <td class="px-3 py-2.5 text-muted-foreground">
                                    {{ item.fecha }}
                                </td>
                                <td class="px-3 py-2.5 text-muted-foreground">
                                    {{ item.observaciones || '-' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <table v-else-if="tipo === 'costo_materiales'" class="w-full text-left text-xs">
                        <thead class="border-b border-border bg-muted/50 font-semibold text-muted-foreground">
                            <tr>
                                <th class="px-3 py-2.5">OP</th>
                                <th class="px-3 py-2.5">Material</th>
                                <th class="px-3 py-2.5 text-right">Cantidad</th>
                                <th class="px-3 py-2.5 text-right">Precio unitario</th>
                                <th class="px-3 py-2.5 text-right">Costo total</th>
                                <th class="px-3 py-2.5">Fecha</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="(item, idx) in itemsFiltrados" :key="idx" class="transition-colors hover:bg-muted/30">
                                <td class="px-3 py-2.5 font-mono text-[11px] text-muted-foreground">
                                    {{ item.op }}
                                </td>
                                <td class="px-3 py-2.5 font-medium text-foreground">
                                    {{ item.material }}
                                </td>
                                <td class="px-3 py-2.5 text-right tabular-nums text-foreground">
                                    {{ formatoNumero(item.cantidad || 0) }}
                                </td>
                                <td class="px-3 py-2.5 text-right tabular-nums text-muted-foreground">
                                    {{ formatoPlata(item.precio_unitario || 0) }}
                                </td>
                                <td class="px-3 py-2.5 text-right font-bold tabular-nums text-primary">
                                    {{ formatoPlata(item.costo_total || 0) }}
                                </td>
                                <td class="px-3 py-2.5 text-muted-foreground">
                                    {{ item.fecha }}
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <table v-else-if="tipo === 'desviacion_costos'" class="w-full text-left text-xs">
                        <thead class="border-b border-border bg-muted/50 font-semibold text-muted-foreground">
                            <tr>
                                <th class="px-3 py-2.5">OP</th>
                                <th class="px-3 py-2.5 text-right">Planeado</th>
                                <th class="px-3 py-2.5 text-right">Real final</th>
                                <th class="px-3 py-2.5 text-right">Desviación</th>
                                <th class="px-3 py-2.5">Fecha cierre</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="(item, idx) in itemsFiltrados" :key="idx" class="transition-colors hover:bg-muted/30">
                                <td class="px-3 py-2.5 font-mono text-[11px] text-muted-foreground">
                                    {{ item.op }}
                                </td>
                                <td class="px-3 py-2.5 text-right tabular-nums text-muted-foreground">
                                    {{ formatoPlata(item.planeado || 0) }}
                                </td>
                                <td class="px-3 py-2.5 text-right tabular-nums text-foreground">
                                    {{ formatoPlata(item.real || 0) }}
                                </td>
                                <td
                                    class="px-3 py-2.5 text-right font-bold tabular-nums"
                                    :class="(item.desviacion || 0) > 0 ? 'text-red-500' : 'text-emerald-500'"
                                >
                                    {{ (item.desviacion || 0) >= 0 ? '+' : '' }}{{ formatoPlata(item.desviacion || 0) }}
                                </td>
                                <td class="px-3 py-2.5 text-muted-foreground">
                                    {{ item.fecha }}
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <table v-else-if="tipo === 'movimientos'" class="w-full text-left text-xs">
                        <thead class="border-b border-border bg-muted/50 font-semibold text-muted-foreground">
                            <tr>
                                <th class="px-3 py-2.5">Material</th>
                                <th class="px-3 py-2.5">Tipo</th>
                                <th class="px-3 py-2.5 text-right">Cantidad</th>
                                <th class="px-3 py-2.5">Motivo</th>
                                <th class="px-3 py-2.5">Fecha</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="(item, idx) in itemsFiltrados" :key="idx" class="transition-colors hover:bg-muted/30">
                                <td class="px-3 py-2.5 font-medium text-foreground">
                                    {{ item.material }}
                                </td>
                                <td class="px-3 py-2.5">
                                    <span
                                        class="inline-block rounded-md px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider"
                                        :class="item.tipo === 'entrada' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-red-500/10 text-red-600 dark:text-red-400'"
                                    >
                                        {{ item.tipo }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5 text-right font-bold tabular-nums text-foreground">
                                    {{ formatoNumero(item.cantidad || 0) }}
                                </td>
                                <td class="px-3 py-2.5 text-muted-foreground">
                                    {{ item.motivo }}
                                </td>
                                <td class="px-3 py-2.5 text-muted-foreground">
                                    {{ item.fecha }}
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <table v-else class="w-full text-left text-xs">
                        <thead class="border-b border-border bg-muted/50 font-semibold text-muted-foreground">
                            <tr>
                                <th class="px-3 py-2.5">OP</th>
                                <th class="px-3 py-2.5">Producto</th>
                                <th class="px-3 py-2.5 text-right">Planificada</th>
                                <th class="px-3 py-2.5">Estado</th>
                                <th class="px-3 py-2.5">Fecha creación</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="(item, idx) in itemsFiltrados" :key="idx" class="transition-colors hover:bg-muted/30">
                                <td class="px-3 py-2.5 font-mono text-[11px] font-medium text-foreground">
                                    {{ item.op }}
                                </td>
                                <td class="px-3 py-2.5 text-muted-foreground">
                                    {{ item.producto }}
                                </td>
                                <td class="px-3 py-2.5 text-right font-bold tabular-nums text-foreground">
                                    {{ formatoNumero(item.cantidad || 0) }}
                                </td>
                                <td class="px-3 py-2.5">
                                    <span class="inline-block rounded-md bg-primary/10 px-2 py-0.5 text-[11px] font-semibold text-primary">
                                        {{ item.estado }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5 text-muted-foreground">
                                    {{ item.fecha }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex items-center justify-between border-t border-border px-6 py-3 bg-muted/20">
                <p class="text-xs text-muted-foreground">
                    Presiona <kbd class="rounded border border-border bg-muted px-1.5 py-0.5 text-[10px]">Esc</kbd> o clic afuera para cerrar
                </p>
                <button
                    type="button"
                    class="rounded-lg bg-secondary px-4 py-2 text-xs font-semibold text-secondary-foreground transition-colors hover:bg-secondary/80 cursor-pointer"
                    @click="cerrar"
                >
                    Cerrar
                </button>
            </div>
        </div>
    </div>
</template>
