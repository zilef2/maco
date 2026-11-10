<?php

namespace App\Repositories;

use App\Models\Tercero;
use Illuminate\Database\Eloquent\Collection;

class ProveedorRepository
{
    public function all(): Collection
    {
        return Tercero::query()->orderByDesc('updated_at')->get();
    }

    public function findById(int $id): ?Tercero
    {
        return Tercero::find($id);
    }

    public function create(array $data): Tercero
    {
        return Tercero::create($data);
    }

    public function update(Tercero $tercero, array $data): bool
    {
        return $tercero->update($data);
    }

    public function delete(Tercero $tercero): bool
    {
        return (bool) $tercero->delete();
    }

    public function deleteBulk(array $ids): int
    {
        return Tercero::whereIn('id', $ids)->delete();
    }
}
