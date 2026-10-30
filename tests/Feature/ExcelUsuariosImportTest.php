<?php

namespace Tests\Feature;

use App\Jobs\ImportarUsuariosJob;
use App\Models\Role;
use App\Models\User;
use App\Services\ExcelUsuariosService;
use App\Services\ImportacionExcelNotificador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Tests\TestCase;

/**
 * La carga masiva de usuarios exige nombre, email, identificación y rol; el
 * resto de campos del formulario son opcionales.
 */
class ExcelUsuariosImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'trabajador', 'guard_name' => 'web']);
        Role::create(['name' => 'admin', 'guard_name' => 'web']);
    }

    /** Crea un .xlsx temporal con la fila de títulos y las filas de datos. */
    private function archivo(array $titulos, array $filas): string
    {
        $spreadsheet = new Spreadsheet();
        $hoja = $spreadsheet->getActiveSheet();
        $hoja->fromArray($titulos, null, 'A1');
        $hoja->fromArray($filas, null, 'A2');

        $ruta = tempnam(sys_get_temp_dir(), 'usuarios') . '.xlsx';
        (new Xlsx($spreadsheet))->save($ruta);

        return $ruta;
    }

    public function test_importa_los_campos_del_usuario_y_le_asigna_el_rol(): void
    {
        $ruta = $this->archivo(
            ['Nombre', 'Email', 'Identificación', 'Rol', 'Área', 'Cargo', 'Celular', 'Sexo', 'Fecha nacimiento', 'Salario', 'Fecha de ingreso'],
            [
                ['Ana Restrepo', 'ana@macondas.com', '1020304050', 'trabajador', 'Producción', 'Operaria', '3001234567', 'Femenino', '1990-05-14', '1300000', '2024-01-15'],
                ['Luis Pérez', 'luis@macondas.com', '1098765432', 'admin', 'Administración', 'Analista', '3109876543', 'Masculino', '1985-11-02', '2500000', '2023-08-01'],
            ]
        );

        $excel = app(ExcelUsuariosService::class);
        $resultado = $excel->importar($excel->leerRegistros($ruta));

        $this->assertSame(2, $resultado['creados']);
        $this->assertSame(0, $resultado['actualizados']);

        $ana = User::where('email', 'ana@macondas.com')->firstOrFail();
        $this->assertSame('Ana Restrepo', $ana->name);
        $this->assertSame('1020304050', $ana->identificacion);
        $this->assertSame('Producción', $ana->area);
        $this->assertSame('Femenino', $ana->sexo);
        $this->assertSame('1990-05-14', $ana->fecha_nacimiento);
        $this->assertTrue(Hash::check('1020304050*', $ana->password));
        $this->assertTrue($ana->hasRole('trabajador'));

        $luis = User::where('email', 'luis@macondas.com')->firstOrFail();
        $this->assertTrue($luis->hasRole('admin'));
    }

    public function test_actualiza_el_usuario_existente_sin_borrar_los_datos_vacios(): void
    {
        $usuario = User::factory()->create([
            'name' => 'Ana Restrepo',
            'email' => 'ana@macondas.com',
            'identificacion' => '1020304050',
            'area' => 'Producción',
            'cargo' => 'Operaria',
        ]);
        $usuario->assignRole('trabajador');

        $ruta = $this->archivo(
            ['Nombre', 'Email', 'Identificación', 'Rol', 'Área', 'Cargo'],
            [['Ana Restrepo', 'ana@macondas.com', '1020304050', 'admin', '', '']]
        );

        $excel = app(ExcelUsuariosService::class);
        $resultado = $excel->importar($excel->leerRegistros($ruta));

        $this->assertSame(0, $resultado['creados']);
        $this->assertSame(1, $resultado['actualizados']);
        $this->assertSame(1, User::count());

        $usuario->refresh();
        $this->assertSame('Producción', $usuario->area);
        $this->assertSame('Operaria', $usuario->cargo);
        $this->assertTrue($usuario->hasRole('admin'));
        $this->assertFalse($usuario->hasRole('trabajador'));
    }

    public function test_exige_las_columnas_obligatorias(): void
    {
        $ruta = $this->archivo(
            ['Nombre', 'Email', 'Identificación'],
            [['Ana Restrepo', 'ana@macondas.com', '1020304050']]
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('faltan las obligatorias');

        app(ExcelUsuariosService::class)->leerRegistros($ruta);
    }

    public function test_rechaza_un_rol_que_no_existe(): void
    {
        $ruta = $this->archivo(
            ['Nombre', 'Email', 'Identificación', 'Rol'],
            [['Ana Restrepo', 'ana@macondas.com', '1020304050', 'superpoder']]
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('el rol "superpoder" no existe');

        app(ExcelUsuariosService::class)->leerRegistros($ruta);
    }

    public function test_rechaza_un_email_invalido(): void
    {
        $ruta = $this->archivo(
            ['Nombre', 'Email', 'Identificación', 'Rol'],
            [['Ana Restrepo', 'no-es-un-correo', '1020304050', 'trabajador']]
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('email no es un correo válido');

        app(ExcelUsuariosService::class)->leerRegistros($ruta);
    }

    public function test_el_job_importa_y_crea_la_notificacion_al_finalizar(): void
    {
        Storage::fake('local');

        $ruta = $this->archivo(
            ['Nombre', 'Email', 'Identificación', 'Rol'],
            [['Ana Restrepo', 'ana@macondas.com', '1020304050', 'trabajador']]
        );
        $rutaRelativa = 'imports/usuarios/usuarios.xlsx';
        Storage::disk('local')->put($rutaRelativa, file_get_contents($ruta));

        $job = new ImportarUsuariosJob($rutaRelativa, 'usuarios.xlsx', null, null);
        $job->handle(
            app(ExcelUsuariosService::class),
            app(ImportacionExcelNotificador::class),
        );

        $this->assertDatabaseHas('users', ['email' => 'ana@macondas.com']);
        $this->assertDatabaseHas('notificaciones', [
            'mensaje' => 'La lectura de los usuarios en el archivo de excel ha finalizado',
            'prioridad' => 'alta',
        ]);
    }

    public function test_la_ruta_encola_el_job_de_importacion(): void
    {
        Queue::fake();
        Storage::fake('local');

        $usuario = User::factory()->create();
        $usuario->assignRole('admin');

        $ruta = $this->archivo(
            ['Nombre', 'Email', 'Identificación', 'Rol'],
            [['Ana Restrepo', 'ana@macondas.com', '1020304050', 'trabajador']]
        );
        $archivo = new UploadedFile(
            $ruta,
            'usuarios.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );

        $response = $this->actingAs($usuario)->post(route('user.upload-excel'), [
            'archivo' => $archivo,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        Queue::assertPushed(ImportarUsuariosJob::class);
    }

    public function test_la_plantilla_de_usuarios_trae_las_columnas_y_dos_ejemplos(): void
    {
        $spreadsheet = app(\App\Services\PlantillasExcelService::class)->spreadsheet('usuario');

        $this->assertNotNull($spreadsheet);
        $this->assertSame('Usuarios', $spreadsheet->getActiveSheet()->getTitle());

        $filas = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

        $this->assertSame('Nombre', $filas[0][0]);
        $this->assertSame('Email', $filas[0][1]);
        $this->assertCount(3, $filas, 'Encabezados y dos filas de ejemplo');
    }
}
