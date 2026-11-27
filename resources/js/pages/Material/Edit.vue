<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import InputLabel from '@/components/InputLabel.vue';
import Modal from '@/components/Modal.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import EditorMateriales from '@/pages/FichaTecnicaMateriales/EditorMateriales.vue';
import { formatdinero } from '@/global.ts';
import { ClipboardDocumentListIcon, ExclamationTriangleIcon, SwatchIcon } from '@heroicons/vue/24/solid';
import { useForm } from '@inertiajs/vue3';
import { normalizarFragmentoCodigo } from '@/lib/utils';
import { computed, watchEffect } from 'vue';
import '@vuepic/vue-datepicker/dist/main.css'
import vSelect from "vue-select";
import "vue-select/dist/vue-select.css";

// --------------------------- ** -------------------------

const props = defineProps({
    show: Boolean,
    title: String,
    Materiala: Object,
    titulos: Object, //parametros de la clase principal
    losSelect: Object,
})
const emit = defineEmits(["close"]);

//very usefull
const justNames = props.titulos.map(names => {
    if (names['order'] !== 'noquiero'
        // &&
        // names['order'] !== 'noquiero1'
    )
        return names['order']
})
/** Qué es el material dentro de su familia. */
type RolMaterial = 'base' | 'hijo' | 'huerfano';
const form = useForm({
    ...Object.fromEntries(justNames.map(field => [field, ''])),
    // Deben existir desde el inicio: useForm solo envía las claves iniciales.
    last_purchase_price: '',
    last_purchase_date: new Date().toISOString().split('T')[0],
    materiales: [] as any[],
    // El rol (base | hijo | huerfano) manda sobre los campos del formulario.
    rol: 'huerfano' as RolMaterial,
    material_base_id: null as number | null,
    // Cada valor escrito acá crea un material hermano del que se está editando.
    nuevas_tallas: '',
    nuevos_colores: '',
});

const printForm: Array<{ idd: string; label: string; type: string }> = [];
props.titulos.forEach((names: any) => {
    if (names['order'] !== 'noquiero'
        // && names['order'] !== 'noquiero1'
    )
        printForm.push({
            idd: names['order'], label: names['label'], type: names['type']
        })
});

const camposNormales = printForm.filter((campo) => campo.type !== 'boolean');
const camposBooleanos = printForm.filter(
    (campo) => campo.type === 'boolean' && !['generico', 'maneja_color', 'maneja_otra_variante'].includes(campo.idd),
);

/** Las tres formas que puede tener un material; la elegida decide los campos. */
const ROLES: Array<{ value: RolMaterial; label: string; descripcion: string }> = [
    {
        value: 'base',
        label: 'Material base',
        descripcion: 'Representa a toda la familia. No lleva color, talla, existencia ni precio propios: la ficha técnica se anota aquí y su precio sale del promedio de sus hijos.',
    },
    {
        value: 'hijo',
        label: 'Hijo de una base',
        descripcion: 'Un material concreto de una base (TEL-ALG-001-05). Hereda el par y la unidad de su base; reasignarla le da un código nuevo.',
    },
    {
        value: 'huerfano',
        label: 'Huérfano',
        descripcion: 'No tiene base arriba: la ficha lo cita directo y se consume tal cual. Lleva un solo valor de talla y uno de color.',
    },
];

// Una base no tiene datos propios de variante ni de compra: se ocultan y no se
// envían, para no pisar lo que ya tiene guardado. Su precio lo aporta el
// promedio de sus similares.
const camposOmitidosPorBase = [
    'existencia_actual',
    'modelo_diseno_acabado',
    'color',
    'talla_tamano',
    'variante_atributo_sugerido',
    'proveedor_habitual',
];

/** Lo que el hijo toma de su base y por eso no se edita en su ficha. */
const camposDeLaBase = ['categoria', 'codigo_categoria', 'nombre_base', 'codigo_base', 'unidad_inventario'];

const esBase = computed(() => form.rol === 'base');
const esHijo = computed(() => form.rol === 'hijo');

const camposOcultos = computed(() => [
    ...(esBase.value ? camposOmitidosPorBase : []),
    ...(esHijo.value ? camposDeLaBase : []),
]);

const camposVisibles = computed(() => camposNormales.filter(
    (campo) => !camposOcultos.value.includes(campo.idd),
));

const camposBooleanosVisibles = computed(() => camposBooleanos.filter(
    (campo) => !(esBase.value && campo.idd === 'maneja_talla')
        && !(esHijo.value && ['maneja_talla', 'material_procesado'].includes(campo.idd)),
));

const esMaterialProcesado = computed(() => Number(form.material_procesado) === 1);

/** Solo los materiales marcados como base pueden adoptar hijos. */
const basesDisponibles = computed<any[]>(() =>
    ((props.losSelect?.materiales ?? []) as any[]).filter((m: any) => Boolean(m?.generico)),
);

const baseElegida = computed<any>(() => {
    const id = Number(form.material_base_id);

    return basesDisponibles.value.find((b: any) => Number(b.id) === id) ?? null;
});

