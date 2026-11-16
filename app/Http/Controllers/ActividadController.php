<?php

namespace App\Http\Controllers;

use App\Models\ActividadRegistro;
use App\Models\EstadoOperacion;
use App\Models\Material;
use App\Services\ExistenciasMaterialesService;
use App\Services\MaterialesGenericosService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bitácora de navegación con análisis de tiempos.
 *
 * Lee actividad_registros, donde cada petición deja una fila y cada acción de
 * negocio hecha dentro de ella deja la suya. Para medir tiempos las filas de
 * una misma petición se colapsan en un solo paso, así el análisis responde tres
 * cosas: en qué horas se mueve la gente, qué camino sigue cada usuario y cuánto
 * tarda entre acción y acción.
 *
 * Un salto se marca anormal cuando supera el mayor de dos umbrales: un múltiplo
 * de la mediana propia del usuario y un piso absoluto en minutos. Así un
 * usuario lento no se pinta de rojo siempre y uno veloz nunca se pinta.
 *
 * Las filas del análisis se leen con el query builder (objetos planos): con
 * Eloquent, decenas de miles de modelos se comen la memoria de PHP.
 */
class ActividadController extends Controller
{
    public const DIAS_PERMITIDOS = [7, 14, 30, 90];

    public const FACTORES_PERMITIDOS = [2, 3, 4, 5];

    public const UMBRALES_PERMITIDOS = [5, 10, 15, 30, 60];

    /** Qué se toma en cuenta para medir los saltos. */
    public const FOCOS = ['todo', 'acciones', 'navegacion'];

    private const METODOS_ESCRITURA = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * Avisos del navegador: son el borde de una ausencia, no acciones del
     * usuario. Se muestran aparte y convierten en pausa el salto que viene detrás.
     */
    private const MARCADORES = [
        ActividadRegistro::ACCION_CIERRE_NAVEGADOR,
        ActividadRegistro::ACCION_PESTANA_OCULTA,
        ActividadRegistro::ACCION_PESTANA_VISIBLE,
    ];

    /**
     * Sin aviso del navegador, un salto más largo que esto también es una pausa:
     * nadie estuvo media hora mirando una pantalla sin hacer nada.
     */
    private const PAUSA_SEGUNDOS = 1800;

    /** Filas máximo por usuario para medir intervalos. */
    private const TOPES_SERIE = 4000;

    /** Pasos del camino que se muestran; los más antiguos se omiten. */
    private const MAX_PASOS = 400;

    /** Traducción de las acciones de los controladores a español. */
    private const ETIQUETAS = [
        'index' => 'Listado',
        'create' => 'Pantalla de creación',
        'store' => 'Creó',
        'show' => 'Detalle',
        'edit' => 'Pantalla de edición',
        'update' => 'Actualizó',
        'destroy' => 'Eliminó',
        'destroyBulk' => 'Eliminación masiva',
        'uploadExcel' => 'Carga de Excel',
        'login' => 'Se conectó',
        'logout' => 'Se desconectó',
        'login_fallido' => 'Intento de ingreso rechazado',
        'formulario' => 'Pantalla de login',
        'vista' => 'Vista',
        'cambiarEstado' => 'Cambió el estado',
        'guardarBorrador' => 'Guardó borrador',
        'recepcionBorrador' => 'Recibió del borrador',
        'syncDistribucion' => 'Sincronizó las tallas',
        'salidaOp' => 'Dió la salida de materiales',
        'transicionNoPermitida' => 'Transición rechazada',
        'estadosIncompatibles' => 'Estados incompatibles',
        'procesoExternoIncompleto' => 'Proceso externo incompleto',
        'faltaFechaRegreso' => 'Falta la fecha de regreso',
        'cierreBloqueado' => 'Cierre bloqueado',
        'recepcionFueraDePlan' => 'Recepción fuera de plan',
        'numeroDuplicado' => 'Número de OP duplicado',
        'purgarMateriales' => 'Borró todos los materiales',
        'ceroExistencias' => 'Puso en cero las existencias',
        'rellenarMateriales' => 'Rellenó existencias y precios al azar',
        'reiniciarOrdenesProduccion' => 'Reinició las OP de prueba',
        'cierre' => 'Cerró la pestaña',
        'oculta' => 'Dejó la pestaña',
        'visible' => 'Volvió a la pestaña',
    ];

