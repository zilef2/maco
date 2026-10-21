<?php

namespace App\Models;

use App\Traits\HasFillableWithTypes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

    class FichaEnsamble extends Model
{
    /** @use HasFactory<\Database\Factories\FichaEnsambleFactory> */
    use HasFactory;
    use HasFillableWithTypes;
    protected $fillable = ['orden_produccion_id', 'producto_referencia_id', 'taller_id', 'cantidad_total', 'fecha_entrega', 'coleccion', 'imagen_referencia_url', 'instrucciones_especiales'];
}
