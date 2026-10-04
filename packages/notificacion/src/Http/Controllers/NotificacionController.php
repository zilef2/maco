<?php

namespace Alejo\Notificaciones\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Alejo\Notificaciones\NotificacionesManager;
use Illuminate\Support\Facades\DB;

class NotificacionController extends Controller
{
    protected $manager;

    public function __construct(NotificacionesManager $manager)
    {
        $this->manager = $manager;
    }

    public function index(Request $request)
    {
        // $usuarioId = $request->user()?->id;

        $notificaciones = $this->manager->noLeidas()->take(5);

        return response()->json([
            'notificaciones' => $notificaciones->map(function ($n) {
                return [
                    'id' => $n->id,
                    'mensaje' => $n->mensaje,
                    'prioridad' => $n->prioridad,
                    'created_at' => $n->created_at,
                ];
            })->values(),
        ]);
    }

    public function marcarComoLeida(Request $request, $id)
    {
        $this->manager->marcarComoLeida((int) $id);

        return response()->json(['success' => true]);
    }

    public function marcarTodas(Request $request)
    {
        $this->manager->todasLeidas(); // This doesn't do much, let's update directly

        DB::table('notificaciones')
            ->where('leida', false)
            ->update(['leida' => true]);

        return response()->json(['success' => true]);
    }
}