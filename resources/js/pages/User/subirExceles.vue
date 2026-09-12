<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
// import Breadcrumb from '@/components/Breadcrumb.vue';

// import PrimaryButton from '@/components/PrimaryButton.vue';
// import SelectInput from '@/components/SelectInput.vue';
import { reactive, watch } from 'vue';
import pkg from 'lodash';
import { useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';

import { ArrowUpCircleIcon } from '@heroicons/vue/24/solid';

import { vectorSelect } from '@/global.ts';


const { _, debounce, pickBy } = pkg
const props = defineProps({
    title: String,
    // fromController: Object,
    breadcrumbs: Object,
    numUsuarios: Number,
    UniversidadSelect: Object
})
const data = reactive({
    UniversidadSelect: null
})


const form = useForm({
    archivo1: null,
    universidadID: 0,
    // fecha_fin: '2023-04-03T'+horas[1]+':00', //toerase
});

// const QuincenaArray = [
//     { 'value': null, 'label': 'seleccione una quincena' },
//     { 'value': 1, 'label': 1 },
//     { 'value': 2, 'label': 2 }
// ]

function uploadFiletrabajadors() {
    form.post(route('user.uploadtrabajadors'), {
        preserveScroll: true,
        onSuccess: () => {
            // emit("close")
            // form.reset()
            // data.respuesta = $page.props.flash.success
        },
        onError: () => null,
        onFinish: () => null,
    });
}
function uploadtrabajadorsUniversidad() {
    if (form.universidadID === null || form.universidadID === 0) {
        console.log("🧈 debu form.universidadID:", form.universidadID);
        // form.setErrors({
        //     universidadID: 'Your error message.',
        // })
        alert('Seleccione la universidad')

    } else {

        form.post(route('user.uploadUniversidad'), {
            preserveScroll: true,
            onSuccess: () => {
                // emit("close")
                // form.reset()
                // data.respuesta = $page.props.flash.success
            },
            onError: () => null,
            onFinish: () => null,
        });
    }

}

data.UniversidadSelect = vectorSelect(data.UniversidadSelect, props.UniversidadSelect, 'una')

// const downloadExcel = () => { window.open('users/export/' + form.quincena + '/' + (form.fecha_ini.month) + '/' + form.fecha_ini.year, '_blank') }
</script>

<template>

    <Head :title="props.title"></Head>
    <AppLayout>
        <Breadcrumb :title="title" :breadcrumbs="breadcrumbs" />
        <div class="space-y-4">

            <div v-if="$page.props.flash.warning" class="px-4 sm:px-0">
                <div class="rounded-lg overflow-hidden w-fit">
                    <div class="flex max-w-screen-xl shadow-lg rounded-lg">
                        <div class="bg-yellow-600 py-4 px-6 rounded-l-lg flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" class="fill-current text-white"
                                width="20" height="20">
                                <path fill-rule="evenodd"
                                    d="M8.22 1.754a.25.25 0 00-.44 0L1.698 13.132a.25.25 0 00.22.368h12.164a.25.25 0 00.22-.368L8.22 1.754zm-1.763-.707c.659-1.234 2.427-1.234 3.086 0l6.082 11.378A1.75 1.75 0 0114.082 15H1.918a1.75 1.75 0 01-1.543-2.575L6.457 1.047zM9 11a1 1 0 11-2 0 1 1 0 012 0zm-.25-5.25a.75.75 0 00-1.5 0v2.5a.75.75 0 001.5 0v-2.5z">
                                </path>
                            </svg>
                        </div>
                        <div
                            class="px-8 py-6 bg-white rounded-r-lg flex justify-between items-center w-full border border-l-transparent border-gray-200">
                            <div>
                                {{ $page.props.flash.warning ? $page.props.flash.warning : '' }}
                            </div>
                            <!-- <button>
                                <svg xmlns="http://www.w3.org/2000/svg" class="fill-current text-gray-700" viewBox="0 0 16 16" width="20" height="20"> <path fill-rule="evenodd" d="M3.72 3.72a.75.75 0 011.06 0L8 6.94l3.22-3.22a.75.75 0 111.06 1.06L9.06 8l3.22 3.22a.75.75 0 11-1.06 1.06L8 9.06l-3.22 3.22a.75.75 0 01-1.06-1.06L6.94 8 3.72 4.78a.75.75 0 010-1.06z"> </path> </svg>
                            </button> -->
                        </div>
                    </div>
                </div>
            </div>
            <div class="relative bg-transparent dark:bg-transparent">
                <section class="text-gray-600 dark:text-gray-300 body-font">
                    <div class="container px-5 py-8 mx-auto">
                        <div v-if="can(['create user'])" class="flex flex-wrap gap-8 justify-center">
                            <!-- user trabajadors -->
                            <div class="p-4 w-full md:w-[48%]">
                                <div
                                    class="h-full bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 shadow-sm hover:shadow-2xl transition-all duration-300 rounded-2xl overflow-hidden group flex flex-col">
                                    <div
                                        class="bg-gradient-to-br from-blue-50 to-indigo-100 dark:from-gray-800 dark:to-gray-700 flex justify-center items-center h-32 lg:h-48 w-full overflow-hidden">
                                        <ArrowUpCircleIcon
                                            class="h-16 w-16 text-blue-500 dark:text-blue-400 group-hover:scale-110 group-hover:-translate-y-2 transition-all duration-500" />
                                    </div>

                                    <div class="p-6">
                                        <h3 class="title-font text-lg font-medium text-gray-900 mb-3">Subir trabajadors
                                        </h3>
                                        <p class="leading-relaxed mb-3"> Este formulario solo permite cargar trabajadors
                                        </p>

                                        <form @submit.prevent="uploadFiletrabajadors" id="upload">
                                            <div class="mt-4 flex flex-col gap-3">
                                                <input type="file" @input="form.archivo1 = $event.target.files[0]"
                                                    accept="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                                                    class="block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 transition-colors cursor-pointer" />
                                                <progress v-if="form.progress" :value="form.progress.percentage"
                                                    max="100"
                                                    class="w-full h-2 rounded-full overflow-hidden [&::-webkit-progress-bar]:bg-gray-100 [&::-webkit-progress-value]:bg-blue-500 [&::-moz-progress-bar]:bg-blue-500 transition-all">
                                                    {{ form.progress.percentage }}%
                                                </progress>

                                                <PrimaryButton v-show="can(['create user'])"
                                                    :disabled="form.archivo1 == null"
                                                    class="w-full justify-center py-3 rounded-xl transition-all shadow-md hover:shadow-lg"
                                                    :class="{ 'opacity-50 cursor-not-allowed hover:shadow-none': form.archivo1 == null }">
                                                    {{ lang().t('app.button.subir') }}
                                                </PrimaryButton>
                                            </div>
                                        </form>

                                        <h2 class="text-xl text-gray-900 dark:text-white">El formato necesita las
                                            siguientes
                                            columnas</h2>
                                        <ul class="list-decimal my-6 mx-5">
                                            <li class="text-lg">Nombre</li>
                                            <li class="text-lg">Correo: <small class="text-lg">Cada correo debe ser
                                                    unico</small></li>
                                            <li class="text-lg">Identificacion: <small class="text-lg">Debe ser un
                                                    numero</small></li>
                                            <li class="text-lg">Sexo: <small class="text-lg">Femenino, masculino u
                                                    otro</small></li>
                                            <li class="text-lg">Fecha de nacimiento</li>
                                            <li class="text-lg">Semestre</li>
                                            <li class="text-lg">Nivel: <small class="text-lg">Primaria, bachillerato,
                                                    tecnologia, profesional,especializacion,maestría,doctorado</small>
                                            </li>
                                            <li class="text-lg">Cargo: <small class="text-lg">trabajador,
                                                    profesor</small>
                                            </li>
                                        </ul>

                                        <div class="flex items-center flex-wrap my-6">
                                            <a class="text-gray-500 inline-flex items-center md:mb-2 lg:mb-0">Numero de
                                                Usuarios: </a>
                                            <span
                                                class="text-gray-400 mr-3 inline-flex items-center lg:ml-auto md:ml-0 ml-auto leading-none text-sm pr-3 py-1 border-r-2 border-gray-200">
                                                <svg class="w-1 h-4 mr-1" stroke="currentColor" stroke-width="2"
                                                    fill="none" stroke-linecap="round" stroke-linejoin="round"
                                                    viewBox="0 0 24 24">
                                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                    <circle cx="12" cy="12" r="3"></circle>
                                                </svg>
                                                {{ props.numUsuarios }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- user_universidad -->
                            <div class="p-4 w-full md:w-[48%]">
                                <div
                                    class="h-full bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 shadow-sm hover:shadow-2xl transition-all duration-300 rounded-2xl overflow-hidden group flex flex-col">
                                    <div
                                        class="bg-gradient-to-br from-emerald-50 to-teal-100 dark:from-gray-800 dark:to-gray-700 flex justify-center items-center h-32 lg:h-48 w-full overflow-hidden">
                                        <ArrowUpCircleIcon
                                            class="h-16 w-16 text-emerald-500 dark:text-emerald-400 group-hover:scale-110 group-hover:-translate-y-2 transition-all duration-500" />
                                    </div>

                                    <div class="p-6">
                                        <h3 class="title-font text-lg font-medium text-gray-900 mb-3">Subir trabajadors
                                        </h3>
                                        <p class="leading-relaxed mb-3"> Este formulario solo permite cargar trabajadors
                                        </p>

                                        <form @submit.prevent="uploadtrabajadorsUniversidad" id="upload">
                                            <div>
                                                <InputLabel for="universidadID"
                                                    :value="lang().t('app.label.carrera')" />
                                                <SelectInput id="universidadID" class="mt-1 block w-full"
                                                    v-model="form.universidadID" required
                                                    :dataSet="data.UniversidadSelect">
                                                </SelectInput>
                                                <InputError class="mt-2" :message="form.errors.universidadID" />
                                            </div>
                                            <div class="mt-4 flex flex-col gap-3">
                                                <input type="file" @input="form.archivo1 = $event.target.files[0]"
                                                    accept="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                                                    class="block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 transition-colors cursor-pointer" />
                                                <progress v-if="form.progress" :value="form.progress.percentage"
                                                    max="100"
                                                    class="w-full h-2 rounded-full overflow-hidden [&::-webkit-progress-bar]:bg-gray-100 [&::-webkit-progress-value]:bg-emerald-500 [&::-moz-progress-bar]:bg-emerald-500 transition-all">
                                                    {{ form.progress.percentage }}%
                                                </progress>

                                                <PrimaryButton v-show="can(['create user'])"
                                                    :disabled="form.archivo1 == null"
                                                    class="w-full justify-center py-3 rounded-xl transition-all shadow-md hover:shadow-lg"
                                                    :class="{ 'opacity-50 cursor-not-allowed hover:shadow-none': form.archivo1 == null }">
                                                    {{ lang().t('app.button.subir') }}
                                                </PrimaryButton>
                                            </div>
                                        </form>

                                        <h2 class="text-xl text-gray-900 dark:text-white">El formato necesita las
                                            siguientes
                                            columnas</h2>
                                        <ul class="list-decimal my-6 mx-5">
                                            <li class="text-lg">Identificacion : del trabajador a inscribir</li>
                                        </ul>

                                        <!-- <div class="flex items-center flex-wrap my-6">
                                            <a class="text-gray-500 inline-flex items-center md:mb-2 lg:mb-0">Numero de Usuarios: </a>
                                            <span
                                                class="text-gray-400 mr-3 inline-flex items-center lg:ml-auto md:ml-0 ml-auto leading-none text-sm pr-3 py-1 border-r-2 border-gray-200">
                                                <svg class="w-1 h-4 mr-1" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"> <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path> <circle cx="12" cy="12" r="3"></circle> </svg>
                                                {{  props.numUsuarios }}
                                            </span>
                                        </div> -->
                                    </div>
                                </div>
                            </div>
                            <!-- user matriculas : multiple -->

                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
