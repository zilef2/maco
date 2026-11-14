<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { FaltanteMaterial, SharedData } from '@/types';
import { Bell, CheckCheck, PackageX } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, reactive } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const props = defineProps<{
    /** Filtra la campana a un tipo de notificación (ej. material_insuficiente). */
    tipo?: string;
    /** Deja el botón visible aunque no haya notificaciones del tipo. */
    siempreVisible?: boolean;
}>();

interface NotificacionItem {
    id: number;
    mensaje: string;
    detalle?: string;
    prioridad: 'alta' | 'media' | 'baja' | string;
    created_at: string;
}

const page = usePage<SharedData>();

const esMaterial = computed(() => props.tipo === 'material_insuficiente');

// Los faltantes son un estado, no un aviso: se recalculan contra las OP abiertas
// (mismo origen que «Comprar faltantes») y desaparecen al cubrirse el material.
const faltantesOp = computed<FaltanteMaterial[]>(() => page.props.faltantesOp ?? []);

const numero = (valor: number | string) =>
    Number(valor).toLocaleString('es', { maximumFractionDigits: 4 });

const itemsMaterial = computed<NotificacionItem[]>(() =>
    faltantesOp.value.map((f) => {
        const ordenes = (f.ordenes ?? [])
            .map((o) => `${o.numero_op} necesita ${numero(o.requerido)}`)
            .join(' · ');

        return {
            id: f.material_id,
            mensaje: `${f.nombre}${f.codigo ? ` (${f.codigo})` : ''}: faltan ${numero(f.faltante)} ${f.unidad ?? ''}`.trim(),
            detalle: `${ordenes ? `${ordenes} · ` : ''}hay ${numero(f.existencia)}`,
            prioridad: 'alta',
            created_at: '',
        };
    }),
);

const query = computed(() => (props.tipo ? `?tipo=${encodeURIComponent(props.tipo)}` : ''));
const tituloBoton = computed(() =>
    esMaterial.value ? 'Faltantes de material' : 'Notificaciones pendientes',
);
const tituloPanel = computed(() => (esMaterial.value ? 'Faltantes de material' : 'Notificaciones'));
const mensajeVacio = computed(() =>
    esMaterial.value ? 'No hay faltantes de material pendientes' : 'No tienes notificaciones pendientes',
);

const state = reactive({
    notificaciones: [] as NotificacionItem[],
    cargando: false,
    timer: null as any,
    quitarListener: null as null | (() => void),
});

// La campana de faltantes no lista avisos históricos: muestra las carencias
// actuales, por eso comparte la fuente con «Comprar faltantes».
const items = computed<NotificacionItem[]>(() =>
    esMaterial.value ? itemsMaterial.value : state.notificaciones,
);

const formatFecha = (fecha: string) => {
    if (!fecha) return '';
    return new Date(fecha).toLocaleDateString([], {
        hour: '2-digit',
        minute: '2-digit',
        day: '2-digit',
        month: '2-digit',
    });
};

const cargarNotificaciones = async () => {
    if (esMaterial.value) return;
    state.cargando = true;
    try {
        const response = await fetch('/notificaciones' + query.value, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        if (response.ok) {
            const data = await response.json();
            state.notificaciones = data.notificaciones || [];
        }
    } catch {
        // Silencioso
    } finally {
        state.cargando = false;
    }
};

const getCsrfToken = () => {
    const meta = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null;
    return meta ? meta.content : '';
};

const marcarLeida = async (id: number) => {
    try {
        await fetch(`/notificaciones/marcar/${id}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        state.notificaciones = state.notificaciones.filter((n) => n.id !== id);
    } catch {
        // Silencioso
    }
};

const marcarTodasLeidas = async () => {
    try {
        await fetch('/notificaciones/marcar-todas' + query.value, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        state.notificaciones = [];
    } catch {
        // Silencioso
    }
};

onMounted(() => {
    // Los faltantes llegan por props en cada visita de Inertia; no hay nada
    // que sondear en el servidor para esta campana.
    if (esMaterial.value) return;

    cargarNotificaciones();
    state.timer = setInterval(cargarNotificaciones, 20000);
    // Al terminar una acción la campana se refresca de inmediato en vez de
    // esperar al siguiente sondeo.
    state.quitarListener = router.on('success', () => cargarNotificaciones());
});

onUnmounted(() => {
    if (state.timer) {
        clearInterval(state.timer);
    }
    state.quitarListener?.();
});
</script>

<template>
    <DropdownMenu v-if="siempreVisible || items.length > 0">
        <DropdownMenuTrigger :as-child="true">
            <Button
                variant="ghost"
                size="sm"
                class="relative h-9 w-9 p-0 text-muted-foreground hover:text-foreground cursor-pointer rounded-full border border-sidebar-border/60 hover:bg-accent transition-colors"
                :title="tituloBoton"
            >
                <PackageX v-if="esMaterial" class="h-4 w-4" />
                <Bell v-else class="h-4 w-4" />
                <span
                    v-if="items.length > 0"
                    class="absolute -top-1 -right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white shadow-xs"
                >
                    {{ items.length > 9 ? '9+' : items.length }}
                </span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-80 p-0 shadow-lg">
            <div class="flex items-center justify-between border-b border-sidebar-border/60 px-4 py-2.5">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-foreground">{{ tituloPanel }}</span>
                    <span
                        v-if="items.length > 0"
                        class="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary"
                    >
                        {{ items.length }}
                    </span>
                </div>
                <button
                    v-if="!esMaterial && items.length > 0"
                    @click="marcarTodasLeidas"
                    class="flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground cursor-pointer transition-colors"
                >
                    <CheckCheck class="h-3.5 w-3.5" />
                    <span>Marcar todas</span>
                </button>
            </div>

            <div class="max-h-80 overflow-y-auto divide-y divide-sidebar-border/40">
                <div v-if="items.length === 0" class="p-6 text-center text-sm text-muted-foreground">
                    {{ mensajeVacio }}
                </div>

                <DropdownMenuItem
                    v-for="notif in items"
                    :key="notif.id"
                    class="flex items-start gap-3 p-3 rounded-none focus:bg-muted/50"
                    :class="{ 'cursor-pointer': !esMaterial }"
                    @click="esMaterial ? undefined : marcarLeida(notif.id)"
                >
                    <span
                        class="mt-1.5 h-2 w-2 shrink-0 rounded-full"
                        :class="{
                            'bg-red-500': notif.prioridad === 'alta',
                            'bg-amber-500': notif.prioridad === 'media',
                            'bg-emerald-500': notif.prioridad === 'baja',
                        }"
                    />
                    <div class="flex-1 space-y-1">
                        <p class="text-xs font-medium text-foreground leading-tight">
                            {{ notif.mensaje }}
                        </p>
                        <p class="text-[10px] text-muted-foreground">
                            {{ esMaterial ? notif.detalle : formatFecha(notif.created_at) }}
                        </p>
                    </div>
                </DropdownMenuItem>
            </div>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
