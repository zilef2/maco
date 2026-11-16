<?php

namespace App\Services;

use App\Repositories\OrdenProduccionPruebaRepository;
use Illuminate\Support\Facades\Cache;

class ReiniciarOrdenesProduccionService
{
    public function __construct(
        protected OrdenProduccionPruebaRepository $repository
    ) {}

    public function ejecutar(int $estadoOpId): array
    {
        $this->repository->vaciarOrdenesProduccion();
        $creadas = $this->repository->generarOrdenes($estadoOpId, 2);
        Cache::flush();

        return $creadas;
    }
}
