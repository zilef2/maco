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
        }),
    },
});

const emit = defineEmits(['close']);

const state = reactive({
    guardarYContinuar: false,
});

const form = useForm({
    tipo: 'Proveedor',
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

const tituloTipo = computed(() => {
    if (form.tipo === 'Taller') return 'taller';
    if (form.tipo === 'Empresa externa') return 'empresa externa';
    return 'proveedor';
});

const prefijoActual = computed(() =>
    form.tipo === 'Taller' ? 'TAL' : form.tipo === 'Empresa externa' ? 'EMP' : 'PRV',
);

const encabezadoTipo = computed(() => {
    if (form.tipo === 'Taller') return 'Nuevo taller';
    if (form.tipo === 'Empresa externa') return 'Nueva empresa externa';
    return 'Nuevo proveedor';
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

const opcionesDe = (campo: string): string[] => (props.opcionesSelect as any)?.[campo] ?? [];

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
    form.reset();
    form.clearErrors();
    reiniciarPlazo();
    reiniciarTipo();
};

const submit = (continuar = false) => {
    const plazoValido = aplicarPlazo();
    const tipoValido = aplicarTipo();
    if (!plazoValido || !tipoValido) return;
    state.guardarYContinuar = continuar;
    form.post(route('Proveedor.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.clearErrors();
            reiniciarPlazo();
            reiniciarTipo();
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
            reiniciarPlazo();
            reiniciarTipo();
            // El código se genera automáticamente en el modelo
            form.estado = 'Activo';
        }
    },
);
</script>

<template>
    <Modal :show="props.show" @close="closeModal" maxWidth="xl5">
        <form class="p-6" @submit.prevent="submit(false)">
            <div class="border-b border-border pb-4">
                <h2 class="text-lg font-semibold text-foreground">
                    {{ encabezadoTipo }}
                </h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Ingresa los datos generales, de contacto y comerciales del tercero.
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
                            :aria-pressed="form.tipo === opcion"
                            :class="[
                                'rounded px-3 py-1.5 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                                form.tipo === opcion
                                    ? claseTipoSeleccionado(opcion)
                                    : 'text-muted-foreground hover:bg-background hover:text-foreground',
                            ]"
                            @click="form.tipo = opcion"
                        >
                            {{ opcion }}
                        </button>
                    </div>
                    <InputError class="mt-1" :message="form.errors.tipo" />
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
            <div class="my-6 rounded-md border border-border bg-muted/40 p-3">
                <p class="text-sm font-medium text-foreground">Código</p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Se genera automáticamente al guardar con el prefijo del tipo elegido:
                    {{ prefijoActual }}-001, {{ prefijoActual }}-002, ...
                </p>
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
                        <select :id="campo" v-model="plazo" class="mt-1 block w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30">
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
                        @update:model-value="(valor: string) => setCampo(campo, soloDigitos(valor))"
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
                            :placeholder="'Nombre de la persona de contacto'"
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
                            @update:model-value="(valor: string) => setCampo('celular_contacto', soloDigitos(valor))"
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
                <PrimaryButton
                    type="button"
                    :disabled="form.processing"
                    class="bg-muted-foreground hover:bg-foreground"
                    @click="submit(true)"
                >
                    {{ form.processing && state.guardarYContinuar ? 'Guardando...' : 'Guardar y continuar' }}
                </PrimaryButton>
                <PrimaryButton :disabled="form.processing" type="submit">
                    {{ form.processing && !state.guardarYContinuar ? 'Guardando...' : `Guardar ${tituloTipo}` }}
                </PrimaryButton>
            </div>
        </form>
    </Modal>
</template>