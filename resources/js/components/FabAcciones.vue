<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import gsap from 'gsap';
import { PlusIcon } from '@heroicons/vue/24/solid';

type FabItem = {
    id: string;
    label: string;
    icon: any;
};

const props = withDefaults(
    defineProps<{
        items: FabItem[];
        radius?: number;
        distancia?: number;
        permanencia?: number;
        direccion?: 'left' | 'right';
        span?: number;
    }>(),
    {
        radius: 130,
        distancia: 150,
        permanencia: 4000,
        direccion: 'left',
        span: 135,
    },
);

const emit = defineEmits<{
    (e: 'select', id: string): void;
}>();

const raiz = ref<HTMLElement | null>(null);
const boton = ref<HTMLElement | null>(null);
const itemsRefs = ref<HTMLElement[]>([]);
const expandido = ref(false);

let timeoutCierre: number | null = null;
let timeline: gsap.core.Timeline | null = null;

/** Arco de apertura: 'left' = 180°(izquierda)→hacia arriba; 'right' = 270°(arriba)→hacia la derecha */
const posiciones = computed(() => {
    const n = props.items.length;
    const base = props.direccion === 'right' ? 270 : 180;
    return props.items.map((_, i) => {
        const grados = base + (props.span * (i + 1)) / (n + 1);
        const rad = (grados * Math.PI) / 180;
        return { x: Math.cos(rad) * props.radius, y: Math.sin(rad) * props.radius };
    });
});

function setItemRef(el: any, i: number) {
    if (el) itemsRefs.value[i] = el as HTMLElement;
}

function cancelarTimeout() {
    if (timeoutCierre !== null) {
        window.clearTimeout(timeoutCierre);
        timeoutCierre = null;
    }
}

function expandir() {
    cancelarTimeout();
    if (expandido.value) return;
    expandido.value = true;
    nextTick(() => {
        timeline?.kill();
        timeline = gsap.timeline();
        timeline.to(boton.value, { rotate: 135, scale: 1.1, duration: 0.35, ease: 'back.out(2.5)' }, 0);
        itemsRefs.value.forEach((el, i) => {
            if (!el) return;
            const p = posiciones.value[i];
            timeline.to(
                el,
                { x: p.x, y: p.y, scale: 1, opacity: 1, duration: 0.55, ease: 'elastic.out(1, 0.5)' },
                0.05 * i,
            );
        });
    });
}

function contraer() {
    cancelarTimeout();
    if (!expandido.value) return;
    expandido.value = false;
    timeline?.kill();
    timeline = gsap.timeline();
    itemsRefs.value.forEach((el, i) => {
        if (!el) return;
        timeline.to(el, { x: 0, y: 0, scale: 0, opacity: 0, duration: 0.22, ease: 'power2.in' }, 0.03 * i);
    });
    timeline.to(boton.value, { rotate: 0, scale: 1, duration: 0.3, ease: 'power2.out' }, 0);
}

/** Cierra `permanencia` ms después de que el mouse salga de la zona del botón */
function programarCierre() {
    if (!expandido.value || timeoutCierre !== null) return;
    timeoutCierre = window.setTimeout(() => {
        timeoutCierre = null;
        contraer();
    }, props.permanencia);
}

function alMoverMouse(e: MouseEvent) {
    if (!raiz.value) return;
    const r = raiz.value.getBoundingClientRect();
    const dx = e.clientX - (r.left + r.width / 2);
    const dy = e.clientY - (r.top + r.height / 2);
    if (Math.hypot(dx, dy) <= props.distancia) {
        expandir();
    } else {
        programarCierre();
    }
}

/** Clic fuera del FAB → colapsa de inmediato */
function alClicFuera(e: MouseEvent) {
    if (raiz.value && !raiz.value.contains(e.target as Node)) {
        contraer();
    }
}

function alSeleccionar(id: string) {
    emit('select', id);
    contraer();
}

onMounted(() => {
    window.addEventListener('mousemove', alMoverMouse);
    window.addEventListener('click', alClicFuera);
    nextTick(() => {
        itemsRefs.value.forEach((el) => el && gsap.set(el, { scale: 0, opacity: 0 }));
    });
});

onBeforeUnmount(() => {
    window.removeEventListener('mousemove', alMoverMouse);
    window.removeEventListener('click', alClicFuera);
    cancelarTimeout();
    timeline?.kill();
});
</script>

<template>
    <div ref="raiz" class="relative inline-flex items-center">
        <button
            ref="boton"
            type="button"
            :aria-expanded="expandido"
            aria-label="Acciones rápidas"
            class="relative z-30 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-white shadow-lg shadow-indigo-500/30 bg-gradient-to-br from-indigo-500 to-violet-600 hover:from-indigo-600 hover:to-violet-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
            @click.stop="expandido ? contraer() : expandir()"
        >
            <PlusIcon class="h-5 w-5" />
        </button>

        <div class="pointer-events-none absolute left-1/2 top-1/2 h-0 w-0">
            <button
                v-for="(item, i) in props.items"
                :key="item.id"
                :ref="(el) => setItemRef(el, i)"
                type="button"
                class="pointer-events-auto absolute z-20 -ml-5 -mt-5 inline-flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-700 shadow-md transition-colors hover:text-indigo-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:text-indigo-400"
                v-tooltip="item.label"
                @mouseenter="cancelarTimeout()"
                @click.stop="alSeleccionar(item.id)"
            >
                <component :is="item.icon" class="h-5 w-5" />
            </button>
        </div>
    </div>
</template>
