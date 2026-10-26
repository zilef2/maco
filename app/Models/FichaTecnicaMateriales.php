<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FichaTecnicaMateriales extends Model
{
    /** @use HasFactory<\Database\Factories\FichaTecnicaMaterialesFactory> */
    use HasFactory;

    protected $fillable = [
        'ficha_tecnica_id', 
        'material_componente_id',
        'aporta', //
        'estado_al_llegar_ensamble',
        'unidad_medida',
        'consumo_estandar',
        'costo_asociado_teorico',
        'costo_asociado_real',
        'fecha_inicio_vigencia',
        'proceso_previo',
        'observaciones',
        'fecha_fin_vigencia',
    ];

    protected $appends = ['costo_total'];

    /**
     * Costo de la línea: costo unitario del material × consumo estándar.
     */
    public function getCostoTotalAttribute(): float
    {
        return (float) $this->consumo_estandar * (float) $this->costo_asociado_teorico;
    }

    public function fichaTecnica()
    {
        return $this->belongsTo(FichaTecnica::class);
    }

    public function material()
    {
        return $this->belongsTo(Material::class, 'material_componente_id');
    }
}
