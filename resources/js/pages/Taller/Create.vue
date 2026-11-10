<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import InputLabel from '@/components/InputLabel.vue';
import Modal from '@/components/Modal.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';
import { OPCION_OTRO, useSeleccionConOtro } from '@/composables/useSeleccionConOtro';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: 'Taller' },
    opcionesSelect: {
        type: Object,
        default: () => ({
            tipo_proveedor: ['Materia prima', 'Servicio', 'Mixto', 'Otro'],
            estado: ['Activo', 'Inactivo'],
            forma_pago: ['Contado', 'Crédito 8 días', 'Crédito 15 días', 'Crédito 30 días', 'Anticipo', 'Otro'],
        }),
    },
});

const emit = defineEmits(['close']);

const form = useForm({
    nit: '',
    nombre_comercial: '',
    razon_social: '',
    direccion: '',
    observaciones_direccion: '',
    correo_electronico: '',
    ciudad: '',
    telefono_principal: '',
    tipo_proveedor: '',
    que_suministra: '',
    persona_contacto: '',
    celular_contacto: '',
    forma_pago: '',
    tiempo_entrega: '',
    estado: 'Activo',
    observaciones_generales: '',
});

const estado = reactive({ guardarYContinuar: false });

const etiquetas: Record<string, string> = {
    nit: 'NIT',
    nombre_comercial: 'Nombre',
    razon_social: 'Razón social',
    direccion: 'Dirección',
    observaciones_direccion: 'Observaciones dirección',
    correo_electronico: 'Correo',
    ciudad: 'Ciudad',
    telefono_principal: 'Teléfono',
    tipo_proveedor: 'Tipo proveedor',
    que_suministra: 'Qué suministra',
    persona_contacto: 'Persona de contacto',
    celular_contacto: 'Celular',
    forma_pago: 'Plazo de pago',
    tiempo_entrega: 'Tiempo habitual de entrega',
    estado: 'Estado',
    observaciones_generales: 'Observaciones generales',
};

const camposObligatorios = ['nombre_comercial', 'tipo_proveedor', 'estado'];

const camposOpcionales = [
    'nit',
    'razon_social',
    'direccion',
    'observaciones_direccion',
    'correo_electronico',
    'ciudad',
    'telefono_principal',
    'que_suministra',
    'persona_contacto',
    'celular_contacto',
    'forma_pago',
    'tiempo_entrega',
    'observaciones_generales',
];

const opcionesDe = (campo: string): string[] =>
    (props.opcionesSelect as any)?.[campo] ?? [];

const {
    otro: plazoOtro,
    seleccion: plazo,
    aplicar: aplicarPlazo,
    reiniciar: reiniciarPlazo,
} = useSeleccionConOtro(form, 'forma_pago', () => opcionesDe('forma_pago'), 'Escribe el plazo de pago.');

const {
    otro: tipoOtro,
    seleccion: tipo,
    aplicar: aplicarTipo,
    reiniciar: reiniciarTipo,
} = useSeleccionConOtro(form, 'tipo_proveedor', () => opcionesDe('tipo_proveedor'), 'Escribe el tipo de proveedor.');

const soloDigitos = (valor: string): string => valor.replace(/\D+/g, '');

const setCampo = (campo: string, valor: string): void => {
    (form as any)[campo] = valor;
};

const claseEstado = (opcion: string): string =>
    opcion === 'Activo' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-rose-600 text-white shadow-sm';

const inputClass =
    'mt-1 block h-10 w-full rounded-md border border-input bg-background px-3 text-sm text-foreground shadow-sm placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30';

const closeModal = () => {
    emit('close');
    form.reset();
    form.clearErrors();
    reiniciarPlazo();
    reiniciarTipo();
};

const submit = (continuar = false) => {
    const plazoValido = aplicarPlazo();
    const tipoValido = aplicarTipo();
    if (!plazoValido || !tipoValido) return;
    estado.guardarYContinuar = continuar;
    form.post(route('Taller.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.clearErrors();
            reiniciarPlazo();
            reiniciarTipo();
            if (!continuar) emit('close');
        },
    });
};

watch(
    () => props.show,
    (abierto) => {
        if (abierto) {
            form.reset();
            form.clearErrors();
            reiniciarPlazo();
            reiniciarTipo();
            // codigo_taller lo genera el modelo (TAL-001, TAL-002, ...)
            form.estado = 'Activo';
        }
    },
);

const opcionesEstado = computed(() => opcionesDe('estado'));
</script>

