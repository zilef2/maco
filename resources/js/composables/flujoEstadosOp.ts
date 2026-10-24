/**
 * Flujo de producción de una Orden de Producción (OP).
 *
 * Fuente única del orden de los estados: lo usan el modal de cambio de estado
 * (para etiquetar avance/retroceso) y el formulario de edición (para saber qué
 * campos siguen abiertos). Las transiciones permitidas las decide el backend
 * con `estado_operacions.puede_pasar_a`; aquí solo vive el orden del flujo.
 */
export const ORDEN_FLUJO = [
    'Borrador',
    'Preparación',
    'Esperando materiales',
    'Procesos externos',
    'Lista para taller',
    'En taller',
    'Recibiendo',
    'Pendiente',
    'Cerrada',
] as const;

export type NombreEstadoFlujo = (typeof ORDEN_FLUJO)[number];

/** Estado a partir del cual la OP ya está en bodega/taller y el plan se congela. */
export const ESTADO_CONGELA_PLAN: NombreEstadoFlujo = 'En taller';

/** Posición del estado en el flujo; los estados desconocidos van al final. */
export const rangoFlujo = (nombre: unknown): number => {
    const posicion = ORDEN_FLUJO.indexOf(String(nombre ?? '') as NombreEstadoFlujo);
    return posicion === -1 ? ORDEN_FLUJO.length : posicion;
};

/**
 * El plan (fecha prevista de envío, taller/empresa, productos y tallas) solo se
 * puede mover mientras la OP no haya entrado a «En taller». Un estado que no
 * está en el flujo no bloquea nada.
 */
export const sePuedeEditarPlan = (...nombres: unknown[]): boolean =>
    nombres.every((nombre) => {
        const texto = String(nombre ?? '');
        if (!texto) return true;
        return ORDEN_FLUJO.indexOf(texto as NombreEstadoFlujo) < rangoFlujo(ESTADO_CONGELA_PLAN);
    });

/** Etiqueta «Avance» / «Retroceso» de una transición respecto al estado actual. */
export const esAvance = (desde: unknown, hacia: unknown): boolean => rangoFlujo(hacia) > rangoFlujo(desde);
