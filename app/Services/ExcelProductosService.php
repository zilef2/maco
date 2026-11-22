<?php

namespace App\Services;

use App\Models\Distribucion_por_tallas;
use App\Models\Producto;
use App\Models\ProductoMovimiento;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Lectura e importación de productos desde las filas de una hoja de Excel.
 *
 * Igual que los materiales, el servicio trabaja sobre filas y no sobre el
 * archivo: la tarjeta de Productos le pasa la hoja activa y la tarjeta MPP le
 * pasa la hoja "producto" del libro.
 *
 * La fila 1 trae los títulos y las siguientes los datos. Un producto se
 * actualiza cuando ya existe con el mismo Codigo_interno y, si no, se crea con
 * las columnas que el archivo no llenó vacías. La excepción es Cantidad: esa
 * columna es el stock del producto, así que reemplaza lo que haya y una celda
 * vacía lo deja en cero.
 */
class ExcelProductosService
{
    /** Columnas que el archivo debe traer sí o sí. */
    private const COLUMNAS_REQUERIDAS = ['Codigo_interno', 'Titulo_Shopify'];

    /**
     * Encabezados aceptados (ya normalizados) y la columna a la que apuntan.
     */
    private const ALIAS_COLUMNAS = [
        'codigointerno'      => 'Codigo_interno',
        'codigo'             => 'Codigo_interno',
        'tituloshopify'      => 'Titulo_Shopify',
        'titulo'             => 'Titulo_Shopify',
        'idproductoshopify'  => 'ID_producto_Shopify',
        'idshopify'          => 'ID_producto_Shopify',
        'categoriaoriginal'  => 'Categoria_original',
        'categoria'          => 'Categoria_original',
        'familianormalizada' => 'Familia_normalizada',
        'familia'            => 'Familia_normalizada',
        'controldevariante'  => 'Control_de_variante',
        'variante'           => 'Control_de_variante',
        'opciones'           => 'Opciones',
        'rango'              => 'Opciones',
        'rangotallas'        => 'Opciones',
        'estado'             => 'Estado',
        'observaciones'      => 'Observaciones',
        'descripcion'        => 'descripcion',
        'preciopredicho'     => 'costo_anticipado',
        'costoanticipado'    => 'costo_anticipado',
        'preciopreventa'     => 'precio_venta_producto',
        'cantidad'           => 'cantidad',
        'existencia'         => 'cantidad',
        'existenciaactual'   => 'cantidad',
        'unidades'           => 'cantidad',
        'stock'              => 'cantidad',
    ];

