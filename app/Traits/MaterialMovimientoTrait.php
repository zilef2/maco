<?php

namespace App\Traits;

use App\Models\Material;
use App\Models\MaterialMovimiento;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

trait MaterialMovimientoTrait
{
    public function registrarMovimientoExistencia(
        Material $material,
        float|int $existenciaAnterior,
        float|int $existenciaNueva,
        ?string $tipo = null,
        ?string $motivo = null,
        ?int $usuarioId = null
    ): ?MaterialMovimiento {
        $anterior = (float) $existenciaAnterior;
        $nueva = (float) $existenciaNueva;

        if ($anterior === $nueva && $tipo !== 'inicial') {
            return null;
        }

        $diferencia = abs($nueva - $anterior);

        if ($tipo === null) {
            $tipo = $nueva >= $anterior ? 'entrada' : 'salida';
        }

        $movimiento = MaterialMovimiento::create([
            'material_id' => $material->id,
            'tipo_movimiento' => $tipo,
            'cantidad' => $diferencia,
            'existencia_anterior' => $anterior,
            'existencia_nueva' => $nueva,
            'motivo' => $motivo,
            // En las importaciones en segundo plano no hay usuario autenticado:
            // el job pasa el id de quien subió el archivo.
            'user_id' => $usuarioId ?? Auth::id(),
        ]);

        // Las salidas a taller son las que alimentan el gasto diario: al
        // registrarse, se recalcula y persiste en el material.
        if ($tipo === 'salida' && $motivo && str_starts_with($motivo, 'Salida de materiales a taller')) {
            $material->recalcularGastoDiario();
        }

        return $movimiento;
    }

    public function registrarMovimientoCreacion(
        Material $material,
        ?string $motivo = null,
        ?int $usuarioId = null
    ): ?MaterialMovimiento {
        $existencia = (float) ($material->existencia_actual ?? 0);

        return MaterialMovimiento::create([
            'material_id' => $material->id,
            'tipo_movimiento' => 'inicial',
            'cantidad' => $existencia,
            'existencia_anterior' => 0,
            'existencia_nueva' => $existencia,
            'motivo' => $motivo ?? 'Creación inicial',
            'user_id' => $usuarioId ?? Auth::id(),
        ]);
    }

    public function registrarMovimientoActualizacion(
        Material $material,
        float|int $existenciaAnterior,
        ?string $motivo = null,
        ?int $usuarioId = null
    ): ?MaterialMovimiento {
        $nuevaExistencia = (float) ($material->existencia_actual ?? 0);

        return $this->registrarMovimientoExistencia(
            $material,
            $existenciaAnterior,
            $nuevaExistencia,
            null,
            $motivo ?? 'Ajuste de inventario',
            $usuarioId
        );
    }

    public function obtenerHistoricoExistenciasMaterial(
        int|Material $material,
        int $perPage = 15
    ): LengthAwarePaginator|Collection {
        $id = $material instanceof Material ? $material->id : $material;

        return MaterialMovimiento::where('material_id', $id)
            ->with('user:id,name')
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    public function historicoMovimientos(Request $request, int $id): JsonResponse
    {
        $material = Material::findOrFail($id);
        $perPage = (int) $request->input('perPage', 15);
        $historico = $this->obtenerHistoricoExistenciasMaterial($material, $perPage);

        return response()->json([
            'material' => $material,
            'movimientos' => $historico,
        ]);
    }
}
