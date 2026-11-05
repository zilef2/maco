<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import InputLabel from '@/components/InputLabel.vue';
import Modal from '@/components/Modal.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: 'Proveedor',
    },
    proveedora: {
        type: Object,
        default: () => ({}),
    },
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
    codigo_proveedor: '',
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
];

const selectClass = 'mt-1 block w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30';

const closeModal = () => {
    emit('close');
    form.clearErrors();
};

const submit = () => {
    if (!props.proveedora?.id) return;
    form.put(route('Proveedor.update', props.proveedora.id), {
        preserveScroll: true,
        onSuccess: () => {
            emit('close');
        },
    });
};

watch(
    () => props.show,
    (abierto) => {
        if (abierto && props.proveedora) {
            form.clearErrors();
            form.codigo_proveedor = props.proveedora.codigo_proveedor ?? '';
            form.nit = props.proveedora.nit ?? '';
            form.nombre_comercial = props.proveedora.nombre_comercial ?? props.proveedora.nombre ?? '';
            form.razon_social = props.proveedora.razon_social ?? '';
            form.direccion = props.proveedora.direccion ?? '';
            form.observaciones_direccion = props.proveedora.observaciones_direccion ?? '';
            form.correo_electronico = props.proveedora.correo_electronico ?? '';
            form.ciudad = props.proveedora.ciudad ?? '';
            form.telefono_principal = props.proveedora.telefono_principal ?? '';
            form.tipo_proveedor = props.proveedora.tipo_proveedor ?? '';
            form.que_suministra = props.proveedora.que_suministra ?? '';
            form.persona_contacto = props.proveedora.persona_contacto ?? '';
            form.celular_contacto = props.proveedora.celular_contacto ?? (props.proveedora.celular1 ? String(props.proveedora.celular1) : '');
            form.forma_pago = props.proveedora.forma_pago ?? '';
            form.tiempo_entrega = props.proveedora.tiempo_entrega ?? '30';
            form.estado = props.proveedora.estado ?? 'Activo';
            form.observaciones_generales = props.proveedora.observaciones_generales ?? '';
        }
    },
);
</script>

<template>
    <Modal :show="props.show" @close="closeModal" maxWidth="xl5">
        <form class="p-6" @submit.prevent="submit">
            <div class="border-b border-border pb-4">
                <h2 class="text-lg font-semibold text-foreground">
                    Editar proveedor
                </h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Actualiza la información comercial, operativa y de contacto del proveedor.
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
                        :placeholder="campo === 'razon_social' ? 'Razón social completa' : campo === 'forma_pago' ? 'Contado, Crédito 8 días, etc.' : ''"
                        :error="form.errors[campo]"
                    />
                    <InputError class="mt-1" :message="form.errors[campo]" />
                </div>
            </div>

            <div class="flex justify-end gap-3 border-t border-border pt-4">
                <SecondaryButton :disabled="form.processing" type="button" @click="closeModal">
                    Cancelar
                </SecondaryButton>
                <PrimaryButton :disabled="form.processing" type="submit">
                    {{ form.processing ? 'Guardando...' : 'Actualizar proveedor' }}
                </PrimaryButton>
            </div>
        </form>
    </Modal>
</template>