<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Parametro extends Model
{
    use HasFactory;

    /** Muestra en el cambio de estado a «En taller» cuánto se resta. */
    public const MOSTRAR_RESTA_MATERIALES_TALLER = 'mostrar_resta_materiales_taller';

    protected $fillable = [
        'nombre',
        'valor',
        'valor2',
        'valor_date',
        'valor_int',
        'valor_double',
        'valor_string1m',
    ];

    /**
     * Lee un parámetro booleano guardado en `valor` ("1"/"0", "true"/"false",
     * "si"/"no"). Si no existe, devuelve el valor por defecto.
     */
    public static function bool(string $nombre, bool $default = false): bool
    {
        $valor = static::where('nombre', $nombre)->value('valor');

        if ($valor === null) {
            return $default;
        }

        return in_array(strtolower(trim((string) $valor)), ['1', 'true', 'on', 'si', 'sí', 'verdadero'], true);
    }
}
