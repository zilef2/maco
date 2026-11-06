<?php

namespace App\Models;

use App\Traits\HasFillableWithTypes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

    class EstadoOperacion extends Model
{
    /** @use HasFactory<\Database\Factories\EstadoOperacionFactory> */
    use HasFactory;
    use HasFillableWithTypes;
    protected $fillable = ['nombre', 'cuando_aplica', 'regla_clave', 'puede_pasar_a'];

    /**
     * OPs que tienen este estado como estado principal.
     */
    public function ordenesProduccion()
    {
        return $this->hasMany(OrdenProduccion::class, 'estado_op_id');
    }

    /**
     * Ids de estados a los que se puede transicionar desde el estado actual,
     * según la lista "nombre / nombre / ..." de la columna puede_pasar_a.
     */
    public function idsPuedePasarA(): array
    {
        if (empty($this->puede_pasar_a)) {
            return [];
        }
        $nombres = array_map('trim', explode('/', $this->puede_pasar_a));
        return EstadoOperacion::whereIn('nombre', $nombres)->pluck('id')->all();
    }
}
