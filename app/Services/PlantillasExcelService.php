<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Formatos descargables (.xlsx) de las entidades con carga masiva por Excel.
 *
 * Cada plantilla declara las columnas que el importador de la entidad lee
 * realmente, con su descripción y dos filas de datos de ejemplo. De ahí
 * salen tanto el archivo descargable como las columnas que muestra el Centro
 * de Importación, para que la tarjeta y el formato nunca se contradigan.
 *
 * Los títulos de la fila 1 son los que el importador normaliza: deben
 * coincidir con sus alias (snake_case o nombre legible).
 */
class PlantillasExcelService
{
    /**
     * Definición de la plantilla de cada entidad.
     *
     * @var array<string, array{nombre: string, hoja: string, archivo: string, columnas: array<int, array{titulo: string, requerida: bool, descripcion: string, ejemplos: array{string, string}}>}>
     */
    private const PLANTILLAS = [
        'producto' => [
            'nombre' => 'Productos',
            'hoja' => 'Productos',
            'archivo' => 'plantilla-productos.xlsx',
            'columnas' => [
                ['titulo' => 'Codigo interno', 'requerida' => true, 'descripcion' => 'Clave interna del producto. Si ya existe, se actualiza; si no, se crea.', 'ejemplos' => ['PROD-001', 'PROD-002']],
                ['titulo' => 'Titulo Shopify', 'requerida' => true, 'descripcion' => 'Título con el que se publica el producto en Shopify.', 'ejemplos' => ['Camisa manga corta', 'Pantalón drill']],
                ['titulo' => 'ID producto Shopify', 'requerida' => false, 'descripcion' => 'Identificador numérico del producto ya publicado en Shopify.', 'ejemplos' => ['123456789', '987654321']],
                ['titulo' => 'Categoria original', 'requerida' => false, 'descripcion' => 'Categoría tal como viene del origen (se normaliza aparte).', 'ejemplos' => ['Camisas', 'Pantalones']],
                ['titulo' => 'Familia normalizada', 'requerida' => false, 'descripcion' => 'Familia ya normalizada del catálogo.', 'ejemplos' => ['CAMISAS', 'PANTALONES']],
                ['titulo' => 'Control de variante', 'requerida' => false, 'descripcion' => 'Tipo de control de variantes del producto.', 'ejemplos' => ['color', 'talla']],
                ['titulo' => 'Opciones', 'requerida' => false, 'descripcion' => 'Opciones del producto o rango de tallas (también acepta la columna "Rango").', 'ejemplos' => ['S,M,L,XL', '30,32,34,36']],
                ['titulo' => 'Estado', 'requerida' => false, 'descripcion' => 'Activo o Inactivo.', 'ejemplos' => ['Activo', 'Inactivo']],
                ['titulo' => 'Observaciones', 'requerida' => false, 'descripcion' => 'Notas internas del producto.', 'ejemplos' => ['Pedido mínimo de 50 unidades', 'Tela de algodón']],
                ['titulo' => 'Descripcion', 'requerida' => false, 'descripcion' => 'Descripción comercial del producto.', 'ejemplos' => ['Camisa de algodón 100%', 'Pantalón drill de 5 Garfield']],
                ['titulo' => 'Precio predicho', 'requerida' => false, 'descripcion' => 'Costo anticipado del producto.', 'ejemplos' => ['25000', '38000']],
                ['titulo' => 'Precio preventa', 'requerida' => false, 'descripcion' => 'Precio de preventa del producto.', 'ejemplos' => ['45000', '69000']],
            ],
        ],
        'material' => [
            'nombre' => 'Materiales',
            'hoja' => 'Materiales',
            'archivo' => 'plantilla-materiales.xlsx',
            'columnas' => [
                ['titulo' => 'Codigo material', 'requerida' => true, 'descripcion' => 'Código del material. Si ya existe, se actualiza; si no, se crea.', 'ejemplos' => ['MAT-30', 'MAT-31']],
                ['titulo' => 'Nombre base', 'requerida' => true, 'descripcion' => 'Nombre del material.', 'ejemplos' => ['Botón forrado', 'Tela poliéster']],
                ['titulo' => 'Categoria', 'requerida' => true, 'descripcion' => 'Categoría del material.', 'ejemplos' => ['Tapas', 'Tela']],
                ['titulo' => 'Unidad inventario', 'requerida' => true, 'descripcion' => 'Cantidad actual en existencia (número entero).', 'ejemplos' => ['240', '1500']],
                ['titulo' => 'Estado', 'requerida' => true, 'descripcion' => 'Activo o Inactivo.', 'ejemplos' => ['Activo', 'Inactivo']],
                ['titulo' => 'Maneja color (SI/NO)', 'requerida' => false, 'descripcion' => 'SI: las presentaciones son colores. NO: son otras variantes.', 'ejemplos' => ['SI', 'NO']],
                ['titulo' => 'Maneja talla (SI/NO)', 'requerida' => false, 'descripcion' => 'SI: el material se maneja por tallas.', 'ejemplos' => ['NO', 'SI']],
                ['titulo' => 'Maneja otra variante (SI/NO)', 'requerida' => false, 'descripcion' => 'SI: maneja variantes distintas a color y talla. No puede ser SI junto a "Maneja color".', 'ejemplos' => ['NO', 'NO']],
                ['titulo' => 'Variante atributo sugerido', 'requerida' => false, 'descripcion' => 'Atributo sugerido de la variante (Color, Talla, etc.).', 'ejemplos' => ['Color', 'Talla']],
                ['titulo' => 'Proveedor habitual', 'requerida' => false, 'descripcion' => 'Proveedor que ya existe en la app: nombre comercial, razón social o código (PRV-001).', 'ejemplos' => ['', '']],
                ['titulo' => 'Observaciones', 'requerida' => false, 'descripcion' => 'Notas internas del material.', 'ejemplos' => ['Ejemplo con colores', 'Ejemplo con tallas']],
                ['titulo' => 'Todas_las_variantes', 'requerida' => true, 'descripcion' => 'Todas las presentaciones separadas por coma. Alimentan las variantes del material.', 'ejemplos' => ['Rojo, Azul, Negro', 'S, M, L, XL']],
                ['titulo' => 'Es producido (SI/NO)', 'requerida' => false, 'descripcion' => 'SI: el material se produce en la planta.', 'ejemplos' => ['NO', 'SI']],
            ],
        ],
        'estado_operacion' => [
            'nombre' => 'Estados de Operación',
            'hoja' => 'Estados',
            'archivo' => 'plantilla-estados-operacion.xlsx',
            'columnas' => [
                ['titulo' => 'Nombre', 'requerida' => true, 'descripcion' => 'Nombre del estado. Si ya existe, se actualiza; si no, se crea.', 'ejemplos' => ['En producción', 'Terminado']],
                ['titulo' => 'Cuando aplica', 'requerida' => false, 'descripcion' => 'Momento o condición en la que aplica el estado.', 'ejemplos' => ['Al iniciar la orden', 'Al cancelar la orden']],
                ['titulo' => 'Regla clave', 'requerida' => false, 'descripcion' => 'Regla que dispara el cambio de estado.', 'ejemplos' => ['Todas las piezas listas', 'Operación finalizada']],
                ['titulo' => 'Puede pasar a', 'requerida' => false, 'descripcion' => 'Estados destino, separados por coma.', 'ejemplos' => ['Terminado, Cancelado', 'En producción']],
            ],
        ],
        'taller' => [
            'nombre' => 'Talleres',
            'hoja' => 'Talleres',
            'archivo' => 'plantilla-talleres.xlsx',
            'columnas' => [
                ['titulo' => 'nombre', 'requerida' => true, 'descripcion' => 'Nombre del taller. Si ya existe, se actualiza; si no, se crea.', 'ejemplos' => ['Taller XYZ', 'Taller Punto']],
                ['titulo' => 'direccion', 'requerida' => false, 'descripcion' => 'Dirección del taller.', 'ejemplos' => ['Calle 10 #20-30', 'Carrera 45 #12-08']],
            ],
        ],
        'proveedor' => [
            'nombre' => 'Proveedores',
            'hoja' => 'Proveedores',
            'archivo' => 'plantilla-proveedores.xlsx',
            'columnas' => [
                ['titulo' => 'NIT', 'requerida' => true, 'descripcion' => 'NIT del proveedor. Es la clave con la que se reconoce un proveedor ya cargado.', 'ejemplos' => ['900.123.456-7', '901.234.567-8']],
                ['titulo' => 'Nombre comercial', 'requerida' => true, 'descripcion' => 'Nombre comercial del proveedor.', 'ejemplos' => ['Proveedor ABC', 'Textiles del Norte']],
                ['titulo' => 'Razón social', 'requerida' => false, 'descripcion' => 'Razón social completa.', 'ejemplos' => ['ABC S.A.S.', 'Textiles del Norte S.A.S.']],
                ['titulo' => 'Dirección', 'requerida' => false, 'descripcion' => 'Dirección física del proveedor.', 'ejemplos' => ['Calle 10 #20-30', 'Carrera 45 #12-08']],
                ['titulo' => 'Observaciones dirección', 'requerida' => false, 'descripcion' => 'Referencias para llegar: bodega, portería, punto de encuentro.', 'ejemplos' => ['Bodega 3, portería azul', 'Entrada por la carrera 45']],
                ['titulo' => 'Correo electrónico', 'requerida' => false, 'descripcion' => 'Correo de contacto (debe ser válido).', 'ejemplos' => ['contacto@proveedorabc.com', 'ventas@textilesnorte.com']],
                ['titulo' => 'Ciudad', 'requerida' => false, 'descripcion' => 'Ciudad del proveedor.', 'ejemplos' => ['Medellín', 'Bogotá']],
                ['titulo' => 'Teléfono principal', 'requerida' => false, 'descripcion' => 'Teléfono fijo o PBX.', 'ejemplos' => ['6041234567', '6012345678']],
                ['titulo' => 'Tipo proveedor', 'requerida' => false, 'descripcion' => 'Materia prima, Servicio, Mixto u Otro.', 'ejemplos' => ['Materia prima', 'Servicio']],
                ['titulo' => 'Qué suministra', 'requerida' => false, 'descripcion' => 'Insumos, productos o servicios que presta el proveedor.', 'ejemplos' => ['Telas y botones', 'Servicio de confección']],
                ['titulo' => 'Persona de contacto', 'requerida' => false, 'descripcion' => 'Nombre del asesor o de la persona de contacto.', 'ejemplos' => ['Ana Restrepo', 'Luis Pérez']],
                ['titulo' => 'Celular contacto', 'requerida' => false, 'descripcion' => 'Celular o WhatsApp de la persona de contacto.', 'ejemplos' => ['3001234567', '3109876543']],
                ['titulo' => 'Forma de pago', 'requerida' => false, 'descripcion' => 'Contado, Crédito 8/15/30 días, Anticipo u Otro.', 'ejemplos' => ['Crédito 30 días', 'Contado']],
                ['titulo' => 'Tiempo entrega', 'requerida' => false, 'descripcion' => 'Tiempo habitual de entrega en días.', 'ejemplos' => ['30', '15']],
                ['titulo' => 'Estado', 'requerida' => false, 'descripcion' => 'Activo o Inactivo.', 'ejemplos' => ['Activo', 'Inactivo']],
                ['titulo' => 'Observaciones generales', 'requerida' => false, 'descripcion' => 'Notas, acuerdos comerciales o condiciones especiales.', 'ejemplos' => ['Entrega en la planta', 'Requiere anticipo del 50%']],
                ['titulo' => 'Clasificación', 'requerida' => false, 'descripcion' => 'proveedor, taller de proceso externo o macondas.', 'ejemplos' => ['proveedor', 'taller de proceso externo']],
            ],
        ],
        'usuario' => [
            'nombre' => 'Usuarios',
            'hoja' => 'Usuarios',
            'archivo' => 'plantilla-usuarios.xlsx',
            'columnas' => [
                ['titulo' => 'Nombre', 'requerida' => true, 'descripcion' => 'Nombre completo del usuario.', 'ejemplos' => ['Ana Restrepo', 'Luis Pérez']],
                ['titulo' => 'Email', 'requerida' => true, 'descripcion' => 'Correo único con el que inicia sesión.', 'ejemplos' => ['ana@macondas.com', 'luis@macondas.com']],
                ['titulo' => 'Identificación', 'requerida' => true, 'descripcion' => 'Documento del usuario. Es la clave de emparejamiento cuando el email no coincide.', 'ejemplos' => ['1020304050', '1098765432']],
                ['titulo' => 'Rol', 'requerida' => true, 'descripcion' => 'Rol existente en la aplicación (ej. trabajador, admin).', 'ejemplos' => ['trabajador', 'admin']],
                ['titulo' => 'Cargo', 'requerida' => false, 'descripcion' => 'Cargo que desempeña.', 'ejemplos' => ['Operaria de confección', 'Analista']],
                ['titulo' => 'Celular', 'requerida' => false, 'descripcion' => 'Número de contacto del usuario.', 'ejemplos' => ['3001234567', '3109876543']],
                ['titulo' => 'Sexo', 'requerida' => false, 'descripcion' => 'Masculino, Femenino u Otro.', 'ejemplos' => ['Femenino', 'Masculino']],
                ['titulo' => 'Fecha nacimiento', 'requerida' => false, 'descripcion' => 'Fecha de nacimiento (Y-m-d o d/m/Y).', 'ejemplos' => ['1990-05-14', '1985-11-02']],
                ['titulo' => 'Salario', 'requerida' => false, 'descripcion' => 'Salario del usuario.', 'ejemplos' => ['1300000', '2500000']],
                ['titulo' => 'Fecha de ingreso', 'requerida' => false, 'descripcion' => 'Fecha de ingreso a la empresa (Y-m-d o d/m/Y).', 'ejemplos' => ['2024-01-15', '2023-08-01']],
            ],
        ],
    ];

