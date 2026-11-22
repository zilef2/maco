<?php

namespace App\Http\Controllers;

use App\helpers\Myhelp;
use App\Jobs\ImportarFichaProductoJob;
use App\Jobs\ImportarMPPJob;
use App\Models\Parametro;
use App\Services\ExcelFichasProductoService;
use App\Services\ExcelImportacionTracker;
use App\Services\ExcelMPPService;
use App\Services\ImportacionExcelNotificador;
use App\Services\PlantillasExcelService;
use App\Services\VerificarImportacionService;
use App\Traits\ExcelDesdeDriveTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportarExcelController extends Controller
{
    use ExcelDesdeDriveTrait;

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
    private const ENTIDADES_CON_EXCEL_DEFAULT = ['producto', 'material', 'estado_operacion', 'taller', 'usuario', 'mpp', 'ficha_tecnica_materiales'];

    private const CATALOGO_ENTIDADES = [
        ['clave' => 'producto', 'nombre' => 'Producto', 'grupo' => 'Inventario'],
        ['clave' => 'material', 'nombre' => 'Material', 'grupo' => 'Inventario'],
        ['clave' => 'mpp', 'nombre' => 'MPP: material, producto, proveedor y ficha', 'grupo' => 'Inventario'],
        ['clave' => 'orden_produccion', 'nombre' => 'Orden de producción', 'grupo' => 'Orden producción'],
        ['clave' => 'proceso_externo', 'nombre' => 'Proceso externo', 'grupo' => 'Orden producción'],
        ['clave' => 'recepcion_calidad', 'nombre' => 'Recepción calidad', 'grupo' => 'Orden producción'],
        ['clave' => 'cierre_orden_produccion', 'nombre' => 'Cierre de OP', 'grupo' => 'Orden producción'],
        ['clave' => 'kit_proceso', 'nombre' => 'Kit de proceso', 'grupo' => 'Producción'],
        ['clave' => 'ficha_tecnica_materiales', 'nombre' => 'Ficha de producto', 'grupo' => 'Producción'],
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
    public function index(Request $request): Response
    {
        Myhelp::EscribirEnLog($this, ' ImportarExcel ');

        // Avisos de la última subida que no cancelaron la carga (por ejemplo,
        // las filas de la ficha que llegaron sin consumo y se tomaron como 1).
        $avisosPorEntidad = (array) $request->session()->get('avisosImportacion', []);

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
                'id'                  => 'ficha_tecnica_materiales',
                'nombre'              => 'Ficha de producto',
                'grupo'               => 'Producción',
                'descripcion'         => 'Receta de materiales de cada producto, una fila por material',
                'ruta'                => route('ImportarExcel.ficha-producto'),
                'nota'                => 'La hoja se llama "Codigos" y la fila 1 trae los títulos: "Codigo producto", "Producto", "codigo material", "Material", "consumo por par" y "unidad". Cada fila es un material de la receta del producto, y todas las filas con el mismo código de producto forman una sola ficha llamada "Ficha PT-042", con el código del producto. Si ese producto ya tenía ficha, se reemplazan sus materiales por los del archivo; si la ficha compartida cubre más productos, se conservan. El código de material se valida por su par, sin mirar el número del final: si existe ACC-APL-0, valen ACC-APL-1, ACC-APL-7 o ACC-APL-9898, y los tres se guardan como el material genérico del par (ACC-APL-0), que es el único que admite la ficha técnica; si el par existe pero no tiene su genérico, créelo con el botón «Materiales genéricos» de Actividad. Las columnas "Producto" y "Material" sirven para ubicar el registro solo cuando la celda del código quedó vacía, y si el nombre coincide con varios registros la fila se rechaza: escriba el código. Una celda vacía en "consumo por par" se toma como 1 y las filas que pasaron por ahí quedan listadas en el aviso amarillo de esta tarjeta y en el correo del resultado. Antes de guardar, la subida verifica que cada producto y cada material existan en la app y que ninguna orden de producción abierta bloquee la ficha: si falta uno solo o hay una OP activa, se rechaza el archivo completo con la lista, sin importar nada. Por eso la validación tarda unos segundos. El archivo se procesa en segundo plano y recibirás un correo con el resultado.',
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
                'nombre'              => 'Proveedores y terceros',
                'grupo'               => 'Producción',
                'descripcion'         => 'Ficha de terceros: talleres, empresas externas y proveedores',
                'ruta'                => route('Proveedor.upload-terceros-excel'),
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
            [
                'id'                  => 'mpp',
                'nombre'              => 'MPP: materiales, productos, proveedores y fichas',
                'grupo'               => 'Inventario',
                'descripcion'         => 'Un solo archivo con las hojas material, producto, proveedor y ficha',
                'ruta'                => route('ImportarExcel.mpp'),
                'nota'                => 'El archivo debe traer las hojas "material", "producto" y "proveedor"; las demás se descartan. Cada hoja usa el mismo formato de su carga individual y la fila 1 son los títulos. En la hoja "proveedor" la columna de clasificación decide el destino de cada fila: "proveedor" guarda un Proveedor, "macondas" un Taller y "taller de proceso externo" una Empresa externa; una celda vacía o con otro valor cancela la subida. Se importa primero esa hoja, para que los materiales del mismo archivo ya puedan señalar a su proveedor habitual. La hoja "ficha" es opcional y trae las recetas de los productos, con el mismo formato de la tarjeta de Ficha de producto; se procesa al final, porque cada producto y cada material que señala la receta los acaba de crear este mismo archivo. Cualquier hoja con una fila mal escrita cancela todo el archivo: no queda nada importado. La importación se procesa en segundo plano y recibirás un correo con el resultado.',
                'hojas'               => $this->resumenHojasMPP(),
            ],
        ];

        $metricas = $this->tracker->promedios();

        // Las columnas y el archivo descargable salen de la misma definición,
        // así la tarjeta y el formato .xlsx nunca se contradicen. El MPP no
        // tiene formato propio: muestra las columnas de cada una de sus hojas.
        $entidades = array_map(function (array $entidad) use ($metricas, $avisosPorEntidad): array {
            $clave = $entidad['id'];

            return $entidad + [
                'columnas_requeridas' => $clave === 'mpp' ? [] : $this->plantillas->titulosRequeridos($clave),
                'columnas_opcionales' => $clave === 'mpp' ? [] : $this->plantillas->titulosOpcionales($clave),
                'plantilla'           => $clave === 'mpp'
                    ? route('ImportarExcel.plantilla-mpp')
                    : route('ImportarExcel.plantilla', ['clave' => $clave]),
                'verificacion'        => in_array($clave, VerificarImportacionService::ENTIDADES, true)
                    ? route('ImportarExcel.verificar')
                    : null,
                'metrica'             => $metricas[$clave] ?? null,
                'avisos'              => array_values(array_map('strval', (array) ($avisosPorEntidad[$clave] ?? []))),
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
     * Descarga el libro MPP de ejemplo: una hoja por entidad (proveedor,
     * material, producto y ficha) con sus encabezados y filas de ejemplo,
     * más una hoja de instrucciones.
     */
    public function descargarPlantillaMpp(): StreamedResponse
    {
        Myhelp::EscribirEnLog($this, 'descargarPlantillaMpp');

        $writer = new Xlsx($this->plantillas->spreadsheetMpp());

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $this->plantillas->nombreArchivoMpp(), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Carga masiva MPP: materiales, productos, proveedores y fichas en un solo
     * archivo.
     *
     * Valida de forma síncrona (tamaño, escaneo de seguridad, las hojas y sus
     * títulos) y solo si todo está correcto encola el job que inserta en la BD y
     * envía los correos.
     *
     * El libro llega adjunto o bajado desde el enlace de Google Drive que trae
     * la tarjeta; por el enlace se importa exactamente el archivo publicado.
     */
    public function uploadMpp(
        Request $request,
        ExcelMPPService $excel,
        ImportacionExcelNotificador $notificador,
    ): RedirectResponse {
        Myhelp::EscribirEnLog($this, ' uploadMpp ');

        $request->validate(self::reglasDeSubidaExcel(), self::mensajesDeSubidaExcel());

        $archivo = $this->excelDeLaSubida($request);
        $usuario = $request->user();
        $nombreArchivo = $archivo->getClientOriginalName();
        $rutaRelativa = null;

        try {
            if (strtolower($archivo->getClientOriginalExtension()) !== 'xlsx') {
                throw new RuntimeException('Solo se permiten archivos con extensión .xlsx.');
            }

            // 1. Escaneo de seguridad del contenedor XLSX.
            $problemas = $excel->escanearSeguridad($archivo->getRealPath());
            if (!empty($problemas)) {
                throw new RuntimeException(implode(' | ', $problemas));
            }

            // 2. Las hojas y sus títulos: el contenido de los datos lo valida
            // el job antes de insertar, porque las fichas necesitan los
            // materiales y los productos que el mismo archivo va a crear.
            $excel->validarLibro($archivo->getRealPath());

            // 3. Todo válido: guardar y encolar la importación.
            $rutaRelativa = $archivo->store('imports/mpp', 'local');
            if (!$rutaRelativa) {
                throw new RuntimeException('No se pudo guardar el archivo para su procesamiento.');
            }

            ImportarMPPJob::dispatch(
                $rutaRelativa,
                $nombreArchivo,
                $usuario?->email,
                $usuario?->name,
                $usuario?->id,
                $request->attributes->get('importacion_excel_id'),
            );

            Myhelp::EscribirEnLog($this, 'uploadMpp ENCOLADO', $nombreArchivo);

            return back()->with(
                'success',
                'Archivo recibido correctamente. La importación de las hojas material, producto, '
                . 'proveedor y ficha se está procesando; si alguna fila tiene errores recibirás el detalle por correo.'
            );
        } catch (\Throwable $e) {
            if ($rutaRelativa) {
                Storage::disk('local')->delete($rutaRelativa);
            }

            $notificador->fallo($usuario?->email, $usuario?->name, $nombreArchivo, 'MPP (materiales, productos, proveedores y fichas)', $e);
            Myhelp::EscribirEnLog($this, 'uploadMpp ERROR', $e->getMessage());

            return back()->with('error', 'No se importó nada: ' . $e->getMessage());
        }
    }

    /**
     * Carga masiva de la ficha de producto.
     *
     * A diferencia del resto de las tarjetas, aquí se valida el contenido
     * completo del archivo en la misma petición: cada producto tiene que existir
     * en la app y cada material tiene que apuntar a un par con su genérico
     * (CAT-BAS-0), y ninguna ficha puede estar bloqueada por una OP activa. Si
     * falta algo, el archivo se rechaza con la lista de los códigos y el usuario
     * lo corrige antes de volver a subirlo; por eso hay que esperar unos segundos
     * mientras se lee la hoja.
     */
    public function uploadFichaProducto(
        Request $request,
        ExcelFichasProductoService $excel,
    ): RedirectResponse {
        Myhelp::EscribirEnLog($this, ' uploadFichaProducto ');

        $request->validate(self::reglasDeSubidaExcel(), self::mensajesDeSubidaExcel());

        $archivo = $this->excelDeLaSubida($request);
        $usuario = $request->user();
        $nombreArchivo = $archivo->getClientOriginalName();
        $rutaRelativa = null;

        try {
            if (strtolower($archivo->getClientOriginalExtension()) !== 'xlsx') {
                throw new RuntimeException('Solo se permiten archivos con extensión .xlsx.');
            }

            $problemas = $excel->escanearSeguridad($archivo->getRealPath());
            if (!empty($problemas)) {
                throw new RuntimeException(implode(' | ', $problemas));
            }

            // Se rechaza todo el archivo aquí, antes de encolar nada.
            $validacion = $excel->validar($archivo->getRealPath());

            $rutaRelativa = $archivo->store('imports/ficha-producto', 'local');
            if (!$rutaRelativa) {
                throw new RuntimeException('No se pudo guardar el archivo para su procesamiento.');
            }

            ImportarFichaProductoJob::dispatch(
                $rutaRelativa,
                $nombreArchivo,
                $usuario?->email,
                $usuario?->name,
                $usuario?->id,
                $request->attributes->get('importacion_excel_id'),
            );

            Myhelp::EscribirEnLog($this, 'uploadFichaProducto ENCOLADO', $nombreArchivo);

            // Lo que el archivo permitió corregir solos (consumos vacíos) se
            // queda visible en la tarjeta mientras se procesa la cola.
            $avisos = $validacion['avisos'];

            return back()
                ->with('avisosImportacion', ['ficha_tecnica_materiales' => $avisos])
                ->with(
                    'success',
                    'El archivo pasó la validación: ' . $validacion['fichas'] . ' ficha(s), '
                    . $validacion['materiales'] . ' material(es) en ' . $validacion['filas']
                    . ' fila(s). ' . ($avisos === [] ? '' : implode(' ', $avisos) . ' ')
                    . 'Las fichas se están guardando; recibirás un correo al terminar.'
                );
        } catch (\Throwable $e) {
            if ($rutaRelativa) {
                Storage::disk('local')->delete($rutaRelativa);
            }

            Myhelp::EscribirEnLog($this, 'uploadFichaProducto ERROR', $e->getMessage());

            return back()->with('error', 'No se importó nada: ' . $e->getMessage());
        }
    }

    /**
     * Prueba la subida sin tocar la base de datos: los mismos requisitos que
     * corre la importación, pero juntando todos los problemas y devolviéndolos a
     * la tarjeta. No encola job ni manda correo: es para corregir el archivo
     * antes de subirlo.
     */
    public function verificar(Request $request, VerificarImportacionService $verificador): JsonResponse
    {
        $datos = $request->validate(
            ['entidad' => ['required', 'string', Rule::in(VerificarImportacionService::ENTIDADES)]]
                + self::reglasDeSubidaExcel(),
            self::mensajesDeSubidaExcel()
        );

        $archivo = $this->excelDeLaSubida($request);

        Myhelp::EscribirEnLog($this, 'verificar', $datos['entidad'] . ' | ' . $archivo->getClientOriginalName());

        return response()->json($verificador->verificar($datos['entidad'], $archivo));
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
     * Columnas de cada hoja del archivo MPP. Salen de las mismas definiciones
     * de PlantillasExcelService que usan las tarjetas de Materiales, Productos,
     * Talleres y Ficha de producto, para que el MPP no tenga una ayuda propia
     * que se desactualice. El orden es el de la importación.
     * La hoja "proveedor" trae la ficha de terceros, así que repite el formato
     * del archivo de talleres: su columna de clasificación decide si cada fila
     * termina en Taller, Empresa externa o Proveedor. La hoja "ficha" repite el
     * formato de la ficha individual y es la última: su receta señala los
     * materiales y productos que las hojas anteriores acaban de escribir.
     *
     * @return array<int, array{nombre: string, columnas_requeridas: string[], columnas_opcionales: string[], columnas: array<int, array{titulo: string, requerida: bool}>}>
     */
    private function resumenHojasMPP(): array
    {
        $hojas = [];

        foreach ($this->plantillas->hojasMpp() as $hoja => $clave) {
            $hojas[] = [
                'nombre'              => $hoja,
                'columnas_requeridas' => $this->plantillas->titulosRequeridos($clave),
                'columnas_opcionales' => $this->plantillas->titulosOpcionales($clave),
                'columnas'            => $this->plantillas->columnasOrdenadas($clave),
            ];
        }

        return $hojas;
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
