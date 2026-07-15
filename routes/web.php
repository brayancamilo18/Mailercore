<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/actions/status', [DashboardController::class, 'jobStatus'])->name('actions.status');
Route::get('/harvest/status', [DashboardController::class, 'harvestStatus'])->name('harvest.status');
Route::post('/leads', [DashboardController::class, 'store'])->name('leads.store');
Route::post('/leads/{lead}/status', [DashboardController::class, 'updateStatus'])->name('leads.status');
Route::post('/actions/search', [DashboardController::class, 'runSearch'])->name('actions.search');
Route::post('/actions/send', [DashboardController::class, 'runSend'])->name('actions.send');
