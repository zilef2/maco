<?php

namespace App\Services;

use App\Exceptions\ExcelRechazado;
use App\Models\Material;
use App\Models\Proveedor;
use App\Traits\MaterialMovimientoTrait;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use RuntimeException;

/**
 * Lectura e importación de materiales desde las filas de una hoja de Excel.
 *
 * Trabaja sobre filas (no sobre el archivo) porque el mismo formato se carga
 * desde dos sitios: la tarjeta de Materiales, que usa la hoja activa del
 * archivo, y la tarjeta MPP, que recibe un libro de varias hojas y entrega
 * solo la hoja "material".
 *
 * El rol de la fila lo dice la columna "Base si o no", no el código: cualquier
 * código puede ser del material base de una familia, de un hijo suyo o de un
 * material huérfano (sin base arriba). Lo que sí se conserva es el vínculo: un
 * material es hijo de la base cuyo código es el suyo sin el último tramo.
 *
 * Las filas llegan tal como las devuelve PhpSpreadsheet: la posición 0 son los
 * títulos y las siguientes los datos. Una fila vacía se ignora y cualquier
 * error cancela toda la subida, antes de tocar la base de datos.
 */
class ExcelMaterialesService
{
    use MaterialMovimientoTrait;

    /** Columnas que el archivo debe traer sí o sí. */
    private const COLUMNAS_REQUERIDAS = [
        'codigo_material',
        'nombre_base',
        'generico',
        'estado',
        'procesado',
    ];

    /** Cómo se le llama a cada columna requerida cuando falta alguna. */
    private const TITULOS_REQUERIDOS = [
        'codigo_material' => 'Código material',
        'nombre_base' => 'Nombre base',
        'generico' => 'Base si o no',
        'estado' => 'Estado',
        'procesado' => 'procesado',
    ];

    /** Rótulos de sección del Excel (celdas decorativas): se ignoran en silencio. */
    private const ROTULOS_DE_SECCION = ['opcionales', 'requeridos', 'obligatorios'];

    /** Textos que valen en una columna de sí o no. El vacío se lee como no. */
    private const TEXTOS_BOOLEANOS = [
        '', 'si', 's', 'yes', 'y', 'true', 't', '1', 'verdadero', 'v', 'x',
        'no', 'n', 'not', 'false', 'f', '0', 'falso',
    ];

    /** Cuáles de esos textos significan sí. */
    private const TEXTOS_AFIRMATIVOS = ['si', 's', 'yes', 'y', 'true', 't', '1', 'verdadero', 'v', 'x'];

    /**
     * Encabezados aceptados (ya normalizados) y la columna a la que apuntan.
     */
    private const ALIAS_COLUMNAS = [
        'codigomaterial'          => 'codigo_material',
        'codigo'                  => 'codigo_material',
        'nombrebase'              => 'nombre_base',
        'nombre'                  => 'nombre_base',
        'nombredelmaterial'       => 'nombre_material',
        'nombrematerial'          => 'nombre_material',
        'nombrecomercial'         => 'nombre_material',
        'basesino'                => 'generico',
        'base'                    => 'generico',
        'esbase'                  => 'generico',
        'materialbase'            => 'generico',
        'generico'                => 'generico',
        'codigobase'              => 'codigo_base',
        'categoria'               => 'categoria',
        'codigocategoria'         => 'codigo_categoria',
        'unidadinventario'        => 'unidad_inventario',
        'unidad'                  => 'unidad_inventario',
        'modelodisenoabacado'     => 'modelo_diseno_acabado',
        'modelodisenoacabado'     => 'modelo_diseno_acabado',
        'modelodiseoacabado'      => 'modelo_diseno_acabado',
        'modelodiseno'            => 'modelo_diseno_acabado',
        'modelo'                  => 'modelo_diseno_acabado',
        'color'                   => 'color',
        'tallatamano'             => 'talla_tamano',
        'tallatamao'              => 'talla_tamano',
        'talla'                   => 'talla_tamano',
        'manejacolor'             => 'maneja_color',
        'manejatalla'             => 'maneja_talla',
        'manejaotravariante'      => 'maneja_otra_variante',
        'manejaotrovariante'      => 'maneja_otra_variante',
        'estado'                  => 'estado',
        'varianteatributosugerido'=> 'modelo_diseno_acabado',
        'proveedorhabitual'       => 'proveedor_habitual',
        'proveedor'               => 'proveedor_habitual',
        'nitproveedor'            => 'nit_proveedor',
        'nitproveedorhabitual'    => 'nit_proveedor',
        'nit'                     => 'nit_proveedor',
        'observaciones'           => 'observaciones',
        'todaslasvariantes'       => 'todas_las_variantes',
        'costo'                   => 'precio',
        'costomaterial'           => 'precio',
        'precio'                  => 'precio',
        'ultimaprecio'            => 'precio',
        'cantidad'                => 'existencia_actual',
        'existencia'              => 'existencia_actual',
        'existenciaactual'        => 'existencia_actual',
        'inventario'              => 'existencia_actual',
        'inventariofisico'        => 'existencia_actual',
        'procesado'               => 'procesado',
    ];

