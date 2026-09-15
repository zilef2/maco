<script setup>
import InputError from '@/components/InputError.vue';
import InputLabel from '@/components/InputLabel.vue';
import Modal from '@/components/Modal.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { onMounted, reactive, watchEffect, watch } from 'vue';
import '@vuepic/vue-datepicker/dist/main.css'
import vSelect from "vue-select";
import "vue-select/dist/vue-select.css";
import { Date_to_html, formatdinero } from '@/global.ts';

// --------------------------- ** -------------------------

const props = defineProps({
    show: Boolean,
    title: String,
    roles: Object,
    titulos: Object, //parametros de la clase principal
    losSelect: Object,
    numberPermissions: Number,
})
const emit = defineEmits(["close"]);

const data = reactive({
    params: {
        pregunta: ''
    },
})

//very usefull
let justNames = props.titulos.map(names => {
    if (names['order'] !== 'fecha_cotizacion'
        && names['order'] !== 'fecha_cotizacion_aceptada'
        && names['order'] !== 'estado'
    )
        return names['order']
})
const form = useForm({
    ...Object.fromEntries(justNames.map(field => [field, ''])),
});
onMounted(() => {
    if (props.numberPermissions > 9) {

        setTimeout(() => {
            const valueRAn = Math.floor(Math.random() * (9) + 1)
            form.nombre = 'Cotización nueva funcion ';
            let hoy = new Date()
            form.fecha_reunion = Date_to_html(hoy)
            form.valor_inicial = valueRAn * 100000
        }, 1000);
    }
});

const printForm = [];
props.titulos.forEach(names => {
    if (names['order'] !== 'fecha_cotizacion'
        && names['order'] !== 'fecha_cotizacion_aceptada'
        && names['order'] !== 'estado'
    )
        printForm.push({
            idd: names['order'], label: names['label'], type: names['type']
        })
});

function ValidarVacios() {
    let result = true
    printForm.forEach(element => {
        if (!form[element.idd]) {
            console.log("=>(Create.vue:70) falta esto papa element.idd", element.idd);
            result = false
            return result
        }
    });
    return result
}

const create = () => {
    if (ValidarVacios()) {
        // console.log("🧈 debu pieza_id:", form.pieza_id);
        form.post(route('desarrollo.store'), {
            preserveScroll: true,
            onSuccess: () => {
                emit("close")
                form.reset()
            },
            onError: () => null,
            onFinish: () => null,
        })
    } else {
        console.log('Hay campos vacios')
    }
}

watch(() => form.valor_inicial, (newValor) => {
    let newval = parseInt(newValor);
    form.valor_parcial1 = newval / 2;
});

watchEffect(() => {
    if (props.show) {
        form.errors = {}
    }
})


// const sexos = [{ label: 'Masculino', value: 0 }, { label: 'Femenino', value: 1 }];

const estados = [
    'Cotizando',
    'Desarrollando',
    'Esperando pago parcial',
    'Pagada totalmente',
    'Finalizada'
];
</script>

