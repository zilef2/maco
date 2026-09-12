<?php

namespace App\Repositories;

use App\Models\ListaDesplegable;
use Illuminate\Database\Eloquent\Collection;

class ListaDesplegableRepository
{
    public function getByCategoriaAndSubcategoria(string $categoria, string $subcategoria): Collection
    {
        return ListaDesplegable::where('categoria', $categoria)
            ->where('subcategoria', $subcategoria)
            ->get();
    }

    public function getByCategoria(string $categoria): Collection
    {
        return ListaDesplegable::where('categoria', $categoria)->get();
    }

    public function create(array $data): ListaDesplegable
    {
        return ListaDesplegable::create($data);
    }
}
