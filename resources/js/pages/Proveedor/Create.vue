<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import InputLabel from '@/components/InputLabel.vue';
import Modal from '@/components/Modal.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: 'Proveedor',
    },
    opcionesSelect: {
        type: Object,
        default: () => ({
            tipo_proveedor: ['Materia prima', 'Servicio', 'Mixto', 'Otro'],
            estado: ['Activo', 'Inactivo'],
            forma_pago: ['Contado', 'Crédito 8 días', 'Crédito 15 días', 'Crédito 30 días', 'Anticipo', 'Otro'],
            clasificacion: ['proveedor', 'taller de proceso externo', 'macondas'],
        }),
    },
});

const emit = defineEmits(['close']);

const state = reactive({
    guardarYContinuar: false,
});

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
    tiempo_entrega: '30',
    estado: 'Activo',
    observaciones_generales: '',
    clasificacion: 'proveedor',
});

const opcionesEstado = computed(() => props.opcionesSelect?.estado ?? ['Activo', 'Inactivo']);

const claseEstadoSeleccionado = (opcion: string): string =>
    opcion === 'Activo' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-rose-600 text-white shadow-sm';

// Campos obligatorios y opcionales
const camposObligatorios = [
    'nit',
    'nombre_comercial',
];

const camposOpcionales = [
    'razon_social',
    'direccion',
    'observaciones_direccion',
    'correo_electronico',
    'ciudad',
    'telefono_principal',
    'tipo_proveedor',
    'que_suministra',
    'persona_contacto',
    'celular_contacto',
    'forma_pago',
    'tiempo_entrega',
    'estado',
    'observaciones_generales',
    'clasificacion',
];

const selectClass = 'mt-1 block w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30';

const closeModal = () => {
    emit('close');
    form.reset();
    form.clearErrors();
};

const submit = (continuar = false) => {
    state.guardarYContinuar = continuar;
    form.post(route('Proveedor.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.clearErrors();
            if (!state.guardarYContinuar) {
                emit('close');
            }
        },
    });
};

watch(
    () => props.show,
    (abierto) => {
        if (abierto) {
            form.reset();
            form.clearErrors();
            // codigo_proveedor se genera automáticamente en el modelo
            form.estado = 'Activo';
            form.clasificacion = 'proveedor';
        }
    },
);
</script>

<template>
    <Modal :show="props.show" @close="closeModal" maxWidth="xl5">
        <form class="p-6" @submit.prevent="submit(false)">
            <div class="border-b border-border pb-4">
                <h2 class="text-lg font-semibold text-foreground">
                    Nuevo proveedor
                </h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Ingresa los datos generales, de contacto y comerciales del proveedor.
                </p>
            </div>

            <!-- Campos obligatorios arriba -->
            <div class="my-6 space-y-4">
                <h3 class="text-sm font-medium text-muted-foreground mb-2">Campos obligatorios</h3>
                <div v-for="campo in camposObligatorios" :key="campo">
                    <InputLabel :for="campo" :value="campo.replace('_', ' ')"/>
                    <TextInput
                        :id="campo"
                        v-model="form[campo]"
                        type="text"
                        class="mt-1 block w-full"
                        :placeholder="campo === 'nit' ? 'Ej. 900.123.456-7' : 'Razón social'"
                        :error="form.errors[campo]"
                    />
                    <InputError class="mt-1" :message="form.errors[campo]" />
                </div>
            </div>

            <!-- Código de proveedor generado automáticamente -->
            <div class="my-6">
                <InputLabel for="codigo_proveedor" value="Código proveedor"/>
                <TextInput
                    id="codigo_proveedor"
                    v-model="form.codigo_proveedor"
                    type="text"
                    class="mt-1 block w-full bg-gray-50 cursor-not-allowed"
                    placeholder="Ej. PRV-001 (generado automáticamente)"
                    readonly
                    :error="form.errors.codigo_proveedor"
                />
                <InputError class="mt-1" :message="form.errors.codigo_proveedor" />
                <p class="text-xs text-muted-foreground mt-1">Se genera automáticamente al guardar (formato: PRV-001, PRV-002, ...)</p>
            </div>

            <!-- Campos opcionales abajo -->
            <div class="my-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="campo in camposOpcionales" :key="campo">
                    <InputLabel :for="campo" :value="campo.replace('_', ' ')"/>
                    
                    <!-- Campo tiempo_entrega: en días -->
                    <TextInput
                        v-if="campo === 'tiempo_entrega'"
                        :id="campo"
                        v-model="form[campo]"
                        type="number"
                        min="1"
                        class="mt-1 block w-full"
                        :placeholder="campo === 'tiempo_entrega' ? 'Ej. 30 (días)' : ''"
                        :error="form.errors[campo]"
                    />
                    
                    <!-- Campo estado: selector Activo/Inactivo -->
                    <div
                        v-else-if="campo === 'estado'"
                        class="mt-1 inline-flex items-center gap-1 rounded-md border border-input bg-muted p-1"
                        role="group"
                        aria-label="Estado"
                    >
                        <button
                            v-for="opcion in opcionesEstado"
                            :key="opcion"
                            type="button"
                            :aria-pressed="form.estado === opcion"
                            :class="[
                                'rounded px-3 py-1 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                                form.estado === opcion
                                    ? claseEstadoSeleccionado(opcion)
                                    : 'text-muted-foreground hover:bg-background hover:text-foreground',
                            ]"
                            @click="form.estado = opcion"
                        >
                            {{ opcion }}
                        </button>
                    </div>
                    
                    <!-- Campos texto normales -->
                    <TextInput
                        v-else
                        :id="campo"
                        v-model="form[campo]"
                        type="text"
                        class="mt-1 block w-full"
                        :placeholder="campo === 'razon_social' ? 'Razón social completa' : campo === 'clasificacion' ? 'proveedor, taller de proceso externo o macondas' : campo === 'forma_pago' ? 'Contado, Crédito 8 días, etc.' : ''"
                        :error="form.errors[campo]"
                    />
                    <InputError class="mt-1" :message="form.errors[campo]" />
                </div>
            </div>

            <div class="flex justify-end gap-3 border-t border-border pt-4">
                <SecondaryButton :disabled="form.processing" type="button" @click="closeModal">
                    Cancelar
                </SecondaryButton>
                <PrimaryButton
                    type="button"
                    :disabled="form.processing"
                    class="bg-muted-foreground hover:bg-foreground"
                    @click="submit(true)"
                >
                    {{ form.processing && state.guardarYContinuar ? 'Guardando...' : 'Guardar y continuar' }}
                </PrimaryButton>
                <PrimaryButton :disabled="form.processing" type="submit">
                    {{ form.processing && !state.guardarYContinuar ? 'Guardando...' : 'Guardar proveedor' }}
                </PrimaryButton>
            </div>
        </form>
    </Modal>
</template>