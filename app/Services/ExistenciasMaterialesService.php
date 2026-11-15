<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Existencias y precios de materiales para el entorno de prueba: dejar todo en
 * cero o dar valor a lo que nunca lo tuvo.
 */
class ExistenciasMaterialesService
{
    /** Existencia que se asigna a un material que nunca tuvo existencia. */
    public const EXISTENCIA_MINIMA = 50;

    public const EXISTENCIA_ALEATORIA = 450;

    /** Precio que se registra a un material que no tiene ninguna fila de precio. */
    public const PRECIO_MINIMO = 1_000;

    public const PRECIO_ALEATORIO = 4_000;

    /** Rango de la existencia al azar, para que la vista lo muestre sin copiarlo. */
    public static function rangoExistencia(): string
    {
        return self::EXISTENCIA_MINIMA.'–'.(self::EXISTENCIA_MINIMA + self::EXISTENCIA_ALEATORIA);
    }

    public static function rangoPrecio(): string
    {
        return number_format(self::PRECIO_MINIMO, 0, ',', '.')
            .'–'.number_format(self::PRECIO_MINIMO + self::PRECIO_ALEATORIO, 0, ',', '.');
    }

    public function ponerEnCero(): int
    {
        // Los genéricos no llevan inventario: se dejan fuera para no escribirle
        // un 0 a la columna que la migración dejó en null.
        return $this->ejecutar(
            'UPDATE materials SET existencia_actual = 0 WHERE existencia_actual <> 0 AND generico = 0'
        );
    }

    /**
     * @return array{existencias: int, precios: int}
     */
    public function rellenarAleatorio(): array
    {
        // El RAND() va del lado de la base: en PHP habría que hacer un UPDATE por
        // material, y con miles de filas eso es una petición por fila. La fecha sí
        // viene de PHP porque la app guarda en UTC y el reloj de MySQL es local.
        $existencias = $this->ejecutar(
            'UPDATE materials
                SET existencia_actual = ROUND(? + RAND() * ?, 0)
              WHERE existencia_actual = 0
                AND generico = 0',
            [self::EXISTENCIA_MINIMA, self::EXISTENCIA_ALEATORIA]
        );

        $precios = $this->ejecutar(
            'INSERT INTO material_prices (material_id, precio, fecha, created_at, updated_at)
             SELECT id, ROUND(? + RAND() * ?, 0), ?, ?, ?
               FROM materials
              WHERE NOT EXISTS (SELECT 1 FROM material_prices mp WHERE mp.material_id = materials.id)',
            [
                self::PRECIO_MINIMO,
                self::PRECIO_ALEATORIO,
                now()->toDateString(),
                now(),
                now(),
            ]
        );

        return ['existencias' => $existencias, 'precios' => $precios];
    }

    private function ejecutar(string $sql, array $ligaduras = []): int
    {
        if (config('app.env') !== 'test' && !app()->environment('test')) {
            throw new RuntimeException('Estos ajustes de materiales solo están permitidos en entorno test.');
        }

        return DB::affectingStatement($sql, $ligaduras);
    }
}
