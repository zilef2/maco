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
        titulo: 'Flujo de estados y edición de una OP',
        resumen:
            'Una OP recorre 7 estados; el número, tipo y responsable nunca cambian, y el plan (productos, tallas) se congela al entrar a «En taller» porque el kit ya descontó inventario.',
        etiquetas: [
            'estado op',
            'flujo de producción',
            'editar orden de producción',
            'campos bloqueados',
            'en taller',
            'cerrada',
            'máquina de estados',
        ],
        actualizada: '2026-11-06',
        bloques: [
            {
                tipo: 'pasos',
                items: [
                    'Borrador → Preparación → Esperando materiales → Lista para taller → En taller → Recibiendo → Cerrada.',
                    'Antes de «En taller» el plan está abierto (productos, tallas, taller, fecha de envío). Después se congela.',
                    'Cada OP tiene un solo estado: ya no hay estados simultáneos ni estado de «Procesos externos».',
                    'La OP se cierra sola cuando la recepción queda completa y no hay pares en reparación.',
                ],
            },
            {
                tipo: 'nota',
                tono: 'alerta',
                texto:
                    'Al pasar a «En taller» el kit descuenta inventario. Si necesita corregir algo, retroceda la OP a un estado anterior, edite y vuelva a avanzar.',
            },
            {
                tipo: 'lista',
                items: [
                    'Campos fijos siempre: número OP, tipo (taller/externa), responsable.',
                    'Campos editables hasta «En taller»: productos, tallas, taller, fecha de envío.',
                    'Campos editables siempre: ID Shopify, observaciones.',
                    'Las transiciones permitidas salen de la columna puede_pasar_a en estado_operacions.',
                ],
            },
        ],
        fuentes: [
            'resources/js/composables/flujoEstadosOp.ts',
            'resources/js/pages/OrdenProduccion/Edit.vue',
            'resources/js/pages/OrdenProduccion/CambiarEstadoModal.vue',
            'app/Http/Controllers/OrdenProduccionController.php (update, cambiarEstado)',
        ],
    },
    {
        id: 'op-cola-esperando-materiales',
        categoria: 'OP',
        categoriaLabel: 'Órdenes de producción',
        subcategoria: 'Estado de la OP',
        titulo: 'Cola de «Esperando materiales» y salto a «Lista para taller»',
        resumen:
            'Las OP con faltantes esperan en una cola por orden de llegada y pasan solas a «Lista para taller» cuando una compra completa su kit; nadie puede saltar a ese estado con materiales incompletos.',
        etiquetas: [
            'esperando materiales',
            'lista para taller',
            'cola',
            'fifo',
            'turno',
            'faltan materiales',
            'kit completo',
            'registrar compra',
            'promoción automática',
        ],
        actualizada: '2026-11-15',
        bloques: [
            {
                tipo: 'texto',
                texto:
                    '«Esperando materiales» es una lista de espera: la primera OP que entró es la primera que sale. El turno se ordena por la fecha y hora en que la OP entró al estado (esperando_materiales_desde) y, si dos coinciden, por el id. Al salir del estado esa fecha se borra; si la OP vuelve a entrar, empieza un turno nuevo.',
            },
            {
                tipo: 'subtitulo',
                texto: 'Cómo se llega a «Lista para taller»',
            },
            {
                tipo: 'lista',
                items: [
                    'Botón «Pasar a lista para taller» al crear la OP: registra la orden y la deja directamente en ese estado, sin pasar por Preparación. Solo se habilita cuando la existencia actual de cada material del kit alcanza para la cantidad producida; con faltantes el botón queda deshabilitado y el aviso de abajo dice cuánto falta de cada uno.',
                    'Sugerencia al elegir «Esperando materiales»: si el kit ya está completo, el modal ofrece pasar directamente a «Lista para taller» en lugar de entrar a la cola.',
                    'Promoción automática al registrar una compra en Materiales: se recorre la cola de la más antigua a la más nueva y cada OP cuyo kit quede completo pasa sola a «Lista para taller».',
                    'Manualmente, solo con el kit completo: si falta algún material, el cambio se rechaza y el detalle dice cuánto falta de cada uno.',
                ],
            },
            {
                tipo: 'nota',
                tono: 'info',
                texto:
                    'Si la compra no alcanza para todas las OP en cola, el stock se reserva en orden: se descuenta mentalmente lo que consume la OP promovida antes de evaluar la siguiente. Así dos OP nunca quedan «listas» peleando por el mismo material.',
            },
            {
                tipo: 'subtitulo',
                texto: 'Cuándo se descuenta el inventario',
            },
            {
                tipo: 'texto',
                texto:
                    'Estar en «Lista para taller» no descuenta nada: el material sigue en bodega. El único descuento ocurre al entrar a «En taller», y aplica a todas las OP, incluidas las externas. En ese momento se resta la cantidad requerida de cada material del kit y queda un movimiento de salida en el histórico.',
            },
            {
                tipo: 'nota',
                tono: 'alerta',
                texto:
                    'Antes existía la acción «Sacar materiales al taller», que descontaba el kit y luego «En taller» lo volvía a descontar. Esa acción se retiró: el descuento es uno solo.',
            },
        ],
        fuentes: [
            'app/Services/ColaMaterialesOpService.php',
            'app/Http/Controllers/MaterialController.php (registrarCompra)',
            'app/Http/Controllers/OrdenProduccionController.php (cambiarEstado, descontarMaterialesTaller)',
            'app/Http/Controllers/OrdenProduccionController.php (saltarALista, llamada desde store y update con pasar_a_lista)',
            'resources/js/pages/OrdenProduccion/Create.vue (botón «Pasar a lista para taller»)',
            'resources/js/pages/OrdenProduccion/CambiarEstadoModal.vue',
            'database/migrations/2026_11_06_000000_reestructura_estados_op.php',
        ],
    },
    {
        id: 'op-recepcion-y-cierre-automatico',
        categoria: 'OP',
        categoriaLabel: 'Órdenes de producción',
        subcategoria: 'Estado de la OP',
        titulo: 'Recepción de calidad y cierre automático',
        resumen:
            'En «Recibiendo» se registra por talla: aprobada (entra a inventario), en reparación (compromiso con avisos) y no recuperable (merma). La OP se cierra sola cuando todo cuadra.',
        etiquetas: [
            'recepción de calidad',
            'recibiendo',
            'cierre automático',
            'compromiso de reparación',
            'no recuperable',
            'merma',
        ],
        actualizada: '2026-10-23',
        bloques: [
            {
                tipo: 'lista',
                items: [
                    'Aprobada: entra al inventario disponible.',
                    'En reparación: vuelve al taller; se registra una fecha de compromiso pendiente con avisos (2 días antes y el mismo día).',
                    'No recuperable: sale de la cuenta y genera un registro de merma.',
                    'La suma de las tres debe igualar la cantidad esperada de la talla.',
                ],
            },
            {
                tipo: 'nota',
                tono: 'info',
                texto:
                    'La OP se cierra automáticamente al guardar cuando todas las tallas cuadran y no hay pares en reparación. «Guardar avance» permite registrar parcialmente sin exigir el cuadre.',
            },
        ],
        fuentes: [
            'app/Http/Controllers/OrdenProduccionController.php (cambiarEstado)',
            'app/Models/CompromisoReparacion.php',
            'resources/js/pages/OrdenProduccion/CambiarEstadoModal.vue',
        ],
    },
    {
        id: 'op-merma-desde-no-recuperable',
        categoria: 'OP',
        categoriaLabel: 'Órdenes de producción',
        subcategoria: 'Merma',
        titulo: 'La merma nace del «No recuperable»',
        resumen:
            'Cada talla con pares «No recuperable» en la recepción deja un registro de merma automático. La pantalla /Merma es de solo lectura.',
        etiquetas: ['merma', 'no recuperable', 'pares perdidos', 'valor perdido'],
        actualizada: '2026-11-03',
        bloques: [
            {
                tipo: 'lista',
                items: [
                    'Al guardar la recepción, cada talla con «No recuperable» deja un registro de merma (llave única por recepción, no duplica).',
                    'Re-guardar ajusta el registro; si queda en cero, se borra.',
                    'El valor perdido = piezas × costo unitario del producto según la ficha técnica.',
                    '/Merma totaliza por mes y periodo (30d, 60d, todo). No se edita ni se agrega desde ahí; se corrige desde la OP.',
                ],
            },
        ],
        fuentes: [
            'app/Services/MermaRecepcionService.php',
            'app/Http/Controllers/MermaController.php',
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
            'ficha de Producto costo',
            'costo_asociado_teorico',
            'material prices',
            'porque cambio el costo',
            'parametro de costos',
        ],
        actualizada: '2026-11-11',
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
                    {
                        pregunta: '¿Dónde veo el histórico de precios de un material?',
                        respuesta:
                            'En Materiales → «Editar material», debajo de «Precio de última compra»: la lista completa de material_prices con su fecha, la marca «último» en la primera fila y el promedio de todas. En el listado de Materiales, la columna «Último precio» muestra el vigente y los anteriores con su fecha.',
                    },
                    {
                        pregunta: '¿Y en el producto se ven los dos costos?',
                        respuesta:
                            'Sí: «Nuevo producto» y «Editar producto» muestran el coste con promedio y el coste con último precio a la vez. Los dos quedan guardados en productos.coste_produccion_promedio y productos.coste_produccion_ultimo; el que está vigente según el parámetro es el que alimenta el precio de venta y la ganancia.',
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
            'resources/js/pages/Material/Edit.vue (histórico de precios) y resources/js/pages/Material/Index.vue',
        ],
    },
    {
        id: 'producto-coste-produccion-ganancia',
        categoria: 'Producto',
        categoriaLabel: 'Productos',
        subcategoria: 'Costos',
        titulo: 'Coste producción, precio venta automático y ganancia',
        resumen:
            'El coste producción se guarda con los dos métodos de precio (promedio y último precio del material) y se ve a la vez en el formulario; el precio venta es un 300% del coste vigente redondeado al millar superior y la ganancia es la diferencia. El coste vigente y el precio venta aparecen como columnas en el listado de Productos.',
        etiquetas: [
            'coste produccion',
            'coste promedio',
            'coste ultimo precio',
            'precio venta automatico',
            '300 por ciento',
            'redondeo al millar',
            'ganancia producto',
            'costo anticipado',
            'diferencia precio costo',
            'margen del producto',
            'costo por unidad',
        ],
        actualizada: '2026-11-11',
        bloques: [
            {
                tipo: 'tabla',
                columnas: ['Campo', '¿Quién lo escribe?', 'Qué es'],
                filas: [
                    ['Coste · promedio', 'Nadie: se deriva solo', 'Materiales de la ficha al promedio de los precios registrados del material (material_prices) × consumo estándar, + procesos, por unidad.'],
                    ['Coste · último precio', 'Nadie: se deriva solo', 'Los mismos materiales al precio de su compra más reciente, + procesos, por unidad.'],
                    ['Coste producción', 'Nadie: se deriva solo', 'El de los dos anteriores que esté vigente según el parámetro costos_promedio. Es el que alimenta el precio venta y la ganancia.'],
                    ['Precio venta', 'Nadie: se deriva solo', 'Un 300% del coste vigente, redondeado hacia arriba al millar más cercano. Ya no se digita.'],
                    ['Ganancia', 'Nadie: se deriva solo', 'Precio venta − coste vigente, en dinero y como porcentaje del coste; con la regla del 300% queda alrededor del doble del coste.'],
                    ['Costo anticipado', 'Nadie: el campo está oculto', 'Se digitaba a mano para especular. Sigue guardado en la BD y se muestra debajo del coste en el listado.'],
                ],
            },
            {
                tipo: 'lista',
                items: [
                    'En «Nuevo producto» y «Editar producto» se ven los dos costes a la vez, como cajas de solo lectura: el del método vigente lleva la marca «Vigente» y el otro «Referencia». Debajo van el precio venta («300% del coste») y la ganancia con su porcentaje.',
                    'Los dos costes se guardan siempre, sin depender del parámetro: productos.coste_produccion_promedio y productos.coste_produccion_ultimo. El canónico (coste_produccion) es una copia del vigente.',
                    'La regla del precio vive en un solo lugar del backend (CostoMaterialOpService::MULTIPLICADOR_PRECIO_VENTA, REDONDEO_PRECIO_VENTA y precioVentaAutomatico()) y los dos formularios aplican exactamente la misma cuenta, para que lo que se vea sea lo que se guarda.',
                    'Los cinco campos se escriben al crear o editar el producto y cada vez que una OP que usa el producto recalcula su coste.',
                    'El coste canónico de una OP usa el precio congelado en esa orden; los dos costes por método se recalculan con los precios actuales del material, porque son la referencia del producto.',
                    'Mientras un material tenga un solo precio registrado, sus dos costes coinciden; empiezan a diferir en cuanto se registra otra compra.',
                ],
            },
            {
                tipo: 'subtitulo',
                texto: 'En el listado de Productos',
            },
            {
                tipo: 'lista',
                items: [
                    'La columna «Coste» muestra el coste vivo (con el método vigente del parámetro costos_promedio) y debajo el costo anticipado.',
                    'La columna «Precio venta» muestra el precio automático y debajo la ganancia en dinero.',
                    'Un producto sin ficha ni procesos queda con coste 0; en ese caso se conserva el precio venta histórico que tuviera guardado.',
                    'Ordenar o filtrar por esas columnas usa exactamente el valor que se muestra en la celda.',
                ],
            },
        ],
        fuentes: [
            'app/Services/CostoMaterialOpService.php (MULTIPLICADOR_PRECIO_VENTA, REDONDEO_PRECIO_VENTA, precioVentaAutomatico, costeProduccionPorMetodo, actualizarCosteProduccion)',
            'app/Http/Controllers/ProductoController.php (sincronizarCosteProduccionSugerido, index: coste_produccion_promedio_vivo, coste_produccion_ultimo_vivo, metodo_costos)',
            'app/Services/FichaTecnicaService.php (opcionesMateriales: costo_unitario y costo_unitario_promedio)',
            'tablas productos.coste_produccion, coste_produccion_promedio, coste_produccion_ultimo, precio_venta_producto y ganancia_producto',
            'tabla material_prices (histórico visible en Material/Edit.vue)',
            'resources/js/pages/Producto/Create.vue, Edit.vue e Index.vue',
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
                    'Si el material con proceso ya está encargado como resultado de una OP activa, la alerta informa que llegará con esa OP.',
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
            'app/Http/Controllers/MaterialController.php (prevision: gasto diario, días restantes y OPs que producen el material)',
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
            'tabla proveedores_maco (nit, nombre_comercial, razon_social, codigo, nombre)',
        ],
    },
    {
        id: 'material-columna-procesado-del-excel',
        categoria: 'Material',
        categoriaLabel: 'Materiales',
        subcategoria: 'Importar Excel',
        titulo: 'La columna «Procesado» va obligatoriamente al final del bloque de «Costo»',
        resumen:
            'El Excel de materiales debe traer la columna «Procesado» (si o no) inmediatamente después de «Costo», o después de «Cantidad» cuando el archivo la trae; sin ese orden la subida se cancela antes de leer una sola fila.',
        etiquetas: [
            'procesado',
            'material procesado',
            'columna costo',
            'columna cantidad',
            'orden de columnas',
            'si o no',
            'importar excel',
            'plantilla materiales',
        ],
        actualizada: '2026-11-04',
        bloques: [
            {
                tipo: 'texto',
                texto:
                    '«Procesado» alimenta material_procesado (0 o 1), el dato que decide si el material entra con ficha técnica propia al recibirlo en una OP. Es columna obligatoria del formato y solo se acepta cerrando el bloque económico del archivo —«Costo» y, si la usa, «Cantidad»—, que es el orden de la plantilla descargable.',
            },
            {
                tipo: 'pasos',
                items: [
                    'Se leen los títulos de la fila 1 ignorando mayúsculas, tildes y puntuación: «Costo», «Costo material» o «Procesado (si/no)» se reconocen igual.',
                    'Falta «Procesado»: el aviso de columnas requeridas lista la que faltó y no se importa nada.',
                    '«Procesado» existe pero en otra columna: el aviso dice en qué letra debía estar (la siguiente a «Costo», o a «Cantidad» si esa columna va entre las dos).',
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
                            'Porque el archivo se lee por posición, no por rótulos sueltos: exigir «Procesado» al cierre del bloque de «Costo» mantiene la subida alineada con la plantilla que descarga la app y evita costos sin su bandera de procesado.',
                    },
                    {
                        pregunta: '¿Sirve «Es producido», como en archivos antiguos?',
                        respuesta:
                            'No. Ese rótulo ya no se reconoce; la columna se llama «Procesado», igual que en el listado y en la ficha del material.',
                    },
                    {
                        pregunta: '¿Y si mi archivo no tiene la columna «Cantidad»?',
                        respuesta:
                            'Funciona igual: «Cantidad» es opcional. Sin ella, «Procesado» va pegada a «Costo» y la existencia actual del material no se toca.',
                    },
                ],
            },
        ],
        fuentes: [
            'app/Http/Controllers/MaterialController.php (uploadExcel: columnas requeridas, chequeo de posición y validación si/no)',
            'app/Services/PlantillasExcelService.php (columnas Costo, Cantidad y Procesado del formato de materiales)',
            'resources/js/pages/Material/ImportarMaterialModal.vue (ayuda visible al subir)',
            'materials.material_procesado',
        ],
    },
    {
        id: 'material-excel-costo-es-precio-y-cantidad-es-existencia',
        categoria: 'Material',
        categoriaLabel: 'Materiales',
        subcategoria: 'Importar Excel',
        titulo: 'En el Excel «Costo» es el Precio y «Cantidad» es la Existencia actual',
        resumen:
            'La columna «Costo» del archivo se guarda en el histórico de precios del material (el Precio que muestra la app), y «Cantidad» fija la existencia actual y queda anotada en el histórico de movimientos.',
        etiquetas: [
            'columna costo',
            'columna cantidad',
            'precio del material',
            'existencia actual',
            'historico de precios',
            'material_prices',
            'importar excel',
        ],
        actualizada: '2026-11-04',
        bloques: [
            {
                tipo: 'texto',
                texto:
                    'El Excel no tiene campos propios: «Costo» se lee como el Precio de la app y se anota en material_prices con la fecha del día, la misma tabla que alimenta el histórico, el promedio y el costo de las OPs abiertas. «Cantidad» es la Existencia actual del material (materials.existencia_actual).',
            },
            {
                tipo: 'pasos',
                items: [
                    '«Costo» con número ⇒ se crea un precio con la fecha de hoy; si el material ya tiene un precio anotado hoy, esa fila se reemplaza en vez de duplicarse.',
                    'El cambio de precio renueva el costo de las OPs abiertas que usan ese material, igual que al editar el material desde el formulario.',
                    '«Cantidad» con número ⇒ reemplaza la existencia actual y deja el movimiento correspondiente en el histórico («Ajuste de inventario desde Excel», o «Existencia inicial desde Excel» cuando el material se acaba de crear).',
                    'Cualquiera de las dos en blanco ⇒ no toca lo que ya tiene el material: la subida no borra precios ni existencias por escribir una celda vacía.',
                    '«Costo» o «Cantidad» negativas ⇒ se cancela la subida y el aviso dice la fila exacta (Fila 7: …).',
                ],
            },
            {
                tipo: 'preguntas',
                pares: [
                    {
                        pregunta: '¿Por qué «Costo» se guarda como Precio?',
                        respuesta:
                            'Porque en la app el valor unitario de un material es el Precio: de ahí salen el promedio, el último precio del listado y el costo de materiales de las OPs. Guardarlo en un campo aparte dejaba el Excel sin efecto sobre los reportes.',
                    },
                    {
                        pregunta: '¿«Cantidad» suma al inventario o lo reemplaza?',
                        respuesta:
                            'Lo reemplaza: la columna es la existencia actual tal como se ve en la app. Para subir compras y que se sumen se usa el registro de compras de Materiales, no el Excel de materiales.',
                    },
                    {
                        pregunta: '¿Se puede escribir «Precio» en vez de «Costo»? ¿Y «Existencia» en vez de «Cantidad»?',
                        respuesta:
                            'Sí. Los rótulos se reconocen ignorando mayúsculas, tildes y puntuación, así que «Costo», «Costo material» y «Precio» son la misma columna; «Cantidad», «Existencia» y «Existencia actual» son la misma.',
                    },
                ],
            },
        ],
        fuentes: [
            'app/Http/Controllers/MaterialController.php (uploadExcel: alias de columnas, registrarPrecioImportado y movimientos de existencia)',
            'app/Services/PlantillasExcelService.php (columnas Costo y Cantidad del formato de materiales)',
            'app/Traits/MaterialMovimientoTrait.php (registrarMovimientoActualizacion / registrarMovimientoCreacion)',
            'tabla material_prices, materials.existencia_actual',
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
    {
        id: 'material-generico',
        categoria: 'Material',
        categoriaLabel: 'Materiales',
        subcategoria: 'Material genérico',
        titulo: 'El material genérico: uno por cada par categoría-base',
        resumen:
            'Un material genérico representa a todos los que comparten su código de categoría y su código base: se codifica con el 0 del par (COM-CAP-0), no maneja talla ni color, no tiene existencia ni gasto diario y su precio es el promedio de los precios de sus similares. La ficha técnica lo usa para declarar el consumo del par, pero el material que se gasta (con su color o talla) se elige en la OP.',
        etiquetas: [
            'genérico',
            'generico',
            'material genérico',
            'promedio de precios',
            'categoria base',
            'CAT-BAS-0',
            'TEX-LFL-0',
            'sin talla',
            'sin color',
            'sin existencia',
            'proyectado',
            'gasto diario',
            'material concreto',
            'familia',
            'color o talla en la OP',
            'botón superadmin',
            'crear genéricos',
        ],
        actualizada: '2026-11-14',
        bloques: [
            {
                tipo: 'texto',
                texto:
                    'Si existen TEX-LFL-1 a TEX-LFL-5 (Lona Florentina) y TEX-LYC-1 (Lycra), solo hacen falta dos genéricos: uno para el par TEX-LFL y otro para TEX-LYC. El genérico no usa el consecutivo del par: siempre lleva el 0 (TEX-LFL-0 y TEX-LYC-0), porque debe haber uno solo por categoría y nombre base. El nombre base lleva el sufijo «(Genérico)» para distinguirlo en los selects.',
            },
            {
                tipo: 'lista',
                items: [
                    'La columna «generico» de la tabla materials marca el material; el modelo obliga a que un genérico no tenga talla ni color: limpia maneja_color, maneja_talla, maneja_otra_variante, color, talla_tamaño y borra sus variantes al guardar, venga de donde venga la escritura.',
                    'La misma escritura del modelo fuerza el código CAT-BAS-0 del par y rechaza un segundo genérico para ese par, así que ni el formulario, ni el Excel ni el botón pueden duplicarlo.',
                    'El precio se calcula en dos pasos: primero el promedio de las filas de precio de cada similar y luego el promedio entre los similares que sí tienen precio. El resultado se guarda como una fila nueva en material_prices con la fecha de hoy.',
                    'Si un grupo no tiene ningún similar con precio, el genérico se crea igual pero se queda sin fila de precio.',
                    'El botón vive en Actividad → Acciones de Superadministrador, al lado de «Existencias y precios al azar», y no requiere entorno de prueba.',
                    'El genérico no almacena nada: la misma escritura del modelo deja en null su existencia actual y su gasto diario, y el recálculo del gasto diario de todos empieza limpiando los genéricos. Por eso tampoco aparece en la proyección de la tabla de Materiales, ni en la previsión de faltantes, y una compra rechazada avisa que se compre el concreto de su familia.',
                ],
            },
            {
                tipo: 'pasos',
                items: [
                    'Abrir Actividad (solo superadmin) y bajar hasta «Acciones de Superadministrador».',
                    'En la tarjeta «Materiales genéricos» revisar el detalle: cuántas combinaciones hay, cuántas están sin genérico y cuántos genéricos existen.',
                    'Pulsar «Crear los genéricos que faltan». Se puede pulsar las veces que sea: crea los que faltan con su código CAT-BAS-0, renumera a ese código a los genéricos creados con la regla antigua y, en los que ya existen, agrega una fila de precio únicamente si el promedio cambió.',
                ],
            },
            {
                tipo: 'nota',
                tono: 'info',
                texto:
                    'El genérico también se puede marcar a mano con el interruptor «Genérico» del formulario de material, que está al lado del código de categoría; al activarlo se le quitan tallas, colores, diseño, proveedor y precio propios, y el código pasa a ser el 0 del par.',
            },
            {
                tipo: 'subtitulo',
                texto: 'La ficha técnica solo admite genéricos',
            },
            {
                tipo: 'lista',
                items: [
                    'En la ficha de producto y de material procesado, el select de material lista únicamente los materiales genéricos; si una ficha antigua tiene un material específico, la fila conserva su nombre pero el servidor rechaza el guardado hasta reemplazarlo por el genérico de su par categoría-base.',
                    'El buscador del select funciona por código o por nombre base, sin distinguir mayúsculas ni tildes (escribir «lfl» o «Florentina» encuentra lo mismo).',
                    'Al dar Enter con el foco en el select de material se agrega otra fila y el foco vuelve al buscador, para encadenar materiales sin usar el ratón.',
                ],
            },
            {
                tipo: 'subtitulo',
                texto: 'La OP define el material concreto que se va a gastar',
            },
            {
                tipo: 'texto',
                texto:
                    'La ficha solo dice «se gasta COM-CAP-0» y cuánto se gasta por unidad producida, porque allí solo importa el consumo del par. El color o la talla reales se deciden al registrar la orden: la tarjeta «Material concreto de la orden» lista, por cada producto o material procesado de la OP, sus líneas genéricas y obliga a elegir un material de esa misma familia (COM-CAP-1, COM-CAP-2, …). Desde ese momento todo el sistema trabaja con el concreto: el kit de proceso, los compromisos con otras OP, los faltantes, la compra, el descuento al pasar a taller y el precio congelado de la orden.',
            },
            {
                tipo: 'lista',
                items: [
                    'Las opciones salen de los materiales activos de la misma familia (el par código_categoria-código_base) que no están marcados como genéricos; cada opción se muestra con su código y sus variantes (COM-CAP-17 · 23), que es donde vive el color o la talla.',
                    'La elección se guarda en orden_produccion_material_concreto, una fila por objetivo (producto o material producido) y línea genérica de su ficha, así que dos productos de la misma OP pueden gastar colores distintos de la misma familia.',
                    'El registro de la OP no se guarda sin todas las elecciones: el aviso dice qué genérico falta («Defina el material concreto (color o talla) de: COM-CAP-0 de …»). Un borrador guardado con «Terminar después» acepta la orden incompleta.',
                    'Si la familia solo tiene un material concreto, ese se elige solo. Si no tiene ninguno, la tarjeta lo advierte: hay que crear el material con su color o talla antes de registrar la OP.',
                    'Al editar, la elección viaja con el plan de la orden: desde «En taller» queda congelada junto con los productos y las tallas, porque el kit ya salió de bodega.',
                    'Las OPs antiguas no tienen elección guardada: el kit y los costos siguen usando el material que estaba en la ficha y la elección se completa al editar la orden.',
                ],
            },
            {
                tipo: 'pasos',
                items: [
                    'Abrir Orden de producción → Crear y seleccionar el producto (o el material procesado).',
                    'Bajar a «Material concreto de la orden» y elegir, en cada selector, el material del color o la talla que se va a usar.',
                    'Revisar «Materiales requeridos»: el físico, el proyectado y el faltante ya corresponden al material elegido, no al genérico.',
                    'Registrar la orden. Si falta alguna elección, el formulario devuelve el aviso con los genéricos pendientes.',
                ],
            },
        ],
        fuentes: [
            'database/migrations/2026_11_11_100000_add_generico_to_materials_table.php',
            'app/Models/Material.php (aplicarReglaDelCodigo con el 0 del par y descartarGenericoDuplicado; limpiarVariantesPorGenerico en saving; sincronizarVariantes la respeta)',
            'app/Services/MaterialesGenericosService.php (sincronizar crea, renumera a CAT-BAS-0 y promedia; estadisticas)',
            'app/Services/FichaTecnicaService.php (opcionesMateriales con bandera generico; guardar solo admite genéricos)',
            'resources/js/pages/FichaTecnicaMateriales/EditorMateriales.vue (select filtrado, búsqueda por código/nombre, Enter agrega fila)',
            'resources/js/pages/Material/Create.vue y Edit.vue (interruptor «Genérico» junto al código de categoría; el código previsualizado usa el 0 del par)',
            'app/Http/Controllers/SuperadminAccionesController.php (materialesGenericos)',
            'routes/web.php (POST /superadmin/materiales-genericos)',
            'resources/js/pages/actividad/AccionesSuperadmin.vue (tarjeta «Materiales genéricos»)',
            'database/migrations/2026_11_14_100000_generico_sin_inventario.php (existencia_actual nullable y null para los genéricos)',
            'app/Models/Material.php (recalcularGastoDiario y recalcularGastoDiarioDeTodos devuelven null en genéricos)',
            'app/Http/Controllers/MaterialController.php (index y prevision excluyen genéricos; registrarCompra los rechaza)',
            'resources/js/pages/Material/Index.vue (celdas de existencia, proyectado y gasto diario muestran «no aplica»)',
            'database/migrations/2026_11_14_110000_create_orden_produccion_material_concreto_table.php',
            'app/Models/OrdenProduccionMaterialConcreto.php (objetivo_tipo producto|material)',
            'app/Services/MaterialConcretoOpService.php (concretosPorFamilia, validar, guardar, mapaDeOrden, resolver, materialResuelto)',
            'app/Http/Controllers/OrdenProduccionController.php (obtenerSelects con materiales_familias; concretosDesdeRequest en store/guardarBorrador/update; necesidadMateriales resuelve el concreto)',
            'app/Services/CostoMaterialOpService.php y CostoRecepcionOpService.php (el snapshot de costos usa el id del concreto)',
            'resources/js/composables/materialesConcretosOp.ts (elecciones, opcionesDe, materialDeLinea, asegurarUnicos, payload)',
            'resources/js/pages/OrdenProduccion/Create.vue y Edit.vue (tarjeta «Material concreto de la orden»)',
        ],
    },
    {
        id: 'material-orden-de-compra',
        categoria: 'Material',
        categoriaLabel: 'Materiales',
        subcategoria: 'Orden de compra',
        titulo: 'La pantalla «Orden de compra»: cuánto falta y a cuánto está',
        resumen:
            'Una hoja de compra en pantalla completa que lista, por material, lo que hace falta para cubrir las OPs abiertas. Se puede ver como lista de marcas para el local o como detalle con el precio vigente editable; no registra compras, solo lee la necesidad, actualiza precios y deja imprimir.',
        etiquetas: [
            'orden de compra',
            'cuánto comprar',
            'faltantes',
            'hoja de compra',
            'lista de compras',
            'marcar',
            'imprimir',
            'pdf',
            'precio del material',
            'actualizar precio',
            'total estimado',
            'comprar faltantes',
        ],
        actualizada: '2026-11-15',
        bloques: [
            {
                tipo: 'texto',
                texto:
                    'La lista sale de comparar el kit de cada OP abierta contra la existencia actual del material: «Hay que comprar» es exactamente lo que piden las OPs menos lo que hay en bodega, sin redondeos, sin margen de seguridad y sin días de previsión. Es la misma cuenta que alimenta el botón «Comprar faltantes» de la barra superior, presentada como hoja de compra.',
            },
            {
                tipo: 'lista',
                items: [
                    'Entran las OPs cuyo estado aún no es «En taller», «Recibiendo», «Terminada» ni «Cerrada»; esas ya descontaron su kit del inventario.',
                    'Una fila por material, con la suma de lo que piden todas las OPs; al expandir la fila aparece cada OP con lo que exige.',
                    'El precio que se muestra es el vigente, o sea el de la compra más reciente del material (material_prices), con la fecha desde la que rige.',
                    'Guardar precios crea una fila nueva de material_prices con la fecha de hoy para cada material cuyo número cambió; un precio igual al vigente no se guarda y ninguna fila anterior se borra.',
                    'Un material sin precio se ve en ámbar y no entra al total de la orden; el botón «Sin precio» de la barra de filtros deja a la vista únicamente esos materiales.',
                    'La orden se puede ordenar por faltante o por importe, y en «Detalle» los materiales que todavía no tienen precio van siempre primero, porque son los únicos que dejan el total incompleto.',
                    'La pantalla tiene dos organizaciones. «Lista» es la hoja de compra: un casillero por material con el nombre, la categoría, la cantidad y el código en gris, sin precios. «Detalle» es la tabla con bodega, precio e importe. La elección queda guardada en el dispositivo.',
                    'En «Lista», tocar la fila marca el material como ya conseguido: el casillero se llena, el nombre se tacha y la fila baja al final de su categoría. Las marcas son locales (se guardan en el navegador de quien compra), no van a la base ni se comparten con otros equipos; «Limpiar marcas» las borra todas.',
                    '«Imprimir / PDF» abre en otra pestaña /Material/orden-compra/imprimir: la misma orden, agrupada por categoría y con un casillero vacío por material, sin barra lateral ni menús. Ahí se puede activar «Con precios» para que entren el precio vigente, el importe y el total estimado.',
                ],
            },
            {
                tipo: 'pasos',
                items: [
                    'Pulsar «Orden de compra» en la barra superior (aparece junto a «Comprar faltantes» mientras haya carencias en las OPs abiertas).',
                    'Revisar o escribir los precios que cambiaron; el total del pie se recalcula mientras se escribe.',
                    'Pulsar «Guardar precios» para dejarlos en el histórico, o «Descartar» para devolverlos al precio vigente.',
                    'Para comprar en el local: dejar la pantalla en «Lista» e ir tocando cada material a medida que se consigue, o imprimir la versión en papel desde «Imprimir / PDF».',
                ],
            },
            {
                tipo: 'nota',
                tono: 'info',
                texto:
                    'Acá no se da ninguna entrada de inventario. Para registrar que la compra ya llegó y hacer que las OPs de la cola pasen a «Lista para taller» se usa «Comprar faltantes», que abre el formulario de compra de Materiales.',
            },
        ],
        fuentes: [
            'app/Services/FaltantesOpService.php (carencias, ordenCompra con precio vigente e importe)',
            'app/Http/Controllers/MaterialController.php (ordenCompra, guardarPreciosOrdenCompra)',
            'routes/web.php (GET /Material/orden-compra, GET /Material/orden-compra/imprimir y POST /Material/orden-compra/precios)',
            'resources/js/pages/Material/OrdenCompra.vue (hoja de compra full-screen, modos Lista y Detalle)',
            'resources/js/pages/Material/OrdenCompraImprimible.vue (versión para papel o PDF)',
            'resources/js/components/OrdenCompraButton.vue (acceso desde la barra superior)',
            'app/Models/Material.php (costoUnitarioActual: la compra más reciente es el precio vigente)',
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
