<?php

namespace App\Services;

use App\Models\FichaTecnica;
use App\Models\Material;
use App\Models\Producto;
use App\Services\Concerns\LeeExcelRapido;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Carga masiva de la ficha de producto desde el archivo "Ficha producto.xlsx".
 *
 * Cada fila de la hoja es un material de la receta de un producto: "Codigo
 * producto" es productos.Codigo_interno, "codigo material" es
 * materials.codigo_material y "consumo por par" el consumo estándar. Las filas
 * que comparten código de producto forman una sola ficha; las columnas
 * "Producto" y "Material" se usan para buscar el registro cuando la celda del
 * código quedó vacía, y la "unidad" queda como referencia porque la ficha
 * conserva la unidad del material.
 *
 * Las reglas aparte del código (el consecutivo no importa, la receta apunta al
 * genérico del par y el consumo vacío vale 1) viven en
 * {@see specialfunctions_fichaproducto}.
 *
 * Este archivo no crea productos ni materiales. `validar()` se corre en la
 * misma petición de subida y rechaza el archivo completo con la lista de los
 * códigos que no existen, para que el usuario no espere un job que no iba a
 * insertar nada. Lo que sí pasa pasa por `FichaTecnicaService::guardar()`, el
 * mismo camino del formulario: conserva la unidad y el costo del material,
 * respeta los bloqueos de OP y recalcula el costo de las OPs abiertas.
 */
class ExcelFichasProductoService
{
    use LeeExcelRapido;

    /** Columnas sin las cuales el archivo no se puede interpretar. */
    private const COLUMNAS_REQUERIDAS = ['codigo_producto', 'codigo_material', 'consumo'];

    /**
     * Títulos aceptados, ya en la clave que produce `clave()` (sin espacios,
     * tildes ni minúsculas), y su campo canónico. Los títulos que no están aquí
     * se ignoran. Las columnas de nombres son el plan B de las de códigos:
     * solo se usan cuando la celda del código de la fila quedó vacía.
     */
    private const ALIAS_COLUMNAS = [
        'CODIGOPRODUCTO'        => 'codigo_producto',
        'CODIGODELPRODUCTO'     => 'codigo_producto',
        'CODIGOINTERNO'         => 'codigo_producto',
        'CODIGOPRODUCTOSHOPIFY' => 'codigo_producto',
        'REFERENCIA'            => 'codigo_producto',
        'CODIGOMATERIAL'        => 'codigo_material',
        'CODIGODELMATERIAL'     => 'codigo_material',
        'CONSUMOPORPAR'         => 'consumo',
        'CONSUMOPORUNIDAD'      => 'consumo',
        'CONSUMO'               => 'consumo',
        'CANTIDAD'              => 'consumo',
        'UNIDAD'                => 'unidad',
        'UNIDADMEDIDA'          => 'unidad',
        'UNIDADINVENTARIO'      => 'unidad',
        'PRODUCTO'              => 'nombre_producto',
        'MATERIAL'              => 'nombre_material',
    ];

    /** Cuántos códigos y cuántas filas se muestran en un aviso. */
    private const MAXIMO_EN_AVISO = 10;

    public function __construct(
        private ExcelSecurityScanner $scanner,
        private FichaTecnicaService $fichas,
        private specialfunctions_fichaproducto $especiales,
    ) {
    }

    /**
     * @return string[] Problemas del contenedor (vacío si el archivo es seguro)
     */
    public function escanearSeguridad(string $ruta): array
    {
        return $this->scanner->escanear($ruta);
    }

