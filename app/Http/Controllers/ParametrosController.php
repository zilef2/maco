<?php

namespace App\Http\Controllers;

use App\helpers\Myhelp;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreListaDesplegableRequest;
use App\Models\ListaDesplegable;
use App\Services\ListaDesplegableService;
use App\Services\CostoMaterialOpService;
use Inertia\Inertia;

use App\Models\Parametro;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;

class ParametrosController extends Controller
{
    public $respuestaLimite = 'Limite de tokens';
    public $respuestaLarga = 'La respuesta es demasiado extensa';
    public $MAX_USAGE_RESPUESTA = 550;
    public $MAX_USAGE_TOTAL = 600;

    public function __construct(
        protected ListaDesplegableService $listaDesplegableService,
        protected CostoMaterialOpService $costosMateriales
    ) {}

    public function fNombresTabla()
    {
        $nombresTabla = [ //0: como se ven //1 como es la BD //2 ordenables
            ["Acciones",
            "nombre",
            "valor",
            "valor2",
            "valor_date",
            "valor_int",
            "valor_double",
            "valor_string1m",
        ],
            [],
            ["Acciones"]
        ];
        $nombresTabla[2][] = ["Fecha_creacion_parametro", "nombre"];
        return $nombresTabla;
    }

    public function losSelect()
    {
        // $MateriasSelect = Materia::all();
        // return [
        //     'MateriasSelect' => $MateriasSelect
        // ];
    }

    public function index(Request $request)
    {
        $permissions = Myhelp::EscribirEnLog($this, ' parametro');

        $titulo = __('app.label.parametros');
        if ($permissions == 'admin' || $permissions == 'superadmin') {
            $nombresTabla = $this->fNombresTabla();

            $listasAgrupadas = ListaDesplegable::all()
                ->groupBy('categoria')
                ->map(fn($porCategoria) => $porCategoria->groupBy('subcategoria'));

            $modelNames = collect(glob(app_path('Models/*.php')))
                ->map(fn($path) => strtolower(pathinfo($path, PATHINFO_FILENAME)))
                ->values()
                ->toArray();

            return Inertia::render('parametro/Index', [
                'title'          => $titulo,
                'filters'        => $request->all(['search', 'field', 'order']),
                'perPage'        => 1,
                'fromController' => Parametro::all(),
                'nombresTabla'   => $nombresTabla,
                'listasAgrupadas' => $listasAgrupadas,
                'isSuperadmin'   => $permissions === 'superadmin',
                'modelNames'     => $modelNames,
                // Parámetro entero costos_promedio, con su valor vigente.
                'costosPromedio' => $this->costosPromedio(),
                // Días de cobertura mínima del banner de previsión de materiales.
                'diasPrevision' => [
                    'nombre' => Parametro::DIAS_PREVISION_STOCK,
                    'valor' => Parametro::entero(Parametro::DIAS_PREVISION_STOCK, 2),
                ],
            ]);
        } else {
            Myhelp::EscribirEnLog($this, ' parametro', 'en el index nos hackearon ', false, true);
        }
    } //fin index

    /**
     * Estado del parámetro entero costos_promedio, con las opciones que ofrece
     * la pantalla de parámetros.
     *
     * @return array{nombre:string, valor:int, descripcion:string, opciones: list<array{value:int,label:string,desc:string}>}
     */
    private function costosPromedio(): array
    {
        $valor = $this->costosMateriales->metodo();

        return [
            'nombre' => Parametro::COSTOS_PROMEDIO,
            'valor' => $valor,
            'descripcion' => (string) (Parametro::where('nombre', Parametro::COSTOS_PROMEDIO)->value('valor_string1m') ?? ''),
            'opciones' => [
                [
                    'value' => Parametro::COSTOS_PROMEDIO_PROMEDIO,
                    'label' => '1 · Precio promedio',
                    'desc' => 'Promedio de todos los precios que ha tenido el material (sus cambios de precio).',
                ],
                [
                    'value' => Parametro::COSTOS_PROMEDIO_ULTIMO,
                    'label' => '2 · Último precio digitado',
                    'desc' => 'El precio más reciente registrado para el material en la app.',
                ],
            ],
        ];
    }

