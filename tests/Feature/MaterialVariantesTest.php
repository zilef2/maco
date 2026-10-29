<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\MaterialVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Feature\Concerns\ContextoMateriales;
use Tests\TestCase;

class MaterialVariantesTest extends TestCase
{
    use ContextoMateriales;
    use RefreshDatabase;

    private function datosMaterial(string $codigo): array
    {
        return [
            'codigo_material' => $codigo,
            'categoria' => 'Tela',
            'nombre_base' => 'Tela ' . $codigo,
            'unidad_inventario' => 'm',
            'estado' => 'Activo',
            'last_purchase_price' => 10,
            'last_purchase_date' => now()->toDateString(),
        ];
    }

    private function archivoExcel(array $filas): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray($filas, null, 'A1');

        $ruta = tempnam(sys_get_temp_dir(), 'materiales_') . '.xlsx';
        (new Xlsx($spreadsheet))->save($ruta);

        return new UploadedFile(
            $ruta,
            'materiales.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    public function test_crear_material_con_colores_crea_sus_variantes(): void
    {
        $user = $this->usuarioAdmin();

        $response = $this->actingAs($user)->post(route('Material.store'), array_merge(
            $this->datosMaterial('MAT-VAR-COLOR'),
            [
                'colores' => ['Rojo', 'Azul', 'Rojo'],
                'variantes' => [],
            ]
        ));

        $response->assertSessionHasNoErrors();

        $material = Material::where('codigo_material', 'MAT-VAR-COLOR')->firstOrFail();

        $this->assertSame(
            ['Rojo', 'Azul'],
            $material->variants()->colores()->pluck('nombre')->all()
        );
        $this->assertTrue((bool) $material->fresh()->maneja_color);
        $this->assertFalse((bool) $material->fresh()->maneja_otra_variante);
    }

    public function test_crear_material_con_colores_y_variantes_es_rechazado(): void
    {
        $user = $this->usuarioAdmin();

        $response = $this->actingAs($user)->post(route('Material.store'), array_merge(
            $this->datosMaterial('MAT-VAR-AMBAS'),
            [
                'colores' => ['Rojo'],
                'variantes' => ['S'],
            ]
        ));

        $response->assertSessionHasErrors('colores');
        $this->assertDatabaseMissing('materials', ['codigo_material' => 'MAT-VAR-AMBAS']);
    }

    public function test_actualizar_material_reemplaza_las_variantes(): void
    {
        $user = $this->usuarioAdmin();
        $material = $this->materialBase('MAT-VAR-UPDATE');
        $material->sincronizarVariantes(['Rojo', 'Azul'], []);

        $response = $this->actingAs($user)->put(route('Material.update', $material->id), [
            'nombre_base' => 'Tela actualizada',
            'categoria' => 'Tela',
            'unidad_inventario' => 'm',
            'estado' => 'Activo',
            'colores' => [],
            'variantes' => ['S', 'M', 'L'],
        ]);

        $response->assertSessionHasNoErrors();

        $material->refresh();

        $this->assertSame(
            ['S', 'M', 'L'],
            $material->variants()->otrasVariantes()->pluck('nombre')->all()
        );
        $this->assertSame(0, $material->variants()->colores()->count());
        $this->assertFalse((bool) $material->maneja_color);
        $this->assertTrue((bool) $material->maneja_otra_variante);
    }

    public function test_importar_excel_crea_variantes_desde_listas_separadas_por_coma(): void
    {
        $user = $this->usuarioAdmin();

        $archivo = $this->archivoExcel([
            [
                'codigo_material', 'nombre_base', 'categoria', 'unidad_inventario',
                'estado', 'colores', 'variantes',
            ],
            ['MAT-EXCEL-COLOR', 'Tela color', 'Tela', 'm', 'Activo', 'Rojo, Azul', ''],
            ['MAT-EXCEL-VAR', 'Tela talla', 'Tela', 'm', 'Activo', '', 'S, M, L'],
        ]);

        $response = $this->actingAs($user)->post(route('Material.upload-excel'), [
            'archivo' => $archivo,
        ]);

        $response->assertSessionHas('success');

        $color = Material::where('codigo_material', 'MAT-EXCEL-COLOR')->firstOrFail();
        $variante = Material::where('codigo_material', 'MAT-EXCEL-VAR')->firstOrFail();

        $this->assertSame(
            ['Rojo', 'Azul'],
            $color->variants()->colores()->pluck('nombre')->all()
        );
        $this->assertSame(
            ['S', 'M', 'L'],
            $variante->variants()->otrasVariantes()->pluck('nombre')->all()
        );
    }

    public function test_importar_excel_rechaza_una_fila_con_colores_y_variantes(): void
    {
        $user = $this->usuarioAdmin();

        $archivo = $this->archivoExcel([
            [
                'codigo_material', 'nombre_base', 'categoria', 'unidad_inventario',
                'estado', 'colores', 'variantes',
            ],
            ['MAT-EXCEL-AMBAS', 'Tela mixta', 'Tela', 'm', 'Activo', 'Rojo', 'S'],
        ]);

        $response = $this->actingAs($user)->post(route('Material.upload-excel'), [
            'archivo' => $archivo,
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('materials', ['codigo_material' => 'MAT-EXCEL-AMBAS']);
        $this->assertSame(0, MaterialVariant::count());
    }

    public function test_eliminar_material_elimina_sus_variantes(): void
    {
        $user = $this->usuarioAdmin();
        $material = $this->materialBase('MAT-VAR-DELETE');
        $material->sincronizarVariantes(['Rojo'], []);

        $this->actingAs($user)->delete(route('Material.destroy', $material->id));

        $this->assertDatabaseMissing('materials', ['id' => $material->id]);
        $this->assertSame(0, MaterialVariant::where('material_id', $material->id)->count());
    }
}
