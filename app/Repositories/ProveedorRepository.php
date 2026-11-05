<?php

namespace App\Repositories;

use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Collection;

class ProveedorRepository
{
    public function all(): Collection
    {
        return Proveedor::query()->orderByDesc('updated_at')->get();
    }

    public function findById(int $id): ?Proveedor
    {
        return Proveedor::find($id);
    }

    public function create(array $data): Proveedor
    {
        return Proveedor::create($data);
    }

    public function update(Proveedor $proveedor, array $data): bool
    {
        return $proveedor->update($data);
    }

    public function delete(Proveedor $proveedor): bool
    {
        return (bool) $proveedor->delete();
    }

    public function deleteBulk(array $ids): int
    {
        return Proveedor::whereIn('id', $ids)->delete();
    }
}