/** La base que tiene arriba el material que se edita (base_id viene de losSelect). */
const baseActual = computed<number | null>(() => {
    const fila = ((props.losSelect?.materiales ?? []) as any[]).find(
        (m: any) => Number(m.id) === Number(props.Materiala?.id),
    );

    return Number(fila?.base_id ?? 0) || null;
});

/** Los hijos de la base elegida, incluido el material que se está editando. */
const hermanos = computed<any[]>(() => {
    const codigo = String(baseElegida.value?.codigo_material ?? '').toUpperCase();
    if (!codigo) return [];

    return ((props.losSelect?.materiales ?? []) as any[])
        .filter((m: any) => {
            const propio = String(m?.codigo_material ?? '').toUpperCase();

            return propio !== codigo && propio.startsWith(codigo + '-');
        })
        .sort((a: any, b: any) => String(a.codigo_material).localeCompare(String(b.codigo_material)));
});

const descripcionRol = computed(() =>
    ROLES.find((rol) => rol.value === form.rol)?.descripcion ?? '',
);

/** «S, M» en una celda de variante son dos valores, no uno (y «S, S» es uno solo). */
const trozos = (texto: any): string[] => {
    const vistos = new Set<string>();

    return String(texto ?? '')
        .split(/[,;]/)
        .map((trozo) => trozo.trim())
        .filter((trozo) => {
            const clave = trozo.toLowerCase();
            if (trozo === '' || vistos.has(clave)) return false;
            vistos.add(clave);

            return true;
        });
};

/** Cada valor escrito en «Agregar variantes», sin filtrar: el servidor crea uno. */
const valoresNuevos = computed<Array<{ tipo: string; nombre: string }>>(() => [
    ...trozos(form.nuevas_tallas).map((nombre) => ({ tipo: 'talla', nombre })),
    ...trozos(form.nuevos_colores).map((nombre) => ({ tipo: 'color', nombre })),
]);

/**
 * La talla y el color que ya gastan los demás hijos de la base. Si el material
 * abierto o los valores nuevos repiten uno, el listado queda con dos materiales
 * iguales de la misma familia.
 *
 * @returns clave «tipo|valor» → código del hermano que la tiene
 */
const variantasTomadas = computed<Map<string, string>>(() => {
    const tomadas = new Map<string, string>();

    hermanos.value
        .filter((hijo: any) => Number(hijo?.id) !== Number(props.Materiala?.id))
        .forEach((hijo: any) => {
            const codigo = String(hijo?.codigo_material ?? '');
            trozos(hijo?.talla_tamano).forEach((valor) => tomadas.set('talla|' + valor.toLowerCase(), codigo));
            trozos(hijo?.color).forEach((valor) => tomadas.set('color|' + valor.toLowerCase(), codigo));
        });

    return tomadas;
});

/** Lo que el servidor rechazaría al guardar, avisado antes de enviar. */
const conflictosVariantes = computed<string[]>(() => {
    if (esBase.value) return [];

    const conflictos: string[] = [];
    const nombreDe = (tipo: string) => (tipo === 'color' ? 'El color' : 'La talla');

    // La ficha lleva un solo valor de cada uno, sea hijo o huérfano: para otra
    // presentación existe «Agregar variantes», que crea su propio material.
    (['talla_tamano', 'color'] as const).forEach((campo) => {
        if (trozos(form[campo]).length > 1) {
            conflictos.push(`Un material lleva un solo valor en ${campo === 'color' ? 'Color' : 'Talla / tamaño'}.`);
        }
    });

    const anotadas = new Map<string, string>();
    const registrar = (tipo: string, nombre: string, origen: string) => {
        const clave = tipo + '|' + nombre.toLowerCase();
        const dueña = variantasTomadas.value.get(clave);

        if (dueña) {
            conflictos.push(`${nombreDe(tipo)} ${nombre} ya la tiene el material ${dueña}.`);
        } else if (anotadas.has(clave)) {
            conflictos.push(`${nombreDe(tipo)} ${nombre} está repetida: ya aparece en ${anotadas.get(clave)}.`);
        }

        anotadas.set(clave, origen);
    };

    if (esHijo.value) {
        trozos(form.talla_tamano).forEach((nombre) => registrar('talla', nombre, 'Talla / tamaño'));
        trozos(form.color).forEach((nombre) => registrar('color', nombre, 'Color'));
    }

    valoresNuevos.value.forEach((valor) => registrar(valor.tipo, valor.nombre, 'Agregar variantes'));

    return conflictos;
});

const mayorConsecutivo = (prefijo: string): number => {
    const codigos = [
        ...((props.losSelect?.materiales ?? []) as any[]).map((m: any) => m?.codigo_material),
        props.Materiala?.codigo_material,
    ];

    return codigos.reduce((max: number, codigo: any) => {
        const texto = String(codigo ?? '').toUpperCase();
        if (!texto.startsWith(prefijo + '-')) return max;

        const numero = Number(texto.split('-').pop());

        return Number.isFinite(numero) && numero > max ? numero : max;
    }, 0);
};

