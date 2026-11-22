/**
 * Paso de mensajes entre la subida del Excel y la recarga de la página.
 *
 * La tarjeta recarga el HTML cuando termina la validación, y el flash que
 * envía el backend ya se consumió en la visita anterior de Inertia, así que el
 * resultado viaja aquí para que el usuario lo siga viendo después del F5.
 */
const CLAVE = 'importarExcel.recarga';

export type ResultadoRecarga = {
    mensajes: Record<string, string>;
    avisos: Record<string, string[]>;
};

export function guardarResultado(resultado: ResultadoRecarga): void {
    if (Object.keys(resultado.mensajes).length === 0 && Object.keys(resultado.avisos).length === 0) {
        return;
    }

    try {
        sessionStorage.setItem(CLAVE, JSON.stringify(resultado));
    } catch {
        // Sin almacenamiento (modo privado o cuota llena) la recarga igual
        // muestra lo que haya llegado por sesión.
    }
}

export function leerResultado(): ResultadoRecarga | null {
    try {
        const crudo = sessionStorage.getItem(CLAVE);
        if (!crudo) return null;

        sessionStorage.removeItem(CLAVE);
        const leido = JSON.parse(crudo) as Partial<ResultadoRecarga>;

        return {
            mensajes: leido.mensajes ?? {},
            avisos: leido.avisos ?? {},
        };
    } catch {
        return null;
    }
}
