<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import InputLabel from '@/components/InputLabel.vue';
import Modal from '@/components/Modal.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import EditorMateriales from '@/pages/FichaTecnicaMateriales/EditorMateriales.vue';
import { dineroANumero } from '@/global.ts';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watchEffect } from 'vue';
import vSelect from "vue-select";
import "vue-select/dist/vue-select.css";
import { CubeIcon, DocumentTextIcon, Squares2X2Icon, WrenchScrewdriverIcon } from '@heroicons/vue/24/solid';

const props = defineProps({
    show: Boolean,
    title: String,
    losSelect: Object,
    numberPermissions: Number,
})
const emit = defineEmits(["close"]);

type TipoObjetivo = 'producto' | 'material';

// El nombre no se captura: lo arma el backend desde el objetivo (igual que
// FichaTecnicaService::nombreAutomatico()).
const form = useForm({
    productos: [] as number[],
    materiales_procesados: [] as number[],
    materiales: [] as any[],
});

const tipoObjetivo = ref<TipoObjetivo>('producto');

// Una ficha se asigna a un producto o a un material procesado, nunca a ambos.
const opcionesPorTipo = computed<Record<TipoObjetivo, any[]>>(() => ({
    producto: (props.losSelect?.productos_sin_ficha ?? []).map((p: any) => ({ id: p.id, name: p.name })),
    material: (props.losSelect?.materiales_procesados_sin_ficha ?? []).map((m: any) => ({ id: m.id, name: m.name })),
}));

const tiposDisponibles = computed<TipoObjetivo[]>(() =>
    (['producto', 'material'] as TipoObjetivo[]).filter((tipo) => opcionesPorTipo.value[tipo].length > 0),
);

const opcionesObjetivo = computed(() => opcionesPorTipo.value[tipoObjetivo.value] ?? []);

const seleccionados = computed<number[]>({
    get: () => (tipoObjetivo.value === 'producto' ? form.productos : form.materiales_procesados),
    set: (ids: number[]) => {
        if (tipoObjetivo.value === 'producto') {
            form.productos = ids;
            form.materiales_procesados = [];
        } else {
            form.materiales_procesados = ids;
            form.productos = [];
        }
    },
});

const etiquetaTipo = (tipo: TipoObjetivo) => (tipo === 'producto' ? 'Producto' : 'Material procesado');

const cambiarTipo = (tipo: TipoObjetivo) => {
    if (tipo === tipoObjetivo.value) return;
    tipoObjetivo.value = tipo;
    form.productos = [];
    form.materiales_procesados = [];
};

const nombreAutomatico = computed(() => {
    const elegidos = opcionesObjetivo.value.filter((opcion: any) => seleccionados.value.includes(opcion.id));
    if (!elegidos.length) return '';

    return elegidos.length === 1 ? elegidos[0].name : `${elegidos[0].name} (+${elegidos.length - 1})`;
});

const sinObjetivo = computed(() => !form.productos.length && !form.materiales_procesados.length);
const hayObjetivos = computed(() => tiposDisponibles.value.length > 0);

const guardar = () => {
    if (sinObjetivo.value) return;

    form
        .transform((datos) => ({
            ...datos,
            materiales: datos.materiales
                .filter((fila: any) => fila.material_componente_id)
                .map((fila: any) => ({
                    ...fila,
                    consumo_estandar: dineroANumero(fila.consumo_estandar),
                    costo_asociado_teorico: dineroANumero(fila.costo_asociado_teorico),
                })),
        }))
        .post(route('FichaTecnicaMateriales.store'), {
            preserveScroll: true,
            onSuccess: () => emit("close"),
            onError: () => null,
            onFinish: () => null,
        })
}

watchEffect(() => {
    if (props.show) {
        form.errors = {}
        form.reset()
        form.materiales = []
        tipoObjetivo.value = tiposDisponibles.value[0] ?? 'producto';
    }
})
</script>

<template>
    <section class="space-y-6">
        <Modal :show="props.show" @close="emit('close')" :maxWidth="'xl8'">
            <form class="flex flex-col" @submit.prevent="guardar">
                <header class="flex items-start gap-3 border-b border-border px-6 py-5">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <DocumentTextIcon class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-base font-semibold text-foreground">
                            {{ lang().t('app.label.add') }} {{ props.title }}
                        </h2>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            Asigna la ficha a un producto o a un material procesado. El nombre se genera automáticamente.
                        </p>
                    </div>
                </header>

                <div class="space-y-6 px-6 py-5">
                    <p
                        v-if="!hayObjetivos"
                        class="flex items-center gap-2 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm font-medium text-amber-800 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-200"
                    >
                        No hay productos ni materiales procesados pendientes de ficha.
                    </p>

                    <template v-else>
                        <section class="space-y-3">
                            <InputLabel value="Objetivo de la ficha" />

                            <div v-if="tiposDisponibles.length > 1" class="inline-flex rounded-lg border border-border bg-muted/40 p-0.5">
                                <button
                                    v-for="tipo in tiposDisponibles"
                                    :key="tipo"
                                    type="button"
                                    @click="cambiarTipo(tipo)"
                                    :class="[
                                        'inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition',
                                        tipoObjetivo === tipo ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground',
                                    ]"
                                >
                                    <Squares2X2Icon v-if="tipo === 'producto'" class="h-3.5 w-3.5" />
                                    <WrenchScrewdriverIcon v-else class="h-3.5 w-3.5" />
                                    {{ etiquetaTipo(tipo) }}
                                </button>
                            </div>

                            <v-select
                                multiple
                                class="block w-full"
                                :options="opcionesObjetivo"
                                label="name"
                                :reduce="(item: any) => item.id"
                                v-model="seleccionados"
                                :placeholder="'Seleccione ' + etiquetaTipo(tipoObjetivo).toLowerCase() + '(s)'"
                            />
                            <InputError class="mt-1" :message="form.errors.productos" />
                            <InputError class="mt-1" :message="form.errors.materiales_procesados" />
                        </section>

                        <section class="rounded-lg border border-border bg-muted/40 px-4 py-3">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground">
                                Nombre de la ficha · automático
                            </p>
                            <p class="mt-1 text-sm font-medium text-foreground">
                                {{ nombreAutomatico || 'Seleccione un objetivo para ver el nombre' }}
                            </p>
                        </section>

                        <section class="space-y-2">
                            <div class="flex items-center gap-2">
                                <CubeIcon class="h-4 w-4 text-muted-foreground" />
                                <InputLabel :value="lang().t('app.label.materiales_ficha')" />
                            </div>
                            <EditorMateriales v-model="form.materiales" :opciones="props.losSelect" :errors="form.errors" />
                            <InputError class="mt-1" :message="form.errors.materiales" />
                        </section>
                    </template>
                </div>

                <footer class="flex items-center justify-end gap-3 border-t border-border px-6 py-4">
                    <SecondaryButton :disabled="form.processing" @click="emit('close')">
                        {{ lang().t('app.button.close') }}
                    </SecondaryButton>
                    <PrimaryButton
                        v-if="hayObjetivos"
                        type="submit"
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing || sinObjetivo"
                        :title="sinObjetivo ? 'Seleccione un objetivo' : undefined"
                    >
                        {{ lang().t('app.button.add') }} {{ form.processing ? '...' : '' }}
                    </PrimaryButton>
                </footer>
            </form>
        </Modal>
    </section>
</template>
