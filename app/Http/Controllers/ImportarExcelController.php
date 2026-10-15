<?php

namespace App\Http\Controllers;

use App\helpers\Myhelp;
use Inertia\Inertia;
use Inertia\Response;

class ImportarExcelController extends Controller
{
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
        ];

        return Inertia::render('Importar/Index', [
            'breadcrumbs' => [['label' => 'Importar Excel', 'href' => route('ImportarExcel.index')]],
            'title'       => 'Centro de Importación Excel',
            'entidades'   => $entidades,
        ]);
    }
}
