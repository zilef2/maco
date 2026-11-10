<?php

namespace App\Services;

use App\Models\ImportacionExcel;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mide y registra las cargas masivas por Excel.
 *
 * Cada archivo deja una fila en importacion_excels: la duración de la petición
 * (lo que espera el usuario) y la duración total, que en las entidades
 * procesadas en segundo plano incluye la espera y el trabajo del job.
 */
class ExcelImportacionTracker
{
    /** Entidades con carga por Excel y métricas propias. */
    public const ENTIDADES = [
        'producto',
        'material',
        'estado_operacion',
        'taller',
        'proveedor',
        'usuario',
        'mpp',
        'ficha_tecnica_materiales',
    ];

    /** Entidades cuyo procesamiento pesado corre en la cola. */
    public const ASINCRONAS = ['taller', 'proveedor', 'usuario', 'mpp', 'ficha_tecnica_materiales'];

    private const ENTIDAD_POR_RUTA = [
        'Producto.upload-excel' => 'producto',
        'Material.upload-excel' => 'material',
        'EstadoOperacion.upload-excel' => 'estado_operacion',
        'Proveedor.upload-terceros-excel' => 'taller',
        'Proveedor.upload-excel' => 'proveedor',
        'user.upload-excel' => 'usuario',
        'ImportarExcel.mpp' => 'mpp',
        'ImportarExcel.ficha-producto' => 'ficha_tecnica_materiales',
    ];

    /** Cuántas cargas recientes entran en el promedio de la vista. */
    public const MUESTRAS_PROMEDIO = 5;

    public static function entidadDeRuta(?string $nombreRuta): ?string
    {
        return $nombreRuta === null ? null : (self::ENTIDAD_POR_RUTA[$nombreRuta] ?? null);
    }

    public static function esAsincrona(string $entidad): bool
    {
        return in_array($entidad, self::ASINCRONAS, true);
    }

    /**
     * Crea el registro al comenzar la petición. Nunca lanza: una métrica no
     * puede tumbar la carga del archivo.
     */
    public function iniciar(string $entidad, ?string $archivo, ?int $userId): ?ImportacionExcel
    {
        try {
            $registro = ImportacionExcel::create([
                'entidad' => $entidad,
                'archivo' => $archivo !== null ? mb_substr($archivo, 0, 255) : null,
                'user_id' => $userId,
                'estado' => ImportacionExcel::ESTADO_PENDIENTE,
                'iniciado_en' => microtime(true),
            ]);

            Log::info('Importación Excel iniciada', [
                'importacion_id' => $registro->id,
                'entidad' => $entidad,
                'archivo' => $archivo,
                'usuario_id' => $userId,
            ]);

            return $registro;
        } catch (Throwable $e) {
            Log::warning('No se pudo registrar el inicio de la importación Excel: '.$e->getMessage(), [
                'entidad' => $entidad,
                'archivo' => $archivo,
            ]);

            return null;
        }
    }

    /**
     * Cierra la parte de la petición HTTP. En entidades asíncronas deja el
     * registro en "procesando" para que el job lo complete después.
     */
    public function marcarSubida(?ImportacionExcel $registro, float $inicio, ?int $filas = null): void
    {
        if ($registro === null) {
            return;
        }

        try {
            $subidaMs = max(0, (int) round((microtime(true) - $inicio) * 1000));

            $registro->forceFill([
                'subida_ms' => $subidaMs,
                'filas' => $filas ?? $registro->filas,
            ])->save();

            if (self::esAsincrona($registro->entidad)) {
                // Condicional: si el job ya terminó, no se retrocede el estado.
                ImportacionExcel::whereKey($registro->id)
                    ->where('estado', ImportacionExcel::ESTADO_PENDIENTE)
                    ->update(['estado' => ImportacionExcel::ESTADO_PROCESANDO]);
            }

            Log::info('Importación Excel: archivo recibido', [
                'importacion_id' => $registro->id,
                'entidad' => $registro->entidad,
                'archivo' => $registro->archivo,
                'subida_ms' => $subidaMs,
                'filas' => $filas,
            ]);
        } catch (Throwable $e) {
            Log::warning('No se pudo registrar la subida del Excel: '.$e->getMessage(), [
                'entidad' => $registro->entidad,
            ]);
        }
    }

