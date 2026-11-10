<?php

namespace App\Services;

use App\Models\Proveedor;
use App\Models\Tercero;
use App\Repositories\ProveedorRepository;
use Illuminate\Database\Eloquent\Collection;

class ProveedorService
{
    public function __construct(
        protected ProveedorRepository $proveedorRepository,
        protected ListaDesplegableService $listas,
    ) {}

    public function listar(): Collection
    {
        return $this->proveedorRepository->all();
    }

    public function obtener(int $id): ?Tercero
    {
        return $this->proveedorRepository->findById($id);
    }

    public function crear(array $data): Tercero
    {
        return $this->proveedorRepository->create($data);
    }

    public function actualizar(int $id, array $data): Tercero
    {
        $tercero = $this->proveedorRepository->findById($id);
        if (! $tercero) {
            throw new \RuntimeException('Proveedor no encontrado');
        }

        // El tipo y el código se fijan al crear la ficha y no se editan.
        unset($data['tipo'], $data['codigo']);

        $this->proveedorRepository->update($tercero, $data);

        return $tercero->fresh();
    }

    public function eliminar(int $id): bool
    {
        $proveedor = $this->proveedorRepository->findById($id);
        if (! $proveedor) {
            return false;
        }

        return $this->proveedorRepository->delete($proveedor);
    }

    public function eliminarMasivo(array $ids): int
    {
        return $this->proveedorRepository->deleteBulk($ids);
    }

    public function obtenerOpcionesSelect(): array
    {
        return [
            'tipo' => Tercero::TIPOS_FICHA,
            'tipo_proveedor' => Proveedor::TIPOS_PROVEEDOR,
            'estado' => Proveedor::ESTADOS,
            'forma_pago' => Proveedor::FORMAS_PAGO,
            'ciudad' => $this->listas->nombresByCategoriaAndSubcategoria('ciudad', 'general'),
        ];
    }
}