    public function index(Request $request): Response
    {
        $dias = $this->enteroFiltro((int) $request->query('dias', 30), self::DIAS_PERMITIDOS, 30);
        $factor = $this->enteroFiltro((int) $request->query('factor', 3), self::FACTORES_PERMITIDOS, 3);
        $umbralMin = $this->enteroFiltro((int) $request->query('umbral', 10), self::UMBRALES_PERMITIDOS, 10);
        $foco = $this->textoFiltro((string) $request->query('foco', 'todo'), self::FOCOS, 'todo');

        $desde = now()->startOfDay()->subDays($dias - 1);
        $series = $this->enfocar($this->seriesPorUsuario($desde), $foco);
        $usuariosConHistorial = $this->usuariosConHistorial();

        $caminoUsuarioId = $this->usuarioFiltro($request->query('camino_usuario'));
        if ($caminoUsuarioId === null) {
            // Usuario con más acciones: max() devolvería el conteo, no la clave.
            $caminoUsuarioId = $series->keys()
                ->sortByDesc(fn (int $id) => $series[$id]->count())
                ->first();
        }

        $bitacoraDesde = $this->fechaHoraFiltro($request->query('bitacora_desde')) ?? $desde->copy();
        $bitacoraHasta = $this->fechaHoraFiltro($request->query('bitacora_hasta')) ?? now();
        if ($bitacoraDesde->gt($bitacoraHasta)) {
            [$bitacoraDesde, $bitacoraHasta] = [$bitacoraHasta, $bitacoraDesde];
        }
        $bitacoraDesde->startOfMinute();
        $bitacoraHasta->endOfMinute();

        $bitacoraUsuarioId = $this->usuarioFiltro($request->query('bitacora_usuario'));
        $bitacoraTipo = $this->textoFiltro(
            (string) $request->query('bitacora_tipo', 'todos'),
            ['todos', 'acciones', 'navegacion'],
            'todos',
        );
        $bitacoraMetodo = strtoupper((string) $request->query('bitacora_metodo', ''));
        if (! in_array($bitacoraMetodo, array_merge(['GET', 'HEAD'], self::METODOS_ESCRITURA), true)) {
            $bitacoraMetodo = '';
        }

        $resumenBase = $this->resumenBase($series);

        return Inertia::render('actividad/Index', [
            'breadcrumbs' => [['title' => 'Actividad', 'href' => route('Actividad.index')]],
            'title' => 'Actividad y tiempos',
            'esAmbienteTest' => config('app.env') === 'test' || app()->environment('test'),
            // Los botones de mantenimiento de materiales dicen sobre cuántos filas
            // van a actuar y con qué rango de valores antes de que se presionen.
            'estadosOp' => EstadoOperacion::select(['id', 'nombre'])->orderBy('id')->get(),
            'materiales' => [
                'total' => Material::count(),
                'sin_existencia' => Material::where('existencia_actual', 0)->count(),
                'sin_precio' => Material::doesntHave('prices')->count(),
                'rango_existencia' => ExistenciasMaterialesService::rangoExistencia(),
                'rango_precio' => ExistenciasMaterialesService::rangoPrecio(),
                'genericos' => MaterialesGenericosService::estadisticas(),
            ],
            'filtro' => [
                'dias' => $dias,
                'factor' => $factor,
                'umbral' => $umbralMin,
                'foco' => $foco,
                'camino_usuario' => $caminoUsuarioId,
            ],
            'bitacoraFiltro' => [
                'desde' => $bitacoraDesde->format('Y-m-d\TH:i'),
                'hasta' => $bitacoraHasta->format('Y-m-d\TH:i'),
                'usuario' => $bitacoraUsuarioId,
                'tipo' => $bitacoraTipo,
                'metodo' => $bitacoraMetodo,
            ],
            'opciones' => [
                'dias' => self::DIAS_PERMITIDOS,
                'factor' => self::FACTORES_PERMITIDOS,
                'umbral' => self::UMBRALES_PERMITIDOS,
                'focos' => ['todo' => 'Todo', 'acciones' => 'Solo acciones', 'navegacion' => 'Solo navegación'],
                'pausa_min' => intdiv(self::PAUSA_SEGUNDOS, 60),
                'metodos' => array_merge(['GET', 'HEAD'], self::METODOS_ESCRITURA),
            ],
            'resumen' => $this->resumen($resumenBase, $desde),
            'porHora' => $this->porHora($resumenBase),
            'usuarios' => $this->usuarios($series, $factor, $umbralMin),
            'usuarios_historial' => $usuariosConHistorial,
            'camino' => $this->camino($series, $caminoUsuarioId, $factor, $umbralMin),
            'transiciones' => $this->transiciones($series, $caminoUsuarioId, $factor, $umbralMin),
            'bitacora' => $this->bitacoraPaginada(
                $bitacoraDesde,
                $bitacoraHasta,
                $bitacoraUsuarioId,
                $bitacoraTipo,
                $bitacoraMetodo,
            ),
        ]);
    }

