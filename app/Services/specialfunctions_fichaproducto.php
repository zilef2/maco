<?php

namespace App\Services;

use App\Models\Material;
use App\Models\Producto;
use Illuminate\Support\Str;

/**
 * Reglas aparte de la carga masiva de la ficha de producto.
 *
 * 1. El código de la fila dice la familia del material: CAT-BAS-X es el genérico
 *    de la familia (X es el número de la empresa) y CAT-BAS-X-Y es uno de sus
 *    hijos, que pertenece a la familia CAT-BAS-X. El número de familia no es
 *    decorativo: ACC-APL-7 y ACC-APL-9 son familias distintas.
 * 2. Al procesar, la celda se reduce a su familia y la receta se escribe sobre
 *    el genérico de esa familia, que es el único material que admite la ficha
 *    técnica. Ningún tramo del código puede valer cero.
 * 3. Los nombres de producto y de material buscan el registro parecido, pero
 *    solo cuando la celda del código llegó vacía.
 * 4. El "consumo por par" vacío vale 1 y cada fila que pasó por ahí se reporta
 *    con su número de fila.
 *
 * Los índices se leen una sola vez por importación: el archivo puede traer
 * cientos de filas y cada una necesita una consulta de material y de producto.
 */
class specialfunctions_fichaproducto
{
    /** Consumo que se toma cuando la celda "consumo por par" viene vacía. */
    public const CONSUMO_VACIO = 1.0;

    /** Motivos por los que una celda no se pudo resolver. */
    public const SIN_FORMATO = 'sin_formato';
    public const INEXISTENTE = 'inexistente';
    public const SIN_GENERICO = 'sin_generico';
    public const AMBIGUO = 'ambiguo';

    /** @var array<string, int> código de familia => id de su material genérico */
    private array $genericosPorFamilia = [];

    /** @var array<string, bool> código de familia => existe algún material de esa familia */
    private array $familiasConocidas = [];

    /** @var array<int, bool> ids de materiales genéricos */
    private array $materialesGenericos = [];

    /** @var array<int, array{clave: string, id: int, familia: string|null}> */
    private array $materialesPorClave = [];

    /** @var array<int, array{clave: string, id: int}> */
    private array $productosPorClave = [];

    private bool $cargado = false;

    /**
     * Genérico de la familia del código de una fila (reglas 1 y 2): si la celda
     * trae un hijo (CAT-BAS-X-Y) se usa su familia (CAT-BAS-X).
     *
     * @return array{material_id: int|null, motivo: string|null}
     */
    public function materialDesdeCodigo(string $codigo): array
    {
        $this->cargar();

        $familia = self::familiaDe($codigo);

        if ($familia === null) {
            return ['material_id' => null, 'motivo' => self::SIN_FORMATO];
        }

        $clave = self::clave($familia);
        $generico = $this->genericosPorFamilia[$clave] ?? null;

        if ($generico !== null) {
            return ['material_id' => $generico, 'motivo' => null];
        }

        return [
            'material_id' => null,
            'motivo' => isset($this->familiasConocidas[$clave]) ? self::SIN_GENERICO : self::INEXISTENTE,
        ];
    }

    /**
     * Material buscado por nombre (regla 3, para las filas con la celda de
     * código vacía). El genérico es el de la familia del nombre encontrado.
     *
     * @return array{material_id: int|null, motivo: string|null}
     */
    public function materialDesdeNombre(string $nombre): array
    {
        $this->cargar();

        $clave = self::clave($nombre);
        if ($clave === '') {
            return ['material_id' => null, 'motivo' => self::INEXISTENTE];
        }

        $candidatos = self::puntuar($this->materialesPorClave, $clave);

        if (empty($candidatos)) {
            return ['material_id' => null, 'motivo' => self::INEXISTENTE];
        }

        $familias = array_values(array_filter(array_unique(array_map(
            fn (array $material): ?string => $material['familia'],
            $candidatos
        ))));

        if (count($familias) > 1) {
            return ['material_id' => null, 'motivo' => self::AMBIGUO];
        }

        // Un solo nombre, aunque no tenga código de familia: sin CAT-BAS-X no hay
        // forma de llegar al genérico.
        $generico = $familias === [] ? null : ($this->genericosPorFamilia[self::clave($familias[0])] ?? null);

        return [
            'material_id' => $generico,
            'motivo' => $generico === null ? self::SIN_GENERICO : null,
        ];
    }

    /**
     * Producto buscado por nombre (regla 3). Se aceptan los nombres que caen a
     * un solo producto; si el título lo comparten varias referencias hay que
     * escribir el código, porque adivinar escribiría la receta en el producto
     * equivocado.
     *
     * @return array{producto_id: int|null, motivo: string|null}
     */
    public function productoDesdeNombre(string $nombre): array
    {
        $this->cargar();

        $clave = self::clave($nombre);
        if ($clave === '') {
            return ['producto_id' => null, 'motivo' => self::INEXISTENTE];
        }

        $candidatos = self::puntuar($this->productosPorClave, $clave);
        $ids = array_values(array_unique(array_column($candidatos, 'id')));

        if (count($ids) === 1) {
            return ['producto_id' => $ids[0], 'motivo' => null];
        }

        return [
            'producto_id' => null,
            'motivo' => empty($ids) ? self::INEXISTENTE : self::AMBIGUO,
        ];
    }