    /**
     * Revisa el archivo entero antes de encolar la carga: el formato, que cada
     * producto y cada material del archivo tengan su genérico en la app, que los
     * consumos sean números y que ninguna ficha esté bloqueada por una OP activa.
     *
     * @return array{filas: int, productos: int, fichas: int, materiales: int, avisos: string[]}
     *
     * @throws RuntimeException con todo lo que hay que corregir, junto
     */
    public function validar(string $ruta): array
    {
        $lectura = $this->gruposDesdeArchivo($ruta);
        $destinos = $this->agruparPorFicha($lectura['grupos']);

        $bloqueos = $this->fichas->bloqueosPorProducto();
        $bloqueados = [];

        foreach ($destinos as $destino) {
            // Los productos con los que la ficha ya comparte receta también
            // bloquean: guardar() los revisa y el aviso debe llegar aquí.
            foreach ($destino['producto_ids'] as $productoId) {
                if (empty($bloqueos[$productoId])) {
                    continue;
                }

                $bloqueados[$productoId] = collect($bloqueos[$productoId])
                    ->map(fn (string $estado, string $op): string => $op . ' (' . $estado . ')')
                    ->implode(', ');
            }
        }

        if (!empty($bloqueados)) {
            $codigos = Producto::whereIn('id', array_keys($bloqueados))->pluck('Codigo_interno', 'id');

            $lista = [];
            foreach ($bloqueados as $productoId => $detalle) {
                $lista[] = ($codigos[$productoId] ?? 'producto #' . $productoId) . ' — OP ' . $detalle;
            }

            throw new RuntimeException(
                'Estos productos tienen una OP que no permite tocar su ficha: '
                . $this->lista($lista)
                . '. La ficha solo se puede cargar con la OP en Borrador, Preparación o Cerrada.'
            );
        }

        $materiales = [];
        $filas = 0;
        $productos = 0;

        foreach ($destinos as $destino) {
            $filas += count($destino['materiales']);
            $productos += count($destino['codigos']);
            $materiales += array_keys($destino['materiales']);
        }

        return [
            'filas' => $filas,
            'productos' => $productos,
            'fichas' => count($destinos),
            'materiales' => count($materiales),
            'avisos' => $this->avisosDeLectura($lectura['consumos_vacios']),
        ];
    }

    /**
     * Crea la ficha de cada producto del archivo o reemplaza los materiales de
     * la que ya tenga. Una ficha puede cubrir varios productos (variantes): el
     * archivo aporta uno y el resto se conserva.
     *
     * @return array{creados: int, actualizados: int, filas: int, avisos: string[]}
     */
    public function importar(string $ruta): array
    {
        $lectura = $this->gruposDesdeArchivo($ruta);
        $destinos = $this->agruparPorFicha($lectura['grupos']);

        $creados = 0;
        $actualizados = 0;
        $filas = 0;

        DB::transaction(function () use ($destinos, &$creados, &$actualizados, &$filas): void {
            foreach ($destinos as $destino) {
                $ficha = $destino['ficha_id'] === null
                    ? null
                    : FichaTecnica::find($destino['ficha_id']);

                try {
                    $this->fichas->guardar([
                        'nombre' => $ficha?->nombre ?: 'Ficha ' . $destino['codigos'][0],
                        'productos' => $destino['producto_ids'],
                        'materiales' => array_values($destino['materiales']),
                    ], $ficha);
                } catch (ValidationException $e) {
                    // El mensaje de validación viene por campos; aquí solo
                    // interesa lo que tiene que leer el usuario en el correo.
                    throw new RuntimeException(
                        'No se pudo guardar la ficha de ' . implode(', ', $destino['codigos']) . ': '
                        . implode(' | ', array_map(
                            fn (array $mensajes): string => implode(', ', $mensajes),
                            $e->errors() ?: ['' => [$e->getMessage()]]
                        ))
                    );
                }

                $filas += count($destino['materiales']);
                $ficha ? $actualizados++ : $creados++;
            }
        });

        // Las fichas mueven el costo de las OPs abiertas y el de los materiales.
        Cache::increment('material_index_version');
        Cache::increment('op_index_version');
        Cache::forget('op_selects');

        return [
            'creados' => $creados,
            'actualizados' => $actualizados,
            'filas' => $filas,
            'avisos' => $this->avisosDeLectura($lectura['consumos_vacios']),
        ];
    }

    /**
     * Una entrada por ficha que hay que escribir. Dos códigos del archivo que
     * en la app comparten ficha se unen: si no, la última receta reemplazaría
     * a la de sus hermanos y medio archivo se perdería sin avisar.
     *
     * @param  array<int, array{codigo_producto: string, producto_id: int, materiales: array<int, array<string, mixed>>}>  $grupos
     * @return array<string, array{ficha_id: int|null, codigos: string[], producto_ids: int[], materiales: array<int, array<string, mixed>>}>
     */
    private function agruparPorFicha(array $grupos): array
    {
        $destinos = [];

        foreach ($grupos as $grupo) {
            $ficha = FichaTecnica::query()
                ->whereHas('productos', fn ($q) => $q->where('producto_id', $grupo['producto_id']))
                ->first();

            $clave = $ficha ? 'ficha-' . $ficha->id : 'producto-' . $grupo['producto_id'];

            $destinos[$clave] ??= [
                'ficha_id' => $ficha?->id,
                'codigos' => [],
                'producto_ids' => [],
                'materiales' => [],
            ];

            $hermanos = $ficha
                ? $ficha->productos()->pluck('productos.id')->map(fn ($id) => (int) $id)->all()
                : [];

            $destinos[$clave]['codigos'][] = $grupo['codigo_producto'];
            $destinos[$clave]['producto_ids'] = array_values(array_unique(array_merge(
                $destinos[$clave]['producto_ids'],
                $hermanos,
                [$grupo['producto_id']],
            )));

            // Mismo material en dos filas de la misma ficha: gana la última.
            foreach ($grupo['materiales'] as $materialId => $fila) {
                $destinos[$clave]['materiales'][$materialId] = $fila;
            }
        }

        return $destinos;
    }

