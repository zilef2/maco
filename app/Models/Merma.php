<?php

namespace App\Models;

use App\Traits\HasFillableWithTypes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

    class Merma extends Model
{
    /** @use HasFactory<\Database\Factories\MermaFactory> */
    use HasFactory;
    use HasFillableWithTypes;
    protected $fillable = [
        'orden_produccion_id',
        'materia_prima_id',
        'cantidad',
        'motivo',
        'fecha_registro',
        'producto_referencia_id',
        'talla',
        'recepcion_calidad_id',
        'proceso_externo_id',
        'porcentaje_merma',
        'costo_unitario',
        'costo_total_perdida',
        'es_recuperable',
        'cantidad_recuperada',
        'responsable_id',
        'taller_proveedor_id',
        'observaciones'
    ];
}