    public function __construct(private CostoMaterialOpService $costosMateriales)
    {
    }

    /**
     * Resuelve la fila de títulos: qué columna canónica lee cada posición y
     * cuáles no se reconocieron.
     *
     * @param  array<int, mixed>  $filaTitulos
     * @return array{encabezado: array<int, string>, ignoradas: string[]}
     *
     * @throws RuntimeException cuando faltan columnas o "Procesado" está mal ubicada
     */
    public function validarTitulos(array $filaTitulos): array
    {
        $encabezado = [];
        $ignoradas = [];
        $clavesAlias = array_keys(self::ALIAS_COLUMNAS);
        usort($clavesAlias, fn ($a, $b) => strlen($b) - strlen($a));

        foreach ($filaTitulos as $indice => $titulo) {
            $clave = preg_replace('/[^a-z0-9]/', '', $this->normalizarTexto((string) $titulo));
            if ($clave === '') {
                continue;
            }
            if (in_array($clave, self::ROTULOS_DE_SECCION, true)) {
                continue;
            }
            if (isset(self::ALIAS_COLUMNAS[$clave])) {
                $encabezado[$indice] = self::ALIAS_COLUMNAS[$clave];
                continue;
            }
            // Encabezado no exacto (ej: "Nombre base material"): aceptar el alias
            // más largo que sea prefijo, para no ignorar columnas por variantes.
            $mapeada = null;
            foreach ($clavesAlias as $alias) {
                if (str_starts_with($clave, $alias)) {
                    $mapeada = self::ALIAS_COLUMNAS[$alias];
                    break;
                }
            }
            if ($mapeada !== null) {
                $encabezado[$indice] = $mapeada;
            } else {
                $ignoradas[] = trim((string) $titulo);
            }
        }

        $faltantes = array_diff(self::COLUMNAS_REQUERIDAS, array_values($encabezado));
        if (!empty($faltantes)) {
            throw new RuntimeException(
                'El archivo no tiene las columnas requeridas. Faltan: '
                . implode(', ', array_map(fn (string $campo): string => self::TITULOS_REQUERIDOS[$campo], $faltantes))
            );
        }

        return ['encabezado' => $encabezado, 'ignoradas' => $ignoradas];
    }

