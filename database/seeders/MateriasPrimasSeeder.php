<?php

namespace Database\Seeders;

use App\Models\materias_primas;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MateriasPrimasSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        materias_primas::factory()
            ->count(100)
            ->create();

        // Opcional: algunos materias_primass sin existencia (agotados)
        materias_primas::factory()
            ->count(10)
            ->state([
                'existencia_actual' => 0,
            ])
            ->create();

        // Opcional: materias_primass con costo alto
        materias_primas::factory()
            ->count(5)
            ->state([
                'costo_promedio_ponderado' => fake()->randomFloat(2, 2000, 10000),
                'categoria' => 'Electrónica',
            ])
            ->create();
    }
}
