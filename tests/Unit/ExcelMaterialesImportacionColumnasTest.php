<?php

namespace Tests\Unit;

use App\Models\Material;
use App\Models\MaterialVariant;
use App\Services\CostoMaterialOpService;
use App\Services\ExcelMaterialesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExcelMaterialesImportacionColumnasTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reads_and_imports_modelo_diseno_acabado_color_and_talla_from_excel_rows(): void
    {
        $service = app(ExcelMaterialesService::class);

        $filas = [
            [
                'Código material',
                'Código categoría',
                'Categoría',
                'Código base',
                'Nombre base',
                'Modelo / diseño / acabado',
                'Color',
                'Talla / tamaño',
                'Unidad inventario',
                'Estado',
                'Costo',
                'Cantidad',
                'Procesado',
            ],
            [
                'TEX-LFI-1',
                'TEX',
                'Textil',
                'LFI',
                'Lana fibrada',
                'Rasurado fino',
                'Rojo, Azul, Negro',
                'S, M, L',
                'Metros',
                'Activo',
                '15000',
                '50',
                'no',
            ],
        ];

        $resultado = $service->registrosDesdeFilas($filas);
        $this->assertCount(1, $resultado['registros']);

        $registro = $resultado['registros'][0];
        $this->assertEquals('Rasurado fino', $registro['datos']['modelo_diseno_acabado']);
        $this->assertEquals('Rojo, Azul, Negro', $registro['datos']['color']);
        $this->assertEquals('S, M, L', $registro['datos']['talla_tamano']);
        $this->assertEquals(['Rojo', 'Azul', 'Negro'], $registro['colores']);
        $this->assertEquals(['S', 'M', 'L'], $registro['variantes']);

        $resImport = $service->importar($resultado['registros']);
        $this->assertEquals(1, $resImport['creados']);

        $material = Material::where('codigo_material', 'TEX-LFI-1')->first();
        $this->assertNotNull($material);
        $this->assertEquals('Rasurado fino', $material->modelo_diseno_acabado);
        $this->assertEquals('Rojo, Azul, Negro', $material->color);
        $this->assertEquals('S, M, L', $material->talla_tamano);
        $this->assertTrue((bool) $material->maneja_color);
        $this->assertTrue((bool) $material->maneja_talla);

        $this->assertDatabaseHas('material_variants', [
            'material_id' => $material->id,
            'nombre'      => 'Rojo',
            'tipo'        => MaterialVariant::TIPO_COLOR,
        ]);
        $this->assertDatabaseHas('material_variants', [
            'material_id' => $material->id,
            'nombre'      => 'S',
            'tipo'        => MaterialVariant::TIPO_VARIANTE,
        ]);
    }
}

