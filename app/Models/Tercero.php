<?php

namespace App\Models;

use App\Traits\HasFichaDeTercero;
use App\Traits\HasFillableWithTypes;
use Illuminate\Database\Eloquent\Model;

/**
 * Tabla unificada de terceros. `tipo` distingue proveedor, taller y empresa
 * externa; los modelos hijos agregan ese filtro de forma automática.
 */
class Tercero extends Model
{
    use HasFichaDeTercero;
    use HasFillableWithTypes;

    protected $table = 'proveedores_maco';

    protected $codigoColumna = 'codigo';

    protected $fillable = [
        'tipo',
        'codigo',
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
        'categoria',
        'celular1',
        'celular2',
    ];
}