    /**
     * Marca la importación como completada y calcula la duración total.
     *
     * @param  array{creados?: int, actualizados?: int}  $resultado
     */
    public function completar(?int $importacionId, array $resultado = [], ?int $filas = null): void
    {
        if ($importacionId === null) {
            return;
        }

        try {
            $registro = ImportacionExcel::find($importacionId);
            if ($registro === null) {
                return;
            }

            $duracionMs = $this->duracionTotal($registro);

            $registro->forceFill([
                'estado' => ImportacionExcel::ESTADO_COMPLETADO,
                'duracion_ms' => $duracionMs,
                'creados' => $resultado['creados'] ?? $registro->creados,
                'actualizados' => $resultado['actualizados'] ?? $registro->actualizados,
                'filas' => $filas ?? $registro->filas,
                'error' => null,
            ])->save();

            Log::info('Importación Excel completada', [
                'importacion_id' => $registro->id,
                'entidad' => $registro->entidad,
                'archivo' => $registro->archivo,
                'duracion_ms' => $duracionMs,
                'subida_ms' => $registro->subida_ms,
                'creados' => $registro->creados,
                'actualizados' => $registro->actualizados,
                'filas' => $registro->filas,
            ]);
        } catch (Throwable $e) {
            Log::warning('No se pudo registrar el fin de la importación Excel: '.$e->getMessage(), [
                'importacion_id' => $importacionId,
            ]);
        }
    }

    /**
     * Marca la importación como fallida conservando el mensaje para la vista.
     */
    public function fallar(int|ImportacionExcel|null $registro, string $error): void
    {
        if ($registro === null) {
            return;
        }

        try {
            $modelo = $registro instanceof ImportacionExcel
                ? $registro
                : ImportacionExcel::find($registro);

            if ($modelo === null) {
                return;
            }

            $duracionMs = $this->duracionTotal($modelo);

            $modelo->forceFill([
                'estado' => ImportacionExcel::ESTADO_FALLIDO,
                'duracion_ms' => $duracionMs,
                'error' => mb_substr($error, 0, 5000),
            ])->save();

            Log::warning('Importación Excel fallida', [
                'importacion_id' => $modelo->id,
                'entidad' => $modelo->entidad,
                'archivo' => $modelo->archivo,
                'duracion_ms' => $duracionMs,
                'subida_ms' => $modelo->subida_ms,
                'error' => $error,
            ]);
        } catch (Throwable $e) {
            Log::warning('No se pudo registrar el error de la importación Excel: '.$e->getMessage());
        }
    }

    /**
     * Promedio de las últimas cargas completadas de cada entidad.
     *
     * @return array<string, array{muestras: int, promedio_ms: ?int, promedio_subida_ms: ?int, ultima_en: ?string, ultimo_archivo: ?string}>
     */
    public function promedios(int $muestras = self::MUESTRAS_PROMEDIO): array
    {
        $resultado = [];

        foreach (self::ENTIDADES as $entidad) {
            try {
                $filas = ImportacionExcel::query()
                    ->where('entidad', $entidad)
                    ->where('estado', ImportacionExcel::ESTADO_COMPLETADO)
                    ->whereNotNull('duracion_ms')
                    ->orderByDesc('id')
                    ->limit($muestras)
                    ->get(['duracion_ms', 'subida_ms', 'archivo', 'created_at']);
            } catch (Throwable) {
                $filas = collect();
            }

            $cantidad = $filas->count();
            $ultima = $filas->first();

            $resultado[$entidad] = [
                'muestras' => $cantidad,
                'promedio_ms' => $cantidad > 0
                    ? (int) round($filas->avg('duracion_ms'))
                    : null,
                'promedio_subida_ms' => $cantidad > 0
                    ? (int) round($filas->avg('subida_ms'))
                    : null,
                'ultima_en' => $ultima?->created_at?->toIso8601String(),
                'ultimo_archivo' => $ultima?->archivo,
            ];
        }

        return $resultado;
    }

    private function duracionTotal(ImportacionExcel $registro): int
    {
        $inicio = (float) $registro->iniciado_en;
        if ($inicio <= 0) {
            return (int) ($registro->subida_ms ?? 0);
        }

        return max(0, (int) round((microtime(true) - $inicio) * 1000));
    }
}
