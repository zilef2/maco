<?php

namespace App\Http\Middleware;

use App\Models\ActividadRegistro;
use App\Services\Bitacora;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;
use Symfony\Component\HttpFoundation\HttpExceptionInterface;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

/**
 * Escribe una fila en actividad_registros por cada petición, incluso cuando
 * terminó en excepción y también cuando el visitante aún no tiene usuario.
 *
 * Se hace después de responder (no antes) para poder registrar el status y la
 * duración reales. Las excepciones se atrapan para bitacorearlas y se vuelven a
 * lanzar: sin eso, un guardado rechazado por validación no dejaba ninguna
 * fila, que es justo la acción que más interesa ver en el camino del usuario.
 */
class RegistrarActividad
{
    /** Rutas que no son navegación de negocio sino pollings internos. */
    private const IGNORAR_RUTAS = [
        'dashboard.datos',
        // El aviso del navegador ya deja su propia fila de acción; la petición
        // que lo transporta no es un paso del usuario.
        'sesion.estado',
    ];

    /**
     * Prefijos de ruido (assets, health checks, pollings de solo lectura). Los
     * métodos de escritura nunca se ignoran: siempre son una acción del usuario.
     */
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

    private const METODOS_LECTURA = ['GET', 'HEAD'];

    private const CONTROLADOR_LOGIN = 'AuthenticatedSessionController';

    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $inicio = microtime(true);
        Bitacora::iniciarPeticion($inicio);

        // El logout limpia el guard dentro del controlador: sin esta foto no hay
        // forma de saber quién cerró la sesión.
        $usuarioAntes = Bitacora::usuario();

        try {
            $respuesta = $next($request);
            $excepcion = null;
        } catch (Throwable $excepcion) {
            $respuesta = null;
        }

        try {
            $this->registrar($request, $respuesta, $excepcion, $usuarioAntes, $inicio);
        } catch (Throwable) {
            // La bitácora no es crítica: si falla, la petición sigue su curso.
        }

        if ($excepcion !== null) {
            throw $excepcion;
        }

