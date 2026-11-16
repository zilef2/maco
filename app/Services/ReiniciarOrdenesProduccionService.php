<?php

namespace App\Services;

use App\Repositories\OrdenProduccionPruebaRepository;
use Illuminate\Support\Facades\Cache;

class ReiniciarOrdenesProduccionService
{
    public function __construct(
        protected OrdenProduccionPruebaRepository $repository
    ) {}

    public function ejecutar(int $estadoOpId, int $cantidad = 2, bool $borrarExistentes = true): array
    {
        $cantidad = max(1, $cantidad);

        if ($borrarExistentes) {
            $this->repository->vaciarOrdenesProduccion();
        }
        $creadas = $this->repository->generarOrdenes($estadoOpId, $cantidad);
        Cache::flush();

        return $creadas;
    }
}
