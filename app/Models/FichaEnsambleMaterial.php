<?php

namespace App\Models;

use App\Traits\HasFillableWithTypes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FichaEnsambleMaterial extends Model
{
    /** @use HasFactory<\Database\Factories\FichaEnsambleMaterialFactory> */
    use HasFactory;
    use HasFillableWithTypes;
    protected $fillable = [
        'ficha_ensamble_id',
        'material_id',
        'cantidad',
        'unidad_medida',
        'aporta',
        'variante_especificacion',
        'proceso_previo',
        'verificado',
        'observaciones',
    ];

}
