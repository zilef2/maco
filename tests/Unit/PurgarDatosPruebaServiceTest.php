<?php

namespace Tests\Unit;

use App\Repositories\PurgarDatosPruebaRepository;
use App\Services\PurgarDatosPruebaService;
use Illuminate\Support\Facades\Config;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class PurgarDatosPruebaServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_ejecutar_falla_si_no_es_entorno_test(): void
    {
        Config::set('app.env', 'production');

        $repository = Mockery::mock(PurgarDatosPruebaRepository::class);
        $repository->shouldNotReceive('purgarTablas');

        $service = new PurgarDatosPruebaService($repository);

        $this->expectException(RuntimeException::class);
        $service->ejecutar();
    }

    public function test_ejecutar_purga_exitosamente_en_entorno_test(): void
    {
        Config::set('app.env', 'test');

        $repository = Mockery::mock(PurgarDatosPruebaRepository::class);
        $repository->shouldReceive('purgarTablas')->once();

        $service = new PurgarDatosPruebaService($repository);
        $service->ejecutar();

        $this->assertTrue(true);
    }
}

