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

    /**
     * Última compra de cada material, para la columna de recomendación.
     *
     * @param  list<int>|null  $materialIds  limitar el barrido a un bloque de filas
     */
    public function obtenerPrediccionesParaMateriales(?array $materialIds = null): array
    {
        $compras = $this->materialMovimientoRepository->obtenerUltimasComprasAgrupadas($materialIds);
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
