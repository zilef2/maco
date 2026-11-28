<?php

namespace App\Services;

use App\Models\Material;
use App\Models\Proveedor;
use Illuminate\Support\Facades\Cache;

/**
 * El listado de Materiales por ventanas de caché.
 *
 * La tabla no se hidrata entera nunca: se cachea la lista ordenada de ids y de
 * ahí salen cortes pequeños (la primera pantalla y cada bloque del resto), cada
 * uno con su propia clave. Un Index de 1.100 materiales cuesta hoy una lectura
 * de caché en vez de recorrer la tabla y sus relaciones en cada petición.
 *
 * Vive en un servicio, y no en el controlador, porque lo mismo lo pide la
 * petición que ve el usuario y lo mismo lo recalienta el job que se dispara al
 * escribir un material. La consistencia la da la versión del listado: toda
 * escritura la sube, así que la clave anterior queda huérfana y jamás se sirve
 * un bloque viejo. Lo que hace la recarga es evitar el sobrecosto de reconstruir
 * justo cuando alguien abre la vista.
 */
class MaterialIndexCacheService
{
    /**
     * Lo ya resuelto en esta petición: el Index pide ids, primera carga y total,
     * y los tres salen de la misma lista.
     */
    private array $calculado = [];

    public function __construct(
        private ExcelMaterialesService $excel,
        private FichaTecnicaService $fichaTecnica,
        private MaterialCompraPredictorService $compraPredictor,
    ) {
    }

    /** De cuántos en cuántos baja el resto del listado en segundo plano. */
    public function lote(): int
    {
        return max(1, (int) config('materiales.batch_size', 30));
    }

    /** Materiales de la primera respuesta (nunca menos que un lote). */
    public function primeraCarga(): int
    {
        return max($this->lote(), (int) config('materiales.primera_carga', 50));
    }

    /** Lo que consume la vista para saber de cuántos en cuántos pedir. */
    public function ajustesCarga(): array
    {
        return ['inicial' => $this->primeraCarga(), 'lote' => $this->lote()];
    }

    /**
     * Los ids del Index, en el orden de la tabla.
     *
     * El desempate por id no es adorno: dos materiales tocados el mismo segundo
     * pueden cambiar de lugar entre la primera pantalla y los bloques, y ahí los
     * cortes se pisan o se saltan filas.
     *
     * @return array<int, int>
     */
    public function ids(): array
    {
        return $this->unaVez('ids', fn () => Cache::remember(
            'material_index_ids_' . $this->excel->versionCache(),
            $this->duracion(),
            fn () => Material::query()
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all()
        ));
    }

    public function total(): int
    {
        return count($this->ids());
    }

    /**
     * Primera pantalla, ya lista para el JSON. Es la ventana que interesa tener
     * caliente: al abrir la vista no se toca la tabla, solo se lee caché.
     *
     * @return array<int, array<string, mixed>>
     */
    public function primera(): array
    {
        return $this->bloqueCacheado('material_index_primera', array_slice($this->ids(), 0, $this->primeraCarga()));
    }

    /**
     * Corte para la carga en segundo plano. El corte es sobre la misma lista de
     * ids de la primera pantalla, así que los bloques nunca se pisan entre sí y
     * al navegador solo le queda appendear.
     *
     * @return array<int, array<string, mixed>>
     */
    public function ventana(int $desde, int $limite): array
    {
        $limite = min($this->lote(), max(1, $limite));

        return $this->bloqueCacheado('material_index_lote', array_slice($this->ids(), $desde, $limite));
    }

    /**
     * Recarga lo que abre la vista. Se llama desde el job, después de que las
     * escrituras se acumularon; una ventana ya caliente sale de la caché sin
     * volver a consultar nada.
     *
     * @return array{ids: int, primera: int}
     */
    public function recalentar(): array
    {
        $ids = $this->ids();

        return [
            'ids' => count($ids),
            'primera' => count($this->primera()),
        ];
    }

    /**
     * Un corte de ids resuelto en filas, en caché. La clave lleva el primer id
     * del bloque además de la versión: si el orden se movió por algún motivo que
     * no subió la versión, la ventana pide otra clave y nunca devuelve filas
     * desubicadas.
     *
     * @param  array<int, int>  $ids
     * @return array<int, array<string, mixed>>
     */
    private function bloqueCacheado(string $prefijo, array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        // `array_key_first` es la posición, no el material: la huella del corte es
        // su primer id, que es lo que cambia si el orden se movió.
        $clave = $prefijo . '_' . $this->excel->versionCache()
            . '_' . $ids[array_key_first($ids)] . '_' . count($ids);

        return $this->unaVez($clave, fn () => Cache::remember(
            $clave,
            $this->duracion(),
            fn () => $this->filas($ids)
        ));
    }

