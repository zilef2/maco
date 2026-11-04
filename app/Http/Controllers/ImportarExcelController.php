<?php

namespace App\Http\Controllers;

use App\helpers\Myhelp;
use App\Models\Parametro;
use App\Services\ExcelImportacionTracker;
use App\Services\PlantillasExcelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportarExcelController extends Controller
{
    public function __construct(
        protected PlantillasExcelService $plantillas,
        protected ExcelImportacionTracker $tracker,
    ) {}
    /**
     * Registro único de Parametro donde se guarda qué entidades tienen
     * habilitada la carga masiva por Excel.
     */
    private const PARAMETRO = 'importar_excel_entidades';

    /**
     * Entidades que se muestran en el panel informativo y que, por ahora,
     * tienen la carga por Excel habilitada. Los proveedores no están: se
     * cargan con el archivo de talleres, cuya columna de clasificación decide
     * el destino de cada fila.
     */
    private const ENTIDADES_CON_EXCEL_DEFAULT = ['producto', 'material', 'estado_operacion', 'taller', 'usuario'];

    private const CATALOGO_ENTIDADES = [
        ['clave' => 'producto', 'nombre' => 'Producto', 'grupo' => 'Inventario'],
        ['clave' => 'material', 'nombre' => 'Material', 'grupo' => 'Inventario'],
        ['clave' => 'orden_produccion', 'nombre' => 'Orden de producción', 'grupo' => 'Orden producción'],
        ['clave' => 'proceso_externo', 'nombre' => 'Proceso externo', 'grupo' => 'Orden producción'],
        ['clave' => 'recepcion_calidad', 'nombre' => 'Recepción calidad', 'grupo' => 'Orden producción'],
        ['clave' => 'cierre_orden_produccion', 'nombre' => 'Cierre de OP', 'grupo' => 'Orden producción'],
        ['clave' => 'kit_proceso', 'nombre' => 'Kit de proceso', 'grupo' => 'Producción'],
        ['clave' => 'ficha_tecnica_materiales', 'nombre' => 'Ficha técnica materiales', 'grupo' => 'Producción'],
        ['clave' => 'taller', 'nombre' => 'Taller', 'grupo' => 'Producción'],
        ['clave' => 'estado_op_material', 'nombre' => 'Estado OP materiales', 'grupo' => 'Producción'],
        ['clave' => 'estado_operacion', 'nombre' => 'Estado de operación', 'grupo' => 'Listas'],
        ['clave' => 'pendientes_produccion', 'nombre' => 'Pendientes producción', 'grupo' => 'Listas'],
        ['clave' => 'distribucion_por_tallas', 'nombre' => 'Distribución por tallas', 'grupo' => 'Listas'],
        ['clave' => 'usuario', 'nombre' => 'Usuario', 'grupo' => 'Personas'],
    ];

    /**
     * Panel dedicado a las entidades que tienen implementada la carga
     * masiva desde Excel. Cada tarjeta del frontend publica directamente
     * contra la ruta upload-excel de su entidad.
     */
    public function index(): Response
    {
        Myhelp::EscribirEnLog($this, ' ImportarExcel ');

        $entidades = [
            [
                'id'                  => 'producto',
                'nombre'              => 'Productos',
                'grupo'               => 'Inventario',
                'descripcion'         => 'Catálogo de productos: referencias internas y títulos de Shopify',
                'ruta'                => route('Producto.upload-excel'),
                'nota'                => 'Si el código interno ya existe se actualiza el registro; si no, se crea. La columna "Opciones" también acepta el nombre "Rango".',
            ],
            [
                'id'                  => 'material',
                'nombre'              => 'Materiales',
                'grupo'               => 'Inventario',
                'descripcion'         => 'Inventario de materias primas e insumos de producción',
                'ruta'                => route('Material.upload-excel'),
                'nota'                => 'Carga masiva por código de material: actualiza si existe, crea si no. "Proveedor habitual" debe existir en la app; se reconoce mejor por su NIT (900347285-7).',
            ],
            [
                'id'                  => 'estado_operacion',
                'nombre'              => 'Estados de Operación',
                'grupo'               => 'Listas',
                'descripcion'         => 'Máquina de estados de las órdenes de producción',
                'ruta'                => route('EstadoOperacion.upload-excel'),
                'nota'                => 'Si el nombre del estado ya existe se actualiza; si no, se crea.',
            ],
            [
                'id'                  => 'taller',
                'nombre'              => 'Talleres',
                'grupo'               => 'Producción',
                'descripcion'         => 'Ficha de terceros: talleres, empresas externas y proveedores',
                'ruta'                => route('Taller.upload-excel'),
                'nota'                => 'La fila 1 debe contener los títulos de las columnas. El nombre, el tipo proveedor y la columna "proveedor o externa o macondas" son obligatorios; el código se autogenera si queda vacío. Esa columna decide dónde va cada fila: macondas se guarda en Taller, taller de proceso externo en Empresa externa y proveedor en Proveedor; cualquier otro valor o una celda vacía cancela la subida antes de procesar el archivo. Si el registro ya existe (mismo código, mismo NIT o mismo nombre) se actualiza; las celdas vacías no borran los datos que ya tenía. La importación se procesa en segundo plano y recibirás un correo con el resultado.',
            ],
            [
                'id'                  => 'usuario',
                'nombre'              => 'Usuarios',
                'grupo'               => 'Personas',
                'descripcion'         => 'Cuentas de acceso del personal y su rol',
                'ruta'                => route('user.upload-excel'),
                'nota'                => 'La fila 1 debe contener los títulos de las columnas. Nombre, email, identificación y rol son obligatorios. Si el usuario ya existe (mismo email o misma identificación) se actualiza; si no, se crea con la contraseña identificación + "*". El rol debe existir previamente en la aplicación. La importación se procesa en segundo plano y recibirás un correo y una notificación al finalizar.',
            ],
        ];

        $metricas = $this->tracker->promedios();

        // Las columnas y el archivo descargable salen de la misma definición,
        // así la tarjeta y el formato .xlsx nunca se contradicen.
        $entidades = array_map(function (array $entidad) use ($metricas): array {
            $clave = $entidad['id'];

            return $entidad + [
                'columnas_requeridas' => $this->plantillas->titulosRequeridos($clave),
                'columnas_opcionales' => $this->plantillas->titulosOpcionales($clave),
                'plantilla'           => route('ImportarExcel.plantilla', ['clave' => $clave]),
                'metrica'             => $metricas[$clave] ?? null,
            ];
        }, $entidades);

        return Inertia::render('Importar/Index', [
            'breadcrumbs' => [['label' => 'Importar Excel', 'href' => route('ImportarExcel.index')]],
            'title'       => 'Centro de Importación Excel',
            'entidades'   => $entidades,
            'entidadesExcel' => $this->entidadesConEstado(),
        ]);
    }

    /**
     * Descarga el formato .xlsx de una entidad: columnas y las filas de
     * ejemplo con las que se arma el archivo de carga.
     */
    public function descargarPlantilla(string $clave): StreamedResponse
    {
        Myhelp::EscribirEnLog($this, 'descargarPlantilla', $clave);

        $writer = $this->plantillas->escribir($clave);
        if ($writer === null) {
            abort(404, 'No existe un formato descargable para ' . $clave);
        }

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $this->plantillas->nombreArchivo($clave), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Activa o desactiva la capacidad de carga por Excel de una entidad.
     * Todo se persiste en un único registro de Parametro.
     */
    public function toggleEntidad(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'clave' => ['required', 'string', Rule::in(array_column(self::CATALOGO_ENTIDADES, 'clave'))],
        ]);

        $parametro = $this->parametroEntidades();
        $estado = $this->estadoActual($parametro);
        $estado[$validated['clave']] = !$estado[$validated['clave']];

        $parametro->update(['valor_string1m' => json_encode($estado)]);

        Myhelp::EscribirEnLog(
            $this,
            'toggleEntidad',
            $validated['clave'] . ' => ' . ($estado[$validated['clave']] ? 'con Excel' : 'sin Excel')
        );

        return back()->with('success', 'Cobertura Excel actualizada.');
    }

    /**
     * Lista el catálogo de entidades con su estado actual de carga por Excel.
     *
     * @return array<int, array{clave: string, nombre: string, grupo: string, tiene_excel: bool}>
     */
    private function entidadesConEstado(): array
    {
        $estado = $this->estadoActual($this->parametroEntidades());

        return array_map(
            fn (array $entidad) => $entidad + ['tiene_excel' => (bool) ($estado[$entidad['clave']] ?? false)],
            self::CATALOGO_ENTIDADES
        );
    }

    /**
     * Obtiene (o crea) el registro único de Parametro con el estado por defecto.
     */
    private function parametroEntidades(): Parametro
    {
        return Parametro::firstOrCreate(
            ['nombre' => self::PARAMETRO],
            [
                'valor' => 'Entidades con carga masiva por Excel',
                'valor_string1m' => json_encode($this->estadoPorDefecto()),
            ]
        );
    }

    /**
     * Devuelve el estado guardado, completando con los valores por defecto
     * las entidades que se hayan agregado después de crear el registro.
     *
     * @return array<string, bool>
     */
    private function estadoActual(Parametro $parametro): array
    {
        $estado = json_decode((string) $parametro->valor_string1m, true);
        if (!is_array($estado)) {
            $estado = [];
        }

        return array_merge($this->estadoPorDefecto(), $estado);
    }

    /**
     * @return array<string, bool>
     */
    private function estadoPorDefecto(): array
    {
        $estado = [];
        foreach (self::CATALOGO_ENTIDADES as $entidad) {
            $estado[$entidad['clave']] = in_array($entidad['clave'], self::ENTIDADES_CON_EXCEL_DEFAULT, true);
        }

        return $estado;
    }
}
