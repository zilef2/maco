<?php

namespace Tests\Unit;

use App\Mail\ImportacionExcelMailable;
use App\Mail\ImportacionFalloMailable;
use App\Services\GeminiErrorAnalyzerService;
use App\Services\ImportacionExcelNotificador;
use Exception;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ImportacionExcelNotificadorTest extends TestCase
{
    public function test_fallo_sends_fallo_mailable_with_ai_summary_to_admin(): void
    {
        Mail::fake();

        $geminiMock = $this->createMock(GeminiErrorAnalyzerService::class);
        $geminiMock->expects($this->once())
            ->method('analizarExcepcion')
            ->willReturn("1. Dónde está el error: ExcelMPPService.php\n2. Qué parece ser: Error de datos.");

        $notificador = new ImportacionExcelNotificador($geminiMock);
        $notificador->fallo(
            'user@example.com',
            'Superadmin',
            'MPP (2).xlsx',
            'MPP (materiales, productos y proveedores)',
            new Exception('Fila 31: el campo nit es obligatorio.'),
        );

        Mail::assertSent(ImportacionFalloMailable::class, function (ImportacionFalloMailable $mail) {
            return $mail->hasTo('ajelof2@gmail.com')
                && $mail->titulo === 'Falló la importación de MPP (materiales, productos y proveedores)'
                && $mail->errorMensaje === 'Fila 31: el campo nit es obligatorio.'
                && $mail->resumenIa !== null;
        });

        Mail::assertSent(ImportacionExcelMailable::class, function (ImportacionExcelMailable $mail) {
            return $mail->hasTo('user@example.com');
        });
    }

    public function test_exito_sends_excel_mailable_to_user(): void
    {
        Mail::fake();

        $notificador = new ImportacionExcelNotificador();
        $notificador->exito('user@example.com', 'proveedores', ['creados' => 5, 'actualizados' => 2]);

        Mail::assertSent(ImportacionExcelMailable::class, function (ImportacionExcelMailable $mail) {
            return $mail->hasTo('user@example.com')
                && str_contains($mail->mensaje, '5 registro(s)');
        });
    }
}

