<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import InputLabel from '@/components/InputLabel.vue';
import Modal from '@/components/Modal.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { ref, watchEffect } from 'vue';

const props = defineProps({
    show: Boolean,
    title: String,
    subUnidadsSelect: Object,
    numberPermissions: Number,

})

const emit = defineEmits(["close"]);

const nombreInput = ref(null);

const form = useForm({
    nombre: '',
    categoria: '',
    celular1: '',
    celular2: '',
});

const soloDigitos = (valor) => {
    return String(valor ?? '').replace(/\D/g, '').slice(0, 15);
};

const numeroRandom = (min = 1000000000, max = 5999999999) => {
    return Math.floor(Math.random() * (max - min + 1)) + min;
};

const textoProveedor = () => {
    return 'proveedor ' + Math.random().toString(36).substring(2, 8).toUpperCase();
};

const normalizarCelulares = () => {
    form.celular1 = soloDigitos(form.celular1);
    form.celular2 = soloDigitos(form.celular2);
};

const closeModal = () => {
    emit("close");
    form.reset();
};

const create = (continuar = false) => {
    normalizarCelulares();
    form.transform((data) => ({
        ...data,
        celular1: data.celular1 === '' ? null : data.celular1,
        celular2: data.celular2 === '' ? null : data.celular2,
    })).post(route('Proveedor.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            if (continuar) {
                if (nombreInput.value) {
                    nombreInput.value.focus();
                }
            } else {
                emit("close");
            }
        },
        // onError: () => alert(form.errors),
        // onError: () => null,
        onError: () => alert(JSON.stringify(form.errors, null, 4)),

        onFinish: () => null,
    });
};

watchEffect(() => {
    if (props.show) {
        form.errors = {};
        if (props.numberPermissions === 100) {
            if (!form.nombre) {
                form.nombre = textoProveedor();
            }
            if (!form.categoria) {
                form.categoria = textoProveedor();
            }
            if (!form.celular1) {
                form.celular1 = String(numeroRandom());
            }
            if (!form.celular2) {
                form.celular2 = String(numeroRandom());
            }
        }
    }
});

</script>

<template>
    <section class="space-y-6">
        <Modal :show="props.show" @close="closeModal">
            <form class="p-6" @submit.prevent="create(false)">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    {{ lang().t('app.label.add') }} {{ props.title }}
                </h2>
                <div class="my-6 grid grid-cols-1 gap-6">
                    <div>
                        <InputLabel for="nombre" :value="lang().t('app.label.nombre') || 'Nombre'" />
                        <TextInput id="nombre" ref="nombreInput" type="text" class="mt-1 block w-full"
                            v-model="form.nombre" required placeholder="Nombre del proveedor"
                            :error="form.errors.nombre" />
                        <InputError class="mt-2" :message="form.errors.nombre" />
                    </div>
                    <div>
                        <InputLabel for="categoria" :value="lang().t('app.label.categoria') || 'Categoria'" />
                        <TextInput id="categoria" type="text" class="mt-1 block w-full"
                            v-model="form.categoria" required placeholder="Categoría"
                            :error="form.errors.categoria" />
                        <InputError class="mt-2" :message="form.errors.categoria" />
                    </div>
                    <div>
                        <InputLabel for="celular1" :value="lang().t('app.label.celular1') || 'Celular 1'" />
                        <TextInput id="celular1" type="text" inputmode="tel" pattern="[0-9]*" maxlength="15" class="mt-1 block w-full"
                            v-model="form.celular1" placeholder="Ej: 573129998877"
                            :error="form.errors.celular1" />
                        <InputError class="mt-2" :message="form.errors.celular1" />
                    </div>
                    <div>
                        <InputLabel for="celular2" :value="lang().t('app.label.celular2') || 'Celular 2'" />
                        <TextInput id="celular2" type="text" inputmode="tel" pattern="[0-9]*" maxlength="15" class="mt-1 block w-full"
                            v-model="form.celular2" placeholder="Opcional"
                            :error="form.errors.celular2" />
                        <InputError class="mt-2" :message="form.errors.celular2" />
                    </div>
                </div>

                <div class="flex justify-end">
                    <SecondaryButton :disabled="form.processing" @click="closeModal"> {{ lang().t('app.button.close')
                        }}
                    </SecondaryButton>
                    <PrimaryButton class="ml-3" :class="{ 'opacity-25': form.processing }" :disabled="form.processing"
                        type="button" @click="create(true)">
                        {{ form.processing ? lang().t('app.button.saving') + '...' : lang().t('app.button.save_continue') || 'Guardar y continuar' }}
                    </PrimaryButton>
                    <PrimaryButton class="ml-3" :class="{ 'opacity-25': form.processing }" :disabled="form.processing"
                        type="submit">
                        {{ form.processing ? lang().t('app.button.add') + '...' : lang().t('app.button.add') }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    </section>
</template>
