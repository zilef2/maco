<?php

namespace App\Services;

use App\Models\Material;
use App\Models\OrdenProduccion;
use App\Models\OrdenProduccionMaterialConcreto;
use App\Models\Producto;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Material concreto por línea genérica de la ficha, dentro de una OP.
 *
 * La ficha técnica declara genéricos (COM-CAP-0) porque allí solo importa el
 * consumo del par; el color o la talla que se van a gastar se deciden en la OP
 * y deben ser un material del mismo par categoría-base (COM-CAP-1..975). Esta
 * clase valida esa elección, la guarda y la resuelve para que el kit, los
 * compromisos, los faltantes, el descuento en taller y los costos trabajen
 * siempre con el material concreto.
 */
class MaterialConcretoOpService
{
    /** Prefijo de familia de un material: el par categoría-base (COM-CAP). */
    public static function familiaDe(?Material $material): string
    {
        if (!$material) {
            return '';
        }

        return Material::normalizarFragmento($material->codigo_categoria)
            . '-' . Material::normalizarFragmento($material->codigo_base);
    }

    /**
     * Materiales concretos (no genéricos y activos) agrupados por familia.
     *
     * @return Collection<string, Collection<int, Material>>
     */
    public function concretosPorFamilia(): Collection
    {
        return Material::query()
            ->with('variants')
            ->where('generico', false)
            ->where('estado', 'Activo')
            ->orderBy('codigo_material')
            ->get(['id', 'codigo_material', 'nombre_base', 'codigo_categoria', 'codigo_base', 'unidad_inventario', 'existencia_actual'])
            ->groupBy(fn (Material $material): string => self::familiaDe($material));
    }

    /**
     * Solo las familias pedidas, listas para armar los selectores de la OP.
     * El color o la talla de un material concreto vive en sus variantes.
     *
     * @param  list<string>  $familias
     * @return Collection<string, Collection<int, Material>>
     */
    public function concretosDeFamilias(array $familias): Collection
    {
        $todas = $this->concretosPorFamilia();

        return collect($familias)
            ->filter()
            ->unique()
            ->mapWithKeys(fn (string $familia) => [$familia => $todas->get($familia, collect())])
            ->reject(fn (Collection $grupo): bool => $grupo->isEmpty());
    }

    /**
     * Nombre con el que un material concreto aparece en los selectores: su
     * código y, si las tiene, sus variantes (COM-CAP-17 · 23).
     */
    public function nombreConcreto(Material $material): string
    {
        $variantes = $material->variants
            ? $material->variants->pluck('nombre')->filter()->implode(' / ')
            : '';

        return trim($material->codigo_material . ($variantes !== '' ? ' · ' . $variantes : ''));
    }

    /**
     * Genéricos declarados en la ficha del objetivo (producto o material
     * procesado), en el orden en que los lee el kit de la OP.
     *
     * @return array<int, Material>
     */
    public function genericosDeObjetivo(Producto|Material $objetivo): array
    {
        $ficha = $objetivo->fichaTecnica?->first();

        if (!$ficha) {
            return [];
        }

        $genericos = [];
        foreach ($ficha->materiales as $fila) {
            $material = $fila->material;
            if (!$material || !$material->generico) {
                continue;
            }

            $genericos[(int) $fila->material_componente_id] = $material;
        }

        return $genericos;
    }

