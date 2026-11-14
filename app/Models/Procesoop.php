<?php

namespace App\Models;

use App\Traits\HasFillableWithTypes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

    class Procesoop extends Model
{
    /** @use HasFactory<\Database\Factories\ProcesoopFactory> */
    use HasFactory;
    use HasFillableWithTypes;
    protected $fillable = ['nombre'];

    /**
     * Catálogo de procesos: un mismo proceso se reutiliza en varios productos y
     * el coste de ese proceso vive en el pivote, no en el catálogo.
     */
    public function productos()
    {
        return $this->belongsToMany(Producto::class, 'procesoop_producto', 'procesoop_id', 'producto_id')
            ->withPivot('coste_proceso')
            ->withTimestamps();
    }
}
