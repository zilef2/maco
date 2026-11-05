<?php

namespace Tests\Unit;

use App\Services\GeminiErrorAnalyzerService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiErrorAnalyzerServiceTest extends TestCase
{
    public function test_resumir_error_returns_summary_when_api_call_succeeds(): void
    {
        Config::set('services.gemini.api_key', 'test-api-key');
        Config::set('services.gemini.model', 'gemini-3.7-flash');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => "1. Dónde está el error: ExcelMPPService.php:102\n2. Qué parece ser: Error de validación en la fila 31."],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $servicio = new GeminiErrorAnalyzerService();
        $resultado = $servicio->resumirError('#0 C:\laragon\www\maco\app\Services\ExcelMPPService.php(102): App\Services\ExcelMPPService->');

        $this->assertNotNull($resultado);
        $this->assertStringContainsString('ExcelMPPService.php:102', $resultado);
        $this->assertStringContainsString('Error de validación', $resultado);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'gemini-3.7-flash')
                && $request->hasHeader('x-goog-api-key', 'test-api-key');
        });
    }

    public function test_resumir_error_returns_null_when_api_key_is_missing(): void
    {
        Config::set('services.gemini.api_key', null);

        Http::fake();

        $servicio = new GeminiErrorAnalyzerService();
        $resultado = $servicio->resumirError('Cualquier error');

        $this->assertNull($resultado);
        Http::assertNothingSent();
    }

    public function test_resumir_error_returns_null_when_api_returns_error(): void
    {
        Config::set('services.gemini.api_key', 'test-api-key');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => 'Quota exceeded'], 429),
        ]);

        $servicio = new GeminiErrorAnalyzerService();
        $resultado = $servicio->resumirError('Error de prueba');

        $this->assertNull($resultado);
    }

    public function test_analizar_excepcion_formats_message_and_trace(): void
    {
        Config::set('services.gemini.api_key', 'test-api-key');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'Diagnóstico generado'],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $servicio = new GeminiErrorAnalyzerService();
        $resultado = $servicio->analizarExcepcion('Error de prueba', "#0 C:\\path\\file.php(10): method()\n#1 C:\\path\\file2.php(20): run()");

        $this->assertEquals('Diagnóstico generado', $resultado);
        Http::assertSent(function ($request) {
            $cuerpo = json_encode($request->data());
            return str_contains($cuerpo, 'Error de prueba') && str_contains($cuerpo, 'file.php(10)');
        });
    }
}