    /**
     * Guarda el parámetro entero costos_promedio y renueva el precio guardado en
     * las OPs abiertas, anotando en cada una el cambio de precio.
     */
    public function actualizarCostosPromedio(Request $request): RedirectResponse
    {
        $permissions = Myhelp::EscribirEnLog($this, ' costos_promedio');
        abort_unless($permissions === 'superadmin', 403, 'Solo superadmin puede modificar parámetros.');

        $datos = $request->validate([
            'valor_int' => [
                'required',
                'integer',
                Rule::in([Parametro::COSTOS_PROMEDIO_PROMEDIO, Parametro::COSTOS_PROMEDIO_ULTIMO]),
            ],
        ]);

        $valor = (int) $datos['valor_int'];

        DB::beginTransaction();
        try {
            Parametro::guardarEntero(Parametro::COSTOS_PROMEDIO, $valor);
            $recalculadas = $this->costosMateriales->recalcularAbiertas();
            DB::commit();

            Log::info('U -> ' . Auth::user()->name . ' cambio costos_promedio a ' . $valor . ' | OPs recalculadas: ' . $recalculadas);

            return back()->with('success', __('app.label.updated_successfully2', [
                'nombre' => Parametro::COSTOS_PROMEDIO . ' = ' . $valor
                    . ' (' . $this->costosMateriales->labelMetodo($valor) . '). '
                    . $recalculadas . ' OPs abiertas recalculadas.',
            ]));
        } catch (\Throwable $th) {
            DB::rollback();
            Log::alert('U -> ' . Auth::user()->name . ' fallo al guardar costos_promedio - ' . $th->getMessage() . ' L:' . $th->getLine() . ' Ubi: ' . $th->getFile());

            return back()->with('error', __('app.label.updated_error', ['nombre' => __('app.label.parametro')]) . $th->getMessage());
        }
    }

    /**
     * Guarda los días de previsión (umbral del banner de faltantes en Materiales).
     */
    public function actualizarDiasPrevision(Request $request): RedirectResponse
    {
        $permissions = Myhelp::EscribirEnLog($this, ' dias_prevision_stock');
        abort_unless($permissions === 'superadmin', 403, 'Solo superadmin puede modificar parámetros.');

        $datos = $request->validate([
            'valor_int' => ['required', 'integer', 'min:1', 'max:30'],
        ]);

        $valor = (int) $datos['valor_int'];

        try {
            Parametro::guardarEntero(Parametro::DIAS_PREVISION_STOCK, $valor);
            Log::info('U -> ' . Auth::user()->name . ' cambio dias_prevision_stock a ' . $valor);

            return back()->with('success', __('app.label.updated_successfully2', [
                'nombre' => Parametro::DIAS_PREVISION_STOCK . ' = ' . $valor,
            ]));
        } catch (\Throwable $th) {
            Log::alert('U -> ' . Auth::user()->name . ' fallo al guardar dias_prevision_stock - ' . $th->getMessage());

            return back()->with('error', __('app.label.updated_error', ['nombre' => __('app.label.parametro')]) . $th->getMessage());
        }
    }

    public function storeLista(Request $request): RedirectResponse
    {
        $permissions = Myhelp::EscribirEnLog($this, 'storeLista');
        abort_unless($permissions === 'superadmin', 403, 'Solo superadmin puede crear listas desplegables.');

        $validated = $request->validate([
            'nombre'       => 'required|string|max:255',
            'categoria'    => 'required|string|max:100',
            'subcategoria' => 'required|string|max:100',
        ]);

        ListaDesplegable::create($validated);

        return back()->with('success', 'Opción creada correctamente.');
    }