        return $respuesta;
    }

    private function registrar(
        Request $request,
        ?SymfonyResponse $respuesta,
        ?Throwable $excepcion,
        ?Authenticatable $usuarioAntes,
        float $inicio,
    ): void {
        $ruta = $request->route();

        // Sin ruta reconocida (404 de URIs inventadas) no hay módulo ni acción que
        // registrar, y es el único ruido que se queda afuera a propósito.
        if ($ruta === null || $request->isMethod('OPTIONS')) {
            return;
        }

        $nombre = $ruta->getName();
        $uri = $request->path();
        $status = $this->statusDe($respuesta, $excepcion);

        if ($this->ignorar($request, $nombre, $uri)) {
            return;
        }

        // En el login el guard ya tiene al usuario aunque la request no tenga
        // resolver (la ruta no pasa por `auth`); en el logout pasa lo contrario.
        $usuario = $request->user() ?? Bitacora::usuario() ?? $usuarioAntes;

        $esIntentoLogin = $uri === 'login' && $request->isMethod('POST');

        if ($usuario === null && ! $esIntentoLogin && $status < 400) {
            return;
        }

        [$modulo, $accion] = $this->moduloYAccion($request, $ruta, $nombre, $esIntentoLogin, $status);
        [$entidad, $entidadId] = $this->entidadDeLaRuta($ruta, $modulo);

        Bitacora::peticionHTTP([
            'user_id' => $usuario?->id,
            'nombre_usuario' => $usuario?->name,
            'ruta_nombre' => $nombre,
            'ruta_uri' => mb_substr($uri, 0, 191),
            'metodo' => $request->method(),
            'modulo' => $modulo,
            'accion' => $accion,
            'controlador' => $this->controlador($ruta),
            'entidad' => $entidad,
            'entidad_id' => $entidadId,
            'detalle' => $this->detalle($request, $accion, $usuario),
            'status' => $status,
            'error' => $excepcion !== null ? $this->errorDe($excepcion) : null,
            'duracion_ms' => (int) round((microtime(true) - $inicio) * 1000),
            'ip' => $request->ip(),
        ]);
    }

    private function ignorar(Request $request, ?string $nombre, string $uri): bool
    {
        if (in_array($nombre, self::IGNORAR_RUTAS, true)) {
            return true;
        }

        if (! in_array($request->method(), self::METODOS_LECTURA, true)) {
            return false;
        }

        foreach (self::IGNORAR_PREFIJOS as $prefijo) {
            if (str_starts_with($uri, $prefijo)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Módulo (a qué pantalla pertenece) y acción (qué hizo), que es lo que
     * permite leer el camino: índice → creación → edición → borrado.
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function moduloYAccion(Request $request, mixed $ruta, ?string $nombre, bool $esIntentoLogin, int $status): array
    {
        $metodo = $this->metodoDelControlador($ruta);

        if ($esIntentoLogin || $metodo === 'store') {
            return ['Auth', $status >= 400 ? ActividadRegistro::ACCION_LOGIN_FALLIDO : ActividadRegistro::ACCION_LOGIN];
        }

        if ($metodo === 'create') {
            return ['Auth', 'formulario'];
        }

        if ($metodo === 'destroy') {
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
     * 'App\Http\Controllers\Auth\AuthenticatedSessionController@store' → 'store',
     * y solo cuando es el controlador de sesión: es la única forma de distinguir
     * el login, cuya ruta POST no tiene nombre ni middleware de autenticación.
     */
    private function metodoDelControlador(mixed $ruta): ?string
    {
        $accion = method_exists($ruta, 'getAction') ? $ruta->getAction('controller') : null;

        if (! is_string($accion) || ! str_contains($accion, self::CONTROLADOR_LOGIN.'@')) {
            return null;
        }

        return explode('@', $accion, 2)[1];
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

        if (! is_string($accion) || ! str_contains($accion, '@')) {
            return [null, null];
        }

        [$clase, $metodo] = explode('@', $accion, 2);

        return [Bitacora::moduloDeClase($clase), $metodo];
    }

    /**
     * A qué registro apunta la petición: el id va en la ruta, o el modelo ya
     * viene resuelto por el binding implícito.
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function entidadDeLaRuta(mixed $ruta, ?string $modulo): array
    {
        foreach ($ruta->parameters() as $parametro) {
            if ($parametro instanceof Model) {
                return [Bitacora::moduloDeClase($parametro::class), (string) $parametro->getKey()];
            }

            if (is_scalar($parametro)) {
                return [$modulo, mb_substr((string) $parametro, 0, 40)];
            }
        }

        return [$modulo, null];
    }

    private function detalle(Request $request, ?string $accion, ?Authenticatable $usuario): ?string
    {
        if ($accion === ActividadRegistro::ACCION_LOGIN_FALLIDO) {
            return 'Intento con: ' . mb_substr((string) $request->input('email', '-'), 0, 120);
        }

        if ($accion === ActividadRegistro::ACCION_LOGIN && $usuario !== null) {
            return 'Sesión iniciada';
        }

        return null;
    }

    private function statusDe(?SymfonyResponse $respuesta, ?Throwable $excepcion): int
    {
        if ($respuesta !== null) {
            return $respuesta->getStatusCode();
        }

        return match (true) {
            $excepcion instanceof ValidationException => Response::HTTP_UNPROCESSABLE_ENTITY,
            $excepcion instanceof AuthenticationException => Response::HTTP_UNAUTHORIZED,
            $excepcion instanceof AuthorizationException => Response::HTTP_FORBIDDEN,
            $excepcion instanceof SuspiciousOperationException => Response::HTTP_BAD_REQUEST,
            $excepcion instanceof HttpExceptionInterface => $excepcion->getStatusCode(),
            default => Response::HTTP_INTERNAL_SERVER_ERROR,
        };
    }

    private function errorDe(Throwable $excepcion): string
    {
        $clase = substr($excepcion::class, strrpos($excepcion::class, '\\') + 1);
        $mensaje = preg_replace('/\s+/', ' ', trim($excepcion->getMessage()));

        return mb_substr($clase . ($mensaje !== '' ? ': ' . $mensaje : ''), 0, 191);
    }

    private function controlador(mixed $ruta): ?string
    {
        $accion = method_exists($ruta, 'getAction') ? $ruta->getAction('controller') : null;

        if (! is_string($accion)) {
            return null;
        }

        return mb_substr($accion, 0, 191);
    }
}
