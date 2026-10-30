<?php

namespace App\Http\Middleware;

use App\Models\ActividadRegistro;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Escribe una fila en actividad_registros por cada petición autenticada.
 *
 * Se hace después de responder (no antes) para poder registrar el status y la
 * duración reales, y el registro nunca puede tumbar la petición: cualquier
 * falla de la bitácora se traga en silencio.
 */
class RegistrarActividad
{
    /** Rutas que no son navegación de negocio sino pollings internos. */
    private const IGNORAR_RUTAS = [
        'dashboard.datos',
    ];

    private const IGNORAR_PREFIJOS = [
        '_debugbar',
        'telescope',
        'horizon',
        '_nightwatch',
        'notificaciones',
        'up',
        'favicon.ico',
        'hot',
        'build',
        'storage',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $inicio = microtime(true);
        $respuesta = $next($request);

        try {
            $this->registrar($request, $respuesta, $inicio);
        } catch (Throwable) {
            // La bitácora no es crítica: si falla, la petición sigue viva.
        }

        return $respuesta;
    }

    private function registrar(Request $request, Response $respuesta, float $inicio): void
    {
        $usuario = $request->user();
        $ruta = $request->route();

        if ($usuario === null || $ruta === null) {
            return;
        }

        $nombre = $ruta->getName();
        $uri = $request->path();

        if (in_array($nombre, self::IGNORAR_RUTAS, true)) {
            return;
        }

        foreach (self::IGNORAR_PREFIJOS as $prefijo) {
            if (str_starts_with($uri, $prefijo)) {
                return;
            }
        }

        [$modulo, $accion] = $this->moduloYAccion($request, $nombre);

        ActividadRegistro::create([
            'user_id' => $usuario->id,
            'nombre_usuario' => $usuario->name,
            'ruta_nombre' => $nombre,
            'ruta_uri' => mb_substr($uri, 0, 191),
            'metodo' => $request->method(),
            'modulo' => $modulo,
            'accion' => $accion,
            'controlador' => $this->controlador($ruta),
            'status' => $respuesta->getStatusCode(),
            'duracion_ms' => (int) round((microtime(true) - $inicio) * 1000),
            'ip' => $request->ip(),
            'created_at' => now(),
        ]);
    }

    /**
     * Módulo (a qué pantalla pertenece) y acción (qué hizo), que es lo que
     * permite leer el camino: índice → creación → edición → borrado.
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function moduloYAccion(Request $request, ?string $nombre): array
    {
        if ($nombre === 'login') {
            return ['Auth', $request->isMethod('POST') ? ActividadRegistro::ACCION_LOGIN : 'formulario'];
        }

        if ($nombre === 'logout') {
            return ['Auth', ActividadRegistro::ACCION_LOGOUT];
        }

        $accionRuta = $this->accionDeRuta($request);
        if ($accionRuta !== [null, null]) {
            return $accionRuta;
        }

        if ($nombre !== null && str_contains($nombre, '.')) {
            [$prefijo, $sufijo] = explode('.', $nombre, 2);

            return [$prefijo, $sufijo];
        }

        return [$nombre, 'vista'];
    }

    /**
     * Controlador@método: el origen más confiable del módulo y la acción,
     * porque las rutas del proyecto no siguen siempre la convención de nombre.
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function accionDeRuta(Request $request): array
    {
        $accion = $request->route()->getAction('controller');

        if (!is_string($accion) || !str_contains($accion, '@')) {
            return [null, null];
        }

        [$clase, $metodo] = explode('@', $accion, 2);
        $nombreCorto = substr($clase, strrpos($clase, '\\') + 1);

        return [str_replace('Controller', '', $nombreCorto), $metodo];
    }

    private function controlador(mixed $ruta): ?string
    {
        $accion = method_exists($ruta, 'getAction') ? $ruta->getAction('controller') : null;

        if (!is_string($accion)) {
            return null;
        }

        return mb_substr($accion, 0, 191);
    }
}
