<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

    class Distribucion_por_tallas extends Model
{
    /** @use HasFactory<\Database\Factories\DistribucionPorTallasFactory> */
    use HasFactory;
    protected $fillable = ['grupo', 'producto_id', 'talla', 'cantidad_planificada', 'cantidad_recibida_buena', 'cantidad_pendiente_reparacion'];

    /**
     * Producto al que pertenece esta talla dentro de una OP.
     */
    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function ordenesProduccion()
    {
        return $this->belongsToMany(OrdenProduccion::class, 'distribucion_orden_produccion', 'distribucion_por_talla_id', 'orden_produccion_id')->withTimestamps();
    }

    public function productos()
    {
        return $this->belongsToMany(Producto::class, 'distribucion_producto', 'distribucion_por_talla_id', 'producto_id')->withTimestamps();
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
