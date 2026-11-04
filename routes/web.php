<?php

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';

use Alejo\Notificaciones\NotificacionesManager;
use App\Http\Controllers\{ DashboardController, NotificacionesController, RoleController, ParametrosController, PermissionController};
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Route::get('/', function () { return Inertia::render('Welcome'); })->name('home');
Route::get('/', function () {
    return redirect()->route('login');
})->name('home');



Route::get('dashboard', function () {
	return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('dashboard/datos', [DashboardController::class, 'datos'])
	->middleware(['auth', 'verified'])
	->name('dashboard.datos');

// Bitácora de navegación y tiempos entre acciones: solo superadmin.
Route::middleware(['auth', 'verified', 'role:superadmin'])->group(function () {
	Route::get('/Actividad', [\App\Http\Controllers\ActividadController::class, 'index'])->name('Actividad.index');
	Route::post('/superadmin/purgar-datos-prueba', [\App\Http\Controllers\SuperadminAccionesController::class, 'purgarDatosPrueba'])->name('superadmin.purgar-datos-prueba');
});

Route::middleware('auth', 'verified')->group(function () {
	// El navegador avisa cuando el usuario deja la pestaña: sin esto, una hora
	// sin usar el sistema se leía como una hora trabajando en ella.
	Route::post('/sesion/estado', [\App\Http\Controllers\SesionEstadoController::class, 'store'])->name('sesion.estado');

	Route::resource('/role', RoleController::class)->except('create', 'show', 'edit');
	Route::post('/role/destroy-bulk', [RoleController::class, 'destroyBulk'])->name('role.destroy-bulk');
	Route::resource('/permission', PermissionController::class)->except('create', 'show', 'edit');
	Route::post('/permission/store-modulo', [PermissionController::class, 'storeModulo'])->name('permission.store-modulo');
	Route::post('/permission/destroy-bulk', [PermissionController::class, 'destroyBulk'])->name('permission.destroy-bulk');
	Route::resource('/Parametros', ParametrosController::class);
	Route::post('/Parametros/lista', [ParametrosController::class, 'storeLista'])->name('parametros.lista.store');
	Route::post('/Parametros/costos-promedio', [ParametrosController::class, 'actualizarCostosPromedio'])->name('parametros.costos-promedio');
	Route::post('/Parametros/dias-prevision', [ParametrosController::class, 'actualizarDiasPrevision'])->name('parametros.dias-prevision');
	
	//<editor-fold desc="User">
	Route::resource('/user', UserController::class);
	Route::post('/user/destroy-bulk', [UserController::class, 'destroyBulk'])->name('user.destroy-bulk');
	Route::post('/user/upload-excel', [UserController::class, 'uploadExcel'])->name('user.upload-excel');
	

    require __DIR__.'/pagoszilef.php';
    require __DIR__.'/solozilef.php';

	Route::resource("/Producto", \App\Http\Controllers\ProductoController::class);
	Route::post("/Producto/upload-excel", [\App\Http\Controllers\ProductoController::class, 'uploadExcel'])->name('Producto.upload-excel');
	Route::post("/Producto/destroy-bulk", [\App\Http\Controllers\ProductoController::class, 'destroyBulk'])->name('Producto.destroy-bulk');
	Route::put("/Producto/{id}/distribucion", [\App\Http\Controllers\ProductoController::class, 'syncDistribucion'])->name('Producto.distribucion');
	Route::get("/Producto/{id}/tallas-history", [\App\Http\Controllers\ProductoController::class, 'tallasHistory'])->name('Producto.tallas-history');
	
	Route::resource("/Talla", \App\Http\Controllers\TallaController::class);
	Route::resource("/OrdenProduccion", \App\Http\Controllers\OrdenProduccionController::class);
	Route::put("/OrdenProduccion/{id}/distribucion", [\App\Http\Controllers\OrdenProduccionController::class, 'syncDistribucion'])->name('OrdenProduccion.distribucion');
	Route::put("/OrdenProduccion/{id}/estado", [\App\Http\Controllers\OrdenProduccionController::class, 'cambiarEstado'])->name('OrdenProduccion.cambiarEstado');
	Route::put("/OrdenProduccion/{id}/recepcion-borrador", [\App\Http\Controllers\OrdenProduccionController::class, 'guardarRecepcionBorrador'])->name('OrdenProduccion.recepcionBorrador');
	Route::post("/OrdenProduccion/borrador", [\App\Http\Controllers\OrdenProduccionController::class, 'guardarBorrador'])->name('OrdenProduccion.borrador');
	Route::get("/ProcesoExterno/materiales-op/{orden}", [\App\Http\Controllers\ProcesoExternoController::class, 'materialesOp'])->name('ProcesoExterno.materialesOp');
	Route::post("/ProcesoExterno/salida-op", [\App\Http\Controllers\ProcesoExternoController::class, 'salidaOp'])->name('ProcesoExterno.salidaOp');
	Route::resource("/ProcesoExterno", \App\Http\Controllers\ProcesoExternoController::class);
	Route::resource("/KitProceso", \App\Http\Controllers\KitProcesoController::class);
	Route::post("/KitProceso/upload-excel", [\App\Http\Controllers\KitProcesoController::class, 'uploadExcel'])->name('KitProceso.upload-excel');
	Route::get("/KitProceso/por-orden/{id}", [\App\Http\Controllers\KitProcesoController::class, 'porOrden'])->name('KitProceso.por-orden');
	Route::put("/KitProceso/por-orden/{id}", [\App\Http\Controllers\KitProcesoController::class, 'updatePorOrden'])->name('KitProceso.update-por-orden');
	Route::resource("/RecepcionCalidad", \App\Http\Controllers\RecepcionCalidadController::class);
	Route::resource("/CierreOrdenProduccion", \App\Http\Controllers\CierreOrdenProduccionController::class);
	Route::resource("/EstadoOperacion", \App\Http\Controllers\EstadoOperacionController::class);
	Route::post("/EstadoOperacion/upload-excel", [\App\Http\Controllers\EstadoOperacionController::class, 'uploadExcel'])->name('EstadoOperacion.upload-excel');
	Route::resource("/FichaTecnicaMateriales", \App\Http\Controllers\FichaTecnicaMaterialesController::class);
	Route::resource("/PendientesProduccion", \App\Http\Controllers\PendientesProduccionController::class);
	Route::resource("/Taller", \App\Http\Controllers\TallerController::class);
	Route::post("/Taller/upload-excel", [\App\Http\Controllers\TallerController::class, 'uploadExcel'])->name('Taller.upload-excel');
	Route::resource("/Proveedor", \App\Http\Controllers\ProveedorController::class);
	Route::post("/Proveedor/upload-excel", [\App\Http\Controllers\ProveedorController::class, 'uploadExcel'])->name('Proveedor.upload-excel');
	Route::resource("/Distribucion_por_tallas", \App\Http\Controllers\Distribucion_por_tallasController::class);
	Route::get("/Material/plantilla-excel", [\App\Http\Controllers\MaterialController::class, 'descargarPlantillaExcel'])->name('Material.plantilla-excel');
	Route::resource("/Material", \App\Http\Controllers\MaterialController::class);
	Route::post("/Material/upload-excel", [\App\Http\Controllers\MaterialController::class, 'uploadExcel'])->name('Material.upload-excel');
	Route::post("/Material/compra", [\App\Http\Controllers\MaterialController::class, 'registrarCompra'])->name('Material.compra');
	Route::get('/Material/{id}/predecir-compra', [\App\Http\Controllers\MaterialController::class, 'predecirCompra'])->name('Material.predecir-compra');
	Route::post("/Material/dependencias-bulk", [\App\Http\Controllers\MaterialController::class, 'dependenciasBulk'])->name('Material.dependencias-bulk');
	Route::post("/Material/destroy-bulk", [\App\Http\Controllers\MaterialController::class, 'destroyBulk'])->name('Material.destroy-bulk');
	Route::get('/Material/{id}/movimientos', [\App\Http\Controllers\MaterialController::class, 'historicoMovimientos'])->name('Material.movimientos');
	Route::resource("/GrupoTalla", \App\Http\Controllers\GrupoTallaController::class);
	Route::get("/ImportarExcel", [\App\Http\Controllers\ImportarExcelController::class, 'index'])->name('ImportarExcel.index');
	Route::post("/ImportarExcel/entidad", [\App\Http\Controllers\ImportarExcelController::class, 'toggleEntidad'])->name('ImportarExcel.toggleEntidad');
	Route::get("/ImportarExcel/plantilla/{clave}", [\App\Http\Controllers\ImportarExcelController::class, 'descargarPlantilla'])->name('ImportarExcel.plantilla');
	Route::resource("/EstadoOpMaterial", \App\Http\Controllers\EstadoOpMaterialController::class);
	Route::resource("/EmpresaExterna", \App\Http\Controllers\EmpresaExternaController::class);
	// La merma no se registra a mano: es el «No recuperable» de la recepción de la OP.
	Route::get("/Merma", [\App\Http\Controllers\MermaController::class, 'index'])->name('Merma.index');
	Route::resource("/FichaEnsamble", \App\Http\Controllers\FichaEnsambleController::class);
	Route::resource("/FichaEnsambleMaterial", \App\Http\Controllers\FichaEnsambleMaterialController::class);

	// Reglas del sistema explicadas (categoría y subcategoría por query).
	Route::get('/Explicaciones', function (\Illuminate\Http\Request $request) {
		return Inertia::render('Explicaciones/Index', [
			'title' => 'Explicaciones',
			'categoria' => $request->query('categoria'),
			'subcategoria' => $request->query('subcategoria'),
		]);
	})->name('Explicaciones.index');
	Route::resource("/Procesoop", \App\Http\Controllers\ProcesoopController::class);
	//aquipues
}); //fin verified



// <editor-fold desc="Artisan">
require __DIR__.'/ArtisanZilef.php';
require __DIR__.'/Routes_entrega1.php';
