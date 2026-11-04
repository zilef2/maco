<?php

namespace App\Models;

use App\Traits\HasFichaDeTercero;
use App\Traits\HasFillableWithTypes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

class Taller extends Model
{
    /** @use HasFactory<\Database\Factories\TallerFactory> */
    use HasFactory;
    use HasFillableWithTypes;
    use HasFichaDeTercero;

    protected $codigoColumna = 'codigo_taller';
    protected $codigoPrefijo = 'TAL';

    protected $fillable = [
        'codigo_taller',
        'nit',
        'nombre_comercial',
        'nombre',
        'razon_social',
        'direccion',
        'observaciones_direccion',
        'correo_electronico',
        'ciudad',
        'telefono_principal',
        'tipo_proveedor',
        'que_suministra',
        'persona_contacto',
        'celular_contacto',
        'forma_pago',
        'tiempo_entrega',
        'estado',
        'observaciones_generales',
    ];

    public function ordenesProduccion()
    {
        return $this->hasMany(OrdenProduccion::class, 'taller_id');
    }
}
