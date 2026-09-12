<?php

namespace App\Models;

use Database\Factories\PresupuestoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Presupuesto extends Model
{
    /** @use HasFactory<PresupuestoFactory> */
    use HasFactory;

    protected $fillable = [
        'nombre',
        'descripcion',
        'fecha_inicio',
        'fecha_fin',
        'monto_total',
        'monto_ejecutado',
        'monto_disponible',
        'total_salarios',
        'total_viaticos',
        'total_materiales',
        'estado',
        'user_id_aprobado',
        'aprobado_por',
        'fecha_aprobacion',
        'codigo_interno',
        'observaciones',
        'porcentaje_ejecutado',
        'umbral_alerta',
        'fecha_ultimo_reajuste',
        'motivo_reajuste',
    ];

    public static function getFillableWithTypes()
    {
        $table = (new static)->getTable();
        $columns = DB::select("SHOW COLUMNS FROM {$table}");

        $fillable = (new static)->getFillable();
        $result = [];

        foreach ($columns as $column) {
            if (!in_array($column->Field, $fillable) 
	            || $column->Field === 'id'
	            || $column->Field === 'cantidad_minutos'
            ) {
                continue;
            }

            // Detectar tipo
            $type = 'text'; // default
            if (str_contains($column->Type, 'int')) {
                $type = 'integer';
            } elseif (str_contains($column->Type, 'decimal') || str_contains($column->Type, 'float')) {
                $type = 'dinero';
            } elseif (str_contains($column->Type, 'date') || str_contains($column->Type, 'datetime')) {
                $type = 'date';
            } elseif (str_contains($column->Type, 'foreign') || $column->Field === 'oferta_id') {
                $type = 'foreign';
            }

            $result[] = [
                'order' => $column->Field,
                'label' => $column->Field,
                'type'  => $type,
            ];
        }

        return $result;
    }
}
