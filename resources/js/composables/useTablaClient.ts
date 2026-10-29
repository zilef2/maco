import { computed, ref, toValue, watch, type MaybeRefOrGetter } from 'vue';

export type ColumnaTabla = {
    key: string;
    label: string;
    sortable?: boolean;
    align?: 'left' | 'right' | 'center';
    headerClass?: string;
    /**
     * Valor usado para ordenar y buscar cuando la columna es derivada
     * (p. ej. el desglose de tallas o el costo actual de un material).
     */
    valor?: (fila: any) => any;
};

export const normalizarTexto = (valor: any): string =>
    String(valor ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/\s+/g, ' ')
        .trim();

const collator = new Intl.Collator('es', { numeric: true, sensitivity: 'base' });

/**
 * Tabla 100% en cliente: los datos llegan una sola vez y aquí se buscan,
 * ordenan y paginan sin volver al backend.
 */
export function useTablaClient(
    filas: MaybeRefOrGetter<any[]>,
    opciones: {
        columnas: ColumnaTabla[];
        /** Claves que lee el filtro de texto; se aceptan claves de columna derivadas. */
        busquedaPor?: string[];
        porPagina?: number;
        orden?: { key: string; dir: 'asc' | 'desc' };
    },
) {
    const { columnas, busquedaPor = [], porPagina: porPaginaInicial = 10 } = opciones;
    const porClave = new Map(columnas.map((columna) => [columna.key, columna]));

    const valorDe = (fila: any, clave: string): any => {
        const columna = porClave.get(clave);
        return columna?.valor ? columna.valor(fila) : fila?.[clave];
    };

    const primeraOrdenable = columnas.find((columna) => columna.sortable)?.key ?? columnas[0]?.key ?? '';
    const busqueda = ref('');
    const sortKey = ref(opciones.orden?.key || primeraOrdenable);
    const sortDir = ref<'asc' | 'desc'>(opciones.orden?.dir ?? 'asc');
    const porPagina = ref<number>(porPaginaInicial);
    const pagina = ref(1);

    const filtradas = computed(() => {
        const texto = normalizarTexto(busqueda.value);
        const todas = toValue(filas) ?? [];
        if (texto === '') return todas;

        return todas.filter((fila: any) =>
            normalizarTexto(busquedaPor.map((clave) => valorDe(fila, clave)).join(' ')).includes(texto),
        );
    });

    const ordenadas = computed(() => {
        const direccion = sortDir.value === 'asc' ? 1 : -1;
        const clave = sortKey.value;

        return [...filtradas.value].sort((a: any, b: any) => {
            const va = valorDe(a, clave);
            const vb = valorDe(b, clave);
            const nuloA = va === null || va === undefined || va === '';
            const nuloB = vb === null || vb === undefined || vb === '';

            if (nuloA && nuloB) return 0;
            if (nuloA) return 1;
            if (nuloB) return -1;

            const na = Number(va);
            const nb = Number(vb);
            if (Number.isFinite(na) && Number.isFinite(nb)) return (na - nb) * direccion;

            return collator.compare(String(va), String(vb)) * direccion;
        });
    });

    const total = computed(() => ordenadas.value.length);
    const paginas = computed(() => Math.max(1, Math.ceil(total.value / porPagina.value)));
    const paginaActual = computed(() => Math.min(pagina.value, paginas.value));
    const desde = computed(() => (total.value === 0 ? 0 : (paginaActual.value - 1) * porPagina.value + 1));
    const hasta = computed(() => Math.min(paginaActual.value * porPagina.value, total.value));
    const visibles = computed(() => {
        const inicio = (paginaActual.value - 1) * porPagina.value;
        return ordenadas.value.slice(inicio, inicio + porPagina.value);
    });

    const hayBusqueda = computed(() => busqueda.value.trim() !== '');
    const totalRegistros = computed(() => (toValue(filas) ?? []).length);

    watch([busqueda, porPagina], () => {
        pagina.value = 1;
    });

    watch(paginas, (n) => {
        if (pagina.value > n) pagina.value = n;
    });

    const cambiarPagina = (nueva: number) => {
        pagina.value = Math.min(Math.max(1, nueva), paginas.value);
    };

    const ordenarPor = (clave: string) => {
        if (sortKey.value === clave) {
            sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
        } else {
            sortKey.value = clave;
            sortDir.value = 'asc';
        }
        pagina.value = 1;
    };

    const indicador = (clave: string) => (sortKey.value === clave ? sortDir.value : null);

    const ariaSort = (clave: string): 'ascending' | 'descending' | 'none' =>
        sortKey.value !== clave ? 'none' : sortDir.value === 'asc' ? 'ascending' : 'descending';

    const limpiar = () => {
        busqueda.value = '';
    };

    return {
        busqueda,
        sortKey,
        sortDir,
        porPagina,
        pagina,
        valorDe,
        filtradas,
        ordenadas,
        total,
        paginas,
        paginaActual,
        desde,
        hasta,
        visibles,
        hayBusqueda,
        totalRegistros,
        cambiarPagina,
        ordenarPor,
        indicador,
        ariaSort,
        limpiar,
    };
}