    /**
     * Revisa las elecciones del formulario y devuelve el mapa resuelto
     * [tipo de objetivo][objetivo][genérico] = concreto.
     *
     * Con $exigir en false (borradores) solo se descarta lo que no corresponde
     * a la familia; con $exigir en true cada línea genérica necesita su
     * material concreto antes de registrar la OP.
     *
     * @param  list<int>  $productoIds
     * @param  list<int>  $materialObjetivoIds
     * @param  array<int|string, array<int|string, mixed>>  $elecciones  [objetivo][genérico] = concreto
     * @return array<string, array<int, array<int, int>>>
     */
    public function validar(array $productoIds, array $materialObjetivoIds, array $elecciones, bool $exigir): array
    {
        $concretosPorFamilia = $this->concretosPorFamilia();
        $mapa = [];
        $faltantes = [];
        $familiasVacias = [];
        $invalidos = [];

        foreach ($this->objetivos($productoIds, $materialObjetivoIds) as [$tipo, $objetivo]) {
            $genericos = $this->genericosDeObjetivo($objetivo);
            if (empty($genericos)) {
                continue;
            }

            $nombreObjetivo = $this->nombreObjetivo($objetivo);
            $elegidosPorObjetivo = $this->normalizarElecciones($elecciones[$objetivo->id] ?? null);

            foreach ($genericos as $genericoId => $generico) {
                $familia = self::familiaDe($generico);
                $concretos = $concretosPorFamilia->get($familia, collect());
                $concretoId = (int) ($elegidosPorObjetivo[$genericoId] ?? 0);

                if ($concretoId <= 0) {
                    if ($exigir && $concretos->isNotEmpty()) {
                        $faltantes[] = $generico->codigo_material . ' de ' . $nombreObjetivo;
                    } elseif ($exigir) {
                        $familiasVacias[] = $familia;
                    }

                    continue;
                }

                $concreto = $concretos->firstWhere('id', $concretoId);
                if (!$concreto) {
                    $invalidos[] = 'el material elegido para ' . $generico->codigo_material . ' no es un material activo de la familia ' . $familia;

                    continue;
                }

                $mapa[$tipo][$objetivo->id][$genericoId] = $concretoId;
            }
        }

        if ($exigir && (!empty($faltantes) || !empty($familiasVacias) || !empty($invalidos))) {
            $mensajes = [];

            if ($faltantes) {
                $mensajes[] = 'Defina el material concreto (color o talla) de: ' . implode(', ', $faltantes) . '.';
            }
            if ($familiasVacias) {
                $mensajes[] = 'Estas familias no tienen ningún material concreto registrado: '
                    . implode(', ', array_unique($familiasVacias))
                    . '. Cree el material con su color o talla antes de registrar la OP.';
            }
            if ($invalidos) {
                $mensajes[] = 'Revisar: ' . implode('; ', $invalidos) . '.';
            }

            throw ValidationException::withMessages([
                'materiales_concretos' => implode(' ', $mensajes),
            ]);
        }

        return $mapa;
    }

    /**
     * Reemplaza las elecciones guardadas de la OP.
     *
     * @param  array<string, array<int, array<int, int>>>  $mapa
     */
    public function guardar(int $ordenProduccionId, array $mapa): void
    {
        OrdenProduccionMaterialConcreto::query()
            ->where('orden_produccion_id', $ordenProduccionId)
            ->delete();

        $ahora = now();
        $filas = [];

        foreach ($mapa as $tipo => $porObjetivo) {
            foreach ($porObjetivo as $objetivoId => $porGenerico) {
                foreach ($porGenerico as $genericoId => $concretoId) {
                    $filas[] = [
                        'orden_produccion_id' => $ordenProduccionId,
                        'objetivo_tipo' => $tipo,
                        'objetivo_id' => (int) $objetivoId,
                        'material_generico_id' => (int) $genericoId,
                        'material_concreto_id' => (int) $concretoId,
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ];
                }
            }
        }

        if ($filas) {
            OrdenProduccionMaterialConcreto::query()->insert($filas);
        }
    }

    /**
     * Elecciones guardadas de la OP: [tipo][objetivo][genérico] = concreto.
     *
     * @return array<string, array<int, array<int, int>>>
     */
    public function mapaDeOrden(OrdenProduccion $op): array
    {
        $mapa = [];

        $filas = $op->relationLoaded('concretosMateriales')
            ? $op->getRelation('concretosMateriales')
            : $op->concretosMateriales()->get();

        foreach ($filas as $fila) {
            $mapa[$fila->objetivo_tipo][(int) $fila->objetivo_id][(int) $fila->material_generico_id]
                = (int) $fila->material_concreto_id;
        }

        return $mapa;
    }