    /**
     * Convierte las filas de la hoja en los registros listos para importar.
     *
     * @param  array<int, array<int, mixed>>  $filas
     * @return array{registros: array<int, array<string, mixed>>, ignoradas: string[]}
     *
     * @throws RuntimeException con el resumen de errores del archivo
     */
    public function registrosDesdeFilas(array $filas): array
    {
        $filas = array_values(array_map(
            fn (array $fila): array => array_values($fila),
            $filas
        ));

        if (empty($filas)) {
            throw new RuntimeException('El archivo está vacío.');
        }

        $titulos = array_shift($filas);
        $columnas = $this->validarTitulos($titulos);
        $encabezado = $columnas['encabezado'];

        // La columna del Estado es el ancla con la que se detecta una fila cuyo
        // contenido no cae bajo su rótulo (un bloque pegado desde otro orden de
        // columnas). Se agrupa por Corrimiento para avisar una sola vez.
        $indiceEstado = array_search('estado', $encabezado, true);
        $desfasadas = [];

        // El Excel puede traer el NIT, el nombre o el código del proveedor; la
        // columna guarda el id. Se indexan todas las formas en que la app lo
        // identifica (sin mayúsculas, tildes ni puntuación). El NIT es la forma
        // fiable: "900347285-7" = "9003472857" = "900.347.285-7".
        [$proveedoresPorClave, $proveedoresPorNombre] = $this->indexarProveedores();

        $errores = [];
        $proveedoresDesconocidos = [];
        $existentes = $this->indexarCodigos();
        $registros = [];

        foreach ($filas as $numFila => $fila) {
            $filaExcel = $numFila + 2; // fila real en el Excel (el encabezado es la 1)

            $registro = [];
            foreach ($encabezado as $indice => $claveCanonica) {
                $registro[$claveCanonica] = $fila[$indice] ?? null;
            }

            // Omitir filas completamente vacías
            if (!array_filter($registro, fn ($v) => $v !== null && $v !== '')) {
                continue;
            }

            // Una fila corridas no tiene un valor malo: tiene todos los valores
            // en la columna equivocada. Se avisa del corrimiento y se deja de
            // revisar, para no culpar a "Base si o no" de un nombre de material.
            $paso = $this->desplazamientoDeFila($fila, $indiceEstado);
            if ($paso !== 0) {
                $desfasadas[$paso]['total'] = ($desfasadas[$paso]['total'] ?? 0) + 1;
                $desfasadas[$paso]['ultima'] = $filaExcel;
                $desfasadas[$paso]['primera'] ??= $filaExcel;

                continue;
            }

            $nombre = trim(strip_tags((string) ($registro['nombre_base'] ?? '')));
            $nombreMaterial = trim(strip_tags((string) ($registro['nombre_material'] ?? '')));
            $categoriaTexto = trim(strip_tags((string) ($registro['categoria'] ?? '')));

            if ($nombre === '' && $nombreMaterial !== '') {
                // El archivo siempre trae el nombre de la fila; el nombre base se
                // le puede dejar vacío y se toma el de la fila como base.
                $nombre = $nombreMaterial;
            }

            if ($nombre === '') {
                $errores[] = "Fila {$filaExcel}: falta el Nombre base (o el Nombre del material), que es obligatorio";
                continue;
            }

            // "Base si o no" es lo único que define el rol de la fila: el código
            // ya no lo dice. Base = sí es el material de la familia (sin inventario
            // ni variantes propias); base = no es un hijo de esa base o un material
            // huérfano, y es el que se gasta.
            $baseBruto = trim(strip_tags((string) ($registro['generico'] ?? '')));
            $baseTexto = preg_replace('/[^a-z]/', '', $this->normalizarTexto($baseBruto));

            if (!in_array($baseTexto, self::TEXTOS_BOOLEANOS, true)) {
                $errores[] = "Fila {$filaExcel}: \"Base si o no\" debe ser si o no y llegó \"{$baseBruto}\"";
                continue;
            }

            $esGenerico = in_array($baseTexto, self::TEXTOS_AFIRMATIVOS, true);

            // El código se respeta tal como llega, solo con su forma canónica
            // (mayúsculas y los ceros de sus números). Vacío: el modelo lo forma
            // con el par y el primer número libre.
            $codigoBruto = trim((string) ($registro['codigo_material'] ?? ''));
            $codigo = Material::normalizarCodigo($codigoBruto);

            // El par forma el prefijo del código: primero lo que digita el
            // archivo, luego el propio prefijo del código de la fila y por
            // último la abreviatura del nombre de la categoría y del material.
            $partesCodigo = $codigo !== null ? explode('-', $codigo) : [];
            $categoriaCodigo = Material::normalizarFragmento($registro['codigo_categoria'] ?? null)
                ?: ($partesCodigo[0] ?? '');
            $baseCodigo = Material::normalizarFragmento($registro['codigo_base'] ?? null)
                ?: ($partesCodigo[1] ?? '');
            $prefijo = ($categoriaCodigo ?: Material::abreviar($categoriaTexto))
                . '-' . ($baseCodigo ?: Material::abreviar($nombre));

            // Unidad inventario: en este formato trae el nombre de la unidad (ej: "Metros", "Unidades").
            $unidadInventario = trim(strip_tags((string) ($registro['unidad_inventario'] ?? '')));

            $estadoBruto = (string) ($registro['estado'] ?? '');
            $estadoTexto = $this->normalizarTexto($estadoBruto);
            if (!in_array($estadoTexto, ['activo', 'inactivo'], true)) {
                $errores[] = "Fila {$filaExcel}: Estado debe ser Activo o Inactivo y llegó \"{$estadoBruto}\"";
                continue;
            }

            // Procesado solo admite sí o no; el vacío se lee como no.
            $procesadoBruto = trim(strip_tags((string) ($registro['procesado'] ?? '')));
            $procesadoTexto = preg_replace('/[^a-z]/', '', $this->normalizarTexto($procesadoBruto));
            if (!in_array($procesadoTexto, self::TEXTOS_BOOLEANOS, true)) {
                $errores[] = "Fila {$filaExcel}: Procesado debe ser si o no y llegó \"{$procesadoBruto}\"";
                continue;
            }
            $procesado = in_array($procesadoTexto, self::TEXTOS_AFIRMATIVOS, true) ? 1 : 0;

            $manejaColor = $this->parsearBooleano($registro['maneja_color'] ?? '');
            $manejaTalla = $this->parsearBooleano($registro['maneja_talla'] ?? '');
            $manejaOtraVariante = $this->parsearBooleano($registro['maneja_otra_variante'] ?? '');

            // Columnas "Color" y "Talla / tamaño" alimentan material_variants y
            // pueden llenarse las dos: un material sí admite colores y otras
            // variantes a la vez. La columna legacy "todas_las_variantes" solo se
            // usa cuando la fila no trajo ninguna de las dos, para no registrar
            // lo mismo repetido.
            $rawColor     = $registro['color'] ?? $registro['colores_lista'] ?? null;
            $rawTalla     = $registro['talla_tamano'] ?? $registro['tallas_lista'] ?? null;
            $colorTexto   = is_scalar($rawColor) ? trim(strip_tags((string) $rawColor)) : null;
            $tallaTexto   = is_scalar($rawTalla) ? trim(strip_tags((string) $rawTalla)) : null;

            $colores      = Material::normalizarListaVariantes($rawColor);
            $tallasLista  = Material::normalizarListaVariantes($rawTalla);
            $variantes    = !empty($tallasLista)
                ? $tallasLista
                : (empty($colores) ? Material::normalizarListaVariantes($registro['todas_las_variantes'] ?? null) : []);

            if (!empty($colores)) {
                $manejaColor = true;
            }
            if (!empty($tallasLista)) {
                $manejaTalla = true;
            }
            if (!empty($variantes)) {
                $manejaOtraVariante = true;
            }

            $nitTexto = trim(strip_tags((string) ($registro['nit_proveedor'] ?? '')));
            $proveedorTexto = trim(strip_tags((string) ($registro['proveedor_habitual'] ?? '')));
            $proveedorId = null;
            $claveProveedor = '';

            // Una base no declara proveedor habitual: el que compra es el hijo.
            if (!$esGenerico) {
                foreach ([$nitTexto, $proveedorTexto] as $textoProveedor) {
                    if ($textoProveedor === '') {
                        continue;
                    }
                    $clave = $this->normalizarClave($textoProveedor);
                    $claveProveedor = $claveProveedor !== '' ? $claveProveedor : $clave;
                    $proveedorId = $this->buscarProveedor($clave, $proveedoresPorClave, $proveedoresPorNombre);
                    if ($proveedorId !== null) {
                        $claveProveedor = $clave;
                        break;
                    }
                }
            }

            if ($proveedorId === null && $claveProveedor !== '') {
                $proveedoresDesconocidos[$claveProveedor] ??= ($nitTexto !== '' ? $nitTexto : $proveedorTexto);
            }

            $atributoSugerido = trim(strip_tags((string) ($registro['modelo_diseno_acabado'] ?? $registro['variante_atributo_sugerido'] ?? '')));
            $observaciones = trim(strip_tags((string) ($registro['observaciones'] ?? '')));

            $precioRaw = $esGenerico ? null : ($registro['precio'] ?? null);
            $precio = ($precioRaw !== null && $precioRaw !== '')
                ? (float) str_replace(',', '.', (string) $precioRaw)
                : null;
            if ($precio !== null && $precio < 0) {
                $errores[] = "Fila {$filaExcel}: el precio (columna Costo) no puede ser negativo.";
                continue;
            }

            $cantidadRaw = $esGenerico ? null : ($registro['existencia_actual'] ?? null);
            $cantidad = ($cantidadRaw !== null && $cantidadRaw !== '')
                ? (float) str_replace(',', '.', (string) $cantidadRaw)
                : null;
            if ($cantidad !== null && $cantidad < 0) {
                $errores[] = "Fila {$filaExcel}: el Inventario Fisico no puede ser negativo.";
                continue;
            }

            if ($esGenerico) {
                // La base es el referente de la familia: no almacena existencia,
                // no declara variantes ni proveedor y su precio lo aporta el
                // promedio de sus hijos, así que esas celdas del archivo no se
                // escriben — igual que en el formulario del material. Costo e
                // Inventario Fisico ya se descartaron al leerlos.
                $colorTexto = null;
                $tallaTexto = null;
                $atributoSugerido = '';
                $colores = [];
                $variantes = [];
                $manejaColor = false;
                $manejaTalla = false;
                $manejaOtraVariante = false;
            }

            $fragmentos = explode('-', $prefijo);
            $existe = $codigo !== null && isset($existentes[$codigo]);

            if (!$existe && $codigo === null && !preg_match(Material::PATRON_FRAGMENTOS, $prefijo)) {
                $errores[] = "Fila {$filaExcel}: sin código material hay que formar el prefijo; escriba Código categoría y Código base (o Categoría y Nombre base)";
                continue;
            }

            $datos = [
                'nombre_base'           => $nombre,
                'nombre_material'       => $nombreMaterial !== '' ? mb_substr($nombreMaterial, 0, 255) : null,
                'generico'              => $esGenerico,
                'unidad_inventario'     => $unidadInventario !== '' ? mb_substr($unidadInventario, 0, 255) : 'Unidades',
                'maneja_color'          => $manejaColor,
                'maneja_talla'          => $manejaTalla,
                'maneja_otra_variante'  => $manejaOtraVariante,
                'estado'                => ucfirst($estadoTexto),
                'modelo_diseno_acabado' => $atributoSugerido !== '' ? mb_substr($atributoSugerido, 0, 255) : null,
                'color'                 => ($colorTexto !== null && $colorTexto !== '') ? mb_substr($colorTexto, 0, 255) : null,
                'talla_tamano'          => ($tallaTexto !== null && $tallaTexto !== '') ? mb_substr($tallaTexto, 0, 255) : null,
                'proveedor_habitual'    => $proveedorId,
                'observaciones'         => $observaciones !== '' ? mb_substr($observaciones, 0, 255) : null,
                'material_procesado'    => $procesado,
            ];

            if ($cantidad !== null) {
                $datos['existencia_actual'] = $cantidad;
            }

            // El código manda cuando viene escrito: el modelo respeta el que llega
            // y solo forma el par-numero cuando la celda quedó vacía. Así volver a
            // subir el mismo archivo no duplica nada.
            if ($codigo !== null) {
                $datos['codigo_material'] = $codigo;
            }

            // El par solo se cambia cuando el archivo lo trae escrito: en blanco
            // conserva el del material existente.
            if (!$existe || $categoriaCodigo !== '') {
                $datos['codigo_categoria'] = $existe ? $categoriaCodigo : $fragmentos[0];
            }
            if (!$existe || $baseCodigo !== '') {
                $datos['codigo_base'] = $existe ? $baseCodigo : $fragmentos[1];
            }
            if ($categoriaTexto !== '') {
                $datos['categoria'] = mb_substr($categoriaTexto, 0, 255);
            } elseif (!$existe) {
                $datos['categoria'] = '';
            }

            $registros[] = [
                'datos'     => $datos,
                'codigo'    => $codigo,
                'colores'   => $colores,
                'variantes' => $variantes,
                'precio'    => $precio,
                'cantidad'  => $cantidad,
                'fila'      => $filaExcel,
            ];
        }

        // El desfase es el problema de fondo de esas filas, así que se informa
        // primero y en un solo aviso por cada corrimiento.
        if (!empty($desfasadas)) {
            $errores = array_merge(
                array_map(
                    fn (int $paso): string => $this->avisoDeDesfase($paso, $desfasadas[$paso], $indiceEstado),
                    array_keys($desfasadas)
                ),
                $errores
            );
        }

        // Todo proveedor de la lista debe existir en la app antes de importar.
        if (!empty($proveedoresDesconocidos)) {
            throw new RuntimeException(
                'No se encontró este proveedor, escriba su NIT (ej: 900347285-7), su nombre comercial o su código: '
                . $this->acortarAvisos(array_values($proveedoresDesconocidos), ', ')
            );
        }
        if (!empty($errores)) {
            // El mensaje se recorta para la pantalla y el correo; la lista
            // completa viaja en la excepción y es la que escribe MPP.log.
            throw ExcelRechazado::conErrores(
                'No se importó nada por los siguientes problemas: ' . $this->acortarAvisos($errores),
                $errores
            );
        }
        if (empty($registros)) {
            throw new RuntimeException('El archivo no contiene filas de datos.');
        }

        return ['registros' => $registros, 'ignoradas' => $columnas['ignoradas']];
    }

