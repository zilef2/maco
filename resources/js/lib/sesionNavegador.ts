type Motivo = 'cierre' | 'oculta' | 'visible';

const RUTA_ENVIO = '/sesion/estado';

/** Una solapa que se escondió y volvió enseguida no dice nada: se ignora. */
const SEGUNDOS_DE_AUSENCIA = 60;

let desdeQueSeVe = Date.now();

let avisoPendiente = false;

const enviar = (motivo: Motivo): void => {
    const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
    if (!token) return;

    // FormData en vez de JSON: sendBeacon no puede poner cabeceras y el token
    // tiene que viajar en el cuerpo para que Laravel valide el POST.
    const datos = new FormData();
    datos.append('_token', token);
    datos.append('motivo', motivo);
    datos.append('ruta', location.pathname.replace(/^\//, ''));

    navigator.sendBeacon(RUTA_ENVIO, datos);
};

/**
 * Registra los bordes de una ausencia: el navegador avisa cuando la pestaña se
 * esconde y cuando se cierra, que es lo que separa «tarda en trabajar» de
 * «no estaba».
 */
export function vigilarSesionNavegador(): void {
    if (typeof document === 'undefined') return;

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            if (Math.round((Date.now() - desdeQueSeVe) / 1000) < SEGUNDOS_DE_AUSENCIA) return;

            avisoPendiente = true;
            enviar('oculta');

            return;
        }

        desdeQueSeVe = Date.now();

        if (!avisoPendiente) return;

        avisoPendiente = false;
        enviar('visible');
    });

    // pagehide es el evento que sí llega durante el cierre (unload ya no se garantiza).
    window.addEventListener('pagehide', () => enviar('cierre'));
}
