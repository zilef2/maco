<?php

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';

use Alejo\Notificaciones\NotificacionesManager;
use App\Http\Controllers\{ LocaleController, NotificacionesController, RoleController, ParametrosController, PermissionController};
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::post('/locale/{locale}', [LocaleController::class, 'update'])->name('locale.update');

// Route::get('/', function () { return Inertia::render('Welcome'); })->name('home');
Route::get('/', function () {
    return redirect()->route('login');
})->name('home');



Route::get('dashboard', function () {
	return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth', 'verified')->group(function () {
	Route::resource('/role', RoleController::class)->except('create', 'show', 'edit');
	Route::post('/role/destroy-bulk', [RoleController::class, 'destroyBulk'])->name('role.destroy-bulk');
	Route::resource('/permission', PermissionController::class)->except('create', 'show', 'edit');
	Route::post('/permission/store-modulo', [PermissionController::class, 'storeModulo'])->name('permission.store-modulo');
	Route::post('/permission/destroy-bulk', [PermissionController::class, 'destroyBulk'])->name('permission.destroy-bulk');
	Route::resource('/Parametros', ParametrosController::class);
	Route::post('/Parametros/lista', [ParametrosController::class, 'storeLista'])->name('parametros.lista.store');
	
	//<editor-fold desc="User">
	Route::resource('/user', UserController::class);
	// Route::resource('/user', UserController::class)->except('create', 'show', 'edit');
	Route::post('/user/destroy-bulk', [UserController::class, 'destroyBulk'])->name('user.destroy-bulk');
//	Route::get('/IndexTrashed', [UserController::class, 'IndexTrashed'])->name('IndexTrashed');
	
    

	Route::resource("/Presupuesto", \App\Http\Controllers\PresupuestoController::class);
    require __DIR__.'/pagoszilef.php';

	Route::resource("/materias_primas", \App\Http\Controllers\Materias_primasController::class);
	Route::get('/materias_primas/{id}/movimientos', [\App\Http\Controllers\Materias_primasController::class, 'historicoMovimientos'])->name('materias_primas.movimientos');
	Route::resource("/Producto", \App\Http\Controllers\ProductoController::class);
	Route::resource("/Producto", \App\Http\Controllers\ProductoController::class);
	Route::get('/notificar-prueba', function (){
            $noti = new NotificacionesManager('notificaciones');
    		$noti->crear('Nuevo pedido recibido', 'alto');

            return 'Notificacion enviada';
	})->name('notificar.prueba');
	//aquipues
}); //fin verified



// <editor-fold desc="Artisan">
require __DIR__.'/ArtisanZilef.php';
require __DIR__.'/Routes_entrega1.php';
