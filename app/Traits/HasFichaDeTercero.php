<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Ficha común de los terceros con los que se trabaja: proveedor, taller y
 * empresa externa comparten los mismos campos, los mismos catálogos y el
 * mismo comportamiento del código.
 *
 * Las tres entidades viven en una sola tabla y cada modelo declara su tipo en
 * `$tipoFicha`: eso agrega un filtro global y sella la columna `tipo` al crear.
 */
trait HasFichaDeTercero
{
    public const TIPOS_FICHA = [
        'Proveedor',
        'Taller',
        'Empresa externa',
    ];

    public const PREFIJOS_FICHA = [
        'Proveedor' => 'PRV',
        'Taller' => 'TAL',
        'Empresa externa' => 'EMP',
    ];

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
     * en qué tipo termina cada fila, pero no se guardan en la tabla.
     */
    public const CLASIFICACIONES = [
        'proveedor',
        'taller de proceso externo',
        'macondas',
    ];

    protected static $tipoFicha = null;

    protected static function bootHasFichaDeTercero(): void
    {
        $tipoFicha = static::$tipoFicha;

        if ($tipoFicha !== null) {
            static::addGlobalScope('tipo_ficha', function (Builder $builder) use ($tipoFicha): void {
                $builder->where($builder->getModel()->qualifyColumn('tipo'), $tipoFicha);
            });

            static::creating(function (Model $modelo) use ($tipoFicha): void {
                $modelo->tipo = $tipoFicha;
            });
        }

        static::saving(function (Model $modelo) use ($tipoFicha): void {
            // `saving` se dispara antes de `creating`: el tipo se sella aquí
            // para que el código se genere con el prefijo correcto.
            if ($tipoFicha !== null) {
                $modelo->tipo = $tipoFicha;
            }

            // `nombre` es la columna que leen los desplegables de toda la app:
            // se mantiene espejada al nombre comercial.
            if (!empty($modelo->nombre_comercial)) {
                $modelo->nombre = $modelo->nombre_comercial;
            } elseif (empty($modelo->nombre)) {
                $modelo->nombre = $modelo->razon_social ?: 'Sin nombre';
            }

            $columnaCodigo = $modelo->codigoColumna;

            if (empty($modelo->{$columnaCodigo})) {
                $tipo = $modelo->tipo ?: self::TIPOS_FICHA[0];
                $modelo->tipo = $tipo;

                // Se toma el mayor número, no el de la última fila: los datos
                // migrados conservan códigos propios y su id no sigue el orden
                // del consecutivo. En cargas masivas la generación se hace una
                // sola vez desde el servicio de Excel.
                $maximo = 0;
                $codigos = static::query()
                    ->where('tipo', $tipo)
                    ->whereNotNull($columnaCodigo)
                    ->pluck($columnaCodigo);

                foreach ($codigos as $codigo) {
                    $maximo = max($maximo, (int) preg_replace('/\D+/', '', (string) $codigo));
                }

                $numero = $maximo + 1;
                $modelo->{$columnaCodigo} = (self::PREFIJOS_FICHA[$tipo] ?? 'PRV')
                    . '-' . str_pad((string) $numero, 3, '0', STR_PAD_LEFT);
            }
        });
    }
}