<template>
    <section>
        <Modal :show="props.show" @close="emit('close')" maxWidth="xl3">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-2xl overflow-hidden transform transition-all">
                <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50">
                    <h2 class="text-xl font-bold text-gray-800 dark:text-gray-100 flex items-center gap-3">
                        <div class="p-2 bg-indigo-100 dark:bg-indigo-900/50 rounded-lg">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-600 dark:text-indigo-400"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4v16m8-8H4" />
                            </svg>
                        </div>
                        <span>{{ lang().t('app.label.add') }} {{ props.title }}</span>
                    </h2>
                </div>

                <form class="p-6 sm:p-8" @submit.prevent="create">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-6">
                        <div v-for="(atributosform, indice) in printForm" :key="indice"
                            :class="{ 'md:col-span-2': atributosform.idd === 'descripcion' }">

                            <!-- foreign -->
                            <div v-if="atributosform.type === 'foreign'" class="flex flex-col gap-1.5">
                                <InputLabel :for="atributosform.idd" :value="atributosform.label"
                                    class="text-sm font-semibold text-gray-700 dark:text-gray-300 ml-1" />
                                <div class="relative rounded-md shadow-sm">
                                    <v-select :options="props.losSelect[0]" v-model="form[atributosform.idd]"
                                        :reduce="element => element.value" label="name"
                                        class="w-full bg-white dark:bg-gray-900 border-gray-300 dark:border-gray-700 rounded-md focus-within:ring-2 focus-within:ring-indigo-500 focus-within:border-indigo-500"></v-select>
                                </div>
                                <InputError class="mt-1" :message="form.errors[atributosform.idd]" />
                            </div>

                            <!-- textarea -->
                            <div v-else-if="atributosform.idd === 'descripcion'" class="flex flex-col gap-1.5">
                                <InputLabel :for="atributosform.idd"
                                    :value="lang().t('app.label.' + atributosform.label) || atributosform.label"
                                    class="text-sm font-semibold text-gray-700 dark:text-gray-300 ml-1" />
                                <textarea :id="atributosform.idd"
                                    class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm transition-colors duration-200 resize-y"
                                    v-model="form[atributosform.idd]" required :placeholder="atributosform.label"
                                    rows="4"
                                    :class="{ 'border-red-500 focus:border-red-500 focus:ring-red-500': form.errors[atributosform.idd] }"></textarea>
                                <InputError class="mt-1" :message="form.errors[atributosform.idd]" />
                            </div>

                            <!-- tiempo -->
                            <div v-else-if="atributosform.type === 'time'" class="flex flex-col gap-1.5">
                                <InputLabel :for="atributosform.idd"
                                    :value="lang().t('app.label.' + atributosform.label) || atributosform.label"
                                    class="text-sm font-semibold text-gray-700 dark:text-gray-300 ml-1" />
                                <TextInput :id="atributosform.idd" :type="atributosform.type"
                                    class="mt-1 block w-full rounded-lg shadow-sm transition-colors duration-200"
                                    v-model="form[atributosform.idd]" required :placeholder="atributosform.label"
                                    :error="form.errors[atributosform.idd]" step="3600" />
                                <InputError class="mt-1" :message="form.errors[atributosform.idd]" />
                            </div>

                            <!-- normal -->
                            <div v-else class="flex flex-col gap-1.5">
                                <InputLabel :for="atributosform.idd"
                                    :value="lang().t('app.label.' + atributosform.label) || atributosform.label"
                                    class="text-sm font-semibold text-gray-700 dark:text-gray-300 ml-1" />
                                <TextInput :id="atributosform.idd" :type="atributosform.type"
                                    class="mt-1 block w-full rounded-lg shadow-sm transition-colors duration-200"
                                    v-model="form[atributosform.idd]" required :placeholder="atributosform.label"
                                    :error="form.errors[atributosform.idd]" />
                                <InputError class="mt-1" :message="form.errors[atributosform.idd]" />
                                <div v-if="atributosform.type === 'number' || atributosform.idd.includes('valor')"
                                    class="mt-1 text-xs font-mono text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 px-2 py-1 rounded-md inline-flex items-center gap-1 shadow-sm border border-indigo-100 dark:border-indigo-800">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    {{ formatdinero(form[atributosform.idd]) }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        class="mt-8 pt-5 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-3 items-center">
                        <SecondaryButton :disabled="form.processing" @click="emit('close')"
                            class="px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors shadow-sm">
                            {{ lang().t('app.button.close') }}
                        </SecondaryButton>
                        <PrimaryButton :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                            :disabled="form.processing" @click="create"
                            class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all shadow-md hover:shadow-lg flex items-center">
                            <svg v-if="form.processing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                            <span v-if="!form.processing">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1.5 inline-block" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v16m8-8H4" />
                                </svg>
                            </span>
                            {{ lang().t('app.button.add') }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </section>
</template>
