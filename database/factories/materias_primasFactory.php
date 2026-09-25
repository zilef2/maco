<?php

namespace Database\Factories;

use App\Models\materias_primas;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<materias_primas>
 */
class materias_primasFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = materias_primas::class;

    public function definition(): array
    {
        $categorias = [
            'Metales',
            'Plásticos',
            'Químicos',
            'Textiles',
            'Maderas',
            'Minerales',
            'Resinas',
            'Adhesivos',
            'Pinturas',
            'Empaques',
        ];

        $unidades = [
            'Kilogramo',
            'Litro',
            'Metro',
            'Tonelada',
            'Galón',
            'Rollo',
            'Bolsa',
            'Caja',
        ];

        $nombres = [
            'Acero Inoxidable', 'Polietileno', 'Ácido Sulfúrico', 'Algodón Crudo',
            'Pino Aserrado', 'Cuarzo Molido', 'Resina Epóxica', 'Pegamento Industrial',
            'Pintura Base Agua', 'Cartón Corrugado', 'Aluminio Laminado', 'PVC Granulado',
            'Sosa Cáustica', 'Lana Virgen', 'Roble Natural', 'Yeso en Polvo',
        ];

        $prefijos = ['MP', 'MAT', 'RAW', 'INS'];

        return [
            'codigo' => strtoupper($this->faker->unique()->bothify(
                $this->faker->randomElement($prefijos) . '-####-??'
            )),
            'nombre' => $this->faker->randomElement($nombres) . ' ' .
                        $this->faker->randomElement(['Grado A', 'Grado B', 'Industrial', 'Premium', 'Técnico']) . ' ' .
                        $this->faker->numberBetween(1, 500),
            'categoria' => $this->faker->randomElement($categorias),
            'unidad_medida' => $this->faker->randomElement($unidades),
            'existencia_actual' => $this->faker->numberBetween(0, 1000),
            'costo_promedio_ponderado' => $this->faker->randomFloat(2, 1.5, 2500),
            'fecha_creacion' => $this->faker->dateTimeBetween('-2 years', 'now'),
        ];
    }
}
