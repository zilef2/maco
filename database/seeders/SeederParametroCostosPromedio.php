<?php

namespace Database\Seeders;

use App\Models\Parametro;
use Illuminate\Database\Seeder;

class SeederParametroCostosPromedio extends Seeder
{
    public const NOMBRE = Parametro::COSTOS_PROMEDIO;

    public function run(): void
    {
        Parametro::firstOrCreate(
            ['nombre' => self::NOMBRE],
            [
                'valor' => null,
                'valor2' => null,
                'valor_date' => null,
                'valor_int' => Parametro::COSTOS_PROMEDIO_PROMEDIO,
                'valor_double' => null,
                'valor_string1m' => 'Cómo se calcula el costo de los materiales de una OP: '
                    . '1 = precio promedio de los cambios de precio del material, '
                    . '2 = último precio digitado del material.',
            ]
        );
    }
}