    private function bitacoraPaginada(
        Carbon $desde,
        Carbon $hasta,
        ?int $usuarioId,
        string $tipo,
        string $metodo,
    ): array {
        $registros = ActividadRegistro::query()
            ->whereBetween('created_at', [$desde, $hasta])
            ->when($usuarioId, fn ($consulta) => $consulta->where('user_id', $usuarioId))
            ->when($tipo === 'acciones', fn ($consulta) => $consulta->where(function ($consulta) {
                $consulta->where('tipo', ActividadRegistro::TIPO_ACCION)
                    ->orWhereIn('metodo', self::METODOS_ESCRITURA);
            }))
            ->when($tipo === 'navegacion', fn ($consulta) => $consulta->where('tipo', ActividadRegistro::TIPO_PETICION)
                ->whereNotIn('metodo', self::METODOS_ESCRITURA))
            ->when($metodo !== '', fn ($consulta) => $consulta->where('metodo', $metodo))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return $registros->through(fn (ActividadRegistro $registro) => [
            'id' => $registro->id,
            'cuando' => $registro->created_at?->format('d-m H:i:s'),
            'usuario' => $registro->nombre_usuario ?? 'Sin usuario',
            'tipo' => $registro->tipo,
            'etiqueta' => $this->etiqueta((object) [
                'modulo' => $registro->modulo,
                'accion' => $registro->accion,
            ]),
            'modulo' => $registro->modulo,
            'metodo' => $registro->metodo,
            'ruta' => $registro->ruta_uri,
            'entidad' => $registro->entidad_id !== null
                ? $registro->entidad.' #'.$registro->entidad_id
                : $registro->entidad,
            'detalle' => $registro->detalle,
            'status' => $registro->status,
            'error' => $registro->error,
            'duracion_ms' => $registro->duracion_ms,
            'ip' => $registro->ip,
            'esEscritura' => in_array($registro->metodo, self::METODOS_ESCRITURA, true),
        ])->toArray();
    }

    private function enteroFiltro(int $valor, array $permitidos, int $porDefecto): int
    {
        return in_array($valor, $permitidos, true) ? $valor : $porDefecto;
    }

    private function usuarioFiltro(mixed $valor): ?int
    {
        if ($valor === null || $valor === '' || ! is_numeric($valor)) {
            return null;
        }

        $id = (int) $valor;

        return $id > 0 ? $id : null;
    }

