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
 * realmente, con su descripción y sus filas de datos de ejemplo. De ahí
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
     * @var array<string, array{nombre: string, hoja: string, archivo: string, columnas: array<int, array{titulo: string, requerida: bool, descripcion: string, ejemplos: array<int, string>}>}>
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
                ['titulo' => 'Código material', 'requerida' => true, 'descripcion' => 'Clave del material con el formato CAT-BASE-N, sin ceros a la izquierda. Si ya existe, se actualiza; si está vacío o el código no existe, se crea con el siguiente número libre del par. TEX-LFL-005 se guarda como TEX-LFL-5.', 'ejemplos' => ['TEX-LFI-1', 'TEX-LFL-5', 'PRE-CBO-1']],
                ['titulo' => 'Código categoría', 'requerida' => false, 'descripcion' => 'Hasta 3 letras de la categoría (TEX). Forma el prefijo del código; si queda vacío se abrevia "Categoría".', 'ejemplos' => ['TEX', 'TEX', 'PRE']],
                ['titulo' => 'Categoría', 'requerida' => false, 'descripcion' => 'Nombre de la categoría del material.', 'ejemplos' => ['Textil', 'Textil', 'Prendas']],
                ['titulo' => 'Código base', 'requerida' => false, 'descripcion' => 'Hasta 3 letras del nombre base (LFI). Forma el prefijo del código; si queda vacío se abrevia "Nombre base".', 'ejemplos' => ['LFI', 'LFL', 'CBO']],
                ['titulo' => 'Nombre base', 'requerida' => true, 'descripcion' => 'Nombre del material.', 'ejemplos' => ['Lana fibrada', 'Lana flock', 'Botón forrado']],
                ['titulo' => 'Modelo / diseño / acabado', 'requerida' => false, 'descripcion' => 'Modelo, diseño o acabado del material (Liso, Rasurado, Estampado…).', 'ejemplos' => ['Rasurado', 'Liso', '']],
                ['titulo' => 'Color', 'requerida' => false, 'descripcion' => 'Colores del material separados por coma (ej: Rojo, Azul, Negro).', 'ejemplos' => ['Rojo, Azul, Negro', '', 'Rojo, Negro']],
                ['titulo' => 'Talla / tamaño', 'requerida' => false, 'descripcion' => 'Tallas o tamaños del material separados por coma (ej: S, M, L, XL).', 'ejemplos' => ['S, M', 'S, M, L, XL', '']],
                ['titulo' => 'Unidad inventario', 'requerida' => false, 'descripcion' => 'Nombre de la unidad con la que se mide el material (Metros, Unidades, Kilos). Si queda vacío se asume "Unidades".', 'ejemplos' => ['Metros', 'Metros', 'Unidades']],
                ['titulo' => 'Proveedor habitual', 'requerida' => false, 'descripcion' => 'Lo más seguro es el NIT tal como está registrado (900347285-7); también sirve el nombre comercial, la razón social o el código (PRV-008). Si ningún proveedor coincide, no se importa nada.', 'ejemplos' => ['', '', '']],
                ['titulo' => 'Estado', 'requerida' => true, 'descripcion' => 'Activo o Inactivo.', 'ejemplos' => ['Activo', 'Activo', 'Inactivo']],
                ['titulo' => 'Observaciones', 'requerida' => false, 'descripcion' => 'Notas internas del material.', 'ejemplos' => ['Rinde 3 metros por prenda', 'Ejemplo con tallas', 'Ejemplo con el número ya usado: se actualiza']],
                ['titulo' => 'Costo', 'requerida' => false, 'descripcion' => 'Costo unitario del material. En la app se guarda como Precio: alimenta el histórico de precios y el costo de las OPs abiertas.', 'ejemplos' => ['8500', '7200', '15000']],
                ['titulo' => 'Cantidad', 'requerida' => false, 'descripcion' => 'Existencia actual del material. La celda vacía no cambia la existencia que ya tiene el registro; el número la reemplaza y queda anotada en el histórico de movimientos.', 'ejemplos' => ['120', '35', '']],
                ['titulo' => 'Procesado', 'requerida' => true, 'descripcion' => 'si o no: si el material ya viene procesado. Es obligatorio que esta columna cierre el bloque, justo después de "Costo" (o de "Cantidad" si la usas); una celda vacía se toma como no y cualquier otro texto cancela la subida.', 'ejemplos' => ['si', 'no', 'si']],
            ],
        ],
        'ficha_tecnica_materiales' => [
            'nombre' => 'Ficha técnica de materiales',
            'hoja' => 'Ficha técnica',
            'archivo' => 'plantilla-ficha-tecnica-materiales.xlsx',
            'columnas' => [
                ['titulo' => 'Producto o material procesado', 'requerida' => true, 'descripcion' => 'Código del producto o del material con proceso al que pertenece la ficha. Un mismo objetivo puede repetirse en varias filas, una por cada material de su receta.', 'ejemplos' => ['CAL-BOT-01', 'MAT-PRO-1']],
                ['titulo' => 'Material', 'requerida' => true, 'descripcion' => 'Código del material existente en el inventario que se incorpora a la ficha.', 'ejemplos' => ['CUE-VAC-1', 'FOR-BAD-2']],
                ['titulo' => 'Cantidad', 'requerida' => true, 'descripcion' => 'Cantidad o consumo estándar del material por una unidad del producto o material procesado. Debe ser mayor que cero y usar punto decimal.', 'ejemplos' => ['0.35', '0.25']],
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
            'hoja' => 'Taller',
            'archivo' => 'plantilla-talleres.xlsx',
            'columnas' => [
                ['titulo' => 'Código_proveedor', 'requerida' => false, 'descripcion' => 'Código de la entidad (TAL-001, EMP-001, PRV-001). Si ya existe, se actualiza; si queda vacío se autogenera.', 'ejemplos' => ['TAL-901', 'EMP-901', 'PRV-901']],
                ['titulo' => 'NIT', 'requerida' => false, 'descripcion' => 'NIT del tercero. Reconoce al registro ya cargado cuando no hay código.', 'ejemplos' => ['900.111.222-3', '900.333.444-5', '900.666.777-8']],
                ['titulo' => 'Nombre', 'requerida' => true, 'descripcion' => 'Nombre comercial. Es el nombre que muestran los desplegables.', 'ejemplos' => ['Taller de ejemplo', 'Bordados de ejemplo', 'Telas de ejemplo']],
                ['titulo' => 'Razón social', 'requerida' => false, 'descripcion' => 'Razón social completa.', 'ejemplos' => ['Taller de ejemplo S.A.S.', 'Bordados de ejemplo S.A.S.', 'Telas de ejemplo S.A.S.']],
                ['titulo' => 'Dirección', 'requerida' => false, 'descripcion' => 'Dirección física.', 'ejemplos' => ['Calle 10 #20-30', 'Carrera 45 #12-08', 'Calle 5 #8-14']],
                ['titulo' => 'Observaciones', 'requerida' => false, 'descripcion' => 'Referencias para llegar: bodega, portería, punto de encuentro.', 'ejemplos' => ['Bodega 3, portería azul', 'Entrada por la carrera 45', 'Muelle de descargas']],
                ['titulo' => 'Correo', 'requerida' => false, 'descripcion' => 'Correo electrónico (debe ser válido).', 'ejemplos' => ['taller.ejemplo@correo.com', 'externa.ejemplo@correo.com', 'proveedor.ejemplo@correo.com']],
                ['titulo' => 'Ciudad', 'requerida' => false, 'descripcion' => 'Ciudad del tercero.', 'ejemplos' => ['Medellín', 'Medellín', 'Bogotá']],
                ['titulo' => 'Teléfono', 'requerida' => false, 'descripcion' => 'Teléfono fijo o PBX.', 'ejemplos' => ['6041234567', '6012345678', '6048765432']],
                ['titulo' => 'Tipo proveedor', 'requerida' => true, 'descripcion' => 'Materia prima, Servicio, Mixto u Otro.', 'ejemplos' => ['Servicio', 'Servicio', 'Materia prima']],
                ['titulo' => 'Qué suministra', 'requerida' => false, 'descripcion' => 'Insumos, productos o servicios que presta.', 'ejemplos' => ['Confección de polos', 'Bordado y estampado', 'Telas y botones']],
                ['titulo' => 'Persona de contacto', 'requerida' => false, 'descripcion' => 'Nombre de la persona de contacto.', 'ejemplos' => ['Ana Restrepo', 'Luis Pérez', 'Carla Gómez']],
                ['titulo' => 'Celular', 'requerida' => false, 'descripcion' => 'Celular o WhatsApp del contacto.', 'ejemplos' => ['3001234567', '3109876543', '3204567890']],
                ['titulo' => 'plazo de pago', 'requerida' => false, 'descripcion' => 'Contado, Crédito 8/15/30 días, Anticipo u Otro.', 'ejemplos' => ['Crédito 15 días', 'Contado', 'Crédito 30 días']],
                ['titulo' => 'Tiempo habitual de entrega', 'requerida' => false, 'descripcion' => 'Tiempo típico de entrega, en el formato que maneje el tercero.', 'ejemplos' => ['15 días', '1 semana', '30 días']],
                ['titulo' => 'Estado', 'requerida' => false, 'descripcion' => 'Activo o Inactivo. Si queda vacío se toma como Activo.', 'ejemplos' => ['Activo', 'Activo', 'Inactivo']],
                ['titulo' => 'Observaciones generales', 'requerida' => false, 'descripcion' => 'Notas, acuerdos comerciales o condiciones especiales.', 'ejemplos' => ['Entrega en la planta', 'Requiere anticipo del 50%', 'Cotiza por volumen']],
                ['titulo' => 'proveedor o externa o macondas', 'requerida' => true, 'descripcion' => 'Destino de la fila: "macondas" guarda un Taller, "taller de proceso externo" guarda una Empresa externa y "proveedor" guarda un Proveedor. Es obligatorio y no se aceptan otros valores: una celda vacía o distinta cancela toda la subida.', 'ejemplos' => ['macondas', 'taller de proceso externo', 'proveedor']],
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
     * @return array{nombre: string, hoja: string, archivo: string, columnas: array<int, array{titulo: string, requerida: bool, descripcion: string, ejemplos: array<int, string>}>}|null
     */
    public function definicion(string $clave): ?array
    {
        return self::PLANTILLAS[$clave] ?? null;
    }

    /**
     * Columnas de la plantilla con su título, si es obligatoria y su
     * descripción. De ahí salen las listas que muestran los modales de subida,
     * para que la ayuda no se escriba por separado del formato descargable.
     *
     * @return array<int, array{titulo: string, requerida: bool, descripcion: string, ejemplos: array<int, string>}>
     */
    public function columnas(string $clave): array
    {
        return self::PLANTILLAS[$clave]['columnas'] ?? [];
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
     * las filas de ejemplo que declare la definición (una por cada valor de
     * ejemplo de las columnas), más una hoja de instrucciones con la
     * descripción de cada columna.
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

        // Cantidad de filas de ejemplo: la columna que más traiga define el
        // ancho de la muestra, para que cada destino tenga su propia fila.
        $cantEjemplos = 0;
        foreach ($plantilla['columnas'] as $columna) {
            $cantEjemplos = max($cantEjemplos, count($columna['ejemplos']));
        }

        $filasEjemplo = [];
        for ($indice = 0; $indice < $cantEjemplos; $indice++) {
            $filasEjemplo[] = array_map(
                fn (array $columna): string => $columna['ejemplos'][$indice] ?? '',
                $plantilla['columnas']
            );
        }
        if ($cantEjemplos > 0) {
            $hoja->fromArray($filasEjemplo, null, 'A2');
        }

        $ultima = Coordinate::stringFromColumnIndex(max(count($titulos), 1));
        $ultimaFilaEjemplo = $cantEjemplos + 1;

        $encabezado = $hoja->getStyle("A1:{$ultima}1");
        $encabezado->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $encabezado->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('111827');

        if ($cantEjemplos > 0) {
            $ejemplo = $hoja->getStyle("A2:{$ultima}{$ultimaFilaEjemplo}");
            $ejemplo->getFont()->setItalic(true)->getColor()->setRGB('9CA3AF');
        }

        $hoja->freezePane('A2');

        foreach ($titulos as $indice => $titulo) {
            $hoja->getColumnDimension(Coordinate::stringFromColumnIndex($indice + 1))->setAutoSize(true);
        }

        $instrucciones = $spreadsheet->createSheet();
        $instrucciones->setTitle('Instrucciones');

        $filas = [array_merge(
            ['Columna', 'Obligatorio', 'Descripción'],
            array_map(fn (int $n): string => 'Ejemplo ' . $n, range(1, $cantEjemplos))
        )];
        foreach ($plantilla['columnas'] as $columna) {
            $fila = [
                $columna['titulo'],
                $columna['requerida'] ? 'Sí' : 'No',
                $columna['descripcion'],
            ];
            for ($indice = 0; $indice < $cantEjemplos; $indice++) {
                $fila[] = $columna['ejemplos'][$indice] ?? '';
            }
            $filas[] = $fila;
        }
        $instrucciones->fromArray($filas, null, 'A1');

        $ultimaColumnaInstrucciones = Coordinate::stringFromColumnIndex(3 + $cantEjemplos);
        $cabeceraInstrucciones = $instrucciones->getStyle("A1:{$ultimaColumnaInstrucciones}1");
        $cabeceraInstrucciones->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $cabeceraInstrucciones->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('111827');

        foreach (range(1, 3 + $cantEjemplos) as $indice) {
            $instrucciones->getColumnDimension(Coordinate::stringFromColumnIndex($indice))->setAutoSize(true);
        }

        if ($cantEjemplos > 0) {
            $ultimaFila = count($filas) + 2;
            $instrucciones->fromArray([[
                "Las filas 2 a {$ultimaFilaEjemplo} de la primera hoja son ejemplos: bórralas antes de cargar el archivo.",
            ]], null, "A{$ultimaFila}");
            $instrucciones->getStyle("A{$ultimaFila}")->getFont()->setItalic(true)->getColor()->setRGB('B45309');
        }

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
