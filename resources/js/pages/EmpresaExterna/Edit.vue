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
    show: { type: Boolean, default: false },
    title: { type: String, default: 'Empresa externa' },
    EmpresaExternaa: { type: Object, default: () => ({}) },
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
    codigo_empresa_externa: '',
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

const opcionesDe = (campo: string): string[] => (props.opcionesSelect as any)?.[campo] ?? [];
const opcionesEstado = computed(() => opcionesDe('estado'));

const claseEstado = (opcion: string): string =>
    opcion === 'Activo' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-rose-600 text-white shadow-sm';

const inputClass =
    'mt-1 block h-10 w-full rounded-md border border-input bg-background px-3 text-sm text-foreground shadow-sm placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30';

const closeModal = () => {
    emit('close');
    form.clearErrors();
};

const submit = () => {
    if (!props.EmpresaExternaa?.id) return;
    form.put(route('EmpresaExterna.update', props.EmpresaExternaa.id), {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
};

watch(
    () => props.show,
    (abierto) => {
        if (!abierto || !props.EmpresaExternaa) return;
        const empresa = props.EmpresaExternaa as any;
        form.clearErrors();
        form.codigo_empresa_externa = empresa.codigo_empresa_externa ?? '';
        form.nit = empresa.nit ?? '';
        form.nombre_comercial = empresa.nombre_comercial ?? empresa.nombre ?? '';
        form.razon_social = empresa.razon_social ?? '';
        form.direccion = empresa.direccion ?? '';
        form.observaciones_direccion = empresa.observaciones_direccion ?? '';
        form.correo_electronico = empresa.correo_electronico ?? '';
        form.ciudad = empresa.ciudad ?? '';
        form.telefono_principal = empresa.telefono_principal ?? '';
        form.tipo_proveedor = empresa.tipo_proveedor ?? '';
        form.que_suministra = empresa.que_suministra ?? '';
        form.persona_contacto = empresa.persona_contacto ?? '';
        form.celular_contacto = empresa.celular_contacto ?? '';
        form.forma_pago = empresa.forma_pago ?? '';
        form.tiempo_entrega = empresa.tiempo_entrega ?? '';
        form.estado = empresa.estado ?? 'Activo';
        form.observaciones_generales = empresa.observaciones_generales ?? '';
    },
);
</script>

<template>
    <Modal :show="props.show" @close="closeModal" maxWidth="xl6">
        <form class="p-6" @submit.prevent="submit">
            <div class="border-b border-border pb-4">
                <h2 class="text-lg font-semibold text-foreground">
                    Editar empresa externa
                    <span v-if="form.codigo_empresa_externa" class="font-mono text-sm text-muted-foreground">
                        · {{ form.codigo_empresa_externa }}
                    </span>
                </h2>
                <p class="mt-1 text-sm text-muted-foreground">Actualice los datos de la empresa externa.</p>
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

                    <select
                        v-else-if="campo === 'tipo_proveedor'"
                        :id="campo"
                        v-model="form[campo as keyof typeof form]"
                        :class="inputClass"
                    >
                        <option value="">Seleccione un tipo</option>
                        <option v-for="opcion in opcionesDe(campo)" :key="opcion" :value="opcion">{{ opcion }}</option>
                    </select>

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

                    <select
                        v-if="campo === 'forma_pago'"
                        :id="campo"
                        v-model="form[campo as keyof typeof form]"
                        :class="inputClass"
                    >
                        <option value="">Seleccione un plazo</option>
                        <option v-for="opcion in opcionesDe(campo)" :key="opcion" :value="opcion">{{ opcion }}</option>
                    </select>

                    <TextInput
                        v-else
                        :id="campo"
                        v-model="form[campo as keyof typeof form]"
                        :type="campo === 'correo_electronico' ? 'email' : 'text'"
                        :class="inputClass"
                        :error="form.errors[campo]"
                    />
                    <InputError class="mt-1" :message="form.errors[campo]" />
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3 border-t border-border pt-4">
                <SecondaryButton :disabled="form.processing" type="button" @click="closeModal">
                    Cancelar
                </SecondaryButton>
                <PrimaryButton :disabled="form.processing" type="submit">
                    {{ form.processing ? 'Guardando...' : 'Guardar cambios' }}
                </PrimaryButton>
            </div>
        </form>
    </Modal>
</template>