    public function create()
    {
    }
    public function store(Request $request)
    {
        $permissions = Myhelp::EscribirEnLog($this, ' store');
        abort_unless($permissions === 'superadmin', 403, 'Solo superadmin puede modificar parámetros.');

        DB::beginTransaction();

        try {
            $parametro = Parametro::create([
                'Fecha_creacion_parametro' => $request->Fecha_creacion_parametro,
                'nombre' => $request->nombre,
                'valor' => $request->valor,
            ]);
            DB::commit();
            Log::info("U -> " . Auth::user()->name . " Guardo parametro " . $request->nombre . " correctamente");

            return back()->with('success', __('app.label.created_successfully2', ['nombre' => $parametro->nombre]));
        } catch (\Throwable $th) {
            DB::rollback();
            Log::alert("U -> " . Auth::user()->name . " fallo en Guardar parametro " . $request->nombre . " - " . $th->getMessage() . ' L:' . $th->getLine() . ' Ubi: ' . $th->getFile());

            return back()->with('error', __('app.label.created_error', ['nombre' => __('app.label.parametro')]) . $th->getMessage() . ' L:' . $th->getLine() . ' Ubi: ' . $th->getFile());
        }
    }

    public function show(parametro $parametro)
    {
    }
    public function edit(parametro $parametro)
    {
    }

    public function update(Request $request, $id)
    {
        $permissions = Myhelp::EscribirEnLog($this, ' update');
        abort_unless($permissions === 'superadmin', 403, 'Solo superadmin puede modificar parámetros.');

        $parametro = Parametro::find($id);
        DB::beginTransaction();
        try {
            // dd($parametro,$request->nombre);
            $numticke = $request->NumeroTicketDefecto < 0 ? $request->NumeroTicketDefecto * -1 : $request->NumeroTicketDefecto;
            $parametro->update([
                'Fecha_creacion_parametro' => $request->Fecha_creacion_parametro,
                'nombre' => $request->nombre,
                'valor' => $request->valor,
            ]);

            DB::commit();
            Log::info("U -> " . Auth::user()->name . " actualizo parametro " . $request->id . " correctamente");

            return back()->with('success', __('app.label.updated_successfully2', ['nombre' => $parametro->id]));
        } catch (\Throwable $th) {

            DB::rollback();
            Log::alert("U -> " . Auth::user()->name . " fallo en actualizar parametro " . $request->nombre . " - " . $th->getMessage() . ' L:' . $th->getLine() . ' Ubi: ' . $th->getFile());
            return back()->with('error', __('app.label.updated_error', ['nombre' => $parametro->nombre]) . $th->getMessage() . ' L:' . $th->getLine() . ' Ubi: ' . $th->getFile());
        }
    }

    // public function destroy(parametro $parametro)
    public function destroy($id)
    {
        $permissions = Myhelp::EscribirEnLog($this, ' destroy');
        abort_unless($permissions === 'superadmin', 403, 'Solo superadmin puede modificar parámetros.');

        DB::beginTransaction();

        try {
            $parametros = Parametro::findOrFail($id);
            Myhelp::EscribirEnLog($this, get_called_class(), "La parametro id:" . $id . " y nombre:" . $parametros->nombre . " ha sido borrada correctamente", false);


            // $parametros->delete();
            DB::commit();
            return back()->with('success', __('app.label.deleted_successfully2', ['nombre' => $parametros->nombre]));
        } catch (\Throwable $th) {
            DB::rollback();
            Log::alert("U -> " . Auth::user()->name . " fallo en borrar parametro " . $id . " - " . $th->getMessage() . ' L:' . $th->getLine() . ' Ubi: ' . $th->getFile());
            return back()->with('error', __('app.label.deleted_error', ['name' => __('app.label.parametros')]) . $th->getMessage() . ' L:' . $th->getLine() . ' Ubi: ' . $th->getFile());
        }
    }
	
	public function probando() {
		 return Inertia::render('parametro/probando', [ //carpeta
                'fromController' =>  Parametro::first(),
            ]);
	}
}