    /**
     * Resuelve la fila de títulos: qué columna canónica lee cada posición y
     * cuáles no se reconocieron.
     *
     * @param  array<int, mixed>  $filaTitulos
     * @return array{encabezado: array<int, string>, ignoradas: string[]}
     *
     * @throws RuntimeException cuando faltan las columnas obligatorias
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
            if (isset(self::ALIAS_COLUMNAS[$clave])) {
                $encabezado[$indice] = self::ALIAS_COLUMNAS[$clave];
                continue;
            }
            // Encabezado no exacto (ej: "Opciones tallas"): aceptar el alias más
            // largo que sea prefijo, para no ignorar columnas por variantes del
            // nombre.
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
                'El archivo no tiene las columnas requeridas. Faltan: ' . implode(', ', $faltantes)
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
     * @throws RuntimeException cuando el archivo tiene filas sin datos obligatorios
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

        $texto = function ($valor, int $max = 255): string {
            return mb_substr(strip_tags((string) ($valor ?? '')), 0, $max);
        };
        $entero = function ($valor) {
            return is_numeric($valor) ? (int) $valor : null;
        };

        $errores = [];
        $registros = [];

        foreach ($filas as $numFila => $fila) {
            $filaExcel = $numFila + 2; // fila real en el Excel (el encabezado es la 1)

            $registro = [];
            foreach ($encabezado as $indice => $claveCanonica) {
                $valorNuevo = $fila[$indice] ?? null;
                // Varias columnas pueden mapear a la misma clave (ej: "Rango" y "Opciones"):
                // gana la primera con valor; una vacía no pisa a una ya cargada.
                if (!array_key_exists($claveCanonica, $registro)
                    || (($registro[$claveCanonica] === null || $registro[$claveCanonica] === '')
                        && $valorNuevo !== null && $valorNuevo !== '')) {
                    $registro[$claveCanonica] = $valorNuevo;
                }
            }

            // Omitir filas completamente vacías
            if (!array_filter($registro, fn ($v) => $v !== null && $v !== '')) {
                continue;
            }

            $codigo = trim((string) ($registro['Codigo_interno'] ?? ''));
            $titulo = trim((string) ($registro['Titulo_Shopify'] ?? ''));

            if ($codigo === '' || $titulo === '') {
                $errores[] = "Fila {$filaExcel}: faltan datos obligatorios (Codigo interno y Titulo Shopify)";
                continue;
            }

            // Regla del archivo: la columna Cantidad es el stock del producto,
            // así que una celda vacía lo deja en cero, no lo conserva.
            $cantidadTexto = trim(strip_tags((string) ($registro['cantidad'] ?? '')));
            $cantidad = 0;

            if ($cantidadTexto !== '') {
                if (!is_numeric($cantidadTexto) || (float) $cantidadTexto < 0) {
                    $errores[] = "Fila {$filaExcel}: la cantidad debe ser un número mayor o igual que cero"
                        . ' (llegó "' . $cantidadTexto . '").';
                    continue;
                }

                $cantidad = (int) round((float) $cantidadTexto);
            }

            $registros[] = [
                'Codigo_interno'        => $codigo,
                'Titulo_Shopify'        => $titulo,
                'ID_producto_Shopify'   => $texto($registro['ID_producto_Shopify'] ?? ''),
                'Categoria_original'    => $texto($registro['Categoria_original'] ?? ''),
                'Familia_normalizada'   => $texto($registro['Familia_normalizada'] ?? ''),
                'Control_de_variante'   => $texto($registro['Control_de_variante'] ?? ''),
                'Opciones'              => $texto($registro['Opciones'] ?? ''),
                'Estado'                => $texto($registro['Estado'] ?? ''),
                'Observaciones'         => $texto($registro['Observaciones'] ?? ''),
                'descripcion'           => mb_substr(strip_tags((string) ($registro['descripcion'] ?? '')), 0, 5000),
                'costo_anticipado'      => $entero($registro['costo_anticipado'] ?? null),
                'precio_venta_producto' => $entero($registro['precio_venta_producto'] ?? null),
                'cantidad'              => $cantidad,
            ];
        }

        if (!empty($errores)) {
            throw new RuntimeException(
                'No se importó nada por los siguientes problemas: ' . implode(' | ', $errores)
            );
        }
        if (empty($registros)) {
            throw new RuntimeException('El archivo no contiene filas de datos.');
        }

        return ['registros' => $registros, 'ignoradas' => $columnas['ignoradas']];
    }

    /**
     * Inserta los productos nuevos y actualiza los existentes (se empareja por
     * Codigo_interno). La columna Cantidad no es un campo del producto: es su
     * stock, que se escribe en las filas por talla.
     *
     * @param  array<int, array<string, mixed>>  $registros
     * @param  int|null  $usuarioId  En la cola no hay sesión y los movimientos
     *                               de inventario necesitan quién los causó.
     * @return array{creados: int, actualizados: int}
     */
    public function importar(array $registros, ?int $usuarioId = null): array
    {
        $creados = 0;
        $actualizados = 0;

        $existentes = Producto::pluck('id', 'Codigo_interno');

        DB::beginTransaction();
        try {
            foreach ($registros as $registro) {
                $cantidad = (int) ($registro['cantidad'] ?? 0);
                unset($registro['cantidad']);

                if (isset($existentes[$registro['Codigo_interno']])) {
                    $productoId = (int) $existentes[$registro['Codigo_interno']];
                    Producto::where('Codigo_interno', $registro['Codigo_interno'])->update($registro);
                    $actualizados++;
                } else {
                    // Columnas NOT NULL sin default que el archivo puede no traer
                    $registro += [
                        'ID_producto_Shopify'   => '',
                        'Categoria_original'    => '',
                        'Familia_normalizada'   => '',
                        'Control_de_variante'   => '',
                        'Opciones'              => '',
                        'Estado'                => '',
                        'Observaciones'         => '',
                        'descripcion'           => '',
                        'costo_anticipado'      => 0,
                        'precio_venta_producto' => 0,
                    ];
                    $productoId = (int) Producto::create($registro)->id;
                    $creados++;
                }

                $this->reemplazarExistenciaPorTallas($productoId, $cantidad, $usuarioId);
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            throw new RuntimeException('Error al guardar los registros: ' . $e->getMessage(), 0, $e);
        }

        return ['creados' => $creados, 'actualizados' => $actualizados];
    }

    /**
     * Deja el stock del producto en el número que trajo el archivo. Como el
     * archivo trae una sola cantidad por producto y la app la guarda por talla,
     * el total se reparte en partes iguales entre las tallas que el producto ya
     * tiene y el residuo va a la primera.
     *
     * Cada cambio se anota sin orden de producción: una OP reconcilia su
     * recepción contra su propio histórico de movimientos, así que un ajuste del
     * archivo no puede confundirse con lo que esa OP metió.
     */
    private function reemplazarExistenciaPorTallas(int $productoId, int $cantidad, ?int $usuarioId): void
    {
        $producto = Producto::find($productoId);

        if ($producto === null) {
            return;
        }

        $tallas = $producto->distribucionesPorTallas()->orderBy('id')->get();

        if ($tallas->isEmpty()) {
            if ($cantidad === 0) {
                return;
            }

            $nueva = Distribucion_por_tallas::create([
                'grupo' => null,
                'producto_id' => $productoId,
                'talla' => null,
                'cantidad_planificada' => 0,
                'cantidad_recibida_buena' => $cantidad,
            ]);
            $producto->distribucionesPorTallas()->sync([$nueva->id]);

            ProductoMovimiento::registrar(
                $productoId,
                null,
                0,
                $cantidad,
                null,
                'Existencia inicial desde Excel',
                $usuarioId
            );

            return;
        }

        $base = intdiv($cantidad, $tallas->count());
        $residuo = $cantidad % $tallas->count();

        foreach ($tallas->values() as $indice => $distribucion) {
            $nuevaCantidad = $base + ($indice === 0 ? $residuo : 0);
            $existenciaAnterior = (int) ($distribucion->cantidad_recibida_buena ?? 0);

            if ($nuevaCantidad === $existenciaAnterior) {
                continue;
            }

            $distribucion->update(['cantidad_recibida_buena' => $nuevaCantidad]);

            ProductoMovimiento::registrar(
                $productoId,
                $distribucion->talla === null ? null : (string) $distribucion->talla,
                $existenciaAnterior,
                $nuevaCantidad,
                null,
                'Ajuste de inventario desde Excel',
                $usuarioId
            );
        }
    }

    /** Normaliza a minúsculas sin acentos ni espacios sobrantes. */
    private function normalizarTexto(string $texto): string
    {
        return strtr(
            mb_strtolower(trim($texto)),
            ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']
        );
    }
}
