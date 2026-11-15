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
            errorTitulo: 'Fila 31',
            errorDetalle: 'el campo nit es obligatorio.',
            resumenIa: "1. Dónde está el error: ExcelProveedoresService.php:222\n2. Qué parece ser: Falta el NIT.",
            traza: '#0 C:\laragon\www\maco\app\Services\ExcelMPPService.php(102)',
        );

        $this->assertEquals('Falló la importación de MPP (materiales, productos y proveedores)', $mailable->envelope()->subject);

        $rendered = $mailable->render();

        $this->assertStringContainsString('Falló la importación de MPP (materiales, productos y proveedores)', $rendered);
        $this->assertStringContainsString('max-width: 1120px', $rendered);
        $this->assertStringContainsString('MPP (2).xlsx', $rendered);
        $this->assertStringContainsString('Superadmin', $rendered);
        $this->assertStringContainsString('Error Detectado', $rendered);
        $this->assertStringContainsString('Fila 31', $rendered);
        $this->assertStringContainsString('el campo nit es obligatorio.', $rendered);
        $this->assertStringContainsString('Diagnóstico y Causa Raíz', $rendered);
        $this->assertStringContainsString('ExcelProveedoresService.php:222', $rendered);
        $this->assertStringContainsString('ExcelMPPService.php(102)', $rendered);
    }

    public function test_it_renders_error_title_detail_and_item_list(): void
    {
        $mailable = new ImportacionFalloMailable(
            titulo: 'Falló la importación de fichas de producto',
            mensaje: 'Detalle del problema',
            errorMensaje: 'No se pudo guardar la ficha de PT-042: La ficha técnica solo admite materiales genéricos: TEX-FLO-6, SUE-EVA-15, COM-CAP-1.',
            errorTitulo: 'No se pudo guardar la ficha de PT-042',
            errorDetalle: 'La ficha técnica solo admite materiales genéricos.',
            errorLista: ['TEX-FLO-6', 'SUE-EVA-15', 'COM-CAP-1'],
        );

        $rendered = $mailable->render();

        $this->assertStringContainsString('No se pudo guardar la ficha de PT-042', $rendered);
        $this->assertStringContainsString('La ficha técnica solo admite materiales genéricos.', $rendered);
        $this->assertStringContainsString('<li style="margin-bottom: 2px;">TEX-FLO-6</li>', $rendered);
        $this->assertStringContainsString('<li style="margin-bottom: 2px;">SUE-EVA-15</li>', $rendered);
        $this->assertStringContainsString('<li style="margin-bottom: 2px;">COM-CAP-1</li>', $rendered);
    }
}

