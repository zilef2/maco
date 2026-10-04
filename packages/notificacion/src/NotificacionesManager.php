<?php

namespace Alejo\Notificaciones;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Facades\DB;

class NotificacionesManager
{
    protected string $table;

    public function __construct(string $table = 'notificaciones')
    {
        $this->table = $table;
    }

    public function crear(string $mensaje, string $prioridad = 'baja'): object
    {
        $id = DB::table($this->table)->insertGetId([
            'mensaje'    => $mensaje,
            'prioridad'  => $prioridad,
            'leida'      => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table($this->table)->find($id);
    }

    public function noLeidas(): \Illuminate\Support\Collection
    {
        return DB::table($this->table)
            ->where('leida', false)
            ->orderBy('prioridad', 'desc')
            ->get();
    }

    public function marcarComoLeida(int $id): bool
    {
        return (bool) DB::table($this->table)
            ->where('id', $id)
            ->update(['leida' => true]);
    }

    public function todasLeidas(): bool
    {
        return DB::table($this->table)
            ->where('leida', false)
            ->doesNotExist();
    }
}