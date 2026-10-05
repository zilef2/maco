<?php

namespace Alejo\Notificaciones;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Facades\DB;

class NotificacionesManager
{
    protected string $table;
    protected ?int $targetUserId = null;

    public function __construct(string $table = 'notificaciones')
    {
        $this->table = $table;
    }

    public function paraUsuario(?int $userId): self
    {
        $clone = clone $this;
        $clone->targetUserId = $userId;
        return $clone;
    }

    public function paraTodos(): self
    {
        $clone = clone $this;
        $clone->targetUserId = null;
        return $clone;
    }

    public function crearPara(int|object $user, string $mensaje, string $prioridad = 'baja'): object
    {
        $userId = is_object($user) ? ($user->id ?? null) : (int) $user;
        return $this->crear($mensaje, $prioridad, $userId);
    }

    public function crear(string $mensaje, string $prioridad = 'baja', ?int $userId = null): object
    {
        $targetUser = $userId ?? $this->targetUserId;
        $prioridadNormalizada = match (strtolower($prioridad)) {
            'alto' => 'alta',
            'alta' => 'alta',
            'medio' => 'media',
            'media' => 'media',
            default => 'baja',
        };

        $id = DB::table($this->table)->insertGetId([
            'user_id'    => $targetUser,
            'mensaje'    => $mensaje,
            'prioridad'  => $prioridadNormalizada,
            'leida'      => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table($this->table)->find($id);
    }

    public function noLeidas(?int $userId = null): \Illuminate\Support\Collection
    {
        return DB::table($this->table)
            ->where('leida', false)
            ->when($userId, function ($query, $userId) {
                return $query->where(function ($q) use ($userId) {
                    $q->where('user_id', $userId)
                      ->orWhereNull('user_id');
                });
            })
            ->orderByRaw("CASE WHEN prioridad = 'alta' THEN 1 WHEN prioridad = 'media' THEN 2 ELSE 3 END")
            ->orderByDesc('created_at')
            ->get();
    }

    public function marcarComoLeida(int $id): bool
    {
        return (bool) DB::table($this->table)
            ->where('id', $id)
            ->update(['leida' => true]);
    }

    public function todasLeidas(?int $userId = null): bool
    {
        return DB::table($this->table)
            ->where('leida', false)
            ->when($userId, function ($query, $userId) {
                return $query->where(function ($q) use ($userId) {
                    $q->where('user_id', $userId)
                      ->orWhereNull('user_id');
                });
            })
            ->doesNotExist();
    }
}