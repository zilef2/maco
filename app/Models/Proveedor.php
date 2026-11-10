<?php

namespace App\Models;

use Database\Factories\ProveedorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proveedor extends Tercero
{
    /** @use HasFactory<ProveedorFactory> */
    use HasFactory;

    protected static $tipoFicha = 'Proveedor';

    public function materialesHabituales(): HasMany
    {
        return $this->hasMany(Material::class, 'proveedor_habitual');
    }

    public function kitProcesos(): HasMany
    {
        return $this->hasMany(KitProceso::class, 'proveedor_id');
    }
}
