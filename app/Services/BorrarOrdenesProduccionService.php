<?php

namespace App\Services;

use App\Models\OrdenProduccion;
use App\Repositories\OrdenProduccionPruebaRepository;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class BorrarOrdenesProduccionService
{
    public function __construct(
        protected OrdenProduccionPruebaRepository $repository
    ) {}

    /**
     * Borra todas las órdenes de producción y todo lo que cuelga de ellas.
     * Solo se permite en entorno test o local: es una herramienta de desarrollo.
     */
    public function ejecutar(): int
    {
        if (config('app.env') !== 'test' && ! app()->environment(['test', 'local'])) {
            throw new RuntimeException('Borrar todas las OP solo está permitido en entorno test o local.');
        }

        $cantidad = OrdenProduccion::count();

        $this->repository->vaciarOrdenesProduccion();
        Cache::flush();

        return $cantidad;
    }
}