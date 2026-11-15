<?php

namespace App\Services;

use App\Models\Material;
use App\Traits\MaterialMovimientoTrait;
use Illuminate\Support\Facades\DB;

/**
 * Materiales genéricos: uno por cada par código categoría + código base.
 *
 * El genérico no maneja talla ni color, se codifica con el 0 del par
 * (COM-CAP-0) y su precio es el promedio de los precios de sus similares
 * (promedio por material y luego entre materiales). sincronizar() se puede
 * ejecutar las veces que sea: crea los que faltan, pone en ese código a los
 * que lo traen de la regla antigua y refresca el precio de los existentes
 * cuando el promedio cambió.
 */
class MaterialesGenericosService
{
    use MaterialMovimientoTrait;

    public function __construct(
        protected ExcelMaterialesService $excelMateriales,
    ) {}

    /** Sufijo del nombre base para distinguir el genérico en los selects. */
    public const SUFIJO_NOMBRE = ' (Genérico)';

    /**
     * Grupos categoría-base y cuántos tienen o no su genérico.
     *
     * @return array{grupos: int, con_generico: int, sin_generico: int, genericos: int}
     */
    public static function estadisticas(): array
    {
        $claves = Material::query()
            ->where('generico', false)
            ->get(['codigo_categoria', 'codigo_base'])
            ->map(fn (Material $m): string => Material::normalizarFragmento($m->codigo_categoria)
                . '-' . Material::normalizarFragmento($m->codigo_base))
            ->filter(fn (string $clave): bool => $clave !== '-')
            ->unique()
            ->values();

        $genericos = Material::where('generico', true)->count();
        $sinGenerico = max(0, $claves->count() - $genericos);

        return [
            'grupos' => $claves->count(),
            'con_generico' => $claves->count() - $sinGenerico,
            'sin_generico' => $sinGenerico,
            'genericos' => $genericos,
        ];
    }

    /**
     * @return array{creados: int, precios: int, renumerados: int}
     */
    public function sincronizar(): array
    {
        return DB::transaction(function (): array {
            $materiales = Material::with('prices')
                ->orderBy('codigo_material')
                ->get()
                ->partition(fn (Material $m): bool => (bool) $m->generico);

            /** @var \Illuminate\Support\Collection<int, Material> $similares */
            $similares = $materiales[1];
            $grupos = $similares->groupBy(
                fn (Material $m): string => Material::normalizarFragmento($m->codigo_categoria)
                    . '-' . Material::normalizarFragmento($m->codigo_base)
            )->reject(fn ($grupo, string $clave): bool => $clave === '-');

            /** @var \Illuminate\Support\Collection<int, Material> $genericos */
            $genericos = $materiales[0]
                ->keyBy(fn (Material $m): string => Material::normalizarFragmento($m->codigo_categoria)
                    . '-' . Material::normalizarFragmento($m->codigo_base));

            $creados = 0;
            $precios = 0;
            $renumerados = 0;

            foreach ($grupos as $clave => $grupo) {
                $promedio = $this->promedioSimilares($grupo);
                $generico = $genericos->get($clave);

                if ($generico === null) {
                    $modelo = $grupo->first();
                    $generico = Material::create([
                        'codigo_categoria' => $modelo->codigo_categoria,
                        'codigo_base' => $modelo->codigo_base,
                        'categoria' => $modelo->categoria,
                        'nombre_base' => $modelo->nombre_base . self::SUFIJO_NOMBRE,
                        'unidad_inventario' => $modelo->unidad_inventario,
                        'estado' => $modelo->estado ?: 'Activo',
                        'proveedor_habitual' => $modelo->proveedor_habitual,
                        'generico' => true,
                    ]);
                    $genericos->put($clave, $generico);
                    $this->registrarMovimientoCreacion($generico, 'Creación de material genérico');
                    $creados++;
                } elseif ($generico->codigo_material !== $clave . '-0') {
                    // Los genéricos creados con la regla antigua traen el
                    // consecutivo del par; el saving los reescribe a CAT-BAS-0.
                    $generico->save();
                    $renumerados++;
                }

                if ($promedio !== null && round((float) $generico->costoUnitarioActual(), 2) !== $promedio) {
                    $generico->prices()->create([
                        'precio' => $promedio,
                        'fecha' => now()->toDateString(),
                    ]);
                    $precios++;
                }
            }

            if ($creados > 0 || $precios > 0 || $renumerados > 0) {
                // El listado de materiales se sirve desde caché: se invalida
                // para que los genéricos nuevos (o su precio) se vean de una.
                $this->excelMateriales->invalidarCacheListados();
            }

            return ['creados' => $creados, 'precios' => $precios, 'renumerados' => $renumerados];
        });
    }

    /**
     * Promedio de los similares: primero el promedio de precios de cada
     * material y luego el promedio entre los materiales que tienen precio.
     */
    private function promedioSimilares($grupo): ?float
    {
        $promedios = $grupo
            ->map(fn (Material $m): float => $m->costoUnitarioPromedio())
            ->filter(fn (float $p): bool => $p > 0)
            ->values();

        return $promedios->isEmpty() ? null : round($promedios->avg(), 2);
    }
}
