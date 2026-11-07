<?php

namespace App\Repositories;

use App\Models\Material;
use Illuminate\Database\Eloquent\Collection;

class MaterialRepository
{
    public function all(): Collection
    {
        return Material::query()->orderByDesc('updated_at')->get();
    }

    public function findById(int $id): ?Material
    {
        return Material::find($id);
    }

    public function findByCodigo(string $codigo): ?Material
    {
        return Material::where('codigo_material', $codigo)->first();
    }

    public function create(array $data): Material
    {
        return Material::create($data);
    }

    public function update(Material $material, array $data): bool
    {
        return $material->update($data);
    }

    public function delete(Material $material): bool
    {
        return (bool) $material->delete();
    }

    public function deleteBulk(array $ids): int
    {
        return Material::whereIn('id', $ids)->delete();
    }
}