    /**
     * Cuántas columnas está corrido el contenido de la fila respecto al
     * encabezado (negativo = más a la izquierda), o 0 cuando cae donde debe.
     *
     * Se mira el Estado porque es el único rótulo con valores cerrados: si bajo
     * él no hay Activo ni Inactivo pero sí lo hay a un lado, la fila perdió o
     * ganó una columna y ningún valor coincide ya con su rótulo.
     *
     * @param  array<int, mixed>  $fila
     */
    private function desplazamientoDeFila(array $fila, int|false $indiceEstado): int
    {
        if ($indiceEstado === false || $this->esEstado($fila[$indiceEstado] ?? null)) {
            return 0;
        }

        foreach ([-1, 1, -2, 2] as $paso) {
            if ($this->esEstado($fila[$indiceEstado + $paso] ?? null)) {
                return $paso;
            }
        }

        return 0;
    }

    private function esEstado(mixed $celda): bool
    {
        if (!is_scalar($celda)) {
            return false;
        }

        return in_array($this->normalizarTexto(trim(strip_tags((string) $celda))), ['activo', 'inactivo'], true);
    }

    /**
     * @param  array{primera: int, ultima: int, total: int}  $censo
     */
    private function avisoDeDesfase(int $paso, array $censo, int|false $indiceEstado): string
    {
        $fila = $censo['total'] === 1
            ? "Fila {$censo['primera']}"
            : "Filas {$censo['primera']} a {$censo['ultima']} ({$censo['total']} filas)";

        $donde = $indiceEstado === false
            ? ''
            : ': el rótulo «Estado» está en la columna '
                . Coordinate::stringFromColumnIndex($indiceEstado + 1)
                . ' y el valor llegó en la ' . Coordinate::stringFromColumnIndex($indiceEstado + 1 + $paso);

        return "{$fila}: las celdas van corridas " . abs($paso)
            . ' columna' . (abs($paso) === 1 ? '' : 's') . ' a la '
            . ($paso < 0 ? 'izquierda' : 'derecha') . " respecto al encabezado{$donde}. "
            . 'Suele pasar cuando el bloque viene de un orden anterior de columnas: descargue la plantilla '
            . 'y vuelva a pegar los datos sobre ella, sin mover «Base si o no» de su sitio.';
    }

