<?php

namespace Database\Seeders;

use App\Models\ListaDesplegable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

/*
php artisan db:seed --class=ListaDesplegableSeeder
*/

class ListaDesplegableSeeder extends Seeder
{
    public function run(): void
    {
        Log::info('Seeder iniciado');

        ListaDesplegable::query()->delete();
        Log::info('Borrado hecho. Quedan: '.ListaDesplegable::count());

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
            // material
            ['nombre' => 'Apliques',            'categoria' => 'material', 'subcategoria' => 'categoria'],
            ['nombre' => 'Forros y cueros',     'categoria' => 'material', 'subcategoria' => 'categoria'],
            ['nombre' => 'Textiles',            'categoria' => 'material', 'subcategoria' => 'categoria'],
            ['nombre' => 'unidad',              'categoria' => 'material', 'subcategoria' => 'unidad_inventario'],
            ['nombre' => 'pie',                 'categoria' => 'material', 'subcategoria' => 'unidad_inventario'],
            ['nombre' => 'metro',               'categoria' => 'material', 'subcategoria' => 'unidad_inventario'],
            ['nombre' => 'Diseño / acabado',    'categoria' => 'material', 'subcategoria' => 'variante_atributo_sugerido'],
            ['nombre' => 'Tipo / espesor',      'categoria' => 'material', 'subcategoria' => 'variante_atributo_sugerido'],
            ['nombre' => 'Diseño / referencia', 'categoria' => 'material', 'subcategoria' => 'variante_atributo_sugerido'],
            // producto
            ['nombre' => 'Bolso manos libres',    'categoria' => 'Producto', 'subcategoria' => 'Categoria_original'],
            ['nombre' => 'Calzador',              'categoria' => 'Producto', 'subcategoria' => 'Categoria_original'],
            ['nombre' => 'Camiseta',              'categoria' => 'Producto', 'subcategoria' => 'Categoria_original'],
            ['nombre' => 'Sin talla / sin color', 'categoria' => 'Producto', 'subcategoria' => 'Control_de_variante'],
            ['nombre' => 'Sin talla',             'categoria' => 'Producto', 'subcategoria' => 'Control_de_variante'],
            ['nombre' => 'Talla alfabética',      'categoria' => 'Producto', 'subcategoria' => 'Control_de_variante'],
            ['nombre' => 'Bolsos',                'categoria' => 'Producto', 'subcategoria' => 'familia_normalizada'],
            ['nombre' => 'Calzador',              'categoria' => 'Producto', 'subcategoria' => 'familia_normalizada'],

            ['nombre' => 'Única',            'categoria' => 'Producto', 'subcategoria' => 'Opciones'],
            ['nombre' => 'XS-S-M-L-XL-XXL',  'categoria' => 'Producto', 'subcategoria' => 'Opciones'],
            ['nombre' => '23-41',            'categoria' => 'Producto', 'subcategoria' => 'Opciones'],
            ['nombre' => 'Única',            'categoria' => 'Producto', 'subcategoria' => 'Opciones'],
            ['nombre' => '34-41',            'categoria' => 'Producto', 'subcategoria' => 'Opciones'],

        ];

        foreach ($opciones as $opcion) {
            ListaDesplegable::create($opcion);
        }
    }
}
