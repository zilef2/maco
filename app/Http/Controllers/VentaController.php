<?php

namespace App\Http\Controllers;

use App\helpers\Myhelp;
use App\helpers\MyModels;
use App\Models\Distribucion_por_tallas;
use App\Models\Producto;
use App\Models\ProductoMovimiento;
use App\Models\Venta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class VentaController extends Controller
{
    /**
     * Historial de ventas: cada fila de la página es una línea vendida
     * (producto y talla), con la venta a la que pertenece.
     */
    public function index(): Response
    {
        $numberPermissions = MyModels::getPermissionToNumber(Myhelp::EscribirEnLog($this, ' Ventas '));

        $ventas = Venta::query()
            ->with([
                'detalles.producto:id,Codigo_interno,Titulo_Shopify',
                'user:id,name',
            ])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Venta $venta) => [
                'id' => $venta->id,
                'fecha' => $venta->fecha?->format('Y-m-d'),
                'forma_pago' => $venta->forma_pago,
                'forma_pago_detalle' => $venta->forma_pago_detalle,
                'subtotal' => (float) $venta->subtotal,
                'descuento_monto' => (float) $venta->descuento_monto,
                'impuesto_monto' => (float) $venta->impuesto_monto,
                'total' => (float) $venta->total,
                'observaciones' => $venta->observaciones,
                'registrado_por' => $venta->user?->name,
                'detalles' => $venta->detalles->map(fn ($detalle) => [
                    'id' => $detalle->id,
                    'producto_id' => $detalle->producto_id,
                    'codigo' => $detalle->producto?->Codigo_interno,
                    'titulo' => $detalle->producto?->Titulo_Shopify,
                    'talla' => $detalle->talla,
                    'cantidad' => (int) $detalle->cantidad,
                    'precio_unitario' => (float) $detalle->precio_unitario,
                    'total' => (float) $detalle->total,
                ])->values()->all(),
            ])
            ->all();

        return Inertia::render('Venta/Index', [
            'ventas' => $ventas,
            'breadcrumbs' => [['label' => __('app.label.Venta'), 'href' => route('Venta.index')]],
            'title' => __('app.label.Venta'),
            'numberPermissions' => $numberPermissions,
        ]);
    }

    /**
     * Registra la venta de uno o varios productos terminados.
     *
     * Cada línea vende una talla concreta y descuenta la existencia recibida
     * de esa talla (cantidad_recibida_buena), dejando su ProductoMovimiento.
     * El subtotal, el descuento, el impuesto y el ingreso final se calculan
     * aquí desde el detalle; el navegador solo los muestra.
     */
    public function store(Request $request): RedirectResponse
    {
        Myhelp::EscribirEnLog($this, ' Begin STORE:Venta');

        $datos = $request->validate([
            'forma_pago' => ['required', 'in:contado,credito,otro'],
            'forma_pago_detalle' => ['nullable', 'string', 'max:100', 'required_if:forma_pago,otro'],
            'descuento_tipo' => ['required', 'in:monto,porcentaje'],
            'descuento_valor' => ['nullable', 'numeric', 'min:0'],
            'impuesto_tipo' => ['required', 'in:monto,porcentaje'],
            'impuesto_valor' => ['nullable', 'numeric', 'min:0'],
            'observaciones' => ['nullable', 'string', 'max:500'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
            'detalles.*.talla' => ['required', 'string', 'max:50'],
            'detalles.*.cantidad' => ['required', 'integer', 'min:1'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'min:0.01'],
        ]);

        $descuentoValor = round((float) ($datos['descuento_valor'] ?? 0), 2);
        $impuestoValor = round((float) ($datos['impuesto_valor'] ?? 0), 2);

        if ($datos['descuento_tipo'] === 'porcentaje' && $descuentoValor > 100) {
            return back()->withErrors(['descuento_valor' => 'El descuento no puede superar el 100%.']);
        }
        if ($datos['impuesto_tipo'] === 'porcentaje' && $impuestoValor > 100) {
            return back()->withErrors(['impuesto_valor' => 'El impuesto no puede superar el 100%.']);
        }

        // Si el mismo producto y talla se repite, se suma la cantidad y se
        // conserva el último precio escrito.
        $lineas = collect($datos['detalles'])
            ->filter(fn ($fila) => !empty($fila['producto_id']) && (int) ($fila['cantidad'] ?? 0) > 0)
            ->map(fn ($fila) => [
                'producto_id' => (int) $fila['producto_id'],
                'talla' => trim((string) $fila['talla']),
                'cantidad' => (int) $fila['cantidad'],
                'precio_unitario' => round((float) $fila['precio_unitario'], 2),
            ])
            ->groupBy(fn ($fila) => $fila['producto_id'] . '|' . $fila['talla'])
            ->map(fn ($filas) => [
                'producto_id' => (int) $filas->first()['producto_id'],
                'talla' => (string) $filas->first()['talla'],
                'cantidad' => (int) $filas->sum('cantidad'),
                'precio_unitario' => (float) $filas->last()['precio_unitario'],
            ])
            ->sortBy([
                ['producto_id', 'asc'],
                ['talla', 'asc'],
            ])
            ->values()
            ->all();

        if (empty($lineas)) {
            return back()->withErrors(['detalles' => 'Agrega al menos un producto con cantidad.']);
        }

        $subtotal = round(collect($lineas)->sum(
            fn ($linea) => $linea['cantidad'] * $linea['precio_unitario']
        ), 2);

        $descuentoMonto = $datos['descuento_tipo'] === 'porcentaje'
            ? round($subtotal * $descuentoValor / 100, 2)
            : min($descuentoValor, $subtotal);
        $impuestoMonto = $datos['impuesto_tipo'] === 'porcentaje'
            ? round($subtotal * $impuestoValor / 100, 2)
            : $impuestoValor;

        $total = max(0, round($subtotal - $descuentoMonto + $impuestoMonto, 2));

        DB::beginTransaction();
        try {
            // Existencia por producto y talla: suma de lo recibido en todas las
            // distribuciones de esa talla. Se bloquea antes de crear la venta.
            $existencias = [];
            foreach ($lineas as $linea) {
                $existencias[$linea['producto_id'] . '|' . $linea['talla']] = Distribucion_por_tallas::query()
                    ->where('producto_id', $linea['producto_id'])
                    ->where('talla', $linea['talla'])
                    ->lockForUpdate()
                    ->orderBy('id')
                    ->get();
            }

            foreach ($lineas as $linea) {
                $disponible = (int) $existencias[$linea['producto_id'] . '|' . $linea['talla']]
                    ->sum(fn ($fila) => (int) ($fila->cantidad_recibida_buena ?? 0));

                if ($disponible < $linea['cantidad']) {
                    $producto = Producto::whereKey($linea['producto_id'])->value('Codigo_interno');
                    $nombre = $producto ?: 'producto #' . $linea['producto_id'];
                    DB::rollBack();

                    return back()->withErrors([
                        'detalles' => "No hay existencia suficiente de {$nombre} talla {$linea['talla']}: "
                            . "disponible {$disponible}, solicitado {$linea['cantidad']}.",
                    ]);
                }
            }

            $venta = Venta::create([
                'fecha' => now()->toDateString(),
                'forma_pago' => $datos['forma_pago'],
                'forma_pago_detalle' => $datos['forma_pago'] === 'otro'
                    ? trim((string) ($datos['forma_pago_detalle'] ?? ''))
                    : null,
                'descuento_tipo' => $datos['descuento_tipo'],
                'descuento_valor' => $descuentoValor,
                'descuento_monto' => $descuentoMonto,
                'impuesto_tipo' => $datos['impuesto_tipo'],
                'impuesto_valor' => $impuestoValor,
                'impuesto_monto' => $impuestoMonto,
                'subtotal' => $subtotal,
                'total' => $total,
                'observaciones' => $datos['observaciones'] ?? null,
                'user_id' => Auth::id(),
            ]);

            foreach ($lineas as $linea) {
                $venta->detalles()->create([
                    'producto_id' => $linea['producto_id'],
                    'talla' => $linea['talla'],
                    'cantidad' => $linea['cantidad'],
                    'precio_unitario' => $linea['precio_unitario'],
                    'total' => round($linea['cantidad'] * $linea['precio_unitario'], 2),
                ]);

                $this->descontarExistencia($linea, $existencias[$linea['producto_id'] . '|' . $linea['talla']], $venta->id);
            }

            DB::commit();
            Myhelp::EscribirEnLog(
                $this,
                'STORE:Venta EXITOSO',
                'Venta id:' . $venta->id . ' | total:' . $total . ' | ' . count($lineas) . ' línea(s)',
                false
            );

            return back()->with('success', 'Venta registrada por $' . number_format($total, 2, ',', '.') . '.');
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Descuenta la cantidad vendida de las filas de existencia recibida de la
     * talla y deja el movimiento de salida. Las filas ya vienen bloqueadas.
     */
    private function descontarExistencia(array $linea, $filas, int $ventaId): void
    {
        $restante = (int) $linea['cantidad'];

        foreach ($filas as $fila) {
            if ($restante <= 0) {
                break;
            }

            $anterior = (int) ($fila->cantidad_recibida_buena ?? 0);
            if ($anterior <= 0) {
                continue;
            }

            $descontar = min($anterior, $restante);
            $nueva = $anterior - $descontar;

            $fila->update(['cantidad_recibida_buena' => $nueva]);

            ProductoMovimiento::registrar(
                (int) $linea['producto_id'],
                (string) $linea['talla'],
                $anterior,
                $nueva,
                null,
                'Venta #' . $ventaId
            );

            $restante -= $descontar;
        }
    }
}
