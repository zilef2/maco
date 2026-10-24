/**
 * Explicaciones del sistema (contenido de la pantalla /Explicaciones).
 *
 * El contenido vive aquí, separado de la vista, para que sea legible tanto por
 * personas como por una IA: cada explicación es un objeto plano, con tipos
 * cerrados y sin HTML, que se puede serializar, buscar y copiar.
 *
 * PARA AGREGAR UNA EXPLICACIÓN NUEVA
 * 1. Elija `categoria` (p. ej. 'OP', 'Material', 'Producto') y `subcategoria`
 *    (p. ej. 'Estado de la OP'). Si la categoría ya existe, reutilice el nombre
 *    exacto: la pantalla agrupa por categoría y subcategoría.
 * 2. Cree un objeto `Explicacion` con `id` en kebab-case y único (es el ancla
 *    `#id` que se enlaza desde otras pantallas).
 * 3. Escriba `resumen` en una frase y luego los `bloques` en el orden de lectura.
 * 4. `etiquetas` es para la búsqueda: incluya sinónimos y términos que usaría
 *    alguien (o un modelo) al buscar el tema.
 * 5. `fuentes` apunta los archivos/SQL donde está implementada la regla: así la
 *    explicación se puede verificar contra el código.
 */

export type BloqueExplicacion =
    | { tipo: 'texto'; texto: string }
    | { tipo: 'lista'; items: string[] }
    | { tipo: 'pasos'; items: string[] }
    | { tipo: 'tabla'; columnas: string[]; filas: string[][] }
    | { tipo: 'preguntas'; pares: { pregunta: string; respuesta: string }[] }
    | { tipo: 'nota'; tono: 'info' | 'alerta' | 'ok'; texto: string };

export type Explicacion = {
    /** Slug estable y único; sirve de ancla y de identificador para una IA. */
    id: string;
    /** Agrupación de primer nivel, tal como se ve en la pantalla. */
    categoria: string;
    /** Nombre largo de la categoría, para títulos y para buscar. */
    categoriaLabel: string;
    /** Agrupación de segundo nivel dentro de la categoría. */
    subcategoria: string;
    titulo: string;
    /** Una frase: qué resuelve esta explicación. */
    resumen: string;
    /** Términos de búsqueda, incluidos sinónimos y formas coloquiales. */
    etiquetas: string[];
    /** Fecha del último cambio relevante, en YYYY-MM-DD. */
    actualizada: string;
    bloques: BloqueExplicacion[];
    /** Archivos, controladores o tablas donde la regla está implementada. */
    fuentes?: string[];
};

