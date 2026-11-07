<?php

namespace App\Services;

use App\Models\Material;
use App\Repositories\MaterialRepository;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

class MaterialService
{
    public function __construct(
        protected MaterialRepository $materialRepository
    ) {}

    public function listar(): Collection
    {
        return $this->materialRepository->all();
    }

    public function obtener(int $id): ?Material
    {
        return $this->materialRepository->findById($id);
    }

    public function crear(array $data, array $colores = [], array $variantes = []): Material
    {
        $material = $this->materialRepository->create($data);

        if (!empty($colores) || !empty($variantes)) {
            $material->sincronizarVariantes($colores, $variantes);
        }

        return $material->fresh();
    }

    public function actualizar(int $id, array $data, ?array $colores = null, ?array $variantes = null): Material
    {
        $material = $this->materialRepository->findById($id);
        if (!$material) {
            throw new RuntimeException('Material no encontrado');
        }

        $this->materialRepository->update($material, $data);

        if ($colores !== null || $variantes !== null) {
            $material->sincronizarVariantes($colores ?? [], $variantes ?? []);
        }

        return $material->fresh();
    }

    public function eliminar(int $id): bool
    {
        $material = $this->materialRepository->findById($id);
        if (!$material) {
            return false;
        }

        return $this->materialRepository->delete($material);
    }
}

