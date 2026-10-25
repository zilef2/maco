<?php

namespace App\Models;

use App\Traits\HasFillableWithTypes;

use Database\Factories\MaterialFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{
    /** @use HasFactory<MaterialFactory> */
    use HasFactory;
    use HasFillableWithTypes;

    protected $fillable = ['codigo_material',
        'categoria',
        'nombre_base',
        'unidad_inventario',
        'existencia_actual',
        'maneja_color',
        'maneja_talla',
        'maneja_otra_variante',
        'estado',
        'variante_atributo_sugerido',
        'proveedor_habitual',
        'material_procesado',
        'observaciones'];

    public function movimientos(): HasMany
    {
        return $this->hasMany(MaterialMovimiento::class, 'material_id');
    }

    public function proveedorHabitual()
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_habitual');
    }

    public function prices()
    {
        return $this->hasMany(MaterialPrice::class, 'material_id')->orderBy('fecha', 'desc');
    }

    public function lastPrice()
    {
        return $this->hasOne(MaterialPrice::class, 'material_id')->orderBy('fecha', 'desc');
    }

    protected static array $booleanFields = [
        'maneja_color',
        'maneja_talla',
        'maneja_otra_variante',
        'material_procesado',
    ];
}
