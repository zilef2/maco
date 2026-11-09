<?php

namespace App\Models;

use App\Traits\HasFillableWithTypes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

    class Compromisomaterial extends Model
{
    /** @use HasFactory<\Database\Factories\CompromisomaterialFactory> */
    use HasFactory;
    use HasFillableWithTypes;
    protected $fillable = ['material_id', 'op_origen_id', 'op_destino_id', 'cantidad', 'estado'];

}
