<?php

namespace Database\Factories;

use App\Models\ListaDesplegable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ListaDesplegable>
 */
class ListaDesplegableFactory extends Factory
{
    protected $model = ListaDesplegable::class;

    public function definition(): array
    {
        return [
            'nombre' => $this->faker->word(),
            'categoria' => 'presupuesto',
            'subcategoria' => 'estado',
        ];
    }
}
