<?php

namespace Database\Seeders;

use App\Models\Distribucion_por_tallas;
use App\Models\EstadoOperacion;
use App\Models\Merma;
use App\Models\OrdenProduccion;
use App\Models\Producto;
use App\Models\RecepcionCalidad;
use App\Models\Taller;
use App\Models\User;
use App\Services\MermaRecepcionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Genera OPs con productos que no fueron aprobados para probar el reporte de Merma.
 *
 * Cada OP creada tiene recepciones de calidad con piezas no recuperables y una
 * merma asociada. Los parámetros son públicos para poder ajustarlos directamente
 * en este archivo antes de ejecutar:
 *
 * php artisan db:seed --class=OrdenesPerdidasSeeder
 */
class OrdenesPerdidasSeeder extends Seeder
{
    /** Número de OPs que se generarán. */
    public const CANTIDAD_ORDENES = 20;

    /** Promedio de piezas no recuperables por OP. */
    public const PROMEDIO_PERDIDAS_POR_ORDEN = 4;

    /** Variación aleatoria de las pérdidas: 0.50 equivale a ±50 %. */
    public const VARIACION_PERDIDAS_POR_ORDEN = 0.50;

    /** Porcentaje promedio de merma sobre la producción esperada: 0.08 = 8 %. */
    public const PROMEDIO_PORCENTAJE_PERDIDAS = 0.08;

    /** Variación aleatoria del porcentaje de merma. */
    public const VARIACION_PORCENTAJE_PERDIDAS = 0.25;

    /** Cantidad de tallas que puede tener cada OP. */
    public const TALLAS_POR_ORDEN_MINIMA = 1;
    public const TALLAS_POR_ORDEN_MAXIMA = 3;

    /** Tallas usadas para las recepciones de calidad. */
    public const TALLAS_POSIBLES = ['6', '7', '8', '9', '10'];

    /** Producción esperada de las tallas que no tienen pérdida. */
    public const ESPERADA_SIN_PERDIDA_MINIMA = 10;
    public const ESPERADA_SIN_PERDIDA_MAXIMA = 40;

    /** Costo genérico cuando el producto no tiene ficha técnica con precios. */
    public const COSTO_UNITARIO_MINIMO = 25000;
    public const COSTO_UNITARIO_MAXIMO = 90000;

    /** Días hacia atrás entre los que se distribuyen las fechas de recepción. */
    public const DIAS_HISTORICO = 90;

    /** Semilla para reproducir los mismos datos; null produce datos aleatorios. */
    public const SEMILLA_ALEATORIA = 20261106;

    /** Productos genéricos creados solo si la base no tiene productos. */
    public const CANTIDAD_PRODUCTOS_GENERICOS = 5;

    public function __construct(
        private readonly ?int $cantidadOrdenes = null,
        private readonly ?float $promedioPerdidasPorOrden = null,
    ) {
    }

