<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Parametro extends Model
{
    use HasFactory;

    /** Muestra en el cambio de estado a «En taller» cuánto se resta. */
    public const MOSTRAR_RESTA_MATERIALES_TALLER = 'mostrar_resta_materiales_taller';

    /** 1 = promedio de los precios del material, 2 = último precio digitado. */
    public const COSTOS_PROMEDIO = 'costos_promedio';

    public const COSTOS_PROMEDIO_PROMEDIO = 1;

    public const COSTOS_PROMEDIO_ULTIMO = 2;

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

    /**
     * Lee un parámetro entero guardado en `valor_int`. Si no existe o no es un
     * número, devuelve el valor por defecto.
     *
     * @param  list<int>  $permitidos
     */
    public static function entero(string $nombre, int $default = 0, array $permitidos = []): int
    {
        $valor = static::where('nombre', $nombre)->value('valor_int');

        if ($valor === null || !is_numeric($valor)) {
            return $default;
        }

        $entero = (int) $valor;

        if (!empty($permitidos) && !in_array($entero, $permitidos, true)) {
            return $default;
        }

        return $entero;
    }

    /**
     * Guarda un parámetro entero creando el registro si todavía no existe.
     */
    public static function guardarEntero(string $nombre, int $valor): self
    {
        $parametro = static::firstOrNew(['nombre' => $nombre]);
        $parametro->valor_int = $valor;
        $parametro->save();

        return $parametro;
    }
}
