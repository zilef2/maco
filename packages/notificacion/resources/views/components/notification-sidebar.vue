<template>
    <transition name="fade">
        <div v-if="notificaciones.length > 0" class="fixed top-4 right-4 z-50">
            <div class="flex flex-col gap-2 w-64 rounded-lg bg-white p-4 shadow-lg border">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-gray-700">Notificaciones</span>
                    <button @click="cerrar" class="text-gray-400 hover:text-gray-600">✕</button>
                </div>
                
                <div v-for="notif in notificaciones" :key="notif.id" class="p-3 rounded-md mb-2 animate-bounce" :class="['bg-'+notif.prioridad]">
                    <div class="flex items-start">
                        <span class="w-2 h-2 rounded-full mr-2" :style="{background: notif.prioridad === 'alto' ? '#ef4444' : notif.prioridad === 'media' ? '#f59e0b' : '#10b981'}"></span>
                        <div>
                            <p class="font-medium text-gray-900">{{ notif.mensaje }}</p>
                            <p class="text-xs text-gray-500 mt-1">{{ formatFecha(notif.created_at) }}</p>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <button @click="marcarTodasLeidas" class="w-full py-2 px-4 rounded-md text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                        Marcar todas como leídas
                    </button>
                </div>
            </div>
        </div>
    </transition>
</template>

<script setup>
import { defineComponent, ref, onMounted, watch } from 'vue';

const props = defineProps({
    usuarioId: { type: Number, default: null }
});

const notificaciones = ref([]);
const cerrar = ref(false);

const prioridadClass = {
    alta: 'bg-red-100 text-red-800',
    media: 'bg-amber-100 text-amber-800',
    baja: 'bg-green-100 text-green-800'
};

const formatFecha = (fecha) => {
    if (!fecha) return '';
    const d = new Date(fecha);
    return d.toLocaleDateString();
};

const marcarTodasLeidas = async () => {
    if (props.usuarioId) {
        await fetch('/api/notificaciones/marcar-todas', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
    }
    notificaciones.value = [];
};

onMounted(async () => {
    await cargarNotificaciones();
});

watch(() => props.usuarioId, async () => {
    await cargarNotificaciones();
});

async function cargarNotificaciones() {
    if (!props.usuarioId) return;
    
    try {
        const response = await fetch(`/api/notificaciones/usuario/${props.usuarioId}`, {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        const data = await response.json();
        notificaciones.value = data.notificaciones;
    } catch (error) {
        console.error('Error cargando notificaciones:', error);
    }
}
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.bg-alta { background-color: #fee2e2; }
.bg-media { background-color: #fef3c7; }
.bg-baja { background-color: #d1f2eb; }
</style>