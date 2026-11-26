<?php

namespace App\Services;

use App\Models\Material;
use App\Models\Producto;
use Illuminate\Support\Str;

/**
 * Reglas aparte de la carga masiva de la ficha de producto.
 *
 * 1. Quién es la base lo dice la columna "Base si o no" del Excel de materiales,
 *    no el código: cualquier forma (CAT-BAS-011 o CAT-BAS-011-04) puede ser base
 *    o no. Lo que el código sí conserva es el vínculo de parentesco: la familia
 *    de CAT-BAS-011-04 es CAT-BAS-011.
 * 2. La ficha se escribe sobre la base de la familia cuando arriba existe y está
 *    marcada como base. Si arriba no hay ninguna base, el material está
 *    huérfano y la ficha va sobre él mismo, sin importar cuántos tramos tenga.
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
    public const INEXISTENTE = 'inexistente';
    public const AMBIGUO = 'ambiguo';

    /** @var array<string, int> código canónico => id de ese material */
    private array $materialesPorCodigo = [];

    /** @var array<string, int> código de familia => id de su material base */
    private array $basesPorFamilia = [];

    /** @var array<int, string> id => código canónico del material */
    private array $codigos = [];

    /** @var array<int, bool> ids de materiales marcados como base */
    private array $materialesGenericos = [];

    /** @var array<int, array{clave: string, id: int}> */
    private array $materialesPorClave = [];

    /** @var array<int, array{clave: string, id: int}> */
    private array $productosPorClave = [];

    private bool $cargado = false;

    /**
     * Material de la celda de código (reglas 1 y 2), ya reducido a la base de su
     * familia o a sí mismo cuando está huérfano.
     *
     * @return array{material_id: int|null, motivo: string|null}
     */
    public function materialDesdeCodigo(string $codigo): array
    {
        $this->cargar();

        $id = $this->buscarCodigo($codigo);

        if ($id === null) {
            return ['material_id' => null, 'motivo' => self::INEXISTENTE];
        }

        return ['material_id' => $this->baseDe($id, $this->codigos[$id]), 'motivo' => null];
    }

    /**
     * Material buscado por nombre (regla 3, para las filas con la celda de
     * código vacía). Se acepta solo cuando el nombre cae en un material; si lo
     * comparten varios hay que escribir el código.
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
        $ids = array_values(array_unique(array_column($candidatos, 'id')));

        if (count($ids) !== 1) {
            return [
                'material_id' => null,
                'motivo' => empty($ids) ? self::INEXISTENTE : self::AMBIGUO,
            ];
        }

        $id = $ids[0];

        return ['material_id' => $this->baseDe($id, $this->codigos[$id] ?? null), 'motivo' => null];
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
     * Si el material es uno de los marcados como base (el "Base si o no" del
     * Excel de materiales).
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
            self::AMBIGUO => 'coincide con varios registros; escriba el código',
            default => 'no existe en la app',
        };
    }

    /**
     * Familia de un código: el código sin su último tramo (COM-CAP-055-34 es
     * COM-CAP-055) y en una familia ella misma. Null si la celda no tiene
     * Null si la celda no tiene tramos suficientes para formar una familia.
     */
    public static function familiaDe(?string $codigo): ?string
    {
        return Material::familiaDeCodigo($codigo);
    }

    /**
     * Id de la base sobre la que va la ficha del material: la base de su familia
     * cuando arriba hay una, y él mismo cuando está huérfano.
     */
    private function baseDe(int $materialId, ?string $codigo): int
    {
        if ($codigo === null) {
            return $materialId;
        }

        $familia = Material::familiaDeCodigo($codigo);

        if ($familia === null || $familia === $codigo) {
            return $materialId;
        }

        return $this->basesPorFamilia[self::clave($familia)] ?? $materialId;
    }

    /** Id del material con ese código, con o sin separadores. */
    private function buscarCodigo(string $codigo): ?int
    {
        $claves = array_filter([
            self::clave($codigo),
            self::clave(Material::normalizarCodigo($codigo)),
        ]);

        foreach ($claves as $clave) {
            if (isset($this->materialesPorCodigo[$clave])) {
                return $this->materialesPorCodigo[$clave];
            }
        }

        return null;
    }

    /**
     * Vuelve a leer los índices en la próxima consulta. La hoja "ficha" del MPP
     * corre después de la de materiales, dentro de la misma petición y de la
     * misma transacción: sin esto se quedaría con los materiales de antes.
     */
    public function recargar(): void
    {
        $this->cargado = false;
        $this->materialesPorCodigo = [];
        $this->basesPorFamilia = [];
        $this->codigos = [];
        $this->materialesGenericos = [];
        $this->materialesPorClave = [];
        $this->productosPorClave = [];
    }

    private function cargar(): void
    {
        if ($this->cargado) {
            return;
        }

        $this->cargado = true;

        foreach (Material::query()->get([
            'id', 'codigo_material', 'nombre_base', 'nombre_material', 'generico',
        ]) as $material) {
            $id = (int) $material->id;
            $esBase = (bool) $material->generico;

            if ($esBase) {
                $this->materialesGenericos[$id] = true;
            }

            $canonico = Material::normalizarCodigo($material->codigo_material);
            if ($canonico === null) {
                continue;
            }

            $claveCodigo = self::clave($canonico);
            $this->materialesPorCodigo[$claveCodigo] ??= $id;
            $this->codigos[$id] = $canonico;

            // Como base de la familia solo sirve el material que ES la familia
            // (sin último tramo) y está marcado: CAT-BAS-011-04 marcado como base
            // no puede adoptar a su propia familia.
            if ($esBase && $canonico === Material::familiaDeCodigo($canonico)) {
                $this->basesPorFamilia[$claveCodigo] ??= $id;
            }

            foreach ([$material->nombre_base, $material->nombre_material] as $nombre) {
                $claveNombre = self::clave($nombre);
                if ($claveNombre !== '') {
                    $this->materialesPorClave[] = ['clave' => $claveNombre, 'id' => $id];
                }
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
