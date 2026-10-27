<?php

namespace Tests\Feature\Concerns;

use App\Models\EstadoOperacion;
use App\Models\FichaTecnica;
use App\Models\Material;
use App\Models\OrdenProduccion;
use App\Models\User;
use Spatie\Permission\Models\Role;

trait ContextoMateriales
{
    protected function usuarioAdmin(): User
    {
        $rol = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($rol);

        return $user;
    }

    /**
     * Material base. Si se pasa precio se registra como última compra.
     */
    protected function materialBase(string $codigo, ?float $precio = null, array $atributos = []): Material
    {
        $material = Material::create(array_merge([
            'codigo_material' => $codigo,
            'categoria' => 'Tela',
            'nombre_base' => 'Material ' . $codigo,
            'unidad_inventario' => 'm',
            'estado' => 'Activo',
            'existencia_actual' => 100,
        ], $atributos));

        if ($precio !== null) {
            $material->prices()->create([
                'precio' => $precio,
                'fecha' => now()->toDateString(),
            ]);
        }

        return $material;
    }

    protected function materialProcesado(string $codigo, array $atributos = []): Material
    {
        return $this->materialBase($codigo, null, array_merge([
            'categoria' => 'Semielaborado',
            'unidad_inventario' => 'u',
            'material_procesado' => 1,
            'existencia_actual' => 0,
        ], $atributos));
    }

    /**
     * Ficha con una línea por cada [material, consumo] recibido.
     *
     * @param  array<int, array{0: Material, 1: float|int}>  $lineas
     */
    protected function fichaParaMaterial(Material $objetivo, array $lineas, string $nombre = 'Ficha de prueba'): FichaTecnica
    {
        $ficha = FichaTecnica::create(['nombre' => $nombre]);

        foreach ($lineas as [$componente, $consumo]) {
            $ficha->materiales()->create([
                'material_componente_id' => $componente->id,
                'consumo_estandar' => $consumo,
                'unidad_medida' => $componente->unidad_inventario,
                'costo_asociado_teorico' => $componente->costoUnitarioActual(),
            ]);
        }

        $ficha->materialesProcesados()->attach($objetivo->id);

        return $ficha;
    }

    protected function estadoOperacion(string $nombre, ?string $puedePasarA = null): EstadoOperacion
    {
        return EstadoOperacion::create([
            'nombre' => $nombre,
            'puede_pasar_a' => $puedePasarA,
        ]);
    }

    protected function opQueProduce(Material $material, User $user, EstadoOperacion $estado, int $cantidad = 1): OrdenProduccion
    {
        $op = OrdenProduccion::create([
            'numero_op' => 'OP-TEST-' . $material->id,
            'cantidad_planificada' => $cantidad,
            'tipo' => 'taller',
            'estado_op_id' => $estado->id,
            'user_id' => $user->id,
        ]);

        $op->materiales()->attach($material->id, ['cantidad' => $cantidad]);

        return $op;
    }
}
