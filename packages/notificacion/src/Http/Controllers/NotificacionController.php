<?php

namespace Alejo\Notificaciones\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Alejo\Notificaciones\NotificacionesManager;

class NotificacionController extends Controller
{
    /**
     * Tipos de notificación con campana propia; el filtro se aplica sobre el
     * mensaje porque la tabla no guarda una columna de tipo.
     */
    private const FILTROS = [
        'material_insuficiente' => 'material insuficiente',
    ];

    protected $manager;

    public function __construct(NotificacionesManager $manager)
    {
        $this->manager = $manager;
    }

    private function filtro(Request $request): ?string
    {
        return self::FILTROS[$request->query('tipo')] ?? null;
    }

    public function index(Request $request)
    {
        $usuarioId = $request->user()?->id;

        $notificaciones = $this->manager->noLeidas($usuarioId, $this->filtro($request))->take(10);

        return response()->json([
            'notificaciones' => $notificaciones->map(function ($n) {
                return [
                    'id' => $n->id,
                    'user_id' => $n->user_id,
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
        $this->manager->marcarTodas($request->user()?->id, $this->filtro($request));

        return response()->json(['success' => true]);
    }
}