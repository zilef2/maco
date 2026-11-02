<?php

namespace Tests\Feature;

use App\Models\ImportacionExcel;
use App\Services\ExcelImportacionTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Feature\Concerns\ContextoMateriales;
use Tests\TestCase;

/**
 * Las cargas de Excel dejan su duración en importacion_excels y la vista
 * /ImportarExcel muestra el promedio de las últimas cargas de cada entidad.
 */
class ExcelImportacionMetricasTest extends TestCase
{
    use ContextoMateriales;
    use RefreshDatabase;

    private function archivoProducto(): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $hoja = $spreadsheet->getActiveSheet();
        $hoja->fromArray(['Codigo_interno', 'Titulo_Shopify'], null, 'A1');
        $hoja->fromArray([['P-METRICA-1', 'Producto de prueba']], null, 'A2');

        $ruta = tempnam(sys_get_temp_dir(), 'productos_').'.xlsx';
        (new Xlsx($spreadsheet))->save($ruta);

        return new UploadedFile(
            $ruta,
            'productos.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );
    }

    public function test_una_carga_sincrona_registra_su_duracion(): void
    {
        $usuario = $this->usuarioAdmin();

        $response = $this->actingAs($usuario)->post(route('Producto.upload-excel'), [
            'archivo' => $this->archivoProducto(),
        ]);

        $response->assertSessionHas('success');

        $registro = ImportacionExcel::where('entidad', 'producto')->first();

        $this->assertNotNull($registro);
        $this->assertSame(ImportacionExcel::ESTADO_COMPLETADO, $registro->estado);
        $this->assertNotNull($registro->subida_ms);
        $this->assertNotNull($registro->duracion_ms);
        $this->assertSame($usuario->id, $registro->user_id);
    }

    public function test_el_promedio_usa_las_ultimas_cargas_de_cada_entidad(): void
    {
        foreach ([1000, 2000, 3000] as $duracion) {
            ImportacionExcel::create([
                'entidad' => 'producto',
                'estado' => ImportacionExcel::ESTADO_COMPLETADO,
                'archivo' => 'productos.xlsx',
                'subida_ms' => $duracion,
                'duracion_ms' => $duracion,
                'iniciado_en' => microtime(true),
            ]);
        }

        $promedios = app(ExcelImportacionTracker::class)->promedios();

        $this->assertSame(3, $promedios['producto']['muestras']);
        $this->assertSame(2000, $promedios['producto']['promedio_ms']);
        $this->assertNull($promedios['proveedor']['promedio_ms']);
    }

    public function test_la_vista_de_importacion_recibe_la_metrica(): void
    {
        $usuario = $this->usuarioAdmin();

        ImportacionExcel::create([
            'entidad' => 'producto',
            'estado' => ImportacionExcel::ESTADO_COMPLETADO,
            'archivo' => 'productos.xlsx',
            'subida_ms' => 1500,
            'duracion_ms' => 1500,
            'iniciado_en' => microtime(true),
        ]);

        $response = $this->actingAs($usuario)->get(route('ImportarExcel.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Importar/Index')
            ->where('entidades.0.id', 'producto')
            ->where('entidades.0.metrica.promedio_ms', 1500)
            ->where('entidades.0.metrica.muestras', 1));
    }
}