    private function fechaHoraFiltro(mixed $valor): ?Carbon
    {
        if (! is_string($valor) || trim($valor) === '') {
            return null;
        }

        try {
            $fecha = Carbon::createFromFormat('!Y-m-d\TH:i', $valor);

            return $fecha->format('Y-m-d\TH:i') === $valor ? $fecha : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Usuarios que alguna vez dejaron historial. Se consulta sin el corte de
     * días para que el selector del camino no esconda a quien solo tiene
     * actividad fuera del período analizado.
     *
     * @return array<int, array{id: int, nombre: string}>
     */
    private function usuariosConHistorial(): array
    {
        $ultimasPorUsuario = DB::table('actividad_registros')
            ->select('user_id', DB::raw('MAX(id) as registro_id'))
            ->whereNotNull('user_id')
            ->groupBy('user_id');

        return DB::table('actividad_registros as actividad')
            ->joinSub($ultimasPorUsuario, 'ultima', function ($consulta) {
                $consulta->on('actividad.id', '=', 'ultima.registro_id');
            })
            ->orderBy('actividad.nombre_usuario')
            ->get(['actividad.user_id', 'actividad.nombre_usuario'])
            ->map(fn (object $fila) => [
                'id' => (int) $fila->user_id,
                'nombre' => $fila->nombre_usuario ?: 'Sin nombre',
            ])
            ->values()
            ->all();
    }

    private function textoFiltro(string $valor, array $permitidos, string $porDefecto): string
    {
        return in_array($valor, $permitidos, true) ? $valor : $porDefecto;
    }

    /**
     * Deja en cada serie solo los pasos que interesan según el foco: los que
     * hicieron algo (acción de negocio o escritura) o los que fueron pura
     * navegación. Filtrar antes de medir es el punto: con 'Solo acciones' el
     * salto entre dos guardados reales es el número que se ve.
     *
     * @param Collection<int, Collection<int, object>> $series
     * @return Collection<int, Collection<int, object>>
     */
    private function enfocar(Collection $series, string $foco): Collection
    {
        if ($foco === 'todo') {
            return $series;
        }

        return $series
            ->map(fn (Collection $serie) => $serie
                ->filter(fn (object $paso) => $paso->tipo === 'marcador'
                    || ($foco === 'acciones' ? $paso->tipo !== 'navegacion' : $paso->tipo === 'navegacion'))
                ->values())
            ->filter(fn (Collection $serie) => $serie->isNotEmpty());
    }

    /**
     * Peticiones del período agrupadas por usuario y ordenadas por tiempo, que
     * es el orden natural para medir los intervalos entre acciones.
     *
     * @return Collection<int, Collection<int, object>>
     */
    private function seriesPorUsuario(Carbon $desde): Collection
    {
        $filas = DB::table('actividad_registros')
            ->where('created_at', '>=', $desde)
            ->whereNotNull('user_id')
            ->orderBy('user_id')
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(self::TOPES_SERIE * 40)
            ->get([
                'id', 'user_id', 'nombre_usuario', 'ruta_nombre', 'ruta_uri',
                'metodo', 'tipo', 'peticion', 'modulo', 'accion', 'entidad',
                'entidad_id', 'detalle', 'status', 'error', 'duracion_ms', 'ip', 'created_at',
                DB::raw('UNIX_TIMESTAMP(created_at) as fecha_ts'),
            ]);

        return $filas->groupBy('user_id')
            ->map(fn (Collection $grupo) => $this->pasos($grupo)->take(self::TOPES_SERIE)->values());
    }

    /**
     * Convierte las filas en un paso por petición.
     *
     * Una petición deja su propia fila y una fila por cada acción de negocio que
     * hizo; todas comparten el mismo `peticion` y el mismo segundo. Separarlas
     * llenaría el análisis de intervalos de 0 segundos y hundiría la mediana, así
     * que se colapsan. El paso toma el nombre de la acción de negocio cuando
     * existe, porque esa es la acción del usuario, y entre varias manda la de la
     * pantalla de la petición. El status y la duración siempre vienen de la fila
     * HTTP, que es la que conoce la respuesta.
     */
    private function pasos(Collection $filas): Collection
    {
        $grupos = [];

        foreach ($filas as $fila) {
            $grupos[$fila->peticion ?? 'p'.$fila->id][] = $fila;
        }

        $pasos = [];

        foreach ($grupos as $grupo) {
            usort($grupo, fn (object $a, object $b) => $a->id <=> $b->id);

            $http = null;
            $negocios = [];
            $error = null;

            foreach ($grupo as $fila) {
                if ($fila->tipo === ActividadRegistro::TIPO_ACCION) {
                    $negocios[] = $fila;
                } else {
                    $http ??= $fila;
                }

                if ($fila->error !== null && $fila->error !== '') {
                    $error = $fila->error;
                }
            }

            // De las acciones de negocio de la petición manda la de la pantalla
            // que el usuario estaba usando; las demás quedan contadas como +N.
            $referencia = collect($negocios)
                ->first(fn (object $fila) => $http !== null && $fila->modulo === $http->modulo)
                ?? ($negocios[0] ?? $http ?? $grupo[0]);
            $metodo = $http?->metodo ?? $referencia->metodo;
            $esMarcador = in_array($referencia->accion, self::MARCADORES, true);

            $pasos[] = (object) [
                'id' => $referencia->id,
                'nombre_usuario' => $referencia->nombre_usuario,
                'ruta_uri' => $referencia->ruta_uri,
                'metodo' => $metodo,
                'modulo' => $referencia->modulo,
                'accion' => $referencia->accion,
                'entidad' => $referencia->entidad,
                'entidad_id' => $referencia->entidad_id,
                'detalle' => $referencia->detalle ?? $http?->detalle,
                'status' => $http?->status ?? $referencia->status,
                'error' => $error,
                'duracion_ms' => $http?->duracion_ms ?? $referencia->duracion_ms,
                'ip' => $http?->ip ?? $referencia->ip,
                'created_at' => $grupo[0]->created_at,
                'fecha_ts' => $grupo[0]->fecha_ts,
                'tipo' => $esMarcador
                    ? 'marcador'
                    : ($negocios !== []
                        ? 'negocio'
                        : (in_array($metodo, self::METODOS_ESCRITURA, true) ? 'escritura' : 'navegacion')),
                'otras' => max(0, count($negocios) - 1),
            ];
        }

        usort($pasos, fn (object $a, object $b) => [$a->fecha_ts, $a->id] <=> [$b->fecha_ts, $b->id]);

        return collect($pasos);
    }

    /**
     * Recorre las series una sola vez y deja lo que necesitan tanto el resumen
     * como el gráfico de horas.
     *
     * @return array{acciones: int, usuarios: int, conexiones: int, escrituras: int, negocios: int, fallidas: int, por_hora: array<int,int>, conexiones_hora: array<int,int>}
     */
    private function resumenBase(Collection $series): array
    {
        $porHora = array_fill(0, 24, 0);
        $conexionesHora = array_fill(0, 24, 0);
        $acciones = 0;
        $conexiones = 0;
        $escrituras = 0;
        $negocios = 0;
        $fallidas = 0;

        foreach ($series as $serie) {
            foreach ($serie as $paso) {
                if ($paso->tipo === 'marcador') {
                    continue;
                }

                $hora = (int) Carbon::parse($paso->created_at)->format('G');
                $acciones++;
                $porHora[$hora]++;

                if ($paso->accion === ActividadRegistro::ACCION_LOGIN) {
                    $conexiones++;
                    $conexionesHora[$hora]++;
                }

                if ($paso->tipo !== 'navegacion') {
                    $escrituras++;
                }

                if ($paso->tipo === 'negocio') {
                    $negocios++;
                }

                if ($this->fallo($paso)) {
                    $fallidas++;
                }
            }
        }

        return [
            'acciones' => $acciones,
            'usuarios' => $series->count(),
            'conexiones' => $conexiones,
            'escrituras' => $escrituras,
            'negocios' => $negocios,
            'fallidas' => $fallidas,
            'por_hora' => $porHora,
            'conexiones_hora' => $conexionesHora,
        ];
    }

    /** Un paso falló cuando la petición respondió 4xx/5xx o el negocio lo rechazó. */
    private function fallo(object $paso): bool
    {
        return ($paso->status !== null && $paso->status >= 400) || $paso->error !== null;
    }

    private function resumen(array $base, Carbon $desde): array
    {
        $horas = $base['por_hora'];
        arsort($horas);

        $picos = [];
        foreach (array_slice($horas, 0, 3, true) as $hora => $total) {
            if ($total > 0) {
                $picos[] = ['hora' => (int) $hora, 'total' => $total];
            }
        }

        return [
            'acciones' => $base['acciones'],
            'usuarios' => $base['usuarios'],
            'conexiones' => $base['conexiones'],
            'escrituras' => $base['escrituras'],
            'negocios' => $base['negocios'],
            'fallidas' => $base['fallidas'],
            'desde' => $desde->format('Y-m-d'),
            'hasta' => now()->format('Y-m-d'),
            'picos' => $picos,
            'generado_en' => now()->format('H:i:s'),
        ];
    }

    private function porHora(array $base): array
    {
        $horas = [];
        for ($hora = 0; $hora < 24; $hora++) {
            $horas[] = sprintf('%02d', $hora);
        }

        return [
            'horas' => $horas,
            'acciones' => array_values($base['por_hora']),
            'conexiones' => array_values($base['conexiones_hora']),
        ];
    }

    /**
     * Saltos entre pasos consecutivos del mismo usuario.
     *
     * Los avisos del navegador se devuelven dentro de `pasos`, para que el camino
     * los dibuje, pero su función es marcar de `pausa` al paso que viene después:
     * después de un «cerró la pestaña» el tiempo que sigue no es trabajo lento.
     * Un salto largo sin aviso también es pausa (PAUSA_SEGUNDOS), y ninguno de los
     * dos entra en la mediana: si no, una noche entre dos visitas volvería el
     * umbral inservible para todo el día.
     *
     * @return array{intervalos: array<int,int>, pasos: array<int, array{fila: object, intervalo: int|null, pausa: bool}>}
     */
    private function intervalos(Collection $serie): array
    {
        $intervalos = [];
        $pasos = [];
        $anterior = null;
        $seFue = false;

        foreach ($serie as $fila) {
            if ($fila->tipo === 'marcador') {
                // Las varias solapas del mismo usuario avisan en el mismo segundo:
                // en el camino basta una línea que diga que el usuario se fue.
                if ($seFue) {
                    continue;
                }

                $pasos[] = ['fila' => $fila, 'intervalo' => null, 'pausa' => false];
                $seFue = true;

                continue;
            }

            $segundos = $anterior === null
                ? null
                : $fila->fecha_ts - $anterior->fecha_ts;

            $pausa = $segundos !== null && ($seFue || $segundos > self::PAUSA_SEGUNDOS);

            if ($segundos !== null && ! $pausa) {
                $intervalos[] = $segundos;
            }

            $pasos[] = ['fila' => $fila, 'intervalo' => $segundos, 'pausa' => $pausa];
            $anterior = $fila;
            $seFue = false;
        }

        return ['intervalos' => $intervalos, 'pasos' => $pasos];
    }

    /**
     * Umbral en segundos a partir del cual un salto se considera anormal.
     * Con pocos intervalos la mediana no es confiable: queda el piso absoluto.
     */
    private function umbralSegundos(array $intervalos, int $factor, int $umbralMin): float
    {
        $piso = $umbralMin * 60;

        if (count($intervalos) < 3) {
            return (float) $piso;
        }

        return max($factor * $this->mediana($intervalos), $piso);
    }

    private function mediana(array $valores): float
    {
        sort($valores);
        $n = count($valores);

        if ($n === 0) {
            return 0.0;
        }

        $mitad = intdiv($n, 2);

        return $n % 2 === 1
            ? (float) $valores[$mitad]
            : ($valores[$mitad - 1] + $valores[$mitad]) / 2;
    }

    private function promedio(array $valores): float
    {
        return $valores === [] ? 0.0 : array_sum($valores) / count($valores);
    }

    /**
     * Ritmo de cada usuario: promedio y mediana entre acción y acción, con sus
     * saltos anormales y sus pausas. Ordenado por anomalías para que lo raro
     * salga primero. La mediana se calcula solo con los saltos dentro de una
     * misma presencia, que es el ritmo real del usuario.
     */
    private function usuarios(Collection $series, int $factor, int $umbralMin): array
    {
        $filas = [];

        foreach ($series as $usuarioId => $serie) {
            ['intervalos' => $intervalos, 'pasos' => $pasos] = $this->intervalos($serie);
            $umbral = $this->umbralSegundos($intervalos, $factor, $umbralMin);

            $anomalias = 0;
            $conexiones = 0;
            $negocio = 0;
            $fallidas = 0;
            $pausas = 0;
            $pasosReales = 0;

            foreach ($pasos as $paso) {
                if ($paso['fila']->tipo === 'marcador') {
                    continue;
                }

                $pasosReales++;

                // Estar sin el usuario delante no es un retraso de trabajo.
                if ($paso['pausa']) {
                    $pausas++;
                } elseif ($paso['intervalo'] !== null && $paso['intervalo'] > $umbral) {
                    $anomalias++;
                }

                if ($paso['fila']->accion === ActividadRegistro::ACCION_LOGIN) {
                    $conexiones++;
                }

                if ($paso['fila']->tipo === 'negocio') {
                    $negocio++;
                }

                if ($this->fallo($paso['fila'])) {
                    $fallidas++;
                }
            }

            $filas[] = [
                'id' => (int) $usuarioId,
                'nombre' => $serie->first()->nombre_usuario ?? 'Sin nombre',
                'acciones' => $pasosReales,
                'pausas' => $pausas,
                'negocio' => $negocio,
                'fallidas' => $fallidas,
                'conexiones' => $conexiones,
                'anomalias' => $anomalias,
                'promedio_min' => round($this->promedio($intervalos) / 60, 1),
                'mediana_min' => round($this->mediana($intervalos) / 60, 1),
                'umbral_min' => round($umbral / 60, 1),
                'max_min' => round(($intervalos === [] ? 0 : max($intervalos)) / 60, 1),
                'ultima' => Carbon::parse($serie->last()->created_at)->format('d-m H:i'),
                'ultima_accion' => $this->etiqueta($serie->last()),
            ];
        }

        usort($filas, fn (array $a, array $b) => ($b['anomalias'] <=> $a['anomalias']) ?: ($b['acciones'] <=> $a['acciones']));

        return $filas;
    }

    /**
     * El camino del usuario seleccionado: cada paso con el tiempo desde el
     * anterior y la marca que la vista pinta en rojo.
     */
    private function camino(Collection $series, ?int $usuarioId, int $factor, int $umbralMin): array
    {
        if ($usuarioId === null || !isset($series[$usuarioId])) {
            return ['usuario' => null, 'umbral_min' => (float) $umbralMin, 'pasos' => [], 'ocultos' => 0];
        }

        $serie = $series[$usuarioId];
        ['intervalos' => $intervalos, 'pasos' => $pasos] = $this->intervalos($serie);
        $umbral = $this->umbralSegundos($intervalos, $factor, $umbralMin);

        $filas = [];
        foreach ($pasos as $paso) {
            $fila = $paso['fila'];
            $anormal = $fila->tipo !== 'marcador'
                && ! $paso['pausa']
                && $paso['intervalo'] !== null
                && $paso['intervalo'] > $umbral;

            $filas[] = [
                'hora' => Carbon::parse($fila->created_at)->format('d-m H:i:s'),
                'etiqueta' => $this->etiqueta($fila),
                'ruta' => $fila->ruta_uri,
                'metodo' => $fila->metodo,
                'tipo' => $fila->tipo,
                'entidad' => $fila->entidad_id !== null
                    ? $fila->entidad.' #'.$fila->entidad_id
                    : $fila->entidad,
                'detalle' => $fila->detalle,
                'otras' => $fila->otras,
                'status' => $fila->status,
                'error' => $fila->error,
                'duracion_ms' => $fila->duracion_ms,
                'ip' => $fila->ip,
                'intervalo_seg' => $paso['intervalo'],
                'pausa' => $paso['pausa'],
                'anormal' => $anormal,
            ];
        }

        return [
            'usuario' => $serie->first()->nombre_usuario ?? 'Sin nombre',
            'umbral_min' => round($umbral / 60, 1),
            'pasos' => array_slice($filas, -self::MAX_PASOS),
            'ocultos' => max(0, count($filas) - self::MAX_PASOS),
        ];
    }

    /**
     * Las transiciones más repetidas (de una acción a la siguiente) con su
     * tiempo promedio, para ver el camino típico y sus desvíos.
     */
    private function transiciones(Collection $series, ?int $usuarioId, int $factor, int $umbralMin): array
    {
        $grupos = $usuarioId === null ? $series : collect([$usuarioId => $series[$usuarioId] ?? collect()]);
        $pares = [];

        foreach ($grupos as $serie) {
            if ($serie->isEmpty()) {
                continue;
            }

            ['intervalos' => $intervalos, 'pasos' => $pasos] = $this->intervalos($serie);
            $umbral = $this->umbralSegundos($intervalos, $factor, $umbralMin);
            $total = count($pasos);

            for ($i = 1; $i < $total; $i++) {
                $de = $pasos[$i - 1];
                $a = $pasos[$i];

                // Un salto que cruza una pausa, o que nace de un aviso del
                // navegador, no forma parte del camino típico del usuario.
                if ($a['intervalo'] === null
                    || $a['pausa']
                    || $de['fila']->tipo === 'marcador'
                    || $a['fila']->tipo === 'marcador') {
                    continue;
                }

                $clave = $this->etiqueta($de['fila']) . '|' . $this->etiqueta($a['fila']);

                $pares[$clave] ??= ['de' => $this->etiqueta($de['fila']), 'a' => $this->etiqueta($a['fila']), 'veces' => 0, 'segundos' => 0, 'anormales' => 0];
                $pares[$clave]['veces']++;
                $pares[$clave]['segundos'] += (int) $a['intervalo'];

                if ($a['intervalo'] > $umbral) {
                    $pares[$clave]['anormales']++;
                }
            }
        }

        $filas = array_map(fn (array $par) => [
            'de' => $par['de'],
            'a' => $par['a'],
            'veces' => $par['veces'],
            'promedio_min' => round($par['segundos'] / max(1, $par['veces']) / 60, 1),
            'anormales' => $par['anormales'],
        ], array_values($pares));

        usort($filas, fn (array $a, array $b) => $b['veces'] <=> $a['veces']);

        return array_slice($filas, 0, 12);
    }

    /** "Eliminó · OrdenProduccion": legible sin conocer los controladores. */
    private function etiqueta(object $fila): string
    {
        $accion = $fila->accion ?? '';
        $texto = self::ETIQUETAS[$accion] ?? ($accion !== '' ? $accion : 'Navegación');

        return empty($fila->modulo) ? $texto : $texto . ' · ' . $fila->modulo;
    }
}
