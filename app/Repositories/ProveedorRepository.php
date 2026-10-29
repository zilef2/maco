<?php

namespace App\Repositories;

use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ProveedorRepository
{
    public function all(): Collection
    {
        return Proveedor::orderBy('nombre_comercial')->get();
    }

    public function paginate(
        int $perPage = 10,
        ?string $search = null,
        ?string $field = null,
        ?string $order = null,
        ?string $tipoProveedor = null,
        ?string $estado = null,
        ?string $formaPago = null,
        ?string $clasificacion = null
    ): LengthAwarePaginator {
        $query = Proveedor::query();

        if ($search !== null && trim($search) !== '') {
            $termino = trim($search);
            $query->where(function ($q) use ($termino): void {
                $q->where('codigo_proveedor', 'LIKE', "%{$termino}%")
                    ->orWhere('nit', 'LIKE', "%{$termino}%")
                    ->orWhere('nombre_comercial', 'LIKE', "%{$termino}%")
                    ->orWhere('razon_social', 'LIKE', "%{$termino}%")
                    ->orWhere('correo_electronico', 'LIKE', "%{$termino}%")
                    ->orWhere('ciudad', 'LIKE', "%{$termino}%")
                    ->orWhere('persona_contacto', 'LIKE', "%{$termino}%")
                    ->orWhere('que_suministra', 'LIKE', "%{$termino}%")
                    ->orWhere('nombre', 'LIKE', "%{$termino}%");
            });
        }

        if ($tipoProveedor !== null && $tipoProveedor !== '') {
            $query->where('tipo_proveedor', $tipoProveedor);
        }

        if ($estado !== null && $estado !== '') {
            $query->where('estado', $estado);
        }

        if ($formaPago !== null && $formaPago !== '') {
            $query->where('forma_pago', $formaPago);
        }

        if ($clasificacion !== null && $clasificacion !== '') {
            $query->where('clasificacion', $clasificacion);
        }

        $allowedFields = [
            'id',
            'codigo_proveedor',
            'nit',
            'nombre_comercial',
            'razon_social',
            'ciudad',
            'tipo_proveedor',
            'forma_pago',
            'estado',
            'clasificacion',
            'updated_at',
            'created_at',
        ];

        $sortField = in_array($field, $allowedFields, true) ? $field : 'updated_at';
        $sortOrder = strtolower((string) $order) === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortField, $sortOrder)->paginate($perPage);
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