<template>
    <Modal :show="props.show" @close="closeModal" maxWidth="xl6">
        <form class="p-6" @submit.prevent="submit(false)">
            <div class="border-b border-border pb-4">
                <h2 class="text-lg font-semibold text-foreground">Nuevo taller</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Datos generales, de contacto y comerciales del taller.
                </p>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="campo in camposObligatorios" :key="campo">
                    <InputLabel :for="campo" :value="etiquetas[campo]" />

                    <div
                        v-if="campo === 'estado'"
                        class="mt-1 inline-flex items-center gap-1 rounded-md border border-input bg-muted p-1"
                        role="group"
                        :aria-label="etiquetas[campo]"
                    >
                        <button
                            v-for="opcion in opcionesEstado"
                            :key="opcion"
                            type="button"
                            :aria-pressed="form[campo as keyof typeof form] === opcion"
                            :class="[
                                'rounded px-3 py-1.5 text-sm font-medium transition-colors',
                                form[campo as keyof typeof form] === opcion
                                    ? claseEstado(opcion)
                                    : 'text-muted-foreground hover:bg-background hover:text-foreground',
                            ]"
                            @click="form[campo as keyof typeof form] = opcion as any"
                        >
                            {{ opcion }}
                        </button>
                    </div>

                    <template v-else-if="campo === 'tipo_proveedor'">
                        <select :id="campo" v-model="tipo" :class="inputClass">
                            <option value="">Seleccione un tipo</option>
                            <option v-for="opcion in opcionesDe(campo)" :key="opcion" :value="opcion">{{ opcion }}</option>
                        </select>
                        <TextInput
                            v-if="tipo === OPCION_OTRO"
                            id="tipo_proveedor_otro"
                            v-model="tipoOtro"
                            type="text"
                            :class="inputClass"
                            placeholder="Escribe el tipo de proveedor"
                        />
                    </template>

                    <TextInput
                        v-else
                        :id="campo"
                        v-model="form[campo as keyof typeof form]"
                        type="text"
                        :class="inputClass"
                        :error="form.errors[campo]"
                    />
                    <InputError class="mt-1" :message="form.errors[campo]" />
                </div>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="campo in camposOpcionales" :key="campo">
                    <InputLabel :for="campo" :value="etiquetas[campo]" />

                    <template v-if="campo === 'forma_pago'">
                        <select :id="campo" v-model="plazo" :class="inputClass">
                            <option value="">Seleccione un plazo</option>
                            <option v-for="opcion in opcionesDe(campo)" :key="opcion" :value="opcion">{{ opcion }}</option>
                        </select>
                        <TextInput
                            v-if="plazo === OPCION_OTRO"
                            id="forma_pago_otro"
                            v-model="plazoOtro"
                            type="text"
                            :class="inputClass"
                            placeholder="Escribe el plazo (Ej. Crédito 45 días)"
                        />
                    </template>

                    <select
                        v-else-if="campo === 'ciudad'"
                        :id="campo"
                        v-model="form[campo as keyof typeof form]"
                        :class="inputClass"
                    >
                        <option value="">Seleccione una ciudad</option>
                        <option v-for="ciudad in opcionesDe(campo)" :key="ciudad" :value="ciudad">{{ ciudad }}</option>
                        <option v-if="form.ciudad && !opcionesDe(campo).includes(form.ciudad)" :value="form.ciudad">
                            {{ form.ciudad }}
                        </option>
                    </select>

                    <TextInput
                        v-else-if="campo === 'telefono_principal' || campo === 'celular_contacto'"
                        :id="campo"
                        :model-value="form[campo as keyof typeof form]"
                        type="tel"
                        inputmode="numeric"
                        :class="inputClass"
                        :error="form.errors[campo]"
                        @update:model-value="(valor: string) => setCampo(campo, soloDigitos(valor))"
                    />

                    <TextInput
                        v-else
                        :id="campo"
                        v-model="form[campo as keyof typeof form]"
                        :type="campo === 'correo_electronico' ? 'email' : 'text'"
                        :class="inputClass"
                        :placeholder="campo === 'nit' ? 'Ej. 900.123.456-7' : campo === 'tiempo_entrega' ? 'Ej. 30 días' : ''"
                        :error="form.errors[campo]"
                    />
                    <InputError class="mt-1" :message="form.errors[campo]" />
                </div>
            </div>

            <p class="mt-4 text-xs text-muted-foreground">
                El código del taller se genera solo al guardar (TAL-001, TAL-002, ...).
            </p>

            <div class="mt-6 flex justify-end gap-3 border-t border-border pt-4">
                <SecondaryButton :disabled="form.processing" type="button" @click="closeModal">
                    Cancelar
                </SecondaryButton>
                <PrimaryButton
                    type="button"
                    :disabled="form.processing"
                    class="bg-muted-foreground hover:bg-foreground"
                    @click="submit(true)"
                >
                    {{ form.processing && estado.guardarYContinuar ? 'Guardando...' : 'Guardar y continuar' }}
                </PrimaryButton>
                <PrimaryButton :disabled="form.processing" type="submit">
                    {{ form.processing && !estado.guardarYContinuar ? 'Guardando...' : 'Guardar taller' }}
                </PrimaryButton>
            </div>
        </form>
    </Modal>
</template>