    /**
     * Claves de las entidades que tienen plantilla descargable.
     *
     * @return string[]
     */
    public function claves(): array
    {
        return array_keys(self::PLANTILLAS);
    }

    /**
     * Definición completa de la plantilla de una entidad.
     *
     * @return array{nombre: string, hoja: string, archivo: string, columnas: array<int, array{titulo: string, requerida: bool, descripcion: string, ejemplos: array{string, string}}>}|null
     */
    public function definicion(string $clave): ?array
    {
        return self::PLANTILLAS[$clave] ?? null;
    }

    /**
     * Títulos de las columnas obligatorias de la entidad.
     *
     * @return string[]
     */
    public function titulosRequeridos(string $clave): array
    {
        return $this->titulosPorRequerida($clave, true);
    }

    /**
     * Títulos de las columnas opcionales de la entidad.
     *
     * @return string[]
     */
    public function titulosOpcionales(string $clave): array
    {
        return $this->titulosPorRequerida($clave, false);
    }

    /**
     * Nombre del archivo .xlsx que se descarga.
     */
    public function nombreArchivo(string $clave): string
    {
        return $this->definicion($clave)['archivo'] ?? 'plantilla-excel.xlsx';
    }

    /**
     * Nombre legible de la entidad.
     */
    public function nombreEntidad(string $clave): string
    {
        return $this->definicion($clave)['nombre'] ?? $clave;
    }

