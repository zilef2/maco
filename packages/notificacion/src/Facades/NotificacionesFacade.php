<?php

namespace Alejo\Notificaciones\Facades;

use Illuminate\Support\Facades\Facade;

class NotificacionesFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'notificaciones';
    }
}