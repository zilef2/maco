<?php

namespace App\Repositories;

use App\Models\Producto;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ProductoRepository
{
    public function findByIds(array $ids): Collection
    {
        return Producto::whereIn('id', $ids)->get();
    }

    public function deleteByIds(array $ids): int
    {
        return Producto::whereIn('id', $ids)->delete();
    }

    public function obtenerDependencias(int $productoId): array
    {
        $motivos = [];

        $recepciones = DB::table('recepcion_calidads')
            ->where('producto_referencia_id', $productoId)
            ->count();
        if ($recepciones > 0) {
            $motivos[] = "{$recepciones} " . ($recepciones === 1 ? 'recepción de calidad vinculada' : 'recepciones de calidad vinculadas');
        }

        $opsDirectas = DB::table('orden_produccions')
            ->where('producto_referencia_id', $productoId)
            ->pluck('numero_op')
            ->filter()
            ->all();

        $opsPivote = DB::table('orden_produccion_producto')
            ->join('orden_produccions', 'orden_produccion_producto.orden_produccion_id', '=', 'orden_produccions.id')
            ->where('orden_produccion_producto.producto_id', $productoId)
            ->pluck('orden_produccions.numero_op')
            ->filter()
            ->all();

        $todasOps = array_values(array_unique(array_merge($opsDirectas, $opsPivote)));
        if (!empty($todasOps)) {
            $opsTexto = implode(', ', array_slice($todasOps, 0, 5));
            if (count($todasOps) > 5) {
                $opsTexto .= ' y ' . (count($todasOps) - 5) . ' más';
            }
            $motivos[] = count($todasOps) . ' ' . (count($todasOps) === 1 ? "orden de producción ({$opsTexto})" : "órdenes de producción ({$opsTexto})");
        }

        $fichasEnsamble = DB::table('ficha_ensambles')
            ->where('producto_referencia_id', $productoId)
            ->count();
        if ($fichasEnsamble > 0) {
            $motivos[] = "{$fichasEnsamble} " . ($fichasEnsamble === 1 ? 'ficha de ensamble vinculada' : 'fichas de ensamble vinculadas');
        }

        $compromisos = DB::table('compromisos_reparacion')
            ->where('producto_referencia_id', $productoId)
            ->count();
        if ($compromisos > 0) {
            $motivos[] = "{$compromisos} " . ($compromisos === 1 ? 'compromiso de reparación' : 'compromisos de reparación');
        }

        return $motivos;
    }
}

