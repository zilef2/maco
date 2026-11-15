<?php

namespace App\Services;

use App\Models\Material;
use App\Models\Producto;
use Illuminate\Support\Str;

/**
 * Reglas aparte de la carga masiva de la ficha de producto.
 *
 * 1. El código de material se valida por su par CATEGORIA-BASE sin mirar el
 *    consecutivo: si ACC-APL-0 existe, pasan ACC-APL-1, ACC-APL-7 y ACC-APL-9898.
 * 2. Al procesar, cualquier consecutivo equivale al genérico del par (CAT-BAS-0),
 *    que es el único material que admite la ficha técnica.
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

    /** @var array<string, int> prefijo CATEGORIA-BASE => id del material genérico */
    private array $genericosPorPar = [];

    /** @var array<string, bool> prefijo CATEGORIA-BASE => existe algún material del par */
    private array $paresConocidos = [];

    /** @var array<int, bool> ids de materiales genéricos */
    private array $materialesGenericos = [];

    /** @var array<int, array{clave: string, id: int, prefijo: string|null}> */
    private array $materialesPorClave = [];

    /** @var array<int, array{clave: string, id: int}> */
    private array $productosPorClave = [];

    private bool $cargado = false;

    /**
     * Material de la receta para el código de una fila: siempre el genérico del
     * par, sin importar el consecutivo que diga la celda (reglas 1 y 2).
     *
     * @return array{material_id: int|null, motivo: string|null}
     */
    public function materialDesdeCodigo(string $codigo): array
    {
        $this->cargar();

        $prefijo = self::prefijoDe($codigo);

        if ($prefijo === null) {
            return ['material_id' => null, 'motivo' => self::SIN_FORMATO];
        }

        $clave = self::clave($prefijo);
        $generico = $this->genericosPorPar[$clave] ?? null;

        if ($generico !== null) {
            return ['material_id' => $generico, 'motivo' => null];
        }

        return [
            'material_id' => null,
            'motivo' => isset($this->paresConocidos[$clave]) ? self::SIN_GENERICO : self::INEXISTENTE,
        ];
    }

    /**
     * Material buscado por nombre (regla 3, para las filas con la celda de
     * código vacía). También devuelve el genérico del par del nombre encontrado.
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

        $pares = array_values(array_filter(array_unique(array_map(
            fn (array $material): ?string => $material['prefijo'],
            $candidatos
        ))));

        if (count($pares) > 1) {
            return ['material_id' => null, 'motivo' => self::AMBIGUO];
        }

        // Un solo par, aunque ninguno de sus materiales tenga código de par:
        // sin CATEGORIA-BASE no hay forma de llegar al genérico.
        $generico = $pares === [] ? null : ($this->genericosPorPar[self::clave($pares[0])] ?? null);

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
     * Si el material ya es uno de los genéricos CAT-BAS-0 de la app.
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
            self::SIN_FORMATO => 'no tiene el formato CATEGORIA-BASE-0',
            self::SIN_GENERICO => 'existe pero su par no tiene material genérico',
            self::AMBIGUO => 'coincide con varios registros',
            default => 'no existe en la app',
        };
    }

    /**
     * Par CATEGORIA-BASE de un código, sin el consecutivo. Null si la celda no
     * tiene formato de código de material.
     */
    public static function prefijoDe(?string $codigo): ?string
    {
        $normalizado = Material::normalizarCodigo($codigo);

        if ($normalizado === null) {
            return null;
        }

        if (preg_match('/^([A-Z]{1,' . Material::LONGITUD_FRAGMENTO . '}-[A-Z]{1,' . Material::LONGITUD_FRAGMENTO . '})-\d{1,6}$/', $normalizado, $coincidencia) === 1) {
            return $coincidencia[1];
        }

        return null;
    }

    private function cargar(): void
    {
        if ($this->cargado) {
            return;
        }

        $this->cargado = true;

        foreach (Material::query()->get(['id', 'codigo_material', 'nombre_base', 'generico']) as $material) {
            $prefijo = self::prefijoDe($material->codigo_material);
            $clavePar = $prefijo === null ? null : self::clave($prefijo);
            $esGenerico = (bool) $material->generico;

            if ($clavePar !== null) {
                $this->paresConocidos[$clavePar] = true;

                if ($esGenerico) {
                    $this->genericosPorPar[$clavePar] ??= (int) $material->id;
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
                    'prefijo' => $prefijo,
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
