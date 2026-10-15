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
	Route::post("/Producto/upload-excel", [\App\Http\Controllers\ProductoController::class, 'uploadExcel'])->name('Producto.upload-excel');
	Route::get('/notificar-prueba', function (\Illuminate\Http\Request $request) {
            $noti = app(\Alejo\Notificaciones\NotificacionesManager::class);
    		$noti->crear('Nuevo pedido recibido #1001', 'alta', $request->user()?->id);

            return 'Notificación enviada correctamente';
	})->name('notificar.prueba');
	Route::resource("/Talla", \App\Http\Controllers\TallaController::class);
	Route::resource("/OrdenProduccion", \App\Http\Controllers\OrdenProduccionController::class);
	Route::put("/OrdenProduccion/{id}/distribucion", [\App\Http\Controllers\OrdenProduccionController::class, 'syncDistribucion'])->name('OrdenProduccion.distribucion');
	Route::put("/OrdenProduccion/{id}/estado", [\App\Http\Controllers\OrdenProduccionController::class, 'cambiarEstado'])->name('OrdenProduccion.cambiarEstado');
	Route::resource("/ProcesoExterno", \App\Http\Controllers\ProcesoExternoController::class);
	Route::resource("/KitTaller", \App\Http\Controllers\KitTallerController::class);
	Route::post("/KitTaller/upload-excel", [\App\Http\Controllers\KitTallerController::class, 'uploadExcel'])->name('KitTaller.upload-excel');
	Route::get("/KitTaller/por-orden/{id}", [\App\Http\Controllers\KitTallerController::class, 'porOrden'])->name('KitTaller.por-orden');
	Route::resource("/RecepcionCalidad", \App\Http\Controllers\RecepcionCalidadController::class);
	Route::resource("/CierreOrdenProduccion", \App\Http\Controllers\CierreOrdenProduccionController::class);
	Route::resource("/EstadoOperacion", \App\Http\Controllers\EstadoOperacionController::class);
	Route::post("/EstadoOperacion/upload-excel", [\App\Http\Controllers\EstadoOperacionController::class, 'uploadExcel'])->name('EstadoOperacion.upload-excel');
	Route::resource("/FichaTecnicaMateriales", \App\Http\Controllers\FichaTecnicaMaterialesController::class);
	Route::resource("/PendientesProduccion", \App\Http\Controllers\PendientesProduccionController::class);
	Route::resource("/Taller", \App\Http\Controllers\TallerController::class);
	Route::resource("/Proveedor", \App\Http\Controllers\ProveedorController::class);
	Route::resource("/Distribucion_por_tallas", \App\Http\Controllers\Distribucion_por_tallasController::class);
	Route::resource("/Material", \App\Http\Controllers\MaterialController::class);
	Route::post("/Material/upload-excel", [\App\Http\Controllers\MaterialController::class, 'uploadExcel'])->name('Material.upload-excel');
	Route::get('/Material/{id}/movimientos', [\App\Http\Controllers\MaterialController::class, 'historicoMovimientos'])->name('Material.movimientos');
	Route::resource("/GrupoTalla", \App\Http\Controllers\GrupoTallaController::class);
	Route::get("/ImportarExcel", [\App\Http\Controllers\ImportarExcelController::class, 'index'])->name('ImportarExcel.index');
	//aquipues
}); //fin verified



// <editor-fold desc="Artisan">
require __DIR__.'/ArtisanZilef.php';
require __DIR__.'/Routes_entrega1.php';
