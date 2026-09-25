<?php

use Illuminate\Support\Facades\Route;
use Alejo\Notificaciones\Http\Controllers\NotificacionController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/notificaciones', [NotificacionController::class, 'index'])->name('notificaciones.index');
    Route::post('/notificaciones/marcar/{id}', [NotificacionController::class, 'marcarComoLeida'])->name('notificaciones.marcar-como-leida');
    Route::post('/notificaciones/marcar-todas', [NotificacionController::class, 'marcarTodas'])->name('notificaciones.marcar-todas');
});