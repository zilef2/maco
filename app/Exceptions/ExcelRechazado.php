<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Archivo de Excel rechazado, con la lista completa de los problemas.
 *
 * El mensaje es lo que lee el usuario en la pantalla y en el correo, por eso
 * viene recortado; `errores()` guarda todos los que se encontraron, que es lo
 * que se escribe en storage/logs/MPP.log para corregir el archivo de una sola
 * pasada en vez de volver a subirlo por cada fila mala.
 */
class ExcelRechazado extends RuntimeException
{
    /** @var string[] */
    private array $errores = [];

    /**
     * @param  string[]  $errores  Un elemento por problema, en la forma corta
     *                            (sin explicaciones largas ni traza)
     */
    public static function conErrores(string $mensaje, array $errores, ?Throwable $anterior = null): self
    {
        $excepcion = new self($mensaje, 0, $anterior);
        $excepcion->errores = array_values(array_unique(array_filter(
            array_map('trim', $errores),
            fn (string $error): bool => $error !== '',
        )));

        return $excepcion;
    }

    /**
     * @return string[]
     */
    public function errores(): array
    {
        return $this->errores;
    }
}
