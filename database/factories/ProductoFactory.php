<?php

namespace Database\Factories;

use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Producto>
 */
class ProductoFactory extends Factory
{
    protected $model = Producto::class;

    public function definition(): array
    {
        return [
            'Codigo_interno' => 'COD-' . $this->faker->unique()->numberBetween(1000, 9999),
            'Titulo_Shopify' => $this->faker->words(3, true),
            'ID_producto_Shopify' => 'SHP-' . $this->faker->numberBetween(100, 999),
            'Categoria_original' => 'General',
            'Familia_normalizada' => 'General',
            'Control_de_variante' => 'Talla',
            'Opciones' => 'S,M,L',
            'Estado' => 1,
            'Observaciones' => null,
            'descripcion' => null,
            'costo_anticipado' => 10,
            'precio_venta_producto' => 20,
        ];
    }
}
