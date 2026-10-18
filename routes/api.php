<?php

declare(strict_types=1);

use App\Http\Controllers\LineController;
use App\Http\Controllers\VehicleController;
use Illuminate\Support\Facades\Route;

// Polled by the map page; no session or cookie, so the answers can be cached (E5.3).
Route::get('/networks/{network}/vehicles', VehicleController::class)->name('vehicles');
Route::get('/networks/{network}/lines', LineController::class)->name('lines');
