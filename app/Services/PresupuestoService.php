<?php

namespace App\Services;

use App\Models\Presupuesto;
use App\Repositories\PresupuestoRepository;

class PresupuestoService
{
    public function __construct(
        protected PresupuestoRepository $presupuestoRepository
    ) {}

    public function create(array $data): Presupuesto
    {
        return $this->presupuestoRepository->create($data);
    }
}
