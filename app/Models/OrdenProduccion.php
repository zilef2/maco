<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

    class OrdenProduccion extends Model
{
    /** @use HasFactory<\Database\Factories\OrdenProduccionFactory> */
    use HasFactory;
    protected $fillable = ['numero_op', 'producto_referencia_id', 'cantidad_planificada', 'fecha_creacion', 'fecha_prevista_envio_taller', 'taller_id', 'estado_op_id', 'user_id', 'id_shopify_producto', 'observaciones'];

    public function productoReferencia()
    {
        return $this->belongsTo(Producto::class, 'producto_referencia_id');
    }

    public function productos()
    {
        return $this->belongsToMany(Producto::class, 'orden_produccion_producto')->withTimestamps();
    }

    public function taller()
    {
        return $this->belongsTo(Taller::class, 'taller_id');
    }

    public function estadoOp()
    {
        return $this->belongsTo(EstadoOperacion::class, 'estado_op_id');
    }

    public function responsable()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function distribucionesPorTallas()
    {
        return $this->belongsToMany(Distribucion_por_tallas::class, 'distribucion_orden_produccion', 'orden_produccion_id', 'distribucion_por_talla_id')->withTimestamps();
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
