<?php

namespace Tests\Unit;

use App\Models\ListaDesplegable;
use App\Repositories\ListaDesplegableRepository;
use App\Services\ListaDesplegableService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListaDesplegableServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_lista_desplegable_successfully()
    {
        $repository = new ListaDesplegableRepository();
        $service = new ListaDesplegableService($repository);

        $payload = [
            'nombre' => 'Activo',
            'categoria' => 'presupuesto',
            'subcategoria' => 'estado',
        ];

        $item = $service->create($payload);

        $this->assertInstanceOf(ListaDesplegable::class, $item);
        $this->assertDatabaseHas('listas_desplegables', [
            'id' => $item->id,
            'nombre' => 'Activo',
            'categoria' => 'presupuesto',
            'subcategoria' => 'estado',
        ]);
    }

    public function test_it_fetches_options_grouped_by_subcategoria()
    {
        $repository = new ListaDesplegableRepository();
        $service = new ListaDesplegableService($repository);

        $service->create([
            'nombre' => 'Borrador',
            'categoria' => 'presupuesto',
            'subcategoria' => 'estado',
        ]);

        $service->create([
            'nombre' => 'Ajuste de IPC',
            'categoria' => 'presupuesto',
            'subcategoria' => 'motivo_reajuste',
        ]);

        $options = $service->getOptionsByCategoriaGrouped('presupuesto');

        $this->assertArrayHasKey('estado', $options);
        $this->assertArrayHasKey('motivo_reajuste', $options);
        $this->assertEquals('Borrador', $options['estado'][0]['nombre']);
        $this->assertEquals('Ajuste de IPC', $options['motivo_reajuste'][0]['nombre']);
    }
}
