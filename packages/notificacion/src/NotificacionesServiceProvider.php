<?php

namespace Alejo\Notificaciones;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;

class NotificacionesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind('notificaciones', function () {
            return new NotificacionesManager($this->app['db']->connection('default')->table('notificaciones'));
        });
    }

    public function boot(): void
    {
        if (! $this->app->routesAreCached()) {
            $this->publishes([
                __DIR__ . '/migrations' => database_path('migrations'),
            ], ['notificaciones-migrations']);

            $this->routes(__DIR__ . '/routes/web.php');

            Schema::defaultStringLength(191);
        }
    }
}