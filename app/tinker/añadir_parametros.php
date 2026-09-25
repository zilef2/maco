<?php

use App\Models\parametro;

/*
 php artisan tinker --execute="include 'app/tinker/añadir_parametros.php';"
 */

$para1 = parametro::firstOrCreate([
    // 'id' => 1 ],[
    'nombre' => 'jefe',
    'valor' => 'Liliana Madrid',
    'valor2' => 'macondascolombia@gmail.com',
    'valor_date' => null,
    'valor_int' => null,
    'valor_double' => null,
    'valor_string1m' => null,
]);
$para2 = parametro::firstOrCreate([
    // 'id' => 2 ],[
    'nombre' => 'Primera reunion',
    'valor' => 'pensando el proyecto, primer boceto',
    'valor2' => null,
    'valor_date' => '2026-09-10',
    'valor_int' => null,
    'valor_double' => null,
    'valor_string1m' => null,
]);

if (! $para1) { echo "❌ No se pudo insertar para1\n"; return; }
if (! $para2) { echo "❌ No se pudo insertar para2\n"; return; }

echo "\n";
echo "========================================\n";
echo "  Parametros: {$para1->nombre} \n";
echo "  Parametros: {$para2->nombre} \n";
echo "========================================\n\n";

