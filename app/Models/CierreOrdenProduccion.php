<?php

namespace App\Models;

use App\Traits\HasFillableWithTypes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

    class CierreOrdenProduccion extends Model
{
    /** @use HasFactory<\Database\Factories\CierreOrdenProduccionFactory> */
    use HasFactory;
    use HasFillableWithTypes;
    protected $fillable = [
        'orden_produccion_id',
        'perdidas_mermas',
        'costo_real_final',
        'completado',
        'control_cierre',
        'dato_esperado',
        'dato_real',
        'diferencia',
        'estado_accion',
        'responsable_id',
        'fecha_cierre',
        'observaciones',
    ];

    protected $casts = [
        'completado' => 'boolean',
        'perdidas_mermas' => 'decimal:2',
        'costo_real_final' => 'decimal:2',
        'fecha_cierre' => 'datetime',
    ];

    public function ordenProduccion()
    {
        return $this->belongsTo(OrdenProduccion::class, 'orden_produccion_id');
    }

    public function responsable()
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }
}
