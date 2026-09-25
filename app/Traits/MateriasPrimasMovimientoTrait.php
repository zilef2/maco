<?php

namespace App\Traits;

use App\Models\MateriaPrimaMovimiento;
use App\Models\materias_primas;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

trait MateriasPrimasMovimientoTrait
{
    public function registrarMovimientoExistencia(
        materias_primas $materiaPrima,
        float|int $existenciaAnterior,
        float|int $existenciaNueva,
        ?string $tipo = null,
        ?string $motivo = null
    ): ?MateriaPrimaMovimiento {
        $anterior = (float) $existenciaAnterior;
        $nueva = (float) $existenciaNueva;

        if ($anterior === $nueva && $tipo !== 'inicial') {
            return null;
        }

        $diferencia = abs($nueva - $anterior);

        if ($tipo === null) {
            $tipo = $nueva >= $anterior ? 'entrada' : 'salida';
        }

        return MateriaPrimaMovimiento::create([
            'materia_prima_id' => $materiaPrima->id,
            'tipo_movimiento' => $tipo,
            'cantidad' => $diferencia,
            'existencia_anterior' => $anterior,
            'existencia_nueva' => $nueva,
            'costo_unitario' => $materiaPrima->costo_promedio_ponderado,
            'motivo' => $motivo,
            'user_id' => Auth::id(),
        ]);
    }

    public function registrarMovimientoCreacion(
        materias_primas $materiaPrima,
        ?string $motivo = null
    ): ?MateriaPrimaMovimiento {
        $existencia = (float) ($materiaPrima->existencia_actual ?? 0);

        return MateriaPrimaMovimiento::create([
            'materia_prima_id' => $materiaPrima->id,
            'tipo_movimiento' => 'inicial',
            'cantidad' => $existencia,
            'existencia_anterior' => 0,
            'existencia_nueva' => $existencia,
            'costo_unitario' => $materiaPrima->costo_promedio_ponderado,
            'motivo' => $motivo ?? 'Creación inicial',
            'user_id' => Auth::id(),
        ]);
    }

    public function registrarMovimientoActualizacion(
        materias_primas $materiaPrima,
        float|int $existenciaAnterior,
        ?string $motivo = null
    ): ?MateriaPrimaMovimiento {
        $nuevaExistencia = (float) ($materiaPrima->existencia_actual ?? 0);

        return $this->registrarMovimientoExistencia(
            $materiaPrima,
            $existenciaAnterior,
            $nuevaExistencia,
            null,
            $motivo ?? 'Ajuste de inventario'
        );
    }

    public function registrarEntrada(
        materias_primas $materiaPrima,
        float|int $cantidad,
        ?string $motivo = null
    ): MateriaPrimaMovimiento {
        $anterior = (float) ($materiaPrima->existencia_actual ?? 0);
        $nueva = $anterior + (float) $cantidad;

        $materiaPrima->update(['existencia_actual' => $nueva]);

        return MateriaPrimaMovimiento::create([
            'materia_prima_id' => $materiaPrima->id,
            'tipo_movimiento' => 'entrada',
            'cantidad' => (float) $cantidad,
            'existencia_anterior' => $anterior,
            'existencia_nueva' => $nueva,
            'costo_unitario' => $materiaPrima->costo_promedio_ponderado,
            'motivo' => $motivo ?? 'Entrada de material',
            'user_id' => Auth::id(),
        ]);
    }

    public function registrarSalida(
        materias_primas $materiaPrima,
        float|int $cantidad,
        ?string $motivo = null
    ): MateriaPrimaMovimiento {
        $anterior = (float) ($materiaPrima->existencia_actual ?? 0);
        $nueva = $anterior - (float) $cantidad;

        $materiaPrima->update(['existencia_actual' => $nueva]);

        return MateriaPrimaMovimiento::create([
            'materia_prima_id' => $materiaPrima->id,
            'tipo_movimiento' => 'salida',
            'cantidad' => (float) $cantidad,
            'existencia_anterior' => $anterior,
            'existencia_nueva' => $nueva,
            'costo_unitario' => $materiaPrima->costo_promedio_ponderado,
            'motivo' => $motivo ?? 'Salida de material',
            'user_id' => Auth::id(),
        ]);
    }

    public function obtenerHistoricoExistencias(
        int|materias_primas $materiaPrima,
        int $perPage = 15
    ): LengthAwarePaginator|Collection {
        $id = $materiaPrima instanceof materias_primas ? $materiaPrima->id : $materiaPrima;

        return MateriaPrimaMovimiento::where('materia_prima_id', $id)
            ->with('user:id,name')
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    public function historicoMovimientos(Request $request, int $id): JsonResponse
    {
        $materiaPrima = materias_primas::findOrFail($id);
        $perPage = (int) $request->input('perPage', 15);
        $historico = $this->obtenerHistoricoExistencias($materiaPrima, $perPage);

        return response()->json([
            'materia_prima' => $materiaPrima,
            'movimientos' => $historico,
        ]);
    }
}
