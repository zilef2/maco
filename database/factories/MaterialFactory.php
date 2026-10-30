<?php

namespace Database\Factories;

use App\Models\Material;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Material>
 */
class MaterialFactory extends Factory
{
    protected $model = Material::class;

    public function definition(): array
    {
        return [
            'codigo_material' => 'MAT-' . fake()->unique()->numberBetween(100, 9999),
            'categoria' => 'Telas',
            'nombre_base' => fake()->words(2, true),
            'unidad_inventario' => 'm',
            'estado' => 'Activo',
            'existencia_actual' => 10,
        ];
    }
}
