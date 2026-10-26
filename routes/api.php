<?php

declare(strict_types=1);

use App\Http\Controllers\DepartureController;
use App\Http\Controllers\LineController;
use App\Http\Controllers\ShapeController;
use App\Http\Controllers\StationController;
use App\Http\Controllers\VehicleController;
use Illuminate\Support\Facades\Route;

// Polled by the map page; no session or cookie, so the answers can be cached (E5.3).
Route::get('/networks/{network}/vehicles', VehicleController::class)->name('vehicles');
Route::get('/networks/{network}/lines', LineController::class)->name('lines');
Route::get('/networks/{network}/shapes', ShapeController::class)->name('shapes');
Route::get('/networks/{network}/stations', StationController::class)->name('stations');
Route::get('/networks/{network}/stations/{station}/departures', DepartureController::class)->name('departures');
