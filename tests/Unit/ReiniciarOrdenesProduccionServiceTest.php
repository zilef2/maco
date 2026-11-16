<?php

namespace Tests\Unit;

use App\Models\EstadoOperacion;
use App\Models\OrdenProduccion;
use App\Repositories\OrdenProduccionPruebaRepository;
use App\Services\ReiniciarOrdenesProduccionService;
use Mockery;
use PHPUnit\Framework\TestCase;

class ReiniciarOrdenesProduccionServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_ejecutar_llama_al_repositorio_para_vaciar_y_generar(): void
    {
        $estadoId = 6;
        $mockOps = [
            new OrdenProduccion(['numero_op' => 'OP-100', 'estado_op_id' => $estadoId]),
            new OrdenProduccion(['numero_op' => 'OP-101', 'estado_op_id' => $estadoId]),
        ];

        $repoMock = Mockery::mock(OrdenProduccionPruebaRepository::class);
        $repoMock->shouldReceive('vaciarOrdenesProduccion')
            ->once()
            ->andReturnNull();

        $repoMock->shouldReceive('generarOrdenes')
            ->once()
            ->with($estadoId, 2)
            ->andReturn($mockOps);

        $service = new ReiniciarOrdenesProduccionService($repoMock);
        $resultado = $service->ejecutar($estadoId);

        $this->assertCount(2, $resultado);
        $this->assertSame($mockOps, $resultado);
    }
}