    /**
     * Lee la hoja y devuelve una entrada por producto con sus materiales, más
     * las filas que llegaron sin consumo y se tomaron como 1.
     *
     * @return array{grupos: array<int, array{codigo_producto: string, producto_id: int, materiales: array<int, array<string, mixed>>}>, consumos_vacios: int[]}
     */
    private function gruposDesdeArchivo(string $ruta): array
    {
        $filas = $this->leerTodasLasFilas($ruta);
        if (empty($filas)) {
            throw new RuntimeException('El archivo está vacío.');
        }

        $encabezado = $this->columnasDesdeTitulos($filas[0] ?? []);
        $productos = $this->indexar(Producto::pluck('id', 'Codigo_interno')->all(), false);
        $materiales = $this->indexar(Material::pluck('id', 'codigo_material')->all(), true);

        $avisos = [];
        $sinProducto = [];
        $sinMaterial = [];
        $grupos = [];
        $consumosVacios = [];
        $codigosPorProducto = [];

        foreach (array_slice($filas, 1) as $posicion => $fila) {
            $numeroFila = $posicion + 2; // La fila 1 son los títulos.
            $registro = $this->registroDesdeFila($encabezado, array_values($fila));

            $codigoProducto = $registro['codigo_producto'];
            $codigoMaterial = $registro['codigo_material'];

            if ($codigoProducto === '' && $codigoMaterial === ''
                && $registro['nombre_producto'] === '' && $registro['nombre_material'] === '') {
                continue;
            }

            // Regla 3: con la celda del código vacía, el nombre busca el registro.
            if ($codigoProducto === '' && $registro['nombre_producto'] === '') {
                $avisos[] = "Fila {$numeroFila}: falta el código y el nombre de producto.";
                continue;
            }

            if ($codigoMaterial === '' && $registro['nombre_material'] === '') {
                $avisos[] = "Fila {$numeroFila}: falta el código y el nombre de material.";
                continue;
            }

            $consumo = $this->parsearConsumo($registro['consumo']);

            if ($consumo === null) {
                if ($registro['consumo'] !== '') {
                    $avisos[] = "Fila {$numeroFila}: el consumo por par debe ser un número mayor que cero"
                        . ' (llegó "' . $registro['consumo'] . '").';
                    continue;
                }

                // Regla 4: vacío vale 1 y la fila queda reportada.
                $consumo = specialfunctions_fichaproducto::CONSUMO_VACIO;
                $consumosVacios[] = $numeroFila;
            }

            if ($codigoProducto !== '') {
                $productoId = $this->buscar($productos, $codigoProducto, false);

                if ($productoId === null) {
                    $sinProducto[$codigoProducto] = true;
                    continue;
                }
            } else {
                $resuelto = $this->especiales->productoDesdeNombre($registro['nombre_producto']);
                $productoId = $resuelto['producto_id'];

                if ($productoId === null) {
                    $sinProducto['"' . $registro['nombre_producto'] . '" ('
                        . specialfunctions_fichaproducto::textoMotivo((string) $resuelto['motivo']) . ')'] = true;
                    continue;
                }

                // El nombre no es el código: la ficha se sigue llamando por él.
                $codigosPorProducto[$productoId] ??= (string) Producto::query()
                    ->whereKey($productoId)
                    ->value('Codigo_interno');
                $codigoProducto = $codigosPorProducto[$productoId] !== ''
                    ? $codigosPorProducto[$productoId]
                    : $registro['nombre_producto'];
            }

            $material = $this->resolverMaterial($codigoMaterial, $registro['nombre_material'], $materiales);

            if ($material['material_id'] === null) {
                $sinMaterial[($codigoMaterial !== '' ? $codigoMaterial : '"' . $registro['nombre_material'] . '"')
                    . ' (' . specialfunctions_fichaproducto::textoMotivo((string) $material['motivo']) . ')'] = true;
                continue;
            }

            $materialId = $material['material_id'];

            // Un material repetido para el mismo producto no duplica la línea:
            // gana la última fila, que es la corrección más reciente.
            $grupos[$productoId] ??= [
                'codigo_producto' => $codigoProducto,
                'producto_id' => $productoId,
                'materiales' => [],
            ];
            $grupos[$productoId]['materiales'][$materialId] = [
                'material_componente_id' => $materialId,
                'consumo_estandar' => $consumo,
                'unidad_medida' => $registro['unidad'] !== '' ? $registro['unidad'] : null,
            ];
        }

        if (!empty($sinProducto)) {
            $avisos[] = 'No se pudo ubicar el producto de estas celdas: ' . $this->lista(array_keys($sinProducto))
                . '. El código debe existir en la app (cárguelo con la tarjeta de Productos) y el nombre solo sirve cuando la celda del código quedó vacía.';
        }

        if (!empty($sinMaterial)) {
            $avisos[] = 'No se pudo ubicar el material de estas celdas: ' . $this->lista(array_keys($sinMaterial))
                . '. El número del final no importa (ACC-APL-7 vale como ACC-APL-0), pero la receta se escribe sobre el material genérico del par, así que el par tiene que existir y tener su CAT-BAS-0; si le falta, crélo con el botón «Materiales genéricos» de Actividad.';
        }

        if (!empty($avisos)) {
            throw new RuntimeException(implode(' | ', array_slice($avisos, 0, 8))
                . (count($avisos) > 8 ? ' | y ' . (count($avisos) - 8) . ' avisos más.' : ''));
        }

        if (empty($grupos)) {
            throw new RuntimeException('El archivo no trae ninguna fila de receta debajo de los títulos.');
        }

        return [
            'grupos' => array_values($grupos),
            'consumos_vacios' => $consumosVacios,
        ];
    }

