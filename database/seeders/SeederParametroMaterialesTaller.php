<?php

namespace Database\Seeders;

use App\Models\Parametro;
use Illuminate\Database\Seeder;

/**
 * Parámetro que controla si el modal de cambio de estado muestra, al pasar a
 * «En taller», cuánto se descuenta de inventario por cada material.
 * El descuento y su movimiento se registran siempre; esto solo oculta la tabla.
 */
class SeederParametroMaterialesTaller extends Seeder
{
    public const NOMBRE = Parametro::MOSTRAR_RESTA_MATERIALES_TALLER;

    public function run(): void
    {
        Parametro::firstOrCreate(
            ['nombre' => self::NOMBRE],
            [
                'valor' => '1', // verdadero
                'valor2' => null,
                'valor_date' => null,
                'valor_int' => null,
                'valor_double' => null,
                'valor_string1m' => 'Muestra en el cambio de estado a «En taller» cuánto se resta de cada material.',
            ]
        );
    }
}
