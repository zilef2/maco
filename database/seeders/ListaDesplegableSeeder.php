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
            // colores del material (los seis básicos y los demás habituales)
            ['nombre' => 'Negro',               'categoria' => 'material', 'subcategoria' => 'color'],
            ['nombre' => 'Blanco',              'categoria' => 'material', 'subcategoria' => 'color'],
            ['nombre' => 'Rojo',                'categoria' => 'material', 'subcategoria' => 'color'],
            ['nombre' => 'Azul',                'categoria' => 'material', 'subcategoria' => 'color'],
            ['nombre' => 'Verde',               'categoria' => 'material', 'subcategoria' => 'color'],
            ['nombre' => 'Amarillo',            'categoria' => 'material', 'subcategoria' => 'color'],
            ['nombre' => 'Beige',               'categoria' => 'material', 'subcategoria' => 'color'],
            ['nombre' => 'Gris',                'categoria' => 'material', 'subcategoria' => 'color'],
            ['nombre' => 'Rosado',              'categoria' => 'material', 'subcategoria' => 'color'],
            ['nombre' => 'Café',                'categoria' => 'material', 'subcategoria' => 'color'],
            ['nombre' => 'Hueso',               'categoria' => 'material', 'subcategoria' => 'color'],
            ['nombre' => 'Verde pistacho',      'categoria' => 'material', 'subcategoria' => 'color'],
            ['nombre' => 'Verde lima',          'categoria' => 'material', 'subcategoria' => 'color'],
            ['nombre' => 'Verde oliva oscuro',  'categoria' => 'material', 'subcategoria' => 'color'],
            // producto
            ['nombre' => 'Bolso manos libres',    'categoria' => 'Producto', 'subcategoria' => 'Categoria_original'],
            ['nombre' => 'Calzador',              'categoria' => 'Producto', 'subcategoria' => 'Categoria_original'],
            ['nombre' => 'Camiseta',              'categoria' => 'Producto', 'subcategoria' => 'Categoria_original'],
            ['nombre' => 'Cinturón',              'categoria' => 'Producto', 'subcategoria' => 'Categoria_original'],
            ['nombre' => 'Clasica Yute',          'categoria' => 'Producto', 'subcategoria' => 'Categoria_original'],
            ['nombre' => 'Clasicas',              'categoria' => 'Producto', 'subcategoria' => 'Categoria_original'],
            ['nombre' => 'Limpiador',             'categoria' => 'Producto', 'subcategoria' => 'Categoria_original'],
            ['nombre' => 'Mocasines',             'categoria' => 'Producto', 'subcategoria' => 'Categoria_original'],
            ['nombre' => 'mocesines',             'categoria' => 'Producto', 'subcategoria' => 'Categoria_original'],
            ['nombre' => 'Plantillas',            'categoria' => 'Producto', 'subcategoria' => 'Categoria_original'],
            ['nombre' => 'Tenis',                 'categoria' => 'Producto', 'subcategoria' => 'Categoria_original'],
            ['nombre' => 'Bolsos',                'categoria' => 'Producto', 'subcategoria' => 'familia_normalizada'],
            ['nombre' => 'Calzador',              'categoria' => 'Producto', 'subcategoria' => 'familia_normalizada'],
            ['nombre' => 'Camisetas',             'categoria' => 'Producto', 'subcategoria' => 'familia_normalizada'],
            ['nombre' => 'Cinturones',            'categoria' => 'Producto', 'subcategoria' => 'familia_normalizada'],
            ['nombre' => 'Clásicas',              'categoria' => 'Producto', 'subcategoria' => 'familia_normalizada'],
            ['nombre' => 'Limpiador',             'categoria' => 'Producto', 'subcategoria' => 'familia_normalizada'],
            ['nombre' => 'Mocasines',             'categoria' => 'Producto', 'subcategoria' => 'familia_normalizada'],
            ['nombre' => 'Plantillas',            'categoria' => 'Producto', 'subcategoria' => 'familia_normalizada'],
            ['nombre' => 'Deportivos',            'categoria' => 'Producto', 'subcategoria' => 'familia_normalizada'],

            ['nombre' => 'Talla numerica',        'categoria' => 'Producto', 'subcategoria' => 'Control_de_variante'],
            ['nombre' => 'Talla alfabética',      'categoria' => 'Producto', 'subcategoria' => 'Control_de_variante'],
            ['nombre' => 'Color',                 'categoria' => 'Producto', 'subcategoria' => 'Control_de_variante'],
            ['nombre' => 'Diseño',                'categoria' => 'Producto', 'subcategoria' => 'Control_de_variante'],
            ['nombre' => 'Sin color ni talla',    'categoria' => 'Producto', 'subcategoria' => 'Control_de_variante'],

            // valores de los controles de variante sin talla
            ['nombre' => 'Amarillo / Amarilla',   'categoria' => 'Producto', 'subcategoria' => 'color'],
            ['nombre' => 'Azul',                  'categoria' => 'Producto', 'subcategoria' => 'color'],
            ['nombre' => 'Azul oscuro',           'categoria' => 'Producto', 'subcategoria' => 'color'],
            ['nombre' => 'Azul rey',              'categoria' => 'Producto', 'subcategoria' => 'color'],
            ['nombre' => 'Beige',                 'categoria' => 'Producto', 'subcategoria' => 'color'],
            ['nombre' => 'Blanco',                'categoria' => 'Producto', 'subcategoria' => 'color'],
            ['nombre' => 'Café',                  'categoria' => 'Producto', 'subcategoria' => 'color'],
            ['nombre' => 'Gris',                  'categoria' => 'Producto', 'subcategoria' => 'color'],
            ['nombre' => 'Hueso',                 'categoria' => 'Producto', 'subcategoria' => 'color'],
            ['nombre' => 'Miel / camel',          'categoria' => 'Producto', 'subcategoria' => 'color'],
            ['nombre' => 'Morada',                'categoria' => 'Producto', 'subcategoria' => 'color'],
            ['nombre' => 'Naranja',               'categoria' => 'Producto', 'subcategoria' => 'color'],
            ['nombre' => 'Negro / Negra',         'categoria' => 'Producto', 'subcategoria' => 'color'],
            ['nombre' => 'Nude / rosado',         'categoria' => 'Producto', 'subcategoria' => 'color'],
            ['nombre' => 'Plateado / Plateada',   'categoria' => 'Producto', 'subcategoria' => 'color'],
            ['nombre' => 'Rojo / Roja',           'categoria' => 'Producto', 'subcategoria' => 'color'],
            ['nombre' => 'Verde',                 'categoria' => 'Producto', 'subcategoria' => 'color'],
            ['nombre' => 'Verde pistacho',        'categoria' => 'Producto', 'subcategoria' => 'color'],

            ['nombre' => 'Diseño / referencia',                       'categoria' => 'Producto', 'subcategoria' => 'diseno'],
            ['nombre' => 'Tipo de intervención + diseño / referencia', 'categoria' => 'Producto', 'subcategoria' => 'diseno'],
            ['nombre' => 'Por definir según existencias',              'categoria' => 'Producto', 'subcategoria' => 'diseno'],
            ['nombre' => 'Descripción / material',                     'categoria' => 'Producto', 'subcategoria' => 'diseno'],
            ['nombre' => 'Material / tipo',                            'categoria' => 'Producto', 'subcategoria' => 'diseno'],
            ['nombre' => 'Estampado / diseño',                         'categoria' => 'Producto', 'subcategoria' => 'diseno'],
            ['nombre' => 'Modelo / descripción',                       'categoria' => 'Producto', 'subcategoria' => 'diseno'],
            ['nombre' => 'Ver carta de sesgos al final de esta hoja',  'categoria' => 'Producto', 'subcategoria' => 'diseno'],
            ['nombre' => 'Por definir',                                'categoria' => 'Producto', 'subcategoria' => 'diseno'],

            ['nombre' => 'XS',  'categoria' => 'Producto', 'subcategoria' => 'Opciones'],
            ['nombre' => 'S',   'categoria' => 'Producto', 'subcategoria' => 'Opciones'],
            ['nombre' => 'M',   'categoria' => 'Producto', 'subcategoria' => 'Opciones'],
            ['nombre' => 'L',   'categoria' => 'Producto', 'subcategoria' => 'Opciones'],
            ['nombre' => 'XL',  'categoria' => 'Producto', 'subcategoria' => 'Opciones'],
            ['nombre' => 'XXL', 'categoria' => 'Producto', 'subcategoria' => 'Opciones'],
            ['nombre' => 'Única',            'categoria' => 'Producto', 'subcategoria' => 'Opciones'],

            // ciudad (ficha de terceros: proveedor, taller y empresa externa)
            ['nombre' => 'Cali',      'categoria' => 'ciudad', 'subcategoria' => 'general'],
            ['nombre' => 'Bogotá',      'categoria' => 'ciudad', 'subcategoria' => 'general'],
            ['nombre' => 'Barranquilla',      'categoria' => 'ciudad', 'subcategoria' => 'general'],        
            ['nombre' => 'Medellín',      'categoria' => 'ciudad', 'subcategoria' => 'general'],
            ['nombre' => 'Manizales',      'categoria' => 'ciudad', 'subcategoria' => 'general'],
            ['nombre' => 'Armenia',        'categoria' => 'ciudad', 'subcategoria' => 'general'],
            ['nombre' => 'Villavicencio',  'categoria' => 'ciudad', 'subcategoria' => 'general'],
            ['nombre' => 'Valledupar',     'categoria' => 'ciudad', 'subcategoria' => 'general'],
            ['nombre' => 'Montería',       'categoria' => 'ciudad', 'subcategoria' => 'general'],
            ['nombre' => 'Pasto',          'categoria' => 'ciudad', 'subcategoria' => 'general'],
            ['nombre' => 'Neiva',          'categoria' => 'ciudad', 'subcategoria' => 'general'],
            ['nombre' => 'Popayán',        'categoria' => 'ciudad', 'subcategoria' => 'general'],
            ['nombre' => 'Sincelejo',      'categoria' => 'ciudad', 'subcategoria' => 'general'],
            ['nombre' => 'Buenaventura',   'categoria' => 'ciudad', 'subcategoria' => 'general'],
            ['nombre' => 'Palmira',        'categoria' => 'ciudad', 'subcategoria' => 'general'],
            ['nombre' => 'Bello',          'categoria' => 'ciudad', 'subcategoria' => 'general'],
            ['nombre' => 'Soacha',         'categoria' => 'ciudad', 'subcategoria' => 'general'],
            ['nombre' => 'Tunja',          'categoria' => 'ciudad', 'subcategoria' => 'general'],
            ['nombre' => 'Riohacha',       'categoria' => 'ciudad', 'subcategoria' => 'general'],
            ['nombre' => 'Florencia',      'categoria' => 'ciudad', 'subcategoria' => 'general'],
            ['nombre' => 'Quibdó',         'categoria' => 'ciudad', 'subcategoria' => 'general'],
            ['nombre' => 'Yopal',          'categoria' => 'ciudad', 'subcategoria' => 'general'],
            ['nombre' => 'Envigado',       'categoria' => 'ciudad', 'subcategoria' => 'general'],
            ['nombre' => 'Itagüí',         'categoria' => 'ciudad', 'subcategoria' => 'general'],
            ['nombre' => 'Dosquebradas',   'categoria' => 'ciudad', 'subcategoria' => 'general'],
            ['nombre' => 'Tuluá',          'categoria' => 'ciudad', 'subcategoria' => 'general'],
            ['nombre' => 'Girardot',       'categoria' => 'ciudad', 'subcategoria' => 'general'],
            ['nombre' => 'Zipaquirá',      'categoria' => 'ciudad', 'subcategoria' => 'general'],
            ['nombre' => 'Buga',           'categoria' => 'ciudad', 'subcategoria' => 'general'],
            ['nombre' => 'Guatapé',                   'categoria' => 'municipio', 'subcategoria' => 'general'],
            ['nombre' => 'Santa Fe de Antioquia',     'categoria' => 'municipio', 'subcategoria' => 'general'],
            ['nombre' => 'Jardín',                    'categoria' => 'municipio', 'subcategoria' => 'general'],
            ['nombre' => 'Jericó',                    'categoria' => 'municipio', 'subcategoria' => 'general'],
            ['nombre' => 'El Retiro',                 'categoria' => 'municipio', 'subcategoria' => 'general'],
            ['nombre' => 'El Carmen de Viboral',      'categoria' => 'municipio', 'subcategoria' => 'general'],
            ['nombre' => 'San Rafael',                'categoria' => 'municipio', 'subcategoria' => 'general'],
            ['nombre' => 'Marinilla',                 'categoria' => 'municipio', 'subcategoria' => 'general'],
            ['nombre' => 'La Ceja',                   'categoria' => 'municipio', 'subcategoria' => 'general'],
            ['nombre' => 'Rionegro',                  'categoria' => 'municipio', 'subcategoria' => 'general'],
            ['nombre' => 'San Vicente Ferrer',        'categoria' => 'municipio', 'subcategoria' => 'general'],
            ['nombre' => 'San Pedro de los Milagros', 'categoria' => 'municipio', 'subcategoria' => 'general'],
            ['nombre' => 'Entrerríos',                'categoria' => 'municipio', 'subcategoria' => 'general'],
            ['nombre' => 'Santa Rosa de Osos',        'categoria' => 'municipio', 'subcategoria' => 'general'],
            ['nombre' => 'Barbosa',                   'categoria' => 'municipio', 'subcategoria' => 'general'],
            ['nombre' => 'Girardota',                 'categoria' => 'municipio', 'subcategoria' => 'general'],
            ['nombre' => 'Copacabana',                'categoria' => 'municipio', 'subcategoria' => 'general'],
            ['nombre' => 'Amagá',                     'categoria' => 'municipio', 'subcategoria' => 'general'],
            ['nombre' => 'Venecia',                   'categoria' => 'municipio', 'subcategoria' => 'general'],
            ['nombre' => 'San Luis',                  'categoria' => 'municipio', 'subcategoria' => 'general'],

            // proceso (nombres de los procesos de fabricación)
            ['nombre' => 'Sublimada',     'categoria' => 'proceso', 'subcategoria' => 'general'],
            ['nombre' => 'Mano de obra',  'categoria' => 'proceso', 'subcategoria' => 'general'],
            ['nombre' => 'Capellar',      'categoria' => 'proceso', 'subcategoria' => 'general'],
        ];

        foreach ($opciones as $opcion) {
            ListaDesplegable::updateOrCreate($opcion);
        }
    }
}
