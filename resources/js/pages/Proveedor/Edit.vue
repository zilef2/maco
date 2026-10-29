<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import InputLabel from '@/components/InputLabel.vue';
import Modal from '@/components/Modal.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

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
            clasificacion: ['proveedor', 'taller de proceso externo', 'macondas'],
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
    tiempo_entrega: '',
    estado: 'Activo',
    observaciones_generales: '',
    clasificacion: 'proveedor',
});

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
            form.tiempo_entrega = props.proveedora.tiempo_entrega ?? '';
            form.estado = props.proveedora.estado ?? 'Activo';
            form.observaciones_generales = props.proveedora.observaciones_generales ?? '';
            form.clasificacion = props.proveedora.clasificacion ?? 'proveedor';
        }
    },
);
</script>

<template>
    <Modal :show="props.show" @close="closeModal" maxWidth="4xl">
        <form class="p-6" @submit.prevent="submit">
            <div class="border-b border-border pb-4">
                <h2 class="text-lg font-semibold text-foreground">
                    Editar proveedor
                </h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Actualiza la información comercial, operativa y de contacto del proveedor.
                </p>
            </div>

            <div class="my-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <InputLabel for="edit_codigo_proveedor" value="Código proveedor" />
                    <TextInput
                        id="edit_codigo_proveedor"
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
                    <InputLabel for="edit_nit" value="NIT" />
                    <TextInput
                        id="edit_nit"
                        v-model="form.nit"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="Ej. 900.123.456-7"
                        :error="form.errors.nit"
                    />
                    <InputError class="mt-1" :message="form.errors.nit" />
                </div>

                <div>
                    <InputLabel for="edit_nombre_comercial" value="Nombre comercial *" />
                    <TextInput
                        id="edit_nombre_comercial"
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
                    <InputLabel for="edit_razon_social" value="Razón social" />
                    <TextInput
                        id="edit_razon_social"
                        v-model="form.razon_social"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="Razón social completa"
                        :error="form.errors.razon_social"
                    />
                    <InputError class="mt-1" :message="form.errors.razon_social" />
                </div>

                <div>
                    <InputLabel for="edit_tipo_proveedor" value="Tipo proveedor" />
                    <select id="edit_tipo_proveedor" v-model="form.tipo_proveedor" :class="selectClass">
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
                    <InputLabel for="edit_clasificacion" value="Proveedor o externa o macondas" />
                    <select id="edit_clasificacion" v-model="form.clasificacion" :class="selectClass">
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
                    <InputLabel for="edit_ciudad" value="Ciudad" />
                    <TextInput
                        id="edit_ciudad"
                        v-model="form.ciudad"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="Ej. Medellín, Bogotá"
                        :error="form.errors.ciudad"
                    />
                    <InputError class="mt-1" :message="form.errors.ciudad" />
                </div>

                <div>
                    <InputLabel for="edit_direccion" value="Dirección" />
                    <TextInput
                        id="edit_direccion"
                        v-model="form.direccion"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="Dirección física"
                        :error="form.errors.direccion"
                    />
                    <InputError class="mt-1" :message="form.errors.direccion" />
                </div>

                <div>
                    <InputLabel for="edit_observaciones_direccion" value="Observaciones dirección" />
                    <TextInput
                        id="edit_observaciones_direccion"
                        v-model="form.observaciones_direccion"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="Barrio, bodega, oficina, punto de referencia"
                        :error="form.errors.observaciones_direccion"
                    />
                    <InputError class="mt-1" :message="form.errors.observaciones_direccion" />
                </div>

                <div>
                    <InputLabel for="edit_correo_electronico" value="Correo electrónico" />
                    <TextInput
                        id="edit_correo_electronico"
                        v-model="form.correo_electronico"
                        type="email"
                        class="mt-1 block w-full"
                        placeholder="contacto@proveedor.com"
                        :error="form.errors.correo_electronico"
                    />
                    <InputError class="mt-1" :message="form.errors.correo_electronico" />
                </div>

                <div>
                    <InputLabel for="edit_telefono_principal" value="Teléfono principal" />
                    <TextInput
                        id="edit_telefono_principal"
                        v-model="form.telefono_principal"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="Teléfono fijo o PBX"
                        :error="form.errors.telefono_principal"
                    />
                    <InputError class="mt-1" :message="form.errors.telefono_principal" />
                </div>

                <div>
                    <InputLabel for="edit_persona_contacto" value="Persona de contacto" />
                    <TextInput
                        id="edit_persona_contacto"
                        v-model="form.persona_contacto"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="Nombre del asesor o contacto"
                        :error="form.errors.persona_contacto"
                    />
                    <InputError class="mt-1" :message="form.errors.persona_contacto" />
                </div>

                <div>
                    <InputLabel for="edit_celular_contacto" value="Celular / WhatsApp contacto" />
                    <TextInput
                        id="edit_celular_contacto"
                        v-model="form.celular_contacto"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="Ej. 3123456789"
                        :error="form.errors.celular_contacto"
                    />
                    <InputError class="mt-1" :message="form.errors.celular_contacto" />
                </div>

                <div>
                    <InputLabel for="edit_forma_pago" value="Forma / plazo de pago" />
                    <select id="edit_forma_pago" v-model="form.forma_pago" :class="selectClass">
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
                    <InputLabel for="edit_tiempo_entrega" value="Tiempo habitual de entrega" />
                    <TextInput
                        id="edit_tiempo_entrega"
                        v-model="form.tiempo_entrega"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="Ej. Inmediato, 3 a 5 días"
                        :error="form.errors.tiempo_entrega"
                    />
                    <InputError class="mt-1" :message="form.errors.tiempo_entrega" />
                </div>

                <div>
                    <InputLabel for="edit_estado" value="Estado" />
                    <select id="edit_estado" v-model="form.estado" :class="selectClass">
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
                    <InputLabel for="edit_que_suministra" value="Qué suministra / servicio" />
                    <TextInput
                        id="edit_que_suministra"
                        v-model="form.que_suministra"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="Descripción de insumos, telas, accesorios o servicios que provee"
                        :error="form.errors.que_suministra"
                    />
                    <InputError class="mt-1" :message="form.errors.que_suministra" />
                </div>

                <div class="sm:col-span-2 lg:col-span-3">
                    <InputLabel for="edit_observaciones_generales" value="Observaciones generales" />
                    <textarea
                        id="edit_observaciones_generales"
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
                <PrimaryButton :disabled="form.processing" type="submit">
                    {{ form.processing ? 'Guardando...' : 'Actualizar proveedor' }}
                </PrimaryButton>
            </div>
        </form>
    </Modal>
</template>
