<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Bell, CheckCheck } from 'lucide-vue-next';
import { onMounted, onUnmounted, reactive } from 'vue';

interface NotificacionItem {
    id: number;
    mensaje: string;
    prioridad: 'alta' | 'media' | 'baja' | string;
    created_at: string;
}

const state = reactive({
    notificaciones: [] as NotificacionItem[],
    cargando: false,
    timer: null as any,
});

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
    state.cargando = true;
    try {
        const response = await fetch('/notificaciones', {
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
        await fetch('/notificaciones/marcar-todas', {
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
    cargarNotificaciones();
    state.timer = setInterval(cargarNotificaciones, 20000);
});

onUnmounted(() => {
    if (state.timer) {
        clearInterval(state.timer);
    }
});
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger :as-child="true">
            <Button
                variant="ghost"
                size="sm"
                class="relative h-9 w-9 p-0 text-muted-foreground hover:text-foreground cursor-pointer border border-sidebar-border/60 rounded-md"
            >
                <Bell class="h-4 w-4" />
                <span
                    v-if="state.notificaciones.length > 0"
                    class="absolute -top-1 -right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white shadow-xs"
                >
                    {{ state.notificaciones.length > 9 ? '9+' : state.notificaciones.length }}
                </span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-80 p-0 shadow-lg">
            <div class="flex items-center justify-between border-b border-sidebar-border/60 px-4 py-2.5">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-foreground">Notificaciones</span>
                    <span
                        v-if="state.notificaciones.length > 0"
                        class="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary"
                    >
                        {{ state.notificaciones.length }}
                    </span>
                </div>
                <button
                    v-if="state.notificaciones.length > 0"
                    @click="marcarTodasLeidas"
                    class="flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground cursor-pointer transition-colors"
                >
                    <CheckCheck class="h-3.5 w-3.5" />
                    <span>Marcar todas</span>
                </button>
            </div>

            <div class="max-h-80 overflow-y-auto divide-y divide-sidebar-border/40">
                <div v-if="state.notificaciones.length === 0" class="p-6 text-center text-sm text-muted-foreground">
                    No tienes notificaciones pendientes
                </div>

                <DropdownMenuItem
                    v-for="notif in state.notificaciones"
                    :key="notif.id"
                    class="flex items-start gap-3 p-3 cursor-pointer focus:bg-muted/50 rounded-none"
                    @click="marcarLeida(notif.id)"
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
                            {{ formatFecha(notif.created_at) }}
                        </p>
                    </div>
                </DropdownMenuItem>
            </div>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
