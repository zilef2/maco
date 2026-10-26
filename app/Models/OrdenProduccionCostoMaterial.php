<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Precio de un material tal como quedó guardado en una OP.
 */
class OrdenProduccionCostoMaterial extends Model
{
    use HasFactory;

    protected $table = 'orden_produccion_costos_materiales';

    protected $fillable = [
        'orden_produccion_id',
        'material_id',
        'metodo_costo',
        'precio_unitario',
        'cantidad',
        'costo_total',
        'user_id',
    ];

    protected $casts = [
        'metodo_costo' => 'integer',
        'precio_unitario' => 'float',
        'cantidad' => 'float',
        'costo_total' => 'float',
    ];

    public function ordenProduccion()
    {
        return $this->belongsTo(OrdenProduccion::class, 'orden_produccion_id');
    }

    public function material()
    {
        return $this->belongsTo(Material::class, 'material_id');
    }
}
