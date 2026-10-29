<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import InputLabel from '@/components/InputLabel.vue';
import Modal from '@/components/Modal.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';

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
    tiempo_entrega: '',
    estado: 'Activo',
    observaciones_generales: '',
    clasificacion: 'proveedor',
});

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

            <div class="my-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <InputLabel for="codigo_proveedor" value="Código proveedor" />
                    <TextInput
                        id="codigo_proveedor"
                        v-model="form.codigo_proveedor"
                        type="text"
                        class="mt-1 block w-full bg-gray-50 cursor-not-allowed"
                        placeholder="Ej. PROV-001 (generado automáticamente)"
                        :error="form.errors.codigo_proveedor"
                        readonly
                    />
                    <InputError class="mt-1" :message="form.errors.codigo_proveedor" />
                </div>

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
                    <InputLabel for="nombre_comercial" value="Nombre comercial *" />
                    <TextInput
                        id="nombre_comercial"
                        v-model="form.nombre_comercial"
                        type="text"
                        class="mt-1 block w-full"
                        required
                        placeholder="Nombre comercial"
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
                        placeholder="Razón social completa"
                        :error="form.errors.razon_social"
                    />
                    <InputError class="mt-1" :message="form.errors.razon_social" />
                </div>

                <div>
                    <InputLabel for="tipo_proveedor" value="Tipo proveedor" />
                    <select id="tipo_proveedor" v-model="form.tipo_proveedor" :class="selectClass">
                        <option value="">Selecciona tipo</option>
                        <option
                            v-for="opcion in (props.opcionesSelect?.tipo_proveedor || ['Materia prima', 'Servicio', 'Mixto', 'Otro'])"
                            :key="opcion"
                            :value="opcion"
                        >
                            {{ opcion }}
                        </option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.tipo_proveedor" />
                </div>

                <div>
                    <InputLabel for="clasificacion" value="Proveedor o externa o macondas" />
                    <select id="clasificacion" v-model="form.clasificacion" :class="selectClass">
                        <option
                            v-for="opcion in (props.opcionesSelect?.clasificacion || ['proveedor', 'taller de proceso externo', 'macondas'])"
                            :key="opcion"
                            :value="opcion"
                        >
                            {{ opcion }}
                        </option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.clasificacion" />
                </div>

                <div>
                    <InputLabel for="ciudad" value="Ciudad" />
                    <TextInput
                        id="ciudad"
                        v-model="form.ciudad"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="Ej. Medellín, Bogotá"
                        :error="form.errors.ciudad"
                    />
                    <InputError class="mt-1" :message="form.errors.ciudad" />
                </div>

                <div>
                    <InputLabel for="direccion" value="Dirección" />
                    <TextInput
                        id="direccion"
                        v-model="form.direccion"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="Dirección física"
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
                        placeholder="Barrio, bodega, oficina, punto de referencia"
                        :error="form.errors.observaciones_direccion"
                    />
                    <InputError class="mt-1" :message="form.errors.observaciones_direccion" />
                </div>

                <div>
                    <InputLabel for="correo_electronico" value="Correo electrónico" />
                    <TextInput
                        id="correo_electronico"
                        v-model="form.correo_electronico"
                        type="email"
                        class="mt-1 block w-full"
                        placeholder="contacto@proveedor.com"
                        :error="form.errors.correo_electronico"
                    />
                    <InputError class="mt-1" :message="form.errors.correo_electronico" />
                </div>

                <div>
                    <InputLabel for="telefono_principal" value="Teléfono principal" />
                    <TextInput
                        id="telefono_principal"
                        v-model="form.telefono_principal"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="Teléfono fijo o PBX"
                        :error="form.errors.telefono_principal"
                    />
                    <InputError class="mt-1" :message="form.errors.telefono_principal" />
                </div>

                <div>
                    <InputLabel for="persona_contacto" value="Persona de contacto" />
                    <TextInput
                        id="persona_contacto"
                        v-model="form.persona_contacto"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="Nombre del asesor o contacto"
                        :error="form.errors.persona_contacto"
                    />
                    <InputError class="mt-1" :message="form.errors.persona_contacto" />
                </div>

                <div>
                    <InputLabel for="celular_contacto" value="Celular / WhatsApp contacto" />
                    <TextInput
                        id="celular_contacto"
                        v-model="form.celular_contacto"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="Ej. 3123456789"
                        :error="form.errors.celular_contacto"
                    />
                    <InputError class="mt-1" :message="form.errors.celular_contacto" />
                </div>

                <div>
                    <InputLabel for="forma_pago" value="Forma / plazo de pago" />
                    <select id="forma_pago" v-model="form.forma_pago" :class="selectClass">
                        <option value="">Selecciona forma de pago</option>
                        <option
                            v-for="opcion in (props.opcionesSelect?.forma_pago || ['Contado', 'Crédito 8 días', 'Crédito 15 días', 'Crédito 30 días', 'Anticipo', 'Otro'])"
                            :key="opcion"
                            :value="opcion"
                        >
                            {{ opcion }}
                        </option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.forma_pago" />
                </div>

                <div>
                    <InputLabel for="tiempo_entrega" value="Tiempo habitual de entrega" />
                    <TextInput
                        id="tiempo_entrega"
                        v-model="form.tiempo_entrega"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="Ej. Inmediato, 3 a 5 días"
                        :error="form.errors.tiempo_entrega"
                    />
                    <InputError class="mt-1" :message="form.errors.tiempo_entrega" />
                </div>

                <div>
                    <InputLabel for="estado" value="Estado" />
                    <select id="estado" v-model="form.estado" :class="selectClass">
                        <option
                            v-for="opcion in (props.opcionesSelect?.estado || ['Activo', 'Inactivo'])"
                            :key="opcion"
                            :value="opcion"
                        >
                            {{ opcion }}
                        </option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.estado" />
                </div>

                <div class="sm:col-span-2 lg:col-span-3">
                    <InputLabel for="que_suministra" value="Qué suministra / servicio" />
                    <TextInput
                        id="que_suministra"
                        v-model="form.que_suministra"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="Descripción de insumos, telas, accesorios o servicios que provee"
                        :error="form.errors.que_suministra"
                    />
                    <InputError class="mt-1" :message="form.errors.que_suministra" />
                </div>

                <div class="sm:col-span-2 lg:col-span-3">
                    <InputLabel for="observaciones_generales" value="Observaciones generales" />
                    <textarea
                        id="observaciones_generales"
                        v-model="form.observaciones_generales"
                        rows="2"
                        class="mt-1 block w-full rounded-md border border-input bg-background p-2.5 text-sm text-foreground shadow-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30"
                        placeholder="Comentarios adicionales, acuerdos comerciales, condiciones especiales..."
                    />
                    <InputError class="mt-1" :message="form.errors.observaciones_generales" />
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
