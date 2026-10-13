<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

    class ProcesoExterno extends Model
{
    /** @use HasFactory<\Database\Factories\ProcesoExternoFactory> */
    use HasFactory;
    protected $fillable = ['orden_produccion_id', 'etapa_componente', 'material_origen_id', 'cantidad_salida', 'unidad_medida', 'bodega_origen', 'proveedor_destino_id', 'servicio_proceso', 'fecha_salida', 'fecha_prevista_regreso', 'resultado_observaciones', 'fecha_regreso_real'];

    public function materialOrigen(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(materias_primas::class, 'material_origen_id');
    }

    public function ordenProduccion(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(OrdenProduccion::class, 'orden_produccion_id');
    }

    public function proveedorDestino(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_destino_id');
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