    /**
     * Si el material ya es uno de los genéricos de la app (el código de su
     * familia, CAT-BAS-X).
     */
    public function esGenerico(int $materialId): bool
    {
        $this->cargar();

        return isset($this->materialesGenericos[$materialId]);
    }

    /**
     * Reporte de la regla 4: cuántas filas llegaron sin consumo y cuáles fueron.
     *
     * @param  int[]  $filas  números de fila del Excel
     */
    public static function avisoFilasSinConsumo(array $filas): ?string
    {
        if (empty($filas)) {
            return null;
        }

        $filas = array_values(array_unique($filas));
        sort($filas);

        $mostradas = array_slice($filas, 0, 15);
        $ocultas = count($filas) - count($mostradas);

        return count($filas) . ' fila(s) llegaron vacías en "consumo por par" y se tomaron como '
            . self::CONSUMO_VACIO . ': fila(s) ' . implode(', ', $mostradas)
            . ($ocultas > 0 ? ' y ' . $ocultas . ' más' : '') . '.';
    }

    /**
     * Motivo en texto para el aviso que ve el usuario.
     */
    public static function textoMotivo(string $motivo): string
    {
        return match ($motivo) {
            self::SIN_FORMATO => 'no tiene el formato CAT-BAS-7 o CAT-BAS-7-3',
            self::SIN_GENERICO => 'existe pero su familia no tiene material genérico',
            self::AMBIGUO => 'coincide con varios registros',
            default => 'no existe en la app',
        };
    }

    /**
     * Familia de un código: el código del genérico. En un hijo es el código sin
     * el último tramo (COM-CAP-055-34 es COM-CAP-055) y en una familia es ella
     * misma. Null si la celda no tiene formato de código de material.
     */
    public static function familiaDe(?string $codigo): ?string
    {
        return Material::familiaDeCodigo($codigo);
    }

    private function cargar(): void
    {
        if ($this->cargado) {
            return;
        }

        $this->cargado = true;

        foreach (Material::query()->get(['id', 'codigo_material', 'nombre_base', 'generico']) as $material) {
            $familia = self::familiaDe($material->codigo_material);
            $claveFamilia = $familia === null ? null : self::clave($familia);
            $esGenerico = (bool) $material->generico;

            if ($claveFamilia !== null) {
                $this->familiasConocidas[$claveFamilia] = true;

                if ($esGenerico) {
                    $this->genericosPorFamilia[$claveFamilia] ??= (int) $material->id;
                }
            }

            if ($esGenerico) {
                $this->materialesGenericos[(int) $material->id] = true;
            }

            $claveNombre = self::clave($material->nombre_base);
            if ($claveNombre !== '') {
                $this->materialesPorClave[] = [
                    'clave' => $claveNombre,
                    'id' => (int) $material->id,
                    'familia' => $familia,
                ];
            }
        }

        foreach (Producto::query()->whereNotNull('Titulo_Shopify')->get(['id', 'Titulo_Shopify']) as $producto) {
            $clave = self::clave($producto->Titulo_Shopify);
            if ($clave !== '') {
                $this->productosPorClave[] = ['clave' => $clave, 'id' => (int) $producto->id];
            }
        }
    }

    /**
     * Mejores coincidencias de un nombre: primero el idéntico, luego el que
     * arranca igual y al final el que contiene lo buscado. Se devuelve todo el
     * nivel ganador para que el llamador decida si es ambiguo.
     *
     * @param  array<int, array{clave: string, ...}>  $indice
     * @return array<int, array{clave: string, ...}>
     */
    private static function puntuar(array $indice, string $clave): array
    {
        $exactas = [];
        $prefijas = [];
        $contenidas = [];

        foreach ($indice as $entrada) {
            if ($entrada['clave'] === $clave) {
                $exactas[] = $entrada;
            } elseif (str_starts_with($entrada['clave'], $clave) || str_starts_with($clave, $entrada['clave'])) {
                $prefijas[] = $entrada;
            } elseif (str_contains($entrada['clave'], $clave)) {
                $contenidas[] = $entrada;
            }
        }

        return $exactas !== [] ? $exactas : ($prefijas !== [] ? $prefijas : $contenidas);
    }

    /** Clave de comparación: sin mayúsculas, tildes, espacios ni símbolos. */
    private static function clave(?string $texto): string
    {
        return (string) preg_replace(
            '/[^A-Z0-9]/',
            '',
            mb_strtoupper(Str::ascii(trim((string) $texto)), 'UTF-8')
        );
    }
}
