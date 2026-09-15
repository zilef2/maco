<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DesarrolloController;
use App\Http\Controllers\PagodesarrolloController;

Route::resource('/desarrollo', DesarrolloController::class);

Route::post('/desarrollo-notify-delay', [DesarrolloController::class, 'notifyDelay'])
    ->name('desarrollo.notifyDelay');

Route::post('/desarrollo-allow-users', [DesarrolloController::class, 'allowUserAccess'])
    ->name('desarrollo.allowUsers');

Route::resource('/pagodesarrollo', PagodesarrolloController::class);
Route::put('/updatePago/{id}', [DesarrolloController::class, 'updatePago'])->name('updatePago');