import { computed, ref } from 'vue';

export const OPCION_OTRO = 'Otro';

type FormConErrores = {
    setError: (campo: string, mensaje: string) => void;
} & Record<string, any>;

/**
 * Select con opción "Otro": al elegirla se escribe el valor real y ese texto
 * es el que termina guardado en el campo.
 *
 * `cargar` deja el texto visible cuando el registro trae un valor que no está
 * en la lista (por ejemplo, datos viejos o cargados por Excel).
 */
export function useSeleccionConOtro(
    form: FormConErrores,
    campo: string,
    opciones: () => string[],
    mensajeOtro: string,
) {
    const otro = ref('');

    const seleccion = computed({
        get: () => {
            const valor = form[campo] as string;
            if (!valor) return '';
            return opciones().includes(valor) ? valor : OPCION_OTRO;
        },
        set: (valor: string) => {
            if (valor === OPCION_OTRO) {
                otro.value = opciones().includes(form[campo] as string) ? '' : (form[campo] as string);
                form[campo] = OPCION_OTRO;
                return;
            }
            form[campo] = valor;
            otro.value = '';
        },
    });

    const aplicar = (): boolean => {
        if (seleccion.value !== OPCION_OTRO) return true;
        const detalle = otro.value.trim();
        if (!detalle) {
            form.setError(campo, mensajeOtro);
            return false;
        }
        form[campo] = detalle;
        return true;
    };

    const reiniciar = (): void => {
        otro.value = '';
    };

    const cargar = (): void => {
        const valor = form[campo] as string;
        otro.value = valor && !opciones().includes(valor) ? valor : '';
    };

    return { otro, seleccion, aplicar, reiniciar, cargar };
}
