<?php

namespace Database\Seeders;

use App\Models\EstadoOpMaterial;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EstadoOpMaterialSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $estados = [
            ['nombre' => 'Faltante',     'valor' => 0, 'pasa_a' => 'Producción' ],
            ['nombre' => 'Producción',   'valor' => 1, 'pasa_a' => 'Completa' ],
            ['nombre' => 'Completa',     'valor' => 2, 'pasa_a' => '' ],
        ];

        foreach ($estados as $estado) {
            EstadoOpMaterial::Create($estado);
        }
    }
}
