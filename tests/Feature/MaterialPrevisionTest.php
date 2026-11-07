<?php

namespace Tests\Feature;

use App\Models\EstadoOperacion;
use App\Models\Material;
use App\Models\MaterialMovimiento;
use App\Models\OrdenProduccion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MaterialPrevisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_alerta_de_prevision_indica_la_op_que_producira_el_material_faltante(): void
    {
        $user = $this->usuarioAdmin();
        $material = $this->materialProcesadoEnPrevision('COM-CAP-777');
        $estado = $this->estado('Preparación');

        MaterialMovimiento::create([
            'material_id' => $material->id,
            'tipo_movimiento' => 'salida',
            'cantidad' => 60,
            'existencia_anterior' => 61,
            'existencia_nueva' => 1,
            'motivo' => 'Salida de materiales a taller — OP-100',
            'user_id' => $user->id,
        ]);

        $op = OrdenProduccion::create([
            'numero_op' => 'OP-777',
            'cantidad_planificada' => 5,
            'tipo' => 'taller',
            'estado_op_id' => $estado->id,
            'user_id' => $user->id,
        ]);
        $op->materiales()->attach($material->id, ['cantidad' => 5]);

        $response = $this->actingAs($user)->get(route('Material.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Material/Index')
            ->where('prevision.faltan', function ($faltan) use ($material): bool {
                $fila = collect($faltan)->firstWhere('material_id', $material->id);

                return $fila !== null
                    && ($fila['ordenes_resultado'][0]['numero_op'] ?? null) === 'OP-777'
                    && (float) ($fila['ordenes_resultado'][0]['cantidad'] ?? 0) === 5.0;
            }));
    }

    public function test_la_alerta_no_anuncia_op_cerradas_que_ya_recibieron_el_material(): void
    {
        $user = $this->usuarioAdmin();
        $material = $this->materialProcesadoEnPrevision('COM-CAP-778');
        $estado = $this->estado('Cerrada');

        MaterialMovimiento::create([
            'material_id' => $material->id,
            'tipo_movimiento' => 'salida',
            'cantidad' => 60,
            'existencia_anterior' => 61,
            'existencia_nueva' => 1,
            'motivo' => 'Salida de materiales a taller — OP-100',
            'user_id' => $user->id,
        ]);

        $op = OrdenProduccion::create([
            'numero_op' => 'OP-778',
            'cantidad_planificada' => 5,
            'tipo' => 'taller',
            'estado_op_id' => $estado->id,
            'user_id' => $user->id,
        ]);
        $op->materiales()->attach($material->id, ['cantidad' => 5]);

        $response = $this->actingAs($user)->get(route('Material.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Material/Index')
            ->where('prevision.faltan', function ($faltan) use ($material): bool {
                $fila = collect($faltan)->firstWhere('material_id', $material->id);

                return $fila !== null && ($fila['ordenes_resultado'] ?? []) === [];
            }));
    }

    private function usuarioAdmin(): User
    {
        $rol = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($rol);

        return $user;
    }

    private function materialProcesadoEnPrevision(string $codigo): Material
    {
        return Material::create([
            'codigo_material' => $codigo,
            'codigo_categoria' => 'COM',
            'codigo_base' => 'CAP',
            'categoria' => 'Componentes',
            'nombre_base' => 'Material procesado en previsión',
            'unidad_inventario' => 'u',
            'estado' => 'Activo',
            'material_procesado' => true,
            'existencia_actual' => 1,
        ]);
    }

    private function estado(string $nombre): EstadoOperacion
    {
        return EstadoOperacion::create(['nombre' => $nombre]);
    }
}