    /**
     * Material que realmente se consume por una línea de ficha: el concreto
     * elegido en la OP o, si la línea no es genérica o la OP no la resolvió
     * (OPs antiguas), el material declarado en la ficha.
     */
    public function resolver(array $mapa, string $tipo, int $objetivoId, int $materialDeFicha): int
    {
        return (int) ($mapa[$tipo][$objetivoId][$materialDeFicha] ?? $materialDeFicha);
    }

    /**
     * Ids de los concretos mencionados por el mapa, sin cargar los materiales.
     *
     * @param  array<string, array<int, array<int, int>>>  $mapa
     * @return list<int>
     */
    public function idsDelMapa(array $mapa): array
    {
        return collect($mapa)
            ->flatMap(fn (array $porObjetivo) => collect($porObjetivo)->flatMap(
                fn (array $porGenerico) => array_values($porGenerico)
            ))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Materiales concretos mencionados por el mapa, cargados una sola vez para
     * poder leer su nombre, unidad y existencia al armar el kit de la OP.
     *
     * @param  array<string, array<int, array<int, int>>>  $mapa
     * @return Collection<int, Material>
     */
    public function materialesDelMapa(array $mapa): Collection
    {
        $ids = $this->idsDelMapa($mapa);

        if (empty($ids)) {
            return collect();
        }

        return Material::query()
            ->with('variants')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
    }

    /**
     * Material del que se toman nombre, unidad y existencia para una línea de
     * ficha: el concreto elegido en la OP, o el de la ficha si la línea no es
     * genérica o el concreto ya no existe.
     *
     * @param  Collection<int, Material>  $concretos
     */
    public function materialResuelto(
        array $mapa,
        Collection $concretos,
        string $tipo,
        int $objetivoId,
        Material $deFicha
    ): Material {
        $id = $this->resolver($mapa, $tipo, $objetivoId, (int) $deFicha->id);

        return $id === (int) $deFicha->id
            ? $deFicha
            : ($concretos->get($id) ?? $deFicha);
    }

    /**
     * @param  list<int>  $productoIds
     * @param  list<int>  $materialObjetivoIds
     * @return iterable<array{0: string, 1: Producto|Material}>
     */
    private function objetivos(array $productoIds, array $materialObjetivoIds): iterable
    {
        if (!empty($productoIds)) {
            foreach (Producto::with('fichaTecnica.materiales.material')->whereIn('id', $productoIds)->get() as $producto) {
                yield [OrdenProduccionMaterialConcreto::OBJETIVO_PRODUCTO, $producto];
            }
        }

        if (!empty($materialObjetivoIds)) {
            foreach (Material::with('fichaTecnica.materiales.material')->whereIn('id', $materialObjetivoIds)->get() as $material) {
                yield [OrdenProduccionMaterialConcreto::OBJETIVO_MATERIAL, $material];
            }
        }
    }

    private function nombreObjetivo(Producto|Material $objetivo): string
    {
        if ($objetivo instanceof Material) {
            return trim(($objetivo->codigo_material ? $objetivo->codigo_material . ' — ' : '') . $objetivo->nombre_base)
                ?: ('Material #' . $objetivo->id);
        }

        return $objetivo->Titulo_Shopify ?: ($objetivo->Codigo_interno ?: ('Producto #' . $objetivo->id));
    }

    /**
     * @return array<int, int>
     */
    private function normalizarElecciones(mixed $elecciones): array
    {
        if (!is_array($elecciones)) {
            return [];
        }

        return collect($elecciones)
            ->filter(fn ($valor) => is_numeric($valor) && (int) $valor > 0)
            ->mapWithKeys(fn ($valor, $clave) => [(int) $clave => (int) $valor])
            ->all();
    }
}
