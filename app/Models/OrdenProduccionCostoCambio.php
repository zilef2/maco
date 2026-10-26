<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Historial de los precios que tuvo un material dentro de una OP.
 */
class OrdenProduccionCostoCambio extends Model
{
    use HasFactory;

    protected $table = 'orden_produccion_costos_cambios';

    protected $fillable = [
        'orden_produccion_id',
        'material_id',
        'metodo_costo',
        'precio_anterior',
        'precio_nuevo',
        'user_id',
    ];

    protected $casts = [
        'metodo_costo' => 'integer',
        'precio_anterior' => 'float',
        'precio_nuevo' => 'float',
    ];

    public function ordenProduccion()
    {
        return $this->belongsTo(OrdenProduccion::class, 'orden_produccion_id');
    }

    public function material()
    {
        return $this->belongsTo(Material::class, 'material_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