    /**
     * Ejecuta el seeder.
     */
    public function run(): void
    {
        $cantidadOrdenes = $this->cantidadOrdenes ?? self::CANTIDAD_ORDENES;
        $promedioPerdidas = $this->promedioPerdidasPorOrden ?? self::PROMEDIO_PERDIDAS_POR_ORDEN;

        if ($cantidadOrdenes < 1) {
            throw new InvalidArgumentException('La cantidad de OPs debe ser mayor que cero.');
        }

        if ($promedioPerdidas < 1) {
            throw new InvalidArgumentException('El promedio de pérdidas debe ser mayor o igual que uno.');
        }

        if (self::VARIACION_PERDIDAS_POR_ORDEN < 0 || self::VARIACION_PERDIDAS_POR_ORDEN >= 1) {
            throw new InvalidArgumentException('La variación de pérdidas debe estar entre 0 y 1.');
        }

        if (self::PROMEDIO_PORCENTAJE_PERDIDAS <= 0 || self::PROMEDIO_PORCENTAJE_PERDIDAS > 1) {
            throw new InvalidArgumentException('El porcentaje promedio de pérdidas debe estar entre 0 y 1.');
        }

        if (self::SEMILLA_ALEATORIA !== null) {
            mt_srand(self::SEMILLA_ALEATORIA);
            srand(self::SEMILLA_ALEATORIA);
        }

        $productos = $this->productosParaGenerar();
        $usuarios = User::query()->pluck('id')->all();
        $talleres = Taller::query()->pluck('id')->all();
        $estadoRecibiendo = $this->estadoRecibiendo();
        $perdidasPorOp = $this->distribuirPerdidas($cantidadOrdenes, $promedioPerdidas);
        $costosGenericos = [];
        $mermasAntes = Merma::query()->count();

        DB::transaction(function () use (
            $productos,
            $usuarios,
            $talleres,
            $estadoRecibiendo,
            $perdidasPorOp,
            &$costosGenericos,
        ): void {
            foreach ($perdidasPorOp as $perdidas) {
                $this->crearOpConPerdidas(
                    $productos[mt_rand(0, count($productos) - 1)],
                    $perdidas,
                    $usuarios,
                    $talleres,
                    $estadoRecibiendo,
                    $costosGenericos,
                );
            }
        });

        $totalPerdidas = array_sum($perdidasPorOp);
        $this->command?->info(sprintf(
            'Órdenes con pérdidas generadas: %d; mermas: %d; piezas perdidas: %d; promedio: %.2f por OP.',
            $cantidadOrdenes,
            Merma::query()->count() - $mermasAntes,
            $totalPerdidas,
            $totalPerdidas / $cantidadOrdenes,
        ));
    }

    /**
     * Productos que se usarán en las OPs. Si la base está vacía, crea productos
     * genéricos para que el seeder también pueda ejecutarse de forma aislada.
     *
     * @return list<int>
     */
    private function productosParaGenerar(): array
    {
        $ids = Producto::query()->pluck('id')->all();

        if ($ids !== []) {
            return $ids;
        }

        $productos = [];

        for ($i = 1; $i <= self::CANTIDAD_PRODUCTOS_GENERICOS; $i++) {
            $productos[] = Producto::create([
                'Codigo_interno' => 'PRD-MERMA-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'ID_producto_Shopify' => 'PRD-MERMA-' . $i,
                'Categoria_original' => 'Pruebas > Merma',
                'Familia_normalizada' => 'Productos genéricos de prueba',
                'Titulo_Shopify' => 'Producto genérico para merma ' . $i,
                'Control_de_variante' => 'Talla',
                'Opciones' => implode(',', self::TALLAS_POSIBLES),
                'Estado' => 'Activo',
                'Observaciones' => 'Producto generado por OrdenesPerdidasSeeder.',
                'descripcion' => 'Producto genérico para verificar el reporte de mermas.',
                'costo_anticipado' => mt_rand(self::COSTO_UNITARIO_MINIMO, self::COSTO_UNITARIO_MAXIMO),
                'precio_venta_producto' => mt_rand(self::COSTO_UNITARIO_MAXIMO, self::COSTO_UNITARIO_MAXIMO * 2),
            ])->id;
        }

        return $productos;
    }

    /**
     * Estado requerido por el reporte para identificar las OPs en recepción.
     */
    private function estadoRecibiendo(): int
    {
        return (int) EstadoOperacion::firstOrCreate(
            ['nombre' => 'Recibiendo'],
            [
                'cuando_aplica' => 'Se recibieron algunos pares aprobados pero la OP no está completa',
                'regla_clave' => 'Solo lo aprobado entra a inventario.',
                'puede_paser_a' => null,
            ],
        )->id;
    }

