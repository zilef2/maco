<script setup lang="ts">
import Modal from '@/components/Modal.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import { computed } from 'vue';
import { ClipboardDocumentListIcon } from '@heroicons/vue/24/solid';
import { formatDate, zilef_nuber_format } from '@/global.ts';

const props = defineProps({
    show: Boolean,
    merma: Object as () => any,
});

const emit = defineEmits(['close', 'ver-orden']);

const merma = computed(() => props.merma ?? {});

/** Cifras de la talla que originó esta merma. */
const cifrasTalla = computed(() => [
    { etiqueta: 'Esperada', valor: merma.value.esperada, tono: 'text-foreground' },
    { etiqueta: 'Aprobada', valor: merma.value.aprobada, tono: 'text-emerald-600 dark:text-emerald-400' },
    { etiqueta: 'En reparación', valor: merma.value.en_reparacion, tono: 'text-amber-600 dark:text-amber-400' },
    { etiqueta: 'No recuperable', valor: merma.value.cantidad, tono: 'text-red-600 dark:text-red-400' },
]);

/** Cifras de la OP completa: suman todas sus tallas, no solo la de la merma. */
const cifrasOp = computed(() => [
    { etiqueta: 'Esperada', valor: merma.value.esperada_op, tono: 'text-foreground' },
    { etiqueta: 'Aprobada', valor: merma.value.aprobada_op, tono: 'text-emerald-600 dark:text-emerald-400' },
    { etiqueta: 'En reparación', valor: merma.value.en_reparacion_op, tono: 'text-amber-600 dark:text-amber-400' },
    { etiqueta: 'No recuperable', valor: merma.value.no_recuperable_op, tono: 'text-red-600 dark:text-red-400' },
]);

const hayTotalesOp = computed(() =>
    cifrasOp.value.some((cifra) => cifra.valor !== null && cifra.valor !== undefined),
);

const dinero = (valor: any): string =>
    valor === null || valor === undefined || valor === ''
        ? '—'
        : zilef_nuber_format(valor, Math.round(Number(valor) * 100) % 100 === 0 ? 0 : 2, true);

const cantidad = (valor: any): string => (valor === null || valor === undefined ? '—' : zilef_nuber_format(valor, 0, false));

const formatearPorcentaje = (valor: number): string =>
    `${valor.toLocaleString('es-CO', { minimumFractionDigits: 1, maximumFractionDigits: 1 })}%`;

const porcentaje = computed(() => {
    const esperada = Number(merma.value.esperada) || 0;
    if (esperada <= 0) return '—';
    return formatearPorcentaje(((Number(merma.value.cantidad) || 0) / esperada) * 100);
});

const porcentajeOp = computed(() => {
    const esperada = Number(merma.value.esperada_op) || 0;
    if (esperada <= 0) return '—';
    return formatearPorcentaje(((Number(merma.value.no_recuperable_op) || 0) / esperada) * 100);
});

const texto = (valor: any): string => String(valor ?? '').trim() || '—';

const fecha = computed(() => (merma.value.fecha_registro ? formatDate(merma.value.fecha_registro, 'fecha') : '—'));
</script>

<template>
    <section>
        <Modal :show="props.show" @close="emit('close')" maxWidth="xl3" closeable>
            <div class="p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="text-lg font-semibold text-foreground">
                            Merma de {{ texto(merma.numero_op) }}
                            <span v-if="merma.talla" class="text-sm font-normal text-muted-foreground">
                                — talla {{ merma.talla }}
                            </span>
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ texto(merma.objetivo_tipo) }}: {{ texto(merma.objetivo) }}
                        </p>
                    </div>

                    <span
                        class="inline-flex items-center gap-1.5 rounded-full border border-border bg-muted/60 px-2.5 py-1 text-xs font-semibold text-foreground"
                    >
                        {{ texto(merma.estado_op) }}
                    </span>
                </div>

                <section v-if="hayTotalesOp" class="mt-5">
                    <h3 class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                        Total de la {{ texto(merma.numero_op) }}
                    </h3>

                    <div class="mt-2 grid gap-2 sm:grid-cols-4">
                        <div
                            v-for="cifra in cifrasOp"
                            :key="`op-${cifra.etiqueta}`"
                            class="rounded-lg border border-border bg-card p-3 shadow-sm"
                        >
                            <p class="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                                {{ cifra.etiqueta }}
                            </p>
                            <p class="mt-1 text-xl font-semibold tabular-nums" :class="cifra.tono">
                                {{ cantidad(cifra.valor) }}
                            </p>
                        </div>
                    </div>

                    <p class="mt-2 text-xs text-muted-foreground">
                        Suma de todas las tallas de la {{ texto(merma.numero_op) }}: la OP perdió
                        <span class="font-medium text-foreground">{{ porcentajeOp }}</span> de lo esperado.
                    </p>
                </section>

                <section class="mt-5">
                    <h3 class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                        Talla {{ texto(merma.talla) }}
                    </h3>

                    <div class="mt-2 grid gap-2 sm:grid-cols-4">
                        <div
                            v-for="cifra in cifrasTalla"
                            :key="`talla-${cifra.etiqueta}`"
                            class="rounded-lg border border-border bg-card p-3 shadow-sm"
                        >
                            <p class="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                                {{ cifra.etiqueta }}
                            </p>
                            <p class="mt-1 text-xl font-semibold tabular-nums" :class="cifra.tono">
                                {{ cantidad(cifra.valor) }}
                            </p>
                        </div>
                    </div>

                    <p class="mt-2 text-xs text-muted-foreground">
                        Esta talla dejó {{ porcentaje }} de sus pares como pérdida. El resto no se pierde: lo
                        aprobado entra a inventario y lo que está en reparación sigue pendiente.
                    </p>
                </section>

                <dl class="mt-5 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                    <div class="flex flex-col gap-0.5">
                        <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Fecha de registro</dt>
                        <dd class="text-foreground">{{ fecha }}</dd>
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Registrado por</dt>
                        <dd class="text-foreground">{{ texto(merma.responsable) }}</dd>
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Taller / Empresa externa</dt>
                        <dd class="text-foreground">
                            {{ texto(merma.destino_nombre) }}
                            <span v-if="merma.destino_tipo" class="text-xs text-muted-foreground">· {{ merma.destino_tipo }}</span>
                        </dd>
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Costo unitario</dt>
                        <dd class="tabular-nums text-foreground">{{ dinero(merma.costo_unitario) }}</dd>
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Valor perdido</dt>
                        <dd class="font-semibold tabular-nums text-foreground">{{ dinero(merma.costo_total_perdida) }}</dd>
                    </div>
                    <div class="flex flex-col gap-0.5 sm:col-span-2">
                        <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Observaciones</dt>
                        <dd class="text-foreground">{{ texto(merma.observaciones) }}</dd>
                    </div>
                </dl>

                <p class="mt-5 rounded-lg border border-border bg-muted/40 px-3 py-2.5 text-xs text-muted-foreground">
                    Esta merma no se edita aquí: se ajusta volviendo a guardar el formulario «Cambiar estado» de la OP
                    en «Recibiendo», donde se digitan los pares no recuperables.
                </p>

                <div class="mt-6 flex flex-wrap justify-end gap-2">
                    <SecondaryButton @click="emit('close')">Cerrar</SecondaryButton>
                    <button
                        type="button"
                        @click="emit('ver-orden', merma)"
                        class="inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-xs font-medium text-primary-foreground shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    >
                        <ClipboardDocumentListIcon class="h-4 w-4" />
                        Ir a la OP
                    </button>
                </div>
            </div>
        </Modal>
    </section>
</template>
