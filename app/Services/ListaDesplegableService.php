<?php

namespace App\Services;

use App\Models\ListaDesplegable;
use App\Repositories\ListaDesplegableRepository;
use Illuminate\Database\Eloquent\Collection;

class ListaDesplegableService
{
    public function __construct(
        protected ListaDesplegableRepository $listaDesplegableRepository
    ) {}

    public function getByCategoriaAndSubcategoria(string $categoria, string $subcategoria): Collection
    {
        return $this->listaDesplegableRepository->getByCategoriaAndSubcategoria($categoria, $subcategoria);
    }

    /**
     * Nombres de una categoría, ordenados alfabéticamente y sin repetir.
     *
     * @return list<string>
     */
    public function nombresByCategoria(string $categoria): array
    {
        return $this->listaDesplegableRepository->getByCategoria($categoria)
            ->sortBy(fn($item) => $item->nombre, SORT_NATURAL | SORT_FLAG_CASE)
            ->pluck('nombre')
            ->unique()
            ->values()
            ->all();
    }

    public function getOptionsByCategoriaGrouped(string $categoria): array
    {
        $items = $this->listaDesplegableRepository->getByCategoria($categoria);
        $grouped = [];

        foreach ($items as $item) {
            $grouped[$item->subcategoria][] = [
                'id' => $item->id,
                'nombre' => $item->nombre,
            ];
        }

        return $grouped;
    }

    public function create(array $data): ListaDesplegable
    {
        return $this->listaDesplegableRepository->create($data);
    }
}
