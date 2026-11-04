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
                    ['No recuperable', 'El par se perdió o no tiene arreglo.', 'Sale de la cuenta de la OP y deja un registro de merma (se totaliza en /Merma).'],
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
                    'El sistema calcula automáticamente las pérdidas en mermas y el costo real final con los productos recibidos; no hay que digitarlos.',
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
                            'No. «Guardar avance» conserva las cantidades sin exigir que cuadren; puede seguir ajustando la recepción otros días. En cuanto el avance deja al menos un producto recibido (aprobado, en reparación o no recuperable), la OP pasa a «Recibiendo».',
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
    {
        id: 'op-merma-desde-no-recuperable',
        categoria: 'OP',
        categoriaLabel: 'Órdenes de producción',
        subcategoria: 'Merma',
        titulo: 'La merma nace del «No recuperable»: no se registra a mano',
        resumen:
            'Cada talla que queda con pares «No recuperable» en la recepción de una OP deja un registro de merma, y la pantalla de Mermas se limita a totalizarlos por mes y por periodo.',
        etiquetas: [
            'merma',
            'mermas',
            'no recuperable',
            'pares perdidos',
            'productos perdidos',
            'valor perdido',
            'cuánto se pierde al mes',
            'merma a 60 días',
            'merma por mes',
            'porcentaje de pérdida',
        ],
        actualizada: '2026-11-03',
        bloques: [
            {
                tipo: 'texto',
                texto:
                    'La pantalla /Merma es de solo lectura y no tiene botón de «Agregar»: una merma no es un dato que alguien deba digitar, sino la consecuencia de lo que ya se registró en la recepción de calidad. Su origen es siempre el formulario «Cambiar estado» de una OP con el estado «Recibiendo», en la columna «No recuperable».',
            },
            {
                tipo: 'subtitulo',
                texto: 'Cómo nace, se corrige o desaparece una merma',
            },
            {
                tipo: 'pasos',
                items: [
                    'En «Cambiar estado» (OP en «Recibiendo») se digitan las tres cantidades por talla: aprobada, en reparación y no recuperable.',
                    'Al guardar, cada talla con no recuperable deja UN registro de merma ligado a esa recepción (la llave es la recepción, así que re-guardar no duplica).',
                    '«Guardar avance» también la registra: no hay que esperar el cierre de la OP para que la pérdida quede anotada.',
                    'Si al volver a guardar la recepción la talla sube o baja su no recuperable, el registro se ajusta. Si queda en cero, se borra.',
                    'Un par que estaba en reparación y al regresar se marca no recuperable entra en la merma de esa talla en el siguiente guardado.',
                ],
            },
            {
                tipo: 'tabla',
                columnas: ['Campo de la merma', 'De dónde sale'],
                filas: [
                    ['Orden de producción', 'La OP de la recepción.'],
                    ['Producto / material y talla', 'El objetivo y la talla de la fila de recepción (en una OP de materiales, el objetivo es el material procesado).'],
                    ['Piezas perdidas', 'La cantidad digitada en «No recuperable».'],
                    ['Fecha de registro', 'La fecha de recepción anotada en el formulario.'],
                    ['Costo unitario', 'El costo de materiales de una unidad del producto o material, con la ficha técnica y los precios guardados en la OP.'],
                    ['Valor perdido', 'Piezas perdidas × costo unitario; es el mismo criterio con el que se calcula «Pérdidas en mermas» del cierre.'],
                    ['Registrado por', 'Quién guardó el cambio de estado.'],
                ],
            },
            {
                tipo: 'subtitulo',
                texto: 'Qué se puede totalizar',
            },
            {
                tipo: 'lista',
                items: [
                    'Periodo: mes actual, últimos 30 días, últimos 60 días o todo el histórico.',
                    'Tarjetas del periodo: pares perdidos, valor perdido, órdenes afectadas y pérdida sobre lo esperado (piezas perdidas entre lo esperado de las tallas con merma).',
                    'Tabla «Pérdidas por mes»: piezas, valor y órdenes de cada mes. Presionar un mes filtra el detalle a ese mes.',
                    'Detalle de una fila: cómo quedó esa talla (esperada, aprobada, en reparación, no recuperable), su costo y quién la registró, con un acceso a la OP de origen.',
                ],
            },
            {
                tipo: 'preguntas',
                pares: [
                    {
                        pregunta: '¿Puedo editar o borrar una merma desde /Merma?',
                        respuesta:
                            'No. Se corrige desde la OP: abrir «Cambiar estado» con la OP en «Recibiendo» y volver a guardar la recepción con la cantidad correcta.',
                    },
                    {
                        pregunta: '¿Por qué el costo unitario sale en cero?',
                        respuesta:
                            'Porque la ficha técnica del producto (o del material procesado) no tiene materiales con precio, o la OP no tiene guardados sus precios. La cantidad perdida sí queda registrada.',
                    },
                    {
                        pregunta: '¿Borrar una OP deja mermas huérfanas?',
                        respuesta:
                            'No. Las mermas se eliminan con la OP, junto con sus recepciones y sus cierres.',
                    },
                ],
            },
            {
                tipo: 'nota',
                tono: 'info',
                texto:
                    'Ejemplo: la OP-103 recibe Zapatillas Runner Pro y marca 2 no recuperables en la talla 38 y 1 en la talla 39. En /Merma aparecen dos registros (uno por talla) que suman 3 pares perdidos; si el mes siguiente se re-guarda la talla 38 con 1 no recuperable, el registro baja a 1 en lugar de abrir uno nuevo.',
            },
        ],
        fuentes: [
            'app/Services/MermaRecepcionService.php (sincronizar)',
            'app/Http/Controllers/OrdenProduccionController.php (cambiarEstado, guardarRecepcionBorrador)',
            'app/Http/Controllers/MermaController.php (index)',
            'app/Services/CostoRecepcionOpService.php (unitarios, resumenCierre)',
            'tablas mermas (recepcion_calidad_id único), recepcion_calidads',
            'resources/js/pages/Merma/Index.vue',
        ],
    },
    {
        id: 'op-costos-materiales-parametro',
        categoria: 'OP',
        categoriaLabel: 'Órdenes de producción',
        subcategoria: 'Costos de materiales',
        titulo: 'Cómo se toma el precio de un material al costear una OP',
        resumen:
            'El parámetro entero «costos_promedio» de /parametros decide si el costo de un producto usa el promedio de los cambios de precio de sus materiales (1) o el último precio digitado (2). El precio usado queda guardado en la OP y cada cambio se anota en su historial.',
        etiquetas: [
            'costos_promedio',
            'costo de la op',
            'precio promedio',
            'ultimo precio del material',
            'costo de materiales',
            'ficha tecnica costo',
            'costo_asociado_teorico',
            'material prices',
            'porque cambio el costo',
            'parametro de costos',
        ],
        actualizada: '2026-10-25',
        bloques: [
            {
                tipo: 'texto',
                texto:
                    'El costo de un producto sale de su ficha técnica: cada línea aporta el consumo estándar del material multiplicado por el precio unitario del material. Como el precio de un material cambia con las compras, el parámetro «costos_promedio» define de dónde se toma ese precio.',
            },
            {
                tipo: 'tabla',
                columnas: ['costos_promedio', 'Precio unitario que se usa', 'De dónde sale'],
                filas: [
                    ['1', 'Promedio de los cambios de precio del material', 'Promedio de todas las filas del material en material_prices'],
                    ['2', 'Último precio digitado del material', 'El precio más reciente registrado en la app'],
                ],
            },
            {
                tipo: 'subtitulo',
                texto: 'El precio queda guardado en la OP',
            },
            {
                tipo: 'lista',
                items: [
                    'Al crear, guardar como borrador o editar una OP se calcula el precio de cada material y se guarda en orden_produccion_costos_materiales (precio unitario, cantidad y costo), con el método (1 o 2) que lo produjo.',
                    'Si el precio guardado de un material cambia, el cambio se anota en orden_produccion_costos_cambios con el precio anterior, el nuevo, el método, la fecha y quién lo registró. La primera vez que se guarda un precio se anota sin precio anterior.',
                    'El total de la columna «Costo fichas» de la tabla de OPs es la suma de esos costos guardados.',
                ],
            },
            {
                tipo: 'subtitulo',
                texto: 'Cuándo se recalcula solo',
            },
            {
                tipo: 'lista',
                items: [
                    'Al guardar una OP (nueva, borrador o edición).',
                    'Al cambiar el precio de un material en su ficha de Material: se renuevan las OPs abiertas que lo usan y queda el cambio en el historial de cada OP.',
                    'Al cambiar el parámetro costos_promedio en /parametros: se renuevan todas las OPs abiertas.',
                    'Las OPs cerradas no se recalculan: conservan el precio con el que cerraron.',
                ],
            },
            {
                tipo: 'preguntas',
                pares: [
                    {
                        pregunta: '¿Dónde veo el precio guardado y sus cambios?',
                        respuesta:
                            'En la tabla de OPs, haciendo clic en el chip del estado: el detalle muestra «Costo de los materiales» con el precio y el método usado, y «Cambios de precio en esta OP» con el historial.',
                    },
                    {
                        pregunta: 'Una OP antigua no tiene precios guardados. ¿Qué muestra?',
                        respuesta:
                            'Muestra el costo calculado en vivo con el método vigente. El precio queda guardado en cuanto esa OP se guarda de nuevo o le cambian el precio a uno de sus materiales.',
                    },
                    {
                        pregunta: '¿El «costo teórico» de la ficha técnica todavía sirve?',
                        respuesta:
                            'Sí, es el valor digitado en la ficha para cada línea. El parámetro solo decide si, al costear una OP, se usa ese precio o el promedio/último precio histórico del material; la vista previa de nueva OP y de edición usan el precio del método vigente.',
                    },
                ],
            },
            {
                tipo: 'nota',
                tono: 'info',
                texto:
                    'El parámetro es un entero (valor_int del registro costos_promedio de la tabla parametros) y se edita en /parametros, en la tarjeta «Costos de materiales en las OPs».',
            },
        ],
        fuentes: [
            'app/Services/CostoMaterialOpService.php',
            'app/Models/Parametro.php (COSTOS_PROMEDIO, entero)',
            'app/Models/Material.php (costoUnitarioPromedio, costoUnitarioActual)',
            'app/Http/Controllers/ParametrosController.php (actualizarCostosPromedio)',
            'tablas orden_produccion_costos_materiales, orden_produccion_costos_cambios, material_prices',
            'resources/js/pages/parametro/Index.vue y resources/js/pages/OrdenProduccion/DetalleEstadoModal.vue',
        ],
    },
    {
        id: 'producto-tallas-solo-lectura',
        categoria: 'Producto',
        categoriaLabel: 'Productos',
        subcategoria: 'Tallas',
        titulo: 'Las tallas de un producto solo se pueden ver desde Productos',
        resumen:
            'El botón «Tallas» del listado de Productos abre la distribución por tallas en modo solo lectura e informa en qué órdenes de producción se está usando; los cambios se hacen desde Órdenes de Producción.',
        etiquetas: [
            'tallas',
            'distribución por tallas',
            'cantidades planificadas',
            'plantilla de tallas',
            'solo lectura',
            'producto',
            'OP que usa las tallas',
        ],
        actualizada: '2026-10-25',
        bloques: [
            {
                tipo: 'texto',
                texto:
                    'La distribución por tallas del producto es la plantilla que toman las órdenes de producción. Cuando una talla ya está usada por una OP, modificarla desde Productos cambiaría algo que forma parte de una orden en curso, así que la pantalla solo la muestra.',
            },
            {
                tipo: 'subtitulo',
                texto: 'Qué se ve en el modal',
            },
            {
                tipo: 'lista',
                items: [
                    'Cada talla con su cantidad planificada y el total planificado del producto.',
                    'El historial de cambios de tallas (quién, cuándo y qué cambió).',
                    'Un resumen al pie con las OPs que pertenecen a esas tallas, por ejemplo «Estas tallas pertenecen a 3 órdenes de producción (OP-100, OP-101, OP-102). Si desea modificarlas, diríjase al apartado de Órdenes de Producción.».',
                ],
            },
            {
                tipo: 'preguntas',
                pares: [
                    {
                        pregunta: 'Entonces, ¿dónde cambio las cantidades por talla?',
                        respuesta:
                            'En la orden de producción: el formulario de nueva OP y de edición tienen su propio editor de distribución por tallas, que es donde se ajusta lo que cada OP va a fabricar.',
                    },
                    {
                        pregunta: 'El resumen dice que no hay ninguna OP usando las tallas. ¿Pasa algo?',
                        respuesta:
                            'No. Significa que la plantilla todavía no está comprometida en ninguna orden; sigue sin editarse desde Productos, pero no hay riesgo de tocar una orden en curso.',
                    },
                    {
                        pregunta: '¿Y si el producto necesita tallas nuevas?',
                        respuesta:
                            'Las tallas disponibles salen del control de variante del producto; se configuran ahí y la distribución se materializa al usar el producto en una OP.',
                    },
                ],
            },
        ],
        fuentes: [
            'resources/js/pages/Producto/DistribucionTallas.vue (modal de solo lectura y aviso de OPs)',
            'app/Http/Controllers/ProductoController.php (index: ordenes_con_tallas)',
            'app/Http/Controllers/FichaTecnicaMaterialesController.php (index de productos sin ficha: ordenes_con_tallas)',
            'tablas distribucion_por_tallas, distribucion_producto, distribucion_orden_produccion',
            'resources/js/pages/OrdenProduccion/DistribucionTallasEditor.vue (edición desde la OP)',
        ],
    },
    {
        id: 'material-prevision-de-existencias',
        categoria: 'Material',
        categoriaLabel: 'Materiales',
        subcategoria: 'Previsión de existencias',
        titulo: 'Banner de previsión: para cuántos días alcanza cada material',
        resumen:
            'En Materiales, debajo del contador, un banner avisa los materiales cuyo stock no alcanza los días de previsión configurados, mostrando cómo se calculó.',
        etiquetas: [
            'previsión',
            'prevision',
            'banner',
            'faltantes',
            'dias restantes',
            'gasto por dia',
            'consumo diario',
            'existencia',
            'stock',
            'salida a taller',
            'en taller',
            'dias_prevision_stock',
        ],
        actualizada: '2026-10-28',
        bloques: [
            {
                tipo: 'texto',
                texto:
                    'Cuando una OP pasa a «En taller», el kit sale de bodega: se descuenta la existencia de cada material y queda un movimiento de salida con el motivo «Salida de materiales a taller». Esas salidas son la base del gasto diario: promedio de los últimos 30 días (total salido ÷ 30).',
            },
            {
                tipo: 'pasos',
                items: [
                    'Gasto por día = sumatoria de las salidas a taller del material en los últimos 30 días ÷ 30.',
                    'Días que alcanzan = existencia actual ÷ gasto por día (se toma el entero hacia abajo).',
                    'Si quedan «días de previsión» o menos, el material aparece en el banner rojo de Materiales con el cálculo completo.',
                    'El umbral se configura en Parámetros → Días de previsión de existencias (dias_prevision_stock); por defecto son 2 días.',
                ],
            },
            {
                tipo: 'nota',
                tono: 'info',
                texto:
                    'Ejemplo: si se gastan 100 unidades de Ajiné Grueso por día y la existencia es 299, el banner avisa que solo queda ajiné para 2 días.',
            },
        ],
        fuentes: [
            'app/Http/Controllers/MaterialController.php (prevision: gasto diario y días restantes)',
            'app/Http/Controllers/OrdenProduccionController.php (descontarMaterialesTaller al pasar a «En taller»)',
            'app/Http/Controllers/ParametrosController.php (actualizarDiasPrevision)',
            'app/Models/Parametro.php (DIAS_PREVISION_STOCK)',
            'resources/js/pages/Material/Index.vue (banner bajo el contador)',
            'resources/js/pages/parametro/Index.vue (card Días de previsión de existencias)',
            'tabla material_movimientos (tipo_movimiento salida, motivo «Salida de materiales a taller…»)',
        ],
    },
    {
        id: 'material-codigo-del-material',
        categoria: 'Material',
        categoriaLabel: 'Materiales',
        subcategoria: 'Código del material',
        titulo: 'Cómo se forma el código CAT-BASE-N y qué pasa al cambiar el par',
        resumen:
            'El código del material ya no es un consecutivo global (MAT-30): se arma con el código de la categoría, el código base y el número libre más alto de esa combinación, sin ceros a la izquierda.',
        etiquetas: [
            'codigo material',
            'codigo categoria',
            'codigo base',
            'consecutivo',
            'prefijo',
            'TEX-LFI-4',
            'MAT-30',
            'ceros a la izquierda',
            'abreviatura',
            'formato del codigo',
        ],
        actualizada: '2026-11-03',
        bloques: [
            {
                tipo: 'texto',
                texto:
                    'El par «código de categoría + código base» define el prefijo y el número es exclusivo de esa combinación. Si TEX-LFI llega hasta el 3, el siguiente material del par es TEX-LFI-4. El número lo asigna el servidor: en el formulario el campo de código sale bloqueado.',
            },
            {
                tipo: 'lista',
                items: [
                    'Cada fragmento admite hasta 3 letras, siempre en mayúsculas y sin tildes; lo que sobre se corta.',
                    'Si el par queda vacío, se abrevia el nombre: una palabra aporta sus tres primeras letras (Textil → TEX) y varias palabras aportan sus iniciales completadas con las letras siguientes (Lana fibrada → LFA).',
                    'Los ceros a la izquierda se borran al guardar y al importar: TEX-LFL-005 queda TEX-LFL-5.',
                    'Misma combinación de par → número consecutivo; par distinto → el material salta al siguiente número libre del prefijo nuevo y el número que deja vuelve a estar disponible.',
                    'El código es único en la base de datos, así que dos materiales no pueden quedar con el mismo.',
                ],
            },
            {
                tipo: 'preguntas',
                pares: [
                    {
                        pregunta: '¿Se puede escribir el código a mano?',
                        respuesta:
                            'No desde la app: el formulario lo genera. En el Excel sí se puede traer el número, y se respeta cuando está libre; si ya existe, la fila actualiza ese material en vez de crear otro.',
                    },
                    {
                        pregunta: '¿Qué pasó con los MAT-30 antiguos?',
                        respuesta:
                            'La migración los reescribió al formato nuevo derivando el par de su categoría y su nombre base. Los que ya venían en formato CAT-BASE-N conservaron su número.',
                    },
                    {
                        pregunta: '¿Por qué una fila nueva quedó con un número distinto al del archivo?',
                        respuesta:
                            'Porque ese número ya estaba usado por otro material del mismo par: se toma el siguiente libre. Para editar un material existente, el código de la fila debe coincidir con uno cargado (con o sin ceros).',
                    },
                ],
            },
            {
                tipo: 'nota',
                tono: 'info',
                texto:
                    'Volver a subir el mismo archivo no duplica materiales: las filas se emparejan por código normalizado, así que la segunda pasada actualiza en vez de crear.',
            },
        ],
        fuentes: [
            'app/Models/Material.php (aplicarReglaDelCodigo, normalizarCodigo, normalizarFragmento, abreviar, siguienteConsecutivo)',
            'app/Http/Controllers/MaterialController.php (store y uploadExcel usan la regla; el código no se copia del cliente en store)',
            'app/Services/PlantillasExcelService.php (columnas y ejemplos del formato de materiales)',
            'database/migrations/2026_11_03_110000_add_codigo_categoria_base_a_materials_table.php (columnas nuevas y reescritura de códigos MAT-##)',
            'resources/js/pages/Material/Create.vue y Edit.vue (código bloqueado, par editable en mayúsculas)',
            'resources/js/pages/Material/ImportarMaterialModal.vue (ayuda del formato, leída de la misma plantilla)',
        ],
    },
    {
        id: 'material-proveedor-habitual-del-excel',
        categoria: 'Material',
        categoriaLabel: 'Materiales',
        subcategoria: 'Proveedor habitual',
        titulo: 'Cómo reconoce el Excel el proveedor: primero el NIT, después el nombre',
        resumen:
            'La columna «Proveedor habitual» se resuelve contra los proveedores ya registrados; el NIT (900347285-7) es la forma fiable porque el nombre puede estar escrito con un artículo de más o de menos.',
        etiquetas: [
            'proveedor habitual',
            'nit',
            'nit proveedor',
            '900347285-7',
            'proveedor desconocido',
            'nombre comercial',
            'razon social',
            'codigo proveedor',
            'importar excel',
            'no se importó nada',
        ],
        actualizada: '2026-11-03',
        bloques: [
            {
                tipo: 'texto',
                texto:
                    'El Excel nunca guarda el nombre del proveedor: la columna «Proveedor habitual» de materiales se convierte al id del proveedor registrado. Si ninguna fila coincide, la subida se cancela en bloque y el aviso lista los proveedores que no se encontraron (máximo 7, con «y N más» al final).',
            },
            {
                tipo: 'pasos',
                items: [
                    'Si la celda trae NIT, se buscan sus dígitos: 900347285-7, 9003472857 y 900.347.285-7 son el mismo proveedor.',
                    'Si no, se prueba el nombre comercial, la razón social o el código (PRV-8 / PROV-8 / el id), sin mayúsculas, tildes ni puntuación.',
                    'El artículo inicial no cuenta: «Casa de los cordones» registra a «LA CASA DE LOS CORDONES».',
                    'De último recurso, si el texto escrito está contenido en el nombre de un solo proveedor (o al revés), se usa ese; con dos candidatos posibles se prefiere avisar antes que asignar el equivocado.',
                    'La columna es opcional: si la dejas vacía el material queda sin proveedor habitual, y en una fila que actualiza un material existente eso le borra el que tuviera.',
                ],
            },
            {
                tipo: 'preguntas',
                pares: [
                    {
                        pregunta: '¿Por qué salió «No se encontró este proveedor…» si el proveedor sí existe?',
                        respuesta:
                            'Porque el nombre registrado tiene una palabra que la hoja no repitió (el artículo «LA», por ejemplo). Escribir el NIT resuelve la duda sin ambigüedad.',
                    },
                    {
                        pregunta: '¿Puedo traer el NIT en su propia columna?',
                        respuesta:
                            'Sí. Si la hoja tiene una columna «NIT proveedor», se usa esa primero y «Proveedor habitual» queda como respaldo.',
                    },
                ],
            },
            {
                tipo: 'nota',
                tono: 'info',
                texto:
                    'En los terceros (talleres, empresas externas y proveedores) el NIT también es la clave con la que se empareja una subida: mismo NIT ⇒ se actualiza el registro existente.',
            },
        ],
        fuentes: [
            'app/Http/Controllers/MaterialController.php (uploadExcel: índices de proveedores y buscarProveedor)',
            'app/Services/PlantillasExcelService.php (descripcion de la columna Proveedor habitual)',
            'app/Http/Controllers/ImportarExcelController.php (tarjeta de materiales del Centro de Importación)',
            'resources/js/pages/Material/ImportarMaterialModal.vue (ayuda visible al subir)',
            'tabla proveedors (nit, nombre_comercial, razon_social, codigo_proveedor, nombre)',
        ],
    },
    {
        id: 'material-columna-procesado-del-excel',
        categoria: 'Material',
        categoriaLabel: 'Materiales',
        subcategoria: 'Importar Excel',
        titulo: 'La columna «Procesado» va obligatoriamente junto a «Costo»',
        resumen:
            'El Excel de materiales debe traer la columna «Procesado» (si o no) en la celda inmediatamente siguiente a «Costo»; sin ese orden la subida se cancela antes de leer una sola fila.',
        etiquetas: [
            'procesado',
            'material procesado',
            'columna costo',
            'orden de columnas',
            'si o no',
            'importar excel',
            'plantilla materiales',
        ],
        actualizada: '2026-11-03',
        bloques: [
            {
                tipo: 'texto',
                texto:
                    '«Procesado» alimenta material_procesado (0 o 1), el dato que decide si el material entra con ficha técnica propia al recibirlo en una OP. Es columna obligatoria del formato y solo se acepta escrita al lado de «Costo», que es el orden de la plantilla descargable.',
            },
            {
                tipo: 'pasos',
                items: [
                    'Se leen los títulos de la fila 1 ignorando mayúsculas, tildes y puntuación: «Costo», «Costo material» o «Procesado (si/no)» se reconocen igual.',
                    'Falta «Procesado»: el aviso de columnas requeridas lista la que faltó y no se importa nada.',
                    '«Procesado» existe pero en otra columna: el aviso dice en qué letra debía estar (la siguiente a «Costo»).',
                    'Sin columna «Costo» no se puede ubicar la vecina, así que también se rechaza el archivo.',
                    'Celda vacía ⇒ se guarda 0 (no procesado). Cualquier texto que no sea si o no cancela la subida y avisa la fila exacta (Fila 7: …).',
                ],
            },
            {
                tipo: 'preguntas',
                pares: [
                    {
                        pregunta: '¿Por qué el orden de las columnas ahora importa?',
                        respuesta:
                            'Porque el archivo se lee por posición, no por rótulos sueltos: exigir «Procesado» pegada a «Costo» mantiene la subida alineada con la plantilla que descarga la app y evita costos sin su bandera de procesado.',
                    },
                    {
                        pregunta: '¿Sirve «Es producido», como en archivos antiguos?',
                        respuesta:
                            'No. Ese rótulo ya no se reconoce; la columna se llama «Procesado», igual que en el listado y en la ficha del material.',
                    },
                ],
            },
        ],
        fuentes: [
            'app/Http/Controllers/MaterialController.php (uploadExcel: columnas requeridas, chequeo de posición y validación si/no)',
            'app/Services/PlantillasExcelService.php (columnas Costo y Procesado del formato de materiales)',
            'resources/js/pages/Material/ImportarMaterialModal.vue (ayuda visible al subir)',
            'materials.material_procesado',
        ],
    },
    {
        id: 'material-colores-y-otras-variantes',
        categoria: 'Material',
        categoriaLabel: 'Materiales',
        subcategoria: 'Variantes del material',
        titulo: 'Un material sí puede tener colores y otras variantes al mismo tiempo',
        resumen:
            'Colores y «otras variantes» (tallas, presentaciones) son dos listas independientes del mismo material: se pueden llenar las dos, en el formulario y en el Excel.',
        etiquetas: [
            'colores',
            'variantes',
            'otras variantes',
            'tallas',
            'maneja_color',
            'maneja_otra_variante',
            'material_variants',
            'las dos listas',
            'a la vez',
            'tipo color variante',
        ],
        actualizada: '2026-11-03',
        bloques: [
            {
                tipo: 'texto',
                texto:
                    'Cada variante se guarda en material_variants con su tipo: «color» o «variante». Como el tipo distingue las listas, un mismo material puede registrar Rojo, Azul y también S, M, L. Antes se obligaba a elegir una de las dos; esa restricción ya no existe.',
            },
            {
                tipo: 'lista',
                items: [
                    'En el formulario, escribir en una lista no borra la otra; al guardar, cada lista reemplaza por completo a las variantes de su tipo.',
                    'Los indicadores «maneja_color» y «maneja_otra_variante» los recalcula el servidor a partir de las listas, así nunca quedan desincronizados.',
                    'En el Excel, las columnas Color y Talla / tamaño se pueden llenar las dos en la misma fila.',
                    'El orden dentro de cada tipo es el índice de la lista (0, 1, 2…), que es lo que usan los editores de tallas de la OP.',
                    'Una lista vacía sí borra las variantes de ese tipo: si la fila no trae Color, el material se queda sin colores.',
                ],
            },
            {
                tipo: 'preguntas',
                pares: [
                    {
                        pregunta: '¿Y la columna legacy «todas las variantes»?',
                        respuesta:
                            'Solo se usa cuando la fila no trae Color ni Talla / tamaño; si no, se ignora para no registrar dos veces la misma lista.',
                    },
                    {
                        pregunta: '¿Qué error aparecía antes al subir las dos listas?',
                        respuesta:
                            '«Un material no puede tener colores y otras variantes al mismo tiempo». Ese tope se quitó; ahora cualquier fallo al guardar avisa además en qué fila del Excel ocurrió (Fila 7: …).',
                    },
                ],
            },
        ],
        fuentes: [
            'app/Models/Material.php (sincronizarVariantes: dos listas, dos tipos)',
            'app/Models/MaterialVariant.php (TIPO_COLOR / TIPO_VARIANTE)',
            'app/Http/Controllers/MaterialController.php (store, update y uploadExcel; el aviso por fila al insertar)',
            'resources/js/pages/Material/Create.vue y Edit.vue (las listas ya no se limpian entre sí)',
            'app/Services/PlantillasExcelService.php (columnas Color y Talla / tamaño del formato)',
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
