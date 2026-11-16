<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Repara el vínculo entre los productos y sus tallas.
 *
 * Las filas de distribucion_por_tallas guardan su producto_id, pero el pivote
 * distribucion_producto (el que leen la página del producto y su modal de
 * tallas) puede quedar vacío. Este comando enlaza una fila por talla: la más
 * reciente, que es la que el último formulario dejó como plantilla. Las filas
 * sobrantes de la misma talla se dejan intactas porque pertenecen a OPs que ya
 * recibieron inventario.
 */
class RepararTallasProducto extends Command
{
    protected $signature = 'productos:reparar-tallas
                            {--dry-run : Muestra lo que se enlazaría sin escribir en la base}';

    protected $description = 'Enlaza en distribucion_producto una fila de talla por producto (la más reciente)';

    public function handle(): int
    {
        $simulacion = (bool) $this->option('dry-run');
        $vinculadas = 0;
        $productos = 0;
        $detalle = [];

        $idsProductos = DB::table('distribucion_por_tallas')
            ->whereNotNull('producto_id')
            ->whereNull('material_id')
            ->distinct()
            ->pluck('producto_id');

        foreach ($idsProductos as $productoId) {
            $productos++;

            $vinculadasAntes = DB::table('distribucion_producto as dp')
                ->join('distribucion_por_tallas as d', 'd.id', '=', 'dp.distribucion_por_talla_id')
                ->where('dp.producto_id', $productoId)
                ->get(['d.id', 'd.talla']);

            $tallas = [];
            foreach ($vinculadasAntes as $fila) {
                $tallas[trim((string) $fila->talla)] = true;
            }

            $candidatas = DB::table('distribucion_por_tallas')
                ->where('producto_id', $productoId)
                ->whereNull('material_id')
                ->orderByDesc('id')
                ->get(['id', 'talla', 'cantidad_planificada', 'cantidad_recibida_buena']);

            foreach ($candidatas as $candidata) {
                $talla = trim((string) $candidata->talla);
                if (isset($tallas[$talla])) {
                    continue;
                }

                $detalle[] = [
                    'producto_id' => $productoId,
                    'distribucion_id' => $candidata->id,
                    'talla' => $talla === '' ? '—' : $talla,
                    'planificada' => (int) $candidata->cantidad_planificada,
                    'recibida' => (int) ($candidata->cantidad_recibida_buena ?? 0),
                ];

                if (!$simulacion) {
                    DB::table('distribucion_producto')->insert([
                        'producto_id' => $productoId,
                        'distribucion_por_talla_id' => $candidata->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $tallas[$talla] = true;
                $vinculadas++;
            }
        }

        $this->table(
            ['Producto', 'Distribución', 'Talla', 'Planificada', 'Recibida'],
            $detalle
        );

        $this->info(($simulacion ? 'Se enlazarían ' : 'Se enlazaron ') . $vinculadas
            . ' talla(s) en ' . $productos . ' producto(s).');

        return self::SUCCESS;
    }
}
