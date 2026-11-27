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

const bloquearNoDigitos = (event: KeyboardEvent): void => {
    if (event.ctrlKey || event.metaKey || event.altKey) return;
    if (event.key.length === 1 && !/^\d$/.test(event.key)) {
        event.preventDefault();
    }
};

const sanitizarInputTelefono = (campo: string, event: Event): void => {
    const input = event.target as HTMLInputElement;
    const limpio = soloDigitos(input.value);
    if (input.value !== limpio) {
        input.value = limpio;
    }
    setCampo(campo, limpio);
};

const setCampo = (campo: string, valor: string): void => {
    (form as any)[campo] = valor;
};

const setCorreo = (valor: string): void => {
    form.correo_electronico = valor;
    if (valor === '') {
        form.clearErrors('correo_electronico');
    } else if (!valor.includes('@')) {
        form.setError('correo_electronico', 'El correo debe contener el símbolo @.');
    } else {
        form.clearErrors('correo_electronico');
    }
};

const claseEstadoSeleccionado = (opcion: string): string =>
    opcion === 'Activo' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-rose-600 text-white shadow-sm';

const camposObligatorios = [
    'nit',
    'nombre_comercial',
    'razon_social',
    'direccion',
    'observaciones_direccion',
    'ciudad',
    'telefono_principal',
    'tipo_proveedor',
    'forma_pago',
    'tiempo_entrega',
];

const validarRequeridos = (): boolean => {
    let valido = true;
    for (const campo of camposObligatorios) {
        const valor = String((form as any)[campo] ?? '').trim();
        if (valor === '') {
            form.setError(campo, `${etiquetas[campo]} es obligatorio.`);
            valido = false;
        } else {
            form.clearErrors(campo);
        }
    }
    return valido;
};

const validarCorreo = (): boolean => {
    const valor = String(form.correo_electronico ?? '').trim();
    if (valor === '') {
        form.clearErrors('correo_electronico');
        return true;
    }
    if (!valor.includes('@')) {
        form.setError('correo_electronico', 'El correo debe contener el símbolo @.');
        return false;
    }
    form.clearErrors('correo_electronico');
    return true;
};

const validarTelefonos = (): boolean => {
    let valido = true;
    for (const campo of ['telefono_principal', 'celular_contacto'] as const) {
        const valor = String((form as any)[campo] ?? '');
        if (valor !== '' && !/^\d+$/.test(valor)) {
            form.setError(campo, campo === 'telefono_principal' ? 'El teléfono solo admite números.' : 'El celular solo admite números.');
            valido = false;
        } else {
            form.clearErrors(campo);
        }
    }
    return valido;
};

const selectClass = 'mt-1 block w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30';

const closeModal = () => {
    emit('close');
    form.reset();
    form.clearErrors();
    reiniciarPlazo();
    reiniciarTipo();
};

