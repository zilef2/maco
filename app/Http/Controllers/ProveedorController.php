<?php

namespace App\Http\Controllers;

use App\helpers\Myhelp;
use App\Traits\ExcelDesdeDriveTrait;
use App\Http\Requests\StoreProveedorRequest;
use App\Http\Requests\UpdateProveedorRequest;
use App\Jobs\ImportarProveedoresJob;
use App\Jobs\ImportarTalleresJob;
use App\Services\ExcelProveedoresService;
use App\Services\ExcelTalleresService;
use App\Services\ImportacionExcelNotificador;
use App\Services\ProveedorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProveedorController extends Controller
{
    use ExcelDesdeDriveTrait;
    public function __construct(
        protected ProveedorService $proveedorService
    ) {}

    public function index(Request $request): Response
    {
        // Listado completo: la búsqueda, los filtros, el orden y la paginación
        // viven en el frontend (igual que Productos), así que aquí no se lee
        // ningún parámetro de filtro.
        $proveedores = $this->proveedorService->listar();

        return Inertia::render('Proveedor/Index', [
            'proveedores' => $proveedores,
            'total' => $proveedores->count(),
            'breadcrumbs' => [['label' => 'Proveedores y terceros', 'href' => route('Proveedor.index')]],
            'title' => 'Proveedores y terceros',
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

    /**
     * Carga masiva de la ficha de terceros (proveedor, taller y empresa
     * externa) desde el mismo archivo: la columna de clasificación decide el
     * tipo de cada fila.
     */
    public function uploadTercerosExcel(
        Request $request,
        ExcelTalleresService $excel,
        ImportacionExcelNotificador $notificador,
    ): RedirectResponse {
        $request->validate(self::reglasDeSubidaExcel(), self::mensajesDeSubidaExcel());

        $archivo = $this->excelDeLaSubida($request);
        $usuario = $request->user();
        $nombreArchivo = $archivo->getClientOriginalName();
        $rutaRelativa = null;

        try {
            if (strtolower($archivo->getClientOriginalExtension()) !== 'xlsx') {
                throw new \RuntimeException('Solo se permiten archivos con extensión .xlsx.');
            }

            $problemas = $excel->escanearSeguridad($archivo->getRealPath());
            if (!empty($problemas)) {
                throw new \RuntimeException(implode(' | ', $problemas));
            }

            $excel->validarEncabezados($archivo->getRealPath());
            $excel->validarDestinos($archivo->getRealPath());

            $rutaRelativa = $archivo->store('imports/terceros', 'local');
            if (!$rutaRelativa) {
                throw new \RuntimeException('No se pudo guardar el archivo para su procesamiento.');
            }

            ImportarTalleresJob::dispatch(
                $rutaRelativa,
                $nombreArchivo,
                $usuario?->email,
                $usuario?->name,
                $usuario?->id,
                $request->attributes->get('importacion_excel_id'),
            );

            return back()->with(
                'success',
                'Archivo recibido correctamente. La importación se está procesando; '
                . 'si alguna fila tiene errores recibirás el detalle por correo.'
            );
        } catch (\Throwable $e) {
            if ($rutaRelativa) {
                \Illuminate\Support\Facades\Storage::disk('local')->delete($rutaRelativa);
            }

            $notificador->fallo($usuario?->email, $usuario?->name, $nombreArchivo, 'terceros', $e);
            Myhelp::EscribirEnLog($this, 'uploadTercerosExcel ERROR', $e->getMessage());

            return back()->with('error', 'No se importó nada: ' . $e->getMessage());
        }
    }

    public function uploadExcel(Request $request, ExcelProveedoresService $excel, ImportacionExcelNotificador $notificador): RedirectResponse
    {
        $request->validate(self::reglasDeSubidaExcel(), self::mensajesDeSubidaExcel());

        $archivo = $this->excelDeLaSubida($request);
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
