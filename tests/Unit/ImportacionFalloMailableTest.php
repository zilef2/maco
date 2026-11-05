<?php

namespace Tests\Unit;

use App\Mail\ImportacionFalloMailable;
use Tests\TestCase;

class ImportacionFalloMailableTest extends TestCase
{
    public function test_it_builds_envelope_and_renders_content_properly(): void
    {
        $mailable = new ImportacionFalloMailable(
            titulo: 'Falló la importación de MPP (materiales, productos y proveedores)',
            mensaje: 'Detalle del problema',
            detalles: [
                'Entidad' => 'MPP (materiales, productos y proveedores)',
                'Archivo' => 'MPP (2).xlsx',
                'Usuario' => 'Superadmin',
            ],
            errorMensaje: 'Fila 31: el campo nit es obligatorio.',
            resumenIa: "1. Dónde está el error: ExcelProveedoresService.php:222\n2. Qué parece ser: Falta el NIT.",
            traza: '#0 C:\laragon\www\maco\app\Services\ExcelMPPService.php(102)',
        );

        $this->assertEquals('Falló la importación de MPP (materiales, productos y proveedores)', $mailable->envelope()->subject);

        $rendered = $mailable->render();

        $this->assertStringContainsString('Falló la importación de MPP (materiales, productos y proveedores)', $rendered);
        $this->assertStringContainsString('max-width: 1120px', $rendered);
        $this->assertStringContainsString('MPP (2).xlsx', $rendered);
        $this->assertStringContainsString('Superadmin', $rendered);
        $this->assertStringContainsString('Fila 31: el campo nit es obligatorio.', $rendered);
        $this->assertStringContainsString('Diagnóstico y Causa Raíz', $rendered);
        $this->assertStringContainsString('ExcelProveedoresService.php:222', $rendered);
        $this->assertStringContainsString('ExcelMPPService.php(102)', $rendered);
    }
}

