<?php

namespace Alejo\Notificaciones;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Capsule\Manager as Capsule;

class NotificacionesManager
{
    protected $table;

    public function __construct($table)
    {
        $this->table = $table;
    }

    public function crear(string $mensaje, string $prioridad = 'baja'): Model
    {
        $notificacion = new Model();
        $notificacion->setTable($this->table);
        $notificacion->mensaje = $mensaje;
        $notificacion->prioridad = $prioridad;
        $notificacion->leida = false;
        $notificacion->save();

        return $notificacion;
    }

    public function noLeidas(): \Illuminate\Database\Eloquent\Collection
    {
        return Capsule::table($this->table)
            ->where('leida', false)
            ->orderBy('prioridad', 'desc')
            ->get();
    }

    public function marcarComoLeida(int $id): bool
    {
        return Capsule::table($this->table)
            ->where('id', $id)
            ->update(['leida' => true]);
    }

    public function todasLeidas(): bool
    {
        return Capsule::table($this->table)
            ->where('leida', false)
            ->doesNotExist();
    }
}