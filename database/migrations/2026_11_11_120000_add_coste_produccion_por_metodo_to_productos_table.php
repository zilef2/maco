<?php

use App\Models\Parametro;
use App\Models\Producto;
use App\Services\CostoMaterialOpService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * El coste de producción se guarda con los dos métodos de precio a la vez,
     * para poder compararlos al crear o editar un producto: el promedio de los
     * precios registrados del material y su último precio. coste_produccion
     * sigue siendo el vigente según el parámetro costos_promedio.
     *
     * Esta migración corre antes de la que convierte los procesos en muchos a
     * muchos, así que el coste de proceso se toma del esquema que exista en la
     * base de datos: el pivote procesoop_producto si ya está creado, o las
     * columnas producto_id + coste_proceso de procesoops si todavía no.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('productos', 'coste_produccion_promedio')) {
            Schema::table('productos', function (Blueprint $table) {
                $table->decimal('coste_produccion_promedio', 15, 2)->nullable()->after('coste_produccion');
            });
        }

        if (!Schema::hasColumn('productos', 'coste_produccion_ultimo')) {
            Schema::table('productos', function (Blueprint $table) {
                $table->decimal('coste_produccion_ultimo', 15, 2)->nullable()->after('coste_produccion_promedio');
            });
        }

        $servicio = app(CostoMaterialOpService::class);
        $costesDeProceso = $this->costesDeProcesoPorProducto();

        Producto::query()
            ->with('fichaTecnica.materiales')
            ->chunkById(100, function ($productos) use ($servicio, $costesDeProceso) {
                $materiales = $servicio->materialesConPrecios(
                    $productos
                        ->flatMap(fn (Producto $producto) => $producto->fichaTecnica
                            ->flatMap(fn ($ficha) => $ficha->materiales->pluck('material_componente_id')))
                        ->filter()
                        ->unique()
                        ->values()
                        ->all()
                );

                foreach ($productos as $producto) {
                    $costeProceso = $costesDeProceso[$producto->id] ?? 0.0;

                    $producto->forceFill([
                        'coste_produccion_promedio' => round(
                            $servicio->costeMaterialesUnitario($producto, $materiales, Parametro::COSTOS_PROMEDIO_PROMEDIO)
                            + $costeProceso,
                            2
                        ),
                        'coste_produccion_ultimo' => round(
                            $servicio->costeMaterialesUnitario($producto, $materiales, Parametro::COSTOS_PROMEDIO_ULTIMO)
                            + $costeProceso,
                            2
                        ),
                    ])->saveQuietly();
                }
            });
    }

    public function down(): void
    {
        $columnas = array_values(array_filter(
            ['coste_produccion_promedio', 'coste_produccion_ultimo'],
            fn (string $columna) => Schema::hasColumn('productos', $columna)
        ));

        if (empty($columnas)) {
            return;
        }

        Schema::table('productos', function (Blueprint $table) use ($columnas) {
            $table->dropColumn($columnas);
        });
    }

    /**
     * Suma de los costes de proceso de cada producto, del esquema que esté activo.
     *
     * @return array<int, float>
     */
    private function costesDeProcesoPorProducto(): array
    {
        if (Schema::hasTable('procesoop_producto')) {
            return DB::table('procesoop_producto')
                ->groupBy('producto_id')
                ->select('producto_id', DB::raw('SUM(coste_proceso) as coste_proceso'))
                ->pluck('coste_proceso', 'producto_id')
                ->map(fn ($total) => (float) $total)
                ->all();
        }

        // Esquema antiguo: un proceso por fila. Se ignora la segunda vez que
        // aparece el mismo nombre en el mismo producto, que es la fila que
        // descarta la migración del pivote por mantener solo la primera.
        $totales = [];
        $ya_vistos = [];

        $filas = DB::table('procesoops')
            ->orderBy('id')
            ->get(['nombre', 'producto_id', 'coste_proceso']);

        foreach ($filas as $fila) {
            if (empty($fila->producto_id)) {
                continue;
            }

            $clave = $fila->producto_id . '|' . $this->normalizar((string) $fila->nombre);

            if (isset($ya_vistos[$clave])) {
                continue;
            }

            $ya_vistos[$clave] = true;
            $totales[$fila->producto_id] = ($totales[$fila->producto_id] ?? 0.0) + (float) $fila->coste_proceso;
        }

        return $totales;
    }

    /** Nombre comparable: sin mayúsculas, sin acentos y con espacios simples. */
    private function normalizar(string $nombre): string
    {
        $nombre = Str::ascii(trim($nombre));

        return preg_replace('/\s+/', ' ', mb_strtolower($nombre)) ?? mb_strtolower($nombre);
    }
};
