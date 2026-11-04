<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurgarDatosPruebaRequest;
use App\Services\PurgarDatosPruebaService;
use Illuminate\Http\RedirectResponse;

class SuperadminAccionesController extends Controller
{
    public function __construct(
        protected PurgarDatosPruebaService $purgarService
    ) {}

    public function purgarDatosPrueba(PurgarDatosPruebaRequest $request): RedirectResponse
    {
        $this->purgarService->ejecutar();
        return back()->with('success', 'Registros de prueba eliminados correctamente.');
    }
}

