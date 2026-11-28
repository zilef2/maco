<?php

namespace App\Listeners;

use App\Events\MaterialesCambiados;
use App\Jobs\ReconstruirCacheMaterialesJob;
use App\Services\ExcelMaterialesService;
use Illuminate\Support\Facades\Cache;

/**
 * Escucha los cambios de materiales: corta el listado en caché al instante y
 * deja una sola recarga programada para el bloque de escrituras.
 *
 * No hay que registrarlo en ningún provider: Laravel descubre los listeners de
 * app/Listeners por el type-hint de handle(). Registrarlo además a mano haría que
 * corra dos veces por evento.
 *
 * La consistencia no depende del job. Subir la versión antes de cualquier otra
 * cosa hace que todas las ventanas cacheadas del listado queden huérfanas, así
 * que ni la petición que sigue al guardado puede leer filas viejas: si la
 * ventana ya no está, se reconstruye en el momento. El job solo evita que esa
 * reconstrucción la pague el primer usuario que abra la vista.
 *
 * Lo mismo con la acumulación: diez guardados seguidos (un Excel, una carga de
 * OPs) tienen que costar una sola recarga. La llave de pendiente se pone con
 * Cache::add, que devuelve false si ya estaba, así que dos peticiones simultáneas
 * no encolan dos jobs. No se usa Cache::lock porque config/cache.php no define
 * disco de locks, y un bloqueo mal liberado dejaría el listado sin recargar.
 */
class ReconstruirListadoMateriales
{
    /** Segundos que se dejan acumular las escrituras antes de recalentar. */
    private const ESPERA_POR_DEFECTO = 10;

    public function __construct(private ExcelMaterialesService $excel)
    {
    }

    public function handle(MaterialesCambiados $evento): void
    {
        $espera = max(1, (int) config('materiales.reconstruccion_espera', self::ESPERA_POR_DEFECTO));

        // Primero el corte: a partir de aquí ningún request sirve el listado tal
        // como estaba, pase lo que pase con la recarga.
        $this->excel->invalidarCacheListados();

        // La marca vive lo mismo que el retraso del job: cuando el job corre, la
        // marca ya venció y la siguiente escritura vuelve a programar uno.
        if (!Cache::add(ReconstruirCacheMaterialesJob::CLAVE_PENDIENTE, $evento->origen, now()->addSeconds($espera))) {
            return;
        }

        ReconstruirCacheMaterialesJob::dispatch()->afterCommit()->delay(now()->addSeconds($espera));
    }
}