/** Número más alto del par: COM-CAP-055 y COM-CAP-055-34 valen 55. */
const mayorNumeroDelPar = (prefijo: string): number => {
    const codigos = [
        ...((props.losSelect?.materiales ?? []) as any[]).map((m: any) => m?.codigo_material),
        props.Materiala?.codigo_material,
    ];

    return codigos.reduce((max: number, codigo: any) => {
        const texto = String(codigo ?? '').toUpperCase();
        if (!texto.startsWith(prefijo + '-')) return max;

        const numero = Number(texto.split('-')[2] ?? '');

        return Number.isFinite(numero) && numero > max ? numero : max;
    }, 0);
};

/**
 * El prefijo que usan los materiales nuevos: el código de la base cuando el que
 * se edita es un hijo, o el par (2 primeros tramos) cuando está huérfano.
 */
const prefijoDeLosNuevos = computed(() => {
    if (esHijo.value) return String(baseElegida.value?.codigo_material ?? '').toUpperCase();

    const codigo = String(props.Materiala?.codigo_material ?? '').toUpperCase();

    return codigo.split('-').slice(0, 2).join('-');
});

/** Los códigos que van a tener los materiales nuevos, en el orden en que se crean. */
const codigosNuevos = computed<string[]>(() => {
    const prefijo = prefijoDeLosNuevos.value;
    if (esBase.value || !prefijo || !valoresNuevos.value.length) return [];

    if (esHijo.value) {
        let mayor = mayorConsecutivo(prefijo);

        // Si el material todavía no cuelga de esa base, el servidor le da primero
        // un código nuevo a él y los hermanos siguen desde ahí.
        const propio = String(props.Materiala?.codigo_material ?? '').toUpperCase();
        if (!propio.startsWith(prefijo + '-')) ++mayor;

        return valoresNuevos.value.map(() =>
            `${prefijo}-${String(++mayor).padStart(2, '0')}`);
    }

    let mayor = mayorNumeroDelPar(prefijo);

    return valoresNuevos.value.map(() =>
        `${prefijo}-${String(++mayor).padStart(3, '0')}`);
});

/**
 * En la edición la ficha es un solo valor: las listas son para «Agregar variantes»,
 * que crea un material por cada uno.
 */
const ayudaVariante = (campo: string): string => {
    const etiqueta = campo === 'color' ? 'color' : 'talla';

    return `Un solo valor, sin comas. Para otra ${etiqueta} usa «Agregar variantes» más abajo: crea su propio material.`;
};

const fichaBloqueada = computed(() => Boolean(props.Materiala?.ficha_bloqueada));
const fichaBloqueoDetalle = computed(() => props.Materiala?.ficha_bloqueo_detalle ?? '');

// Histórico de precios del material (tabla material_prices): cada compra o
// cambio de precio anota una fila. De ahí salen el «último precio» (la primera)
// y el «precio promedio» (la media de todas) que usan los costos.
const historialPrecios = computed<any[]>(() =>
    [...((props.Materiala?.prices ?? []) as any[])].sort(
        (a, b) =>
            String(b.fecha ?? '').localeCompare(String(a.fecha ?? '')) ||
            Number(b.id ?? 0) - Number(a.id ?? 0),
    ),
);

const precioPromedio = computed<number>(() => {
    const filas = historialPrecios.value;
    if (!filas.length) return 0;
    const suma = filas.reduce((total, fila) => total + (Number(fila.precio) || 0), 0);
    return Math.round((suma / filas.length) * 100) / 100;
});

const fechaCorta = (valor: any): string => String(valor ?? '').slice(0, 10);

const camposBloqueados = ['codigo_material', 'existencia_actual'];
const esCampoBloqueado = (campo: string) => camposBloqueados.includes(campo) && campo !== 'estado';

// Campos que la edición nunca envía: el precio y la fecha de última compra solo
// los define el formulario «Registrar compra». A una base se le suman los datos
// de variante y de existencia que no le aplican; a un hijo, los que hereda de su
// base y las líneas de su ficha, porque la receta se anota en la base.
const camposFueraDeEnvio = computed(() => Array.from(new Set([
    ...camposBloqueados,
    'last_purchase_price',
    'last_purchase_date',
    ...(esBase.value ? camposOmitidosPorBase : []),
    ...(esHijo.value ? [...camposDeLaBase, 'material_procesado', 'materiales'] : []),
])));

const estadoActivo = (valor: any): boolean => {
    const texto = String(valor ?? '').trim().toLowerCase();
    return texto === 'activo' || texto === '1' || texto === 'true';
};

const toggleEstado = () => {
    form.estado = estadoActivo(form.estado) ? 'Inactivo' : 'Activo';
};

const toggleActivo = (campo: string, valor: any): boolean => {
    if (campo === 'material_procesado') {
        return Number(valor) === 1;
    }
    return !!valor;
};

const toggleBoolean = (campo: string) => {
    if (campo === 'material_procesado') {
        form[campo] = Number(form[campo]) === 1 ? 0 : 1;
        return;
    }
    form[campo] = !form[campo];
};

