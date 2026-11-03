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
    protected $fillable = ['nombre', 'producto_id', 'coste_proceso'];

    protected $casts = [
        'coste_proceso' => 'float',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}