    /**
     * Construye el .xlsx de la plantilla: hoja de datos con los encabezados y
     * dos filas de ejemplo, más una hoja de instrucciones con la descripción
     * de cada columna.
     */
    public function spreadsheet(string $clave): ?Spreadsheet
    {
        $plantilla = $this->definicion($clave);
        if ($plantilla === null) {
            return null;
        }

        $spreadsheet = new Spreadsheet();

        $hoja = $spreadsheet->getActiveSheet();
        $hoja->setTitle($plantilla['hoja']);

        $titulos = array_column($plantilla['columnas'], 'titulo');
        $hoja->fromArray($titulos, null, 'A1');

        $filasEjemplo = [];
        foreach ([0, 1] as $indice) {
            $filasEjemplo[] = array_map(
                fn (array $columna): string => $columna['ejemplos'][$indice] ?? '',
                $plantilla['columnas']
            );
        }
        $hoja->fromArray($filasEjemplo, null, 'A2');

        $ultima = Coordinate::stringFromColumnIndex(max(count($titulos), 1));

        $encabezado = $hoja->getStyle("A1:{$ultima}1");
        $encabezado->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $encabezado->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('111827');

        $ejemplo = $hoja->getStyle("A2:{$ultima}3");
        $ejemplo->getFont()->setItalic(true)->getColor()->setRGB('9CA3AF');

        $hoja->freezePane('A2');

        foreach ($titulos as $indice => $titulo) {
            $hoja->getColumnDimension(Coordinate::stringFromColumnIndex($indice + 1))->setAutoSize(true);
        }

        $instrucciones = $spreadsheet->createSheet();
        $instrucciones->setTitle('Instrucciones');
        $filas = [['Columna', 'Obligatorio', 'Descripción', 'Ejemplo 1', 'Ejemplo 2']];
        foreach ($plantilla['columnas'] as $columna) {
            $filas[] = [
                $columna['titulo'],
                $columna['requerida'] ? 'Sí' : 'No',
                $columna['descripcion'],
                $columna['ejemplos'][0] ?? '',
                $columna['ejemplos'][1] ?? '',
            ];
        }
        $instrucciones->fromArray($filas, null, 'A1');

        $cabeceraInstrucciones = $instrucciones->getStyle('A1:E1');
        $cabeceraInstrucciones->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $cabeceraInstrucciones->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('111827');

        foreach (['A', 'B', 'C', 'D', 'E'] as $columna) {
            $instrucciones->getColumnDimension($columna)->setAutoSize(true);
        }

        $ultimaFila = count($filas) + 2;
        $instrucciones->fromArray([[
            'Las filas 2 y 3 de la primera hoja son ejemplos: bórralas antes de cargar el archivo.',
        ]], null, "A{$ultimaFila}");
        $instrucciones->getStyle("A{$ultimaFila}")->getFont()->setItalic(true)->getColor()->setRGB('B45309');

        // Los importadores leen la hoja activa: debe seguir siendo la de datos,
        // no la de instrucciones.
        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * Escribe el .xlsx en la salida estándar (para streamDownload).
     */
    public function escribir(string $clave): ?Xlsx
    {
        $spreadsheet = $this->spreadsheet($clave);
        if ($spreadsheet === null) {
            return null;
        }

        return new Xlsx($spreadsheet);
    }

    /**
     * @return string[]
     */
    private function titulosPorRequerida(string $clave, bool $requerida): array
    {
        $columnas = $this->definicion($clave)['columnas'] ?? [];

        return array_values(array_map(
            fn (array $columna): string => $columna['titulo'],
            array_filter($columnas, fn (array $columna): bool => $columna['requerida'] === $requerida)
        ));
    }
}
