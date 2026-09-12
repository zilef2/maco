<?php

namespace Database\Seeders;

use App\Models\ListaDesplegable;
use Illuminate\Database\Seeder;

class ListaDesplegableSeeder extends Seeder
{
    public function run(): void
    {
        $opciones = [
            // Categoria: presupuesto | Subcategoria: estado
            ['categoria' => 'presupuesto', 'subcategoria' => 'estado', 'nombre' => 'Borrador'],
            ['categoria' => 'presupuesto', 'subcategoria' => 'estado', 'nombre' => 'Activo'],
            ['categoria' => 'presupuesto', 'subcategoria' => 'estado', 'nombre' => 'Aprobado'],
            ['categoria' => 'presupuesto', 'subcategoria' => 'estado', 'nombre' => 'Cerrado'],
            ['categoria' => 'presupuesto', 'subcategoria' => 'estado', 'nombre' => 'Suspendido'],

            // Categoria: presupuesto | Subcategoria: motivo_reajuste
            ['categoria' => 'presupuesto', 'subcategoria' => 'motivo_reajuste', 'nombre' => 'No hay reajustes'],
            ['categoria' => 'presupuesto', 'subcategoria' => 'motivo_reajuste', 'nombre' => 'Ajuste de IPC / Inflación'],
            ['categoria' => 'presupuesto', 'subcategoria' => 'motivo_reajuste', 'nombre' => 'Ampliación de alcance'],
            ['categoria' => 'presupuesto', 'subcategoria' => 'motivo_reajuste', 'nombre' => 'Reasignación de recursos'],
            ['categoria' => 'presupuesto', 'subcategoria' => 'motivo_reajuste', 'nombre' => 'Ajuste imprevisto'],
        ];

        foreach ($opciones as $opcion) {
            ListaDesplegable::firstOrCreate($opcion);
        }
    }
}