    /**
     * Inserta los materiales nuevos y actualiza los existentes (se empareja por
     * codigo_material).
     *
     * @param  array<int, array<string, mixed>>  $registros
     * @param  int|null  $usuarioId  El que subió el archivo, para los movimientos
     *                               que se escriben desde la cola (sin sesión).
     * @return array{creados: int, actualizados: int}
     */
    public function importar(array $registros, ?int $usuarioId = null): array
    {
        $creados = 0;
        $actualizados = 0;
        $materialesConPrecioNuevo = [];

        $existentes = $this->indexarCodigos();

        DB::beginTransaction();
        try {
            foreach ($registros as $registro) {
                $fila = $registro['fila'];

                try {
                    $codigoMaterial = $registro['codigo'];
                    $material = $codigoMaterial !== null && isset($existentes[$codigoMaterial])
                        ? Material::find($existentes[$codigoMaterial])
                        : null;

                    if ($material) {
                        $existenciaAnterior = (float) ($material->existencia_actual ?? 0);
                        $material->update($registro['datos']);
                        $actualizados++;
                        if ($registro['cantidad'] !== null) {
                            $this->registrarMovimientoActualizacion(
                                $material,
                                $existenciaAnterior,
                                'Ajuste de inventario desde Excel',
                                $usuarioId
                            );
                        }
                    } else {
                        // El modelo arma el código con el par, así que se registra
                        // tanto el código generado como el que traía la fila.
                        $material = Material::create($registro['datos']);
                        $canonico = Material::normalizarCodigo($material->codigo_material);
                        $existentes[$material->codigo_material] ??= $material->id;
                        if ($canonico !== null) {
                            $existentes[$canonico] ??= $material->id;
                        }
                        if ($codigoMaterial !== null) {
                            $existentes[$codigoMaterial] ??= $material->id;
                        }
                        $creados++;
                        if ($registro['cantidad'] !== null) {
                            $this->registrarMovimientoCreacion($material, 'Existencia inicial desde Excel', $usuarioId);
                        }
                    }

                    // La columna Costo de este archivo es el Precio de la app:
                    // se anota en el histórico de precios, no en el material.
                    if ($registro['precio'] !== null) {
                        $this->registrarPrecioImportado($material, $registro['precio']);
                        $materialesConPrecioNuevo[] = (int) $material->id;
                    }

                    // Lista vacía borra las variantes anteriores; los indicadores
                    // maneja_color/maneja_otra_variante se recalculan dentro.
                    $material->sincronizarVariantes($registro['colores'], $registro['variantes']);
                } catch (\Throwable $e) {
                    // El aviso tiene que decir en qué fila del Excel nació el
                    // problema; si no, el usuario no sabe qué corregir. Las
                    // validaciones del modelo no traen mensaje útil en
                    // getMessage(), hay que leer su lista de errores.
                    $mensaje = $e instanceof \Illuminate\Validation\ValidationException
                        ? (string) collect($e->errors())->flatten()->first()
                        : $e->getMessage();

                    throw new \RuntimeException("Fila {$fila}: " . $mensaje, 0, $e);
                }
            }

            // Los precios nuevos también mueven el costo de las OPs abiertas
            // que usan esos materiales.
            $this->costosMateriales->recalcularPorMateriales(array_values(array_unique($materialesConPrecioNuevo)));

            DB::commit();
            $this->invalidarCacheListados();
        } catch (\Throwable $e) {
            DB::rollBack();

            throw new RuntimeException('Error al guardar los registros: ' . $e->getMessage(), 0, $e);
        }

        return ['creados' => $creados, 'actualizados' => $actualizados];
    }