    /**
     * Filas del listado para un puñado de ids, ya como arrays listos para el
     * JSON. Los campos derivados son los que lee la tabla; sin ellos la vista
     * tendría que hidratar relaciones por su cuenta.
     *
     * @param  array<int, int>  $ids
     * @return array<int, array<string, mixed>>
     */
    private function filas(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        // Bloqueos y predicciones se piden solo para este corte: recorrer las OPs
        // o los movimientos por cada bloque salía carísimo con la tabla llena.
        $bloqueosFicha = $this->fichaTecnica->bloqueosPorMaterial($ids);
        $prediccionesCompras = $this->compraPredictor->obtenerPrediccionesParaMateriales($ids);
        // El nombre del proveedor se resuelve aquí en vez de cargar la relación:
        // serializada ocupa la clave `proveedor_habitual`, que el listado usa como
        // identificador para filtrar.
        $nombresProveedor = Proveedor::query()->pluck('nombre', 'id');

        return Material::query()
            ->with(['prices', 'variants', 'fichaTecnica.materiales.material'])
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (Material $material) => array_search($material->id, $ids, true))
            ->map(function (Material $material) use ($bloqueosFicha, $prediccionesCompras, $nombresProveedor) {
                $material->proveedor_nombre = $material->proveedor_habitual
                    ? ($nombresProveedor[$material->proveedor_habitual] ?? null)
                    : null;

                // La relación ya viene ordenada por fecha, así que el último precio
                // y los tres recientes se leen de ella; consultarla suelta costaba
                // dos consultas por cada material.
                $precios = $material->prices;
                $material->last_purchase_price = $precios->first()?->precio ?? null;
                $material->last_purchase_date = $precios->first()?->fecha ?? null;
                // Gasto diario (unidades por día, persistido en BD)
                $material->gasto_diario = $material->gasto_diario ?? null;
                $material->gasto_diario_actualizado_en = $material->gasto_diario_actualizado_en ?? null;
                $material->recent_prices = $precios->take(3)->map(fn ($price) => [
                    'precio' => $price->precio,
                    'fecha'  => $price->fecha,
                ])->values()->all();

                $ficha = $material->fichaTecnica->first();
                $material->ficha_id = $ficha?->id;
                $material->ficha_nombre = $ficha?->nombre;
                $material->ficha_materiales = ($ficha?->materiales ?? collect())
                    ->map(fn ($fila) => [
                        'material_componente_id' => $fila->material_componente_id,
                        'consumo_estandar' => $fila->consumo_estandar,
                        'unidad_medida' => $fila->unidad_medida,
                        'costo_asociado_teorico' => $fila->costo_asociado_teorico,
                    ])
                    ->values()
                    ->all();
                $bloqueos = $bloqueosFicha[$material->id] ?? [];
                $material->ficha_bloqueada = !empty($bloqueos);
                $material->ficha_bloqueo_detalle = collect($bloqueos)
                    ->map(fn ($estado, $numeroOp) => $numeroOp . ' (' . $estado . ')')
                    ->implode(', ');

                $pred = $prediccionesCompras[$material->id] ?? null;
                $material->ultima_compra_cantidad = $pred['cantidad'] ?? null;
                $material->ultima_compra_fecha = $pred['fecha'] ?? null;
                $material->ultima_compra_fecha_humana = $pred['fecha_humana'] ?? null;

                // Ya no hacen falta en el JSON: la tabla lee los campos derivados
                // de arriba. `variants` sí se queda (sus chips). Se ocultan con el
                // nombre de la relación, no con la clave snake del JSON.
                $material->makeHidden(['prices', 'fichaTecnica']);

                return $material->toArray();
            })
            ->values()
            ->all();
    }

    private function duracion(): \DateTimeInterface
    {
        return now()->addMinutes(max(1, (int) config('materiales.cache_minutos', 10)));
    }

    private function unaVez(string $clave, callable $fabrica): mixed
    {
        return $this->calculado[$clave] ??= $fabrica();
    }
}
