<?php

namespace App\Services;

use App\Repositories\ProductoRepository;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;

class ProductoService
{
    public function __construct(
        protected ProductoRepository $productoRepository
    ) {}

    public function eliminarMasivo(array $ids): array
    {
        $productos = $this->productoRepository->findByIds($ids);
        $bloqueados = [];
        $idsParaEliminar = [];

        foreach ($productos as $producto) {
            $motivos = $this->productoRepository->obtenerDependencias($producto->id);
            if (!empty($motivos)) {
                $bloqueados[] = [
                    'id' => $producto->id,
                    'codigo' => (string) ($producto->Codigo_interno ?: "ID #{$producto->id}"),
                    'titulo' => (string) ($producto->Titulo_Shopify ?: 'Sin título'),
                    'motivos' => $motivos,
                ];
            } else {
                $idsParaEliminar[] = $producto->id;
            }
        }

        $eliminados = 0;
        if (!empty($idsParaEliminar)) {
            try {
                $eliminados = $this->productoRepository->deleteByIds($idsParaEliminar);
                Cache::forget('op_selects');
            } catch (QueryException) {
                foreach ($this->productoRepository->findByIds($idsParaEliminar) as $prodRestante) {
                    $bloqueados[] = [
                        'id' => $prodRestante->id,
                        'codigo' => (string) ($prodRestante->Codigo_interno ?: "ID #{$prodRestante->id}"),
                        'titulo' => (string) ($prodRestante->Titulo_Shopify ?: 'Sin título'),
                        'motivos' => ['Registros dependientes impiden su eliminación'],
                    ];
                }
            }
        }

        return [
            'eliminados' => $eliminados,
            'bloqueados' => $bloqueados,
            'total_solicitados' => count($ids),
        ];
    }
}

