<?php

namespace App\Models;

use App\Traits\HasFichaDeTercero;
use App\Traits\HasFillableWithTypes;
use Database\Factories\ProveedorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proveedor extends Model
{
    /** @use HasFactory<ProveedorFactory> */
    use HasFactory;
    use HasFillableWithTypes;
    use HasFichaDeTercero;

    protected $codigoColumna = 'codigo_proveedor';
    protected $codigoPrefijo = 'PRV';

    protected $fillable = [
        'codigo_proveedor',
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

    public function materialesHabituales(): HasMany
    {
        return $this->hasMany(Material::class, 'proveedor_habitual');
    }

    public function kitProcesos(): HasMany
    {
        return $this->hasMany(KitProceso::class, 'proveedor_id');
    }
}
