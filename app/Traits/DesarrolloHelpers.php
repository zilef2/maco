<?php

namespace App\Traits;

use App\Models\desarrollo;
use App\helpers\Myhelp;
use App\Models\pagodesarrollo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


trait DesarrolloHelpers
{
    public array $thisAtributos;

    public string $FromController = 'desarrollo';

    public array $estados = [
        'Cotizando',
        'Desarrollando', // 1
        'Esperando pago parcial',
        'Pagada totalmente', // 3
        'Finalizada',
    ];

    public $breadcrumbs;

    public function __construct()
    {
        $this->thisAtributos = (new desarrollo)->getFillable(); // not using
        $this->breadcrumbs = [
            [
                'label' => __('app.label.'.$this->FromController),
                'href' => route($this->FromController.'.index'),
            ],
        ];
    }


    public function updatePago(Request $request, $id): RedirectResponse
    {
        $permissions = Myhelp::EscribirEnLog($this, ' Begin UPDATE:desarrollos');
        DB::beginTransaction();
        $desarrollo = desarrollo::findOrFail($id);
        if ($request->estado && gettype($request->estado) !== 'string' && $request->estado['value']) {
            $desarrollo->update(['estado' => $request->estado['value']]);
        }

        $numCuotas = pagodesarrollo::Where('desarrollo_id', $desarrollo->id)->count();

        pagodesarrollo::create([
            'valor' => $request->valor,
            'fecha' => $request->fecha,
            'cuota' => $numCuotas + 1,
            'final' => 0,
            'desarrollo_id' => $desarrollo->id,
        ]);
        DB::commit();
        Myhelp::EscribirEnLog($this, 'UPDATE2:desarrollos EXITOSO', 'desarrollo id:'.$desarrollo->id.' | '.$desarrollo->nombre, false);

        return back()->with('success', __('app.label.updated_successfully2', ['nombre' => $desarrollo->nombre]));
    }

    public function notifyDelay(Request $request): RedirectResponse
    {
        
        $detalle = [ 'mensaje' => 'El pago se ha retrasado mucho, por favor comuníquese a la brevedad.' ];
        $esProduccion = App::environment('production');
        $yomismo = User::find(1)->email;
        if ($esProduccion) {
            $jefe = \App\Models\User::Where('name', 'Carlos Daniel Anaya Barrios')->first();
            if ($jefe) {
                EnviarViaticoJob::dispatch($jefe->email, $detalle);
                EnviarViaticoJob::dispatch($yomismo, $detalle)->delay(now()->addSeconds(3));
                $mensaje = 'Correo de retraso enviado y acceso a usuarios denegado.';
            }
        } else {
            EnviarViaticoJob::dispatch($yomismo, $detalle)->delay(now()->addSeconds(2));
            $mensaje = 'Correo de retraso enviado y acceso a usuarios denegado.';
        }

        \Illuminate\Support\Facades\Cache::put('block_user_access', true);

        return back()->with('success', $mensaje);
    }

    public function allowUserAccess(): RedirectResponse
    {
        \Illuminate\Support\Facades\Cache::forget('block_user_access');

        return back()->with('success', 'Acceso a usuarios restaurado con normalidad.');
    }
}
