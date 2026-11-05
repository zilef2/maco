<?php

namespace App\Services;

use App\Repositories\PurgarMaterialesRepository;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class PurgarMaterialesService
{
    public function __construct(
        protected PurgarMaterialesRepository $purgarRepository
    ) {}

    /**
     * @return array{materiales: int, variantes: int, precios: int, vinculos: int}
     */
    public function ejecutar(): array
    {
        if (config('app.env') !== 'test' && !app()->environment('test')) {
            throw new RuntimeException('La purga de materiales solo está permitida en entorno test.');
        }

        $conteo = $this->purgarRepository->purgar();
        Cache::flush();

        return $conteo;
    }
}
