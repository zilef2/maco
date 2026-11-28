<?php

namespace App\Jobs;

use App\Services\ExcelMaterialesService;
use App\Services\MaterialIndexCacheService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Recalienta la caché del listado de Materiales después de una escritura.
 *
 * Se encola desde ReconstruirListadoMateriales con un retraso, y solo uno por
 * tanda de cambios, para que un Excel de mil filas no reconstruya el listado mil
 * veces. Que corra en la cola y no en la petición es el punto: el guardado del
 * material no espera a la tabla, y el usuario que abre la vista después se la
 * encuentra lista.
 *
 * Antes de recalentar vuelve a subir la versión del listado. Si la escritura que
 * lo disparó estaba metida en una transacción larga, algún request pudo ya
 * armar y cachear un listado que no la veía; el segundo corte deja afuera esa
 * ventana y lo que queda caliente es la versión buena.
 */
class ReconstruirCacheMaterialesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Llave que dice que ya hay una recarga programada para la tanda actual. */
    public const CLAVE_PENDIENTE = 'material_reconstruccion_pendiente';

    /** Sin reintentos: recalentar es idempotente y la caché se reconstruye sola si hace falta. */
    public int $tries = 1;

    public int $timeout = 120;

    public function handle(MaterialIndexCacheService $cache, ExcelMaterialesService $excel): void
    {
        // Libre desde aquí: lo que se escriba mientras dura esta recarga programa
        // su propia ronda, en vez de perderse detrás de una marca vieja.
        Cache::forget(self::CLAVE_PENDIENTE);

        $excel->invalidarCacheListados();

        Log::info('Caché del listado de Materiales recalentada', $cache->recalentar());
    }
}
