<?php

namespace App\Models;

use App\Traits\HasFillableWithTypes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

    class KitProceso extends Model
{
    /** @use HasFactory<\Database\Factories\KitProcesoFactory> */
    use HasFactory;
    use HasFillableWithTypes;
    protected $fillable = ['orden_produccion_id', 'material_id', 'proveedor_id', 'estado_requerido', 'unidad', 'cantidad_requerida', 'listo', 'cantidad_disponible', 'cantidad_preparada', 'falta', 'observaciones'];

    protected $casts = [
        'listo' => 'boolean',
        'cantidad_requerida' => 'float',
        'cantidad_disponible' => 'float',
        'cantidad_preparada' => 'float',
        'falta' => 'float',
    ];

    /**
     * El "falta" y el "listo" no se preguntan al usuario: se derivan de las
     * cantidades. Falta = existencia del material - cantidad preparada; un
     * registro está listo cuando lo preparado cubre lo requerido.
     */
    protected static function booted(): void
    {
        static::saving(function (KitProceso $kit) {
            $requerida = (float) ($kit->cantidad_requerida ?? 0);
            $preparada = (float) ($kit->cantidad_preparada ?? 0);
            $disponible = (float) ($kit->cantidad_disponible ?? 0);

            $kit->falta = $disponible - $preparada;
            $kit->listo = $requerida > 0 && $preparada >= $requerida;
        });
    }

    public function ordenProduccion()
    {
        return $this->belongsTo(OrdenProduccion::class, 'orden_produccion_id');
    }

    public function material()
    {
        return $this->belongsTo(Material::class, 'material_id');
    }

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }
}
