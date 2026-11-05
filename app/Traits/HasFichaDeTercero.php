<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;

/**
 * Ficha común de los terceros con los que se trabaja: proveedor, taller y
 * empresa externa comparten los mismos campos, los mismos catálogos y el
 * mismo comportamiento del código.
 *
 * Los modelos que lo usan declaran qué columna guarda el código y con qué
 * prefijo se genera: `protected $codigoColumna` y `$codigoPrefijo`.
 */
trait HasFichaDeTercero
{
    public const TIPOS_PROVEEDOR = [
        'Materia prima',
        'Servicio',
        'Mixto',
        'Otro',
    ];

    public const ESTADOS = [
        'Activo',
        'Inactivo',
    ];

    public const FORMAS_PAGO = [
        'Contado',
        'Crédito 8 días',
        'Crédito 15 días',
        'Crédito 30 días',
        'Anticipo',
        'Otro',
    ];

    /**
     * Valores de la columna "proveedor o externa o macondas" del Excel: deciden
     * en qué entidad termina cada fila, pero no se guardan en ninguna tabla.
     */
    public const CLASIFICACIONES = [
        'proveedor',
        'taller de proceso externo',
        'macondas',
    ];

    protected static function bootHasFichaDeTercero(): void
    {
        static::saving(function (Model $modelo): void {
            // `nombre` es la columna que leen los desplegables de toda la app:
            // se mantiene espejada al nombre comercial para que un taller o una
            // empresa externa aparezca con el mismo texto en su listado y en la OP.
            if (!empty($modelo->nombre_comercial)) {
                $modelo->nombre = $modelo->nombre_comercial;
            } elseif (empty($modelo->nombre)) {
                $modelo->nombre = $modelo->razon_social ?: 'Sin nombre';
            }

            $columnaCodigo = $modelo->codigoColumna;

            if (empty($modelo->{$columnaCodigo})) {
                // Se ordena por la clave primaria, no por el texto del código:
                // así "EMP-1000" no queda antes que "EMP-999". En cargas masivas
                // la generación se hace una sola vez desde el servicio de Excel.
                $ultimo = static::query()
                    ->whereNotNull($columnaCodigo)
                    ->orderByDesc('id')
                    ->value($columnaCodigo);

                $numero = ((int) preg_replace('/\D+/', '', (string) $ultimo)) + 1;
                $modelo->{$columnaCodigo} = $modelo->codigoPrefijo
                    . '-' . str_pad((string) $numero, 3, '0', STR_PAD_LEFT);
            }
        });
    }
}