    /**
     * Material de la fila (reglas 1, 2 y 3): el genérico del par del código, con
     * el nombre solo si la celda del código está vacía. El consecutivo se busca
     * sin ceros a la izquierda (COM-SES-039 vale como COM-SES-39) y el material
     * tiene que ser genérico: la ficha técnica no admite específicos.
     *
     * @param  array<string, int>  $materiales
     * @return array{material_id: int|null, motivo: string|null}
     */
    private function resolverMaterial(string $codigo, string $nombre, array $materiales): array
    {
        if ($codigo === '') {
            return $this->especiales->materialDesdeNombre($nombre);
        }

        // El Excel suele traer el consecutivo con ceros (COM-SES-039) y en la app
        // el código vive sin ellas (COM-SES-39): se busca por la forma canónica.
        $canonico = Material::normalizarCodigo($codigo) ?? $codigo;
        $resuelto = $this->especiales->materialDesdeCodigo($canonico);

        if ($resuelto['material_id'] !== null) {
            return $resuelto;
        }

        // Aunque el par no tenga genérico, el código puede apuntar directo a uno
        // que no sigue el formato CAT-BAS-0.
        $exacto = $this->buscar($materiales, $canonico, true);

        if ($exacto === null) {
            return $resuelto['motivo'] === specialfunctions_fichaproducto::SIN_FORMATO
                ? ['material_id' => null, 'motivo' => specialfunctions_fichaproducto::INEXISTENTE]
                : $resuelto;
        }

        return $this->especiales->esGenerico($exacto)
            ? ['material_id' => $exacto, 'motivo' => null]
            : ['material_id' => null, 'motivo' => specialfunctions_fichaproducto::SIN_GENERICO];
    }

    /**
     * Avisos que se le muestran al usuario sin cancelar la carga. Hoy solo hay
     * uno: las filas que llegaron sin consumo.
     *
     * @param  int[]  $consumosVacios
     * @return string[]
     */
    private function avisosDeLectura(array $consumosVacios): array
    {
        $reporte = specialfunctions_fichaproducto::avisoFilasSinConsumo($consumosVacios);

        return $reporte === null ? [] : [$reporte];
    }

    /**
     * Índice clave => id del modelo. Si dos registros comparten clave, gana el
     * primero.
     *
     * @param  array<int|string, int>  $codigoPorId
     * @return array<string, int>
     */
    private function indexar(array $codigoPorId, bool $esMaterial): array
    {
        $indice = [];

        foreach ($codigoPorId as $codigo => $id) {
            foreach ($this->claves((string) $codigo, $esMaterial) as $clave) {
                $indice[$clave] ??= (int) $id;
            }
        }

        return $indice;
    }

    /**
     * @param  array<string, int>  $indice
     */
    private function buscar(array $indice, string $codigo, bool $esMaterial): ?int
    {
        foreach ($this->claves($codigo, $esMaterial) as $clave) {
            if (isset($indice[$clave])) {
                return $indice[$clave];
            }
        }

        return null;
    }

