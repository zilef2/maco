<?php

namespace App\Http\Controllers;

use App\helpers\Myhelp;
use App\Http\Requests\StoreProveedorRequest;
use App\Http\Requests\UpdateProveedorRequest;
use App\Jobs\ImportarProveedoresJob;
use App\Services\ExcelProveedoresService;
use App\Services\ImportacionExcelNotificador;
use App\Services\ProveedorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProveedorController extends Controller
{
    public function __construct(
        protected ProveedorService $proveedorService
    ) {}

    public function index(Request $request): Response
    {
        $filtros = $request->only(['search', 'field', 'order', 'tipo_proveedor', 'estado', 'forma_pago', 'clasificacion']);
        $proveedores = $this->proveedorService->listar($filtros, (int) $request->input('perPage', 10));

        return Inertia::render('Proveedor/Index', [
            'fromController' => $proveedores,
            'total' => $proveedores->total(),
            'breadcrumbs' => [['label' => 'Proveedores', 'href' => route('Proveedor.index')]],
            'title' => 'Proveedores',
            'filters' => $filtros,
            'perPage' => (int) $request->input('perPage', 10),
            'opcionesSelect' => $this->proveedorService->obtenerOpcionesSelect(),
        ]);
    }

    public function store(StoreProveedorRequest $request): RedirectResponse
    {
        $proveedor = $this->proveedorService->crear($request->validated());

        return back()->with('success', __('app.label.created_successfully', ['name' => $proveedor->nombre_comercial]));
    }

    public function update(UpdateProveedorRequest $request, int $id): RedirectResponse
    {
        $proveedor = $this->proveedorService->actualizar($id, $request->validated());

        return back()->with('success', __('app.label.updated_successfully2', ['nombre' => $proveedor->nombre_comercial]));
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->proveedorService->eliminar($id);

        return back()->with('success', __('app.label.deleted_successfully', ['name' => 'Proveedor']));
    }

    public function destroyBulk(Request $request): RedirectResponse
    {
        $this->proveedorService->eliminarMasivo((array) $request->input('id', []));

        return back()->with('success', __('app.label.deleted_successfully', ['name' => 'Proveedores']));
    }

    public function uploadExcel(Request $request, ExcelProveedoresService $excel, ImportacionExcelNotificador $notificador): RedirectResponse
    {
        $request->validate([
            'archivo' => ['required', 'file', 'max:10240', 'mimes:xlsx'],
        ], [
            'archivo.required' => 'Selecciona un archivo .xlsx.',
            'archivo.file' => 'El archivo no es válido.',
            'archivo.max' => 'El archivo supera el tamaño máximo de 10 MB.',
            'archivo.mimes' => 'Solo se permiten archivos .xlsx.',
        ]);

        $archivo = $request->file('archivo');
        $usuario = $request->user();
        $rutaGuardada = null;

        try {
            $rutaGuardada = $excel->procesarSubida(
                $archivo,
                $usuario,
                $request->attributes->get('importacion_excel_id'),
            );
        } catch (\Throwable $e) {
            if ($rutaGuardada !== null) {
                \Illuminate\Support\Facades\Storage::disk('local')->delete($rutaGuardada);
            }

            $notificador->fallo($usuario?->email, $usuario?->name, $archivo->getClientOriginalName(), 'proveedores', $e);
            Myhelp::EscribirEnLog($this, 'uploadExcel ERROR', $e->getMessage());

            return back()->with('error', 'No se importó nada: ' . $e->getMessage());
        }

        return back()->with(
            'success',
            'Archivo recibido correctamente. La importación se está procesando; '
            . 'si alguna fila tiene errores recibirás el detalle por correo.'
        );
    }
}
