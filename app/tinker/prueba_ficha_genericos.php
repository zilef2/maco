// Prueba: la ficha solo admite genéricos. Todo en transacción con rollback.

DB::beginTransaction();

try {
    $Material = 'App\Models\Material';
    $Producto = 'App\Models\Producto';
    $servicio = app('App\Services\FichaTecnicaService');

    $opciones = $servicio->opcionesMateriales()['materiales'];
    $conBandera = collect($opciones)->filter(fn ($o) => array_key_exists('generico', $o))->count();
    $genericos = collect($opciones)->where('generico', true)->count();
    echo "Opciones: {$conBandera}/" . count($opciones) . " con bandera generico; genericos actuales: {$genericos}" . PHP_EOL;

    // Genérico sintético
    $gen = $Material::create([
        'codigo_categoria' => 'TST',
        'codigo_base' => 'FIC',
        'categoria' => 'Textil prueba',
        'nombre_base' => 'Prueba Ficha (Genérico)',
        'unidad_inventario' => 'Metros',
        'estado' => 'Activo',
        'generico' => true,
    ]);
    $gen->prices()->create(['precio' => 5000, 'fecha' => '2026-11-01']);

    // Material específico sintético
    $esp = $Material::create([
        'codigo_categoria' => 'TST',
        'codigo_base' => 'FIC',
        'categoria' => 'Textil prueba',
        'nombre_base' => 'Prueba Específico',
        'unidad_inventario' => 'Metros',
        'estado' => 'Activo',
    ]);
    $esp->prices()->create(['precio' => 7000, 'fecha' => '2026-11-01']);

    $producto = $Producto::create([
        'ID_producto_Shopify' => 'prueba-ficha-gen-' . time(),
        'Titulo_Shopify' => 'Producto prueba ficha genéricos',
        'Codigo_interno' => 'PRUEBA-FICHA-GEN',
        'Categoria_original' => 'Prueba',
        'Familia_normalizada' => 'Prueba',
        'Control_de_variante' => 'Sin talla',
        'Opciones' => '',
        'Estado' => '1',
    ]);
    echo 'Producto de prueba: #' . $producto->id . ' ' . $producto->Titulo_Shopify . PHP_EOL;

    // 1) Ficha con material específico → debe rechazar
    try {
        $servicio->guardar([
            'nombre' => 'Ficha prueba específico',
            'productos' => [$producto->id],
            'materiales' => [[
                'material_componente_id' => $esp->id,
                'consumo_estandar' => 2,
            ]],
        ]);
        echo 'FALLO: se guardó con material específico' . PHP_EOL;
    } catch (Illuminate\Validation\ValidationException $e) {
        $errores = $e->errors();
        $clave = isset($errores['materiales']) ? 'materiales' : array_key_first($errores);
        echo "OK específico rechazado [{$clave}]: " . $errores[$clave][0] . PHP_EOL;
    }

    // 2) Ficha con genérico → debe guardar
    $ficha = $servicio->guardar([
        'nombre' => 'Ficha prueba genérico',
        'productos' => [$producto->id],
        'materiales' => [[
            'material_componente_id' => $gen->id,
            'consumo_estandar' => 2,
        ]],
    ]);
    $linea = $ficha->materiales()->first();
    echo 'OK genérico guardado: ficha #' . $ficha->id . ' linea material=' . $linea->material_componente_id
        . ' consumo=' . $linea->consumo_estandar . ' costo_teorico=' . $linea->costo_asociado_teorico
        . ' unidad=' . $linea->unidad_medida . PHP_EOL;

    // 3) El genérico sigue sin aceptar variantes
    $gen->sincronizarVariantes(['Rojo'], ['S']);
    $gen->refresh();
    echo 'Variantes del genérico tras intento: ' . $gen->variants()->count() . PHP_EOL;
} catch (\Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
} finally {
    DB::rollBack();
    echo 'Rollback hecho.' . PHP_EOL;
}
