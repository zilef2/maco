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

    /**
     * El código ya no se digita: lo arma el modelo con el par categoría/base.
     * Los tests buscan el material por su nombre.
     */
    private function datosMaterial(string $nombre): array
    {
        return [
            'codigo_categoria' => 'MAT',
            'codigo_base' => 'VAR',
            'generico' => false,
            'categoria' => 'Tela',
            'nombre_base' => $nombre,
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
            $this->datosMaterial('Tela color'),
            [
                'colores' => ['Rojo', 'Azul', 'Rojo'],
                'variantes' => [],
            ]
        ));

        $response->assertSessionHasNoErrors();

        $material = Material::where('nombre_base', 'Tela color')->firstOrFail();

        $this->assertSame(
            ['Rojo', 'Azul'],
            $material->variants()->colores()->pluck('nombre')->all()
        );
        $this->assertTrue((bool) $material->fresh()->maneja_color);
        $this->assertFalse((bool) $material->fresh()->maneja_otra_variante);
    }

    public function test_crear_material_con_colores_y_variantes_guarda_las_dos_listas(): void
    {
        $user = $this->usuarioAdmin();

        $response = $this->actingAs($user)->post(route('Material.store'), array_merge(
            $this->datosMaterial('Tela mixta'),
            [
                'colores' => ['Rojo'],
                'variantes' => ['S'],
            ]
        ));

        $response->assertSessionHasNoErrors();

        // Un material sí puede manejar color y otra variante a la vez.
        $material = Material::where('nombre_base', 'Tela mixta')->firstOrFail();

        $this->assertSame(['Rojo'], $material->variants()->colores()->pluck('nombre')->all());
        $this->assertSame(['S'], $material->variants()->otrasVariantes()->pluck('nombre')->all());
        $this->assertTrue((bool) $material->fresh()->maneja_color);
        $this->assertTrue((bool) $material->fresh()->maneja_otra_variante);
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
                'estado', 'base si o no', 'Color', 'Talla / tamaño', 'costo', 'procesado',
            ],
            ['MAT-EXCEL-COLOR', 'Tela color', 'Tela', 'm', 'Activo', 'no', 'Rojo, Azul', '', 8500, 'si'],
            ['MAT-EXCEL-VAR', 'Tela talla', 'Tela', 'm', 'Activo', 'no', '', 'S, M, L', 7200, 'no'],
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

    public function test_importar_excel_guarda_colores_y_variantes_del_mismo_material(): void
    {
        $user = $this->usuarioAdmin();

        $archivo = $this->archivoExcel([
            [
                'codigo_material', 'nombre_base', 'categoria', 'unidad_inventario',
                'estado', 'base si o no', 'Color', 'Talla / tamaño', 'costo', 'procesado',
            ],
            ['MAT-EXCEL-AMBAS', 'Tela mixta', 'Tela', 'm', 'Activo', 'no', 'Rojo, Azul', 'S, M', 5000, 'no'],
        ]);

        $response = $this->actingAs($user)->post(route('Material.upload-excel'), [
            'archivo' => $archivo,
        ]);

        $response->assertSessionHas('success');

        $material = Material::where('codigo_material', 'MAT-EXCEL-AMBAS')->firstOrFail();

        $this->assertSame(['Rojo', 'Azul'], $material->variants()->colores()->pluck('nombre')->all());
        $this->assertSame(['S', 'M'], $material->variants()->otrasVariantes()->pluck('nombre')->all());
        $this->assertTrue((bool) $material->maneja_color);
        $this->assertTrue((bool) $material->maneja_otra_variante);
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
