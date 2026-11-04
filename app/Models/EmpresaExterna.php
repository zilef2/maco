<?php

namespace App\Models;

use App\Traits\HasFichaDeTercero;
use App\Traits\HasFillableWithTypes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

class EmpresaExterna extends Model
{
    /** @use HasFactory<\Database\Factories\EmpresaExternaFactory> */
    use HasFactory;
    use HasFillableWithTypes;
    use HasFichaDeTercero;

    protected $codigoColumna = 'codigo_empresa_externa';
    protected $codigoPrefijo = 'EMP';

    protected $fillable = [
        'codigo_empresa_externa',
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
        return $this->hasMany(OrdenProduccion::class, 'empresa_externa_id');
    }
}
