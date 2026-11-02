<?php

namespace App\Services;

use App\Models\ActividadRegistro;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;

/**
 * Escritor único de la bitácora de actividad (tabla `actividad_registros`).
 *
 * Hay dos tipos de fila: la `peticion` que escribe el middleware por cada
 * petición HTTP, y la `accion` que un controlador registra desde dentro cuando una
 * petición hace más de una cosa o cuando lo que hizo no se ve en la URL (por
 * ejemplo el cambio de estado de una OP). Ambas comparten `peticion`, un id que
 * el middleware genera al empezar la request, para que el análisis pueda
 * colapsarlas en un solo paso y medir el salto entre acciones reales.
 *
 * Ninguna llamada a la bitácora puede tumbar el negocio: todo falla en silencio.
 */
class Bitacora
{
    /** Etiquetas de `Myhelp::EscribirEnLog` que son navegación, no acción. */
    private const IGNORAR_EN_LEGADO = ['begin'];

    /** Verbos del log legado convertidos a la acción que entiende la vista. */
    private const VERBOS_LEGADO = [
        'STORE' => 'store',
        'CREATE' => 'store',
        'UPDATE' => 'update',
        'UPDATE2' => 'update',
        'PATCH' => 'update',
        'DELETE' => 'destroy',
        'DESTROY' => 'destroy',
        'IMPORT' => 'uploadExcel',
        'CAMBIAR-ESTADO' => 'cambiarEstado',
        'CAMBIO-ESTADO' => 'cambiarEstado',
        'SYNC-DISTRIBUCION' => 'syncDistribucion',
        'BORRADOR' => 'guardarBorrador',
        'RECEPCION-BORRADOR' => 'recepcionBorrador',
        'SALIDA-OP' => 'salidaOp',
    ];

    private static ?string $peticionActual = null;

    private static ?float $inicioPeticion = null;

    /**
     * Abre la petición actual y devuelve su id. Lo llama el middleware al
     * empezar la request.
     */
    public static function iniciarPeticion(?float $inicio = null): string
    {
        self::$peticionActual = (string) Str::uuid();
        self::$inicioPeticion = $inicio;

        return self::$peticionActual;
    }

    public static function peticion(): ?string
    {
        return self::$peticionActual;
    }

    /**
     * Registra una acción de negocio hecha dentro de una petición.
     *
     * `$modulo` es la pantalla, `$accion` lo que pasó (se traduce en la vista),
     * `$detalle` es la frase legible ('En taller → Recibiendo') y `$entidad`
     * apunta al registro tocado.
     */
    public static function accion(
        string $modulo,
        string $accion,
        ?string $detalle = null,
        ?string $entidad = null,
        int|string|null $entidadId = null,
        ?string $error = null,
    ): void {
        self::registrar([
            'tipo' => ActividadRegistro::TIPO_ACCION,
            'modulo' => $modulo,
            'accion' => $accion,
            'detalle' => $detalle,
            'entidad' => $entidad,
            'entidad_id' => $entidadId,
            'error' => $error,
        ]);
    }

    /**
     * Acción que el usuario intentó y el negocio rechazó.
     *
     * Hay que registrarla aparte porque el rechazo responde 302 y vuelve a la
     * misma pantalla: la fila de la petición no delata que algo falló, y es
     * justamente el intento rechazado el que corta el camino del usuario.
     */
    public static function rechazo(
        string $modulo,
        string $accion,
        string $motivo,
        ?string $entidad = null,
        int|string|null $entidadId = null,
    ): void {
        self::accion($modulo, $accion, $motivo, $entidad, $entidadId, $motivo);
    }

    /**
     * Fila de una petición HTTP, escrita por el middleware al responder.
     */
    public static function peticionHTTP(array $campos): void
    {
        self::registrar($campos + ['tipo' => ActividadRegistro::TIPO_PETICION]);
    }

