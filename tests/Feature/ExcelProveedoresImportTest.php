<?php

namespace Tests\Feature;

use App\Jobs\ImportarProveedoresJob;
use App\Models\Proveedor;
use App\Services\ExcelProveedoresService;
use App\Services\ImportacionExcelNotificador;
use App\Services\PlantillasExcelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Tests\TestCase;

/**
 * La carga masiva de proveedores usa el mismo formato que el formulario:
 * NIT y nombre comercial obligatorios y el resto de campos del proveedor.
 */
class ExcelProveedoresImportTest extends TestCase
{
    use RefreshDatabase;

    /** Crea un .xlsx temporal con la fila de títulos y las filas de datos. */
    private function archivo(array $titulos, array $filas): string
    {
        $spreadsheet = new Spreadsheet();
        $hoja = $spreadsheet->getActiveSheet();
        $hoja->fromArray($titulos, null, 'A1');
        $hoja->fromArray($filas, null, 'A2');

        $ruta = tempnam(sys_get_temp_dir(), 'proveedores') . '.xlsx';
        (new Xlsx($spreadsheet))->save($ruta);

        return $ruta;
    }

    public function test_importa_los_campos_nuevos_del_proveedor(): void
    {
        $ruta = $this->archivo(
            ['NIT', 'Nombre comercial', 'Razón social', 'Correo electrónico', 'Ciudad', 'Tipo proveedor', 'Qué suministra', 'Celular contacto', 'Forma de pago', 'Tiempo entrega', 'Estado', 'Clasificación'],
            [
                ['900.123.456-7', 'Proveedor ABC', 'ABC S.A.S.', 'contacto@abc.com', 'Medellín', 'Materia prima', 'Telas y botones', '3001234567', 'Crédito 30 días', '30', 'Activo', 'proveedor'],
                ['901.234.567-8', 'Textiles del Norte', 'Textiles del Norte S.A.S.', 'ventas@norte.com', 'Bogotá', 'Servicio', 'Servicio de confección', '3109876543', 'Contado', '15', 'Inactivo', 'taller de proceso externo'],
            ]
        );

        $excel = app(ExcelProveedoresService::class);
        $registros = $excel->leerRegistros($ruta);
        $resultado = $excel->importar($registros);

        $this->assertSame(2, $resultado['creados']);
        $this->assertSame(0, $resultado['actualizados']);

        $proveedor = Proveedor::where('nit', '900.123.456-7')->firstOrFail();
        $this->assertSame('Proveedor ABC', $proveedor->nombre_comercial);
        $this->assertSame('ABC S.A.S.', $proveedor->razon_social);
        $this->assertSame('contacto@abc.com', $proveedor->correo_electronico);
        $this->assertSame('Medellín', $proveedor->ciudad);
        $this->assertSame('Materia prima', $proveedor->tipo_proveedor);
        $this->assertSame('Telas y botones', $proveedor->que_suministra);
        $this->assertSame('3001234567', $proveedor->celular_contacto);
        $this->assertSame('Crédito 30 días', $proveedor->forma_pago);
        $this->assertSame('30', $proveedor->tiempo_entrega);
        $this->assertSame('Activo', $proveedor->estado);
        $this->assertSame('proveedor', $proveedor->clasificacion);
        $this->assertNotNull($proveedor->codigo_proveedor);
    }

    public function test_actualiza_el_proveedor_existente_sin_borrar_los_datos_vacios(): void
    {
        $proveedor = Proveedor::factory()->create([
            'nit' => '900.123.456-7',
            'nombre_comercial' => 'Proveedor ABC',
            'celular_contacto' => '3001112222',
            'ciudad' => 'Cali',
        ]);

        // Mismo NIT escrito de otra forma y celdas vacías en las columnas
        // opcionales: el registro se actualiza y no se duplica.
        $ruta = $this->archivo(
            ['NIT', 'Nombre comercial', 'Celular contacto', 'Ciudad'],
            [['9001234567', 'Proveedor ABC', '', '']]
        );

        $excel = app(ExcelProveedoresService::class);
        $resultado = $excel->importar($excel->leerRegistros($ruta));

        $this->assertSame(0, $resultado['creados']);
        $this->assertSame(1, $resultado['actualizados']);
        $this->assertSame(1, Proveedor::count());

        $proveedor->refresh();
        $this->assertSame('3001112222', $proveedor->celular_contacto);
        $this->assertSame('Cali', $proveedor->ciudad);
    }

