<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
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
                ['titulo' => 'Cantidad', 'requerida' => false, 'descripcion' => 'Existencia actual del producto. El número reemplaza el stock de la app y se reparte en partes iguales entre las tallas que el producto ya tiene (el sobrante va a la primera); la celda vacía lo deja en cero. Cada cambio queda anotado en el histórico de movimientos del producto.', 'ejemplos' => ['120', '35', '']],
            ],
        ],
        'material' => [
            'nombre' => 'Materiales',
            'hoja' => 'Materiales',
            'archivo' => 'plantilla-materiales.xlsx',
            'columnas' => [
                ['titulo' => 'Código material', 'requerida' => true, 'descripcion' => 'Clave del material: CAT-BAS-X para el genérico de la familia o CAT-BAS-X-Y para uno de sus hijos (el material con su color o talla). X es el número de la empresa e Y el consecutivo dentro de la familia; los dos empiezan en 1 y ninguno puede ser 0. El número de familia no es relleno: TEX-LFL-055 y TEX-LFL-096 son familias distintas. Se guarda con sus ceros (COM-CAP-55-4 queda COM-CAP-055-04). El rol de la fila lo decide el código: el de tres tramos se marca como genérico y el de cuatro como concreto. Si el código ya existe, se actualiza; si está vacío, el material se crea como hijo de la familia más alta del par.', 'ejemplos' => ['TEX-LFI-001', 'TEX-LFL-055-34', 'PRE-CBO-002-01']],
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
            'nombre' => 'Ficha de producto',
            'hoja' => 'Codigos',
            'archivo' => 'plantilla-ficha-producto.xlsx',
            'columnas' => [
                ['titulo' => 'Codigo producto', 'requerida' => true, 'descripcion' => 'Código interno del producto (productos.Codigo_interno, ej: PT-042). Todas las filas con el mismo código forman una sola ficha. Debe existir en la app: si falta, se rechaza el archivo completo antes de importar.', 'ejemplos' => ['PT-998', 'PT-998']],
                ['titulo' => 'Producto', 'requerida' => false, 'descripcion' => 'Nombre del producto (productos.Titulo_Shopify). Se usa para ubicar el producto solo cuando la celda de "Codigo producto" quedó vacía; si el nombre coincide con varios productos, la fila se rechaza.', 'ejemplos' => ['Zapato de ejemplo', 'Zapato de ejemplo']],
                ['titulo' => 'codigo material', 'requerida' => true, 'descripcion' => 'Código del material genérico (materials.codigo_material, ej: COM-SES-055). Si la celda trae un hijo suyo (COM-SES-055-3) la receta se anota en su familia, COM-SES-055. El número de la familia es el de la empresa, no relleno: COM-SES-055 y COM-SES-096 son familias distintas. La ficha solo admite genéricos, así que el genérico tiene que existir en la app o venir en la hoja "material" del mismo archivo. Si un material se repite para el mismo producto, gana la última fila.', 'ejemplos' => ['COM-SES-055', 'TEX-LFL-012-3']],
                ['titulo' => 'Material', 'requerida' => false, 'descripcion' => 'Nombre del material (materials.nombre_base). Se usa para ubicar el material solo cuando la celda de "codigo material" quedó vacía; si el nombre coincide con varios materiales, la fila se rechaza.', 'ejemplos' => ['Sesgo de ejemplo', 'Lana flock de ejemplo']],
                ['titulo' => 'consumo por par', 'requerida' => true, 'descripcion' => 'Cuánto del material consume un par. Número mayor que cero; acepta coma o punto decimal (1,2 / 1.2) y se guarda con dos decimales. La celda vacía se toma como 1 y la fila queda reportada en los avisos de la tarjeta.', 'ejemplos' => ['1,20', '0,35']],
                ['titulo' => 'unidad', 'requerida' => false, 'descripcion' => 'Unidad del consumo (metro, unidad…). Sirve de referencia: la ficha conserva la unidad de inventario que ya tiene el material.', 'ejemplos' => ['metro', 'unidad']],
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
     * Hojas del libro MPP, en el mismo orden en que se importan, y la
     * plantilla de la que sale el formato de cada una.
     *
     * @var array<string, string>
     */
    private const HOJAS_MPP = [
        'proveedor' => 'taller',
        'material'  => 'material',
        'producto'  => 'producto',
        'ficha'     => 'ficha_tecnica_materiales',
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
     * Hojas del libro MPP y la clave de plantilla que define sus columnas.
     *
     * @return array<string, string>
     */
    public function hojasMpp(): array
    {
        return self::HOJAS_MPP;
    }

    /**
     * Columnas en el orden exacto en que van en el Excel, indicando cuáles
     * son obligatorias. La tarjeta las muestra en ese mismo orden y conserva
     * el rojo en las requeridas.
     *
     * @return array<int, array{titulo: string, requerida: bool}>
     */
    public function columnasOrdenadas(string $clave): array
    {
        return array_map(
            fn (array $columna): array => [
                'titulo'    => $columna['titulo'],
                'requerida' => (bool) $columna['requerida'],
            ],
            $this->columnas($clave)
        );
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

        $cantEjemplos = $this->escribirHojaDatos($hoja, $plantilla);

        $this->agregarInstrucciones($spreadsheet, [[
            'hoja'      => $plantilla['hoja'],
            'plantilla' => $plantilla,
            'ejemplos'  => $cantEjemplos,
        ]], false);

        // Los importadores leen la hoja activa: debe seguir siendo la de datos,
        // no la de instrucciones.
        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * Construye el libro MPP con una hoja por cada entidad, en el mismo orden
     * en que se importan: proveedor, material, producto y ficha. Cada hoja
     * replica exactamente el formato de su plantilla individual, para que el
     * archivo descargado sirva como ejemplo de cómo va cada hoja.
     */
    public function spreadsheetMpp(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $resumen = [];
        $primera = true;

        foreach (self::HOJAS_MPP as $nombreHoja => $clave) {
            $plantilla = $this->definicion($clave);
            if ($plantilla === null) {
                continue;
            }

            $hoja = $primera ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
            $primera = false;
            $hoja->setTitle($nombreHoja);

            $resumen[] = [
                'hoja'      => $nombreHoja,
                'plantilla' => $plantilla,
                'ejemplos'  => $this->escribirHojaDatos($hoja, $plantilla),
            ];
        }

        $this->agregarInstrucciones($spreadsheet, $resumen, true);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * Escribe encabezados, filas de ejemplo y formato de una hoja de datos.
     * Devuelve la cantidad de filas de ejemplo.
     */
    private function escribirHojaDatos(Worksheet $hoja, array $plantilla): int
    {
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

        return $cantEjemplos;
    }

    /**
     * Agrega la hoja de instrucciones con la descripción y los ejemplos de
     * cada columna. En el libro MPP se incluye además la hoja a la que
     * pertenece cada columna.
     *
     * @param array<int, array{hoja: string, plantilla: array, ejemplos: int}> $hojas
     */
    private function agregarInstrucciones(Spreadsheet $spreadsheet, array $hojas, bool $incluirHoja): void
    {
        $instrucciones = $spreadsheet->createSheet();
        $instrucciones->setTitle('Instrucciones');

        $cantEjemplos = 0;
        foreach ($hojas as $hoja) {
            $cantEjemplos = max($cantEjemplos, $hoja['ejemplos']);
        }

        $cabecera = $incluirHoja
            ? ['Hoja', 'Columna', 'Obligatorio', 'Descripción']
            : ['Columna', 'Obligatorio', 'Descripción'];

        $columnasFijas = count($cabecera);

        $filas = [array_merge(
            $cabecera,
            array_map(fn (int $n): string => 'Ejemplo ' . $n, range(1, max($cantEjemplos, 1)))
        )];

        foreach ($hojas as $hoja) {
            foreach ($hoja['plantilla']['columnas'] as $columna) {
                $fila = [];
                if ($incluirHoja) {
                    $fila[] = $hoja['hoja'];
                }
                $fila[] = $columna['titulo'];
                $fila[] = $columna['requerida'] ? 'Sí' : 'No';
                $fila[] = $columna['descripcion'];

                for ($indice = 0; $indice < $cantEjemplos; $indice++) {
                    $fila[] = $columna['ejemplos'][$indice] ?? '';
                }
                $filas[] = $fila;
            }
        }

        // Si no hay ejemplos, se quita la columna sobrante "Ejemplo 1".
        if ($cantEjemplos === 0) {
            $filas = array_map(fn (array $fila): array => array_slice($fila, 0, $columnasFijas), $filas);
        }

        $instrucciones->fromArray($filas, null, 'A1');

        $totalColumnas = $columnasFijas + $cantEjemplos;
        $ultimaColumnaInstrucciones = Coordinate::stringFromColumnIndex(max($totalColumnas, 1));
        $cabeceraInstrucciones = $instrucciones->getStyle("A1:{$ultimaColumnaInstrucciones}1");
        $cabeceraInstrucciones->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $cabeceraInstrucciones->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('111827');

        foreach (range(1, max($totalColumnas, 1)) as $indice) {
            $instrucciones->getColumnDimension(Coordinate::stringFromColumnIndex($indice))->setAutoSize(true);
        }

        if ($cantEjemplos > 0) {
            $ultimaFila = count($filas) + 2;
            $nota = $incluirHoja
                ? 'Las filas de ejemplo de cada hoja son solo una muestra: bórralas antes de cargar el archivo.'
                : 'Las filas 2 a ' . ($cantEjemplos + 1) . ' de la primera hoja son ejemplos: bórralas antes de cargar el archivo.';
            $instrucciones->fromArray([[$nota]], null, "A{$ultimaFila}");
            $instrucciones->getStyle("A{$ultimaFila}")->getFont()->setItalic(true)->getColor()->setRGB('B45309');
        }
    }

    /**
     * Nombre del archivo .xlsx del libro MPP.
     */
    public function nombreArchivoMpp(): string
    {
        return 'plantilla-mpp.xlsx';
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
