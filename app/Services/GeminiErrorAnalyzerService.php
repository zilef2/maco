<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GeminiErrorAnalyzerService
{
    public function __construct(
        private readonly ?string $apiKey = null,
        private readonly ?string $modelo = null,
        private readonly ?string $baseUrl = null,
    ) {
    }

    public function resumirError(string $texto): ?string
    {
        $key = $this->apiKey ?? config('services.gemini.api_key');
        if (empty($key)) {
            return null;
        }

        $modelo = $this->modelo ?? config('services.gemini.model', 'gemini-3.7-flash');
        $baseUrl = $this->baseUrl ?? config('services.gemini.url', 'https://generativelanguage.googleapis.com/v1beta');

        $prompt = "Eres un analista de software experto en Laravel y PHP.\n"
            . "Analiza el siguiente extracto técnico de error y traza de ejecución:\n\n"
            . "{$texto}\n\n"
            . "Responde de forma muy clara, concisa y directa con exactamente estos dos puntos:\n"
            . "1. **Dónde está el error:** (indica archivo, método y número de línea exacta detectada en la traza).\n"
            . "2. **Qué parece ser:** (explica con palabras sencillas cuál es la causa más probable del problema y qué dato o condición lo detonó).\n"
            . "Reglas: no menciones pantallas, vistas, paneles, módulos ni botones de uso interno o exclusivo del desarrollador "
            . "(por ejemplo, la vista «Actividad»); describe el problema solo en términos del dato, del archivo o del proceso de importación. "
            . "No agregues saludos ni despedidas.";

        try {
            $url = "{$baseUrl}/models/{$modelo}:generateContent?key={$key}";
            $respuesta = Http::withHeaders([
                'x-goog-api-key' => $key,
                'Content-Type' => 'application/json',
            ])->timeout(20)->post($url, [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
            ]);

            if ($respuesta->failed()) {
                Log::warning('Error consultando Gemini API: ' . $respuesta->status() . ' - ' . $respuesta->body());
                return null;
            }

            return $respuesta->json('candidates.0.content.parts.0.text');
        } catch (Throwable $e) {
            Log::warning('Fallo de conexión con servicio Gemini: ' . $e->getMessage());
            return null;
        }
    }

    public function analizarExcepcion(string $mensaje, ?string $traza = null): ?string
    {
        $extractoTraza = '';
        if (!empty($traza)) {
            $lineas = explode("\n", $traza);
            $primerasLineas = array_slice($lineas, 0, 15);
            $extractoTraza = implode("\n", $primerasLineas);
        }

        $contenido = "Mensaje: {$mensaje}";
        if (!empty($extractoTraza)) {
            $contenido .= "\n\nTraza:\n{$extractoTraza}";
        }

        return $this->resumirError($contenido);
    }
}

