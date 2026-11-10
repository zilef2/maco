<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ciudades más comunes del país para la ficha de terceros. Viven en
 * `listas_desplegables` para que desde Parámetros se puedan agregar más sin
 * tocar código.
 */
return new class extends Migration
{
    private const CIUDADES = [
        'Barranquilla',
        'Bogotá',
        'Bucaramanga',
        'Cali',
        'Cartagena',
        'Cúcuta',
        'Ibagué',
        'Medellín',
        'Pereira',
        'Santa Marta',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('listas_desplegables')) {
            return;
        }

        foreach (self::CIUDADES as $ciudad) {
            $existe = DB::table('listas_desplegables')
                ->where('categoria', 'ciudad')
                ->where('nombre', $ciudad)
                ->exists();

            if ($existe) {
                continue;
            }

            DB::table('listas_desplegables')->insert([
                'nombre' => $ciudad,
                'categoria' => 'ciudad',
                'subcategoria' => 'general',
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

        DB::table('listas_desplegables')->where('categoria', 'ciudad')->delete();
    }
};
