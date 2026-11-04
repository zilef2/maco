<?php

namespace App\Services;

use App\Repositories\PurgarDatosPruebaRepository;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class PurgarDatosPruebaService
{
    public function __construct(
        protected PurgarDatosPruebaRepository $purgarRepository
    ) {}

    public function ejecutar(): void
    {
        if (config('app.env') !== 'test' && !app()->environment('test')) {
            throw new RuntimeException('La purga solo está permitida en entorno test.');
        }

        $this->purgarRepository->purgarTablas();
        Cache::flush();
    }
}

