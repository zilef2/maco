return [

    // 1. ORDEN DE PRODUCCIÓN
    [
        'OrdenProduccion',
        'required' => [
            'numero_op' => 'string',
            'producto_referencia_id' => 'foreign',
            'cantidad_planificada' => 'number',
            'taller_id' => 'foreign',
            'estado_op_id' => 'foreign',
            'responsable_id' => 'foreign',
            'fecha_creacion' => 'datetime',
            'fecha_inicio_programada' => 'date',
            'fecha_prevista_envio_taller' => 'date',
            'fecha_entrega_estimada' => 'date',
        ],
        'optional' => [
            'id' => 'string',
            'shopify_product_id' => 'string',
            'observaciones' => 'text',
            'fecha_fin_real' => 'datetime',
        ],
    ],

    // 2. PROCESO EXTERNO
    [
        'ProcesoExterno',
        'required' => [
            'orden_produccion_id' => 'foreign',
            'etapa_componente' => 'string',
            'material_origen_id' => 'foreign',
            'cantidad_salida' => 'number',
            'unidad_medida' => 'string',
            'bodega_origen' => 'string',
            'proveedor_destino_id' => 'foreign',
            'servicio_proceso' => 'string',
            'fecha_salida' => 'datetime',
            'fecha_prevista_regreso' => 'date',
        ],
        'optional' => [
            'id' => 'string',
            'resultado_observaciones' => 'text',
            'fecha_regreso_real' => 'datetime',
        ],
    ],

    // 3. KIT TALLER
    [
        'KitTaller',
        'required' => [
            'orden_produccion_id' => 'foreign',
            'material_componente_id' => 'foreign',
            'estado_requerido' => 'string',
            'unidad_medida' => 'string',
            'cantidad_requerida' => 'number',
            'cantidad_disponible' => 'number',
            'cantidad_preparada' => 'number',
            'cantidad_faltante' => 'number',
            'esta_listo' => 'boolean',
        ],
        'optional' => [
            'id' => 'string',
            'observaciones' => 'text',
            'fecha_inicio_alistamiento' => 'datetime',
            'fecha_completado' => 'datetime',
        ],
    ],

    // 4. RECEPCIÓN CALIDAD
    [
        'RecepcionCalidad',
        'required' => [
            'orden_produccion_id' => 'foreign',
            'producto_referencia_id' => 'foreign',
            'talla' => 'string',
            'cantidad_esperada' => 'number',
            'cantidad_recibida' => 'number',
            'cantidad_aprobada' => 'number',
            'cantidad_en_reparacion' => 'number',
            'cantidad_no_recuperable' => 'number',
            'fecha_recepcion' => 'datetime',
        ],
        'optional' => [
            'id' => 'string',
            'observaciones' => 'text',
            'fecha_inicio_revision' => 'datetime',
            'fecha_fin_revision' => 'datetime',
        ],
    ],

    // 5. CIERRE DE ORDEN DE PRODUCCIÓN
    [
        'CierreOrdenProduccion',
        'required' => [
            'orden_produccion_id' => 'foreign',
            'control_cierre' => 'string',
            'dato_esperado' => 'number',
            'dato_real' => 'number',
            'diferencia' => 'number',
            'estado_accion' => 'string',
            'responsable_id' => 'foreign',
            'fecha_cierre' => 'datetime',
        ],
        'optional' => [
            'id' => 'string',
            'observaciones' => 'text',
        ],
    ],

    // 6. ESTADO OPERACIÓN
    [
        'EstadoOperacion',
        'required' => [
            'estado_op' => 'string',
            'cuando_aplica' => 'text',
            'regla_clave' => 'text',
        ],
        'optional' => [
            'id' => 'string',
            'puede_pasar_a' => 'json',
            'fecha_creacion' => 'datetime',
        ],
    ],

    // 7. FICHA TÉCNICA MATERIALES (BOM)
    [
        'FichaTecnicaMateriales',
        'required' => [
            'producto_referencia_id' => 'foreign',
            'material_componente_id' => 'foreign',
            'aporta' => 'string',
            'estado_al_llegar_ensamble' => 'string',
            'unidad_medida' => 'string',
            'consumo_estandar' => 'number',
            'costo_asociado' => 'dinero',
            'fecha_inicio_vigencia' => 'date',
        ],
        'optional' => [
            'id' => 'string',
            'proceso_previo' => 'string',
            'observaciones' => 'text',
            'fecha_fin_vigencia' => 'date',
        ],
    ],

    // 8. PENDIENTES PRODUCCIÓN
    [
        'PendientesProduccion',
        'required' => [
            'orden_produccion_id' => 'foreign',
            'prioridad' => 'string',
            'pendiente' => 'text',
            'donde_se_usa' => 'string',
            'por_definir' => 'boolean',
            'fecha_registro' => 'datetime',
        ],
        'optional' => [
            'id' => 'string',
            'fecha_estimada_resolucion' => 'date',
            'fecha_resolucion_real' => 'datetime',
        ],
    ],

];