    /**
     * Distribuye las piezas perdidas entre las OPs conservando exactamente el
     * total solicitado y aplicando la variación configurada.
     *
     * @return list<int>
     */
    private function distribuirPerdidas(int $cantidadOrdenes, float $promedio): array
    {
        $minimo = max(1, (int) floor($promedio * (1 - self::VARIACION_PERDIDAS_POR_ORDEN)));
        $maximo = max($minimo, (int) ceil($promedio * (1 + self::VARIACION_PERDIDAS_POR_ORDEN)));
        $totalObjetivo = max($cantidadOrdenes, (int) round($promedio * $cantidadOrdenes));

        $perdidas = [];

        for ($i = 0; $i < $cantidadOrdenes; $i++) {
            $perdidas[] = mt_rand($minimo, $maximo);
        }

        $diferencia = $totalObjetivo - array_sum($perdidas);

        while ($diferencia !== 0) {
            $candidatos = [];

            foreach ($perdidas as $indice => $valor) {
                $puedeAumentar = $diferencia > 0 && $valor < $maximo;
                $puedeReducir = $diferencia < 0 && $valor > $minimo;

                if ($puedeAumentar || $puedeReducir) {
                    $candidatos[] = $indice;
                }
            }

            if ($candidatos === []) {
                break;
            }

            $indice = $candidatos[mt_rand(0, count($candidatos) - 1)];
            $perdidas[$indice] += $diferencia > 0 ? 1 : -1;
            $diferencia += $diferencia > 0 ? -1 : 1;
        }

        return $perdidas;
    }

    /**
     * Crea una OP, sus recepciones de calidad y las mermas correspondientes.
     *
     * @param  list<int>  $usuarios
     * @param  list<int>  $talleres
     * @param  array<int, float>  $costosGenericos
     */
    private function crearOpConPerdidas(
        int $productoId,
        int $perdidas,
        array $usuarios,
        array $talleres,
        int $estadoRecibiendo,
        array &$costosGenericos,
    ): void {
        $filas = $this->filasRecepcion($perdidas);
        $cantidadPlanificada = array_sum(array_column($filas, 'esperada'));
        $responsableId = $usuarios !== [] ? $usuarios[mt_rand(0, count($usuarios) - 1)] : null;

        $op = new OrdenProduccion();
        $op->forceFill([
            'numero_op' => OrdenProduccion::siguienteNumeroOp(),
            'producto_referencia_id' => $productoId,
            'cantidad_planificada' => $cantidadPlanificada,
            'costo_proceso' => 0,
            'costo_total' => 0,
            'fecha_prevista_envio_taller' => now()->addDays(mt_rand(5, 30))->toDateString(),
            'taller_id' => $talleres !== [] ? $talleres[mt_rand(0, count($talleres) - 1)] : null,
            'tipo' => 'taller',
            'externa' => false,
            'estado_op_id' => $estadoRecibiendo,
            'user_id' => $responsableId,
            'observaciones' => 'OP generada por OrdenesPerdidasSeeder para probar el reporte de Merma.',
        ])->save();

        $op->productos()->sync([$productoId]);

        foreach ($filas as $fila) {
            $distribucion = Distribucion_por_tallas::create([
                'producto_id' => $productoId,
                'talla' => $fila['talla'],
                'cantidad_planificada' => $fila['esperada'],
            ]);
            $op->distribucionesPorTallas()->attach($distribucion->id);
        }

        foreach ($filas as $fila) {
            RecepcionCalidad::create([
                'orden_produccion_id' => $op->id,
                'producto_referencia_id' => $productoId,
                'talla' => $fila['talla'],
                'cantidad_esperada' => $fila['esperada'],
                'cantidad_recibida' => $fila['recibida'],
                'cantidad_aprobada' => $fila['aprobada'],
                'cantidad_en_reparacion' => $fila['reparacion'],
                'cantidad_no_recuperable' => $fila['noRecuperable'],
                'fecha_recepcion' => $fila['fecha'],
                'observaciones' => $fila['observaciones'],
                'fecha_inicio_revision' => $fila['fecha']->copy()->subHours(mt_rand(2, 8)),
                'fecha_fin_revision' => $fila['fecha'],
            ]);
        }

        app(MermaRecepcionService::class)->sincronizar($op, $responsableId);

        $costoUnitario = (float) Merma::query()
            ->where('orden_produccion_id', $op->id)
            ->value('costo_unitario');

        if ($costoUnitario <= 0) {
            $costoUnitario = (float) ($costosGenericos[$productoId] ?? 0);
        }

        if ($costoUnitario <= 0) {
            $costoUnitario = round(mt_rand(self::COSTO_UNITARIO_MINIMO, self::COSTO_UNITARIO_MAXIMO), 2);
            $costosGenericos[$productoId] = $costoUnitario;
        }

        Merma::query()
            ->where('orden_produccion_id', $op->id)
            ->update([
                'costo_unitario' => $costoUnitario,
                'costo_total_perdida' => DB::raw('cantidad * ' . $costoUnitario),
            ]);
    }




