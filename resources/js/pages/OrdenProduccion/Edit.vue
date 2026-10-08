<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import InputLabel from '@/components/InputLabel.vue';
import Modal from '@/components/Modal.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { onMounted, reactive, watchEffect } from 'vue';
import "vue-select/dist/vue-select.css";


const props = defineProps({
    show: Boolean,
    title: String,
    OrdenProducciona: Object,
    titulos: Object, //parametros de la clase principal
    losSelect: Object,

})

const emit = defineEmits(["close"]);
const data = reactive({
    printForm: []
})

//very usefull
const justNames = props.titulos.map(names => names['order'])
const form = useForm({ ...Object.fromEntries(justNames.map(field => [field, ''])) });
onMounted(() => {
    if (props.numberPermissions > 9) {
        const valueRAn = Math.floor(Math.random() * 9 + 1)
        form.nombre = 'admin orden trabajo ' + (valueRAn);
        form.codigo = (valueRAn);
        // form.hora_inicial = '0'+valueRAn+':00'//temp
        // form.fecha = '2023-06-01'
    }
    // data.printForm.length -= 1 //dependex
});

props.titulos.forEach(names => {
    data.printForm.push({
        idd: names['order'], label: names['label'], type: names['type']
        , value: form[names['order']]
    })
});

watchEffect(() => {
    if (props.show) {
        // data.justNames.forEach(element => {
        //     form[element] =  props.OrdenProducciona[element]
        // });
        form.errors = {}
        props.titulos.forEach(names => {
            form[names['order']] = props.OrdenProducciona[names['order']]
        });

        // form.codigo = props.OrdenProducciona?.codigo
    }
})

const update = () => {
    form.put(route('OrdenProduccion.update', props.OrdenProducciona?.id), {
        preserveScroll: true,
        onSuccess: () => {
            emit("close")
            form.reset()
        },
        onError: () => null,
        onFinish: () => null,
    })
}
// const sexos = [ { label: 'Masculino', value: 'Masculino' }, { label: 'Femenino', value: 'Femenino' } ];

</script>

<template>
    <section class="space-y-6">
        <Modal :show="props.show" @close="emit('close')" :maxWidth="'xl4'">
            <form class="p-6" @submit.prevent="create">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    {{ lang().t('app.label.edit') }} {{ props.title }}
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">


                    <!--                    <div id="SelectVue" class="">-->
                    <!--                        <label name="labelSelectVue2"> Centro de costo </label>-->
                    <!--                        <v-select :options="props.losSelect[0]"-->
                    <!--                                  v-model="form.centro_costo_id"-->
                    <!--                                  label="name"-->
                    <!--                        ></v-select>-->
                    <!--                    </div>-->



                    <div v-for="(atributosform, indice) in data.printForm" :key="indice">
                        <div v-if="atributosform.idd === 'responsable_id'" class="">
                            <InputLabel :for="atributosform.label"
                                :value="lang().t('app.label')[atributosform.label] || atributosform.label" />
                            <select
                                :id="atributosform.idd"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 shadow-sm"
                                v-model="form[atributosform.idd]"
                                required
                            >
                                <option value="" disabled>Seleccione una opción</option>
                                <option v-for="item in ((props.losSelect as any)?.responsable_id || [])" :key="item.id" :value="item.id">
                                    {{ item.name }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="form.errors[atributosform.idd]" />
                        </div>

                        <div v-else-if="atributosform.idd === 'estado_op_id'" class="">
                            <InputLabel :for="atributosform.label"
                                :value="lang().t('app.label')[atributosform.label] || atributosform.label" />
                            <select
                                :id="atributosform.idd"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 shadow-sm"
                                v-model="form[atributosform.idd]"
                                required
                            >
                                <option value="" disabled>Seleccione una opción</option>
                                <option v-for="item in ((props.losSelect as any)?.estado_op_id || [])" :key="item.id" :value="item.name">
                                    {{ item.name }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="form.errors[atributosform.idd]" />
                        </div>

                        <div v-else-if="atributosform.idd === 'producto_referencia_id'" class="">
                            <InputLabel :for="atributosform.label"
                                :value="lang().t('app.label')[atributosform.label] || atributosform.label" />
                            <select
                                :id="atributosform.idd"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 shadow-sm"
                                v-model="form[atributosform.idd]"
                                required
                            >
                                <option value="" disabled>Seleccione una opción</option>
                                <option v-for="item in ((props.losSelect as any)?.producto_referencia_id || [])" :key="item.id" :value="item.id">
                                    {{ item.name }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="form.errors[atributosform.idd]" />
                        </div>

                        <div v-else-if="atributosform.type === 'foreign'" id="SelectVue" class="">
                            <label name="labelSelectVue"> {{ atributosform.label }} </label>
                            <v-select :options="(props.losSelect as any)?.[atributosform.idd] || []" v-model="form[atributosform.idd]"
                                :reduce="(element: any) => element.value" label="name"></v-select>
                            <InputError class="mt-2" :message="form.errors[atributosform.idd]" />
                        </div>

                        <div v-else-if="atributosform.type === 'time'" id="SelectVue">
                            <InputLabel :for="atributosform.label"
                                :value="lang().t('app.label')[atributosform.label]" />
                            <TextInput :id="atributosform.idd" :type="atributosform.type" class="mt-1 block w-full"
                                v-model="form[atributosform.idd]" required :placeholder="atributosform.label"
                                :error="form.errors[atributosform.idd]" step="3600" />
                            <InputError class="mt-2" :message="form.errors[atributosform.idd]" />
                        </div>
                        <div v-else class="">
                            <InputLabel :for="atributosform.label"
                                :value="lang().t('app.label')[atributosform.label]" />
                            <TextInput :id="atributosform.idd" :type="atributosform.type" class="mt-1 block w-full"
                                v-model="form[atributosform.idd]" required :placeholder="atributosform.label"
                                :error="form.errors[atributosform.idd]" />
                            <InputError class="mt-2" :message="form.errors[atributosform.idd]" />
                        </div>
                    </div>

                </div>
                <div class=" my-8 flex justify-end">
                    <SecondaryButton :disabled="form.processing" @click="emit('close')"> {{ lang().t('app.button.close')
                        }}
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
[name="labelSelectVue"],
[name="labelSelectVue"] {
    /* font-size: 22px; */
    font-weight: 600;
}
</style>