    /**
     * Versión del cache del listado: al incrementarse, todas las claves
     * anteriores dejan de usarse y se reconstruyen con datos frescos.
     */
    public function versionCache(): int
    {
        Cache::add('material_index_version', 1, now()->addDays(7));

        return (int) Cache::get('material_index_version', 1);
    }

    public function invalidarCacheListados(): void
    {
        Cache::increment('material_index_version');
        Cache::increment('op_index_version');
        Cache::forget('op_selects');
    }

    /**
     * La columna Costo del archivo es el precio del material: se guarda en el
     * histórico (material_prices), que es de donde la app lee el Precio. Con la
     * fecha de hoy, igual que el formulario: si el material ya tiene un precio
     * anotado hoy, se reemplaza en vez de duplicar la fila.
     */
    private function registrarPrecioImportado(Material $material, float $precio): void
    {
        $fecha = now()->toDateString();
        $precioHoy = $material->prices()->whereDate('fecha', $fecha)->first();

        if ($precioHoy) {
            $precioHoy->update(['precio' => $precio]);
            return;
        }

        $material->prices()->create([
            'precio' => $precio,
            'fecha' => $fecha,
        ]);
    }

    /**
     * Id de cada material indexado por su código canónico y por el que tiene
     * escrito: el archivo puede llegar con otros ceros (COM-CAP-55-4) y seguir
     * señalando al mismo material (COM-CAP-055-04), así que volver a subirlo no
     * crea un duplicado.
     *
     * @return array<string, int>
     */
    private function indexarCodigos(): array
    {
        $indice = [];

        foreach (Material::query()->get(['id', 'codigo_material']) as $material) {
            $canonico = Material::normalizarCodigo($material->codigo_material);

            foreach (array_filter([$material->codigo_material, $canonico]) as $clave) {
                $indice[$clave] ??= (int) $material->id;
            }
        }

        return $indice;
    }