    /**
     * Construye filas de recepción coherentes. La suma de aprobada, reparación y
     * no recuperable siempre coincide con la cantidad recibida.
     *
     * @return list<array<string, mixed>>
     */
    private function filasRecepcion(int $perdidas): array
    {
        $cantidadTallas = mt_rand(self::TALLAS_POR_ORDEN_MINIMA, self::TALLAS_POR_ORDEN_MAXIMA);
        $tallas = [];
        $tallasDisponibles = self::TALLAS_POSIBLES;

        while (count($tallas) < $cantidadTallas) {
            $indice = mt_rand(0, count($tallasDisponibles) - 1);
            $tallas[] = $tallasDisponibles[$indice];
            array_splice($tallasDisponibles, $indice, 1);
        }

        $filas = [];

        $perdidasPorTalla = $this->repartirPerdidasPorTalla($perdidas, count($tallas));

        foreach ($tallas as $indice => $talla) {
            $noRecuperable = $perdidasPorTalla[$indice] ?? 0;

            if ($noRecuperable > 0) {
                $porcentaje = self::PROMEDIO_PORCENTAJE_PERDIDAS
                    * (1 + ((mt_rand(-100, 100) / 100) * self::VARIACION_PORCENTAJE_PERDIDAS));
                $porcentaje = max(0.01, min(0.95, $porcentaje));
                $esperada = max($noRecuperable, (int) ceil($noRecuperable / $porcentaje));
                $noRecibidas = mt_rand(0, (int) floor($esperada * 0.10));
                $recibida = $esperada - $noRecibidas;
                $disponiblesParaReparacion = max(0, $recibida - $noRecuperable);
                $reparacion = mt_rand(0, (int) floor($disponiblesParaReparacion * 0.15));
                $aprobada = $recibida - $noRecuperable - $reparacion;
                $observaciones = 'Pieza no recuperable detectada durante la recepción de calidad.';
            } else {
                $esperada = mt_rand(self::ESPERADA_SIN_PERDIDA_MINIMA, self::ESPERADA_SIN_PERDIDA_MAXIMA);
                $recibida = $esperada;
                $aprobada = $recibida;
                $reparacion = 0;
                $observaciones = 'Recepción de calidad sin pérdidas.';
            }

            $filas[] = [
                'talla' => (string) $talla,
                'esperada' => $esperada,
                'recibida' => $recibida,
                'aprobada' => $aprobada,
                'reparacion' => $reparacion,
                'noRecuperable' => $noRecuperable,
                'fecha' => now()->subDays(mt_rand(0, self::DIAS_HISTORICO))->setTime(mt_rand(8, 17), mt_rand(0, 59)),
                'observaciones' => $observaciones,
            ];
        }

        return $filas;
    }

    /**
     * Reparte las piezas no recuperables entre las tallas. La suma se conserva.
     *
     * @return list<int>
     */
    private function repartirPerdidasPorTalla(int $perdidas, int $cantidadTallas): array
    {
        $reparto = array_fill(0, $cantidadTallas, 0);

        if ($perdidas <= 0 || $cantidadTallas <= 0) {
            return $reparto;
        }

        $conPerdidas = min($perdidas, $cantidadTallas);
        $indices = array_keys($reparto);
        shuffle($indices);

        foreach (array_slice($indices, 0, $conPerdidas) as $indice) {
            $reparto[$indice] = 1;
        }

        $restantes = $perdidas - $conPerdidas;

        while ($restantes > 0) {
            $indice = $indices[mt_rand(0, count($indices) - 1)];
            $reparto[$indice]++;
            $restantes--;
        }

        return $reparto;
    }
}
