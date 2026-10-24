<?php

use Illuminate\Support\Facades\Route;
// use App\Http\Controllers\DesarrolloController;

Route::middleware(['auth', 'verified', 'role:superadmin'])->group(function () {
    Route::get('/notificar-prueba', function (\Illuminate\Http\Request $request) {
        $noti = app(\Alejo\Notificaciones\NotificacionesManager::class);
        $noti->crear('Nuevo pedido recibido #1001', 'alta', $request->user()?->id);
        
        return 'Notificación enviada correctamente';
    })->name('notificar.prueba');
});


	// Route::get('/IndexTrashed', [UserController::class, 'IndexTrashed'])->name('IndexTrashed');
