<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ListaDesplegable extends Model
{
    use HasFactory;

    protected $table = 'listas_desplegables';

    protected $fillable = [
        'nombre',
        'categoria',
        'subcategoria',
    ];
}