    /**
     * Aprovecha las ~230 llamadas existentes de `Myhelp::EscribirEnLog`: sus
     * etiquetas tienen la forma `VERBO:Entidad` ('CAMBIAR-ESTADO:OrdenProduccions',
     * 'STORE:Materials EXITOSO'), que es exactamente módulo + acción + resultado.
     * Las etiquetas sueltas (' Materials ', 'Dashboard') son navegación y se
     * omiten, porque la petición ya quedó en la bitácora.
     */
    public static function desdeEtiquetaLegacy(object $controlador, string $etiqueta, string $mensaje = ''): void
    {
        try {
            $etiqueta = trim($etiqueta);
            $primeraPalabra = Str::before($etiqueta, ' ');

            if ($etiqueta === '' || in_array(mb_strtolower($primeraPalabra), self::IGNORAR_EN_LEGADO, true)) {
                return;
            }

            // 'uploadExcel OK', 'uploadExcel: inicio', 'uploadExcel ERROR lectura':
            // la entidad va en el mensaje, no en la etiqueta.
            if (str_starts_with(mb_strtolower($primeraPalabra), 'uploadexcel')) {
                self::accion(
                    self::moduloDelControlador($controlador),
                    'uploadExcel',
                    self::detalleDelLegado($etiqueta, $mensaje),
                    null,
                    null,
                    str_contains(mb_strtolower($etiqueta), 'error') ? $etiqueta : null,
                );

                return;
            }

            if (! str_contains($etiqueta, ':')) {
                return;
            }

            [$token, $entidad] = explode(':', $etiqueta, 2);

            // 'Materials EXITOSO' -> 'Materials'; 'KitProcesos por OP' -> 'KitProcesos'
            $entidad = self::singular(Str::before(trim(preg_replace('/\s+/', ' ', $entidad)), ' '));
            $accion = self::VERBOS_LEGADO[strtoupper(trim($token))] ?? Str::snake(trim($token));

            self::accion(
                $entidad !== '' ? $entidad : self::moduloDelControlador($controlador),
                $accion,
                self::detalleDelLegado($etiqueta, $mensaje),
                $entidad !== '' ? $entidad : null,
                self::idDelMensaje($mensaje),
                str_contains(mb_strtolower($etiqueta), 'error') ? $etiqueta : null,
            );
        } catch (Throwable) {
            // La bitácora nunca interrumpe lo que el usuario estaba haciendo.
        }
    }

    /**
     * Inserta la fila completando desde el request lo que el llamador no dio.
     */
    private static function registrar(array $campos): void
    {
        try {
            $request = request();
            $ruta = $request?->route();

            ActividadRegistro::create($campos + [
                'user_id' => $campos['user_id'] ?? self::usuario()?->id,
                'nombre_usuario' => $campos['nombre_usuario'] ?? self::usuario()?->name,
                'ruta_nombre' => $campos['ruta_nombre'] ?? $ruta?->getName(),
                'ruta_uri' => $campos['ruta_uri'] ?? mb_substr((string) ($request?->path() ?? ''), 0, 191),
                'metodo' => $campos['metodo'] ?? ($request?->method() ?? 'CLI'),
                'peticion' => $campos['peticion'] ?? self::$peticionActual,
                'controlador' => $campos['controlador'] ?? self::controladorDeLaRuta($ruta),
                'duracion_ms' => $campos['duracion_ms'] ?? self::msDesdeInicio(),
                'ip' => $campos['ip'] ?? $request?->ip(),
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // Si la bitácora falla, la petición sigue viva.
        }
    }

    /**
     * El usuario de la sesión actual, aunque el middleware de autenticación no
     * haya corrido (es el caso del login, donde la request no tiene resolver).
     */
    public static function usuario(): ?Authenticatable
    {
        try {
            return Auth::guard('web')->user();
        } catch (Throwable) {
            return null;
        }
    }

    private static function controladorDeLaRuta(mixed $ruta): ?string
    {
        if (! is_object($ruta)) {
            return null;
        }

        $accion = method_exists($ruta, 'getAction') ? $ruta->getAction('controller') : null;

        return is_string($accion) ? mb_substr($accion, 0, 191) : null;
    }

    private static function moduloDelControlador(object $controlador): string
    {
        return self::moduloDeClase($controlador::class);
    }

    public static function moduloDeClase(string $clase): string
    {
        $corto = substr($clase, strrpos($clase, '\\') + 1);

        return str_replace('Controller', '', $corto);
    }

    /**
     * 'OrdenProduccions' → 'OrdenProduccion' y 'users' → 'User': el nombre que
     * usa la bitácora HTTP, para que el mismo módulo no se escriba de dos formas.
     */
    private static function singular(string $nombre): string
    {
        $nombre = trim($nombre);

        return $nombre === '' ? '' : Str::ucfirst(Str::singular($nombre));
    }

    /** 'Material id:45 | Cinta' → 45. */
    private static function idDelMensaje(string $mensaje): ?string
    {
        return preg_match('/id:\s*(\d+)/i', $mensaje, $m) === 1 ? $m[1] : null;
    }

    private static function detalleDelLegado(string $etiqueta, string $mensaje): string
    {
        $detalle = trim($mensaje) !== '' ? $mensaje : $etiqueta;

        return mb_substr(preg_replace('/\s+/', ' ', $detalle), 0, 255);
    }

    private static function msDesdeInicio(): ?int
    {
        return self::$inicioPeticion === null
            ? null
            : (int) round((microtime(true) - self::$inicioPeticion) * 1000);
    }
}
