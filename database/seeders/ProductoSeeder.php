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
                'Codigo_interno' => 'AUR-PROX-001',
                'ID_producto_Shopify' => 'gid://shopify/Product/8801234567890',
                'Categoria_original' => 'Audio > Auriculares > Over-Ear',
                'Familia_normalizada' => 'Auriculares Inalámbricos',
                'Titulo_Shopify' => 'Auriculares Pro X',
                'Control_de_variante' => 'Color',
                'Opciones' => 'Negro, Blanco, Azul',
                'Estado' => 'Activo',
                'Observaciones' => 'Producto destacado en la campaña de lanzamiento Q1.',
                'descripcion' => 'Auriculares inalámbricos con cancelación activa de ruido, 40h de batería y sonido Hi-Res.',
                'precio_predicho' => 74900,
                'precio_preventa' => 69900,
            ],
            [
                'Codigo_interno' => 'AUR-PROX-002',
                'ID_producto_Shopify' => 'gid://shopify/Product/8801234567891',
                'Categoria_original' => 'Audio > Auriculares > In-Ear',
                'Familia_normalizada' => 'Auriculares Inalámbricos',
                'Titulo_Shopify' => 'Auriculares Pro X Lite',
                'Control_de_variante' => 'Talla',
                'Opciones' => 'S, M, L',
                'Estado' => 'Inactivo',
                'Observaciones' => null,
                'descripcion' => 'Versión compacta de los Auriculares Pro X, con 25h de batería y resistencia al agua IPX5.',
                'precio_predicho' => 49900,
                'precio_preventa' => 45900,
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
