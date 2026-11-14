<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Colores que se pueden elegir en un material. Viven en `listas_desplegables`
 * (categoría material, subcategoría color) para que desde Parámetros se puedan
 * agregar más sin tocar código.
 */
return new class extends Migration
{
    private const COLORES = [
        // Los seis básicos.
        'Negro',
        'Blanco',
        'Rojo',
        'Azul',
        'Verde',
        'Amarillo',
        // Los demás que usa la operación.
        'Beige',
        'Gris',
        'Rosado',
        'Café',
        'Hueso',
        'Verde pistacho',
        'Verde lima',
        'Verde oliva oscuro',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('listas_desplegables')) {
            return;
        }

        foreach (self::COLORES as $color) {
            $existe = DB::table('listas_desplegables')
                ->where('categoria', 'material')
                ->where('subcategoria', 'color')
                ->where('nombre', $color)
                ->exists();

            if ($existe) {
                continue;
            }

            DB::table('listas_desplegables')->insert([
                'nombre' => $color,
                'categoria' => 'material',
                'subcategoria' => 'color',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('listas_desplegables')) {
            return;
        }

        DB::table('listas_desplegables')
            ->where('categoria', 'material')
            ->where('subcategoria', 'color')
            ->whereIn('nombre', self::COLORES)
            ->delete();
    }
};
