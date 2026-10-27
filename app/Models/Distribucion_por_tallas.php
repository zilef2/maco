<?php

namespace App\Models;

use App\Traits\HasFillableWithTypes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

    class Distribucion_por_tallas extends Model
{
    /** @use HasFactory<\Database\Factories\DistribucionPorTallasFactory> */
    use HasFactory;
    use HasFillableWithTypes;
    protected $fillable = ['grupo', 'producto_id', 'material_id', 'talla', 'cantidad_planificada', 'cantidad_recibida_buena', 'cantidad_pendiente_reparacion'];

    /**
     * Producto al que pertenece esta talla dentro de una OP.
     */
    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    /**
     * Material procesado al que pertenece esta fila cuando la OP produce
     * materiales en lugar de productos.
     */
    public function material()
    {
        return $this->belongsTo(Material::class, 'material_id');
    }

    public function ordenesProduccion()
    {
        return $this->belongsToMany(OrdenProduccion::class, 'distribucion_orden_produccion', 'distribucion_por_talla_id', 'orden_produccion_id')->withTimestamps();
    }

    public function productos()
    {
        return $this->belongsToMany(Producto::class, 'distribucion_producto', 'distribucion_por_talla_id', 'producto_id')->withTimestamps();
    }
}