    /**
     * Todas las formas en que la app identifica a un proveedor (NIT, nombre
     * comercial, razón social, nombre y códigos) apuntando a su id.
     *
     * @return array{0: array<string, int>, 1: array<string, int>}
     */
    private function indexarProveedores(): array
    {
        $porClave = [];
        $porNombre = [];

        $query = Proveedor::query()->get(['id', 'codigo', 'nombre_comercial', 'razon_social', 'nombre', 'nit']);

        foreach ($query as $proveedor) {
            $identificadores = [
                $proveedor->nombre_comercial,
                $proveedor->razon_social,
                $proveedor->nombre,
                $proveedor->codigo,
                $proveedor->nit,
                // Código visual que usa el listado cuando no hay código.
                'PRV-' . $proveedor->id,
                'PROV-' . $proveedor->id,
                (string) $proveedor->id,
            ];

            foreach ($identificadores as $identificador) {
                $clave = $this->normalizarClave($identificador);
                if ($clave !== '' && !isset($porClave[$clave])) {
                    $porClave[$clave] = (int) $proveedor->id;
                }
            }

            $nombres = [$proveedor->nombre_comercial, $proveedor->razon_social, $proveedor->nombre];

            foreach ($nombres as $nombre) {
                // "LA CASA DE LOS CORDONES" admite "Casa de los cordones": el
                // artículo inicial no es parte del nombre con que se le escribe.
                $sinArticulo = preg_replace('/^(la|el|los|las|un|una)\s+/u', '', $this->normalizarTexto((string) $nombre));
                foreach ([$nombre, $sinArticulo] as $variante) {
                    $clave = $this->normalizarClave($variante);
                    if ($clave === '') {
                        continue;
                    }
                    $porClave[$clave] ??= (int) $proveedor->id;
                    $porNombre[$clave] = (int) $proveedor->id;
                }
            }
        }

        return [$porClave, $porNombre];
    }