const submit = (continuar = false) => {
    const requeridosValidos = validarRequeridos();
    const telefonosValidos = validarTelefonos();
    const correoValido = validarCorreo();
    const plazoValido = aplicarPlazo();
    const tipoValido = aplicarTipo();
    if (!requeridosValidos || !telefonosValidos || !correoValido || !plazoValido || !tipoValido) return;
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
    <Modal :show="props.show" @close="closeModal" maxWidth="xl7">
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
            <div class="my-6 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div class="col-span-2">
                    <InputLabel value="Tipo de tercero" />
                    <div
                        class=" mt-1 inline-flex items-center gap-1 rounded-md border border-input bg-muted p-1"
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

            <div class="my-6">
                <h3 class="mb-2 text-sm font-medium text-muted-foreground">Campos obligatorios</h3>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <div>
                        <InputLabel for="nit" value="NIT" />
                        <TextInput
                            id="nit"
                            v-model="form.nit"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="Ej. 900.123.456-7"
                            :error="form.errors.nit"
                        />
                        <InputError class="mt-1" :message="form.errors.nit" />
                    </div>

                    <div>
                        <InputLabel for="nombre_comercial" value="Nombre" />
                        <TextInput
                            id="nombre_comercial"
                            v-model="form.nombre_comercial"
                            type="text"
                            class="mt-1 block w-full"
                            :error="form.errors.nombre_comercial"
                        />
                        <InputError class="mt-1" :message="form.errors.nombre_comercial" />
                    </div>

                    <div>
                        <InputLabel for="razon_social" value="Razón social" />
                        <TextInput
                            id="razon_social"
                            v-model="form.razon_social"
                            type="text"
                            class="mt-1 block w-full"
                            :error="form.errors.razon_social"
                        />
                        <InputError class="mt-1" :message="form.errors.razon_social" />
                    </div>

                    <div>
                        <InputLabel for="direccion" value="Dirección" />
                        <TextInput
                            id="direccion"
                            v-model="form.direccion"
                            type="text"
                            class="mt-1 block w-full"
                            :error="form.errors.direccion"
                        />
                        <InputError class="mt-1" :message="form.errors.direccion" />
                    </div>

                    <div>
                        <InputLabel for="observaciones_direccion" value="Observaciones dirección" />
                        <TextInput
                            id="observaciones_direccion"
                            v-model="form.observaciones_direccion"
                            type="text"
                            class="mt-1 block w-full"
                            :error="form.errors.observaciones_direccion"
                        />
                        <InputError class="mt-1" :message="form.errors.observaciones_direccion" />
                    </div>

                    <div>
                        <InputLabel for="ciudad" value="Ciudad" />
                        <v-select
                            id="ciudad"
                            v-model="form.ciudad"
                            :options="opcionesDe('ciudad')"
                            :filterable="true"
                            :clearable="true"
                            :taggable="true"
                            placeholder="Escribe para buscar una ciudad"
                            class="mt-1 block w-full"
                        />
                        <InputError class="mt-1" :message="form.errors.ciudad" />
                    </div>

                    <div>
                        <InputLabel for="telefono_principal" value="Teléfono" />
                        <TextInput
                            id="telefono_principal"
                            :model-value="form.telefono_principal"
                            type="tel"
                            inputmode="numeric"
                            class="mt-1 block w-full"
                            :error="form.errors.telefono_principal"
                            @keydown="bloquearNoDigitos"
                            @input="(e: Event) => sanitizarInputTelefono('telefono_principal', e)"
                        />
                        <InputError class="mt-1" :message="form.errors.telefono_principal" />
                    </div>

                    <div>
                        <InputLabel for="tipo_proveedor" value="Tipo proveedor" />
                        <select id="tipo_proveedor" v-model="tipo" :class="selectClass">
                            <option value="">Seleccione un tipo</option>
                            <option v-for="opcion in opcionesDe('tipo_proveedor')" :key="opcion" :value="opcion">
                                {{ opcion }}
                            </option>
                        </select>
                        <TextInput
                            v-if="tipo === OPCION_OTRO"
                            id="tipo_proveedor_otro"
                            v-model="tipoOtro"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="Escribe el tipo de proveedor"
                        />
                        <InputError class="mt-1" :message="form.errors.tipo_proveedor" />
                    </div>

                    <div>
                        <InputLabel for="forma_pago" value="Plazo de pago" />
                        <select id="forma_pago" v-model="plazo" :class="selectClass">
                            <option value="">Seleccione un plazo</option>
                            <option v-for="opcion in opcionesDe('forma_pago')" :key="opcion" :value="opcion">
                                {{ opcion }}
                            </option>
                        </select>
                        <TextInput
                            v-if="plazo === OPCION_OTRO"
                            id="forma_pago_otro"
                            v-model="plazoOtro"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="Escribe el plazo (Ej. Crédito 45 días)"
                        />
                        <InputError class="mt-1" :message="form.errors.forma_pago" />
                    </div>

                    <div>
                        <InputLabel for="tiempo_entrega" value="Tiempo habitual de entrega" />
                        <TextInput
                            id="tiempo_entrega"
                            v-model="form.tiempo_entrega"
                            type="number"
                            min="1"
                            class="mt-1 block w-full"
                            placeholder="Ej. 30 (días)"
                            :error="form.errors.tiempo_entrega"
                        />
                        <InputError class="mt-1" :message="form.errors.tiempo_entrega" />
                    </div>
                </div>
            </div>

            <div class="my-6 rounded-md border border-border bg-muted/40 p-3">
                <p class="text-sm font-medium text-foreground">Código</p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Se genera automáticamente al guardar con el prefijo del tipo elegido:
                    {{ prefijoActual }}-001, {{ prefijoActual }}-002, ...
                </p>
            </div>

            <div class="my-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <div>
                    <InputLabel for="que_suministra" value="Qué suministra" />
                    <TextInput
                        id="que_suministra"
                        v-model="form.que_suministra"
                        type="text"
                        class="mt-1 block w-full"
                        :error="form.errors.que_suministra"
                    />
                    <InputError class="mt-1" :message="form.errors.que_suministra" />
                </div>

                <div class="sm:col-span-2">
                    <InputLabel for="observaciones_generales" value="Observaciones generales" />
                    <textarea
                        id="observaciones_generales"
                        v-model="form.observaciones_generales"
                        rows="4"
                        :class="selectClass"
                        placeholder="Escribe las observaciones generales"
                    ></textarea>
                    <InputError class="mt-1" :message="form.errors.observaciones_generales" />
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
                            :model-value="form.correo_electronico"
                            type="email"
                            class="mt-1 block w-full"
                            placeholder="correo@ejemplo.com"
                            :error="form.errors.correo_electronico"
                            @update:model-value="(valor: string) => setCorreo(valor)"
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