export const explicaciones: Explicacion[] = [
    {
        id: 'op-estado-de-la-op',
        categoria: 'OP',
        categoriaLabel: 'Órdenes de producción',
        subcategoria: 'Estado de la OP',
        titulo: 'Qué se puede editar en cada estado de una OP',
        resumen:
            'El estado de la orden decide qué campos del formulario de edición siguen abiertos: el número, el tipo y el responsable nunca cambian, y el plan de producción se congela cuando la OP entra a «En taller».',
        etiquetas: [
            'estado op',
            'estados de la orden',
            'flujo de producción',
            'editar orden de producción',
            'campos bloqueados',
            'no me deja editar',
            'en taller',
            'lista para taller',
            'recibiendo',
            'pendiente',
            'cerrada',
            'retroceder',
            'avanzar',
            'máquina de estados',
        ],
        actualizada: '2026-10-22',
        bloques: [
            {
                tipo: 'texto',
                texto:
                    'Una Orden de Producción (OP) recorre un flujo de 9 estados. Cada estado responde por una parte del inventario, así que el formulario de edición abre o cierra campos según el punto del flujo en el que esté la orden.',
            },
            {
                tipo: 'pasos',
                items: [
                    'Borrador: se guardó con «Terminar después». No genera movimientos de inventario.',
                    'Preparación: se comparan los materiales requeridos contra los disponibles.',
                    'Esperando materiales: faltan insumos; la OP todavía no debe enviarse al taller.',
                    'Procesos externos: el material sale a un proveedor pero sigue siendo propiedad de Macondas.',
                    'Lista para taller: el sistema valida que no haya faltantes antes de entregar.',
                    'En taller: la orden está en producción; el kit salió de bodega y quedó registrado en los movimientos.',
                    'Recibiendo: recepción de calidad por talla; solo lo aprobado entra a inventario.',
                    'Pendiente: hay pares en reparación que no ingresan al inventario disponible.',
                    'Cerrada: estado final. Los movimientos no se editan sin un procedimiento de ajuste.',
                ],
            },
            {
                tipo: 'tabla',
                columnas: ['Campo', '¿Se puede editar?', 'Hasta cuándo / por qué'],
                filas: [
                    ['Número OP', 'Nunca', 'Lo asigna el servidor al crear la orden (OP-XXX, el mayor registrado + 1) y es el identificador de la OP.'],
                    ['Tipo (taller o externa)', 'Nunca', 'Decide si el destino es un Taller o una Empresa externa; cambiarlo reescribiría el origen de la producción.'],
                    ['Responsable', 'Nunca desde «Editar»', 'Se muestra en la cabecera del formulario junto al número de la OP.'],
                    ['Fecha prevista de envío a taller', 'Sí', 'Hasta que la OP entra a «En taller».'],
                    ['Taller / Empresa externa', 'Sí', 'Hasta que la OP entra a «En taller».'],
                    ['Productos de la orden', 'Sí', 'Hasta «En taller»: de los productos y su ficha técnica se deriva el kit de proceso.'],
                    ['Tallas y cantidades planificadas', 'Sí', 'Hasta «En taller»: son la base del requerimiento de materiales.'],
                    ['ID Shopify y observaciones', 'Siempre', 'No afectan el kit ni el inventario.'],
                ],
            },
            {
                tipo: 'nota',
                tono: 'alerta',
                texto:
                    'Al pasar a «En taller» el kit descuenta inventario y esa resta queda en el histórico de movimientos. Si después se cambiaran productos o tallas, el histórico ya no cuadraría con lo que realmente salió de bodega: por eso el plan se congela.',
            },
            {
                tipo: 'texto',
                texto:
                    'Cuando un campo aparece bloqueado, la pantalla de edición lo muestra en gris con el candado y no lo envía al guardar, de modo que el servidor conserva el valor registrado aunque otro usuario lo haya cambiado.',
            },
            {
                tipo: 'preguntas',
                pares: [
                    {
                        pregunta: 'La OP ya está «En taller» y me equivoqué un producto o una talla. ¿Qué hago?',
                        respuesta:
                            'Retroceda la orden con el botón «Cambiar estado» hasta un estado anterior a «En taller» (por ejemplo «Lista para taller»), corrija en «Editar» y vuelva a avanzarla. Nunca se edita el plan mientras la orden está en taller.',
                    },
                    {
                        pregunta: '¿Puedo cambiar el responsable desde «Editar»?',
                        respuesta:
                            'No. El responsable se muestra en la cabecera del formulario y se asigna al crear la orden o mediante el cambio de estado, no desde la edición.',
                    },
                    {
                        pregunta: '¿Dos estados pueden ocurrir a la vez?',
                        respuesta:
                            'Solo un par: «Esperando materiales» y «Procesos externos» pueden coexistir en la misma OP. Es la única combinación permitida y el estado secundario se limpia solo al salir de esos dos estados.',
                    },
                    {
                        pregunta: '¿De dónde salen los estados que puedo elegir?',
                        respuesta:
                            'De la columna puede_pasar_a del estado actual, en la tabla estado_operacions. El modal agrupa esas transiciones en botones «Avanzar» y «Retroceder» usando el orden del flujo; si el estado actual no admite salidas, aparece como estado final.',
                    },
                    {
                        pregunta: 'Una OP en «Borrador» también tiene bloqueos?',
                        respuesta:
                            'No. Antes de «En taller» el plan está abierto: lo único fijo en cualquier estado es el número de la OP, el tipo y el responsable.',
                    },
                ],
            },
            {
                tipo: 'lista',
                items: [
                    'El orden del flujo vive en resources/js/composables/flujoEstadosOp.ts (ORDEN_FLUJO, sePuedeEditarPlan).',
                    'Las transiciones permitidas vienen de la tabla estado_operacions (columna puede_pasar_a) y se validan en el backend.',
                    'El bloqueo de campos se aplica en resources/js/pages/OrdenProduccion/Edit.vue: los campos congelados no se muestran editables y viajan fuera del payload.',
                ],
            },
        ],
        fuentes: [
            'resources/js/composables/flujoEstadosOp.ts',
            'resources/js/pages/OrdenProduccion/Edit.vue',
            'resources/js/pages/OrdenProduccion/CambiarEstadoModal.vue',
            'app/Http/Controllers/OrdenProduccionController.php (update, cambiarEstado)',
            'tabla estado_operacions: nombre, puede_pasar_a, regla_clave',
        ],
    },
];

/** Categorías en el orden en que aparecen, cada una con sus subcategorías. */
function agruparCategorias(): Array<{ categoria: string; categoriaLabel: string; subcategorias: string[] }> {
    const mapa = new Map<string, { categoria: string; categoriaLabel: string; subcategorias: string[] }>();
    explicaciones.forEach((explicacion) => {
        const grupo = mapa.get(explicacion.categoria) ?? {
            categoria: explicacion.categoria,
            categoriaLabel: explicacion.categoriaLabel,
            subcategorias: [],
        };
        if (!grupo.subcategorias.includes(explicacion.subcategoria)) {
            grupo.subcategorias.push(explicacion.subcategoria);
        }
        mapa.set(explicacion.categoria, grupo);
    });
    return Array.from(mapa.values());
}

export const categoriasOrdenadas = agruparCategorias();
