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
            ['categoria' => 'presupuesto', 'subcategoria' => 'estado', 'nombre' => '1 borrador'],
            ['categoria' => 'presupuesto', 'subcategoria' => 'estado', 'nombre' => '2 aprobado'],
            ['categoria' => 'presupuesto', 'subcategoria' => 'estado', 'nombre' => '3 activo'],
            ['categoria' => 'presupuesto', 'subcategoria' => 'estado', 'nombre' => '4 suspendido'],
            ['categoria' => 'presupuesto', 'subcategoria' => 'estado', 'nombre' => '5 cerrado'],

            // Categoria: presupuesto | Subcategoria: motivo_reajuste
            ['categoria' => 'presupuesto', 'subcategoria' => 'motivo_reajuste', 'nombre' => 'No hay reajustes'],
            ['categoria' => 'presupuesto', 'subcategoria' => 'motivo_reajuste', 'nombre' => 'Ajuste de IPC / Inflación'],
            ['categoria' => 'presupuesto', 'subcategoria' => 'motivo_reajuste', 'nombre' => 'Ampliación de alcance'],
            ['categoria' => 'presupuesto', 'subcategoria' => 'motivo_reajuste', 'nombre' => 'Reasignación de recursos'],
            ['categoria' => 'presupuesto', 'subcategoria' => 'motivo_reajuste', 'nombre' => 'Ajuste imprevisto'],
                // materias primas
            ['nombre' => 'Metros', 'categoria'  => 'materias_primas', 'subcategoria'    => 'unidad_medida'],
            ['nombre' => 'cm', 'categoria'      => 'materias_primas', 'subcategoria'    => 'unidad_medida'],
            ['nombre' => 'mm', 'categoria'      => 'materias_primas', 'subcategoria'    => 'unidad_medida'],
            ['nombre' => 'Unidades', 'categoria'=> 'materias_primas', 'subcategoria'    => 'unidad_medida'],
            ['nombre' => 'Talla', 'categoria'   => 'materias_primas', 'subcategoria'    => 'unidad_medida'],
            ['nombre' => 'Pares', 'categoria'   => 'materias_primas', 'subcategoria'    => 'unidad_medida'],

            //producto
            ['categoria' => 'Producto', 'subcategoria' => 'Categoria_original', 'nombre' => 'Bolso manos libres'],

        ];

        foreach ($opciones as $opcion) {
            ListaDesplegable::firstOrCreate($opcion);
        }
    }
}
