<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import InputLabel from '@/components/InputLabel.vue';
import Modal from '@/components/Modal.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { OPCION_OTRO, useSeleccionConOtro } from '@/composables/useSeleccionConOtro';

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
    tipo: 'Proveedor',
    codigo: '',
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

const opcionesTipo = computed<string[]>(
    () => (props.opcionesSelect as any)?.tipo ?? ['Proveedor', 'Taller', 'Empresa externa'],
);

const claseTipoSeleccionado = (opcion: string): string =>
    opcion === 'Proveedor'
        ? 'bg-sky-600 text-white shadow-sm'
        : opcion === 'Taller'
          ? 'bg-amber-600 text-white shadow-sm'
          : 'bg-violet-600 text-white shadow-sm';

const tituloTipo = computed(() => form.tipo.toLowerCase());

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

const opcionesDe = (campo: string): string[] => (props.opcionesSelect as any)?.[campo] ?? [];

const {
    otro: plazoOtro,
    seleccion: plazo,
    aplicar: aplicarPlazo,
    cargar: cargarPlazo,
} = useSeleccionConOtro(form, 'forma_pago', () => opcionesDe('forma_pago'), 'Escribe el plazo de pago.');

const {
    otro: tipoOtro,
    seleccion: tipo,
    aplicar: aplicarTipo,
    cargar: cargarTipo,
} = useSeleccionConOtro(form, 'tipo_proveedor', () => opcionesDe('tipo_proveedor'), 'Escribe el tipo de proveedor.');

const REGEX_SOLO_NUMEROS = /^\d+$/;

const validarTelefono = (campo: 'telefono_principal' | 'celular_contacto'): boolean => {
    const valor = String((form as any)[campo] ?? '');
    if (valor === '') {
        form.clearErrors(campo);
        return true;
    }
    if (!REGEX_SOLO_NUMEROS.test(valor)) {
        form.setError(
            campo,
            campo === 'telefono_principal'
                ? 'El teléfono solo admite números.'
                : 'El celular solo admite números.'
        );
        return false;
    }
    form.clearErrors(campo);
    return true;
};

const validarTelefonos = (): boolean => {
    const telValido = validarTelefono('telefono_principal');
    const celValido = validarTelefono('celular_contacto');
    return telValido && celValido;
};

const setCampoTelefono = (campo: 'telefono_principal' | 'celular_contacto', valor: string): void => {
    (form as any)[campo] = valor;
    validarTelefono(campo);
};

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
    'ciudad',
    'telefono_principal',
    'tipo_proveedor',
    'que_suministra',
    'forma_pago',
    'tiempo_entrega',
    'observaciones_generales',
];

const selectClass = 'mt-1 block w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30';

const closeModal = () => {
    emit('close');
    form.clearErrors();
};

