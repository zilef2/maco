<?php

namespace App\Repositories;

use App\Models\MaterialMovimiento;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MaterialMovimientoRepository
{
    public function obtenerUltimaCompraPorMaterial(int $materialId): ?MaterialMovimiento
    {
        return MaterialMovimiento::query()
            ->where('material_id', $materialId)
            ->where(function ($q): void {
                $q->where('motivo', 'LIKE', '%Compra%')
                    ->orWhere(function ($q2): void {
                        $q2->where('tipo_movimiento', 'entrada')
                            ->where('motivo', 'not like', '%Ajuste%');
                    });
            })
            ->orderByDesc('id')
            ->first();
    }

    public function obtenerUltimasComprasAgrupadas(): Collection
    {
        $sub = DB::table('material_movimientos')
            ->select('material_id', DB::raw('MAX(id) as max_id'))
            ->where(function ($q): void {
                $q->where('motivo', 'LIKE', '%Compra%')
                    ->orWhere(function ($q2): void {
                        $q2->where('tipo_movimiento', 'entrada')
                            ->where('motivo', 'not like', '%Ajuste%');
                    });
            })
            ->groupBy('material_id');

        return DB::table('material_movimientos as mm')
            ->joinSub($sub, 'ultimos', function ($join): void {
                $join->on('mm.id', '=', 'ultimos.max_id');
            })
            ->select('mm.material_id', 'mm.cantidad', 'mm.created_at')
            ->get();
    }
}
