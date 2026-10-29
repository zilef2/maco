<?php

namespace Database\Seeders;

use App\Models\Producto;
use Illuminate\Database\Seeder;

class ProductoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $productos = [
            [
                'Codigo_interno' => 'ZAP-RUN-'.rand(100, 999),
                'ID_producto_Shopify' => 'gid://shopify/Product/'.rand(1000000000000, 9999999999999),
                'Categoria_original' => 'Calzado > Deportivo > Running',
                'Familia_normalizada' => 'Zapatillas Deportivas',
                'Titulo_Shopify' => 'Zapatillas Runner Pro',
                'Control_de_variante' => 'Talla',
                'Opciones' => '38, 39, 40, 41, 42, 43, 44',
                'Estado' => 'Activo',
                'Observaciones' => 'Modelo estrella para la campaña de temporada primavera-verano.',
                'descripcion' => 'Zapatillas de running con amortiguación de alto impacto, malla transpirable y suela de caucho antideslizante.',
                'costo_anticipado' => 189900,
                'precio_preventa' => 169900,
            ],
            [
                'Codigo_interno' => 'ZAP-URB-'.rand(100, 999),
                'ID_producto_Shopify' => 'gid://shopify/Product/'.rand(1000000000000, 9999999999999),
                'Categoria_original' => 'Calzado > Casual > Sneakers',
                'Familia_normalizada' => 'Zapatillas Urbanas',
                'Titulo_Shopify' => 'Sneakers Urban Style',
                'Control_de_variante' => 'Color',
                'Opciones' => 'Blanco, Negro, Beige',
                'Estado' => 'Inactivo',
                'Observaciones' => null,
                'descripcion' => 'Sneakers casuales de diseño minimalista, ideales para uso diario con plantilla acolchada y suela ligera.',
                'costo_anticipado' => 129900,
                'precio_preventa' => 119900,
            ],
        ];

        foreach ($productos as $producto) {
            Producto::updateOrCreate(
                ['Codigo_interno' => $producto['Codigo_interno']],
                $producto
            );
        }
    }
}