const descripcionToggle = (campo: string, activo: boolean): string => {
    if (campo === 'material_procesado') {
        return activo ? 'Sí, este material es procesado' : 'No, este material no es procesado';
    }
    return activo ? 'Sí, este material lo maneja' : 'No aplica para este material';
};

/**
 * Cambiar el rol a mano reordena el formulario: una base pierde los datos que no
 * le aplican y un hijo no puede declararse procesado (la ficha vive en la base).
 * Se hace acá y no en un watch del rol, para que abrir el formulario con los
 * datos de la fila no borre lo que la fila ya tiene guardado.
 */
const elegirRol = (nuevo: RolMaterial) => {
    form.rol = nuevo;
    form.generico = nuevo === 'base';

    if (nuevo === 'base') {
        camposOmitidosPorBase.forEach((campo) => {
            form[campo] = campo === 'existencia_actual' ? 0 : '';
        });
        form.maneja_color = false;
        form.maneja_talla = false;
        form.maneja_otra_variante = false;
    }

    if (nuevo === 'hijo') {
        form.material_procesado = 0;
        form.maneja_talla = false;

        if (!form.material_base_id) {
            form.material_base_id = baseActual.value ?? basesDisponibles.value[0]?.id ?? null;
        }
    }
};

watchEffect(() => {
    if (props.show) {
        form.errors = {}
        props.titulos.forEach(names => {
            const valor = props.Materiala[names['order']];
            form[names['order']] = names['type'] === 'boolean'
                ? Number(valor) === 1 || valor === true
                : valor;
        });
        // El rol de la fila: base si está marcada, hijo si su código cuelga de una
        // base, huérfano si no tiene nada arriba.
        form.rol = form.generico ? 'base' : (baseActual.value ? 'hijo' : 'huerfano');
        form.material_base_id = baseActual.value;
        form.nuevas_tallas = '';
        form.nuevos_colores = '';
        // Se normaliza desde el prop, no desde form: leer form.estado aquí convertiría
        // el efecto en dependiente de sí mismo y desharía el toggle al pulsarlo.
        form.estado = estadoActivo(props.Materiala?.estado) ? 'Activo' : 'Inactivo';
        
        // Cargar precio de última compra
        if (props.Materiala && props.Materiala.last_purchase_price) {
            form.last_purchase_price = props.Materiala.last_purchase_price;
            form.last_purchase_date = props.Materiala.last_purchase_date || new Date().toISOString().split('T')[0];
        } else {
            form.last_purchase_price = '';
            form.last_purchase_date = new Date().toISOString().split('T')[0];
        }

        form.materiales = (props.Materiala?.ficha_materiales ?? []).map((fila: any) => ({
            material_componente_id: fila.material_componente_id,
            consumo_estandar: fila.consumo_estandar,
            unidad_medida: fila.unidad_medida ?? '',
            costo_asociado_teorico: fila.costo_asociado_teorico ?? '',
        }));
    }
})

const update = () => {
    if (conflictosVariantes.value.length) {
        return;
    }

    const respaldo: Record<string, any> = {};
    camposFueraDeEnvio.value.forEach(campo => {
        respaldo[campo] = form[campo];
        delete form[campo];
    });

    form.put(route('Material.update', props.Materiala?.id), {
        preserveScroll: true,
        onSuccess: () => {
            emit("close")
            form.reset()
        },
        onError: () => null,
        onFinish: () => {
            camposFueraDeEnvio.value.forEach(campo => {
                form[campo] = respaldo[campo];
            });
        },
    })
}
</script>