    /**
     * Claves con las que se puede encontrar un código. Los materiales aceptan
     * además su forma canónica: el Excel suele traer el consecutivo con ceros
     * (COM-SES-039) y en la app se guarda sin ellas (COM-SES-39).
     *
     * @return string[]
     */
    private function claves(string $codigo, bool $esMaterial): array
    {
        $claves = [$this->clave($codigo)];

        if ($esMaterial) {
            $normalizado = Material::normalizarCodigo($codigo);

            if ($normalizado !== null) {
                $claves[] = $this->clave($normalizado);
            }
        }

        return array_values(array_filter(array_unique($claves)));
    }

    /**
     * Resuelve la fila 1: posición de columna => campo canónico.
     *
     * @param  array<int, mixed>  $filaTitulos
     * @return array<int, string>
     */
    private function columnasDesdeTitulos(array $filaTitulos): array
    {
        $alias = array_keys(self::ALIAS_COLUMNAS);
        usort($alias, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        $porIndice = [];

        foreach (array_values($filaTitulos) as $indice => $titulo) {
            $clave = $this->clave((string) $titulo);
            if ($clave === '') {
                continue;
            }

            foreach ($alias as $posible) {
                if ($clave === $posible || str_starts_with($clave, $posible)) {
                    $porIndice[$indice] = self::ALIAS_COLUMNAS[$posible];
                    break;
                }
            }
        }

        $faltantes = array_diff(self::COLUMNAS_REQUERIDAS, array_values($porIndice));

        if (!empty($faltantes)) {
            $leible = [
                'codigo_producto' => 'Codigo producto',
                'codigo_material' => 'codigo material',
                'consumo' => 'consumo por par',
            ];

            throw new RuntimeException(
                'La fila 1 del archivo debe traer las columnas: '
                . implode(', ', array_map(fn (string $campo): string => $leible[$campo], self::COLUMNAS_REQUERIDAS))
                . '. Faltan: ' . implode(', ', array_map(fn (string $campo): string => $leible[$campo], $faltantes)) . '.'
            );
        }

        return $porIndice;
    }

    /**
     * Fila => valores de los campos que interesan, en texto recortado.
     *
     * @param  array<int, string>  $encabezado
     * @param  array<int, mixed>   $fila
     * @return array<string, string>
     */
    private function registroDesdeFila(array $encabezado, array $fila): array
    {
        $registro = [
            'codigo_producto' => '',
            'codigo_material' => '',
            'nombre_producto' => '',
            'nombre_material' => '',
            'consumo' => '',
            'unidad' => '',
        ];

        foreach ($encabezado as $indice => $campo) {
            $valor = trim((string) ($fila[$indice] ?? ''));

            // Una columna repetida no borra la primera: la ficha se arma con la
            // primera celda que traiga datos.
            if ($registro[$campo] === '') {
                $registro[$campo] = $valor;
            }
        }

        return $registro;
    }

    /**
     * Consumo "1.2", "1,2" o "1.234,5" en número con dos decimales (la columna
     * de la ficha es decimal). Devuelve null si no es un número usable.
     */
    private function parsearConsumo(string $valor): ?float
    {
        $texto = str_replace([' ', "\xc2\xa0"], '', trim($valor));

        // "1.234,5": los puntos son de miles y la coma es el decimal.
        if (preg_match('/^\d{1,3}(?:\.\d{3})+,\d+$/', $texto) === 1) {
            $texto = str_replace('.', '', $texto);
        }

        if (preg_match('/^\d{1,12}([.,]\d{1,6})?$/', $texto) !== 1) {
            return null;
        }

        $numero = (float) str_replace(',', '.', $texto);

        return $numero > 0 ? round($numero, 2) : null;
    }

    /** Clave de comparación: sin mayúsculas, tildes, espacios ni guiones. */
    private function clave(?string $texto): string
    {
        return (string) preg_replace(
            '/[^A-Z0-9]/',
            '',
            mb_strtoupper(Str::ascii(trim((string) $texto)), 'UTF-8')
        );
    }

    /**
     * @param  string[]  $codigos
     */
    private function lista(array $codigos): string
    {
        $mostrados = array_slice($codigos, 0, self::MAXIMO_EN_AVISO);

        return implode(', ', $mostrados)
            . (count($codigos) > count($mostrados)
                ? ' y ' . (count($codigos) - count($mostrados)) . ' más'
                : '');
    }
}
