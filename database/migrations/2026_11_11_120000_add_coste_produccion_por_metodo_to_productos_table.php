<?php

use App\Models\Parametro;
use App\Models\Producto;
use App\Services\CostoMaterialOpService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El coste de producción se guarda con los dos métodos de precio a la vez,
     * para poder compararlos al crear o editar un producto: el promedio de los
     * precios registrados del material y su último precio. coste_produccion
     * sigue siendo el vigente según el parámetro costos_promedio.
     */
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->decimal('coste_produccion_promedio', 15, 2)->nullable()->after('coste_produccion');
            $table->decimal('coste_produccion_ultimo', 15, 2)->nullable()->after('coste_produccion_promedio');
        });

        $servicio = app(CostoMaterialOpService::class);

        Producto::query()
            ->with(['fichaTecnica.materiales', 'procesos'])
            ->chunkById(100, function ($productos) use ($servicio) {
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
                    $producto->forceFill([
                        'coste_produccion_promedio' => $servicio->costeProduccionUnitario(
                            $producto,
                            $materiales,
                            Parametro::COSTOS_PROMEDIO_PROMEDIO
                        ),
                        'coste_produccion_ultimo' => $servicio->costeProduccionUnitario(
                            $producto,
                            $materiales,
                            Parametro::COSTOS_PROMEDIO_ULTIMO
                        ),
                    ])->saveQuietly();
                }
            });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn(['coste_produccion_promedio', 'coste_produccion_ultimo']);
        });
    }
};
