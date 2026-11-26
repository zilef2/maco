import { reactive } from 'vue';

/**
 * Material concreto elegido en la OP para cada línea base de la ficha.
 *
 * La ficha declara la base de la familia (COM-CAP-055) porque allí solo importa
 * el consumo de la familia; el color o la talla que se gastan se eligen en la OP
 * y tienen que ser un hijo de esa misma base (COM-CAP-055-34). Todo lo que se
 * muestra o se costea en el formulario sale del concreto. Una línea sin base
 * arriba (un huérfano) no se pregunta: se usa tal como está.
 */
export type LineaFicha = {
    material_id: number;
    nombre: string;
    generico?: boolean;
    familia?: string;
    unidad_medida?: string | null;
    consumo_estandar?: number | string;
    [clave: string]: any;
};

export type ObjetivoGenerico = {
    id: number;
    nombre: string;
    lineas: LineaFicha[];
};

export function useMaterialesConcretos(opcionesPorFamilia: () => Record<string, any[]>) {
    // elecciones[objetivo][material genérica de la ficha] = material concreto.
    const elecciones = reactive<Record<number, Record<number, number | ''>>>({});

    const opcionesDe = (linea: LineaFicha): any[] => {
        if (!linea?.generico || !linea.familia) return [];

        return opcionesPorFamilia()[linea.familia] ?? [];
    };

    const concretoDe = (objetivoId: number, linea: LineaFicha): any | null => {
        const elegido = elecciones[objetivoId]?.[linea.material_id];
        if (!elegido) return null;

        return opcionesDe(linea).find((opcion) => Number(opcion.id) === Number(elegido)) ?? null;
    };

    /**
     * Material del que se leen existencia, proyección y precio: el concreto
     * elegido o, si aún falta la elección, la propia línea de la ficha.
     */
    const materialDeLinea = (objetivoId: number, linea: LineaFicha): LineaFicha => {
        const concreto = concretoDe(objetivoId, linea);
        if (!concreto) return linea;

        return {
            ...linea,
            material_id: Number(concreto.id),
            nombre: concreto.name,
            unidad_medida: linea.unidad_medida || concreto.unidad_inventario,
            precio_unitario: concreto.precio_unitario,
            fisico: concreto.fisico,
            pendiente: concreto.pendiente,
            comprometido: concreto.comprometido,
            proyectado: concreto.proyectado,
            productores: concreto.productores ?? [],
            consumidores: concreto.consumidores ?? [],
        };
    };

    const elegir = (objetivoId: number, genericoId: number, valor: unknown) => {
        if (!elecciones[objetivoId]) elecciones[objetivoId] = {};

        const numero = Number(valor) || 0;
        elecciones[objetivoId][genericoId] = numero > 0 ? numero : '';
    };

    /**
     * Cuando la familia solo tiene un concreto no hay nada que decidir: se toma
     * él para que el resumen de materiales salga bien desde el principio.
     */
    const asegurarUnicos = (objetivos: ObjetivoGenerico[]) => {
        for (const objetivo of objetivos) {
            for (const linea of objetivo.lineas) {
                if (elecciones[objetivo.id]?.[linea.material_id]) continue;

                const opciones = opcionesDe(linea);
                if (opciones.length === 1) elegir(objetivo.id, linea.material_id, opciones[0].id);
            }
        }
    };

    /** Elecciones de los objetivos que siguen en la orden, en el formato del request. */
    const payload = (objetivoIds: number[]): Record<number, Record<number, number>> => {
        const salida: Record<number, Record<number, number>> = {};

        for (const id of objetivoIds) {
            const porObjetivo = elecciones[id];
            if (!porObjetivo) continue;

            const plano: Record<number, number> = {};
            for (const [genericoId, concretoId] of Object.entries(porObjetivo)) {
                if (Number(concretoId) > 0) plano[Number(genericoId)] = Number(concretoId);
            }

            if (Object.keys(plano).length) salida[id] = plano;
        }

        return salida;
    };

    /** Restaurar las elecciones ya guardadas de una OP o de un borrador. */
    const cargar = (mapa?: Record<string | number, Record<string | number, number | string> | null> | null) => {
        for (const clave of Object.keys(elecciones)) delete elecciones[Number(clave)];
        if (!mapa) return;

        for (const [objetivoId, porGenerico] of Object.entries(mapa)) {
            for (const [genericoId, concretoId] of Object.entries(porGenerico ?? {})) {
                elegir(Number(objetivoId), Number(genericoId), concretoId);
            }
        }
    };

    /** Al quitar un producto o material de la orden se descartan sus elecciones. */
    const olvidar = (objetivoId: number) => {
        delete elecciones[objetivoId];
    };

    return { elecciones, opcionesDe, concretoDe, materialDeLinea, elegir, asegurarUnicos, payload, cargar, olvidar };
}
