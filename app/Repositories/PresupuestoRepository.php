<?php

namespace App\Repositories;

use App\Models\Presupuesto;

class PresupuestoRepository
{
    public function create(array $data): Presupuesto
    {
        return Presupuesto::create($data);
    }
}
