<?php

namespace Database\Seeders;

use App\Models\FichaTecnica;
use App\Models\FichaTecnicaMateriales;
use App\Models\Material;
use App\Models\Producto;
use Illuminate\Database\Seeder;

class FichaTecnicaMaterialesSeeder extends Seeder
{
    /**
     * Fichas técnicas de ejemplo: materiales + cantidades para fabricar
     * un zapato, y los productos que usan cada ficha (única por producto).
     */
    public function run(): void
    {
        $productosBase = Producto::whereIn('Codigo_interno', ['AUR-PROX-001', 'AUR-PROX-002'])->get()->keyBy('Codigo_interno');
        if ($productosBase->isEmpty() || Material::count() === 0) {
            return;
        }

        $mat = fn (string $codigo) => Material::where('codigo_material', $codigo)->first();

        $fichaDeportiva = FichaTecnica::firstOrCreate(['nombre' => 'Zapato deportivo estándar']);
        $this->syncMateriales($fichaDeportiva, [
            ['codigo' => 'MAT-0001', 'consumo' => 2, 'unidad' => 'Metros', 'teorico' => 5000, 'real' => 5200],
            ['codigo' => 'MAT-0003', 'consumo' => 1, 'unidad' => 'Kilos', 'teorico' => 1200, 'real' => null],
            ['codigo' => 'MAT-0005', 'consumo' => 4, 'unidad' => 'Unidades', 'teorico' => 800, 'real' => 850],
        ]);
        $this->syncProductos($fichaDeportiva, [$productosBase['AUR-PROX-001'] ?? null]);

        $fichaPremium = FichaTecnica::firstOrCreate(['nombre' => 'Zapato premium edición limitada']);
        $this->syncMateriales($fichaPremium, [
            // consumo alto a propósito: supera la existencia sembrada (0-500) para demostrar faltantes en rojo
            ['codigo' => 'MAT-0002', 'consumo' => 3000, 'unidad' => 'Metros', 'teorico' => 90000, 'real' => null],
            ['codigo' => 'MAT-0004', 'consumo' => 6, 'unidad' => 'Unidades', 'teorico' => 2400, 'real' => 2600],
        ]);
        $this->syncProductos($fichaPremium, [$productosBase['AUR-PROX-002'] ?? null]);
    }

    private function syncMateriales(FichaTecnica $ficha, array $filas): void
    {
        $ficha->materiales()->delete();
        foreach ($filas as $fila) {
            $material = Material::where('codigo_material', $fila['codigo'])->first();
            if (!$material) {
                continue;
            }
            FichaTecnicaMateriales::create([
                'ficha_tecnica_id'       => $ficha->id,
                'material_componente_id' => $material->id,
                'consumo_estandar'       => $fila['consumo'],
                'unidad_medida'          => $fila['unidad'],
                'costo_asociado_teorico' => $fila['teorico'],
                'costo_asociado_real'    => $fila['real'],
            ]);
        }
    }

    private function syncProductos(FichaTecnica $ficha, array $productos): void
    {
        $ids = collect($productos)->filter()->pluck('id')->all();
        $ficha->productos()->sync($ids);
    }
}
