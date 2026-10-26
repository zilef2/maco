/**
 * Costo de la recepción de calidad de una OP.
 *
 * Regla de negocio (el costo unitario de cada producto lo aporta el backend
 * desde app/Services/CostoRecepcionOpService.php, con los precios que la OP
 * tiene guardados por material):
 * - Un par aprobado ya consumió todos los materiales de su ficha: vale su costo
 *   unitario completo.
 * - Un par no recuperable es pérdida total: vale igual que un aprobado.
 * - Un par en reparación todavía no se suma: puede regresar convertido en
 *   aprobado y recién entonces se cuesta.
 */

export interface FilaRecepcion {
    producto_id?: number | string | null;
    cantidad_aprobada?: number | string | null;
    cantidad_en_reparacion?: number | string | null;
    cantidad_no_recuperable?: number | string | null;
}

export type CostosUnitarios = Record<string, number> | null | undefined;

export interface ResumenRecepcion {
    aprobadas: number;
    enReparacion: number;
    noRecuperables: number;
    costoAprobadas: number;
    /** Siempre 0: lo que está en reparación aún no se suma al costo. */
    costoEnReparacion: number;
    costoNoRecuperables: number;
    costoOp: number;
}

const enteros = (valor: unknown): number => {
    const numero = Math.trunc(Number(valor));
    return Number.isFinite(numero) && numero > 0 ? numero : 0;
};

const redondear = (valor: number): number => Math.round(valor * 100) / 100;

/** Costo unitario del producto de la fila; 0 si la OP no lo conoce. */
export const costoUnitarioFila = (fila: FilaRecepcion, unitarios: CostosUnitarios): number => {
    if (!unitarios || fila.producto_id === null || fila.producto_id === undefined) return 0;
    return Number(unitarios[String(fila.producto_id)]) || 0;
};

/**
 * Suma las tres columnas de la recepción y costea la OP: aprobadas y no
 * recuperables valen sus materiales, las en reparación no se suman.
 */
export const resumenRecepcionCosto = (
    filas: FilaRecepcion[],
    unitarios: CostosUnitarios
): ResumenRecepcion => {
    const total = filas.reduce(
        (acum, fila) => {
            const unitario = costoUnitarioFila(fila, unitarios);
            const aprobadas = enteros(fila.cantidad_aprobada);
            const noRecuperables = enteros(fila.cantidad_no_recuperable);

            acum.aprobadas += aprobadas;
            acum.enReparacion += enteros(fila.cantidad_en_reparacion);
            acum.noRecuperables += noRecuperables;
            acum.costoAprobadas += aprobadas * unitario;
            acum.costoNoRecuperables += noRecuperables * unitario;
            return acum;
        },
        {
            aprobadas: 0,
            enReparacion: 0,
            noRecuperables: 0,
            costoAprobadas: 0,
            costoNoRecuperables: 0,
        }
    );

    return {
        ...total,
        costoEnReparacion: 0,
        costoOp: redondear(total.costoAprobadas + total.costoNoRecuperables),
    };
};
