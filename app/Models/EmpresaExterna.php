<?php

namespace App\Models;

use App\Traits\HasFillableWithTypes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

    class EmpresaExterna extends Model
{
    /** @use HasFactory<\Database\Factories\EmpresaExternaFactory> */
    use HasFactory;
    use HasFillableWithTypes;
    protected $fillable = ['nombre', 'actividad', 'direccion', 'numero_contacto'];

    public function ordenesProduccion()
    {
        return $this->hasMany(OrdenProduccion::class, 'empresa_externa_id');
    }
}
