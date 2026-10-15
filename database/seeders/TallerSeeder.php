<?php

namespace Database\Seeders;

use App\Models\Taller;
use Illuminate\Database\Seeder;

class TallerSeeder extends Seeder
{
    /** Cuántos talleres aleatorios crear (editar aquí) */
    private const CANTIDAD = 15;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $nombres = [
            'Taller Norte', 'Taller Sur', 'Taller Centro', 'Taller Occidente', 'Taller Oriente',
            'Confecciones El Rosal', 'Puntadas del Valle', 'Manufacturas La 80', 'Estilo Express',
            'Costura Andina', 'Atelier Dinámico', 'Prendas del Sur', 'Taller Metropolitano',
        ];
        $calles = [
            'Cra. 15', 'Cra. 43', 'Cra. 7', 'Cll. 10', 'Cll. 26', 'Cll. 80', 'Av. Siempre Viva',
            'Cra. 100', 'Cll. 5', 'Av. Principal',
        ];
        $sectores = ['Centro', 'Norte', 'Sur', 'Occidente', 'Chapinero', 'Kennedy', 'Suba', 'Usaquén'];

        for ($i = 1; $i <= self::CANTIDAD; $i++) {
            Taller::create([
                'nombre'    => $nombres[array_rand($nombres)] . ' #' . $i . '-' . rand(10, 99),
                'direccion' => $calles[array_rand($calles)] . ' #' . rand(1, 99) . '-' . rand(1, 20)
                    . ', ' . $sectores[array_rand($sectores)],
            ]);
        }
    }
}
