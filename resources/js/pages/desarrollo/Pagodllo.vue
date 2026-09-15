<script setup>
import InputError from '@/components/InputError.vue';
import InputLabel from '@/components/InputLabel.vue';
import Modal from '@/components/Modal.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { onMounted, reactive, watchEffect } from 'vue';
import "vue-select/dist/vue-select.css";
import vSelect from "vue-select";
import "vue-select/dist/vue-select.css";



const props = defineProps({
    show: Boolean,
    title: String,
    desarrolloa: Object,
    titulos: Object, //parametros de la clase principal
    losSelect: Object,
})

const emit = defineEmits(["close"]);
const data = reactive({
    numCoutasaNow: 0,
})

const form = useForm({
    valor: '',
    fecha: '',
    estado: '',
});
onMounted(() => {
    if (props.numberPermissions > 9) { }
});

watchEffect(() => {
    if (props.show) {
        form.errors = {}
        data.numCoutasaNow = props.desarrolloa?.Numcuotas + 1
    }
})

const update = () => {
    form.put(route('updatePago', props.desarrolloa?.id), {
        preserveScroll: true,
        onSuccess: () => {
            emit("close")
            form.reset()
        },
        onError: () => null,
        onFinish: () => null,
    })
}

</script>

<template>
    <section class="space-y-6">
        <Modal :show="props.show" @close="emit('close')" :maxWidth="'xl4'" class="pb-20">
            <form class="px-6 pt-6 pb-20" @submit.prevent="create">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    {{ lang().t('app.label.edit') }} {{ props.title }}
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-8 gap-8">
                    <div class="col-span-2">
                        <InputLabel for="valor" :value="lang().t('app.label.valor')" />
                        <TextInput id="valor" type="number" class="mt-1 block w-full" v-model="form.valor"
                            placeholder="valor" :error="form.errors.valor" />
                        <InputError class="mt-2" :message="form.errors.valor" />
                    </div>
                    <div class="col-span-2">
                        <InputLabel for="fecha" :value="lang().t('app.label.fecha')" />
                        <TextInput id="fecha" type="date" class="mt-1 block w-full" v-model="form.fecha"
                            placeholder="fecha" :error="form.errors.fecha" />
                        <InputError class="mt-2" :message="form.errors.fecha" />
                    </div>
                    <div class="">
                        <InputLabel for="cuota" :value="lang().t('app.label.cuota')" />
                        <TextInput id="cuota" type="number" class="px-2 mt-1 block w-full bg-gray-400"
                            v-model="data.numCoutasaNow" disabled />
                    </div>
                    <div class="col-span-3">
                        <label name="estado">
                            {{ lang().t('app.label.estado') }}
                        </label>
                        <vSelect v-model="form.estado" :options="[
                            { value: 'Cotizando', label: 'Cotizando' },
                            { value: 'Desarrollando', label: 'Desarrollando' },
                            { value: 'Esperando pago parcial', label: 'Esperando pago parcial' },
                            { value: 'Pagada totalmente', label: 'Pagada totalmente' },
                            { value: 'Finalizada', label: 'Finalizada' },
                        ]" label="label" class="max-h-4"></vSelect>
                    </div>


                </div>
                <div class=" my-8 flex justify-end">
                    <SecondaryButton :disabled="form.processing" @click="emit('close')"> {{ lang().t('app.button.close') }}
                    </SecondaryButton>
                    <PrimaryButton class="ml-3" :class="{ 'opacity-25': form.processing }" :disabled="form.processing"
                        @click="update">
                        {{ form.processing ? lang().t('app.button.save') + '...' : lang().t('app.button.save') }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    </section>
</template>

<style>
</style>
