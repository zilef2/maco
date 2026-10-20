<?php

namespace App\Http\Controllers;

use App\helpers\Myhelp;
use App\Models\Parametro;
use App\Models\Proveedor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ImportarExcelController extends Controller
{
    /**
     * Registro único de Parametro donde se guarda qué entidades tienen
     * habilitada la carga masiva por Excel.
     */
    private const PARAMETRO = 'importar_excel_entidades';

    /**
     * Entidades que se muestran en el panel informativo y que, por ahora,
     * tienen la carga por Excel habilitada.
     */
    private const ENTIDADES_CON_EXCEL_DEFAULT = ['producto', 'material', 'estado_operacion', 'taller', 'proveedor'];

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
        ['clave' => 'proveedor', 'nombre' => 'Proveedor', 'grupo' => 'Personas'],
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

        // Las columnas de proveedores se derivan del fillable del modelo.
        $proveedorRequeridas = ['nombre', 'categoria'];
        $proveedorOpcionales = array_values(array_diff(
            (new Proveedor())->getFillable(),
            $proveedorRequeridas
        ));

        $entidades = [
            [
                'id'                  => 'producto',
                'nombre'              => 'Productos',
                'descripcion'         => 'Catálogo de productos: referencias internas y títulos de Shopify',
                'ruta'                => route('Producto.upload-excel'),
                'columnas_requeridas' => ['Codigo interno', 'Titulo Shopify'],
                'columnas_opcionales' => [
                    'ID producto Shopify', 'Categoria original', 'Familia normalizada',
                    'Control de variante', 'Opciones', 'Rango', 'Estado',
                    'Observaciones', 'Descripcion', 'Precio predicho', 'Precio preventa',
                ],
                'nota' => 'Si el código interno ya existe se actualiza el registro; si no, se crea. La columna "Rango" se guarda en "Opciones".',
            ],
            [
                'id'                  => 'material',
                'nombre'              => 'Materiales',
                'descripcion'         => 'Inventario de materias primas e insumos de producción',
                'ruta'                => route('Material.upload-excel'),
                'columnas_requeridas' => ['codigo_material', 'nombre_base', 'categoria', 'unidad_inventario', 'estado'],
                'columnas_opcionales' => [
                    'maneja_color', 'maneja_talla', 'maneja_otra_variante',
                    'variante_atributo_sugerido', 'proveedor_habitual', 'observaciones',
                ],
                'nota' => 'Carga masiva por código de material: actualiza si existe, crea si no.',
            ],
            [
                'id'                  => 'estado_operacion',
                'nombre'              => 'Estados de Operación',
                'descripcion'         => 'Máquina de estados de las órdenes de producción',
                'ruta'                => route('EstadoOperacion.upload-excel'),
                'columnas_requeridas' => ['Nombre'],
                'columnas_opcionales' => ['Cuando aplica', 'Regla clave', 'Puede pasar a'],
                'nota' => 'Si el nombre del estado ya existe se actualiza; si no, se crea.',
            ],
            [
                'id'                  => 'taller',
                'nombre'              => 'Talleres',
                'descripcion'         => 'Talleres de confección a los que se asignan las órdenes de producción',
                'ruta'                => route('Taller.upload-excel'),
                'columnas_requeridas' => ['nombre'],
                'columnas_opcionales' => ['direccion'],
                'nota' => 'La fila 1 debe contener los títulos de las columnas. El nombre es obligatorio; la dirección es opcional. La importación se procesa en segundo plano y recibirás un correo con el resultado.',
            ],
            [
                'id'                  => 'proveedor',
                'nombre'              => 'Proveedores',
                'descripcion'         => 'Directorio de proveedores y sus datos de contacto',
                'ruta'                => route('Proveedor.upload-excel'),
                'columnas_requeridas' => $proveedorRequeridas,
                'columnas_opcionales' => $proveedorOpcionales,
                'nota' => 'La fila 1 debe contener los títulos de las columnas. Si el proveedor ya existe (mismo nombre) se actualiza; si no, se crea. Los celulares son opcionales. La importación se procesa en segundo plano y recibirás un correo con el resultado.',
            ],
        ];

        return Inertia::render('Importar/Index', [
            'breadcrumbs' => [['label' => 'Importar Excel', 'href' => route('ImportarExcel.index')]],
            'title'       => 'Centro de Importación Excel',
            'entidades'   => $entidades,
            'entidadesExcel' => $this->entidadesConEstado(),
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
