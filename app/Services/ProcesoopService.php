<?php

namespace App\Services;

use App\Models\Procesoop;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Catálogo de procesos. Un proceso solo guarda su nombre: el coste vive en el
 * pivote con cada producto (procesoop_producto.coste_proceso).
 *
 * El nombre es único y, además, se avisa y bloquea cuando es demasiado parecido
 * a uno ya registrado (p. ej. «capellada» frente a «capelada»).
 */
class ProcesoopService
{
    /** Similitud mínima (0-1) para considerar dos nombres la misma cosa con otra grafía. */
    private const SIMILITUD_MINIMA = 0.8;

    /** Bajo esta longitud los nombres cortos no se comparan por similitud. */
    private const LONGITUD_MINIMA = 4;

    public function crear(array $datos): Procesoop
    {
        $nombre = $this->nombreValido($datos['nombre'] ?? null);

        $this->validarNombreDisponible($nombre);

        return Procesoop::create(['nombre' => $nombre]);
    }

    public function actualizar(Procesoop $procesoop, array $datos): Procesoop
    {
        $nombre = $this->nombreValido($datos['nombre'] ?? null);

        $this->validarNombreDisponible($nombre, $procesoop->id);

        $procesoop->update(['nombre' => $nombre]);

        return $procesoop;
    }

    /**
     * Proceso ya existente que coincide o es demasiado parecido al propuesto.
     */
    public function similar(string $nombre, ?int $exceptoId = null): ?Procesoop
    {
        $objetivo = $this->normalizar($nombre);
        if ($objetivo === '') {
            return null;
        }

        $longitud = mb_strlen($objetivo);

        foreach ($this->catalogo($exceptoId) as $procesoop) {
            $existente = $this->normalizar($procesoop->nombre);
            if ($existente === '') {
                continue;
            }

            if ($existente === $objetivo) {
                return $procesoop;
            }

            if ($longitud < self::LONGITUD_MINIMA || mb_strlen($existente) < self::LONGITUD_MINIMA) {
                continue;
            }

            $mayor = max($longitud, mb_strlen($existente));
            $similitud = 1 - (levenshtein($objetivo, $existente) / $mayor);

            if ($similitud >= self::SIMILITUD_MINIMA) {
                return $procesoop;
            }
        }

        return null;
    }

    private function validarNombreDisponible(string $nombre, ?int $exceptoId = null): void
    {
        $similar = $this->similar($nombre, $exceptoId);

        if (!$similar) {
            return;
        }

        $esMismo = $this->normalizar($similar->nombre) === $this->normalizar($nombre);

        throw ValidationException::withMessages([
            'nombre' => $esMismo
                ? "El proceso «{$similar->nombre}» ya existe."
                : "El nombre «{$nombre}» es muy parecido al proceso «{$similar->nombre}».",
        ]);
    }

    private function nombreValido($nombre): string
    {
        $nombre = trim((string) $nombre);

        if ($nombre === '') {
            throw ValidationException::withMessages([
                'nombre' => 'El nombre del proceso es obligatorio.',
            ]);
        }

        return $nombre;
    }

    /**
     * Nombres del catálogo, excluyendo el propio al editar.
     *
     * @return \Illuminate\Support\Collection<int, Procesoop>
     */
    private function catalogo(?int $exceptoId)
    {
        return Procesoop::query()
            ->when($exceptoId, fn ($query) => $query->where('id', '!=', $exceptoId))
            ->get(['id', 'nombre']);
    }

    /** Nombre comparable: sin mayúsculas, sin acentos y con espacios simples. */
    private function normalizar(string $nombre): string
    {
        $nombre = Str::ascii(trim($nombre));

        return preg_replace('/\s+/', ' ', mb_strtolower($nombre)) ?? mb_strtolower($nombre);
    }
}
