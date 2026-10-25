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
    | { tipo: 'subtitulo'; texto: string }
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
            'cerrada',
            'retroceder',
            'avanzar',
            'máquina de estados',
        ],
        actualizada: '2026-10-23',
        bloques: [
            {
                tipo: 'texto',
                texto:
                    'Una Orden de Producción (OP) recorre un flujo de 8 estados. Cada estado responde por una parte del inventario, así que el formulario de edición abre o cierra campos según el punto del flujo en el que esté la orden.',
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
                    'Recibiendo: recepción de calidad por talla; solo lo aprobado entra a inventario y los pares en reparación quedan pendientes de un compromiso.',
                    'Cerrada: estado final, registrado por el sistema cuando la recepción queda completa. Los movimientos no se editan sin un procedimiento de ajuste.',
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
                    {
                        pregunta: '¿Por qué ya no aparece el estado «Pendiente»?',
                        respuesta:
                            'Dejó de existir. Los pares que se van a reparación se siguen controlando desde «Recibiendo», donde cada talla registra su fecha de compromiso pendiente.',
                    },
                    {
                        pregunta: '¿Cómo cierro una OP?',
                        respuesta:
                            'No se cierra a mano: «Recibiendo» no ofrece la opción «Cerrada». El sistema cierra la OP al guardar cuando cada talla quedó completa (aprobada + en reparación + no recuperable = esperada), no quedan pares en reparación y los datos del cierre están registrados.',
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
    {
        id: 'op-recepcion-y-cierre-automatico',
        categoria: 'OP',
        categoriaLabel: 'Órdenes de producción',
        subcategoria: 'Estado de la OP',
        titulo: 'Recepción de calidad, compromiso de reparación y cierre automático',
        resumen:
            'En «Recibiendo» se registra la calidad por talla; lo que se va a reparación exige una fecha de compromiso pendiente con sus avisos, y la OP se cierra sola cuando ya no queda nada pendiente.',
        etiquetas: [
            'recepción de calidad',
            'recibiendo',
            'aprobada',
            'en reparación',
            'no recuperable',
            'cuadrar tallas',
            'fecha de compromiso pendiente',
            'compromiso de reparación',
            'avisos',
            'notificaciones',
            'cierre automático',
            'cerrar op',
            'cierre de op',
            'costo real final',
        ],
        actualizada: '2026-10-23',
        bloques: [
            {
                tipo: 'texto',
                texto:
                    'La recepción de calidad se diligencia en el modal «Cambiar estado» cuando la OP está en «Recibiendo». Hay una fila por talla (con su producto) y se registran tres cantidades: aprobada, en reparación y no recuperable. La suma de las tres es la cantidad recibida y debe cuadrar con la cantidad esperada de la talla.',
            },
            {
                tipo: 'tabla',
                columnas: ['Cantidad', 'Qué significa', 'Efecto'],
                filas: [
                    ['Aprobada', 'El par pasó el control de calidad.', 'Es lo único que entra al inventario disponible.'],
                    ['En reparación', 'El par vuelve al taller a corregirse.', 'No entra a inventario y todos los pares en reparación comparten una fecha de compromiso pendiente.'],
                    ['No recuperable', 'El par se perdió o no tiene arreglo.', 'Sale de la cuenta de la OP; queda como merma de producción.'],
                ],
            },
            {
                tipo: 'subtitulo',
                texto: 'Cuándo se cierra la OP',
            },
            {
                tipo: 'pasos',
                items: [
                    'Cada talla registró exactamente su cantidad esperada (aprobada + en reparación + no recuperable = esperada).',
                    'No quedó ningún par en reparación.',
                    'Están registrados los tres datos del cierre: materiales sobrantes o recuperados, pérdidas en mermas y costo real final.',
                    'Al presionar «Guardar», el sistema pone la OP en «Cerrada». El usuario no elige «Cerrada»: desde «En taller» solo se puede ir a «Recibiendo», y «Recibiendo» no ofrece salidas manuales.',
                ],
            },
            {
                tipo: 'nota',
                tono: 'info',
                texto:
                    'Ejemplo: el producto 1 tiene 3 tallas pendientes. Si se marca 1 en aprobada y 2 en no recuperable, la talla quedó completa y sin pares en reparación; al guardar, la OP pasa a «Cerrada».',
            },
            {
                tipo: 'subtitulo',
                texto: 'Compromiso de reparación',
            },
            {
                tipo: 'texto',
                texto:
                    'Si la recepción deja pares en reparación, se registra una sola fecha de compromiso pendiente para todos ellos. Cada vez que la OP se va a reparación se abre un episodio nuevo por producto + talla en la tabla compromisos_reparacion, todos con la misma fecha, así que el historial de los viajes al taller se conserva. Cuando esa talla se queda sin pares en reparación, el episodio se marca como atendido.',
            },
            {
                tipo: 'lista',
                items: [
                    'Aviso 1: dos días antes de la fecha de compromiso, prioridad media.',
                    'Aviso 2: el día de la fecha de compromiso, prioridad alta.',
                    'Los dos avisos se le envían al responsable de la OP y quedan programados en la campana de notificaciones: solo aparecen cuando llega su fecha.',
                ],
            },
            {
                tipo: 'preguntas',
                pares: [
                    {
                        pregunta: 'La recepción no cuadra todavía. ¿Pierdo lo digitado?',
                        respuesta:
                            'No. «Guardar avance» conserva las cantidades sin exigir que cuadren y sin cambiar el estado; puede seguir ajustando la recepción otros días.',
                    },
                    {
                        pregunta: 'Quedaron pares en reparación y quiero cerrar la OP.',
                        respuesta:
                            'No se puede: mientras haya pares en reparación la OP sigue en «Recibiendo». Cuando el taller devuelva esos pares y la talla quede completa sin reparación, el cierre se produce solo.',
                    },
                    {
                        pregunta: '¿Puedo cambiar la fecha de compromiso de un episodio?',
                        respuesta:
                            'Sí. Al guardar con una fecha distinta se actualiza el episodio abierto y se generan de nuevo los dos avisos con la fecha nueva.',
                    },
                ],
            },
            {
                tipo: 'lista',
                items: [
                    'Recepción y cierre automático: app/Http/Controllers/OrdenProduccionController.php (cambiarEstado, recepcionesDeCalidad, recepcionCompleta).',
                    'Compromisos y avisos: app/Models/CompromisoReparacion.php y OrdenProduccionController.php (registrarCompromisosReparacion, avisarCompromisoReparacion).',
                    'Avisos programados: packages/notificacion/src/NotificacionesManager.php (crear con fecha_programada, noLeidas).',
                    'Formulario: resources/js/pages/OrdenProduccion/CambiarEstadoModal.vue.',
                ],
            },
        ],
        fuentes: [
            'app/Http/Controllers/OrdenProduccionController.php (cambiarEstado)',
            'app/Models/CompromisoReparacion.php',
            'tablas recepcion_calidads, compromisos_reparacion, notificaciones (columna fecha_programada)',
            'resources/js/pages/OrdenProduccion/CambiarEstadoModal.vue',
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
