import { computed, reactive, ref, toValue, watch, type MaybeRefOrGetter } from 'vue';

export const TALLAS_ALFABETICAS = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
export const CONTROLES_SIN_TALLA = ['Sin color ni talla', 'Sin talla', 'Sin talla / sin color', 'Sin color'];
export const MAX_TALLAS_POR_RANGO = 50;

export type ModoTallas = 'sin-control' | 'sin-talla' | 'numerica' | 'alfabetica' | 'color' | 'diseno';

/**
 * Deriva las tallas (y su resumen de opciones) del control de variante elegido.
 *
 * - sin talla: no se preguntan opciones y se guarda vacío
 * - talla numérica: un rango "desde-hasta" que se expande en tallas consecutivas
 * - talla alfabética: elección individual sobre XS..XXL
 * - color / diseño: se eligen valores de sus listas, sin tallas
 */
export function useTallasVariante(controlDeVariante: MaybeRefOrGetter<string>) {
    const modo = computed<ModoTallas>(() => {
        const control = toValue(controlDeVariante);
        if (CONTROLES_SIN_TALLA.includes(control)) return 'sin-talla';
        if (control === 'Talla numerica') return 'numerica';
        if (control === 'Talla alfabética') return 'alfabetica';
        if (control === 'Color') return 'color';
        if (control === 'Diseño') return 'diseno';
        return 'sin-control';
    });

    const rango = reactive({ desde: '', hasta: '' });
    const tallasEscogidas = ref<string[]>([]);
    const opcionesEscogidas = ref<string[]>([]);

    // Cambiar de control invalida lo escogido con el anterior.
    watch(
        () => toValue(controlDeVariante),
        () => {
            opcionesEscogidas.value = [];
        },
        { flush: 'sync' },
    );

    const alternarTalla = (talla: string) => {
        tallasEscogidas.value = tallasEscogidas.value.includes(talla)
            ? tallasEscogidas.value.filter((t) => t !== talla)
            : [...tallasEscogidas.value, talla];
    };

    const tallasNumerizadas = computed(() => {
        const desde = Number(rango.desde);
        const hasta = Number(rango.hasta);
        if (!rango.desde || !rango.hasta || !Number.isInteger(desde) || !Number.isInteger(hasta)) return [];
        if (desde < 0 || hasta < desde) return [];
        if (hasta - desde + 1 > MAX_TALLAS_POR_RANGO) return [];
        return Array.from({ length: hasta - desde + 1 }, (_, i) => String(desde + i));
    });

    const tallas = computed(() => {
        if (modo.value === 'numerica') return tallasNumerizadas.value;
        if (modo.value === 'alfabetica') return TALLAS_ALFABETICAS.filter((t) => tallasEscogidas.value.includes(t));
        return [];
    });

    const rangoDemasiadoGrande = computed(() =>
        modo.value === 'numerica'
        && String(rango.desde) !== '' && String(rango.hasta) !== ''
        && Number(rango.hasta) >= Number(rango.desde)
        && Number(rango.hasta) - Number(rango.desde) + 1 > MAX_TALLAS_POR_RANGO
    );

    const opcionesTexto = computed(() => {
        if (modo.value === 'color' || modo.value === 'diseno') return opcionesEscogidas.value.join(', ');
        if (!tallas.value.length) return '';
        if (modo.value === 'numerica') return `${rango.desde}-${rango.hasta}`;
        return tallas.value.join(', ');
    });

    const limpiar = () => {
        rango.desde = '';
        rango.hasta = '';
        tallasEscogidas.value = [];
        opcionesEscogidas.value = [];
    };

    /**
     * Reconstruye el estado del control a partir de tallas ya registradas,
     * para que la edición arranque con lo que hay en base de datos.
     * Se evita leer `modo` aqui: el formulario carga dentro de un watchEffect
     * y esa lectura volvería reactiva la edición del control de variante.
     */
    const sembrar = (actuales: string[], resumen = '') => {
        limpiar();
        const numericas = actuales.map((talla) => Number(talla)).filter((talla) => Number.isInteger(talla));
        if (numericas.length) {
            rango.desde = String(Math.min(...numericas));
            rango.hasta = String(Math.max(...numericas));
            return;
        }
        const rangoGuardado = resumen.match(/^\s*(\d+)\s*-\s*(\d+)\s*$/);
        if (rangoGuardado) {
            rango.desde = rangoGuardado[1];
            rango.hasta = rangoGuardado[2];
            return;
        }
        const partes = resumen.split(/\s*,\s*/).map((parte) => parte.trim()).filter(Boolean);
        if (partes.length && partes.every((parte) => TALLAS_ALFABETICAS.includes(parte))) {
            tallasEscogidas.value = partes;
            return;
        }
        tallasEscogidas.value = actuales.filter((talla) => TALLAS_ALFABETICAS.includes(talla));
        opcionesEscogidas.value = partes;
    };

    return {
        modo,
        rango,
        tallasEscogidas,
        opcionesEscogidas,
        tallas,
        alternarTalla,
        rangoDemasiadoGrande,
        opcionesTexto,
        limpiar,
        sembrar,
    };
}