<template>
    <section class="space-y-6">
        <Modal :show="props.show" @close="emit('close')" :maxWidth="'xl6'">
            <form class="p-0" @submit.prevent="update">
                <!-- Encabezado -->
                <div class="px-6 pt-6 pb-4 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-indigo-50 via-white to-violet-50 dark:from-gray-800 dark:via-gray-800 dark:to-gray-800 rounded-t-lg">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-600 text-white">
                            <ClipboardDocumentListIcon class="w-5 h-5" />
                        </span>
                        {{ lang().t('app.label.edit') }} {{ props.title }}
                    </h2>
                    <p class="mt-1.5 text-sm text-gray-500 dark:text-gray-400 pl-10">
                        Modifica los datos del material. La existencia no es editable y el rol decide qué se puede cambiar.
                    </p>
                </div>

                <div class="px-6 py-5 space-y-7 max-h-[70vh] overflow-y-auto scrollbar-table">
                    <!-- Información general -->
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 flex items-center gap-1.5 mb-4">
                            <ClipboardDocumentListIcon class="w-4 h-4 text-gray-400 dark:text-gray-500" />
                            Información general
                        </h3>

                        <!-- El rol decide qué campos se editan después -->
                        <div class="mb-5">
                            <span class="block text-sm font-medium text-gray-800 dark:text-gray-200">
                                ¿Qué es este material?
                            </span>
                            <div class="mt-1.5 inline-flex w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900/40 p-1">
                                <button v-for="rol in ROLES" :key="rol.value" type="button"
                                    :aria-pressed="form.rol === rol.value"
                                    @click="elegirRol(rol.value)"
                                    class="flex-1 rounded-lg px-3 py-2 text-sm font-medium transition-colors"
                                    :class="form.rol === rol.value
                                        ? 'bg-indigo-600 text-white shadow'
                                        : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800'">
                                    {{ rol.label }}
                                </button>
                            </div>
                            <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">{{ descripcionRol }}</p>
                            <InputError class="mt-1" :message="form.errors.rol ?? form.errors.generico" />
                        </div>

                        <div v-if="esHijo" class="mb-5">
                            <InputLabel for="material_base_id" value="Material base" />
                            <v-select v-if="basesDisponibles.length" id="material_base_id"
                                class="mt-1 block w-full" :options="basesDisponibles" label="name"
                                :reduce="(item: any) => item.id" v-model="form.material_base_id"
                                placeholder="TEL-ALG-001 — Algodón" />
                            <div v-else
                                class="mt-1 flex items-start gap-2 rounded-lg border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/20 px-3 py-2">
                                <ExclamationTriangleIcon class="w-4 h-4 text-amber-500 shrink-0 mt-0.5" />
                                <p class="text-xs text-amber-700 dark:text-amber-300">
                                    Todavía no hay materiales base. Crea primero la base para poder asignarle hijos.
                                </p>
                            </div>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Si eliges otra base, el material recibe un código nuevo de esa familia
                                <span v-if="baseElegida" class="font-mono">{{ baseElegida.codigo_material }}-XX</span>.
                                Las OPs lo siguen apuntando a él, no a su código.
                            </p>
                            <p v-if="hermanos.length" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Hijos de {{ baseElegida?.codigo_material }}:
                                <span class="font-mono">{{ hermanos.map((h: any) => h.codigo_material).join(', ') }}</span>
                            </p>
                            <InputError class="mt-1" :message="form.errors.material_base_id" />
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                            <template v-for="(atributosform, indice) in camposVisibles" :key="indice">

                                <!-- estado: toggle Activo/Inactivo -->
                                <div v-if="atributosform.idd === 'estado'" class="md:col-span-2">
                                    <div role="switch" tabindex="0"
                                        :aria-checked="estadoActivo(form.estado)"
                                        @click="toggleEstado"
                                        @keydown.space.prevent="toggleEstado"
                                        class="flex items-center justify-between gap-4 px-4 py-3 rounded-xl border cursor-pointer transition-colors"
                                        :class="estadoActivo(form.estado)
                                            ? 'border-emerald-300 dark:border-emerald-700 bg-emerald-50/70 dark:bg-emerald-900/20'
                                            : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900/40 hover:border-gray-300 dark:hover:border-gray-600'">
                                        <div>
                                            <span class="block text-sm font-medium text-gray-800 dark:text-gray-200">
                                                {{ lang().t('app.label')[atributosform.label] || atributosform.label }}
                                            </span>
                                            <span class="block text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                                {{ estadoActivo(form.estado) ? 'Activo' : 'Inactivo' }}
                                            </span>
                                        </div>
                                        <span class="relative inline-flex h-7 w-12 shrink-0 items-center rounded-full transition-colors duration-200"
                                            :class="estadoActivo(form.estado) ? 'bg-emerald-600' : 'bg-gray-300 dark:bg-gray-600'">
                                            <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform duration-200"
                                                :class="estadoActivo(form.estado) ? 'translate-x-6' : 'translate-x-1'" />
                                        </span>
                                    </div>
                                    <InputError class="mt-2" :message="form.errors.estado" />
                                </div>

                                <!-- código material: lo arma el servidor con el par categoría/base -->
                                <div v-else-if="atributosform.idd === 'codigo_material'">
                                    <InputLabel :for="atributosform.label"
                                        :value="lang().t('app.label')[atributosform.label] || atributosform.label" />
                                    <TextInput :id="atributosform.idd" class="mt-1 block w-full bg-gray-100 dark:bg-gray-800/60 cursor-not-allowed opacity-70"
                                        v-model="form[atributosform.idd]" disabled />
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        <template v-if="esHijo">
                                            Es el código de la familia: el prefijo de su base más un consecutivo. Si le
                                            cambias la base, el servidor le da el siguiente código libre de esa familia.
                                        </template>
                                        <template v-else-if="esBase">
                                            Una base se queda con el código del par (TEX-LFI-004) sin consecutivo: de ahí
                                            cuelgan los códigos de sus hijos.
                                        </template>
                                        <template v-else>
                                            Se formó con el código de la categoría y el código base (TEX-LFI-004). La forma
                                            del código no define el rol: eso lo decide la selección de arriba. Si lo
                                            cambias a hijo, pasa al código de la familia de la base que elijas.
                                        </template>
                                    </p>
                                    <InputError class="mt-2" :message="form.errors[atributosform.idd]" />
                                </div>

                                <!-- par de códigos que forma el prefijo del material -->
                                <div v-else-if="['codigo_categoria', 'codigo_base'].includes(atributosform.idd)">
                                    <InputLabel :for="atributosform.label"
                                        :value="lang().t('app.label')[atributosform.label] || atributosform.label" />
                                    <TextInput :id="atributosform.idd" type="text"
                                        class="mt-1 block w-full uppercase tracking-widest"
                                        v-model="form[atributosform.idd]" :maxlength="3"
                                        :placeholder="atributosform.idd === 'codigo_categoria' ? 'TEX' : 'LFI'"
                                        @blur="form[atributosform.idd] = normalizarFragmentoCodigo(form[atributosform.idd])"
                                        :error="form.errors[atributosform.idd]" />
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Hasta 3 letras. Determina el prefijo del código del material.
                                    </p>
                                    <InputError class="mt-2" :message="form.errors[atributosform.idd]" />
                                </div>

                                <!-- proveedor habitual: FK hacia Proveedor -->
                                <div v-else-if="atributosform.idd === 'proveedor_habitual'">
                                    <InputLabel :for="atributosform.label"
                                        :value="lang().t('app.label')[atributosform.label] || atributosform.label" />
                                    <v-select :id="atributosform.idd"
                                        class="mt-1 block w-full"
                                        :options="props.losSelect?.proveedor_habitual || []"
                                        label="nombre"
                                        :reduce="(item: any) => item.id"
                                        v-model="form[atributosform.idd]"
                                        :placeholder="atributosform.label"
                                        :disabled="esCampoBloqueado(atributosform.idd)" />
                                    <InputError class="mt-2" :message="form.errors[atributosform.idd]" />
                                </div>

                                <div v-else-if="atributosform.type === 'foreign'" id="SelectVue">
                                    <label name="labelSelectVue"> {{ lang().t('app.label')[atributosform.label] || atributosform.label }} </label>
                                    <v-select :options="props.losSelect[0]" v-model="form[atributosform.idd]"
                                        :reduce="element => element.value" label="name"
                                        :disabled="esCampoBloqueado(atributosform.idd)"></v-select>
                                    <InputError class="mt-2" :message="form.errors[atributosform.idd]" />
                                </div>

                                <!-- tiempo -->
                                <div v-else-if="atributosform.type === 'time'" id="SelectVue">
                                    <InputLabel :for="atributosform.label"
                                        :value="lang().t('app.label')[atributosform.label]" />
                                    <TextInput :id="atributosform.idd" :type="atributosform.type" class="mt-1 block w-full"
                                        v-model="form[atributosform.idd]" required :placeholder="atributosform.label"
                                        :error="form.errors[atributosform.idd]" step="3600"
                                        :disabled="esCampoBloqueado(atributosform.idd)" />
                                    <InputError class="mt-2" :message="form.errors[atributosform.idd]" />
                                </div>

                                <!-- variantes: hijo = un material por valor, huérfano = un solo valor -->
                                <div v-else-if="['color', 'talla_tamano'].includes(atributosform.idd)">
                                    <InputLabel :for="atributosform.label"
                                        :value="lang().t('app.label')[atributosform.label] || atributosform.label" />
                                    <TextInput :id="atributosform.idd" :type="atributosform.type" class="mt-1 block w-full"
                                        v-model="form[atributosform.idd]" placeholder="S"
                                        :error="form.errors[atributosform.idd]" />
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ ayudaVariante(atributosform.idd) }}
                                    </p>
                                    <InputError class="mt-2" :message="form.errors[atributosform.idd]" />
                                </div>

                                <!-- normal -->
                                <div v-else>
                                    <InputLabel :for="atributosform.label"
                                        :value="lang().t('app.label')[atributosform.label]" />
                                    <TextInput :id="atributosform.idd" :type="atributosform.type" class="mt-1 block w-full"
                                        v-model="form[atributosform.idd]" :required="!['observaciones', 'modelo_diseno_acabado', 'color', 'talla_tamano', 'variante_atributo_sugerido'].includes(atributosform.idd)" :placeholder="atributosform.label"
                                        :error="form.errors[atributosform.idd]"
                                        :disabled="esCampoBloqueado(atributosform.idd)"
                                        :class="esCampoBloqueado(atributosform.idd) ? 'bg-gray-100 dark:bg-gray-800/60 cursor-not-allowed opacity-70' : ''" />
                                    <InputError class="mt-2" :message="form.errors[atributosform.idd]" />
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Atributos del material (una sola columna, toggles grandes) -->
                    <div v-if="camposBooleanosVisibles.length">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 flex items-center gap-1.5 mb-1">
                            <SwatchIcon class="w-4 h-4 text-gray-400 dark:text-gray-500" />
                            Atributos del material
                        </h3>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mb-3 pl-6">
                            Activa los atributos que aplican a este material.
                        </p>
                        <div class="space-y-2 max-w-2xl">
                            <div v-for="atributosform in camposBooleanosVisibles" :key="atributosform.idd"
                                role="switch" tabindex="0"
                                :aria-checked="toggleActivo(atributosform.idd, form[atributosform.idd])"
                                @click="toggleBoolean(atributosform.idd)"
                                @keydown.space.prevent="toggleBoolean(atributosform.idd)"
                                class="flex items-center justify-between gap-4 px-4 py-3 rounded-xl border cursor-pointer transition-colors"
                                :class="toggleActivo(atributosform.idd, form[atributosform.idd])
                                    ? 'border-indigo-300 dark:border-indigo-700 bg-indigo-50/70 dark:bg-indigo-900/20'
                                    : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900/40 hover:border-gray-300 dark:hover:border-gray-600'">
                                <div>
                                    <span class="block text-sm font-medium text-gray-800 dark:text-gray-200">
                                        {{ lang().t('app.label')[atributosform.label] || atributosform.label }}
                                    </span>
                                    <span class="block text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                        {{ descripcionToggle(atributosform.idd, toggleActivo(atributosform.idd, form[atributosform.idd])) }}
                                    </span>
                                    <InputError class="mt-1" :message="form.errors[atributosform.idd]" />
                                </div>
                                <span class="relative inline-flex h-7 w-12 shrink-0 items-center rounded-full transition-colors duration-200"
                                    :class="toggleActivo(atributosform.idd, form[atributosform.idd]) ? 'bg-indigo-600' : 'bg-gray-300 dark:bg-gray-600'">
                                    <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform duration-200"
                                        :class="toggleActivo(atributosform.idd, form[atributosform.idd]) ? 'translate-x-6' : 'translate-x-1'" />
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Ficha técnica del material procesado (el hijo hereda la de su base) -->
                    <div v-if="esMaterialProcesado && !esHijo">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 flex items-center gap-1.5 mb-1">
                            <SwatchIcon class="w-4 h-4 text-gray-400 dark:text-gray-500" />
                            Ficha de Producto
                        </h3>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mb-3 pl-6">
                            Materiales y consumos necesarios para fabricar una unidad de este material procesado.
                        </p>
                        <div v-if="fichaBloqueada"
                            class="mb-3 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-200">
                            La ficha no se puede modificar porque el material está en la OP {{ fichaBloqueoDetalle }}.
                        </div>
                        <EditorMateriales v-model="form.materiales" :opciones="props.losSelect"
                            :errors="form.errors" :disabled="fichaBloqueada" />
                        <InputError class="mt-2" :message="form.errors.materiales" />
                        <InputError class="mt-2" :message="form.errors.materiales_procesados" />
                    </div>

                    <!-- Tallas y colores nuevos: cada uno crea un material hermano -->
                    <div v-if="!esBase">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 flex items-center gap-1.5 mb-1">
                            <SwatchIcon class="w-4 h-4 text-gray-400 dark:text-gray-500" />
                            Agregar variantes
                        </h3>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mb-3 pl-6">
                            Cada talla y cada color que escribas acá crea un material nuevo, separados por coma.
                            {{ esHijo
                                ? 'Salen como hijos de ' + (baseElegida?.codigo_material ?? 'la base') + '.'
                                : 'Salen como materiales del mismo par, sin base arriba.' }}
                        </p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                            <div>
                                <InputLabel for="nuevas_tallas" value="Tallas nuevas" />
                                <TextInput id="nuevas_tallas" type="text" class="mt-1 block w-full"
                                    v-model="form.nuevas_tallas" placeholder="XL, XXL"
                                    :error="form.errors.nuevas_tallas" />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Hoy: {{ form.talla_tamano || 'sin talla' }}
                                </p>
                            </div>
                            <div>
                                <InputLabel for="nuevos_colores" value="Colores nuevos" />
                                <TextInput id="nuevos_colores" type="text" class="mt-1 block w-full"
                                    v-model="form.nuevos_colores" placeholder="Verde, Negro"
                                    :error="form.errors.nuevos_colores" />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Hoy: {{ form.color || 'sin color' }}
                                </p>
                            </div>
                        </div>
                        <p v-if="codigosNuevos.length" class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            Se crearán {{ codigosNuevos.length }}
                            {{ codigosNuevos.length === 1 ? 'material' : 'materiales' }}:
                            <span class="font-mono">{{ codigosNuevos.join(', ') }}</span>
                        </p>
                        <InputError class="mt-2" :message="form.errors.nuevas_tallas" />
                        <InputError class="mt-2" :message="form.errors.nuevos_colores" />
                    </div>

                    <!-- La variante tiene que quedar única dentro de la familia -->
                    <div v-if="conflictosVariantes.length"
                        class="flex items-start gap-2 rounded-lg border border-red-300 dark:border-red-700 bg-red-50 dark:bg-red-900/20 px-3 py-2">
                        <ExclamationTriangleIcon class="w-4 h-4 text-red-500 shrink-0 mt-0.5" />
                        <div>
                            <p v-for="conflicto in conflictosVariantes" :key="conflicto"
                                class="text-xs text-red-700 dark:text-red-300">
                                {{ conflicto }}
                            </p>
                            <p class="mt-0.5 text-xs text-red-600/80 dark:text-red-400/80">
                                Cada talla y cada color de la familia vive en un solo material.
                            </p>
                        </div>
                    </div>

                    <!-- Precio de última compra -->
                    <div>
                        <template v-if="!esBase">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 flex items-center gap-1.5 mb-4">
                            <span class="inline-flex h-4 w-4 items-center justify-center rounded bg-indigo-600 text-white text-[10px]">
                                $
                            </span>
                            Precio de última compra
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4 pl-6">
                            Solo se puede modificar desde el formulario «Registrar compra». Aquí se muestra como referencia.
                        </p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                            <div>
                                <InputLabel for="last_purchase_price" value="Precio" />
                                <TextInput id="last_purchase_price"
                                    type="number"
                                    class="mt-1 block w-full bg-gray-100 dark:bg-gray-800/60 cursor-not-allowed opacity-70"
                                    v-model="form.last_purchase_price"
                                    placeholder="0.00"
                                    :error="form.errors.last_purchase_price"
                                    step="0.01"
                                    min="0"
                                    disabled />
                                <InputError class="mt-2" :message="form.errors.last_purchase_price" />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Se actualiza al registrar una compra
                                </p>
                            </div>
                            <div>
                                <InputLabel for="last_purchase_date" value="Fecha" />
                                <TextInput id="last_purchase_date"
                                    type="date"
                                    class="mt-1 block w-full bg-gray-100 dark:bg-gray-800/60 cursor-not-allowed opacity-70"
                                    v-model="form.last_purchase_date"
                                    :error="form.errors.last_purchase_date"
                                    disabled />
                                <InputError class="mt-2" :message="form.errors.last_purchase_date" />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    La define cada registro de compra
                                </p>
                            </div>
                        </div>
                        </template>
                        <div v-else
                            class="rounded-xl border border-indigo-200 dark:border-indigo-800 bg-indigo-50/60 dark:bg-indigo-900/20 px-4 py-3 mb-4">
                            <p class="text-sm font-medium text-gray-800 dark:text-gray-200">
                                Una base no registra precio ni fecha de compra propios
                            </p>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                Su precio es el promedio de los precios de sus similares de la misma categoría y nombre
                                base; lo actualiza la acción de materiales genéricos.
                            </p>
                        </div>

                        <!-- Histórico de precios (material_prices) -->
                        <div class="mt-5">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Histórico de precios
                                    <span class="ml-1 font-medium normal-case tracking-normal text-gray-400 dark:text-gray-500">
                                        {{ historialPrecios.length }}
                                        {{ historialPrecios.length === 1 ? 'registro' : 'registros' }}
                                    </span>
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Promedio:
                                    <span class="font-semibold tabular-nums text-gray-700 dark:text-gray-200">
                                        {{ formatdinero(precioPromedio) }}
                                    </span>
                                </p>
                            </div>
                            <div
                                class="mt-2 max-h-44 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700">
                                <table class="w-full text-xs">
                                    <thead
                                        class="sticky top-0 bg-gray-50 text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                        <tr>
                                            <th class="px-3 py-1.5 text-left font-medium">Fecha</th>
                                            <th class="px-3 py-1.5 text-right font-medium">Precio</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                        <tr v-for="(fila, indice) in historialPrecios" :key="fila.id ?? indice">
                                            <td
                                                class="px-3 py-1.5 tabular-nums text-gray-600 dark:text-gray-300">
                                                {{ fechaCorta(fila.fecha) }}
                                            </td>
                                            <td
                                                class="px-3 py-1.5 text-right font-semibold tabular-nums text-gray-800 dark:text-gray-100">
                                                {{ formatdinero(fila.precio) }}
                                                <span v-if="indice === 0"
                                                    class="ml-1 rounded-full bg-sky-100 px-1.5 py-0.5 text-[10px] font-medium text-sky-800 dark:bg-sky-900/40 dark:text-sky-200">
                                                    último
                                                </span>
                                            </td>
                                        </tr>
                                        <tr v-if="!historialPrecios.length">
                                            <td colspan="2"
                                                class="px-3 py-3 text-center text-gray-400 dark:text-gray-500">
                                                Este material todavía no tiene precios registrados.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                La primera fila es el «último precio» y la media de todas es el «precio promedio»: el
                                parámetro «Costos de materiales en las OPs» decide cuál de los dos se usa.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Pie -->
                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-3 bg-gray-50 dark:bg-gray-800/50 rounded-b-lg">
                    <SecondaryButton :disabled="form.processing" @click="emit('close')"> {{ lang().t('app.button.close')
                        }}
                    </SecondaryButton>
                    <PrimaryButton class="ml-3" :class="{ 'opacity-25': form.processing || conflictosVariantes.length }"
                        :disabled="form.processing || conflictosVariantes.length">
                        {{ lang().t('app.button.save') }} {{ form.processing ? '...' : '' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    </section>
</template>

<style>
[name="labelSelectVue"],
.muted {
    color: #1b416699;
}

[name="labelSelectVue"] {
    /* font-size: 22px; */
    font-weight: 600;
}
</style>
