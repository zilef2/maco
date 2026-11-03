<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Merma de producción: los pares que quedaron «No recuperable» en la recepción
 * de calidad de una OP. No se captura a mano: la genera
 * App\Services\MermaRecepcionService desde la recepción.
 */
class Merma extends Model
{
    /** @use HasFactory<\Database\Factories\MermaFactory> */
    use HasFactory;

    protected $fillable = [
        'orden_produccion_id',
        'recepcion_calidad_id',
        'producto_referencia_id',
        'material_referencia_id',
        'talla',
        'cantidad',
        'fecha_registro',
        'costo_unitario',
        'costo_total_perdida',
        'responsable_id',
        'observaciones',
    ];

    protected $casts = [
        'fecha_registro' => 'datetime',
        'cantidad' => 'integer',
        'costo_unitario' => 'decimal:2',
        'costo_total_perdida' => 'decimal:2',
    ];

    public function ordenProduccion(): BelongsTo
    {
        return $this->belongsTo(OrdenProduccion::class, 'orden_produccion_id');
    }

    public function recepcionCalidad(): BelongsTo
    {
        return $this->belongsTo(RecepcionCalidad::class, 'recepcion_calidad_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_referencia_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_referencia_id');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    /** Qué se perdió: el producto de la talla, o el material en una OP de materiales. */
    public function objetivoNombre(): string
    {
        if ($this->material) {
            return trim(($this->material->codigo_material ? $this->material->codigo_material . ' — ' : '')
                . $this->material->nombre_base);
        }

        return $this->producto?->Titulo_Shopify
            ?: ($this->producto?->Codigo_interno
                ?: ('Objetivo #' . ($this->producto_referencia_id ?? $this->material_referencia_id ?? '?')));
    }
}
