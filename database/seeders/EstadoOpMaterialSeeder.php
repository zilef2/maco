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
            ['nombre' => 'Faltante', 'cuando_aplica' => 'OP creada pero aún no confirmada'   ,                                      'puede_pasar_a' => 'Preparación', 'regla_clave' => 'No genera movimientos de inventario.'],
            ['nombre' => 'Producción', 'cuando_aplica' => 'Se están reuniendo materiales',                                         'puede_pasar_a' => 'Esperando materiales / Procesos externos / Lista para taller', 'regla_clave' => 'Debe comparar materiales requeridos vs disponibles.'],
            ['nombre' => 'Completa', 'cuando_aplica' => 'Se están reuniendo materiales',                                         'puede_pasar_a' => 'Esperando materiales / Procesos externos / Lista para taller', 'regla_clave' => 'Debe comparar materiales requeridos vs disponibles.'],
        ];

        foreach ($estados as $estado) {
            EstadoOpMaterial::Create($estado);
        }
    }
}