const submit = () => {
    if (!props.proveedora?.id) return;
    const plazoValido = aplicarPlazo();
    const tipoValido = aplicarTipo();
    const telefonosValidos = validarTelefonos();
    if (!plazoValido || !tipoValido || !telefonosValidos) return;
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
            form.tipo = props.proveedora.tipo ?? 'Proveedor';
            form.codigo = props.proveedora.codigo ?? '';
            form.nit = props.proveedora.nit ?? '';
            form.nombre_comercial = props.proveedora.nombre_comercial ?? props.proveedora.nombre ?? '';
            form.razon_social = props.proveedora.razon_social ?? '';
            form.direccion = props.proveedora.direccion ?? '';
            form.observaciones_direccion = props.proveedora.observaciones_direccion ?? '';
            form.correo_electronico = props.proveedora.correo_electronico ?? '';
            form.ciudad = props.proveedora.ciudad ?? '';
            form.telefono_principal = props.proveedora.telefono_principal ? String(props.proveedora.telefono_principal) : '';
            form.tipo_proveedor = props.proveedora.tipo_proveedor ?? '';
            form.que_suministra = props.proveedora.que_suministra ?? '';
            form.persona_contacto = props.proveedora.persona_contacto ?? '';
            form.celular_contacto = props.proveedora.celular_contacto
                ? String(props.proveedora.celular_contacto)
                : props.proveedora.celular1
                  ? String(props.proveedora.celular1)
                  : '';
            form.forma_pago = props.proveedora.forma_pago ?? '';
            cargarTipo();
            cargarPlazo();
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
                    Editar {{ tituloTipo }}
                    <span v-if="form.codigo" class="font-mono text-sm text-muted-foreground">
                        · {{ form.codigo }}
                    </span>
                </h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Actualiza la ficha comercial, operativa y de contacto del tercero.
                </p>
            </div>

            <!-- Tipo de tercero y estado -->
            <div class="my-6 grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <InputLabel value="Tipo de tercero" />
                    <div
                        class="mt-1 inline-flex items-center gap-1 rounded-md border border-input bg-muted p-1"
                        role="group"
                        aria-label="Tipo de tercero"
                    >
                        <button
                            v-for="opcion in opcionesTipo"
                            :key="opcion"
                            type="button"
                            disabled
                            :aria-pressed="form.tipo === opcion"
                            :class="[
                                'cursor-not-allowed rounded px-3 py-1.5 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                                form.tipo === opcion
                                    ? claseTipoSeleccionado(opcion)
                                    : 'text-muted-foreground/70',
                            ]"
                        >
                            {{ opcion }}
                        </button>
                    </div>
                    <p class="mt-1 text-xs text-muted-foreground">
                        El tipo se define al crear el registro y no se puede cambiar.
                    </p>
                </div>

                <div>
                    <InputLabel for="estado" value="Estado" />
                    <div
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
                    <InputError class="mt-1" :message="form.errors.estado" />
                </div>
            </div>

            <!-- Campos obligatorios -->
            <div class="my-6">
                <h3 class="mb-2 text-sm font-medium text-muted-foreground">Campos obligatorios</h3>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div v-for="campo in camposObligatorios" :key="campo">
                        <InputLabel :for="campo" :value="etiquetas[campo]" />
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
            </div>

            <!-- Código generado automáticamente -->
            <div class="my-6">
                <InputLabel for="codigo" value="Código"/>
                <TextInput
                    id="codigo"
                    v-model="form.codigo"
                    type="text"
                    class="mt-1 block w-full bg-gray-50 cursor-not-allowed"
                    placeholder="Ej. PRV-001 (generado automáticamente)"
                    readonly
                    :error="form.errors.codigo"
                />
                <InputError class="mt-1" :message="form.errors.codigo" />
                <p class="text-xs text-muted-foreground mt-1">El código se genera al crear el registro según el tipo: PRV, TAL o EMP.</p>
            </div>

            <!-- Campos opcionales abajo -->
            <div class="my-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="campo in camposOpcionales" :key="campo">
                    <InputLabel :for="campo" :value="etiquetas[campo]"/>

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

                    <!-- Tipo proveedor con opción "Otro" editable -->
                    <template v-else-if="campo === 'tipo_proveedor'">
                        <select :id="campo" v-model="tipo" :class="selectClass">
                            <option value="">Seleccione un tipo</option>
                            <option v-for="opcion in opcionesDe(campo)" :key="opcion" :value="opcion">{{ opcion }}</option>
                        </select>
                        <TextInput
                            v-if="tipo === OPCION_OTRO"
                            id="tipo_proveedor_otro"
                            v-model="tipoOtro"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="Escribe el tipo de proveedor"
                        />
                    </template>

                    <!-- Plazo de pago con opción "Otro" editable -->
                    <template v-else-if="campo === 'forma_pago'">
                        <select :id="campo" v-model="plazo" :class="selectClass">
                            <option value="">Seleccione un plazo</option>
                            <option v-for="opcion in opcionesDe(campo)" :key="opcion" :value="opcion">{{ opcion }}</option>
                        </select>
                        <TextInput
                            v-if="plazo === OPCION_OTRO"
                            id="forma_pago_otro"
                            v-model="plazoOtro"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="Escribe el plazo (Ej. Crédito 45 días)"
                        />
                    </template>

                    <!-- Ciudad: permite escribir para buscar entre las opciones -->
                    <v-select
                        v-else-if="campo === 'ciudad'"
                        :id="campo"
                        v-model="form[campo]"
                        :options="opcionesDe(campo)"
                        :filterable="true"
                        :clearable="true"
                        :taggable="true"
                        placeholder="Escribe para buscar una ciudad"
                        class="mt-1 block w-full"
                    />

                    <!-- Teléfono y celular: solo números -->
                    <TextInput
                        v-else-if="campo === 'telefono_principal' || campo === 'celular_contacto'"
                        :id="campo"
                        :model-value="form[campo]"
                        type="tel"
                        inputmode="numeric"
                        class="mt-1 block w-full"
                        :error="form.errors[campo]"
                        @update:model-value="(valor: string) => setCampoTelefono(campo as 'telefono_principal' | 'celular_contacto', valor)"
                    />

                    <!-- Observaciones generales: área de texto -->
                    <textarea
                        v-else-if="campo === 'observaciones_generales'"
                        :id="campo"
                        v-model="form[campo]"
                        rows="4"
                        class="mt-1 block w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30"
                        placeholder="Escribe las observaciones generales"
                    ></textarea>

                    <!-- Campos texto normales -->
                    <TextInput
                        v-else
                        :id="campo"
                        v-model="form[campo]"
                        type="text"
                        class="mt-1 block w-full"
                        :placeholder="campo === 'razon_social' ? 'Razón social completa' : ''"
                        :error="form.errors[campo]"
                    />
                    <InputError class="mt-1" :message="form.errors[campo]" />
                </div>
            </div>

            <!-- Datos de contacto al final del formulario -->
            <div class="my-6 border-t border-border pt-6">
                <h3 class="mb-4 text-sm font-medium text-muted-foreground">Datos de contacto</h3>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <InputLabel for="persona_contacto" value="Persona de contacto" />
                        <TextInput
                            id="persona_contacto"
                            v-model="form.persona_contacto"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="Nombre de la persona de contacto"
                            :error="form.errors.persona_contacto"
                        />
                        <InputError class="mt-1" :message="form.errors.persona_contacto" />
                    </div>

                    <div>
                        <InputLabel for="correo_electronico" value="Correo" />
                        <TextInput
                            id="correo_electronico"
                            v-model="form.correo_electronico"
                            type="email"
                            class="mt-1 block w-full"
                            placeholder="correo@ejemplo.com"
                            :error="form.errors.correo_electronico"
                        />
                        <InputError class="mt-1" :message="form.errors.correo_electronico" />
                    </div>

                    <div>
                        <InputLabel for="celular_contacto" value="Celular" />
                        <TextInput
                            id="celular_contacto"
                            :model-value="form.celular_contacto"
                            type="tel"
                            inputmode="numeric"
                            class="mt-1 block w-full"
                            placeholder="3001234567"
                            :error="form.errors.celular_contacto"
                            @update:model-value="(valor: string) => setCampoTelefono('celular_contacto', valor)"
                        />
                        <p class="mt-1 text-xs text-muted-foreground">Todos los teléfonos son de Colombia.</p>
                        <InputError class="mt-1" :message="form.errors.celular_contacto" />
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3 border-t border-border pt-4">
                <SecondaryButton :disabled="form.processing" type="button" @click="closeModal">
                    Cancelar
                </SecondaryButton>
                <PrimaryButton :disabled="form.processing" type="submit">
                    {{ form.processing ? 'Guardando...' : `Actualizar ${tituloTipo}` }}
                </PrimaryButton>
            </div>
        </form>
    </Modal>
</template>