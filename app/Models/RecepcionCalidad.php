<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

    class RecepcionCalidad extends Model
{
    /** @use HasFactory<\Database\Factories\RecepcionCalidadFactory> */
    use HasFactory;
    protected $fillable = [
        'orden_produccion_id',
        'producto_referencia_id',
        'talla',
        'cantidad_esperada',
        'cantidad_recibida',
        'cantidad_aprobada',
        'cantidad_en_reparacion',
        'cantidad_no_recuperable',
        'fecha_recepcion',
        'observaciones',
        'fecha_inicio_revision',
        'fecha_fin_revision'
    ];

    /**
     * Genera una recepción por cada talla recibida de la OP.
     *
     * Las filas llegan normalizadas desde el cambio de estado: incluyen el
     * producto de la talla, la cantidad esperada y la recibida (que es la suma
     * de aprobada + en reparación + no recuperable). Es idempotente por
     * OP + producto + talla.
     *
     * @param  array<int, array{producto_id: int|null, talla: string, cantidad_esperada: int, cantidad_recibida: int, cantidad_aprobada: int, cantidad_en_reparacion: int, cantidad_no_recuperable: int}>  $filas
     * @return int cantidad de tallas procesadas
     */
    public static function generarDesdeOrden(OrdenProduccion $orden, array $filas = []): int
    {
        $generadas = 0;

        foreach ($filas as $fila) {
            static::updateOrCreate(
                [
                    'orden_produccion_id'     => $orden->id,
                    'producto_referencia_id'  => $fila['producto_id'] ?? null,
                    'talla'                   => $fila['talla'],
                ],
                [
                    'cantidad_esperada'       => (int) $fila['cantidad_esperada'],
                    'cantidad_recibida'       => (int) $fila['cantidad_recibida'],
                    'cantidad_aprobada'       => (int) $fila['cantidad_aprobada'],
                    'cantidad_en_reparacion'  => (int) $fila['cantidad_en_reparacion'],
                    'cantidad_no_recuperable' => (int) $fila['cantidad_no_recuperable'],
                    'fecha_recepcion'         => now(),
                ]
            );

            $generadas++;
        }

        return $generadas;
    }



    public static function getFillableWithTypes(): array
    {
        $table = (new static)->getTable();
        $columns = DB::select("SHOW COLUMNS FROM {$table}");

        $fillable = (new static)->getFillable();
        $result = [];

        foreach ($columns as $column) {
            if (! in_array($column->Field, $fillable) || $column->Field === 'id') {
                continue;
            }

            $type = 'text';
            if (str_contains($column->Type, 'foreign') || str_ends_with($column->Field, '_id')) {
                $type = 'foreign';
            } elseif (str_contains($column->Type, 'int')) {
                $type = 'integer';
            } elseif (str_contains($column->Type, 'decimal') || str_contains($column->Type, 'float')) {
                $type = 'dinero';
            } elseif (str_contains($column->Type, 'date') || str_contains($column->Type, 'datetime')) {
                $type = 'date';
            }

            $result[] = [
                'order' => $column->Field,
                'label' => $column->Field,
                'type' => $type,
            ];
        }

        return $result;
    }
}
