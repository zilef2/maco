<?php

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';

use App\Http\Controllers\{ LocaleController, ProveedorController, RoleController, ParametrosController, PermissionController};
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Artisan;
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
	//aquipues
}); //fin verified



// <editor-fold desc="Artisan">
Route::get('/exception', function () {
    throw new Exception('Probandof excepciones y enrutamiento. La prueba ha concluido exitosamente.');
});

Route::get('/clear-c', function () {
    // Artisan::call('optimize');
    Artisan::call('optimize:clear');

    return 'Optimizacion finalizada';
    // throw new Exception('Optimizacion finalizada!');
});
Route::get('/back-up', function () {
    $result = Artisan::call('backup:run');
    $output = Artisan::output();
    if ($result === 0) {
        // Éxito
        return response()->json(['status' => 'success', 'message' => 'Backup completed successfully!', 'output' => $output]);
    } else {
        // Error
        return response()->json(['status' => 'error', 'message' => 'Backup failed!', 'output' => $output]);
        //         throw new Exception('Backup failed!'. $output);
    }
});

Route::get('/test-email', function () {
    try {
        \Illuminate\Support\Facades\Mail::raw('Este es un correo de prueba.', function ($message) {
            $message->to('ajelof2@gmail.com')
                ->subject('Correo de prueba');
        });
        return 'Correo enviado con éxito.';
    } catch (\Exception $e) {
        return 'Error al enviar el correo: ' . $e->getMessage();
    }
});
//</editor-fold>