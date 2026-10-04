<?php

namespace Alejo\Notificaciones;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;

class NotificacionesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NotificacionesManager::class, function () {
            return new NotificacionesManager('notificaciones');
        });

        $this->app->alias(NotificacionesManager::class, 'notificaciones');
    }

public function boot(): void
    {
        // 1. Cargar las rutas del paquete automáticamente
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        // 2. Permitir publicar migraciones y componentes
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/migrations' => database_path('migrations'),
            ], 'notificaciones-migrations');
            $this->publishes([
                __DIR__ . '/../resources/views/components' => resource_path('js/Components/Notificaciones'),
            ], 'notificaciones-vue');
        }
    }
}