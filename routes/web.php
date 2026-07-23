<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ColaController;
use App\Http\Controllers\CosechaController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\PanelController;
use App\Http\Controllers\SaludController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'mostrarLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware(['auth', 'throttle:10,1'])
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/', [PanelController::class, 'resumen'])->name('panel.resumen');
    Route::get('/leads', [LeadController::class, 'indice'])->name('leads.indice');
    Route::get('/leads/{lead}', [LeadController::class, 'ficha'])->name('leads.ficha');
    Route::post('/leads/{lead}/estado', [LeadController::class, 'cambiarEstado'])
        ->middleware('throttle:10,1')
        ->name('leads.estado');
    Route::get('/cola', [ColaController::class, 'indice'])->name('cola.indice');
    Route::get('/mensajes/{mensaje}', [ColaController::class, 'ver'])->name('mensajes.ver');
    Route::post('/mensajes/{mensaje}/cancelar', [ColaController::class, 'cancelar'])
        ->middleware('throttle:10,1')
        ->name('mensajes.cancelar');
    Route::get('/salud', [SaludController::class, 'indice'])->name('salud.indice');
    Route::post('/envio/pausar', [SaludController::class, 'pausar'])
        ->middleware('throttle:10,1')
        ->name('envio.pausar');
    Route::post('/envio/reanudar', [SaludController::class, 'reanudar'])
        ->middleware('throttle:10,1')
        ->name('envio.reanudar');
    Route::get('/cosecha', [CosechaController::class, 'indice'])->name('cosecha.indice');
    Route::get('/api/estado', [PanelController::class, 'estadoJson'])->name('api.estado');
});
