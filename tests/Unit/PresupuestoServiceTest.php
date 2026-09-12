<?php

namespace Tests\Unit;

use App\Models\Presupuesto;
use App\Repositories\PresupuestoRepository;
use App\Services\PresupuestoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PresupuestoServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_presupuesto_successfully()
    {
        $repository = new PresupuestoRepository();
        $service = new PresupuestoService($repository);

        $payload = [
            'nombre' => 'Presupuesto Test',
            'monto_total' => 10000.00,
            'descripcion' => 'Descripción de prueba',
            'estado' => 'activo',
        ];

        $presupuesto = $service->create($payload);

        $this->assertInstanceOf(Presupuesto::class, $presupuesto);
        $this->assertDatabaseHas('presupuestos', [
            'id' => $presupuesto->id,
            'nombre' => 'Presupuesto Test',
        ]);
    }
}
