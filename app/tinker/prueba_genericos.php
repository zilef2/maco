// Prueba sintética del servicio de genéricos: todo dentro de una transacción
// que al final se revierte. No toca datos reales de forma permanente.

$Material = 'App\Models\Material';
$MGS = 'App\Services\MaterialesGenericosService';

DB::beginTransaction();

try {
    echo 'Materiales actuales: ' . $Material::count() . PHP_EOL;
    echo 'Estadísticas antes: ' . json_encode($MGS::estadisticas()) . PHP_EOL;

    // Limpiar genéricos previos (si existen) para una prueba limpia del grupo sintético.
    $m1 = $Material::create([
        'codigo_categoria' => 'TST',
        'codigo_base' => 'PRB',
        'categoria' => 'Textil prueba',
        'nombre_base' => 'Prueba Uno',
        'unidad_inventario' => 'Metros',
        'estado' => 'Activo',
    ]);
    $m1->prices()->create(['precio' => 8000, 'fecha' => '2026-10-01']);
    $m1->prices()->create(['precio' => 12000, 'fecha' => '2026-10-05']);
    // m1 con variantes para probar que el genérico no las hereda ni las permite
    $m1->sincronizarVariantes(['Rojo', 'Azul'], ['S', 'M']);

    $m2 = $Material::create([
        'codigo_categoria' => 'TST',
        'codigo_base' => 'PRB',
        'categoria' => 'Textil prueba',
        'nombre_base' => 'Prueba Dos',
        'unidad_inventario' => 'Metros',
        'estado' => 'Activo',
    ]);
    $m2->prices()->create(['precio' => 20000, 'fecha' => '2026-10-02']);
    $m2->prices()->create(['precio' => 24000, 'fecha' => '2026-10-06']);

    $m3 = $Material::create([
        'codigo_categoria' => 'TST',
        'codigo_base' => 'OTR',
        'categoria' => 'Textil prueba',
        'nombre_base' => 'Otra base sin precios',
        'unidad_inventario' => 'Unidad',
        'estado' => 'Activo',
    ]);

    $servicio = app($MGS);
    $r1 = $servicio->sincronizar();
    echo 'Primera corrida: ' . json_encode($r1) . PHP_EOL;

    $g1 = $Material::where('codigo_material', 'like', 'TST-PRB-%')->where('generico', true)->first();
    $g2 = $Material::where('codigo_material', 'like', 'TST-OTR-%')->where('generico', true)->first();
    echo 'Genérico TST-PRB: ' . ($g1 ? $g1->codigo_material . ' | ' . $g1->nombre_base . ' | precio=' . $g1->costoUnitarioActual() : 'NO CREADO') . PHP_EOL;
    echo '  maneja_color=' . var_export((bool) $g1->maneja_color, true) . ' color=' . var_export($g1->color, true) . ' variantes=' . $g1->variants()->count() . PHP_EOL;
    echo 'Genérico TST-OTR: ' . ($g2 ? $g2->codigo_material . ' | ' . $g2->nombre_base . ' | precio=' . $g2->costoUnitarioActual() : 'NO CREADO') . PHP_EOL;
    echo 'Movimiento del genérico: ' . $g1->movimientos()->count() . ' ("' . $g1->movimientos()->first()->motivo . '")' . PHP_EOL;

    $r2 = $servicio->sincronizar();
    echo 'Segunda corrida (idempotente): ' . json_encode($r2) . PHP_EOL;
    echo 'Total genéricos TST: ' . $Material::where('generico', true)->where('codigo_categoria', 'TST')->count() . PHP_EOL;

    // Regla del modelo: si al genérico se le intentan poner variantes, se limpian.
    $g1->generico = true;
    $g1->maneja_color = true;
    $g1->color = 'Verde';
    $g1->save();
    $g1->sincronizarVariantes(['Verde'], ['L']);
    $g1->refresh();
    echo 'Tras intento de variantes: maneja_color=' . var_export((bool) $g1->maneja_color, true) . ' color=' . var_export($g1->color, true) . ' variantes=' . $g1->variants()->count() . PHP_EOL;

    // Cambio de precio de un similar → el genérico refresca su promedio.
    $m2->prices()->create(['precio' => 32000, 'fecha' => '2026-11-01']);
    $r3 = $servicio->sincronizar();
    echo 'Tercera corrida tras nuevo precio: ' . json_encode($r3) . PHP_EOL;
    $g1->refresh();
    echo 'Nuevo precio genérico (esperado 17666.67 = avg(10000, 25333.33)): ' . $g1->costoUnitarioActual() . PHP_EOL;

    echo 'Estadísticas después: ' . json_encode($MGS::estadisticas()) . PHP_EOL;
} catch (\Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
} finally {
    DB::rollBack();
    echo 'Rollback hecho. Genéricos TST restantes: ' . $Material::where('codigo_categoria', 'TST')->count() . PHP_EOL;
}