    public function test_exige_nit_y_nombre_comercial(): void
    {
        $ruta = $this->archivo(
            ['Nombre comercial', 'Ciudad'],
            [['Proveedor ABC', 'Medellín']]
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('faltan las obligatorias');

        app(ExcelProveedoresService::class)->leerRegistros($ruta);
    }

    public function test_rechaza_valores_fuera_de_las_listas_permitidas(): void
    {
        $ruta = $this->archivo(
            ['NIT', 'Nombre comercial', 'Estado'],
            [['900.123.456-7', 'Proveedor ABC', 'Ludicrous']]
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('estado debe ser uno de');

        app(ExcelProveedoresService::class)->leerRegistros($ruta);
    }

    public function test_rechaza_un_correo_invalido(): void
    {
        $ruta = $this->archivo(
            ['NIT', 'Nombre comercial', 'Correo electrónico'],
            [['900.123.456-7', 'Proveedor ABC', 'no-es-un-correo']]
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('correo_electronico no es un correo válido');

        app(ExcelProveedoresService::class)->leerRegistros($ruta);
    }

    public function test_el_job_importa_y_crea_la_notificacion_al_finalizar(): void
    {
        Storage::fake('local');

        $ruta = $this->archivo(
            ['NIT', 'Nombre comercial'],
            [['9001234567', 'Proveedor Notificado']]
        );
        $rutaRelativa = 'imports/proveedores/proveedores.xlsx';
        Storage::disk('local')->put($rutaRelativa, file_get_contents($ruta));

        $registro = \App\Models\ImportacionExcel::create([
            'entidad' => 'proveedor',
            'archivo' => 'proveedores.xlsx',
            'estado' => \App\Models\ImportacionExcel::ESTADO_PENDIENTE,
            'iniciado_en' => microtime(true),
        ]);

        $job = new ImportarProveedoresJob($rutaRelativa, 'proveedores.xlsx', null, null, null, $registro->id);
        $job->handle(
            app(ExcelProveedoresService::class),
            app(ImportacionExcelNotificador::class),
            app(\App\Services\ExcelImportacionTracker::class),
        );

        $this->assertDatabaseHas('proveedors', ['nombre_comercial' => 'Proveedor Notificado']);
        $this->assertDatabaseHas('notificaciones', [
            'mensaje' => 'La lectura de los proveedores en el archivo de excel ha finalizado',
            'prioridad' => 'alta',
        ]);

        $registro->refresh();
        $this->assertSame(\App\Models\ImportacionExcel::ESTADO_COMPLETADO, $registro->estado);
        $this->assertSame(1, $registro->creados);
        $this->assertNotNull($registro->duracion_ms);
    }

    public function test_la_plantilla_trae_las_columnas_y_dos_ejemplos(): void
    {
        $spreadsheet = app(PlantillasExcelService::class)->spreadsheet('proveedor');

        $this->assertNotNull($spreadsheet);
        $this->assertSame('Proveedores', $spreadsheet->getActiveSheet()->getTitle());

        $filas = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

        $this->assertSame('NIT', $filas[0][0]);
        $this->assertSame('Nombre comercial', $filas[0][1]);
        $this->assertCount(3, $filas, 'Encabezados y dos filas de ejemplo');
        $this->assertNotEmpty($filas[1][0]);
        $this->assertNotEmpty($filas[2][0]);
    }
}
