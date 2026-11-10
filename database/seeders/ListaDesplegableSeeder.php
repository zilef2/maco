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
            // material
            ['nombre' => 'Apliques',            'categoria' => 'material', 'subcategoria' => 'categoria'],
            ['nombre' => 'Forros y cueros',     'categoria' => 'material', 'subcategoria' => 'categoria'],
            ['nombre' => 'Textiles',            'categoria' => 'material', 'subcategoria' => 'categoria'],
            ['nombre' => 'unidad',              'categoria' => 'material', 'subcategoria' => 'unidad_inventario'],
            ['nombre' => 'pie',                 'categoria' => 'material', 'subcategoria' => 'unidad_inventario'],
            ['nombre' => 'metro',               'categoria' => 'material', 'subcategoria' => 'unidad_inventario'],
            ['nombre' => 'Diseño / acabado',    'categoria' => 'material', 'subcategoria' => 'modelo_diseno_acabado'],
            ['nombre' => 'Tipo / espesor',      'categoria' => 'material', 'subcategoria' => 'modelo_diseno_acabado'],
            ['nombre' => 'Diseño / referencia', 'categoria' => 'material', 'subcategoria' => 'modelo_diseno_acabado'],
            // producto
            ['nombre' => 'Bolso manos libres',    'categoria' => 'Producto', 'subcategoria' => 'Categoria_original'],
            ['nombre' => 'Calzador',              'categoria' => 'Producto', 'subcategoria' => 'Categoria_original'],
            ['nombre' => 'Camiseta',              'categoria' => 'Producto', 'subcategoria' => 'Categoria_original'],
            ['nombre' => 'Bolsos',                'categoria' => 'Producto', 'subcategoria' => 'familia_normalizada'],
            ['nombre' => 'Calzador',              'categoria' => 'Producto', 'subcategoria' => 'familia_normalizada'],

            ['nombre' => 'Sin talla / sin color', 'categoria' => 'Producto', 'subcategoria' => 'Control_de_variante'],
            ['nombre' => 'Sin talla',             'categoria' => 'Producto', 'subcategoria' => 'Control_de_variante'],
            ['nombre' => 'Sin color',             'categoria' => 'Producto', 'subcategoria' => 'Control_de_variante'],
            ['nombre' => 'Talla numerica',        'categoria' => 'Producto', 'subcategoria' => 'Control_de_variante'],
            ['nombre' => 'Talla alfabética',      'categoria' => 'Producto', 'subcategoria' => 'Control_de_variante'],

            ['nombre' => 'XS',  'categoria' => 'Producto', 'subcategoria' => 'Opciones'],
            ['nombre' => 'S',   'categoria' => 'Producto', 'subcategoria' => 'Opciones'],
            ['nombre' => 'M',   'categoria' => 'Producto', 'subcategoria' => 'Opciones'],
            ['nombre' => 'L',   'categoria' => 'Producto', 'subcategoria' => 'Opciones'],
            ['nombre' => 'XL',  'categoria' => 'Producto', 'subcategoria' => 'Opciones'],
            ['nombre' => 'XXL', 'categoria' => 'Producto', 'subcategoria' => 'Opciones'],
            ['nombre' => 'Única',            'categoria' => 'Producto', 'subcategoria' => 'Opciones'],

            // ciudad (ficha de terceros: proveedor, taller y empresa externa)
            ['nombre' => 'Barranquilla', 'categoria' => 'ciudad', 'subcategoria' => 'general'],
            ['nombre' => 'Bogotá',       'categoria' => 'ciudad', 'subcategoria' => 'general'],
            ['nombre' => 'Bucaramanga',  'categoria' => 'ciudad', 'subcategoria' => 'general'],
            ['nombre' => 'Cali',         'categoria' => 'ciudad', 'subcategoria' => 'general'],
            ['nombre' => 'Cartagena',    'categoria' => 'ciudad', 'subcategoria' => 'general'],
            ['nombre' => 'Cúcuta',       'categoria' => 'ciudad', 'subcategoria' => 'general'],
            ['nombre' => 'Ibagué',       'categoria' => 'ciudad', 'subcategoria' => 'general'],
            ['nombre' => 'Medellín',     'categoria' => 'ciudad', 'subcategoria' => 'general'],
            ['nombre' => 'Pereira',      'categoria' => 'ciudad', 'subcategoria' => 'general'],
            ['nombre' => 'Santa Marta',  'categoria' => 'ciudad', 'subcategoria' => 'general'],
        ];

        foreach ($opciones as $opcion) {
            ListaDesplegable::create($opcion);
        }
    }
}
