<?php

namespace App\Services;

use App\Repositories\MaterialMovimientoRepository;
use Carbon\Carbon;

class MaterialCompraPredictorService
{
    public function __construct(
        protected MaterialMovimientoRepository $materialMovimientoRepository
    ) {}

    public function predecirParaMaterial(int $materialId): ?array
    {
        $movimiento = $this->materialMovimientoRepository->obtenerUltimaCompraPorMaterial($materialId);
        if (!$movimiento) {
            return null;
        }

        $fecha = Carbon::parse($movimiento->created_at);

        return [
            'cantidad' => round((float) $movimiento->cantidad, 2),
            'fecha' => $fecha->format('Y-m-d H:i'),
            'fecha_humana' => $fecha->diffForHumans(),
        ];
    }

    public function obtenerPrediccionesParaMateriales(): array
    {
        $compras = $this->materialMovimientoRepository->obtenerUltimasComprasAgrupadas();
        $predicciones = [];

        foreach ($compras as $compra) {
            $fecha = Carbon::parse($compra->created_at);
            $predicciones[(int) $compra->material_id] = [
                'cantidad' => round((float) $compra->cantidad, 2),
                'fecha' => $fecha->format('Y-m-d H:i'),
                'fecha_humana' => $fecha->diffForHumans(),
            ];
        }

        return $predicciones;
    }
}
