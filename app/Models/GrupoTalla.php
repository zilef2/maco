<?php

namespace App\Models;

use App\Traits\HasFillableWithTypes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

    class GrupoTalla extends Model
{
    /** @use HasFactory<\Database\Factories\GrupoTallaFactory> */
    use HasFactory;
    use HasFillableWithTypes;
    protected $fillable = ['nombre', 'valor'];
}
