<?php

namespace App\Services;

use App\Models\Proveedor;
use App\Repositories\ProveedorRepository;
use Illuminate\Database\Eloquent\Collection;

class ProveedorService
{
    public function __construct(
        protected ProveedorRepository $proveedorRepository,
        protected ?ListaDesplegableService $listas = null,
    ) {}

    public function listar(): Collection
    {
        return $this->proveedorRepository->all();
    }

    public function obtener(int $id): ?Proveedor
    {
        return $this->proveedorRepository->findById($id);
    }

    public function crear(array $data): Proveedor
    {
        return $this->proveedorRepository->create($data);
    }

    public function actualizar(int $id, array $data): Proveedor
    {
        $proveedor = $this->proveedorRepository->findById($id);
        if (!$proveedor) {
            throw new \RuntimeException('Proveedor no encontrado');
        }

        $this->proveedorRepository->update($proveedor, $data);

        return $proveedor->fresh();
    }

    public function eliminar(int $id): bool
    {
        $proveedor = $this->proveedorRepository->findById($id);
        if (!$proveedor) {
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
            'tipo_proveedor' => Proveedor::TIPOS_PROVEEDOR,
            'estado' => Proveedor::ESTADOS,
            'forma_pago' => Proveedor::FORMAS_PAGO,
            'ciudad' => $this->listas?->nombresByCategoria('ciudad') ?? [],
        ];
    }
}

