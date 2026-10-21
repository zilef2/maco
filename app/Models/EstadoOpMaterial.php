<?php

namespace App\Models;

use App\Traits\HasFillableWithTypes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

    class EstadoOpMaterial extends Model
{
    /** @use HasFactory<\Database\Factories\EstadoOpMaterialFactory> */
    use HasFactory;
    use HasFillableWithTypes;
    protected $fillable = ['nombre', 'valor', 'pasa_a'];
}