    /**
     * Un aviso de importación no puede ocupar toda la pantalla: se muestran
     * como máximo 7 y el resto se resume.
     *
     * @param array<int, string> $avisos
     */
    private function acortarAvisos(array $avisos, string $separador = ' | ', int $maximo = 7): string
    {
        $mostrados = array_slice(array_values($avisos), 0, $maximo);
        $restantes = count($avisos) - count($mostrados);

        if ($restantes > 0) {
            $mostrados[] = "y {$restantes} más";
        }

        return implode($separador, $mostrados);
    }

    /**
     * Id del proveedor que corresponde a lo que escribió el Excel: NIT, nombre
     * comercial, razón social o código, ya normalizados. Si nada coincide de
     * forma exacta, se acepta el único proveedor cuyo nombre contenga lo
     * escrito (o al revés), así «Casa de los cordones» sirve para registrar
     * «LA CASA DE LOS CORDONES». Con dos candidatos se prefiere avisar antes
     * que asignar el proveedor equivocado.
     *
     * @param array<string, int> $porClave
     * @param array<string, int> $porNombre
     */
    private function buscarProveedor(string $clave, array $porClave, array $porNombre): ?int
    {
        if ($clave === '') {
            return null;
        }
        if (isset($porClave[$clave])) {
            return $porClave[$clave];
        }
        if (mb_strlen($clave) < 5) {
            return null;
        }

        $candidatos = [];

        foreach ($porNombre as $claveProveedor => $idProveedor) {
            if (str_contains($claveProveedor, $clave) || str_contains($clave, $claveProveedor)) {
                $candidatos[$idProveedor] = true;
            }
        }

        return count($candidatos) === 1 ? (int) array_key_first($candidatos) : null;
    }

    /** Normaliza a minúsculas sin acentos ni espacios sobrantes. */
    private function normalizarTexto(string $texto): string
    {
        return strtr(
            mb_strtolower(trim($texto)),
            ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']
        );
    }

    /** Clave de comparación: sin mayúsculas, tildes ni puntuación. */
    private function normalizarClave(?string $texto): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '', $this->normalizarTexto((string) $texto));
    }

    private function parsearBooleano(mixed $valor): bool
    {
        $clave = preg_replace('/[^a-z]/', '', mb_strtolower(trim((string) $valor)));

        return in_array($clave, self::TEXTOS_AFIRMATIVOS, true);
    }
}
