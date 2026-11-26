<?php

namespace Tests\Unit;

use App\Models\Material;
use App\Models\MaterialVariant;
use App\Services\ExcelMaterialesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ExcelMaterialesImportacionColumnasTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Cabecera mínima del formato actual: el rol de la fila lo declara
     * "Base si o no", no la forma del código.
     */
    private function cabecera(array $extra = []): array
    {
        return array_merge([
            'Código material',
            'Código categoría',
            'Categoría',
            'Código base',
            'Nombre base',
            'Unidad inventario',
            'Estado',
            'Procesado',
            'Base si o no',
        ], $extra);
    }

    public function test_it_reads_and_imports_modelo_diseno_acabado_color_and_talla_from_excel_rows(): void
    {
        $service = app(ExcelMaterialesService::class);

        $filas = [
            $this->cabecera([
                'Modelo / diseño / acabado',
                'Color',
                'Talla / tamaño',
                'Costo',
                'Cantidad',
            ]),
            [
                'TEX-LFI-1',
                'TEX',
                'Textil',
                'LFI',
                'Lana fibrada',
                'Metros',
                'Activo',
                'no',
                'no',
                'Rasurado fino',
                'Rojo, Azul, Negro',
                'S, M, L',
                '15000',
                '50',
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

        // El modelo rellena el número del código: TEX-LFI-1 queda TEX-LFI-001.
        $material = Material::where('codigo_material', 'TEX-LFI-001')->first();
        $this->assertNotNull($material);
        $this->assertFalse((bool) $material->generico);
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

    public function test_una_fila_base_descarta_variantes_precio_e_inventario(): void
    {
        $service = app(ExcelMaterialesService::class);

        $filas = [
            $this->cabecera([
                'Modelo / diseño / acabado',
                'Color',
                'Talla / tamaño',
                'Proveedor habitual',
                'Costo',
                'Inventario Fisico',
            ]),
            [
                'TEX-LFI-1',
                'TEX',
                'Textil',
                'LFI',
                'Lana fibrada',
                'Metros',
                'Activo',
                'no',
                'si',
                'Rasurado fino',
                'Rojo, Azul',
                'S, M',
                'Proveedor que no existe',
                '15000',
                '50',
            ],
        ];

        $resultado = $service->registrosDesdeFilas($filas);
        $registro = $resultado['registros'][0];

        $this->assertTrue($registro['datos']['generico']);
        $this->assertNull($registro['datos']['modelo_diseno_acabado']);
        $this->assertNull($registro['datos']['color']);
        $this->assertNull($registro['datos']['talla_tamano']);
        $this->assertNull($registro['datos']['proveedor_habitual']);
        $this->assertArrayNotHasKey('existencia_actual', $registro['datos']);
        $this->assertSame([], $registro['colores']);
        $this->assertSame([], $registro['variantes']);
        $this->assertNull($registro['precio']);
        $this->assertNull($registro['cantidad']);

        $service->importar($resultado['registros']);
        $material = Material::where('codigo_material', 'TEX-LFI-001')->firstOrFail();
        $this->assertTrue((bool) $material->generico);
        $this->assertNull($material->existencia_actual);
        $this->assertSame(0, $material->variants()->count());
        $this->assertSame(0, $material->prices()->count());
    }

    public function test_rechaza_un_valor_de_base_que_no_es_si_o_no(): void
    {
        $service = app(ExcelMaterialesService::class);

        $filas = [
            $this->cabecera(),
            [
                'TEX-LFI-1',
                'TEX',
                'Textil',
                'LFI',
                'Lana fibrada',
                'Metros',
                'Activo',
                'no',
                'quizas',
            ],
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('"Base si o no" debe ser si o no');
        $service->registrosDesdeFilas($filas);
    }

    public function test_los_alias_de_la_columna_base_resuelven_a_generico(): void
    {
        $service = app(ExcelMaterialesService::class);

        foreach (['Base si o no', 'Base', 'Es base', 'Material base', 'Generico'] as $alias) {
            $titulos = $this->cabecera();
            $titulos[array_search('Base si o no', $titulos, true)] = $alias;

            $columnas = $service->validarTitulos($titulos);
            $this->assertContains(
                'generico',
                $columnas['encabezado'],
                "El alias «{$alias}» no resolvió a la columna generico."
            );
        }
    }
}
